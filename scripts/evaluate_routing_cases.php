<?php

/** Controlled paired routing benchmark; run only against the disposable evaluation DB. */

use App\Services\RouteHazardGeometry;
use App\Services\RouteRecommendationService;
use App\Services\RouteReportRepository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require __DIR__.'/../backend/vendor/autoload.php';
$app = require __DIR__.'/../backend/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception): void {
    fwrite(STDERR, (string) $exception.PHP_EOL);
    exit(1);
});

if (! str_starts_with((string) config('database.connections.pgsql.database'), 'pedestriroute_eval_')) {
    throw new RuntimeException('This benchmark requires a disposable evaluation database.');
}

config([
    'services.osrm.url' => 'http://evaluation.invalid/route/v1/foot',
    'services.osrm.foot_profile_verified' => true,
    'services.osrm.max_snap_m' => 40,
    'services.osrm.waypoint_snap_m' => 40,
    'services.osrm.residential_detours' => false,
    'services.osrm.search_requests' => 0,
    'cache.default' => 'array',
]);
Http::preventStrayRequests();
$rows = [];
$scenarios = ['no_reports', 'validated', 'pending', 'rejected', 'repaired', 'repair_active', 'endpoint', 'unavoidable', 'duration_first'];

foreach ([10, 25, 40] as $radius) {
    foreach ($scenarios as $scenario) {
        DB::beginTransaction();
        try {
            Cache::flush();
            Http::swap(new Factory);
            Http::preventStrayRequests();
            config(['services.osrm.avoid_radius_m' => $radius]);
            $origin = ['lat' => -7.2700, 'lng' => 112.8070];
            $destination = ['lat' => -7.2700, 'lng' => 112.8090];
            $direct = [[112.8070, -7.2700], [112.8090, -7.2700]];
            $detour = [[112.8070, -7.2700], [112.8070, -7.2690], [112.8090, -7.2690], [112.8090, -7.2700]];
            // Distances are independent spherical lengths, not production geometry outputs.
            $length = function (array $coordinates): float {
                $sum = 0.0;
                for ($i = 1; $i < count($coordinates); $i++) {
                    [$lng1, $lat1] = $coordinates[$i - 1];
                    [$lng2, $lat2] = $coordinates[$i];
                    $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
                        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;
                    $sum += 2 * 6371000 * atan2(sqrt($a), sqrt(1 - $a));
                }

                return $sum;
            };
            $route = fn (array $coordinates, int $duration): array => [
                'distance' => $length($coordinates), 'duration' => $duration,
                'geometry' => ['type' => 'LineString', 'coordinates' => $coordinates], 'legs' => [],
            ];
            $candidates = [$route($direct, $scenario === 'duration_first' ? 240 : 200)];
            if ($scenario !== 'unavoidable') {
                $candidates[] = $route($detour, $scenario === 'duration_first' ? 200 : 400);
            }
            $hasRecord = ! in_array($scenario, ['no_reports', 'duration_first'], true);
            if ($hasRecord) {
                $wargaId = DB::table('warga')->insertGetId([
                    'nama' => 'Evaluation fixture', 'email' => $scenario.'@evaluation.invalid',
                ], 'warga_id');
                $lng = $scenario === 'endpoint' ? $origin['lng'] : 112.8080;
                $lokasiId = DB::table('lokasi')->insertGetId([
                    'latitude' => -7.2700, 'longitude' => $lng,
                    'geom' => DB::raw("ST_SetSRID(ST_Point({$lng}, -7.2700), 4326)::geography"),
                ], 'lokasi_id');
                DB::table('laporan')->insert([
                    'warga_id' => $wargaId, 'lokasi_id' => $lokasiId, 'kategori_id' => 1,
                    'deskripsi' => 'Controlled fixture',
                    'status' => match ($scenario) {
                        'pending' => 'MENUNGGU_VALIDASI_ADMIN',
                        'rejected' => 'DITOLAK',
                        default => 'TERVALIDASI',
                    },
                    'status_perbaikan' => match ($scenario) {
                        'repaired' => 'SUDAH_DIPERBAIKI',
                        'repair_active' => 'SEDANG_DIPERBAIKI',
                        default => null,
                    },
                ]);
            }
            Http::fake(['http://evaluation.invalid/*' => Http::response(['code' => 'Ok', 'routes' => $candidates])]);
            $active = in_array($scenario, ['validated', 'repair_active', 'endpoint', 'unavoidable'], true);
            foreach (['fastest', 'safest'] as $preference) {
                $start = hrtime(true);
                $response = $app->make(RouteRecommendationService::class)->recommend($origin, $destination, $preference);
                $elapsed = (hrtime(true) - $start) / 1e6;
                $expectDetour = $scenario === 'duration_first'
                    || ($preference === 'safest' && in_array($scenario, ['validated', 'repair_active'], true));
                $expected = $candidates[$expectDetour ? 1 : 0];
                $expectedExposure = $active && ! $expectDetour ? ($scenario === 'endpoint' ? $radius : 2 * $radius) : 0;
                $expectedBlocking = $active && ! $expectDetour && $scenario !== 'endpoint' ? 1 : 0;
                $expectedPolyline = array_map(fn (array $p): array => [$p[1], $p[0]], $expected['geometry']['coordinates']);
                $warningCodes = array_column($response['warnings'], 'code');
                $checks = [
                    'expected_route' => $response['polyline'] === $expectedPolyline,
                    'expected_duration' => $response['duration_s'] === $expected['duration'],
                    'analytic_exposure' => abs($response['summary']['hazard_distance_m'] - $expectedExposure) <= 0.2,
                    'blocking_count' => $response['summary']['remaining_report_count'] === $expectedBlocking,
                    'residual_warning' => in_array('SAFEST_AVAILABLE_WITH_REPORTS', $warningCodes, true)
                        === ($preference === 'safest' && $expectedBlocking > 0),
                    'repair_warning' => in_array('ACTIVE_REPAIR', $warningCodes, true)
                        === ($scenario === 'repair_active' && ! $expectDetour),
                ];
                $rows[] = [
                    'scenario' => $scenario, 'radius_m' => $radius, 'preference' => $preference,
                    'duration_s' => $response['duration_s'], 'distance_m' => $response['distance_m'],
                    'exposure_m' => $response['summary']['hazard_distance_m'],
                    'expected_exposure_m' => $expectedExposure,
                    'blocking_count' => $response['summary']['remaining_report_count'],
                    'candidate_duration_gap_percent' => $preference === 'fastest'
                        ? 100 * ($response['duration_s'] - min(array_column($candidates, 'duration'))) / min(array_column($candidates, 'duration')) : null,
                    'in_process_ms' => $elapsed, 'checks' => $checks, 'passed' => ! in_array(false, $checks, true),
                    'warnings' => $warningCodes,
                    'candidate_raw_exposure_m' => array_map(function (array $candidate) use ($app, $radius): float {
                        $assessment = $app->make(RouteHazardGeometry::class)->blockingAssessment(
                            $candidate['geometry']['coordinates'], $app->make(RouteReportRepository::class)->active(), $radius,
                        );

                        return array_sum(array_column($assessment['sections'], 'distance_m'));
                    }, $candidates),
                ];
            }
        } finally {
            DB::rollBack();
        }
    }
}

echo json_encode(['rows' => $rows, 'passed' => count(array_filter($rows, fn (array $r): bool => $r['passed'])), 'total' => count($rows)], JSON_THROW_ON_ERROR).PHP_EOL;
