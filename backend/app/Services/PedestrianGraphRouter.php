<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use SplPriorityQueue;
use Throwable;

/**
 * A* routing over the pedestrian graph stored in `segmen_jalur` (F7).
 *
 * Edge cost:
 *   fastest: length
 *   safest:  length × (1 + ((100 − skor_wsm) / 100) × score_penalty) × repair_penalty?
 *            + report_penalty_m for an edge inside an active report zone, accepted
 *            when the path passes at most report_tolerance reports; otherwise zone
 *            edges are blocked (or penalised when no clear path fits the detour cap).
 * The heuristic is the great-circle distance to the destination. Every cost is
 * ≥ the edge length and an edge is never shorter than its chord, so the heuristic
 * is admissible and consistent: A* returns the minimum-cost path.
 */
class PedestrianGraphRouter
{
    /** @var array<int, array{id: int, name: ?string, score: float, coords: array<int, array{0: float, 1: float}>, length: float, from: string, to: string}> */
    private array $edges = [];

    /** @var array<string, array<int, array{0: string, 1: int|string, 2: bool}>> node => [neighbour, edge ref, reversed] */
    private array $adjacency = [];

    /** @var array<string, array{0: float, 1: float}> */
    private array $nodes = [];

    /** @var array<string, array{id: int, name: ?string, score: float, coords: array, length: float, from: string, to: string}> */
    private array $virtualEdges = [];

    /** True when the last safest request was covered by the graph but every path entered a report zone. */
    public bool $noClearPath = false;

    public function __construct(private readonly RouteHazardGeometry $geometry) {}

    /**
     * @param  Closure(float): float  $maxDetour  maps the shortest length to the safest-mode cap
     * @return array<string, mixed>|null OSRM-shaped route, or null when the graph cannot serve this trip
     */
    public function route(array $origin, array $destination, string $preference, array $reports, float $radius, Closure $maxDetour): ?array
    {
        $this->noClearPath = false;
        if (! $this->load($origin, $destination, $preference === 'safest')) {
            return null;
        }
        $snapLimit = (float) config('services.routing_graph.snap_m', 100);
        $start = $this->snap($origin);
        $end = $this->snap($destination);
        if ($start === null || $end === null || $start['distance'] > $snapLimit || $end['distance'] > $snapLimit) {
            return null;
        }
        $this->addVirtualNode('O', $start);
        $this->addVirtualNode('D', $end);
        if ($start['edge'] === $end['edge']) {
            $this->addDirectVirtualEdge($start, $end);
        }

        $shortest = $this->search(fn (array $edge): float => $edge['length']);
        if ($shortest === null) {
            return null;
        }
        $maxDistance = $maxDetour($shortest['length']);
        $chosen = $shortest;
        $pass = 'shortest';

        if ($preference !== 'fastest') {
            $penalty = max(0.0, (float) config('services.osrm.score_penalty', 3.0));
            $repairPenalty = max(1.0, (float) config('services.routing_graph.repair_penalty', 1.35));
            $zonePenalty = max(1.0, (float) config('services.routing_graph.report_zone_penalty', 10));
            $repairSegments = array_flip(array_filter(array_map(fn (array $report) => $report['repair'] ? $report['segmen_id'] : null, $reports)));
            $blocking = array_values(array_filter($reports, fn (array $report): bool => $this->geometry->distance($report, $start['point']) > $radius
                && $this->geometry->distance($report, $end['point']) > $radius));
            $wsm = fn (array $edge): float => (1 + ((100 - $edge['score']) / 100) * $penalty) * (isset($repairSegments[$edge['id']]) ? $repairPenalty : 1.0);
            $zone = [];
            $inZone = function (array $edge, int|string $ref) use (&$zone, $blocking, $radius): bool {
                return $zone[$ref] ??= $this->touchesReport($edge['coords'], $blocking, $radius);
            };

            // Only the balanced mode may pass reports; safest blocks every report zone.
            $tolerance = $preference === 'balanced' ? max(0, (int) config('services.osrm.report_tolerance', 3)) : 0;
            $reportPenalty = max(0.0, (float) config('services.osrm.report_penalty_m', 300));
            $passes = [
                'clear' => fn (array $edge, int|string $ref): ?float => $inZone($edge, $ref) ? null : $edge['length'] * $wsm($edge),
                'penalised' => fn (array $edge, int|string $ref): float => $edge['length'] * $wsm($edge) * ($inZone($edge, $ref) ? $zonePenalty : 1.0),
                'wsm' => fn (array $edge): float => $edge['length'] * $wsm($edge),
            ];
            if ($preference === 'safest') {
                // Teraman: report zones are never entered. No clear path → let the next engine try.
                $passes = ['clear' => $passes['clear']];
            } elseif ($tolerance > 0) {
                $passes = ['tolerated' => fn (array $edge, int|string $ref): float => $edge['length'] * $wsm($edge) + ($inZone($edge, $ref) ? $reportPenalty : 0.0)] + $passes;
            }
            foreach ($passes as $name => $edgeCost) {
                $candidate = $this->search($edgeCost);
                $line = $candidate === null ? [] : $this->pathCoordinates($candidate['path']);
                if ($name === 'tolerated' && count($line) >= 2
                    && count($this->geometry->blockingAssessment($line, $reports, $radius)['blocking_points']) > $tolerance) {
                    continue;
                }
                if ($candidate !== null && $candidate['length'] <= $maxDistance + 0.5) {
                    $chosen = $candidate;
                    $pass = $name;
                    break;
                }
            }
            if ($preference === 'safest' && $pass !== 'clear') {
                $this->noClearPath = true;

                return null;
            }
        }

        return $this->toRoute($chosen, $pass, $maxDistance, $start, $end);
    }

    /** Load the graph inside an ellipse-safe box around the trip (5 km margin when the detour is unbounded). */
    private function load(array $origin, array $destination, bool $unbounded = false): bool
    {
        $this->edges = $this->adjacency = $this->nodes = $this->virtualEdges = [];
        $direct = $this->geometry->distance($origin, $destination);
        $ratio = max(1.0, (float) config('services.osrm.safest_max_detour_ratio', 1.5));
        $extra = max(0.0, (float) config('services.osrm.safest_max_detour_extra_m', 500));
        // Any path within the cap stays inside this box around the straight line.
        $padding = $unbounded ? 5000.0 : min(5000.0, max($direct * $ratio, $direct + $extra) / 2 + 200);
        $latPad = $padding / 111195;
        $lngPad = $padding / (111195 * max(0.01, cos(deg2rad(($origin['lat'] + $destination['lat']) / 2))));

        try {
            $rows = DB::select(
                'SELECT segmen_id, nama_jalan, skor_kelayakan, ST_AsGeoJSON(geom::geometry) AS geojson FROM segmen_jalur
                 WHERE ST_Intersects(geom, ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography)',
                [min($origin['lng'], $destination['lng']) - $lngPad, min($origin['lat'], $destination['lat']) - $latPad,
                    max($origin['lng'], $destination['lng']) + $lngPad, max($origin['lat'], $destination['lat']) + $latPad],
            );
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
        if (count($rows) < (int) config('services.routing_graph.min_edges', 50)) {
            return false;
        }

        foreach ($rows as $row) {
            $geometry = json_decode($row->geojson, true);
            if (($geometry['type'] ?? '') !== 'LineString' || count($geometry['coordinates'] ?? []) < 2) {
                continue;
            }
            $coords = array_map(fn (array $c): array => [(float) $c[0], (float) $c[1]], $geometry['coordinates']);
            $length = $this->length($coords);
            if ($length <= 0) {
                continue;
            }
            $from = $this->nodeKey($coords[0]);
            $to = $this->nodeKey($coords[count($coords) - 1]);
            $index = count($this->edges);
            $this->edges[$index] = [
                'id' => (int) $row->segmen_id, 'name' => $row->nama_jalan, 'score' => max(0.0, min(100.0, (float) $row->skor_kelayakan)),
                'coords' => $coords, 'length' => $length, 'from' => $from, 'to' => $to,
            ];
            $this->nodes[$from] = $coords[0];
            $this->nodes[$to] = $coords[count($coords) - 1];
            $this->adjacency[$from][] = [$to, $index, false];
            $this->adjacency[$to][] = [$from, $index, true];
        }

        return true;
    }

    /** Nearest point on any edge. */
    private function snap(array $point): ?array
    {
        $best = null;
        foreach ($this->edges as $index => $edge) {
            $coords = $edge['coords'];
            for ($i = 1; $i < count($coords); $i++) {
                [$distance, $t, $projected] = $this->project($point, $coords[$i - 1], $coords[$i]);
                if ($best === null || $distance < $best['distance']) {
                    $best = ['edge' => $index, 'piece' => $i, 't' => $t, 'distance' => $distance, 'coord' => $projected,
                        'point' => ['lng' => $projected[0], 'lat' => $projected[1]]];
                }
            }
        }

        return $best;
    }

    /** Split the snapped edge into two virtual edges joined at the virtual node. */
    private function addVirtualNode(string $key, array $snap): void
    {
        $edge = $this->edges[$snap['edge']];
        $before = [...array_slice($edge['coords'], 0, $snap['piece']), $snap['coord']];
        $after = [$snap['coord'], ...array_slice($edge['coords'], $snap['piece'])];
        $this->nodes[$key] = $snap['coord'];
        foreach ([[$key.':a', array_reverse($before), $edge['from']], [$key.':b', $after, $edge['to']]] as [$ref, $coords, $node]) {
            if ($this->length($coords) <= 0) {
                $coords = [$snap['coord'], $this->nodes[$node]];
            }
            $this->virtualEdges[$ref] = ['coords' => $coords, 'length' => $this->length($coords), 'from' => $key, 'to' => $node] + $edge;
            $this->adjacency[$key][] = [$node, $ref, false];
            $this->adjacency[$node][] = [$key, $ref, true];
        }
    }

    private function addDirectVirtualEdge(array $start, array $end): void
    {
        $coords = $this->edges[$start['edge']]['coords'];
        $forward = [$start['piece'], $start['t']] <= [$end['piece'], $end['t']];
        [$first, $last] = $forward ? [$start, $end] : [$end, $start];
        $line = [$first['coord'], ...array_slice($coords, $first['piece'], $last['piece'] - $first['piece']), $last['coord']];
        if (! $forward) {
            $line = array_reverse($line);
        }
        $this->virtualEdges['OD'] = ['coords' => $line, 'length' => max(0.01, $this->length($line)), 'from' => 'O', 'to' => 'D'] + $this->edges[$start['edge']];
        $this->adjacency['O'][] = ['D', 'OD', false];
        $this->adjacency['D'][] = ['O', 'OD', true];
    }

    /**
     * A* from O to D. $edgeCost returns the cost of traversing an edge (≥ its length), or null when blocked.
     *
     * @param  Closure(array, int|string): ?float  $edgeCost
     * @return array{path: array<int, array{0: int|string, 1: bool}>, cost: float, length: float}|null
     */
    private function search(Closure $edgeCost): ?array
    {
        $target = $this->nodes['D'];
        $heuristic = fn (string $node): float => $this->haversine($this->nodes[$node], $target);
        $g = ['O' => 0.0];
        $length = ['O' => 0.0];
        $previous = [];
        $closed = [];
        $queue = new SplPriorityQueue;
        $queue->setExtractFlags(SplPriorityQueue::EXTR_DATA);
        $queue->insert('O', -$heuristic('O'));

        while (! $queue->isEmpty()) {
            $node = $queue->extract();
            if (isset($closed[$node])) {
                continue;
            }
            if ($node === 'D') {
                $path = [];
                for ($cursor = 'D'; $cursor !== 'O'; $cursor = $previous[$cursor][0]) {
                    $path[] = [$previous[$cursor][1], $previous[$cursor][2]];
                }

                return ['path' => array_reverse($path), 'cost' => $g['D'], 'length' => $length['D']];
            }
            $closed[$node] = true;
            foreach ($this->adjacency[$node] ?? [] as [$next, $ref, $reversed]) {
                if (isset($closed[$next])) {
                    continue;
                }
                $edge = $this->edge($ref);
                $cost = $edgeCost($edge, $ref);
                if ($cost === null) {
                    continue;
                }
                $candidate = $g[$node] + $cost;
                if ($candidate < ($g[$next] ?? INF)) {
                    $g[$next] = $candidate;
                    $length[$next] = $length[$node] + $edge['length'];
                    $previous[$next] = [$node, $ref, $reversed];
                    $queue->insert($next, -($candidate + $heuristic($next)));
                }
            }
        }

        return null;
    }

    private function edge(int|string $ref): array
    {
        return is_int($ref) ? $this->edges[$ref] : $this->virtualEdges[$ref];
    }

    /** @return array<int, array{0: float, 1: float}> */
    private function pathCoordinates(array $path): array
    {
        $coordinates = [];
        foreach ($path as [$ref, $reversed]) {
            $coords = $this->edge($ref)['coords'];
            foreach ($reversed ? array_reverse($coords) : $coords as $coordinate) {
                if ($coordinates === [] || end($coordinates) !== $coordinate) {
                    $coordinates[] = $coordinate;
                }
            }
        }

        return $coordinates;
    }

    /** Shape the path like an OSRM route so the existing response formatter can be reused. */
    private function toRoute(array $result, string $pass, float $maxDistance, array $start, array $end): array
    {
        $coordinates = [];
        $steps = [];
        $legs = [];
        foreach ($result['path'] as [$ref, $reversed]) {
            $edge = $this->edge($ref);
            $coords = $reversed ? array_reverse($edge['coords']) : $edge['coords'];
            foreach ($coords as $coordinate) {
                if ($coordinates === [] || end($coordinates) !== $coordinate) {
                    $coordinates[] = $coordinate;
                }
            }
            $steps[] = ['name' => $edge['name'] ?? '', 'distance' => $edge['length'], 'geometry' => ['coordinates' => $coords],
                'segmen_id' => $edge['id'], 'score' => $edge['score']];
            for ($i = 1; $i < count($coords); $i++) {
                $from = ['lng' => $coords[$i - 1][0], 'lat' => $coords[$i - 1][1]];
                $to = ['lng' => $coords[$i][0], 'lat' => $coords[$i][1]];
                $legs[] = ['from' => $from, 'to' => $to, 'distance_m' => $this->geometry->distance($from, $to),
                    'street' => $edge['name'] ?: 'Jalur pejalan kaki', 'connector' => false];
            }
        }
        if (count($coordinates) < 2) {
            $coordinates = [$start['coord'], $end['coord']];
        }

        return [
            'distance' => $result['length'], 'duration' => $result['length'] / TurnInstructionService::WALK_SPEED_MPS,
            'geometry' => ['type' => 'LineString', 'coordinates' => $coordinates],
            'legs' => [['steps' => $steps]],
            '_instruction_legs' => $legs, '_engine' => 'postgis_astar', '_graph_pass' => $pass,
            '_graph_cost' => round($result['cost'], 1), '_max_distance' => $maxDistance,
        ];
    }

    private function touchesReport(array $coords, array $reports, float $radius): bool
    {
        foreach ($reports as $report) {
            $point = ['lng' => $report['lng'], 'lat' => $report['lat']];
            for ($i = 1; $i < count($coords); $i++) {
                if ($this->project($point, $coords[$i - 1], $coords[$i])[0] <= $radius) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array{0: float, 1: float, 2: array{0: float, 1: float}} distance (m), t, projected [lng, lat] */
    private function project(array $point, array $a, array $b): array
    {
        $scale = 111195 * cos(deg2rad($point['lat']));
        $ax = ($a[0] - $point['lng']) * $scale;
        $ay = ($a[1] - $point['lat']) * 111195;
        $dx = ($b[0] - $a[0]) * $scale;
        $dy = ($b[1] - $a[1]) * 111195;
        $lengthSquared = $dx * $dx + $dy * $dy;
        $t = $lengthSquared > 0 ? max(0, min(1, -($ax * $dx + $ay * $dy) / $lengthSquared)) : 0;

        return [hypot($ax + $t * $dx, $ay + $t * $dy), $t, [$a[0] + ($b[0] - $a[0]) * $t, $a[1] + ($b[1] - $a[1]) * $t]];
    }

    private function length(array $coords): float
    {
        $total = 0.0;
        for ($i = 1; $i < count($coords); $i++) {
            $total += $this->haversine($coords[$i - 1], $coords[$i]);
        }

        return $total;
    }

    private function haversine(array $a, array $b): float
    {
        return $this->geometry->distance(['lng' => $a[0], 'lat' => $a[1]], ['lng' => $b[0], 'lat' => $b[1]]);
    }

    private function nodeKey(array $coordinate): string
    {
        return sprintf('%.6F,%.6F', $coordinate[0], $coordinate[1]);
    }
}
