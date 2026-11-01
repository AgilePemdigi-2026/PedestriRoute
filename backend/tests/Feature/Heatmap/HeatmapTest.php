<?php

namespace Tests\Feature\Heatmap;

use App\Models\Admin;
use App\Models\Laporan;
use App\Models\SegmenJalur;
use App\Models\Warga;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HeatmapTest extends TestCase
{
    use DatabaseTransactions;

    protected Warga $warga;
    protected string $wargaToken;
    protected Admin $admin;
    protected string $adminToken;
    protected int $segmenId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warga = Warga::create([
            'nama' => 'Warga Heatmap Tester',
            'email' => 'warga_map_' . uniqid() . '@test.com',
            'no_hp' => '62877' . rand(1000000, 9999999),
            'password' => Hash::make('password123'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
        ]);
        $this->wargaToken = $this->warga->createToken('warga-token', ['role:warga'])->plainTextToken;

        $this->admin = Admin::create([
            'nama' => 'Admin Heatmap Tester',
            'email' => 'admin_map_' . uniqid() . '@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'tanggal_dibuat' => now(),
            'gagal_login' => 0,
        ]);
        $this->adminToken = $this->admin->createToken('admin-token', ['role:admin'])->plainTextToken;

        $segment = SegmenJalur::first();
        if ($segment) {
            $this->segmenId = $segment->segmen_id;
        } else {
            $this->segmenId = DB::table('segmen_jalur')->insertGetId([
                'osm_way_id' => 9998,
                'u_node' => 8887,
                'v_node' => 7776,
                'nama_jalan' => 'Jl. Mulyosari Heatmap',
                'geom' => DB::raw("ST_GeogFromText('SRID=4326;LINESTRING(112.7950 -7.2660, 112.7980 -7.2665)')"),
                'panjang_m' => 350.0,
                'skor_kelayakan' => 100.00,
                'tanggal_update' => now(),
            ], 'segmen_id');
        }
    }

    public function test_unauthenticated_cannot_access_heatmap(): void
    {
        $response = $this->getJson('/api/v1/heatmap?min_lon=112.78&min_lat=-7.28&max_lon=112.82&max_lat=-7.25&zoom=15');
        $response->assertStatus(401);
    }

    public function test_warga_and_admin_can_access_heatmap(): void
    {
        $resWarga = $this->withHeader('Authorization', 'Bearer ' . $this->wargaToken)
            ->getJson('/api/v1/heatmap?min_lon=112.78&min_lat=-7.28&max_lon=112.82&max_lat=-7.25&zoom=15');
        $resWarga->assertStatus(200);

        $resAdmin = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/heatmap?min_lon=112.78&min_lat=-7.28&max_lon=112.82&max_lat=-7.25&zoom=15');
        $resAdmin->assertStatus(200);
    }

    public function test_heatmap_returns_valid_geojson_structure(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->wargaToken)
            ->getJson('/api/v1/heatmap?min_lon=112.75&min_lat=-7.30&max_lon=112.85&max_lat=-7.24&zoom=15');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'type',
                'features' => [
                    '*' => [
                        'type',
                        'geometry' => [
                            'type',
                            'coordinates',
                        ],
                        'properties' => [
                            'segmen_id',
                            'nama_jalan',
                            'skor',
                            'tingkat',
                            'jumlah_laporan_aktif',
                        ],
                    ],
                ],
                'meta' => [
                    'counts' => [
                        'SANGAT_LAYAK',
                        'PERLU_PERHATIAN',
                        'TIDAK_LAYAK',
                    ],
                    'truncated',
                ],
            ]);

        $this->assertEquals('FeatureCollection', $response->json('type'));
    }

    public function test_heatmap_low_zoom_filters_only_problematic_segments(): void
    {
        // 1. Create a damaged segment (score < 100)
        $lokasiId = DB::table('lokasi')->insertGetId([
            'latitude' => -7.2660,
            'longitude' => 112.7950,
            'geom' => DB::raw("ST_SetSRID(ST_Point(112.7950, -7.2660), 4326)"),
            'alamat' => 'Jl. Rusak Low Zoom',
            'segmen_id' => $this->segmenId,
        ], 'lokasi_id');

        DB::table('laporan')->insert([
            'warga_id' => $this->warga->warga_id,
            'kategori_id' => 1,
            'lokasi_id' => $lokasiId,
            'deskripsi' => 'Trotoar amblas parah',
            'status' => 'TERVALIDASI',
            'status_perbaikan' => null,
            'tanggal_lapor' => now(),
            'tanggal_validasi' => now(),
        ]);

        app(\App\Services\SegmentScoreService::class)->recalculate($this->segmenId);

        // Score must now be < 100
        $score = SegmenJalur::find($this->segmenId)->skor_kelayakan;
        $this->assertLessThan(100.0, $score);

        // 2. Request with zoom = 12 (< CFG_HEATMAP_MIN_ZOOM default 14)
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->wargaToken)
            ->getJson('/api/v1/heatmap?min_lon=112.75&min_lat=-7.30&max_lon=112.85&max_lat=-7.24&zoom=12');

        $response->assertStatus(200);
        $features = $response->json('features');

        // All returned features must have skor < 100
        $this->assertNotEmpty($features);
        foreach ($features as $f) {
            $this->assertLessThan(100.0, $f['properties']['skor']);
        }

        // Meta counts should still include total segments in the viewport
        $metaCounts = $response->json('meta.counts');
        $totalCounted = $metaCounts['SANGAT_LAYAK'] + $metaCounts['PERLU_PERHATIAN'] + $metaCounts['TIDAK_LAYAK'];
        $this->assertGreaterThan(count($features), $totalCounted);
    }

    public function test_heatmap_category_filter_returns_only_matching_segments(): void
    {
        // Create an active report for category 2 (Masalah Drainase)
        $lokasiId = DB::table('lokasi')->insertGetId([
            'latitude' => -7.2660,
            'longitude' => 112.7950,
            'geom' => DB::raw("ST_SetSRID(ST_Point(112.7950, -7.2660), 4326)"),
            'alamat' => 'Jl. Drainase Rusak',
            'segmen_id' => $this->segmenId,
        ], 'lokasi_id');

        DB::table('laporan')->insert([
            'warga_id' => $this->warga->warga_id,
            'kategori_id' => 2,
            'lokasi_id' => $lokasiId,
            'deskripsi' => 'Manhole terbuka',
            'status' => 'TERVALIDASI',
            'status_perbaikan' => null,
            'tanggal_lapor' => now(),
            'tanggal_validasi' => now(),
        ]);

        // Filter by category 2
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->wargaToken)
            ->getJson('/api/v1/heatmap?min_lon=112.75&min_lat=-7.30&max_lon=112.85&max_lat=-7.24&zoom=15&kategori_id=2');

        $response->assertStatus(200);
        $features = $response->json('features');
        $this->assertNotEmpty($features);

        // Segment ID should be in the results
        $segmentIds = array_column(array_column($features, 'properties'), 'segmen_id');
        $this->assertContains($this->segmenId, $segmentIds);

        // 1 active report with saturation 3 -> intensity 1/3 -> score ~ 66.67 (PERLU_PERHATIAN)
        $matched = null;
        foreach ($features as $f) {
            if ($f['properties']['segmen_id'] === $this->segmenId) {
                $matched = $f;
                break;
            }
        }
        $this->assertNotNull($matched);
        $this->assertEquals(66.67, $matched['properties']['skor']);
        $this->assertEquals('PERLU_PERHATIAN', $matched['properties']['tingkat']);
        $this->assertEquals(1, $matched['properties']['jumlah_laporan_aktif']);
    }

    public function test_heatmap_category_filter_without_reports_returns_empty_features(): void
    {
        // Create an explicit category with 0 active reports
        $cat = \App\Models\KategoriMasalah::create([
            'nama_kategori' => 'Kategori Uji Kosong ' . uniqid(),
            'bobot' => 0.100,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->wargaToken)
            ->getJson("/api/v1/heatmap?min_lon=112.75&min_lat=-7.30&max_lon=112.85&max_lat=-7.24&zoom=15&kategori_id={$cat->kategori_id}");

        $response->assertStatus(200);
        $this->assertEmpty($response->json('features'));
        $this->assertEquals(0, $response->json('meta.counts.SANGAT_LAYAK'));
        $this->assertEquals(0, $response->json('meta.counts.PERLU_PERHATIAN'));
        $this->assertEquals(0, $response->json('meta.counts.TIDAK_LAYAK'));
    }

    public function test_heatmap_empty_viewport_returns_empty_features(): void
    {
        // Viewport far away (e.g. Pacific ocean: lat 0, lon 0)
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->wargaToken)
            ->getJson('/api/v1/heatmap?min_lon=0.0&min_lat=0.0&max_lon=0.1&max_lat=0.1&zoom=15');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('features'));
        $this->assertEquals(0, $response->json('meta.counts.SANGAT_LAYAK'));
    }

    public function test_invalid_parameters_returns_422(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->wargaToken)
            ->getJson('/api/v1/heatmap?min_lon=112.75&max_lat=-7.24');

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'INVALID_PARAMETERS',
                ],
            ]);
    }
}
