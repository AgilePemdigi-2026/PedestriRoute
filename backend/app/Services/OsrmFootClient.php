<?php

namespace App\Services;

use App\Exceptions\RouteUnavailableException;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;

class OsrmFootClient
{
    /** All route and detour requests share one foot-only endpoint. */
    public function routes(array $origin, array $destination, array $waypointSets = [[]]): array
    {
        $base = $this->baseUrl();
        $publicFoot = $base === 'https://routing.openstreetmap.de/routed-foot/route/v1/foot';

        $urls = [];
        foreach ($waypointSets as $waypoints) {
            $points = [$origin, ...$waypoints, $destination];
            $coordinates = implode(';', array_map(fn (array $point): string => sprintf('%.7F,%.7F', $point['lng'], $point['lat']), $points));
            $radius = (float) config('services.osrm.max_snap_m');
            $radii = array_fill(0, count($points), (string) config('services.osrm.waypoint_snap_m', 40));
            $radii[0] = (string) $radius;
            $radii[count($radii) - 1] = (string) $radius;
            $urls[] = $base.'/'.$coordinates.'?'.http_build_query([
                'overview' => 'full', 'geometries' => 'geojson', 'steps' => 'true',
                'alternatives' => count($points) === 2 ? 'true' : 'false',
                'continue_straight' => 'false',
                'radiuses' => implode(';', $radii),
            ]);
        }

        try {
            if ($publicFoot) {
                // The shared public server must not receive concurrent detour bursts.
                $responses = [];
                foreach ($urls as $url) {
                    $response = $this->publicResponse($url);
                    $responses[] = $response;
                    if (in_array($response->status(), [429, 502, 503, 504], true)) {
                        break;
                    }
                }
            } else {
                $responses = Http::pool(function (Pool $pool) use ($urls): array {
                    return array_map(fn (string $url) => $pool->acceptJson()->withUserAgent('PedestriRoute/1.0')->connectTimeout(5)->timeout(10)->get($url), $urls);
                });
            }
        } catch (Throwable $exception) {
            Log::warning('OSRM foot request failed', ['host' => parse_url($base, PHP_URL_HOST), 'failure' => $exception::class]);
            throw new RouteUnavailableException('PEDESTRIAN_ENGINE_UNAVAILABLE', 'Layanan rute pejalan kaki sedang tidak dapat dihubungi. Silakan coba lagi beberapa saat.', 503);
        }

        $routes = [];
        $engineResponded = false;
        foreach ($responses as $index => $response) {
            if ($response instanceof Response && in_array($response->json('code'), ['Ok', 'NoRoute', 'NoSegment'], true)) {
                $engineResponded = true;
            }
            if (! $response instanceof Response || ! $response->successful() || $response->json('code') !== 'Ok') {
                continue;
            }
            foreach ($response->json('routes', []) as $route) {
                $coordinates = $route['geometry']['coordinates'] ?? [];
                if (! is_array($coordinates) || count($coordinates) < 2 || ($route['geometry']['type'] ?? '') !== 'LineString'
                    || ! is_numeric($route['distance'] ?? null) || ! is_finite((float) $route['distance']) || $route['distance'] <= 0
                    || (isset($route['duration']) && (! is_numeric($route['duration']) || ! is_finite((float) $route['duration']) || $route['duration'] <= 0))) {
                    continue;
                }
                $valid = true;
                foreach ($coordinates as $coordinate) {
                    if (! is_array($coordinate) || count($coordinate) < 2 || ! is_numeric($coordinate[0]) || ! is_numeric($coordinate[1]) || abs($coordinate[0]) > 180 || abs($coordinate[1]) > 90) {
                        $valid = false;
                        break;
                    }
                }
                foreach ($route['legs'] ?? [] as $leg) {
                    foreach ($leg['steps'] ?? [] as $step) {
                        if (isset($step['mode']) && ! in_array($step['mode'], ['walking', 'ferry'], true)) {
                            $valid = false;
                        }
                    }
                }
                if ($valid) {
                    $route['_waypoints'] = $waypointSets[$index];
                    $route['_provider_cache'] = $response->header('X-Route-Cache') ?: 'miss';
                    $routes[] = $route;
                }
            }
        }

        if (! $engineResponded) {
            throw new RouteUnavailableException('PEDESTRIAN_ENGINE_UNAVAILABLE', 'Layanan rute pejalan kaki sedang tidak dapat dihubungi. Silakan coba lagi beberapa saat.', 503);
        }

        return $routes;
    }

    private function baseUrl(): string
    {
        $base = rtrim((string) config('services.osrm.url'), '/');
        $publicFoot = $base === 'https://routing.openstreetmap.de/routed-foot/route/v1/foot';
        if (! str_ends_with($base, '/route/v1/foot') || (! $publicFoot && ! config('services.osrm.foot_profile_verified'))) {
            throw new RouteUnavailableException('PEDESTRIAN_ENGINE_REQUIRED', 'OSRM harus menggunakan dataset profil pejalan kaki (foot).', 503);
        }

        return $base;
    }

    /** Resolve a geocoder's street reference onto that same street in the foot graph. */
    public function nearestOnStreet(array $point, string $street): ?array
    {
        if (trim($street) === '') {
            return null;
        }
        $base = $this->baseUrl();
        $url = substr($base, 0, -strlen('/route/v1/foot')).'/nearest/v1/foot/'
            .sprintf('%.7F,%.7F', $point['lng'], $point['lat']).'?number=8';
        $response = $base === 'https://routing.openstreetmap.de/routed-foot/route/v1/foot'
            ? $this->publicResponse($url)
            : Http::acceptJson()->connectTimeout(3)->timeout(6)->get($url);
        if (! $response->successful() || $response->json('code') !== 'Ok') {
            return null;
        }
        $normalise = fn (string $name): string => trim(preg_replace('/^(jalan|jl\.?)\s+/iu', '', mb_strtolower($name)));
        foreach ($response->json('waypoints', []) as $waypoint) {
            $location = $waypoint['location'] ?? [];
            if ($normalise($waypoint['name'] ?? '') !== $normalise($street) || ($waypoint['distance'] ?? INF) > 200
                || ! is_array($location) || count($location) < 2 || ! is_numeric($location[0]) || ! is_numeric($location[1])) {
                continue;
            }
            if (abs($location[0]) > 180 || abs($location[1]) > 90) {
                continue;
            }

            return ['lat' => (float) $location[1], 'lng' => (float) $location[0]];
        }

        return null;
    }

    /** Cache walking geometry only: current report exclusions are always rechecked by the service. */
    private function publicResponse(string $url): Response
    {
        $key = 'osrm-foot:v1:'.hash('sha256', $url);
        $cached = Cache::get($key.':fresh');
        if (is_array($cached)) {
            return $this->cachedResponse($cached, 'fresh');
        }
        $cooldown = (int) Cache::get('osrm-foot:retry-after', 0) - now()->timestamp;
        if ($cooldown > 0) {
            $backup = Cache::get($key.':backup');

            return is_array($backup) ? $this->cachedResponse($backup, 'stale')
                : new Response(new PsrResponse(429, ['Retry-After' => $cooldown], '{}'));
        }

        $lastResponse = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $lastResponse = Cache::lock('osrm-foot:public-request', 20)->block(12, function () use ($url): Response {
                    $delay = max(0, (float) Cache::get('osrm-foot:next-request', 0) - microtime(true));
                    if ($delay > 0) {
                        Sleep::usleep((int) ceil($delay * 1000000));
                    }
                    try {
                        return Http::acceptJson()->withUserAgent('PedestriRoute/1.0')->connectTimeout(5)->timeout(10)->get($url);
                    } finally {
                        Cache::put('osrm-foot:next-request', microtime(true) + 1.1, 60);
                    }
                });

                if ($lastResponse->successful() && $lastResponse->json('code') === 'Ok') {
                    $payload = $lastResponse->json();
                    Cache::put($key.':fresh', $payload, (int) config('services.osrm.cache_seconds', 1800));
                    Cache::put($key.':backup', $payload, 86400);

                    return $lastResponse;
                }
                // Topology failures are real results; retry only transient provider failures.
                if (! in_array($lastResponse->status(), [429, 502, 503, 504], true)) {
                    return $lastResponse;
                }
                $retryAfter = $lastResponse->header('Retry-After');
                $wait = is_numeric($retryAfter) ? (float) $retryAfter : ($retryAfter ? max(0, strtotime($retryAfter) - time()) : 1);
                if ($wait > 5) {
                    Cache::put('osrm-foot:retry-after', now()->timestamp + (int) ceil($wait), (int) ceil($wait));
                    break;
                }
            } catch (Throwable $exception) {
                Log::notice('OSRM public foot connection failed', ['attempt' => $attempt + 1, 'failure' => $exception::class]);
                $wait = 1;
            }
            if ($attempt === 0) {
                Sleep::usleep((int) (max(1, $wait) * 1000000));
            }
        }

        if (! Cache::has('osrm-foot:retry-after')) {
            Cache::put('osrm-foot:retry-after', now()->timestamp + 10, 10);
        }
        $backup = Cache::get($key.':backup');
        if (is_array($backup)) {
            return $this->cachedResponse($backup, 'stale');
        }

        return $lastResponse ?? new Response(new PsrResponse(503, [], '{}'));
    }

    private function cachedResponse(array $payload, string $status): Response
    {
        return new Response(new PsrResponse(200, ['X-Route-Cache' => $status], json_encode($payload)));
    }
}
