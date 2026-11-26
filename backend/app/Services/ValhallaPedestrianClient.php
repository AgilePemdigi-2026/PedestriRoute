<?php

namespace App\Services;

use App\Exceptions\RouteUnavailableException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Valhalla `pedestrian` costing. Responses are mapped to the OSRM route shape
 * used by RouteRecommendationService (distance m, duration s, [lng, lat] line).
 */
class ValhallaPedestrianClient
{
    private const PUBLIC_HOST = 'valhalla1.openstreetmap.de';

    /**
     * @param  array<string, mixed>  $costing  pedestrian costing options
     * @param  array<int, array<int, array{0: float, 1: float}>>  $excludePolygons  rings of [lng, lat]
     * @return array<int, array<string, mixed>> routes; [] when Valhalla finds no path
     *
     * @throws RouteUnavailableException when the service cannot be reached
     */
    public function routes(array $origin, array $destination, array $costing, array $excludePolygons = [], int $alternates = 2): array
    {
        $radius = (int) config('services.valhalla.snap_radius_m', 100);
        $body = [
            'locations' => [
                ['lat' => $origin['lat'], 'lon' => $origin['lng'], 'type' => 'break', 'radius' => $radius],
                ['lat' => $destination['lat'], 'lon' => $destination['lng'], 'type' => 'break', 'radius' => $radius],
            ],
            'costing' => 'pedestrian',
            'costing_options' => ['pedestrian' => $costing],
            'alternates' => $alternates,
            'directions_options' => ['units' => 'kilometers'],
        ];
        if ($excludePolygons !== []) {
            $body['exclude_polygons'] = $excludePolygons;
        }

        $payload = $this->request($body);
        if ($payload === null) {
            return [];
        }
        $trips = [$payload['trip'] ?? null];
        foreach ($payload['alternates'] ?? [] as $alternate) {
            $trips[] = $alternate['trip'] ?? null;
        }

        $routes = [];
        foreach ($trips as $trip) {
            $route = is_array($trip) ? $this->toRoute($trip) : null;
            if ($route !== null) {
                $routes[] = $route;
            }
        }

        return $routes;
    }

    /** @return array<string, mixed>|null null = no path (HTTP 400, codes 442/171) */
    private function request(array $body): ?array
    {
        $url = rtrim((string) config('services.valhalla.url'), '/').'/route';
        $key = 'valhalla:v1:'.hash('sha256', $url.json_encode($body));
        if (is_array($cached = Cache::get($key))) {
            return ($cached['no_path'] ?? false) ? null : $cached;
        }

        try {
            $send = fn () => Http::acceptJson()->withUserAgent('PedestriRoute/1.0')->connectTimeout(5)->timeout(15)->post($url, $body);
            $response = parse_url($url, PHP_URL_HOST) === self::PUBLIC_HOST
                // FOSSGIS terms: at most one request per second.
                ? Cache::lock('valhalla:public-request', 20)->block(15, function () use ($send) {
                    $delay = max(0, (float) Cache::get('valhalla:next-request', 0) - microtime(true));
                    if ($delay > 0) {
                        Sleep::usleep((int) ceil($delay * 1000000));
                    }
                    try {
                        return $send();
                    } finally {
                        Cache::put('valhalla:next-request', microtime(true) + 1.1, 60);
                    }
                })
                : $send();
        } catch (Throwable $exception) {
            Log::notice('Valhalla request failed', ['failure' => $exception::class]);
            throw new RouteUnavailableException('PEDESTRIAN_ENGINE_UNAVAILABLE', 'Layanan rute Valhalla tidak dapat dihubungi.', 503);
        }

        $ttl = (int) config('services.valhalla.cache_seconds', 1800);
        if ($response->status() === 400 && in_array((int) $response->json('error_code'), [442, 171], true)) {
            Cache::put($key, ['no_path' => true], $ttl);

            return null;
        }
        if (! $response->successful() || ! is_array($response->json('trip'))) {
            Log::warning('Valhalla rejected the route request', [
                'status' => $response->status(), 'error_code' => $response->json('error_code'), 'error' => $response->json('error'),
                'exclude_polygons' => count($body['exclude_polygons'] ?? []),
            ]);
            throw new RouteUnavailableException('PEDESTRIAN_ENGINE_UNAVAILABLE', 'Layanan rute Valhalla sedang bermasalah.', 503);
        }
        $payload = $response->json();
        Cache::put($key, $payload, $ttl);

        return $payload;
    }

    private function toRoute(array $trip): ?array
    {
        $coordinates = [];
        $legs = [];
        $steps = [];
        foreach ($trip['legs'] ?? [] as $leg) {
            $shape = $this->decode((string) ($leg['shape'] ?? ''));
            if (count($shape) < 2) {
                return null;
            }
            foreach ($leg['maneuvers'] ?? [] as $maneuver) {
                $begin = (int) ($maneuver['begin_shape_index'] ?? 0);
                $end = (int) ($maneuver['end_shape_index'] ?? $begin);
                $line = array_slice($shape, $begin, max(0, $end - $begin) + 1);
                if (count($line) < 2) {
                    continue;
                }
                $street = trim((string) (($maneuver['street_names'] ?? [])[0] ?? ''));
                $steps[] = ['name' => $street, 'distance' => (float) ($maneuver['length'] ?? 0) * 1000, 'geometry' => ['coordinates' => $line]];
                for ($i = 1; $i < count($line); $i++) {
                    $from = ['lng' => $line[$i - 1][0], 'lat' => $line[$i - 1][1]];
                    $to = ['lng' => $line[$i][0], 'lat' => $line[$i][1]];
                    $legs[] = ['from' => $from, 'to' => $to, 'distance_m' => $this->metres($line[$i - 1], $line[$i]),
                        'street' => $street ?: 'Jalur pejalan kaki', 'connector' => false];
                }
            }
            foreach ($shape as $point) {
                if ($coordinates === [] || end($coordinates) !== $point) {
                    $coordinates[] = $point;
                }
            }
        }
        $distance = (float) ($trip['summary']['length'] ?? 0) * 1000;
        $duration = (float) ($trip['summary']['time'] ?? 0);
        if (count($coordinates) < 2 || $distance <= 0) {
            return null;
        }

        return [
            'distance' => $distance, 'duration' => $duration > 0 ? $duration : $distance / TurnInstructionService::WALK_SPEED_MPS,
            'geometry' => ['type' => 'LineString', 'coordinates' => $coordinates],
            'legs' => [['steps' => $steps]],
            '_instruction_legs' => $legs, '_engine' => 'valhalla_pedestrian',
        ];
    }

    /**
     * Decode a Valhalla polyline (precision 6) into [lng, lat] pairs.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public function decode(string $encoded): array
    {
        $points = [];
        $index = $lat = $lng = 0;
        $length = strlen($encoded);
        while ($index < $length) {
            foreach (['lat', 'lng'] as $axis) {
                $result = $shift = 0;
                do {
                    if ($index >= $length) {
                        return $points;
                    }
                    $byte = ord($encoded[$index++]) - 63;
                    $result |= ($byte & 0x1F) << $shift;
                    $shift += 5;
                } while ($byte >= 0x20);
                $delta = ($result & 1) ? ~($result >> 1) : ($result >> 1);
                $$axis += $delta;
            }
            $points[] = [round($lng / 1e6, 6), round($lat / 1e6, 6)];
        }

        return $points;
    }

    private function metres(array $a, array $b): float
    {
        $scale = 111195 * cos(deg2rad(($a[1] + $b[1]) / 2));

        return hypot(($b[0] - $a[0]) * $scale, ($b[1] - $a[1]) * 111195);
    }
}
