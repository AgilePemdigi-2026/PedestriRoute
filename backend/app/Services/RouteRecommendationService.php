<?php

namespace App\Services;

use App\Support\MessageCatalog;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Recommends a walking route between two coordinates using the PostGIS
 * pedestrian network and an A* search with dynamic Weighted Sum Model (WSM)
 * penalties.
 *
 * Every candidate segment already carries the feasibility score produced by
 * {@see SegmentScoreService}. The score is converted into a routing penalty so
 * that a "safest" route prefers well-maintained footpaths, while a "fastest"
 * route minimises walking distance. Segments that are currently being repaired
 * receive an additional penalty.
 *
 * The service deliberately keeps a corridor/direct fallback because the OSM
 * extract may be incomplete on a fresh installation.
 */
class RouteRecommendationService
{
    private const EARTH_RADIUS_M = 6371000.0;

    private const WALK_SPEED_MPS = 1.25;

    private const MAX_SEGMENTS = 500;

    private const CONNECTOR_RADII = [20.0, 40.0, 80.0, 160.0, 300.0];

    private const MAX_SNAP_DISTANCE_M = 900.0;

    /**
     * @param  array{lat: float, lng: float}  $origin
     * @param  array{lat: float, lng: float}  $destination
     * @return array<string, mixed>
     */
    public function recommend(array $origin, array $destination, string $preference = 'safest'): array
    {
        $origin = ['lat' => (float) $origin['lat'], 'lng' => (float) $origin['lng']];
        $destination = ['lat' => (float) $destination['lat'], 'lng' => (float) $destination['lng']];
        $preference = $preference === 'fastest' ? 'fastest' : 'safest';

        $segments = $this->loadSegments($origin, $destination);
        $legs = null;
        $usedRadius = null;
        $engine = 'direct_fallback';

        if (! empty($segments)) {
            foreach (self::CONNECTOR_RADII as $radius) {
                $graph = $this->buildGraph($segments, $radius);
                $legs = $this->findLegs($graph, $origin, $destination, $preference);

                if ($legs !== null) {
                    $usedRadius = $radius;
                    $engine = 'postgis_astar';
                    break;
                }
            }
        }

        $directDistance = $this->haversine($origin, $destination);
        $graphDistance = $legs !== null ? $this->legsDistance($legs) : null;

        // With incomplete OSM topology an endpoint-only graph can produce a huge
        // detour. A corridor that follows nearby segments is far more useful.
        if ($legs === null || ($graphDistance !== null && $graphDistance > max($directDistance * 1.6, $directDistance + 300))) {
            $corridor = $this->corridorLegs($segments, $origin, $destination);

            if (! empty($corridor)) {
                $legs = $corridor;
                $engine = 'corridor_fallback';
                $usedRadius = null;
            }
        }

        if ($legs === null) {
            $legs = [$this->directLeg($origin, $destination)];
        }

        return $this->formatResponse(
            $origin,
            $destination,
            $preference,
            $legs,
            $segments,
            $engine,
            $usedRadius,
        );
    }

    /**
     * Load candidate segments around the route corridor.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadSegments(array $origin, array $destination): array
    {
        $distance = $this->haversine($origin, $destination);
        $padding = max(0.012, min(0.08, ($distance / 111320.0) * 0.75 + 0.012));

        $minLat = min($origin['lat'], $destination['lat']) - $padding;
        $maxLat = max($origin['lat'], $destination['lat']) + $padding;
        $minLng = min($origin['lng'], $destination['lng']) - $padding;
        $maxLng = max($origin['lng'], $destination['lng']) + $padding;

        try {
            $rows = DB::select('
                SELECT segmen_id, nama_jalan, skor_kelayakan,
                       ST_AsGeoJSON(geom::geometry) AS geojson
                FROM segmen_jalur
                WHERE ST_Intersects(geom, ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography)
                ORDER BY segmen_id ASC
                LIMIT '.self::MAX_SEGMENTS.'
            ', [$minLng, $minLat, $maxLng, $maxLat]);

            if (count($rows) < 2) {
                $midLat = ($origin['lat'] + $destination['lat']) / 2;
                $midLng = ($origin['lng'] + $destination['lng']) / 2;

                $rows = DB::select('
                    SELECT segmen_id, nama_jalan, skor_kelayakan,
                           ST_AsGeoJSON(geom::geometry) AS geojson
                    FROM segmen_jalur
                    ORDER BY geom <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography ASC
                    LIMIT 200
                ', [$midLng, $midLat]);
            }

            $repairMap = $this->activeRepairMap(array_map(fn ($row) => (int) $row->segmen_id, $rows));
        } catch (Throwable) {
            return [];
        }

        $segments = [];

        foreach ($rows as $row) {
            $geometry = json_decode((string) $row->geojson, true);

            if (! is_array($geometry) || empty($geometry['coordinates'])) {
                continue;
            }

            $lines = [];

            if (($geometry['type'] ?? null) === 'LineString') {
                $lines[] = $this->coordinateLine($geometry['coordinates']);
            } elseif (($geometry['type'] ?? null) === 'MultiLineString') {
                foreach ($geometry['coordinates'] as $coordinates) {
                    $lines[] = $this->coordinateLine($coordinates);
                }
            }

            $lines = array_values(array_filter($lines, fn (array $line): bool => count($line) >= 2));

            if (empty($lines)) {
                continue;
            }

            $segmenId = (int) $row->segmen_id;
            $segments[] = [
                'id' => $segmenId,
                'name' => $row->nama_jalan ?: 'Jalur pejalan kaki',
                'score' => max(0.0, min(100.0, (float) $row->skor_kelayakan)),
                'repair' => ! empty($repairMap[$segmenId]),
                'lines' => $lines,
            ];
        }

        return $segments;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $coordinates  [lon, lat]
     * @return array<int, array{lat: float, lng: float}>
     */
    private function coordinateLine(array $coordinates): array
    {
        $line = [];

        foreach ($coordinates as $coordinate) {
            if (! is_array($coordinate) || count($coordinate) < 2) {
                continue;
            }

            $line[] = ['lat' => (float) $coordinate[1], 'lng' => (float) $coordinate[0]];
        }

        return $line;
    }

    /**
     * @param  array<int, int>  $segmenIds
     * @return array<int, bool>
     */
    private function activeRepairMap(array $segmenIds): array
    {
        if (empty($segmenIds)) {
            return [];
        }

        try {
            $rows = DB::table('laporan')
                ->join('lokasi', 'laporan.lokasi_id', '=', 'lokasi.lokasi_id')
                ->whereIn('lokasi.segmen_id', array_values(array_unique($segmenIds)))
                ->where('laporan.status', 'TERVALIDASI')
                ->where('laporan.status_perbaikan', 'SEDANG_DIPERBAIKI')
                ->select('lokasi.segmen_id', DB::raw('count(*) as total'))
                ->groupBy('lokasi.segmen_id')
                ->get();

            $map = [];

            foreach ($rows as $row) {
                $map[(int) $row->segmen_id] = true;
            }

            return $map;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Build a routable graph from segment geometries and nearby endpoints.
     *
     * @param  array<int, array<string, mixed>>  $segments
     * @return array{nodes: array<string, array{lat: float, lng: float}>, edges: array<string, array<int, array<string, mixed>>>, endpoints: array<string, array<string, mixed>>}
     */
    private function buildGraph(array $segments, float $connectorRadius): array
    {
        $nodes = [];
        $edges = [];
        $endpoints = [];
        $directPairs = [];

        foreach ($segments as $segment) {
            foreach ($segment['lines'] as $lineIndex => $line) {
                $lineCount = count($line);

                for ($i = 0; $i < $lineCount - 1; $i++) {
                    $a = $line[$i];
                    $b = $line[$i + 1];
                    $distance = $this->haversine($a, $b);

                    if ($distance < 0.1) {
                        continue;
                    }

                    $keyA = $this->nodeKey($a);
                    $keyB = $this->nodeKey($b);
                    $nodes[$keyA] = $a;
                    $nodes[$keyB] = $b;

                    $edge = [
                        'to' => $keyB,
                        'distance_m' => $distance,
                        'street' => $segment['name'],
                        'score' => $segment['score'],
                        'segmen_id' => $segment['id'],
                        'repair' => $segment['repair'],
                        'connector' => false,
                        'line_index' => $lineIndex,
                        'point_index' => $i,
                    ];
                    $reverse = $edge;
                    $reverse['to'] = $keyA;

                    $edges[$keyA][] = $edge;
                    $edges[$keyB][] = $reverse;
                    $directPairs[$keyA.'|'.$keyB] = true;
                    $directPairs[$keyB.'|'.$keyA] = true;
                }

                foreach ([$line[0], $line[$lineCount - 1]] as $endpoint) {
                    $key = $this->nodeKey($endpoint);
                    $nodes[$key] = $endpoint;
                    $endpoints[$key] = [
                        'lat' => $endpoint['lat'],
                        'lng' => $endpoint['lng'],
                        'street' => $segment['name'],
                        'score' => $segment['score'],
                        'segmen_id' => $segment['id'],
                        'repair' => $segment['repair'],
                    ];
                }
            }
        }

        // Connect endpoints that belong to the same physical intersection but
        // use different OSM node identifiers. Larger radii are only used when
        // the source data is incomplete.
        $endpointList = array_values($endpoints);
        $count = count($endpointList);

        for ($i = 0; $i < $count; $i++) {
            $a = $endpointList[$i];
            $keyA = $this->nodeKey($a);

            for ($j = $i + 1; $j < $count; $j++) {
                $b = $endpointList[$j];
                $keyB = $this->nodeKey($b);

                if ($keyA === $keyB || isset($directPairs[$keyA.'|'.$keyB])) {
                    continue;
                }

                $distance = $this->haversine($a, $b);

                if ($distance > $connectorRadius) {
                    continue;
                }

                $connector = [
                    'to' => $keyB,
                    'distance_m' => $distance,
                    'street' => 'Jalur penghubung',
                    'score' => 100.0,
                    'segmen_id' => null,
                    'repair' => false,
                    'connector' => true,
                ];
                $reverse = $connector;
                $reverse['to'] = $keyA;

                $edges[$keyA][] = $connector;
                $edges[$keyB][] = $reverse;
                $directPairs[$keyA.'|'.$keyB] = true;
                $directPairs[$keyB.'|'.$keyA] = true;
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'endpoints' => $endpoints,
        ];
    }

    /**
     * @param  array{nodes: array, edges: array, endpoints: array}  $graph
     * @return array<int, array<string, mixed>>|null
     */
    private function findLegs(array $graph, array $origin, array $destination, string $preference): ?array
    {
        $snapOrigin = $this->nearestPointOnSegments($graph, $origin);
        $snapDestination = $this->nearestPointOnSegments($graph, $destination);

        if ($snapOrigin === null || $snapDestination === null) {
            return null;
        }

        // Do not route through a road that is unreasonably far away.
        if ($snapOrigin['distance_m'] > self::MAX_SNAP_DISTANCE_M || $snapDestination['distance_m'] > self::MAX_SNAP_DISTANCE_M) {
            return null;
        }

        $originKey = '__origin';
        $destinationKey = '__destination';

        $this->attachVirtualNode($graph, $snapOrigin, $originKey);
        $this->attachVirtualNode($graph, $snapDestination, $destinationKey);

        // Both points can snap to the same edge. Connect them directly so the
        // search does not unnecessarily walk to an endpoint and back.
        if (
            $snapOrigin['segmen_id'] !== null
            && $snapOrigin['segmen_id'] === $snapDestination['segmen_id']
            && $snapOrigin['line_index'] === $snapDestination['line_index']
            && $snapOrigin['point_index'] === $snapDestination['point_index']
        ) {
            $segmentLength = $this->haversine($snapOrigin['from'], $snapOrigin['to']);
            $along = abs($snapDestination['t'] - $snapOrigin['t']) * $segmentLength;

            if ($along > 0.1) {
                $meta = $this->snapMeta($snapOrigin);
                $graph['edges'][$originKey][] = $meta + ['to' => $destinationKey, 'distance_m' => $along];
                $graph['edges'][$destinationKey][] = $meta + ['to' => $originKey, 'distance_m' => $along];
            }
        }

        $path = $this->shortestPath($graph, $originKey, $destinationKey, $preference);

        if ($path === null) {
            return null;
        }

        $legs = [];

        if ($snapOrigin['distance_m'] > 1.0) {
            $legs[] = [
                'from' => $origin,
                'to' => ['lat' => $snapOrigin['lat'], 'lng' => $snapOrigin['lng']],
                'distance_m' => $snapOrigin['distance_m'],
                'street' => $snapOrigin['street'] ? 'Menuju '.$snapOrigin['street'] : 'Menuju jalur pejalan kaki',
                'score' => 100.0,
                'segmen_id' => null,
                'repair' => false,
                'connector' => true,
            ];
        }

        $legs = array_merge($legs, $path);

        if ($snapDestination['distance_m'] > 1.0) {
            $legs[] = [
                'from' => ['lat' => $snapDestination['lat'], 'lng' => $snapDestination['lng']],
                'to' => $destination,
                'distance_m' => $snapDestination['distance_m'],
                'street' => 'Menuju titik tujuan',
                'score' => 100.0,
                'segmen_id' => null,
                'repair' => false,
                'connector' => true,
            ];
        }

        return $legs;
    }

    /**
     * @param  array{nodes: array, edges: array, endpoints: array}  $graph
     * @return array<string, mixed>|null
     */
    private function nearestPointOnSegments(array $graph, array $point): ?array
    {
        $best = null;

        foreach ($graph['edges'] as $fromKey => $edges) {
            $from = $graph['nodes'][$fromKey] ?? null;

            if ($from === null) {
                continue;
            }

            foreach ($edges as $edge) {
                if (! empty($edge['connector'])) {
                    continue;
                }

                $to = $graph['nodes'][$edge['to']] ?? null;

                if ($to === null) {
                    continue;
                }

                // Only consider each geometric edge once.
                if (strcmp($fromKey, $edge['to']) > 0) {
                    continue;
                }

                $projection = $this->projectOnSegment($point, $from, $to);

                if ($best === null || $projection['distance_m'] < $best['distance_m']) {
                    $t = $projection['t'];
                    $edgeFromKey = $fromKey;
                    $edgeToKey = $edge['to'];

                    // Normalise the edge orientation so both snapped points on
                    // the same edge use the same t direction.
                    if (strcmp($edgeFromKey, $edgeToKey) > 0) {
                        [$edgeFromKey, $edgeToKey] = [$edgeToKey, $edgeFromKey];
                        $t = 1.0 - $t;
                    }

                    $best = [
                        'lat' => $projection['lat'],
                        'lng' => $projection['lng'],
                        'distance_m' => $projection['distance_m'],
                        't' => $t,
                        'segmen_id' => $edge['segmen_id'] ?? null,
                        'street' => $edge['street'] ?? null,
                        'score' => $edge['score'] ?? 100.0,
                        'repair' => $edge['repair'] ?? false,
                        'connector' => $edge['connector'] ?? false,
                        'from' => $graph['nodes'][$edgeFromKey] ?? $from,
                        'to' => $graph['nodes'][$edgeToKey] ?? $to,
                        'from_key' => $edgeFromKey,
                        'to_key' => $edgeToKey,
                        'line_index' => $edge['line_index'] ?? 0,
                        'point_index' => $edge['point_index'] ?? 0,
                    ];
                }
            }
        }

        return $best;
    }

    /**
     * Attach a virtual node (the snapped user position) to the nearest edge.
     *
     * @param  array{nodes: array, edges: array, endpoints: array}  $graph
     * @param  array<string, mixed>  $snap
     */
    private function attachVirtualNode(array &$graph, array $snap, string $key): void
    {
        $graph['nodes'][$key] = ['lat' => $snap['lat'], 'lng' => $snap['lng']];

        $segmentLength = $this->haversine($snap['from'], $snap['to']);
        $t = max(0.0, min(1.0, (float) $snap['t']));
        $meta = $this->snapMeta($snap);

        $connections = [
            [$snap['from_key'], $t * $segmentLength],
            [$snap['to_key'], (1.0 - $t) * $segmentLength],
        ];

        foreach ($connections as [$endpointKey, $distance]) {
            if ($key === $endpointKey) {
                continue;
            }

            $graph['edges'][$key][] = $meta + [
                'to' => $endpointKey,
                'distance_m' => $distance,
            ];
            $graph['edges'][$endpointKey][] = $meta + [
                'to' => $key,
                'distance_m' => $distance,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $snap
     * @return array<string, mixed>
     */
    private function snapMeta(array $snap): array
    {
        return [
            'street' => $snap['street'] ?? 'Jalur pejalan kaki',
            'score' => $snap['score'] ?? 100.0,
            'segmen_id' => $snap['segmen_id'] ?? null,
            'repair' => $snap['repair'] ?? false,
            'connector' => $snap['connector'] ?? false,
        ];
    }

    /**
     * A* search over the segment graph.
     *
     * @param  array{nodes: array, edges: array, endpoints: array}  $graph
     * @return array<int, array<string, mixed>>|null
     */
    private function shortestPath(array $graph, string $start, string $goal, string $preference): ?array
    {
        if ($start === $goal) {
            return [];
        }

        if (! isset($graph['nodes'][$start], $graph['nodes'][$goal])) {
            return null;
        }

        $open = new \SplPriorityQueue;
        $open->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);
        $open->insert($start, 0.0);

        $best = [$start => 0.0];
        $enqueued = [$start => 0.0];
        $visited = [];
        $came = [];
        $iterations = 0;

        while (! $open->isEmpty() && $iterations < 50000) {
            $iterations++;
            $entry = $open->extract();
            $node = $entry['data'];
            $f = -((float) $entry['priority']);

            if (isset($visited[$node])) {
                continue;
            }

            if ($f > ($enqueued[$node] ?? INF) + 0.001) {
                continue;
            }

            $visited[$node] = true;

            if ($node === $goal) {
                break;
            }

            $currentG = $best[$node];

            foreach ($graph['edges'][$node] ?? [] as $edge) {
                $to = $edge['to'];

                if (isset($visited[$to])) {
                    continue;
                }

                $newG = $currentG + $this->edgeCost($edge, $preference);

                if (isset($best[$to]) && $newG >= $best[$to] - 0.001) {
                    continue;
                }

                $best[$to] = $newG;
                $came[$to] = ['from' => $node, 'edge' => $edge];
                $heuristic = $this->haversine($graph['nodes'][$to], $graph['nodes'][$goal]);
                $newF = $newG + $heuristic;
                $enqueued[$to] = $newF;
                $open->insert($to, -$newF);
            }
        }

        if (! isset($best[$goal])) {
            return null;
        }

        $legs = [];
        $current = $goal;

        while ($current !== $start) {
            if (! isset($came[$current])) {
                return null;
            }

            $previous = $came[$current];
            $edge = $previous['edge'];
            $from = $graph['nodes'][$previous['from']];
            $to = $graph['nodes'][$current];

            $legs[] = [
                'from' => $from,
                'to' => $to,
                'distance_m' => (float) $edge['distance_m'],
                'street' => $edge['street'] ?? 'Jalur pejalan kaki',
                'score' => (float) ($edge['score'] ?? 100.0),
                'segmen_id' => $edge['segmen_id'] ?? null,
                'repair' => (bool) ($edge['repair'] ?? false),
                'connector' => (bool) ($edge['connector'] ?? false),
            ];

            $current = $previous['from'];
        }

        return array_reverse($legs);
    }

    /**
     * Convert a segment's distance into a routing cost.
     *
     * For the "safest" preference the WSM score is applied as a multiplicative
     * penalty. A segment with score 40 is therefore 2.8x more expensive than a
     * perfect segment of the same length (with the default factor of 3).
     */
    private function edgeCost(array $edge, string $preference): float
    {
        $cost = max(0.01, (float) $edge['distance_m']);

        if ($preference === 'safest') {
            $score = max(0.0, min(100.0, (float) ($edge['score'] ?? 100.0)));
            $scorePenalty = max(0.0, (float) env('CFG_ROUTE_SCORE_PENALTY', 3.0));
            $cost *= 1.0 + ((100.0 - $score) / 100.0) * $scorePenalty;

            if (! empty($edge['repair'])) {
                $cost *= max(1.0, (float) env('CFG_ROUTE_REPAIR_PENALTY', 1.35));
            }
        }

        if (! empty($edge['connector'])) {
            $cost *= max(1.0, (float) env('CFG_ROUTE_CONNECTOR_PENALTY', 1.2));
        }

        return $cost;
    }

    /**
     * Follow the nearest road segments along the straight corridor between
     * origin and destination. Used only when the topological graph is too
     * incomplete to produce a sensible route.
     *
     * @param  array<int, array<string, mixed>>  $segments
     * @return array<int, array<string, mixed>>
     */
    private function corridorLegs(array $segments, array $origin, array $destination): array
    {
        if (empty($segments)) {
            return [];
        }

        $directDistance = $this->haversine($origin, $destination);

        if ($directDistance < 1) {
            return [];
        }

        $sampleCount = max(2, min(160, (int) ceil($directDistance / 20)));
        $spacing = $directDistance / $sampleCount;
        $maxSnapDistance = min(120.0, max(40.0, $spacing * 2 + 30));
        $points = [];
        $snappedPoints = 0;

        for ($i = 0; $i <= $sampleCount; $i++) {
            $t = $i / $sampleCount;
            $sample = [
                'lat' => $origin['lat'] + ($destination['lat'] - $origin['lat']) * $t,
                'lng' => $origin['lng'] + ($destination['lng'] - $origin['lng']) * $t,
            ];

            $before = [
                'lat' => $origin['lat'] + ($destination['lat'] - $origin['lat']) * max(0.0, ($i - 1) / $sampleCount),
                'lng' => $origin['lng'] + ($destination['lng'] - $origin['lng']) * max(0.0, ($i - 1) / $sampleCount),
            ];
            $after = [
                'lat' => $origin['lat'] + ($destination['lat'] - $origin['lat']) * min(1.0, ($i + 1) / $sampleCount),
                'lng' => $origin['lng'] + ($destination['lng'] - $origin['lng']) * min(1.0, ($i + 1) / $sampleCount),
            ];
            $corridorBearing = $this->bearing($before, $after);

            $nearest = $this->nearestOnRawSegments($segments, $sample, $corridorBearing);

            if ($nearest !== null && $nearest['distance_m'] <= $maxSnapDistance) {
                $points[] = $nearest;
                $snappedPoints++;
            } else {
                $points[] = $sample + [
                    'segmen_id' => null,
                    'street' => null,
                    'score' => 100.0,
                    'repair' => false,
                ];
            }
        }

        // If the straight corridor does not follow a single known segment, a
        // direct line is a more honest fallback than a fake street route.
        if ($snappedPoints === 0) {
            return [];
        }

        // Always start and end exactly at the requested coordinates.
        $points[0] = array_merge($points[0], $origin);
        $points[count($points) - 1] = array_merge($points[count($points) - 1], $destination);

        $legs = [];

        for ($i = 0; $i < count($points) - 1; $i++) {
            $from = $points[$i];
            $to = $points[$i + 1];
            $distance = $this->haversine($from, $to);

            if ($distance < 0.5) {
                continue;
            }

            $street = $to['street'] ?? $from['street'] ?? null;

            $legs[] = [
                'from' => ['lat' => $from['lat'], 'lng' => $from['lng']],
                'to' => ['lat' => $to['lat'], 'lng' => $to['lng']],
                'distance_m' => $distance,
                'street' => $street ?: 'Jalur langsung',
                'score' => (float) ($to['score'] ?? $from['score'] ?? 100.0),
                'segmen_id' => $to['segmen_id'] ?? $from['segmen_id'] ?? null,
                'repair' => (bool) ($to['repair'] ?? $from['repair'] ?? false),
                'connector' => $street === null,
            ];
        }

        return $legs;
    }

    /**
     * @param  array<int, array<string, mixed>>  $segments
     * @return array<string, mixed>|null
     */
    private function nearestOnRawSegments(array $segments, array $point, ?float $preferredBearing = null): ?array
    {
        $best = null;

        foreach ($segments as $segment) {
            foreach ($segment['lines'] as $line) {
                for ($i = 0; $i < count($line) - 1; $i++) {
                    if ($preferredBearing !== null) {
                        $edgeBearing = $this->bearing($line[$i], $line[$i + 1]);

                        if ($this->bearingDelta($edgeBearing, $preferredBearing) > 60.0) {
                            continue;
                        }
                    }

                    $projection = $this->projectOnSegment($point, $line[$i], $line[$i + 1]);

                    if ($best !== null && $projection['distance_m'] >= $best['distance_m']) {
                        continue;
                    }

                    $best = [
                        'lat' => $projection['lat'],
                        'lng' => $projection['lng'],
                        'distance_m' => $projection['distance_m'],
                        'segmen_id' => $segment['id'],
                        'street' => $segment['name'],
                        'score' => $segment['score'],
                        'repair' => $segment['repair'],
                    ];
                }
            }
        }

        return $best;
    }

    /**
     * Initial bearing between two coordinates in degrees (0 = north).
     */
    private function bearing(array $from, array $to): float
    {
        $lat1 = deg2rad((float) $from['lat']);
        $lat2 = deg2rad((float) $to['lat']);
        $deltaLng = deg2rad((float) $to['lng'] - (float) $from['lng']);

        $y = sin($deltaLng) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($deltaLng);

        return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
    }

    private function bearingDelta(float $a, float $b): float
    {
        $delta = abs($a - $b) % 360.0;

        return $delta > 180.0 ? 360.0 - $delta : $delta;
    }

    /**
     * @param  array<int, array<string, mixed>>  $legs
     */
    private function legsDistance(array $legs): float
    {
        return array_reduce($legs, fn (float $carry, array $leg): float => $carry + (float) $leg['distance_m'], 0.0);
    }

    /**
     * @return array<string, mixed>
     */
    private function directLeg(array $origin, array $destination): array
    {
        return [
            'from' => $origin,
            'to' => $destination,
            'distance_m' => max(0.1, $this->haversine($origin, $destination)),
            'street' => 'Jalur langsung',
            'score' => 100.0,
            'segmen_id' => null,
            'repair' => false,
            'connector' => true,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $legs
     * @param  array<int, array<string, mixed>>  $segments
     * @return array<string, mixed>
     */
    private function formatResponse(
        array $origin,
        array $destination,
        string $preference,
        array $legs,
        array $segments,
        string $engine,
        ?float $connectorRadius,
    ): array {
        $polyline = [];
        $distance = 0.0;
        $weightedScore = 0.0;
        $repairDistance = 0.0;
        $safeDistance = 0.0;
        $minimumScore = 100.0;
        $grouped = [];
        $current = null;

        $appendPoint = function (array $point) use (&$polyline): void {
            $last = end($polyline);

            if ($last !== false && abs($last[0] - $point['lat']) < 1e-9 && abs($last[1] - $point['lng']) < 1e-9) {
                return;
            }

            $polyline[] = [(float) $point['lat'], (float) $point['lng']];
        };

        if (! empty($legs)) {
            $appendPoint($legs[0]['from']);
        }

        foreach ($legs as $leg) {
            $legDistance = (float) $leg['distance_m'];

            if ($legDistance <= 0.1) {
                continue;
            }

            $appendPoint($leg['to']);
            $distance += $legDistance;
            $score = (float) ($leg['score'] ?? 100.0);
            $weightedScore += $score * $legDistance;

            if (empty($leg['connector'])) {
                $minimumScore = min($minimumScore, $score);
            }

            if (! empty($leg['repair'])) {
                $repairDistance += $legDistance;
            }

            if ($score >= $this->goodScoreThreshold()) {
                $safeDistance += $legDistance;
            }

            $key = implode('|', [
                $leg['segmen_id'] ?? 'connector',
                $leg['street'] ?? '',
                ! empty($leg['repair']) ? '1' : '0',
            ]);

            if ($current === null || $current['key'] !== $key) {
                if ($current !== null) {
                    unset($current['key']);
                    $grouped[] = $current;
                }

                $current = [
                    'key' => $key,
                    'segmen_id' => $leg['segmen_id'] ?? null,
                    'name' => $leg['street'] ?? 'Jalur pejalan kaki',
                    'distance_m' => 0.0,
                    'score' => $score,
                    'tingkat' => $this->scoreLevel($score),
                    'repair' => (bool) ($leg['repair'] ?? false),
                    'connector' => (bool) ($leg['connector'] ?? false),
                    'polyline' => [
                        [(float) $leg['from']['lat'], (float) $leg['from']['lng']],
                    ],
                ];
            }

            $current['distance_m'] += $legDistance;
            $current['polyline'][] = [(float) $leg['to']['lat'], (float) $leg['to']['lng']];
        }

        if ($current !== null) {
            unset($current['key']);
            $grouped[] = $current;
        }

        foreach ($grouped as &$item) {
            $item['distance_m'] = round((float) $item['distance_m'], 1);
            $item['score'] = round((float) $item['score'], 1);
        }
        unset($item);

        $averageScore = $distance > 0 ? round($weightedScore / $distance, 1) : 100.0;
        $minimumScore = $minimumScore === 100.0 && $distance === 0.0 ? 100.0 : round($minimumScore, 1);

        return [
            'route_id' => 'RTE-'.strtoupper(bin2hex(random_bytes(4))),
            'engine' => $engine,
            'preference' => $preference,
            'origin' => $origin,
            'destination' => $destination,
            'distance_m' => round($distance, 1),
            'duration_min' => max(1, (int) ceil($distance / self::WALK_SPEED_MPS / 60)),
            'duration_s' => (int) round($distance / self::WALK_SPEED_MPS),
            'walk_speed_mps' => self::WALK_SPEED_MPS,
            'polyline' => $polyline,
            'segments' => $grouped,
            'summary' => [
                'average_score' => $averageScore,
                'minimum_score' => $minimumScore,
                'repair_distance_m' => round($repairDistance, 1),
                'safe_distance_m' => round($safeDistance, 1),
                'total_segments' => count($grouped),
                'fallback' => $engine !== 'postgis_astar',
            ],
            'warnings' => $this->warnings($engine, $averageScore, $repairDistance),
            'meta' => [
                'considered_segments' => count($segments),
                'connector_radius_m' => $connectorRadius,
                'score_penalty_factor' => max(0.0, (float) env('CFG_ROUTE_SCORE_PENALTY', 3.0)),
                'repair_penalty_factor' => max(1.0, (float) env('CFG_ROUTE_REPAIR_PENALTY', 1.35)),
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<int, array{code: string, message: string}>
     */
    private function warnings(string $engine, float $averageScore, float $repairDistance): array
    {
        $warnings = [];

        if ($repairDistance > 0) {
            $warnings[] = [
                'code' => 'ACTIVE_REPAIR',
                'message' => MessageCatalog::ROUTE_ACTIVE_REPAIR,
            ];
        }

        if ($averageScore < $this->goodScoreThreshold()) {
            $warnings[] = [
                'code' => 'LOW_FEASIBILITY',
                'message' => MessageCatalog::ROUTE_LOW_FEASIBILITY,
            ];
        }

        if ($engine !== 'postgis_astar') {
            $warnings[] = [
                'code' => 'NETWORK_FALLBACK',
                'message' => MessageCatalog::ROUTE_NETWORK_FALLBACK,
            ];
        }

        return $warnings;
    }

    private function goodScoreThreshold(): float
    {
        return (float) env('CFG_LEVEL_GOOD', 70);
    }

    private function warnScoreThreshold(): float
    {
        return (float) env('CFG_LEVEL_WARN', 40);
    }

    private function scoreLevel(float $score): string
    {
        if ($score >= $this->goodScoreThreshold()) {
            return 'SANGAT_LAYAK';
        }

        return $score >= $this->warnScoreThreshold() ? 'PERLU_PERHATIAN' : 'TIDAK_LAYAK';
    }

    private function nodeKey(array $point): string
    {
        return sprintf('%.7F,%.7F', (float) $point['lat'], (float) $point['lng']);
    }

    /**
     * @return array{lat: float, lng: float, t: float, distance_m: float}
     */
    private function projectOnSegment(array $point, array $from, array $to): array
    {
        $referenceLat = deg2rad((float) $point['lat']);
        $metersPerLat = 110540.0;
        $metersPerLng = 111320.0 * cos($referenceLat);

        $ax = ((float) $from['lng'] - (float) $point['lng']) * $metersPerLng;
        $ay = ((float) $from['lat'] - (float) $point['lat']) * $metersPerLat;
        $bx = ((float) $to['lng'] - (float) $point['lng']) * $metersPerLng;
        $by = ((float) $to['lat'] - (float) $point['lat']) * $metersPerLat;

        $dx = $bx - $ax;
        $dy = $by - $ay;
        $lengthSquared = $dx * $dx + $dy * $dy;

        $t = $lengthSquared > 0 ? -(($ax * $dx) + ($ay * $dy)) / $lengthSquared : 0.0;
        $t = max(0.0, min(1.0, $t));

        $nearestX = $ax + $t * $dx;
        $nearestY = $ay + $t * $dy;

        return [
            'lat' => (float) $from['lat'] + $t * ((float) $to['lat'] - (float) $from['lat']),
            'lng' => (float) $from['lng'] + $t * ((float) $to['lng'] - (float) $from['lng']),
            't' => $t,
            'distance_m' => sqrt(($nearestX * $nearestX) + ($nearestY * $nearestY)),
        ];
    }

    private function haversine(array $from, array $to): float
    {
        $lat1 = deg2rad((float) $from['lat']);
        $lat2 = deg2rad((float) $to['lat']);
        $deltaLat = $lat2 - $lat1;
        $deltaLng = deg2rad((float) $to['lng'] - (float) $from['lng']);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }
}
