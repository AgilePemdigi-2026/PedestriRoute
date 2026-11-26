<?php

namespace Tests\Feature\Route;

use App\Models\Warga;
use App\Services\RouteHazardGeometry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A* over segmen_jalur on an 8 × 8 grid (~110 m blocks) far from real data.
 * Node (i, j) sits at (LNG0 + 0.001 i, LAT0 + 0.001 j).
 */
class GraphRoutingTest extends TestCase
{
    use DatabaseTransactions;

    private const LNG0 = 112.9;

    private const LAT0 = -7.4;

    private const SIZE = 8;

    /** @var array<string, int> "i,j-i,j" => segmen_id */
    private array $segments = [];

    private Warga $warga;

    protected function setUp(): void
    {
        parent::setUp();
        $this->warga = Warga::create([
            'nama' => 'Graph routing test', 'email' => uniqid().'@graph.test',
            'no_hp' => '628'.random_int(100000000, 999999999),
            'password' => 'password123', 'tanggal_daftar' => now(), 'gagal_login' => 0,
        ]);
        Sanctum::actingAs($this->warga, ['role:warga']);
        Http::preventStrayRequests();
        config(['services.routing_graph.engine' => 'auto', 'services.osrm.report_tolerance' => 0]);

        $wayId = 990000000;
        for ($i = 0; $i < self::SIZE; $i++) {
            for ($j = 0; $j < self::SIZE; $j++) {
                foreach ([[$i + 1, $j], [$i, $j + 1]] as [$ni, $nj]) {
                    if ($ni >= self::SIZE || $nj >= self::SIZE) {
                        continue;
                    }
                    $wkt = sprintf('SRID=4326;LINESTRING(%.7F %.7F, %.7F %.7F)', ...[...$this->coord($i, $j), ...$this->coord($ni, $nj)]);
                    $this->segments["$i,$j-$ni,$nj"] = DB::table('segmen_jalur')->insertGetId([
                        'osm_way_id' => $wayId++, 'u_node' => $i * 100 + $j, 'v_node' => $ni * 100 + $nj,
                        'nama_jalan' => $ni === $i ? "Jl. Grid Kolom $i" : "Jl. Grid Baris $j",
                        'geom' => DB::raw("ST_GeogFromText('$wkt')"), 'panjang_m' => 110, 'skor_kelayakan' => 100,
                    ], 'segmen_id');
                }
            }
        }
    }

    /** @return array{0: float, 1: float} [lng, lat] */
    private function coord(int $i, int $j): array
    {
        return [self::LNG0 + 0.001 * $i, self::LAT0 + 0.001 * $j];
    }

    private function point(int $i, int $j): array
    {
        [$lng, $lat] = $this->coord($i, $j);

        return ['lat' => $lat, 'lng' => $lng];
    }

    private function setScore(string $key, float $score): void
    {
        DB::table('segmen_jalur')->where('segmen_id', $this->segments[$key])->update(['skor_kelayakan' => $score]);
    }

    private function requestRoute(array $from, array $to, string $preference)
    {
        return $this->postJson('/api/v1/routes/recommend', ['origin' => $this->point(...$from), 'destination' => $this->point(...$to), 'preference' => $preference]);
    }

    private function reportAt(array $point, ?string $repair = null): void
    {
        $location = DB::table('lokasi')->insertGetId([
            'latitude' => $point['lat'], 'longitude' => $point['lng'],
            'geom' => DB::raw("ST_SetSRID(ST_Point({$point['lng']}, {$point['lat']}), 4326)"), 'alamat' => 'Graph report',
        ], 'lokasi_id');
        DB::table('laporan')->insert([
            'warga_id' => $this->warga->warga_id, 'kategori_id' => 1, 'lokasi_id' => $location,
            'deskripsi' => 'Graph hazard', 'status' => 'TERVALIDASI', 'status_perbaikan' => $repair, 'tanggal_lapor' => now(),
        ]);
    }

    /** Independent Dijkstra over the same grid and WSM formula (no heuristic). */
    private function dijkstraCost(array $from, array $to, array $scores, float $penalty): float
    {
        $geometry = new RouteHazardGeometry;
        $adjacency = [];
        foreach ($this->segments as $key => $id) {
            [$a, $b] = explode('-', $key);
            [$ai, $aj] = array_map('intval', explode(',', $a));
            [$bi, $bj] = array_map('intval', explode(',', $b));
            $length = $geometry->distance($this->point($ai, $aj), $this->point($bi, $bj));
            $cost = $length * (1 + ((100 - ($scores[$key] ?? 100)) / 100) * $penalty);
            $adjacency[$a][$b] = $cost;
            $adjacency[$b][$a] = $cost;
        }
        $source = implode(',', $from);
        $target = implode(',', $to);
        $distance = [$source => 0.0];
        $done = [];
        while (true) {
            $best = null;
            foreach ($distance as $node => $value) {
                if (! isset($done[$node]) && ($best === null || $value < $distance[$best])) {
                    $best = $node;
                }
            }
            if ($best === null || $best === $target) {
                return $distance[$target] ?? INF;
            }
            $done[$best] = true;
            foreach ($adjacency[$best] as $next => $cost) {
                $distance[$next] = min($distance[$next] ?? INF, $distance[$best] + $cost);
            }
        }
    }

    public function test_fastest_uses_a_star_on_the_graph_and_returns_the_shortest_path_without_osrm(): void
    {
        $response = $this->requestRoute([0, 0], [7, 0], 'fastest')->assertOk()
            ->assertJsonPath('engine', 'postgis_astar')
            ->assertJsonPath('meta.algorithm', 'astar_wsm');

        $this->assertEqualsWithDelta(7 * 110.4, $response->json('distance_m'), 7);
        $this->assertSame(array_values(array_map(fn (int $i): int => $this->segments["$i,0-".($i + 1).',0'], range(0, 6))),
            array_values(array_unique(array_column($response->json('segments'), 'segmen_id'))));
        $this->assertSame('arrive', collect($response->json('steps'))->last()['type']);
        Http::assertNothingSent();
    }

    public function test_safest_cost_equals_an_independent_dijkstra_with_the_wsm_formula(): void
    {
        mt_srand(20261126);
        $scores = [];
        foreach (array_keys($this->segments) as $key) {
            $scores[$key] = (float) mt_rand(0, 100);
            $this->setScore($key, $scores[$key]);
        }

        foreach ([[[0, 0], [7, 7]], [[0, 7], [6, 1]], [[3, 0], [3, 7]]] as [$from, $to]) {
            $response = $this->requestRoute($from, $to, 'safest')->assertOk()->assertJsonPath('engine', 'postgis_astar');
            $expected = $this->dijkstraCost($from, $to, $scores, 3.0);
            // A random-score optimum may exceed the detour cap; the cap is checked separately.
            if ($response->json('meta.graph_pass') === 'clear') {
                $this->assertEqualsWithDelta($expected, $response->json('meta.graph_cost'), 0.5, 'A* harus sama dengan Dijkstra untuk '.json_encode([$from, $to]));
            } else {
                $this->assertLessThanOrEqual($response->json('meta.max_detour_distance_m') + 0.5, $response->json('distance_m'));
            }
        }
    }

    public function test_safest_avoids_low_wsm_segments_that_fastest_uses(): void
    {
        foreach (range(1, 5) as $i) {
            $this->setScore("$i,0-".($i + 1).',0', 20);
        }

        $fastest = $this->requestRoute([0, 0], [7, 0], 'fastest')->assertOk();
        $safest = $this->requestRoute([0, 0], [7, 0], 'safest')->assertOk()->assertJsonPath('meta.graph_pass', 'clear');

        $low = $this->segments['3,0-4,0'];
        $this->assertContains($low, array_column($fastest->json('segments'), 'segmen_id'));
        $this->assertNotContains($low, array_column($safest->json('segments'), 'segmen_id'));
        $this->assertGreaterThan($fastest->json('distance_m'), $safest->json('distance_m'));
        $this->assertNull($safest->json('meta.max_detour_distance_m'));
        $this->assertGreaterThan($fastest->json('summary.average_score'), $safest->json('summary.average_score'));
    }

    public function test_safest_blocks_active_report_zones_and_fastest_reports_them(): void
    {
        $this->reportAt(['lat' => self::LAT0, 'lng' => self::LNG0 + 0.0035]);

        $this->requestRoute([0, 0], [7, 0], 'fastest')->assertOk()->assertJsonPath('summary.report_count', 1);
        $safest = $this->requestRoute([0, 0], [7, 0], 'safest')->assertOk()
            ->assertJsonPath('summary.report_free', true)
            ->assertJsonPath('summary.safer_fallback', false)
            ->assertJsonPath('meta.graph_pass', 'clear');
        // The bypass is one block up and back: 7 + 2 blocks.
        $this->assertEqualsWithDelta(9 * 110.4, $safest->json('distance_m'), 9);
    }

    public function test_detour_cap_limits_balanced_while_safest_refuses(): void
    {
        config(['services.osrm.safest_max_detour_ratio' => 1.0, 'services.osrm.safest_max_detour_extra_m' => 100]);
        $this->reportAt(['lat' => self::LAT0, 'lng' => self::LNG0 + 0.0035]);

        // The only clear bypass (+2 blocks) exceeds the 872 m cap: Teraman still takes it.
        $this->requestRoute([0, 0], [7, 0], 'safest')->assertOk()->assertJsonPath('summary.report_free', true);
        $response = $this->requestRoute([0, 0], [7, 0], 'balanced')->assertOk()
            ->assertJsonPath('summary.safer_fallback', true)
            ->assertJsonPath('summary.remaining_report_count', 1);
        $this->assertLessThanOrEqual($response->json('meta.max_detour_distance_m') + 0.5, $response->json('distance_m'));
        $this->assertContains('SAFEST_AVAILABLE_WITH_REPORTS', array_column($response->json('warnings'), 'code'));
    }

    public function test_balanced_passes_a_report_when_the_bypass_costs_more_than_the_report_penalty(): void
    {
        $this->reportAt(['lat' => self::LAT0, 'lng' => self::LNG0 + 0.0035]);
        // Bypass = 2 extra blocks ≈ 221 m; penalty 100 m → passing the single report is cheaper.
        config(['services.osrm.report_tolerance' => 3, 'services.osrm.report_penalty_m' => 100]);

        $response = $this->requestRoute([0, 0], [7, 0], 'balanced')->assertOk()
            ->assertJsonPath('meta.graph_pass', 'tolerated')
            ->assertJsonPath('summary.remaining_report_count', 1)
            ->assertJsonPath('summary.report_tolerance', 3);
        $this->assertEqualsWithDelta(7 * 110.4, $response->json('distance_m'), 7);
        $this->assertStringContainsString('toleransi maks. 3 titik', collect($response->json('warnings'))->firstWhere('code', 'SAFEST_AVAILABLE_WITH_REPORTS')['message']);
    }

    public function test_balanced_still_detours_when_the_bypass_is_cheaper_than_the_report_penalty(): void
    {
        $this->reportAt(['lat' => self::LAT0, 'lng' => self::LNG0 + 0.0035]);
        // Bypass ≈ 221 m < penalty 300 m → detouring is cheaper than passing the report.
        config(['services.osrm.report_tolerance' => 3, 'services.osrm.report_penalty_m' => 300]);

        $response = $this->requestRoute([0, 0], [7, 0], 'balanced')->assertOk()
            ->assertJsonPath('meta.graph_pass', 'tolerated')
            ->assertJsonPath('summary.report_free', true);
        $this->assertEqualsWithDelta(9 * 110.4, $response->json('distance_m'), 9);
    }

    public function test_balanced_avoids_more_reports_than_the_tolerance(): void
    {
        foreach ([0.5, 2.5, 4.5] as $x) {
            $this->reportAt(['lat' => self::LAT0, 'lng' => self::LNG0 + 0.001 * $x]);
        }
        // A tiny penalty makes the tolerant search pass all 3 reports; 3 > T = 2, so it is rejected.
        config(['services.osrm.report_tolerance' => 2, 'services.osrm.report_penalty_m' => 1]);

        $this->requestRoute([0, 0], [7, 0], 'balanced')->assertOk()
            ->assertJsonPath('meta.graph_pass', 'clear')
            ->assertJsonPath('summary.report_free', true);
    }

    public function test_safest_ignores_the_report_tolerance_and_always_detours(): void
    {
        $this->reportAt(['lat' => self::LAT0, 'lng' => self::LNG0 + 0.0035]);
        config(['services.osrm.report_tolerance' => 3, 'services.osrm.report_penalty_m' => 1]);

        $this->requestRoute([0, 0], [7, 0], 'safest')->assertOk()
            ->assertJsonPath('meta.graph_pass', 'clear')
            ->assertJsonPath('summary.report_free', true)
            ->assertJsonPath('summary.report_tolerance', 0);
    }

    public function test_safest_refuses_when_reports_block_every_graph_path(): void
    {
        // Reports on every vertical edge between rows 3 and 4 cut the grid in two.
        foreach (range(0, 7) as $i) {
            $this->reportAt(['lat' => self::LAT0 + 0.0035, 'lng' => self::LNG0 + 0.001 * $i]);
        }
        // Other engines are down: the graph's own proof still yields the precise answer.
        Http::fake(['https://valhalla1.openstreetmap.de/*' => Http::response([], 503), 'https://routing.openstreetmap.de/*' => Http::response([], 503)]);
        $this->requestRoute([0, 0], [0, 7], 'safest')->assertStatus(422)->assertJsonPath('error.code', 'NO_REPORT_FREE_ROUTE');

        config(['services.routing_graph.engine' => 'graph']);
        $this->requestRoute([0, 0], [0, 7], 'safest')->assertStatus(422)->assertJsonPath('error.code', 'NO_REPORT_FREE_ROUTE');
        $this->requestRoute([0, 0], [0, 7], 'fastest')->assertOk()->assertJsonPath('engine', 'postgis_astar')->assertJsonPath('summary.report_count', 1);
    }

    public function test_osrm_is_used_when_the_graph_engine_is_disabled(): void
    {
        config(['services.routing_graph.engine' => 'osrm', 'services.osrm.url' => 'https://routing.openstreetmap.de/routed-foot/route/v1/foot', 'services.osrm.residential_detours' => false]);
        Http::fake(['https://routing.openstreetmap.de/routed-foot/route/v1/foot/*' => Http::response(['code' => 'Ok', 'routes' => [[
            'distance' => 772, 'duration' => 618, 'legs' => [],
            'geometry' => ['type' => 'LineString', 'coordinates' => [$this->coord(0, 0), $this->coord(7, 0)]],
        ]]])]);

        $this->requestRoute([0, 0], [7, 0], 'fastest')->assertOk()->assertJsonPath('engine', 'osrm_foot');
    }

    public function test_pins_too_far_from_the_graph_fall_back_to_valhalla_then_osrm(): void
    {
        config(['services.osrm.url' => 'https://routing.openstreetmap.de/routed-foot/route/v1/foot', 'services.osrm.residential_detours' => false]);
        Http::fake(['https://valhalla1.openstreetmap.de/*' => Http::response([], 503), 'https://routing.openstreetmap.de/routed-foot/route/v1/foot/*' => Http::response(['code' => 'Ok', 'routes' => [[
            'distance' => 900, 'duration' => 720, 'legs' => [],
            'geometry' => ['type' => 'LineString', 'coordinates' => [[self::LNG0, self::LAT0 - 0.005], $this->coord(7, 0)]],
        ]]])]);

        $this->postJson('/api/v1/routes/recommend', [
            'origin' => ['lat' => self::LAT0 - 0.005, 'lng' => self::LNG0], 'destination' => $this->point(7, 0), 'preference' => 'fastest',
        ])->assertOk()->assertJsonPath('engine', 'osrm_foot');
    }
}
