<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Laporan;
use App\Models\Notifikasi;
use App\Models\RiwayatStatusLaporan;
use App\Models\SegmenJalur;
use App\Models\Warga;
use App\Support\MessageCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RepairStatusTest extends TestCase
{
    use DatabaseTransactions;

    protected Admin $admin;
    protected string $adminToken;
    protected Warga $warga;
    protected string $wargaToken;
    protected int $segmenId;
    protected int $laporanId;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Admin
        $this->admin = Admin::create([
            'nama' => 'Admin Dishub Perbaikan',
            'email' => 'admin_repair_' . uniqid() . '@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'tanggal_dibuat' => now(),
            'gagal_login' => 0,
        ]);
        $this->adminToken = $this->admin->createToken('admin-token', ['role:admin'])->plainTextToken;

        // 2. Setup Warga
        $this->warga = Warga::create([
            'nama' => 'Warga Pelapor',
            'email' => 'warga_repair_' . uniqid() . '@test.com',
            'no_hp' => '62877' . rand(1000000, 9999999),
            'password' => Hash::make('password123'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
        ]);
        $this->wargaToken = $this->warga->createToken('warga-token', ['role:warga'])->plainTextToken;

        // 3. Setup a clean, dedicated Segment
        $unique = rand(10000, 99999);
        $this->segmenId = DB::table('segmen_jalur')->insertGetId([
            'osm_way_id' => 90000 + $unique,
            'u_node' => 80000 + $unique,
            'v_node' => 70000 + $unique,
            'nama_jalan' => 'Jl. Uji Perbaikan ' . $unique,
            'geom' => DB::raw("ST_GeogFromText('SRID=4326;LINESTRING(112.7950 -7.2660, 112.7980 -7.2665)')"),
            'panjang_m' => 300.0,
            'skor_kelayakan' => 100.00,
            'tanggal_update' => now(),
        ], 'segmen_id');

        // 4. Create location on that segment
        $lokasiId = DB::table('lokasi')->insertGetId([
            'latitude' => -7.2660,
            'longitude' => 112.7950,
            'geom' => DB::raw("ST_SetSRID(ST_Point(112.7950, -7.2660), 4326)"),
            'alamat' => 'Jl. Uji Perbaikan No. 10',
            'segmen_id' => $this->segmenId,
        ], 'lokasi_id');

        // 5. Create a TERVALIDASI report on that segment
        $this->laporanId = DB::table('laporan')->insertGetId([
            'warga_id' => $this->warga->warga_id,
            'kategori_id' => 1, // Trotoar Rusak
            'lokasi_id' => $lokasiId,
            'deskripsi' => 'Trotoar amblas butuh perbaikan segera',
            'status' => 'TERVALIDASI',
            'status_perbaikan' => null,
            'tanggal_lapor' => now()->subDay(),
            'tanggal_validasi' => now()->subHours(2),
        ], 'laporan_id');

        // Initial score reduction by recalculating WSM with the active report
        app(\App\Services\SegmentScoreService::class)->recalculate($this->segmenId);
    }

    public function test_unauthenticated_cannot_update_repair_status(): void
    {
        $response = $this->patchJson("/api/v1/admin/reports/{$this->laporanId}/repair-status", [
            'status_perbaikan' => 'SEDANG_DIPERBAIKI',
        ]);

        $response->assertStatus(401);
    }

    public function test_warga_cannot_update_repair_status(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->wargaToken)
            ->patchJson("/api/v1/admin/reports/{$this->laporanId}/repair-status", [
                'status_perbaikan' => 'SEDANG_DIPERBAIKI',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_repair_status_to_sedang_diperbaiki(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->patchJson("/api/v1/admin/reports/{$this->laporanId}/repair-status", [
                'status_perbaikan' => 'SEDANG_DIPERBAIKI',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'laporan_id' => $this->laporanId,
                    'status' => 'TERVALIDASI',
                    'status_perbaikan' => 'SEDANG_DIPERBAIKI',
                ],
                'message' => MessageCatalog::REPAIR_STATUS_SAVED,
            ]);

        // Check database
        $this->assertDatabaseHas('laporan', [
            'laporan_id' => $this->laporanId,
            'status_perbaikan' => 'SEDANG_DIPERBAIKI',
        ]);

        // Check riwayat
        $this->assertDatabaseHas('riwayat_status_laporan', [
            'laporan_id' => $this->laporanId,
            'jenis' => 'PERBAIKAN',
            'status' => 'SEDANG_DIPERBAIKI',
            'admin_id' => $this->admin->admin_id,
        ]);

        // Check notification created for warga
        $notif = Notifikasi::where('laporan_id', $this->laporanId)
            ->where('penerima_tipe', 'WARGA')
            ->where('penerima_id', $this->warga->warga_id)
            ->where('pesan', 'like', '%sedang diperbaiki%')
            ->first();
        $this->assertNotNull($notif);
    }

    public function test_admin_can_update_repair_status_to_sudah_diperbaiki_and_recalculates_wsm(): void
    {
        // Initial score should be < 100 because of active report
        $scoreBefore = SegmenJalur::find($this->segmenId)->skor_kelayakan;
        $this->assertLessThan(100.0, $scoreBefore);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->patchJson("/api/v1/admin/reports/{$this->laporanId}/repair-status", [
                'status_perbaikan' => 'SUDAH_DIPERBAIKI',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'laporan_id' => $this->laporanId,
                    'status' => 'TERVALIDASI',
                    'status_perbaikan' => 'SUDAH_DIPERBAIKI',
                ],
                'message' => MessageCatalog::REPAIR_STATUS_SAVED,
            ]);

        // Verify score is recovered back to 100.00
        $scoreAfter = SegmenJalur::find($this->segmenId)->skor_kelayakan;
        $this->assertEquals(100.0, $scoreAfter);

        // Check database
        $this->assertDatabaseHas('laporan', [
            'laporan_id' => $this->laporanId,
            'status_perbaikan' => 'SUDAH_DIPERBAIKI',
        ]);

        // Check notification created for warga
        $notif = Notifikasi::where('laporan_id', $this->laporanId)
            ->where('penerima_tipe', 'WARGA')
            ->where('penerima_id', $this->warga->warga_id)
            ->where('pesan', 'like', '%sudah diperbaiki%')
            ->first();
        $this->assertNotNull($notif);
    }

    public function test_missing_status_perbaikan_returns_422(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->patchJson("/api/v1/admin/reports/{$this->laporanId}/repair-status", []);

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'REPAIR_STATUS_REQUIRED',
                    'message' => MessageCatalog::REPAIR_STATUS_REQUIRED,
                ],
            ]);
    }

    public function test_cannot_revert_sudah_diperbaiki_status(): void
    {
        // First set to SUDAH_DIPERBAIKI
        DB::table('laporan')->where('laporan_id', $this->laporanId)->update([
            'status_perbaikan' => 'SUDAH_DIPERBAIKI',
            'tanggal_perbaikan' => now(),
        ]);

        // Try reverting to SEDANG_DIPERBAIKI
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->patchJson("/api/v1/admin/reports/{$this->laporanId}/repair-status", [
                'status_perbaikan' => 'SEDANG_DIPERBAIKI',
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'error' => [
                    'code' => 'CONFLICT',
                ],
            ]);
    }

    public function test_unvalidated_report_cannot_have_repair_status(): void
    {
        // Set report status to MENUNGGU_VALIDASI_ADMIN
        DB::table('laporan')->where('laporan_id', $this->laporanId)->update([
            'status' => 'MENUNGGU_VALIDASI_ADMIN',
            'status_perbaikan' => null,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->patchJson("/api/v1/admin/reports/{$this->laporanId}/repair-status", [
                'status_perbaikan' => 'SEDANG_DIPERBAIKI',
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'error' => [
                    'code' => 'CONFLICT',
                ],
            ]);
    }

    public function test_admin_can_filter_reports_by_repair_status(): void
    {
        DB::table('laporan')->where('laporan_id', $this->laporanId)->update([
            'status_perbaikan' => 'SEDANG_DIPERBAIKI',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/reports?status=TERVALIDASI&status_perbaikan=SEDANG_DIPERBAIKI');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertEquals('SEDANG_DIPERBAIKI', $item['status_perbaikan']);
        }
    }

    public function test_admin_can_upload_repair_photo_when_marking_sedang_diperbaiki(): void
    {
        Storage::fake('public');

        $fakePhoto = UploadedFile::fake()->image('pengerjaan_lapangan.jpg', 640, 480);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->post("/api/v1/admin/reports/{$this->laporanId}/repair-status", [
                'status_perbaikan' => 'SEDANG_DIPERBAIKI',
                'catatan' => 'Petugas satgas sedang memasang barikade dan membongkar paving rusak',
                'foto_perbaikan' => $fakePhoto,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => MessageCatalog::REPAIR_STATUS_SAVED,
                'data' => [
                    'laporan_id' => $this->laporanId,
                    'status_perbaikan' => 'SEDANG_DIPERBAIKI',
                    'catatan_perbaikan' => 'Petugas satgas sedang memasang barikade dan membongkar paving rusak',
                ],
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data['foto_perbaikan_url']);

        // Check report in database has foto_perbaikan stored
        $report = Laporan::find($this->laporanId);
        $this->assertNotNull($report->foto_perbaikan);
        Storage::disk('public')->assertExists($report->foto_perbaikan);

        // Verify signed URL allows fetching the repair photo
        $photoStreamResponse = $this->get($data['foto_perbaikan_url']);
        $photoStreamResponse->assertStatus(200);
    }

    public function test_heatmap_points_include_foto_perbaikan_url_for_sedang_diperbaiki(): void
    {
        Storage::fake('public');

        $filePath = 'reports/test_progress_' . uniqid() . '.jpg';
        Storage::disk('public')->put($filePath, 'fake image content');

        DB::table('laporan')->where('laporan_id', $this->laporanId)->update([
            'status_perbaikan' => 'SEDANG_DIPERBAIKI',
            'catatan_perbaikan' => 'Penanganan fisik sedang berlangsung',
            'foto_perbaikan' => $filePath,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->wargaToken)
            ->getJson('/api/v1/heatmap?min_lon=112.75&min_lat=-7.30&max_lon=112.85&max_lat=-7.24&zoom=15');

        $response->assertStatus(200);
        $reports = $response->json('reports');
        $matched = collect($reports)->firstWhere('laporan_id', $this->laporanId);

        $this->assertNotNull($matched);
        $this->assertEquals('SEDANG_DIPERBAIKI', $matched['status_perbaikan']);
        $this->assertNotEmpty($matched['foto_perbaikan_url']);
    }
}
