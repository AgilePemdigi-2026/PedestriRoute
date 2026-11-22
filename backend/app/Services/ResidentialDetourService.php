<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class ResidentialDetourService
{
    public function __construct(private readonly RouteHazardGeometry $geometry, private readonly OsrmFootClient $osrm) {}

    /** Discover actual street vertices; never treat a geocoder's bounding-box centre as a road. */
    public function waypointSets(array $origin, array $destination, array $reports, float $radius): array
    {
        if (! config('services.osrm.residential_detours', true)) {
            return [];
        }
        $padding = 0.005;
        $bbox = [min($origin['lng'], $destination['lng']) - $padding, min($origin['lat'], $destination['lat']) - $padding,
            max($origin['lng'], $destination['lng']) + $padding, max($origin['lat'], $destination['lat']) + $padding];
        $bbox = array_map(fn (float $value): float => round($value, 3), $bbox);

        try {
            $features = Cache::remember('residential-streets:'.hash('sha256', json_encode($bbox)), 3600, function () use ($bbox, $origin, $destination): array {
                return Http::acceptJson()->withUserAgent('PedestriRoute/1.0')->connectTimeout(3)->timeout(8)
                    ->get(config('services.places.url'), [
                        'q' => 'Jalan', 'osm_tag' => 'highway:residential', 'limit' => 20,
                        'lat' => ($origin['lat'] + $destination['lat']) / 2,
                        'lon' => ($origin['lng'] + $destination['lng']) / 2, 'bbox' => implode(',', $bbox),
                    ])->throw()->json('features', []);
            });
        } catch (Throwable) {
            return [];
        }

        $streets = [];
        $seen = [];
        $deadline = microtime(true) + 8;
        foreach ($this->spreadFeatures($features) as $feature) {
            if (count($seen) >= 8 || microtime(true) >= $deadline) {
                break;
            }
            $properties = $feature['properties'] ?? [];
            $id = $properties['osm_id'] ?? null;
            if (($properties['osm_type'] ?? '') !== 'W' || ($properties['osm_value'] ?? '') !== 'residential'
                || ! is_numeric($id) || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $street = $this->streetVertices((int) $id);
            if ($street === null) {
                $reference = $feature['geometry']['coordinates'] ?? [];
                if (is_array($reference) && count($reference) >= 2 && is_numeric($reference[0]) && is_numeric($reference[1])) {
                    try {
                        $snap = $this->osrm->nearestOnStreet(['lat' => (float) $reference[1], 'lng' => (float) $reference[0]], (string) ($properties['name'] ?? ''));
                        $street = $snap === null || $this->geometry->distance($snap, ['lat' => (float) $reference[1], 'lng' => (float) $reference[0]]) > 200 ? [] : [$snap];
                    } catch (Throwable) {
                        $street = [];
                    }
                } else {
                    $street = [];
                }
            }
            $vertices = array_values(array_filter($street, fn (array $point): bool => ! $this->insideReport($point, $reports, $radius)));
            if ($vertices === []) {
                continue;
            }
            $point = $this->pointAlong($street, 0.5);
            if ($this->insideReport($point, $reports, $radius)) {
                $point = $vertices[(int) floor((count($vertices) - 1) / 2)];
            }
            $duplicate = false;
            foreach ($streets as $existing) {
                if ($this->geometry->distance($point, $existing['point']) < 120) {
                    $duplicate = true;
                    break;
                }
            }
            if (! $duplicate) {
                $streets[] = ['point' => $point, 'vertices' => $street];
            }
            if (count($streets) === 6) {
                break;
            }
        }

        usort($streets, fn (array $a, array $b): int => ($this->geometry->distance($origin, $a['point']) + $this->geometry->distance($a['point'], $destination))
            <=> ($this->geometry->distance($origin, $b['point']) + $this->geometry->distance($b['point'], $destination)));
        $sets = [];
        foreach (array_slice($streets, 0, 4) as $street) {
            $vertices = $street['vertices'];
            $first = $vertices[0];
            $last = $vertices[count($vertices) - 1];
            if ($this->geometry->distance($first, $last) > 180) {
                $first = $this->pointAlong($vertices, 1 / 3);
                $last = $this->pointAlong($vertices, 2 / 3);
                if ($this->insideReport($first, $reports, $radius) || $this->insideReport($last, $reports, $radius)) {
                    $sets[] = [$street['point']];

                    continue;
                }
                if ($this->geometry->distance($origin, $first) > $this->geometry->distance($origin, $last)) {
                    [$first, $last] = [$last, $first];
                }
                $sets[] = [$first, $last];
            } else {
                $sets[] = [$street['point']];
            }
        }

        return $sets;
    }

    private function pointAlong(array $line, float $fraction): array
    {
        $lengths = [];
        for ($i = 1; $i < count($line); $i++) {
            $lengths[] = $this->geometry->distance($line[$i - 1], $line[$i]);
        }
        $remaining = array_sum($lengths) * $fraction;
        foreach ($lengths as $index => $length) {
            if ($remaining <= $length && $length > 0) {
                $t = $remaining / $length;

                return [
                    'lat' => $line[$index]['lat'] + ($line[$index + 1]['lat'] - $line[$index]['lat']) * $t,
                    'lng' => $line[$index]['lng'] + ($line[$index + 1]['lng'] - $line[$index]['lng']) * $t,
                ];
            }
            $remaining -= $length;
        }

        return $line[count($line) - 1];
    }

    /** Avoid spending every lookup on adjacent blocks of the same housing estate. */
    private function spreadFeatures(array $features): array
    {
        $ordered = [];
        $anchors = [];
        while ($features !== []) {
            $position = 0;
            $furthest = -1;
            foreach ($features as $index => $feature) {
                $coordinates = $feature['geometry']['coordinates'] ?? [];
                $distance = 0;
                if (is_array($coordinates) && count($coordinates) >= 2 && is_numeric($coordinates[0]) && is_numeric($coordinates[1]) && $anchors !== []) {
                    $point = ['lat' => (float) $coordinates[1], 'lng' => (float) $coordinates[0]];
                    $distance = min(array_map(fn (array $anchor): float => $this->geometry->distance($point, $anchor), $anchors));
                }
                if ($distance > $furthest) {
                    $furthest = $distance;
                    $position = $index;
                }
            }
            $feature = $features[$position];
            array_splice($features, $position, 1);
            $ordered[] = $feature;
            $coordinates = $feature['geometry']['coordinates'] ?? [];
            if (is_array($coordinates) && count($coordinates) >= 2 && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) {
                $anchors[] = ['lat' => (float) $coordinates[1], 'lng' => (float) $coordinates[0]];
            }
        }

        return $ordered;
    }

    private function streetVertices(int $id): ?array
    {
        if (Cache::has('residential-map-source-unavailable') || Cache::has('residential-way-unavailable:'.$id)) {
            return null;
        }
        try {
            $data = Cache::remember('residential-way:'.$id, 86400, fn (): array => Http::acceptJson()
                ->withUserAgent('PedestriRoute/1.0')->connectTimeout(2)->timeout(4)
                ->get(rtrim(config('services.osrm.map_data_url', 'https://api.openstreetmap.org/api/0.6'), '/').'/way/'.$id.'/full.json')
                ->throw()->json());
        } catch (Throwable) {
            Cache::put('residential-way-unavailable:'.$id, true, 60);
            Cache::put('residential-map-source-unavailable', true, 60);

            return null;
        }
        $nodes = [];
        $way = null;
        foreach ($data['elements'] ?? [] as $element) {
            if (($element['type'] ?? '') === 'node' && isset($element['lat'], $element['lon'])) {
                $nodes[$element['id']] = $element;
            } elseif (($element['type'] ?? '') === 'way' && ($element['id'] ?? null) === $id) {
                $way = $element;
            }
        }
        $tags = $way['tags'] ?? [];
        $footAllowed = in_array($tags['foot'] ?? '', ['yes', 'designated', 'permissive'], true);
        if (($tags['highway'] ?? '') !== 'residential' || in_array($tags['foot'] ?? '', ['no', 'private'], true)
            || (! $footAllowed && in_array($tags['access'] ?? '', ['no', 'private'], true))) {
            return [];
        }
        $points = [];
        foreach ($way['nodes'] ?? [] as $nodeId) {
            $node = $nodes[$nodeId] ?? null;
            if ($node === null || ! is_numeric($node['lat']) || ! is_numeric($node['lon']) || abs($node['lat']) > 90 || abs($node['lon']) > 180) {
                return [];
            }
            $nodeTags = $node['tags'] ?? [];
            if (in_array($nodeTags['foot'] ?? '', ['no', 'private'], true)
                || (in_array($nodeTags['access'] ?? '', ['no', 'private'], true) && ! in_array($nodeTags['foot'] ?? '', ['yes', 'designated', 'permissive'], true))) {
                return [];
            }
            $points[] = ['lat' => (float) $node['lat'], 'lng' => (float) $node['lon']];
        }

        return $points;
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
}
