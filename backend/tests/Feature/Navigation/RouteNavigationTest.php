<?php

namespace Tests\Feature\Navigation;

use App\Models\Warga;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RouteNavigationTest extends TestCase
{
    use DatabaseTransactions;

    protected Warga $warga;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warga = Warga::create([
            'nama' => 'Warga Navigasi Tester',
            'email' => 'warga_nav_' . uniqid() . '@test.com',
            'no_hp' => '62878' . rand(1000000, 9999999),
            'password' => Hash::make('password123'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
        ]);
        $this->token = $this->warga->createToken('warga-token', ['role:warga'])->plainTextToken;

        $unique = rand(10000, 99999);
        DB::table('segmen_jalur')->insert([
            'osm_way_id' => 95000 + $unique,
            'u_node' => 85000 + $unique,
            'v_node' => 75000 + $unique,
            'nama_jalan' => 'Jl. Navigasi Uji ' . $unique,
            'geom' => DB::raw("ST_GeogFromText('SRID=4326;LINESTRING(112.7950 -7.2660, 112.7960 -7.2660, 112.7970 -7.2665)')"),
            'panjang_m' => 220.0,
            'skor_kelayakan' => 90.00,
            'tanggal_update' => now(),
        ]);
    }

    public function test_unauthenticated_cannot_request_route(): void
    {
        $response = $this->postJson('/api/v1/routes/recommend', [
            'origin' => ['lat' => -7.2660, 'lng' => 112.7950],
            'destination' => ['lat' => -7.2665, 'lng' => 112.7970],
        ]);

        $response->assertStatus(401);
    }

    public function test_route_requires_valid_coordinates(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/routes/recommend', [
                'origin' => ['lat' => 200, 'lng' => 112.7950],
                'destination' => ['lat' => -7.2665],
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'INVALID_ROUTE_PARAMETERS',
                ],
            ]);
    }

    public function test_route_returns_polyline_and_turn_by_turn_steps(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/routes/recommend', [
                'origin' => ['lat' => -7.2660, 'lng' => 112.7950],
                'destination' => ['lat' => -7.2665, 'lng' => 112.7970],
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
                    '*' => ['name', 'distance_m', 'score', 'polyline'],
                ],
                'steps' => [
                    '*' => [
                        'index',
                        'type',
                        'maneuver',
                        'instruction',
                        'distance_m',
                        'distance_from_start_m',
                        'remaining_distance_m',
                        'location' => ['lat', 'lng'],
                    ],
                ],
                'summary' => [
                    'average_score',
                    'repair_distance_m',
                    'safe_distance_m',
                    'total_segments',
                    'fallback',
                ],
                'meta' => ['considered_segments', 'generated_at'],
            ]);

        $this->assertTrue($response->json('success'));
        $this->assertGreaterThan(0, $response->json('distance_m'));
        $this->assertGreaterThanOrEqual(2, count($response->json('polyline')));

        $steps = $response->json('steps');
        $this->assertSame('depart', $steps[0]['type']);
        $this->assertSame('arrive', $steps[count($steps) - 1]['type']);
        $this->assertSame(0, $steps[count($steps) - 1]['remaining_distance_m']);
    }

    public function test_route_accepts_flattened_payload_and_fastest_preference(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/routes/recommend', [
                'origin_lat' => -7.2660,
                'origin_lng' => 112.7950,
                'dest_lat' => -7.2665,
                'dest_lng' => 112.7970,
                'preference' => 'fastest',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'preference' => 'fastest',
            ]);

        $this->assertEqualsWithDelta(-7.2660, $response->json('origin.lat'), 0.000001);
        $this->assertEqualsWithDelta(112.7970, $response->json('destination.lng'), 0.000001);
    }
}
