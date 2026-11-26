<?php

namespace App\Services;

use App\Exceptions\RouteUnavailableException;

class RouteRecommendationService
{
    public function __construct(
        private readonly OsrmFootClient $osrm,
        private readonly RouteReportRepository $reports,
        private readonly RouteHazardGeometry $geometry,
        private readonly TurnInstructionService $instructions,
        private readonly ResidentialDetourService $residential,
        private readonly RouteSegmentScorer $scorer,
        private readonly PedestrianGraphRouter $graph,
        private readonly ValhallaPedestrianClient $valhalla,
    ) {}

    /** Report tolerance of the current request (0 for safest/fastest). */
    private int $tolerance = 0;

    public function recommend(array $origin, array $destination, string $preference = 'safest'): array
    {
        $origin = ['lat' => (float) $origin['lat'], 'lng' => (float) $origin['lng']];
        $destination = ['lat' => (float) $destination['lat'], 'lng' => (float) $destination['lng']];
        $preference = in_array($preference, ['fastest', 'balanced'], true) ? $preference : 'safest';
        // Teraman never tolerates reports; Balance may pass up to report_tolerance of them.
        $this->tolerance = $preference === 'balanced' ? max(0, (int) config('services.osrm.report_tolerance', 3)) : 0;
        $reports = $this->reports->active();
        $radius = (float) config('services.osrm.avoid_radius_m', 25);

        // Engine order (CFG_ROUTE_ENGINE=auto): F7 A* over the imported pedestrian graph
        // with WSM edge costs → Valhalla pedestrian → OSRM foot as the last resort.
        $engine = (string) config('services.routing_graph.engine', 'auto');
        if (in_array($engine, ['auto', 'graph'], true)) {
            $graphRoute = $this->graph->route($origin, $destination, $preference, $reports, $radius, fn (float $shortest): float => $this->detourCap($preference, $shortest));
            if ($graphRoute !== null) {
                $assessment = $this->geometry->blockingAssessment($graphRoute['geometry']['coordinates'], $reports, $radius);

                return $this->formatResponse($graphRoute, $origin, $destination, $preference, $assessment, $graphRoute['_max_distance']);
            }
            if ($engine === 'graph' && $this->graph->noClearPath) {
                throw $this->noReportFreeRoute();
            }
            if ($engine === 'graph') {
                throw new RouteUnavailableException('PEDESTRIAN_ROUTE_UNAVAILABLE', 'Jaringan jalur pejalan kaki belum mencakup titik awal atau tujuan. Pilih titik di dekat jalan atau jalur pejalan kaki.', 503);
            }
        }
        if (in_array($engine, ['auto', 'valhalla'], true)) {
            $valhallaRoute = $this->valhallaRoute($origin, $destination, $preference, $reports, $radius);
            if ($valhallaRoute !== null) {
                $assessment = $this->geometry->blockingAssessment($valhallaRoute['geometry']['coordinates'], $reports, $radius);

                return $this->formatResponse($valhallaRoute, $origin, $destination, $preference, $assessment, $valhallaRoute['_max_distance']);
            }
        }

        try {
            $routes = $this->validRoutes($this->osrm->routes($origin, $destination), $origin, $destination);
        } catch (RouteUnavailableException $exception) {
            // The graph already proved that every path enters a report zone.
            if ($preference === 'safest' && $engine !== 'osrm' && $this->graph->noClearPath) {
                throw $this->noReportFreeRoute();
            }
            throw $exception;
        }
        if ($routes === []) {
            throw new RouteUnavailableException('PEDESTRIAN_ROUTE_UNAVAILABLE', 'Jalur pejalan kaki yang terhubung belum ditemukan di sekitar titik pilihan. Coba titik pintu masuk atau jalan terdekat.', 503);
        }

        usort($routes, fn (array $a, array $b): int => $this->compareRoutes($a, $b));
        $maxDistance = $this->detourCap($preference, min(array_map(fn (array $route): float => (float) $route['distance'], $routes)));
        $this->loadScoredSegments($origin, $destination, $maxDistance);

        $chosen = $routes[0];
        if ($preference !== 'fastest') {
            $chosen = $this->findSafestAvailableRoute($routes, $origin, $destination, $reports, $radius, $maxDistance);
        }

        $assessment = $this->geometry->blockingAssessment($chosen['geometry']['coordinates'], $reports, $radius);
        if ($preference === 'safest' && $assessment['blocking_points'] !== []) {
            throw $this->noReportFreeRoute(! empty($chosen['_avoidance_search_interrupted']));
        }

        return $this->formatResponse($chosen, $origin, $destination, $preference, $assessment, $maxDistance);
    }

    /**
     * Valhalla pedestrian routing. Fastest is the purely distance-based path
     * (`shortest`). Safest asks Valhalla to avoid every active report zone and
     * to prefer pedestrian infrastructure, then ranks all candidates within the
     * detour cap by blocking reports, exposure and WSM cost. Returns null when
     * Valhalla is unavailable or finds no path, so OSRM can still answer.
     */
    private function valhallaRoute(array $origin, array $destination, string $preference, array $reports, float $radius): ?array
    {
        try {
            $routes = $this->validRoutes($this->valhalla->routes($origin, $destination, ['shortest' => true], [], 0), $origin, $destination);
        } catch (RouteUnavailableException) {
            return null;
        }
        if ($routes === []) {
            return null;
        }
        usort($routes, fn (array $a, array $b): int => $this->compareRoutes($a, $b));
        $maxDistance = $this->detourCap($preference, min(array_map(fn (array $route): float => (float) $route['distance'], $routes)));
        $this->loadScoredSegments($origin, $destination, $maxDistance);
        if ($preference === 'fastest') {
            return $routes[0] + ['_max_distance' => $maxDistance];
        }

        // Iterative exclusion: start with the reports the shortest route actually hits,
        // ask Valhalla to avoid them, and add every further report a new route touches.
        // Reports covering the snapped start/destination are never sent: Valhalla could
        // not reach the first/last road otherwise (error 171).
        $interrupted = false;
        $noPath = false;
        $first = $routes[0]['geometry']['coordinates'][0];
        $last = $routes[0]['geometry']['coordinates'][count($routes[0]['geometry']['coordinates']) - 1];
        $endpoints = [['lng' => $first[0], 'lat' => $first[1]], ['lng' => $last[0], 'lat' => $last[1]], $origin, $destination];
        $excludable = fn (array $report): bool => array_filter($endpoints, fn (array $point): bool => $this->geometry->distance($report, $point) <= $radius + 5) === [];
        $excluded = [];
        foreach ($this->searchAssessment($routes[0], $reports, $radius)['points'] as $point) {
            if ($excludable($point)) {
                $excluded[$point['laporan_id']] = $point;
            }
        }
        $rounds = max(1, (int) config('services.valhalla.exclusion_rounds', 5));
        $limit = (int) config('services.valhalla.max_exclude_polygons', 50);
        for ($round = 0; $round < $rounds; $round++) {
            try {
                $safe = $this->validRoutes($this->valhalla->routes($origin, $destination, $this->safestCosting(),
                    $this->exclusionPolygons(array_slice(array_values($excluded), 0, $limit), $radius), 2), $origin, $destination);
            } catch (RouteUnavailableException) {
                $interrupted = true;
                break;
            }
            if ($safe === []) {
                $noPath = true;
                break;
            }
            $routes = [...$safe, ...$routes];
            $added = false;
            $clear = false;
            foreach ($safe as $route) {
                $points = $this->searchAssessment($route, $reports, $radius)['points'];
                $clear = $clear || $points === [];
                foreach ($points as $point) {
                    if (! isset($excluded[$point['laporan_id']]) && $excludable($point)) {
                        $excluded[$point['laporan_id']] = $point;
                        $added = true;
                    }
                }
            }
            if ($clear || ! $added) {
                break;
            }
        }

        // Balance: Valhalla can only forbid polygons, so the routes above either hit
        // many reports (shortest) or none (Teraman). Probe the middle ground: re-open
        // one report (plus its neighbours within 60 m, e.g. the opposite sidewalk,
        // at most T in total) while still forbidding the rest. Reports closest to the
        // straight origin–destination line are tried first: crossing there saves most.
        if (! $interrupted && $this->reportTolerance() > 0 && $excluded !== []) {
            $routes = [...$this->balanceProbes($origin, $destination, array_values($excluded), $radius, $limit), ...$routes];
        }

        $best = null;
        foreach ($routes as $route) {
            if (! $this->withinDetourCap($route, $maxDistance)) {
                continue;
            }
            $candidate = ['route' => $route, 'assessment' => $this->searchAssessment($route, $reports, $radius), 'waypoints' => []];
            if ($best === null || $this->candidateRank($candidate) < $this->candidateRank($best)) {
                $best = $candidate;
            }
        }
        if ($preference === 'safest' && ($best === null || $best['assessment']['points'] !== [])) {
            // Only "no path while avoiding the reports on every route found" is conclusive.
            // An outage or an unfinished refinement is retried with OSRM.
            if (! $noPath) {
                return null;
            }
            throw $this->noReportFreeRoute();
        }
        $chosen = ($best['route'] ?? $routes[count($routes) - 1]) + ['_max_distance' => $maxDistance];
        if ($interrupted) {
            $chosen['_avoidance_search_interrupted'] = true;
        }

        return $chosen;
    }

    /** Pedestrian costing for the safest mode (Valhalla defaults: walkway/sidewalk 1, alley 2, use_lit 0). */
    private function safestCosting(): array
    {
        return [
            'walkway_factor' => (float) config('services.valhalla.safest.walkway_factor', 0.8),
            'sidewalk_factor' => (float) config('services.valhalla.safest.sidewalk_factor', 0.8),
            'alley_factor' => (float) config('services.valhalla.safest.alley_factor', 1.0),
            'use_lit' => (float) config('services.valhalla.safest.use_lit', 0.5),
            'use_living_streets' => (float) config('services.valhalla.safest.use_living_streets', 0.8),
        ];
    }

    /**
     * Valhalla routes that may pass a small group (≤ tolerance) of the excluded reports.
     *
     * @return array<int, array<string, mixed>>
     */
    private function balanceProbes(array $origin, array $destination, array $excluded, float $radius, int $limit): array
    {
        $line = [[$origin['lng'], $origin['lat']], [$destination['lng'], $destination['lat']]];
        $offLine = fn (array $report): float => $this->geometry->assess($line, [$report], 1e7)['points'][0]['distance_to_route_m'] ?? INF;
        usort($excluded, fn (array $a, array $b): int => $offLine($a) <=> $offLine($b));

        $routes = [];
        $seen = [];
        $probes = max(0, (int) config('services.valhalla.balance_probes', 6));
        foreach ($excluded as $report) {
            if ($probes === 0) {
                break;
            }
            $allowed = array_filter($excluded, fn (array $other): bool => $this->geometry->distance($report, $other) <= 60);
            $ids = array_column($allowed, 'laporan_id');
            sort($ids);
            if (count($ids) > $this->reportTolerance() || isset($seen[implode(',', $ids)])) {
                continue;
            }
            $seen[implode(',', $ids)] = true;
            $probes--;
            $forbidden = array_values(array_filter($excluded, fn (array $other): bool => ! in_array($other['laporan_id'], $ids, true)));
            try {
                $found = $this->valhalla->routes($origin, $destination, $this->safestCosting(),
                    $this->exclusionPolygons(array_slice($forbidden, 0, $limit), $radius), 0);
            } catch (RouteUnavailableException) {
                break;
            }
            $routes = [...$routes, ...$this->validRoutes($found, $origin, $destination)];
        }

        return $routes;
    }

    /**
     * Circumscribed 12-gons (radius + 2 m margin) around the given reports.
     *
     * @return array<int, array<int, array{0: float, 1: float}>>
     */
    private function exclusionPolygons(array $reports, float $radius): array
    {
        $circumradius = ($radius + 2) / cos(M_PI / 12);
        $polygons = [];
        foreach ($reports as $report) {
            $ring = [];
            for ($k = 0; $k <= 12; $k++) {
                $point = $this->geometry->offset($report, $k % 12 * 30, $circumradius);
                $ring[] = [round($point['lng'], 7), round($point['lat'], 7)];
            }
            $polygons[] = $ring;
        }

        return $polygons;
    }

    /** Teraman never passes an active report, however long the detour; Balance keeps the cap. */
    private function detourCap(string $preference, float $shortestDistance): float
    {
        return $preference === 'safest' ? INF : $this->maxDetourDistance($shortestDistance);
    }

    private function noReportFreeRoute(bool $interrupted = false): RouteUnavailableException
    {
        return new RouteUnavailableException('NO_REPORT_FREE_ROUTE', $interrupted
            ? 'Pencarian rute teraman terhenti karena layanan routing terganggu sebelum jalur tanpa titik laporan ditemukan. Coba lagi beberapa saat, atau pilih mode Balance/Tercepat.'
            : 'Semua jalur pejalan kaki menuju tujuan ini melewati titik laporan aktif, jadi rute teraman tidak tersedia. Mode Balance atau Tercepat tetap bisa menampilkan rute dengan penanda titik laporan.', 422);
    }

    /**
     * Longest walking distance the safest mode may choose. Avoiding a report is
     * only worthwhile while the detour stays reasonable for a pedestrian.
     */
    private function maxDetourDistance(float $shortestDistance): float
    {
        $ratio = max(1.0, (float) config('services.osrm.safest_max_detour_ratio', 1.5));
        $extra = max(0.0, (float) config('services.osrm.safest_max_detour_extra_m', 500));

        return max($shortestDistance * $ratio, $shortestDistance + $extra);
    }

    /** Load scored segments covering every route that can fit inside the detour cap. */
    private function loadScoredSegments(array $origin, array $destination, float $maxDistance): void
    {
        $direct = $this->geometry->distance($origin, $destination);
        $padding = min(3000.0, max(100.0, ($maxDistance - $direct) / 2 + 50));
        $latPadding = $padding / 111195;
        $lngPadding = $padding / (111195 * max(0.01, cos(deg2rad(($origin['lat'] + $destination['lat']) / 2))));
        $this->scorer->load(
            min($origin['lng'], $destination['lng']) - $lngPadding,
            min($origin['lat'], $destination['lat']) - $latPadding,
            max($origin['lng'], $destination['lng']) + $lngPadding,
            max($origin['lat'], $destination['lat']) + $latPadding,
        );
    }

    /** WSM-weighted walking cost; lower is safer per metre walked. */
    private function safetyCost(array $route): float
    {
        return $this->scorer->cost(
            $route['geometry']['coordinates'],
            max(0.0, (float) config('services.osrm.score_penalty', 3.0)),
            max(0.0, min(100.0, (float) config('services.osrm.unscored_score', 70))),
        );
    }

    private function withinDetourCap(array $route, float $maxDistance): bool
    {
        return (float) $route['distance'] <= $maxDistance + 0.5;
    }

    /**
     * Drop waypoint sets whose straight-line path already exceeds the detour cap
     * (OSRM can only make them longer) and try the shortest bypasses first.
     */
    private function rankWaypointSets(array $sets, array $origin, array $destination, float $maxDistance): array
    {
        $ranked = [];
        foreach ($sets as $waypoints) {
            $points = [$origin, ...$waypoints, $destination];
            $estimate = 0.0;
            for ($i = 1; $i < count($points); $i++) {
                $estimate += $this->geometry->distance($points[$i - 1], $points[$i]);
            }
            if ($estimate <= $maxDistance) {
                $ranked[] = ['waypoints' => $waypoints, 'estimate' => $estimate];
            }
        }
        usort($ranked, fn (array $a, array $b): int => $a['estimate'] <=> $b['estimate']);

        return array_column($ranked, 'waypoints');
    }

    /**
     * Search for clear walking detours within the detour cap. When complete
     * avoidance is unavailable, retain the lowest-exposure valid foot candidate
     * that still fits the cap. The shortest candidate always fits.
     */
    private function findSafestAvailableRoute(array $routes, array $origin, array $destination, array $reports, float $radius, float $maxDistance): array
    {
        $frontier = [];
        $acceptable = [];
        $best = null;
        foreach ($routes as $route) {
            if (! $this->withinDetourCap($route, $maxDistance)) {
                continue;
            }
            $assessment = $this->searchAssessment($route, $reports, $radius);
            $candidate = ['route' => $route, 'assessment' => $assessment, 'waypoints' => []];
            if ($best === null || $this->candidateRank($candidate) < $this->candidateRank($best)) {
                $best = $candidate;
            }
            if ($assessment['points'] === []) {
                $route['_endpoint_exposure_m'] = $assessment['endpoint_exposure_m'];
                $acceptable[] = $route;
            } else {
                $frontier[] = $candidate;
            }
        }
        // A report-free candidate exists; with a report tolerance a cheaper
        // candidate passing ≤ T reports may still rank first.
        if ($acceptable !== []) {
            return $best['route'];
        }

        $seenQueries = [];
        $seenRoutes = [];
        $requests = 0;
        $residentialSets = $this->residential->waypointSets($origin, $destination, $reports, $radius);
        $seedResidential = true;
        $budget = (int) config('services.osrm.search_requests', 48);
        if (rtrim((string) config('services.osrm.url'), '/') === 'https://routing.openstreetmap.de/routed-foot/route/v1/foot') {
            $budget = min($budget, (int) config('services.osrm.public_search_requests', 16));
        }
        while ($frontier !== [] && $requests < $budget) {
            usort($frontier, fn (array $a, array $b): int => $this->candidateRank($a) <=> $this->candidateRank($b));
            $current = array_shift($frontier);
            if (count($current['waypoints']) >= 6) {
                continue;
            }

            $report = $current['assessment']['points'][0];
            $bearing = $this->routeBearingAt($current['route']['geometry']['coordinates'], $report, $radius);
            $sets = [];
            if ($seedResidential) {
                foreach ($residentialSets as $waypoints) {
                    $seenQueries[json_encode($waypoints)] = true;
                    $sets[] = $waypoints;
                }
                $seedResidential = false;
            }
            foreach ($this->nextJunctionBypasses($current['route'], $report, $radius) as $bypass) {
                if (count(array_filter($bypass, fn (array $point): bool => $this->insideReport($point, $reports, $radius))) > 0) {
                    continue;
                }
                $waypoints = $this->insertWaypointSequence($origin, $destination, $current['waypoints'], $bypass);
                $key = json_encode($waypoints);
                if (count($waypoints) <= 6 && ! isset($seenQueries[$key])) {
                    $seenQueries[$key] = true;
                    $sets[] = $waypoints;
                }
            }
            $hasJunctionBypasses = $sets !== [];
            foreach ($hasJunctionBypasses ? [80.0] : [80.0, 200.0] as $offset) {
                foreach ([-90.0, 90.0] as $side) {
                    $waypoint = $this->geometry->offset($report, $bearing + $side, $offset);
                    if ($this->insideReport($waypoint, $reports, $radius)) {
                        continue;
                    }
                    $waypoints = $this->insertWaypoint($origin, $destination, $current['waypoints'], $waypoint);
                    $key = json_encode($waypoints);
                    if (! isset($seenQueries[$key])) {
                        $seenQueries[$key] = true;
                        $sets[] = $waypoints;
                    }
                }
            }

            // Bypass the whole report cluster, rather than repeatedly nudging a
            // single point on the same blocked street. These may be long routes.
            $blocked = $current['assessment']['points'];
            $centroid = [
                'lat' => array_sum(array_column($blocked, 'lat')) / count($blocked),
                'lng' => array_sum(array_column($blocked, 'lng')) / count($blocked),
            ];
            $corridorBearing = rad2deg(atan2(($destination['lng'] - $origin['lng']) * cos(deg2rad($centroid['lat'])), $destination['lat'] - $origin['lat']));
            foreach ($hasJunctionBypasses ? [3500.0] : [1500.0, 3500.0] as $offset) {
                foreach ([-90.0, 90.0] as $side) {
                    $bypass = $offset < 3000
                        ? [$this->geometry->offset($centroid, $corridorBearing + $side, $offset)]
                        : [$this->geometry->offset($origin, $corridorBearing + $side, $offset), $this->geometry->offset($destination, $corridorBearing + $side, $offset)];
                    foreach ($bypass as $waypoint) {
                        if ($this->insideReport($waypoint, $reports, $radius)) {
                            continue 2;
                        }
                    }
                    $waypoints = $this->insertWaypointSequence($origin, $destination, $current['waypoints'], $bypass);
                    $key = json_encode($waypoints);
                    if (count($waypoints) <= 6 && ! isset($seenQueries[$key])) {
                        $seenQueries[$key] = true;
                        $sets[] = $waypoints;
                    }
                }
            }

            $sets = array_slice($this->rankWaypointSets($sets, $origin, $destination, $maxDistance), 0, $budget - $requests);
            if ($sets === []) {
                continue;
            }
            $requests += count($sets);
            $safe = [];
            // Keep each result associated with its waypoint sequence for multi-report detours.
            foreach (array_chunk($sets, 8) as $batch) {
                try {
                    $batchRoutes = $this->osrm->routes($origin, $destination, $batch);
                } catch (RouteUnavailableException $exception) {
                    if ($exception->reason !== 'PEDESTRIAN_ENGINE_UNAVAILABLE') {
                        throw $exception;
                    }
                    $best['route']['_avoidance_search_interrupted'] = true;

                    return $best['route'];
                }
                foreach ($this->validRoutes($batchRoutes, $origin, $destination) as $route) {
                    $hash = md5(json_encode($route['geometry']['coordinates']));
                    if (isset($seenRoutes[$hash]) || ! $this->withinDetourCap($route, $maxDistance)) {
                        continue;
                    }
                    $seenRoutes[$hash] = true;
                    $assessment = $this->searchAssessment($route, $reports, $radius);
                    $candidate = ['route' => $route, 'assessment' => $assessment, 'waypoints' => $route['_waypoints'] ?? []];
                    if ($this->candidateRank($candidate) < $this->candidateRank($best)) {
                        $best = $candidate;
                    }
                    if ($assessment['points'] === []) {
                        $route['_endpoint_exposure_m'] = $assessment['endpoint_exposure_m'];
                        $safe[] = $route;
                    } else {
                        $frontier[] = $candidate;
                    }
                }
                if ($safe !== []) {
                    return $best['route'];
                }
            }
        }

        return $best['route'];
    }

    /**
     * Safest ranking. With report tolerance T and per-report cost P:
     *   1. reports beyond the tolerance, max(0, n − T)  (fewer is always better)
     *   2. unavoidable start/destination exposure
     *   3. WSM cost + min(n, T) × P                     (passing a report must save > P)
     *   4. distance
     * T = 0 reproduces strict avoidance: fewest reports, then exposure, then WSM cost.
     */
    private function candidateRank(array $candidate): array
    {
        $count = count($candidate['assessment']['points']);
        $tolerance = $this->reportTolerance();

        return [
            max(0, $count - $tolerance),
            round($tolerance === 0 ? $candidate['assessment']['endpoint_exposure_m'] : $candidate['assessment']['unavoidable_exposure_m'], 1),
            $this->safetyCost($candidate['route']) + min($count, $tolerance) * $this->reportPenalty(),
            $candidate['route']['distance'],
        ];
    }

    /** Maximum active reports the balanced route may pass when avoiding them would detour too far. */
    private function reportTolerance(): int
    {
        return $this->tolerance;
    }

    /** WSM-weighted metres a pedestrian is assumed to accept as detour to avoid one report. */
    private function reportPenalty(): float
    {
        return max(0.0, (float) config('services.osrm.report_penalty_m', 300));
    }

    private function searchAssessment(array $route, array $reports, float $radius): array
    {
        $assessment = $this->geometry->blockingAssessment($route['geometry']['coordinates'], $reports, $radius);
        $assessment['endpoint_exposure_m'] = array_sum(array_column($assessment['sections'], 'distance_m'));
        $unavoidable = array_column(array_filter($assessment['points'], fn (array $point): bool => $point['endpoint_unavoidable']), 'laporan_id');
        $assessment['unavoidable_exposure_m'] = array_sum(array_column(array_filter($assessment['sections'],
            fn (array $section): bool => array_diff($section['report_ids'], $unavoidable) === []), 'distance_m'));
        $assessment['points'] = $assessment['blocking_points'];

        return $assessment;
    }

    private function insideReport(array $point, array $reports, float $radius): bool
    {
        foreach ($reports as $report) {
            if ($this->geometry->distance($point, $report) <= $radius) {
                return true;
            }
        }

        return false;
    }

    private function routeBearingAt(array $coordinates, array $report, float $radius): float
    {
        for ($i = 1; $i < count($coordinates); $i++) {
            if ($this->geometry->assess([$coordinates[$i - 1], $coordinates[$i]], [$report], $radius)['points'] !== []) {
                $a = $coordinates[$i - 1];
                $b = $coordinates[$i];

                return rad2deg(atan2(($b[0] - $a[0]) * cos(deg2rad($report['lat'])), $b[1] - $a[1]));
            }
        }

        return 0;
    }

    private function insertWaypoint(array $origin, array $destination, array $waypoints, array $point): array
    {
        return $this->insertWaypointSequence($origin, $destination, $waypoints, [$point]);
    }

    /** Preserve the order of "continue straight, then turn" anchors. */
    private function insertWaypointSequence(array $origin, array $destination, array $waypoints, array $sequence): array
    {
        $points = [$origin, ...$waypoints, $destination];
        $best = INF;
        $position = 0;
        for ($i = 1; $i < count($points); $i++) {
            $block = [$points[$i - 1], ...$sequence, $points[$i]];
            $cost = -$this->geometry->distance($points[$i - 1], $points[$i]);
            for ($j = 1; $j < count($block); $j++) {
                $cost += $this->geometry->distance($block[$j - 1], $block[$j]);
            }
            if ($cost < $best) {
                $best = $cost;
                $position = $i - 1;
            }
        }
        array_splice($waypoints, $position, 0, $sequence);

        return $waypoints;
    }

    /** Try the next block on either side before falling back to kilometre-wide detours. */
    private function nextJunctionBypasses(array $route, array $report, float $radius): array
    {
        $steps = array_merge(...array_map(fn (array $leg): array => $leg['steps'] ?? [], $route['legs'] ?? []));
        foreach ($steps as $index => $step) {
            if ($this->geometry->assess($step['geometry']['coordinates'] ?? [], [$report], $radius)['points'] === []) {
                continue;
            }
            $junctions = [];
            for ($j = $index; $j >= 1; $j--) {
                $maneuver = $steps[$j]['maneuver'] ?? [];
                if (! isset($maneuver['location'], $maneuver['bearing_before'], $maneuver['bearing_after'])) {
                    continue;
                }
                $delta = abs($maneuver['bearing_before'] - $maneuver['bearing_after']);
                $delta = min($delta, 360 - $delta);
                $junction = ['lat' => $maneuver['location'][1], 'lng' => $maneuver['location'][0]];
                $distance = $this->geometry->distance($junction, $report);
                if ($delta < 25 || $delta > 155 || $distance > 600) {
                    continue;
                }
                $junctions[] = ['point' => $junction, 'bearing' => $maneuver['bearing_before'], 'distance' => $distance];
                if (count($junctions) === 2) {
                    break;
                }
            }
            $junctions = array_reverse($junctions);
            $bypasses = [];
            // A hazard may be two turns ahead. Staying on the approach street
            // can avoid it without ever entering the affected main road.
            foreach ([80.0, 180.0, 300.0] as $ahead) {
                foreach ($junctions as $junction) {
                    $bypasses[] = [$this->geometry->offset($junction['point'], $junction['bearing'], $ahead)];
                }
            }
            foreach ($junctions as $junction) {
                $straight = $this->geometry->offset($junction['point'], $junction['bearing'], 180);
                foreach ([-90.0, 90.0] as $side) {
                    $turn = $this->geometry->offset($straight, $junction['bearing'] + $side, min(300, max(100, $junction['distance'] + 75)));
                    $bypasses[] = [$straight, $turn];
                }
            }

            return array_slice($bypasses, 0, 8);

        }

        return [];
    }

    private function compareRoutes(array $a, array $b): int
    {
        return [$a['duration'] ?? $a['distance'] / 1.25, $a['distance']]
            <=> [$b['duration'] ?? $b['distance'] / 1.25, $b['distance']];
    }

    private function validRoutes(array $routes, array $origin, array $destination): array
    {
        $maxSnap = (float) config('services.osrm.max_snap_m', 40);

        return array_values(array_filter($routes, function (array $route) use ($origin, $destination, $maxSnap): bool {
            $coordinates = $route['geometry']['coordinates'];
            $first = $coordinates[0];
            $last = $coordinates[count($coordinates) - 1];

            return $this->geometry->distance($origin, ['lat' => $first[1], 'lng' => $first[0]]) <= $maxSnap
                && $this->geometry->distance($destination, ['lat' => $last[1], 'lng' => $last[0]]) <= $maxSnap;
        }));
    }

    private function formatResponse(array $route, array $origin, array $destination, string $preference, array $assessment, float $maxDistance): array
    {
        $coordinates = $route['geometry']['coordinates'];
        $polyline = array_map(fn (array $point): array => [(float) $point[1], (float) $point[0]], $coordinates);
        $distance = (float) $route['distance'];
        $duration = (int) round($route['duration'] ?? $distance / TurnInstructionService::WALK_SPEED_MPS);
        $segments = $this->routeSegments($route);
        $known = array_filter($segments, fn (array $segment): bool => $segment['score'] !== null);
        $knownDistance = array_sum(array_column($known, 'distance_m'));
        $weighted = array_sum(array_map(fn (array $segment): float => $segment['score'] * $segment['distance_m'], $known));
        $average = $knownDistance > 0 ? round($weighted / $knownDistance, 1) : null;
        $repairDistance = array_sum(array_column(array_filter($assessment['sections'], fn (array $section): bool => $section['repair']), 'distance_m'));
        $hazardDistance = array_sum(array_column($assessment['sections'], 'distance_m'));
        $remainingReports = count($assessment['blocking_points']);
        $saferFallback = $preference !== 'fastest' && $remainingReports > 0;
        $warnings = [];
        if ($assessment['points'] !== []) {
            $warnings[] = ['code' => 'REPORT_ON_ROUTE', 'message' => $preference !== 'fastest' && ! $saferFallback
                ? 'Laporan berada di area awal/tujuan. Bagian keluar atau masuk area tersebut tidak dapat dihindari dan ditandai garis putus-putus; laporan lain di sepanjang perjalanan dihindari.'
                : 'Rute melewati '.count($assessment['points']).' titik laporan aktif. Perhatikan bagian jalur bergaris putus-putus.'];
        }
        if ($saferFallback && $remainingReports <= $this->reportTolerance()) {
            $warnings[] = ['code' => 'SAFEST_AVAILABLE_WITH_REPORTS', 'message' => 'Rute balance melewati '.$remainingReports.' titik laporan (toleransi maks. '.$this->reportTolerance().' titik): memutar untuk menghindarinya menambah jarak lebih dari '.round($this->reportPenalty()).' m per titik atau melebihi batas '.number_format($maxDistance / 1000, 1, ',', '.').' km. Perhatikan garis oranye putus-putus.'];
        } elseif ($saferFallback) {
            $warnings[] = ['code' => 'SAFEST_AVAILABLE_WITH_REPORTS', 'message' => 'Rute bebas laporan dengan jarak memutar yang wajar (maks. '.number_format($maxDistance / 1000, 1, ',', '.').' km) belum ditemukan. Rute pejalan kaki dengan paparan laporan terendah ditampilkan; masih ada '.$remainingReports.' titik masalah di sepanjang perjalanan. Perhatikan garis oranye putus-putus.'];
        }
        if (! empty($route['_avoidance_search_interrupted'])) {
            $warnings[] = ['code' => 'AVOIDANCE_SEARCH_INTERRUPTED', 'message' => 'Pencarian alternatif terhenti karena layanan routing terganggu. Jalur pejalan kaki yang sudah ditemukan tetap ditampilkan dengan laporan aktifnya.'];
        }
        if ($repairDistance > 0) {
            $warnings[] = ['code' => 'ACTIVE_REPAIR', 'message' => 'Sebagian jalur sedang diperbaiki.'];
        }
        if ($average !== null && $average < 70) {
            $warnings[] = ['code' => 'LOW_FEASIBILITY', 'message' => 'Sebagian kondisi jalur memerlukan perhatian.'];
        }
        $snappedOrigin = ['lat' => (float) $coordinates[0][1], 'lng' => (float) $coordinates[0][0]];
        $end = $coordinates[count($coordinates) - 1];
        $snappedDestination = ['lat' => (float) $end[1], 'lng' => (float) $end[0]];
        $originOffset = round($this->geometry->distance($origin, $snappedOrigin), 1);
        $destinationOffset = round($this->geometry->distance($destination, $snappedDestination), 1);
        if ($originOffset > 40 || $destinationOffset > 40) {
            $warnings[] = ['code' => 'ENDPOINT_SNAPPED', 'message' => 'Rute mengikuti jalur pejalan kaki terdekat: awal '.round($originOffset).' m dan tujuan '.round($destinationOffset).' m dari pin pilihan. Akses dari pin ke jalur belum termasuk dalam rute.'];
        }

        return [
            'route_id' => 'RTE-'.strtoupper(bin2hex(random_bytes(4))),
            'engine' => $route['_engine'] ?? 'osrm_foot', 'preference' => $preference,
            'origin' => $origin, 'destination' => $destination,
            'snapped_origin' => $snappedOrigin, 'snapped_destination' => $snappedDestination,
            'snap_distances_m' => ['origin' => $originOffset, 'destination' => $destinationOffset],
            'distance_m' => round($distance, 1), 'duration_min' => max(1, (int) ceil($duration / 60)),
            'duration_s' => $duration, 'walk_speed_mps' => $duration > 0 ? round($distance / $duration, 2) : 1.25,
            'polyline' => $polyline, 'segments' => $segments, 'steps' => $this->routeSteps($route, $origin, $destination),
            'summary' => [
                'average_score' => $average,
                'minimum_score' => $known === [] ? null : min(array_column($known, 'score')),
                'repair_distance_m' => round($repairDistance, 1),
                'hazard_distance_m' => round($hazardDistance, 1),
                'safe_distance_m' => round(array_sum(array_column(array_filter($known, fn (array $segment): bool => $segment['score'] >= 70), 'distance_m')), 1),
                'score_coverage' => min(1, round($knownDistance / max(1, $distance), 3)),
                'total_segments' => count($segments), 'fallback' => false,
                'report_count' => count($assessment['points']),
                'endpoint_report_count' => count(array_filter($assessment['points'], fn (array $point): bool => $point['endpoint_unavoidable'])),
                'remaining_report_count' => $remainingReports,
                'safer_fallback' => $saferFallback,
                'report_tolerance' => $preference === 'fastest' ? null : $this->reportTolerance(),
                'report_free' => $assessment['points'] === [],
            ],
            'hazard_sections' => $assessment['sections'],
            'colliding_points' => $assessment['points'], 'obstacles' => $assessment['points'], 'avoided_points' => [],
            'warnings' => $warnings,
            'meta' => [
                'considered_segments' => count($segments), 'connector_radius_m' => null,
                'score_penalty_factor' => $preference !== 'fastest' ? (float) config('services.osrm.score_penalty', 3.0) : 0, 'repair_penalty_factor' => 0,
                'max_detour_distance_m' => $preference === 'fastest' || ! is_finite($maxDistance) ? null : round($maxDistance, 1),
                'avoid_radius_m' => (float) config('services.osrm.avoid_radius_m'),
                'strict_report_avoidance' => $preference !== 'fastest' && $assessment['points'] === [],
                'endpoint_zone_exception' => $preference !== 'fastest' && count(array_filter($assessment['points'], fn (array $point): bool => $point['endpoint_unavoidable'])) > 0,
                'avoidance_search_interrupted' => ! empty($route['_avoidance_search_interrupted']), 'profile' => 'foot',
                'algorithm' => match ($route['_engine'] ?? 'osrm_foot') {
                    'postgis_astar' => 'astar_wsm', 'valhalla_pedestrian' => 'valhalla_wsm', default => 'osrm_candidates',
                },
                'graph_pass' => $route['_graph_pass'] ?? null, 'graph_cost' => $route['_graph_cost'] ?? null,
                'provider_cache' => $route['_provider_cache'] ?? 'miss',
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function routeSegments(array $route): array
    {
        $hasSegmentIds = array_filter(array_merge(...array_map(fn (array $leg): array => $leg['steps'] ?? [], $route['legs'] ?? [])), fn (array $step): bool => array_key_exists('segmen_id', $step)) !== [];
        if (! $this->scorer->isLoaded() && ! $hasSegmentIds) {
            $coordinates = $route['geometry']['coordinates'];
            $latitudes = array_column($coordinates, 1);
            $longitudes = array_column($coordinates, 0);
            $this->scorer->load(min($longitudes), min($latitudes), max($longitudes), max($latitudes));
        }
        $segments = [];
        $rawSteps = array_merge(...array_map(fn (array $leg): array => $leg['steps'] ?? [], $route['legs'] ?? []));
        if ($rawSteps === []) {
            $rawSteps = [['name' => 'Jalur pejalan kaki', 'distance' => $route['distance'], 'geometry' => $route['geometry']]];
        }
        foreach ($rawSteps as $step) {
            $line = $step['geometry']['coordinates'] ?? [];
            if (count($line) < 2 || ($step['distance'] ?? 0) <= 0) {
                continue;
            }
            // Score each geometric edge, not an entire street based on a turn location.
            $geometricLength = 0;
            for ($i = 1; $i < count($line); $i++) {
                $geometricLength += $this->geometry->distance(['lng' => $line[$i - 1][0], 'lat' => $line[$i - 1][1]], ['lng' => $line[$i][0], 'lat' => $line[$i][1]]);
            }
            for ($i = 1; $i < count($line); $i++) {
                $a = $line[$i - 1];
                $b = $line[$i];
                $matched = array_key_exists('segmen_id', $step)
                    ? ['id' => $step['segmen_id'], 'score' => $step['score']]
                    : $this->scorer->match([($a[0] + $b[0]) / 2, ($a[1] + $b[1]) / 2]);
                $length = $this->geometry->distance(['lng' => $a[0], 'lat' => $a[1]], ['lng' => $b[0], 'lat' => $b[1]]);
                $score = isset($matched['score']) ? max(0, min(100, $matched['score'])) : null;
                $segments[] = [
                    'segmen_id' => $matched['id'] ?? null, 'name' => ($step['name'] ?? '') ?: 'Jalur pejalan kaki',
                    'distance_m' => round($geometricLength > 0 ? $length / $geometricLength * $step['distance'] : 0, 1),
                    'score' => $score, 'tingkat' => $score === null ? 'BELUM_DINILAI' : ($score >= 70 ? 'SANGAT_LAYAK' : ($score >= 40 ? 'PERLU_PERHATIAN' : 'TIDAK_LAYAK')),
                    'repair' => false, 'connector' => false, 'polyline' => [[$a[1], $a[0]], [$b[1], $b[0]]],
                ];
            }
        }

        return $segments;
    }

    private function routeSteps(array $route, array $origin, array $destination): array
    {
        if (isset($route['_instruction_legs'])) {
            return $this->instructions->build($route['_instruction_legs'], $origin, $destination);
        }
        $steps = [];
        $cumulative = 0.0;
        foreach ($route['legs'] ?? [] as $leg) {
            foreach ($leg['steps'] ?? [] as $raw) {
                $maneuver = $raw['maneuver'] ?? [];
                $type = $maneuver['type'] ?? 'continue';
                // Intermediate OSRM waypoint arrivals are not the trip destination.
                if ($type === 'arrive') {
                    continue;
                }
                $street = trim($raw['name'] ?? '');
                $modifier = $maneuver['modifier'] ?? 'straight';
                $instruction = match ($modifier) {
                    'left', 'sharp left', 'slight left' => 'Belok kiri',
                    'right', 'sharp right', 'slight right' => 'Belok kanan',
                    'uturn' => 'Putar balik',
                    default => 'Lanjutkan lurus',
                };
                if ($steps === []) {
                    $instruction = 'Mulai perjalanan';
                }
                if ($type === 'roundabout') {
                    $instruction = 'Di bundaran, ambil jalan keluar '.($maneuver['exit'] ?? '');
                }
                $location = $maneuver['location'] ?? [$origin['lng'], $origin['lat']];
                $steps[] = [
                    'index' => count($steps), 'type' => $steps === [] ? 'depart' : 'turn',
                    'maneuver' => $type, 'modifier' => $modifier,
                    'instruction' => $instruction.($street !== '' ? ' di '.$street : '').'.', 'street' => $street ?: null,
                    'distance_m' => (float) ($raw['distance'] ?? 0),
                    'distance_from_start_m' => round($cumulative, 1),
                    'remaining_distance_m' => round(max(0, $route['distance'] - $cumulative), 1),
                    'duration_s' => (int) round($raw['duration'] ?? ($raw['distance'] ?? 0) / 1.25),
                    'location' => ['lat' => $location[1], 'lng' => $location[0]],
                    'bearing_before' => $maneuver['bearing_before'] ?? 0, 'bearing_after' => $maneuver['bearing_after'] ?? 0,
                ];
                $cumulative += $raw['distance'] ?? 0;
            }
        }
        if ($steps === []) {
            $legs = [];
            $coordinates = $route['geometry']['coordinates'];
            for ($i = 1; $i < count($coordinates); $i++) {
                $from = ['lng' => $coordinates[$i - 1][0], 'lat' => $coordinates[$i - 1][1]];
                $to = ['lng' => $coordinates[$i][0], 'lat' => $coordinates[$i][1]];
                $legs[] = ['from' => $from, 'to' => $to, 'distance_m' => $this->geometry->distance($from, $to), 'street' => 'Jalur pejalan kaki', 'connector' => false];
            }

            return $this->instructions->build($legs, $origin, $destination);
        }
        $steps[] = [
            'index' => count($steps), 'type' => 'arrive', 'maneuver' => 'arrive', 'modifier' => 'straight',
            'instruction' => 'Anda telah tiba di tujuan.', 'street' => null, 'distance_m' => 0,
            'distance_from_start_m' => (float) $route['distance'], 'remaining_distance_m' => 0, 'duration_s' => 0,
            'location' => $destination, 'bearing_before' => 0, 'bearing_after' => 0,
        ];

        return $steps;
    }
}
