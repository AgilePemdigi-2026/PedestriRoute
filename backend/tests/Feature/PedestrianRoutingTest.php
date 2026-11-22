<?php

namespace Tests\Feature;

use App\Exceptions\RouteUnavailableException;
use App\Models\Warga;
use App\Services\RouteReportRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PedestrianRoutingTest extends TestCase
{
    use DatabaseTransactions;

    private const FOOT_URL = 'https://routing.openstreetmap.de/routed-foot/route/v1/foot';

    private function authenticate(): Warga
    {
        $warga = Warga::create([
            'nama' => 'Pedestrian routing test', 'email' => uniqid().'@route.test',
            'no_hp' => '628'.random_int(100000000, 999999999),
            'password' => 'password123', 'tanggal_daftar' => now(), 'gagal_login' => 0,
        ]);
        Sanctum::actingAs($warga, ['role:warga']);
        config(['services.osrm.url' => self::FOOT_URL, 'services.osrm.residential_detours' => false]);
        Http::preventStrayRequests();
        Sleep::fake();

        return $warga;
    }

    private function reportAt(Warga $warga, float $lng = 0, float $lat = 0, ?string $repair = null, string $status = 'TERVALIDASI'): int
    {
        $location = DB::table('lokasi')->insertGetId([
            'latitude' => $lat, 'longitude' => $lng,
            'geom' => DB::raw("ST_SetSRID(ST_Point({$lng}, {$lat}), 4326)"),
            'alamat' => 'Report test location',
        ], 'lokasi_id');

        return DB::table('laporan')->insertGetId([
            'warga_id' => $warga->warga_id, 'kategori_id' => 1, 'lokasi_id' => $location,
            'deskripsi' => 'Routing hazard', 'status' => $status,
            'status_perbaikan' => $repair, 'tanggal_lapor' => now(),
        ], 'laporan_id');
    }

    private function route(array $coordinates = [[-0.005, 0], [0.005, 0]], float $distance = 1112, float $duration = 890): array
    {
        return ['distance' => $distance, 'duration' => $duration, 'geometry' => ['type' => 'LineString', 'coordinates' => $coordinates], 'legs' => []];
    }

    private function requestRoute(string $preference = 'safest')
    {
        return $this->postJson('/api/v1/routes/recommend', [
            'origin' => ['lat' => 0, 'lng' => -0.005],
            'destination' => ['lat' => 0, 'lng' => 0.005], 'preference' => $preference,
        ]);
    }

    public function test_safest_accepts_a_report_free_foot_alternative_without_a_detour_distance_cap(): void
    {
        $warga = $this->authenticate();
        $this->reportAt($warga);
        $safe = $this->route([[-0.005, 0], [-0.005, 0.02], [0.005, 0.02], [0.005, 0]], 5600, 4480);
        Http::fake([self::FOOT_URL.'/*' => Http::response(['code' => 'Ok', 'routes' => [$this->route(), $safe]])]);

        $this->requestRoute()->assertOk()
            ->assertJsonPath('engine', 'osrm_foot')
            ->assertJsonPath('distance_m', 5600)
            ->assertJsonPath('summary.report_count', 0)
            ->assertJsonPath('colliding_points', [])
            ->assertJsonPath('hazard_sections', [])
            ->assertJsonPath('obstacles', [])
            ->assertJsonPath('meta.strict_report_avoidance', true);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), self::FOOT_URL.'/') && str_contains($request->url(), 'radiuses=500%3B500'));
    }

    public function test_fastest_shows_only_encountered_reports_and_clipped_hazard_sections(): void
    {
        $warga = $this->authenticate();
        $hit = $this->reportAt($warga);
        $this->reportAt($warga, 0, 0.001);
        $this->reportAt($warga, 0.001, 0, 'SUDAH_DIPERBAIKI');
        $this->reportAt($warga, -0.001, 0, null, 'MENUNGGU_VALIDASI_ADMIN');
        Http::fake([self::FOOT_URL.'/*' => Http::response(['code' => 'Ok', 'routes' => [$this->route()]])]);

        $response = $this->requestRoute('fastest')->assertOk()
            ->assertJsonPath('summary.report_count', 1)
            ->assertJsonPath('colliding_points.0.laporan_id', $hit)
            ->assertJsonCount(1, 'obstacles')
            ->assertJsonCount(1, 'hazard_sections')
            ->assertJsonPath('summary.repair_distance_m', 0)
            ->assertJsonPath('warnings.0.code', 'REPORT_ON_ROUTE');
        $this->assertEqualsWithDelta(50, $response->json('summary.hazard_distance_m'), 0.1);
        $this->assertNotContains('ACTIVE_REPAIR', array_column($response->json('warnings'), 'code'));
        Http::assertSentCount(1);
    }

    public function test_fastest_selects_lowest_walking_duration_instead_of_provider_order(): void
    {
        $this->authenticate();
        Http::fake([self::FOOT_URL.'/*' => Http::response(['code' => 'Ok', 'routes' => [$this->route(duration: 1000), $this->route(distance: 1200, duration: 800)]])]);

        $this->requestRoute('fastest')->assertOk()->assertJsonPath('duration_s', 800)->assertJsonPath('distance_m', 1200);
        Http::assertSentCount(1);
    }

    public function test_safest_searches_foot_waypoints_when_direct_candidates_hit_reports(): void
    {
        $warga = $this->authenticate();
        $this->reportAt($warga);
        $safe = $this->route([[-0.005, 0], [0, 0.002], [0.005, 0]], 1400, 1120);
        Http::fake([self::FOOT_URL.'/*' => function ($request) use ($safe) {
            $coordinates = explode(';', basename(parse_url($request->url(), PHP_URL_PATH)));

            return Http::response(['code' => 'Ok', 'routes' => [count($coordinates) > 2 ? $safe : $this->route()]]);
        }]);

        $this->requestRoute()->assertOk()->assertJsonPath('distance_m', 1400)->assertJsonPath('colliding_points', []);
        Http::assertSentCount(9);
    }

    public function test_safest_returns_a_labeled_walking_route_with_hazards_when_no_clear_candidate_is_found(): void
    {
        $warga = $this->authenticate();
        $this->reportAt($warga);
        config(['services.osrm.search_requests' => 8]);
        Http::fake([self::FOOT_URL.'/*' => Http::response(['code' => 'Ok', 'routes' => [$this->route()]])]);

        $response = $this->requestRoute()->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('engine', 'osrm_foot')
            ->assertJsonPath('summary.safer_fallback', true)->assertJsonPath('summary.remaining_report_count', 1)
            ->assertJsonPath('summary.report_free', false)->assertJsonPath('meta.strict_report_avoidance', false)
            ->assertJsonCount(1, 'hazard_sections')->assertJsonCount(2, 'polyline');
        $this->assertContains('SAFEST_AVAILABLE_WITH_REPORTS', array_column($response->json('warnings'), 'code'));
        Http::assertSentCount(9);
    }

    public function test_safest_checks_new_reports_on_detours_and_preserves_multiple_waypoints(): void
    {
        $warga = $this->authenticate();
        $this->reportAt($warga);
        $this->reportAt($warga, 0, 0.002);
        $blockedDetour = $this->route([[-0.005, 0], [-0.005, 0.002], [0.005, 0.002], [0.005, 0]], 1600, 1280);
        $safe = $this->route([[-0.005, 0], [-0.005, 0.004], [0.005, 0.004], [0.005, 0]], 2000, 1600);
        Http::fake([self::FOOT_URL.'/*' => function ($request) use ($blockedDetour, $safe) {
            $count = count(explode(';', basename(parse_url($request->url(), PHP_URL_PATH))));

            return Http::response(['code' => 'Ok', 'routes' => [match (true) {
                $count >= 4 => $safe,
                $count === 3 => $blockedDetour,
                default => $this->route(),
            }]]);
        }]);

        $this->requestRoute()->assertOk()->assertJsonPath('distance_m', 2000)->assertJsonPath('colliding_points', []);
        Http::assertSent(fn ($request): bool => count(explode(';', basename(parse_url($request->url(), PHP_URL_PATH)))) >= 4);
    }

    public function test_report_data_failure_is_not_treated_as_a_report_free_route(): void
    {
        $this->authenticate();
        $this->mock(RouteReportRepository::class)->shouldReceive('active')->once()
            ->andThrow(new RouteUnavailableException('REPORT_DATA_UNAVAILABLE', 'Data laporan tidak dapat diperiksa.', 503));

        $this->requestRoute()->assertStatus(503)->assertJsonPath('error.code', 'REPORT_DATA_UNAVAILABLE');
        Http::assertNothingSent();
    }

    public function test_safest_can_exit_a_report_zone_at_the_origin_and_warns_about_unavoidable_exposure(): void
    {
        $warga = $this->authenticate();
        $this->reportAt($warga, -0.005);
        Http::fake([self::FOOT_URL.'/*' => Http::response(['code' => 'Ok', 'routes' => [$this->route()]])]);

        $this->requestRoute()->assertOk()
            ->assertJsonPath('summary.endpoint_report_count', 1)
            ->assertJsonPath('colliding_points.0.endpoint_unavoidable', true)
            ->assertJsonPath('meta.endpoint_zone_exception', true)
            ->assertJsonPath('meta.strict_report_avoidance', false)
            ->assertJsonPath('warnings.0.code', 'REPORT_ON_ROUTE')
            ->assertJsonCount(1, 'hazard_sections');
        Http::assertSentCount(1);
    }

    public function test_unverified_osrm_server_cannot_be_used_as_a_foot_engine(): void
    {
        $this->authenticate();
        config(['services.osrm.url' => 'https://router.project-osrm.org/route/v1/foot', 'services.osrm.foot_profile_verified' => false]);

        $this->requestRoute()->assertStatus(503)->assertJsonPath('error.code', 'PEDESTRIAN_ENGINE_REQUIRED');
        Http::assertNothingSent();
    }

    public function test_safest_prefers_less_unavoidable_endpoint_exposure_over_shorter_walking_time(): void
    {
        $warga = $this->authenticate();
        $this->reportAt($warga, -0.0048);
        $quickExit = $this->route([[-0.005, 0], [-0.005, 0.001], [0.005, 0.001], [0.005, 0]], 1400, 1120);
        Http::fake([self::FOOT_URL.'/*' => Http::response(['code' => 'Ok', 'routes' => [$this->route(), $quickExit]])]);

        $response = $this->requestRoute()->assertOk()->assertJsonPath('distance_m', 1400)->assertJsonPath('summary.endpoint_report_count', 1);
        $this->assertLessThan(15, $response->json('summary.hazard_distance_m'));
        Http::assertSentCount(1);
    }

    public function test_provider_failure_does_not_return_a_straight_line_route(): void
    {
        $this->authenticate();
        Http::fake([self::FOOT_URL.'/*' => Http::failedConnection()]);

        $this->requestRoute()->assertStatus(503)->assertJsonPath('error.code', 'PEDESTRIAN_ENGINE_UNAVAILABLE')->assertJsonMissingPath('polyline');
        Http::assertSentCount(2);
    }

    public function test_transient_provider_failure_is_retried_before_returning_an_error(): void
    {
        $this->authenticate();
        Http::fake([self::FOOT_URL.'/*' => Http::sequence()
            ->push([], 503)
            ->push(['code' => 'Ok', 'routes' => [$this->route()]])]);

        $this->requestRoute('fastest')->assertOk()->assertJsonPath('engine', 'osrm_foot');
        Http::assertSentCount(2);
    }

    public function test_cached_geometry_is_reused_but_new_reports_are_always_rechecked(): void
    {
        $warga = $this->authenticate();
        Http::fake([self::FOOT_URL.'/*' => Http::response(['code' => 'Ok', 'routes' => [$this->route()]])]);
        $this->requestRoute('fastest')->assertOk()->assertJsonPath('summary.report_count', 0);
        $this->reportAt($warga);

        $this->requestRoute('fastest')->assertOk()->assertJsonPath('summary.report_count', 1)->assertJsonPath('meta.provider_cache', 'fresh');
        Http::assertSentCount(1);
    }

    public function test_provider_outage_uses_backup_foot_geometry_and_current_report_data(): void
    {
        $warga = $this->authenticate();
        config(['services.osrm.cache_seconds' => 1]);
        Http::fake([self::FOOT_URL.'/*' => Http::sequence()
            ->push(['code' => 'Ok', 'routes' => [$this->route()]])
            ->push([], 503)->push([], 503)]);
        $this->requestRoute('fastest')->assertOk();
        $this->travel(2)->seconds();
        $this->reportAt($warga);

        $this->requestRoute('fastest')->assertOk()
            ->assertJsonPath('meta.provider_cache', 'stale')
            ->assertJsonPath('summary.report_count', 1);
        Http::assertSentCount(3);
    }

    public function test_long_provider_rate_limit_is_not_hammered_with_immediate_retries(): void
    {
        $this->authenticate();
        Http::fake([self::FOOT_URL.'/*' => Http::response([], 429, ['Retry-After' => '60'])]);

        $this->requestRoute('fastest')->assertStatus(503)->assertJsonPath('error.code', 'PEDESTRIAN_ENGINE_UNAVAILABLE');
        $this->requestRoute('fastest')->assertStatus(503)->assertJsonPath('error.code', 'PEDESTRIAN_ENGINE_UNAVAILABLE');
        Http::assertSentCount(1);
    }

    public function test_driving_geometry_is_rejected_even_when_endpoint_is_labelled_foot(): void
    {
        $this->authenticate();
        $route = $this->route();
        $route['legs'] = [['steps' => [['mode' => 'driving']]]];
        Http::fake([self::FOOT_URL.'/*' => Http::response(['code' => 'Ok', 'routes' => [$route]])]);

        $this->requestRoute()->assertStatus(503)->assertJsonPath('error.code', 'PEDESTRIAN_ROUTE_UNAVAILABLE');
        Http::assertSentCount(1);
    }

    public function test_provider_snap_far_from_requested_origin_is_rejected(): void
    {
        $this->authenticate();
        Http::fake([self::FOOT_URL.'/*' => Http::response(['code' => 'Ok', 'routes' => [$this->route([[0.001, 0], [0.005, 0]])]])]);

        $this->requestRoute()->assertStatus(503)->assertJsonPath('error.code', 'PEDESTRIAN_ROUTE_UNAVAILABLE');
        Http::assertSentCount(1);
    }

    public function test_place_pin_inside_hospital_or_park_can_snap_to_connected_foot_network(): void
    {
        $this->authenticate();
        Http::fake([self::FOOT_URL.'/*' => Http::response(['code' => 'Ok', 'routes' => [$this->route([[-0.005, 0.002], [0.005, 0]])]])]);

        $this->requestRoute()->assertOk()
            ->assertJsonPath('snapped_origin.lat', 0.002)
            ->assertJsonPath('warnings.0.code', 'ENDPOINT_SNAPPED')
            ->assertJsonPath('polyline.0', [0.002, -0.005]);
        Http::assertSentCount(1);
    }

    public function test_origin_report_exception_does_not_exempt_other_reports_along_the_route(): void
    {
        $warga = $this->authenticate();
        $this->reportAt($warga, -0.005);
        $this->reportAt($warga);
        config(['services.osrm.search_requests' => 8]);
        Http::fake([self::FOOT_URL.'/*' => Http::response(['code' => 'Ok', 'routes' => [$this->route()]])]);

        $this->requestRoute()->assertOk()
            ->assertJsonPath('summary.endpoint_report_count', 1)
            ->assertJsonPath('summary.remaining_report_count', 1)
            ->assertJsonPath('summary.safer_fallback', true);
        Http::assertSentCount(9);
    }

    public function test_long_clear_detours_are_considered_beyond_the_nearby_report_street(): void
    {
        $warga = $this->authenticate();
        $this->reportAt($warga);
        $clear = $this->route([[-0.005, 0], [-0.005, 0.04], [0.005, 0.04], [0.005, 0]], 10000, 8000);
        Http::fake([self::FOOT_URL.'/*' => function ($request) use ($clear) {
            $coordinates = array_map(fn ($value): array => array_map('floatval', explode(',', $value)), explode(';', basename(parse_url($request->url(), PHP_URL_PATH))));
            $wide = count($coordinates) >= 4 && max(array_map(fn ($point): float => abs($point[1]), $coordinates)) > 0.02;

            return Http::response(['code' => 'Ok', 'routes' => [$wide ? $clear : $this->route()]]);
        }]);

        $this->requestRoute()->assertOk()
            ->assertJsonPath('distance_m', 10000)->assertJsonPath('summary.report_free', true)
            ->assertJsonPath('summary.safer_fallback', false)->assertJsonPath('colliding_points', []);
        Http::assertSentCount(9);
    }

    public function test_safest_continues_past_an_earlier_turn_to_avoid_a_report_two_turns_ahead(): void
    {
        $warga = $this->authenticate();
        $this->reportAt($warga, -0.001, 0.002);
        $blocked = $this->route([[-0.005, 0], [-0.003, 0], [-0.003, 0.002], [0.002, 0.002], [0.002, 0], [0.005, 0]], 1800, 1440);
        $makeStep = fn (array $line, int $before, int $after): array => [
            'name' => 'Test pedestrian street', 'distance' => 200,
            'geometry' => ['coordinates' => $line],
            'maneuver' => ['type' => 'turn', 'location' => $line[0], 'bearing_before' => $before, 'bearing_after' => $after],
        ];
        $blocked['legs'] = [['steps' => [
            $makeStep([[-0.005, 0], [-0.003, 0]], 90, 90),
            $makeStep([[-0.003, 0], [-0.003, 0.002]], 90, 0),
            $makeStep([[-0.003, 0.002], [0.002, 0.002]], 0, 90),
            $makeStep([[0.002, 0.002], [0.002, 0]], 90, 180),
            $makeStep([[0.002, 0], [0.005, 0]], 180, 90),
        ]]];
        Http::fake([self::FOOT_URL.'/*' => function ($request) use ($blocked) {
            $coordinates = array_map(fn ($value): array => array_map('floatval', explode(',', $value)), explode(';', basename(parse_url($request->url(), PHP_URL_PATH))));
            $continuesApproach = count($coordinates) === 3 && abs($coordinates[1][1]) < 0.00001 && $coordinates[1][0] > -0.0025 && $coordinates[1][0] < -0.0001;

            return Http::response(['code' => 'Ok', 'routes' => [$continuesApproach ? $this->route() : $blocked]]);
        }]);

        $this->requestRoute()->assertOk()->assertJsonPath('summary.report_free', true)
            ->assertJsonPath('summary.safer_fallback', false)->assertJsonPath('colliding_points', [])
            ->assertJsonPath('distance_m', 1112);
        Http::assertSent(fn ($request): bool => substr_count(parse_url($request->url(), PHP_URL_PATH), ';') === 2);
    }

    public function test_safest_fallback_selects_fewer_reports_even_when_the_route_is_much_longer(): void
    {
        $warga = $this->authenticate();
        $this->reportAt($warga);
        $this->reportAt($warga, 0.002);
        config(['services.osrm.search_requests' => 0]);
        $lessExposed = $this->route([[-0.005, 0], [-0.005, 0.003], [0, 0], [0.005, 0.003], [0.005, 0]], 4000, 3200);
        Http::fake([self::FOOT_URL.'/*' => Http::response(['code' => 'Ok', 'routes' => [$this->route(), $lessExposed]])]);

        $this->requestRoute()->assertOk()->assertJsonPath('distance_m', 4000)
            ->assertJsonPath('summary.remaining_report_count', 1)->assertJsonPath('summary.safer_fallback', true);
        Http::assertSentCount(1);
    }

    public function test_failed_detour_provider_keeps_a_valid_foot_route_with_explicit_remaining_hazards(): void
    {
        $warga = $this->authenticate();
        $this->reportAt($warga);
        Http::fake([self::FOOT_URL.'/*' => Http::sequence()
            ->push(['code' => 'Ok', 'routes' => [$this->route()]])
            ->push([], 503)->push([], 503)]);

        $response = $this->requestRoute()->assertOk()
            ->assertJsonPath('summary.safer_fallback', true)
            ->assertJsonPath('meta.avoidance_search_interrupted', true)
            ->assertJsonPath('summary.report_count', 1);
        $this->assertContains('AVOIDANCE_SEARCH_INTERRUPTED', array_column($response->json('warnings'), 'code'));
        Http::assertSentCount(3);
    }

    public function test_safest_uses_actual_residential_street_vertices_to_bypass_a_reported_main_road(): void
    {
        $warga = $this->authenticate();
        $this->reportAt($warga);
        config(['services.osrm.residential_detours' => true]);
        $clear = $this->route([[-0.005, 0], [-0.005, 0.002], [0.005, 0.002], [0.005, 0]], 1600, 1280);
        Http::fake([
            'https://photon.komoot.io/api/*' => Http::response(['features' => [[
                'properties' => ['osm_type' => 'W', 'osm_id' => 101, 'osm_value' => 'residential'],
                // A street's bounding-box centre is deliberately not on its actual geometry.
                'geometry' => ['type' => 'Point', 'coordinates' => [0, 0]],
            ]]]),
            'https://api.openstreetmap.org/api/0.6/way/101/full.json' => Http::response(['elements' => [
                ['type' => 'node', 'id' => 1, 'lat' => 0.002, 'lon' => -0.001],
                ['type' => 'node', 'id' => 2, 'lat' => 0.002, 'lon' => 0.001],
                ['type' => 'way', 'id' => 101, 'nodes' => [1, 2], 'tags' => ['highway' => 'residential', 'name' => 'Housing street']],
            ]]),
            self::FOOT_URL.'/*' => function ($request) use ($clear) {
                return Http::response(['code' => 'Ok', 'routes' => [str_contains($request->url(), '-0.0003333,0.0020000;0.0003333,0.0020000') ? $clear : $this->route()]]);
            },
        ]);

        $this->requestRoute()->assertOk()->assertJsonPath('summary.report_free', true)
            ->assertJsonPath('summary.safer_fallback', false)->assertJsonPath('distance_m', 1600)
            ->assertJsonPath('colliding_points', []);
        Http::assertSent(fn ($request): bool => ($request['osm_tag'] ?? null) === 'highway:residential');
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/way/101/full.json'));
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '-0.0003333,0.0020000;0.0003333,0.0020000'));
    }

    public function test_private_residential_streets_are_not_used_as_detour_anchors(): void
    {
        $warga = $this->authenticate();
        $this->reportAt($warga);
        config(['services.osrm.residential_detours' => true, 'services.osrm.search_requests' => 0]);
        Http::fake([
            'https://photon.komoot.io/api/*' => Http::response(['features' => [[
                'properties' => ['osm_type' => 'W', 'osm_id' => 102, 'osm_value' => 'residential'],
            ]]]),
            'https://api.openstreetmap.org/api/0.6/way/102/full.json' => Http::response(['elements' => [
                ['type' => 'node', 'id' => 1, 'lat' => 0.002, 'lon' => 0],
                ['type' => 'way', 'id' => 102, 'nodes' => [1], 'tags' => ['highway' => 'residential', 'access' => 'private']],
            ]]),
            self::FOOT_URL.'/*' => Http::response(['code' => 'Ok', 'routes' => [$this->route()]]),
        ]);

        $this->requestRoute()->assertOk()->assertJsonPath('summary.safer_fallback', true);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/route/v1/foot/') && substr_count(parse_url($request->url(), PHP_URL_PATH), ';') > 1);
        Http::assertSentCount(3);
    }

    public function test_residential_lookup_outage_resolves_the_named_street_on_the_foot_graph_not_the_main_road(): void
    {
        $warga = $this->authenticate();
        $this->reportAt($warga);
        config(['services.osrm.residential_detours' => true]);
        $clear = $this->route([[-0.005, 0], [-0.005, 0.001], [0.005, 0.001], [0.005, 0]], 1400, 1120);
        Http::fake([
            'https://photon.komoot.io/api/*' => Http::response(['features' => [[
                'properties' => ['osm_type' => 'W', 'osm_id' => 103, 'osm_value' => 'residential', 'name' => 'Jalan Housing Street'],
                'geometry' => ['type' => 'Point', 'coordinates' => [0, 0.0011]],
            ]]]),
            'https://api.openstreetmap.org/api/0.6/way/103/full.json' => Http::response([], 503),
            'https://routing.openstreetmap.de/routed-foot/nearest/v1/foot/*' => Http::response(['code' => 'Ok', 'waypoints' => [
                ['name' => 'Main Road', 'location' => [0, 0], 'distance' => 5],
                ['name' => 'Jalan Housing Street', 'location' => [0, 0.001], 'distance' => 12],
            ]]),
            self::FOOT_URL.'/*' => function ($request) use ($clear) {
                return Http::response(['code' => 'Ok', 'routes' => [str_contains($request->url(), ';0.0000000,0.0010000;') ? $clear : $this->route()]]);
            },
        ]);

        $this->requestRoute()->assertOk()->assertJsonPath('summary.report_free', true)
            ->assertJsonPath('distance_m', 1400)->assertJsonPath('colliding_points', []);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/nearest/v1/foot/'));
    }
}
