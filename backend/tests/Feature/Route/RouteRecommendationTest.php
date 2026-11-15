<?php

namespace Tests\Feature\Route;

use App\Models\Admin;
use App\Models\Warga;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RouteRecommendationTest extends TestCase
{
    use DatabaseTransactions;

    protected Warga $warga;

    protected string $wargaToken;

    protected Admin $admin;

    protected string $adminToken;

    protected int $directSegmentId;

    protected int $safeSegmentAId;

    protected int $safeSegmentBId;

    protected array $origin = ['lat' => -7.2700, 'lng' => 112.8070];

    protected array $destination = ['lat' => -7.2700, 'lng' => 112.8079];

    protected function setUp(): void
    {
        parent::setUp();

        $this->warga = Warga::create([
            'nama' => 'Warga Rute Tester',
            'email' => 'warga_route_'.uniqid().'@test.com',
            'no_hp' => '62879'.rand(1000000, 9999999),
            'password' => Hash::make('password123'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
        ]);
        $this->wargaToken = $this->warga->createToken('warga-token', ['role:warga'])->plainTextToken;

        $this->admin = Admin::create([
            'nama' => 'Admin Rute Tester',
            'email' => 'admin_route_'.uniqid().'@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'tanggal_dibuat' => now(),
            'gagal_login' => 0,
        ]);
        $this->adminToken = $this->admin->createToken('admin-token', ['role:admin'])->plainTextToken;

        $unique = rand(10000, 99999);

        // Short but damaged direct footpath.
        $this->directSegmentId = $this->insertSegment(
            'Jl. Rute Langsung '.$unique,
            'LINESTRING(112.8070 -7.2700, 112.8079 -7.2700)',
            25.00,
            $unique,
        );

        // Slightly longer detour with an excellent WSM score.
        $this->safeSegmentAId = $this->insertSegment(
            'Jl. Rute Aman A '.$unique,
            'LINESTRING(112.8070 -7.2700, 112.80745 -7.2696)',
            96.00,
            $unique + 1,
        );
        $this->safeSegmentBId = $this->insertSegment(
            'Jl. Rute Aman B '.$unique,
            'LINESTRING(112.80745 -7.2696, 112.8079 -7.2700)',
            96.00,
            $unique + 2,
        );
    }

    private function insertSegment(string $name, string $lineWkt, float $score, int $unique): int
    {
        return DB::table('segmen_jalur')->insertGetId([
            'osm_way_id' => 97000 + $unique,
            'u_node' => 87000 + $unique,
            'v_node' => 77000 + $unique,
            'nama_jalan' => $name,
            'geom' => DB::raw("ST_GeogFromText('SRID=4326;{$lineWkt}')"),
            'panjang_m' => 120.0,
            'skor_kelayakan' => $score,
            'tanggal_update' => now(),
        ], 'segmen_id');
    }

    private function requestRoute(array $payload, ?string $token = null)
    {
        return $this
            ->withHeader('Authorization', 'Bearer '.($token ?? $this->wargaToken))
            ->postJson('/api/v1/routes/recommend', $payload);
    }

    public function test_unauthenticated_user_cannot_request_route(): void
    {
        $response = $this->postJson('/api/v1/routes/recommend', [
            'origin' => $this->origin,
            'destination' => $this->destination,
        ]);

        $response->assertStatus(401);
    }

    public function test_route_requires_valid_coordinates_and_preference(): void
    {
        $response = $this->requestRoute([
            'origin' => ['lat' => 200, 'lng' => 112.8070],
            'destination' => ['lat' => -7.2700],
            'preference' => 'scenic',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'INVALID_ROUTE_PARAMETERS',
                    'message' => 'Koordinat atau preferensi rute tidak valid.',
                ],
            ]);
    }

    public function test_warga_receives_route_polyline_and_wsm_segment_scores(): void
    {
        $response = $this->requestRoute([
            'origin' => $this->origin,
            'destination' => $this->destination,
            'preference' => 'safest',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'route_id',
                'engine',
                'preference',
                'origin' => ['lat', 'lng'],
                'destination' => ['lat', 'lng'],
                'distance_m',
                'duration_min',
                'duration_s',
                'walk_speed_mps',
                'polyline',
                'segments' => [
                    '*' => ['segmen_id', 'name', 'distance_m', 'score', 'tingkat', 'repair', 'connector', 'polyline'],
                ],
                'summary' => [
                    'average_score',
                    'minimum_score',
                    'repair_distance_m',
                    'safe_distance_m',
                    'total_segments',
                    'fallback',
                ],
                'warnings',
                'meta' => [
                    'considered_segments',
                    'connector_radius_m',
                    'score_penalty_factor',
                    'repair_penalty_factor',
                    'generated_at',
                ],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertGreaterThan(0, $response->json('distance_m'));
        $this->assertGreaterThanOrEqual(2, count($response->json('polyline')));
        $this->assertGreaterThan(0, $response->json('duration_min'));
        $this->assertSame('safest', $response->json('preference'));

        foreach ($response->json('segments') as $segment) {
            $this->assertContains($segment['tingkat'], ['SANGAT_LAYAK', 'PERLU_PERHATIAN', 'TIDAK_LAYAK']);
            $this->assertGreaterThanOrEqual(0, $segment['score']);
            $this->assertLessThanOrEqual(100, $segment['score']);
        }
    }

    public function test_safest_route_avoids_damaged_segment_and_fastest_uses_it(): void
    {
        $safest = $this->requestRoute([
            'origin' => $this->origin,
            'destination' => $this->destination,
            'preference' => 'safest',
        ]);
        $safest->assertStatus(200);

        $fastest = $this->requestRoute([
            'origin' => $this->origin,
            'destination' => $this->destination,
            'preference' => 'fastest',
        ]);
        $fastest->assertStatus(200);

        $safestIds = array_column($safest->json('segments'), 'segmen_id');
        $fastestIds = array_column($fastest->json('segments'), 'segmen_id');

        $this->assertNotContains($this->directSegmentId, $safestIds, 'Rute teraman tidak boleh melewati segmen dengan skor rendah bila ada alternatif.');
        $this->assertContains($this->safeSegmentAId, $safestIds);
        $this->assertContains($this->safeSegmentBId, $safestIds);

        $this->assertContains($this->directSegmentId, $fastestIds, 'Rute tercepat harus memilih jalur dengan jarak terpendek.');
        $this->assertLessThan($safest->json('distance_m'), $fastest->json('distance_m'));

        $this->assertGreaterThan(0, $safest->json('summary.minimum_score'));
        $this->assertGreaterThanOrEqual(90, $safest->json('summary.average_score'));
        $this->assertLessThan(90, $fastest->json('summary.average_score'));
    }

    public function test_active_repair_produces_warning_when_route_passes_through_it(): void
    {
        $lokasiId = DB::table('lokasi')->insertGetId([
            'latitude' => -7.2698,
            'longitude' => 112.8074,
            'geom' => DB::raw('ST_SetSRID(ST_Point(112.8074, -7.2698), 4326)'),
            'alamat' => 'Segmen perbaikan rute',
            'segmen_id' => $this->safeSegmentAId,
        ], 'lokasi_id');

        DB::table('laporan')->insert([
            'warga_id' => $this->warga->warga_id,
            'kategori_id' => 1,
            'lokasi_id' => $lokasiId,
            'deskripsi' => 'Paving sedang diganti',
            'status' => 'TERVALIDASI',
            'status_perbaikan' => 'SEDANG_DIPERBAIKI',
            'tanggal_lapor' => now(),
            'tanggal_validasi' => now(),
        ]);

        $response = $this->requestRoute([
            'origin' => $this->origin,
            'destination' => $this->destination,
            'preference' => 'safest',
        ]);

        $response->assertStatus(200);

        $warnings = array_column($response->json('warnings'), 'code');
        $this->assertContains('ACTIVE_REPAIR', $warnings);
        $this->assertGreaterThan(0, $response->json('summary.repair_distance_m'));
    }

    public function test_flattened_payload_and_admin_access_are_supported(): void
    {
        $response = $this->requestRoute([
            'origin_lat' => $this->origin['lat'],
            'origin_lng' => $this->origin['lng'],
            'dest_lat' => $this->destination['lat'],
            'dest_lng' => $this->destination['lng'],
            'preference' => 'fastest',
        ], $this->adminToken);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'preference' => 'fastest',
            ]);

        $this->assertEqualsWithDelta($this->origin['lat'], $response->json('origin.lat'), 0.000001);
        $this->assertEqualsWithDelta($this->destination['lng'], $response->json('destination.lng'), 0.000001);
    }

    public function test_route_falls_back_gracefully_when_network_is_unavailable(): void
    {
        $response = $this->requestRoute([
            'origin' => ['lat' => -7.0000, 'lng' => 112.0000],
            'destination' => ['lat' => -7.0010, 'lng' => 112.0010],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'engine' => 'direct_fallback',
                'summary' => [
                    'fallback' => true,
                ],
            ]);

        $this->assertGreaterThanOrEqual(2, count($response->json('polyline')));
        $this->assertContains('NETWORK_FALLBACK', array_column($response->json('warnings'), 'code'));
    }
}
