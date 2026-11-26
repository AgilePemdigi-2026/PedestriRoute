<?php

namespace Tests\Feature\Route;

use App\Models\Warga;
use App\Services\ValhallaPedestrianClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ValhallaRoutingTest extends TestCase
{
    use DatabaseTransactions;

    private const VALHALLA = 'https://valhalla1.openstreetmap.de/route';

    private const OSRM = 'https://routing.openstreetmap.de/routed-foot/route/v1/foot';

    private Warga $warga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->warga = Warga::create([
            'nama' => 'Valhalla test', 'email' => uniqid().'@valhalla.test', 'no_hp' => '628'.random_int(100000000, 999999999),
            'password' => 'password123', 'tanggal_daftar' => now(), 'gagal_login' => 0,
        ]);
        Sanctum::actingAs($this->warga, ['role:warga']);
        config(['services.routing_graph.engine' => 'auto', 'services.osrm.url' => self::OSRM, 'services.osrm.residential_detours' => false, 'services.osrm.report_tolerance' => 0]);
        Http::preventStrayRequests();
        Sleep::fake();
    }

    /** Polyline encoding with 6-digit precision, as Valhalla returns. */
    private function encode(array $coordinates): string
    {
        $output = '';
        $previous = [0, 0];
        foreach ($coordinates as [$lng, $lat]) {
            $current = [(int) round($lat * 1e6), (int) round($lng * 1e6)];
            foreach ([0, 1] as $axis) {
                $value = $current[$axis] - $previous[$axis];
                $value = $value < 0 ? ~($value << 1) : $value << 1;
                while ($value >= 0x20) {
                    $output .= chr((0x20 | ($value & 0x1F)) + 63);
                    $value >>= 5;
                }
                $output .= chr($value + 63);
            }
            $previous = $current;
        }

        return $output;
    }

    private function trip(array $coordinates, float $km): array
    {
        $last = count($coordinates) - 1;

        return [
            'legs' => [[
                'shape' => $this->encode($coordinates),
                'maneuvers' => [
                    ['type' => 1, 'instruction' => 'Walk east.', 'begin_shape_index' => 0, 'end_shape_index' => $last, 'length' => $km, 'time' => $km * 720, 'street_names' => ['Jl. Uji Valhalla']],
                    ['type' => 4, 'instruction' => 'You have arrived.', 'begin_shape_index' => $last, 'end_shape_index' => $last, 'length' => 0, 'time' => 0],
                ],
            ]],
            'summary' => ['length' => $km, 'time' => $km * 720],
            'status' => 0,
        ];
    }

    private function direct(): array
    {
        return $this->trip([[-0.005, 0], [0.005, 0]], 1.112);
    }

    private function detour(): array
    {
        return $this->trip([[-0.005, 0], [-0.005, 0.002], [0.005, 0.002], [0.005, 0]], 1.557);
    }

    private function reportAt(float $lng, float $lat): void
    {
        $location = DB::table('lokasi')->insertGetId([
            'latitude' => $lat, 'longitude' => $lng,
            'geom' => DB::raw("ST_SetSRID(ST_Point({$lng}, {$lat}), 4326)"), 'alamat' => 'Valhalla report',
        ], 'lokasi_id');
        DB::table('laporan')->insert([
            'warga_id' => $this->warga->warga_id, 'kategori_id' => 1, 'lokasi_id' => $location,
            'deskripsi' => 'Rusak', 'status' => 'TERVALIDASI', 'tanggal_lapor' => now(),
        ]);
    }

    private function requestRoute(string $preference)
    {
        return $this->postJson('/api/v1/routes/recommend', [
            'origin' => ['lat' => 0, 'lng' => -0.005], 'destination' => ['lat' => 0, 'lng' => 0.005], 'preference' => $preference,
        ]);
    }

    public function test_polyline6_decoding_matches_the_reference_algorithm(): void
    {
        $client = new ValhallaPedestrianClient;
        // Google's reference string decoded at precision 6 is the precision-5 example divided by 10.
        $this->assertSame([[-12.02, 3.85], [-12.095, 4.07], [-12.6453, 4.3252]], $client->decode('_p~iF~ps|U_ulLnnqC_mqNvxq`@'));
        $line = [[112.795123, -7.266001], [112.796789, -7.267456]];
        $this->assertSame($line, $client->decode($this->encode($line)));
    }

    public function test_fastest_uses_valhalla_shortest_distance_with_indonesian_instructions(): void
    {
        Http::fake([self::VALHALLA => Http::response(['trip' => $this->direct()])]);

        $response = $this->requestRoute('fastest')->assertOk()
            ->assertJsonPath('engine', 'valhalla_pedestrian')
            ->assertJsonPath('meta.algorithm', 'valhalla_wsm')
            ->assertJsonPath('distance_m', 1112);
        $this->assertStringContainsString('Jl. Uji Valhalla', $response->json('steps.0.instruction'));
        $this->assertSame('Anda telah tiba di tujuan.', collect($response->json('steps'))->last()['instruction']);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::VALHALLA
            && $request['costing'] === 'pedestrian' && $request['costing_options']['pedestrian'] === ['shortest' => true]);
        Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), self::OSRM));
    }

    public function test_safest_excludes_report_zones_and_returns_the_clear_detour(): void
    {
        $this->reportAt(0, 0);
        Http::fake([self::VALHALLA => fn (Request $request) => Http::response(['trip' => isset($request['exclude_polygons']) ? $this->detour() : $this->direct()])]);

        $this->requestRoute('safest')->assertOk()
            ->assertJsonPath('engine', 'valhalla_pedestrian')
            ->assertJsonPath('distance_m', 1557)
            ->assertJsonPath('summary.report_free', true)
            ->assertJsonPath('summary.safer_fallback', false);

        Http::assertSent(function (Request $request): bool {
            $rings = $request['exclude_polygons'] ?? [];
            if (count($rings) !== 1 || count($rings[0]) !== 13 || $rings[0][0] !== $rings[0][12]) {
                return false;
            }
            $pedestrian = $request['costing_options']['pedestrian'];
            // Circumscribed 12-gon around the 25 m zone (+2 m margin): every vertex ≈ 27.95 m away.
            $north = $rings[0][0];

            return $pedestrian['alley_factor'] === 1.0 && $pedestrian['sidewalk_factor'] === 0.8
                && abs($north[0]) < 1e-7 && abs($north[1] * 111195 - 27.0 / cos(M_PI / 12)) < 0.05;
        });
    }

    public function test_safest_adds_reports_hit_by_each_detour_until_the_route_is_clear(): void
    {
        $this->reportAt(0, 0);
        $this->reportAt(0, 0.002);
        $firstDetour = $this->trip([[-0.005, 0], [-0.005, 0.002], [0.005, 0.002], [0.005, 0]], 1.557);
        $secondDetour = $this->trip([[-0.005, 0], [-0.005, 0.004], [0.005, 0.004], [0.005, 0]], 2.002);
        Http::fake([self::VALHALLA => fn (Request $request) => Http::response(['trip' => match (count($request['exclude_polygons'] ?? [])) {
            0 => $this->direct(), 1 => $firstDetour, default => $secondDetour,
        }])]);

        $this->requestRoute('safest')->assertOk()
            ->assertJsonPath('distance_m', 2002)
            ->assertJsonPath('summary.report_free', true);
        Http::assertSent(fn (Request $request): bool => count($request['exclude_polygons'] ?? []) === 2);
    }

    public function test_reports_near_the_snapped_start_are_never_sent_as_exclusions(): void
    {
        // The pin is 55 m from this report, but the road it snaps to is only 22 m away.
        $this->reportAt(-0.005, 0.0005);
        $this->reportAt(0, 0.0003);
        $base = $this->trip([[-0.005, 0.0003], [0.005, 0.0003]], 1.112);
        $clear = $this->trip([[-0.005, 0.0003], [-0.005, 0.002], [0.005, 0.002], [0.005, 0.0003]], 1.490);
        Http::fake([self::VALHALLA => function (Request $request) use ($base, $clear) {
            foreach ($request['exclude_polygons'] ?? [] as $ring) {
                if (abs($ring[0][0] + 0.005) < 0.0002) {
                    return Http::response(['error_code' => 171, 'error' => 'No suitable edges near location', 'status_code' => 400], 400);
                }
            }

            return Http::response(['trip' => isset($request['exclude_polygons']) ? $clear : $base]);
        }]);

        $this->requestRoute('safest')->assertOk()
            ->assertJsonPath('distance_m', 1490)
            ->assertJsonPath('summary.remaining_report_count', 0)
            ->assertJsonPath('summary.endpoint_report_count', 1);
    }

    public function test_reports_at_the_start_or_destination_are_not_excluded(): void
    {
        $this->reportAt(-0.005, 0);
        Http::fake([self::VALHALLA => Http::response(['trip' => $this->direct()])]);

        $this->requestRoute('safest')->assertOk()->assertJsonPath('summary.endpoint_report_count', 1);
        Http::assertNotSent(fn (Request $request): bool => isset($request['exclude_polygons']));
    }

    public function test_safest_refuses_and_balanced_discloses_when_valhalla_finds_no_clear_path(): void
    {
        $this->reportAt(0, 0);
        Http::fake([self::VALHALLA => fn (Request $request) => isset($request['exclude_polygons'])
            ? Http::response(['error_code' => 442, 'error' => 'No path could be found for input', 'status_code' => 400], 400)
            : Http::response(['trip' => $this->direct()])]);

        $this->requestRoute('safest')->assertStatus(422)->assertJsonPath('error.code', 'NO_REPORT_FREE_ROUTE');
        $response = $this->requestRoute('balanced')->assertOk()
            ->assertJsonPath('engine', 'valhalla_pedestrian')
            ->assertJsonPath('summary.safer_fallback', true)
            ->assertJsonPath('summary.remaining_report_count', 1);
        $this->assertContains('SAFEST_AVAILABLE_WITH_REPORTS', array_column($response->json('warnings'), 'code'));
    }

    public function test_only_balanced_caps_the_valhalla_detour(): void
    {
        $this->reportAt(0, 0);
        $far = $this->trip([[-0.005, 0], [-0.005, 0.02], [0.005, 0.02], [0.005, 0]], 5.56);
        Http::fake([self::VALHALLA => fn (Request $request) => Http::response(['trip' => isset($request['exclude_polygons']) ? $far : $this->direct()])]);

        $this->requestRoute('balanced')->assertOk()->assertJsonPath('distance_m', 1112)->assertJsonPath('summary.safer_fallback', true);
        $this->requestRoute('safest')->assertOk()->assertJsonPath('distance_m', 5560)->assertJsonPath('summary.report_free', true);
    }

    public function test_balanced_prefers_passing_one_report_over_a_costly_valhalla_detour(): void
    {
        $this->reportAt(0, 0);
        config(['services.osrm.report_tolerance' => 3, 'services.osrm.report_penalty_m' => 300]);
        Http::fake([self::VALHALLA => fn (Request $request) => Http::response(['trip' => isset($request['exclude_polygons']) ? $this->detour() : $this->direct()])]);

        // Unscored roads: detour 1557 × 1.9 = 2958 vs direct 1112 × 1.9 + 300 = 2413.
        $this->requestRoute('balanced')->assertOk()->assertJsonPath('distance_m', 1112)->assertJsonPath('summary.remaining_report_count', 1);

        config(['services.osrm.report_penalty_m' => 1000]);
        $this->requestRoute('balanced')->assertOk()->assertJsonPath('distance_m', 1557)->assertJsonPath('summary.report_free', true);
    }

    public function test_balanced_crosses_one_report_instead_of_the_long_teraman_detour(): void
    {
        // Four reports along the direct street: the shortest route passes 4 > T = 3.
        foreach ([-0.003, -0.001, 0.001, 0.003] as $lng) {
            $this->reportAt($lng, 0);
        }
        config(['services.osrm.report_tolerance' => 3, 'services.osrm.report_penalty_m' => 300]);
        $farClear = $this->trip([[-0.005, 0], [-0.005, 0.01], [0.005, 0.01], [0.005, 0]], 3.336);
        // Re-opening one report lets the walk touch the street once at x = -0.001.
        $crossOnce = $this->trip([[-0.005, 0], [-0.005, 0.0008], [-0.0012, 0.0008], [-0.001, 0], [-0.0008, 0.0008], [0.005, 0.0008], [0.005, 0]], 1.43);
        Http::fake([self::VALHALLA => fn (Request $request) => Http::response(['trip' => match (count($request['exclude_polygons'] ?? [])) {
            0 => $this->direct(), 3 => $crossOnce, default => $farClear,
        }])]);

        $this->requestRoute('balanced')->assertOk()
            ->assertJsonPath('distance_m', 1430)
            ->assertJsonPath('summary.remaining_report_count', 1);
        $this->requestRoute('safest')->assertOk()
            ->assertJsonPath('distance_m', 3336)
            ->assertJsonPath('summary.report_free', true);
    }

    public function test_valhalla_outage_falls_back_to_osrm(): void
    {
        Http::fake([
            self::VALHALLA => Http::response([], 503),
            self::OSRM.'/*' => Http::response(['code' => 'Ok', 'routes' => [[
                'distance' => 1112, 'duration' => 890, 'legs' => [],
                'geometry' => ['type' => 'LineString', 'coordinates' => [[-0.005, 0], [0.005, 0]]],
            ]]]),
        ]);

        $this->requestRoute('fastest')->assertOk()->assertJsonPath('engine', 'osrm_foot');
    }
}
