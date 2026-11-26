<?php

namespace App\Services;

/**
 * Turns an Overpass JSON response into pedestrian graph edges for `segmen_jalur`.
 *
 * Each walkable OSM way is split at every node shared with another walkable way
 * (and at its own end nodes), so `u_node`/`v_node` are real graph vertices and
 * consecutive edges meet at identical coordinates.
 */
class OsmPedestrianNetworkParser
{
    /** Highway classes a pedestrian may use by default (mirrors OSRM foot.lua). */
    private const WALKABLE = [
        'primary', 'primary_link', 'secondary', 'secondary_link', 'tertiary', 'tertiary_link',
        'unclassified', 'residential', 'road', 'living_street', 'service', 'track',
        'path', 'footway', 'pedestrian', 'steps', 'pier',
    ];

    /** Classes walkable only when foot access is explicitly granted. */
    private const FOOT_TAG_REQUIRED = ['trunk', 'trunk_link', 'cycleway', 'bridleway'];

    private const FOOT_ALLOWED = ['yes', 'designated', 'permissive', 'destination'];

    /**
     * @param  array{elements?: array<int, array<string, mixed>>}  $overpass
     * @return array<int, array{osm_way_id: int, u_node: int, v_node: int, nama_jalan: ?string, coordinates: array<int, array{0: float, 1: float}>, panjang_m: float}>
     */
    public function parse(array $overpass): array
    {
        $nodes = [];
        $ways = [];
        foreach ($overpass['elements'] ?? [] as $element) {
            $type = $element['type'] ?? null;
            if ($type === 'node' && isset($element['id'], $element['lat'], $element['lon'])
                && is_numeric($element['lat']) && is_numeric($element['lon'])) {
                $nodes[(int) $element['id']] = [(float) $element['lon'], (float) $element['lat']];
            } elseif ($type === 'way' && isset($element['id']) && $this->isWalkable($element['tags'] ?? [])) {
                $ways[] = $element;
            }
        }

        $usage = [];
        foreach ($ways as $way) {
            foreach (array_unique($way['nodes'] ?? []) as $nodeId) {
                $usage[$nodeId] = ($usage[$nodeId] ?? 0) + 1;
            }
        }

        $edges = [];
        foreach ($ways as $way) {
            $wayNodes = array_values(array_filter($way['nodes'] ?? [], fn ($id): bool => isset($nodes[$id])));
            if (count($wayNodes) < 2) {
                continue;
            }
            $tags = $way['tags'] ?? [];
            $name = $this->streetName($tags);
            $piece = [$wayNodes[0]];
            $last = count($wayNodes) - 1;
            for ($i = 1; $i <= $last; $i++) {
                $nodeId = $wayNodes[$i];
                $piece[] = $nodeId;
                if ($i === $last || ($usage[$nodeId] ?? 0) > 1) {
                    $edge = $this->edge((int) $way['id'], $piece, $nodes, $name);
                    if ($edge !== null) {
                        $key = $edge['osm_way_id'].':'.$edge['u_node'].':'.$edge['v_node'];
                        $edges[$key] ??= $edge;
                    }
                    $piece = [$nodeId];
                }
            }
        }

        return array_values($edges);
    }

    /** @param  array<string, string>  $tags */
    public function isWalkable(array $tags): bool
    {
        $highway = $tags['highway'] ?? null;
        $foot = $tags['foot'] ?? null;
        if ($highway === null || ($tags['area'] ?? null) === 'yes') {
            return false;
        }
        if (in_array($foot, ['no', 'private', 'use_sidepath'], true)) {
            return false;
        }
        $footGranted = in_array($foot, self::FOOT_ALLOWED, true);
        if (in_array($tags['access'] ?? null, ['no', 'private'], true) && ! $footGranted) {
            return false;
        }
        if (in_array($highway, self::FOOT_TAG_REQUIRED, true)) {
            return $footGranted;
        }

        return in_array($highway, self::WALKABLE, true);
    }

    /** @param  array<string, string>  $tags */
    private function streetName(array $tags): ?string
    {
        $name = trim((string) ($tags['name'] ?? $tags['ref'] ?? ''));
        if ($name !== '') {
            return mb_substr($name, 0, 255);
        }

        return match ($tags['highway'] ?? null) {
            'footway', 'pedestrian', 'path' => 'Jalur pejalan kaki',
            'steps' => 'Tangga',
            default => null,
        };
    }

    /**
     * @param  array<int, int>  $piece
     * @param  array<int, array{0: float, 1: float}>  $nodes
     */
    private function edge(int $wayId, array $piece, array $nodes, ?string $name): ?array
    {
        $coordinates = array_map(fn (int $id): array => $nodes[$id], $piece);
        $length = 0.0;
        for ($i = 1; $i < count($coordinates); $i++) {
            $length += $this->haversine($coordinates[$i - 1], $coordinates[$i]);
        }
        if ($length < 0.1) {
            return null;
        }

        return [
            'osm_way_id' => $wayId,
            'u_node' => $piece[0],
            'v_node' => $piece[count($piece) - 1],
            'nama_jalan' => $name,
            'coordinates' => $coordinates,
            'panjang_m' => round($length, 2),
        ];
    }

    /** @param array{0: float, 1: float} $a */
    private function haversine(array $a, array $b): float
    {
        $lat1 = deg2rad($a[1]);
        $lat2 = deg2rad($b[1]);
        $value = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(deg2rad($b[0] - $a[0]) / 2) ** 2;

        return 12742000 * asin(min(1, sqrt($value)));
    }
}
