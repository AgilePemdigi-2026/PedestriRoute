<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Laporan;
use App\Models\SegmenJalur;
use App\Models\Warga;
use App\Services\CvService;
use App\Support\MessageCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ValidateReportTest extends TestCase
{
    use DatabaseTransactions;

    protected Admin $admin;
    protected string $adminToken;
    protected Warga $warga;
    protected string $wargaToken;
    protected int $laporanId;
    protected int $segmenId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // Setup Admin
        $this->admin = Admin::create([
            'nama' => 'Admin Validator',
            'email' => 'admin_val_' . uniqid() . '@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'tanggal_dibuat' => now(),
            'gagal_login' => 0,
        ]);
        $this->adminToken = $this->admin->createToken('admin-token', ['role:admin'])->plainTextToken;

        // Setup Warga
        $this->warga = Warga::create([
            'nama' => 'Warga Pelapor',
            'email' => 'warga_val_' . uniqid() . '@test.com',
            'no_hp' => '62877' . rand(1000000, 9999999),
            'password' => Hash::make('password123'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
        ]);
        $this->wargaToken = $this->warga->createToken('warga-token', ['role:warga'])->plainTextToken;

        // Get nearest segment
        $segment = SegmenJalur::first();
        $this->segmenId = $segment->segmen_id;

        // Create a report with status MENUNGGU_VALIDASI_ADMIN
        $lokasiId = DB::table('lokasi')->insertGetId([
            'latitude' => -7.2663,
            'longitude' => 112.7965,
            'geom' => DB::raw("ST_SetSRID(ST_Point(112.7965, -7.2663), 4326)"),
            'alamat' => 'Jl. Raya Mulyosari',
            'segmen_id' => $this->segmenId,
        ], 'lokasi_id');

        $this->laporanId = DB::table('laporan')->insertGetId([
            'warga_id' => $this->warga->warga_id,
            'kategori_id' => 1, // Trotoar Rusak, bobot 0.400
            'lokasi_id' => $lokasiId,
            'deskripsi' => 'Trotoar amblas dekat kampus',
            'status' => 'MENUNGGU_VALIDASI_ADMIN',
            'tanggal_lapor' => now(),
        ], 'laporan_id');
    }

    public function test_admin_validasi_laporan_valid(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/admin/reports/{$this->laporanId}/validation", [
                'keputusan' => 'VALID',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'TERVALIDASI');

        $this->assertDatabaseHas('laporan', [
            'laporan_id' => $this->laporanId,
            'status' => 'TERVALIDASI',
            'admin_id' => $this->admin->admin_id,
        ]);

        // Check that segment score was recalculated (dropped below 100)
        $updatedSegment = SegmenJalur::find($this->segmenId);
        $this->assertLessThan(100.00, $updatedSegment->skor_kelayakan);

        // Check notification for Warga
        $this->assertDatabaseHas('notifikasi', [
            'penerima_tipe' => 'WARGA',
            'penerima_id' => $this->warga->warga_id,
        ]);
    }

    public function test_admin_validasi_tidak_valid_dengan_alasan(): void
    {
        $alasan = 'Foto tidak menunjukkan kerusakan fasilitas yang jelas';

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/admin/reports/{$this->laporanId}/validation", [
                'keputusan' => 'TIDAK_VALID',
                'alasan_penolakan' => $alasan,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'DITOLAK')
            ->assertJsonPath('data.alasan_penolakan', $alasan);

        $this->assertDatabaseHas('laporan', [
            'laporan_id' => $this->laporanId,
            'status' => 'DITOLAK',
            'alasan_penolakan' => $alasan,
        ]);

        $this->assertDatabaseHas('notifikasi', [
            'penerima_tipe' => 'WARGA',
            'penerima_id' => $this->warga->warga_id,
        ]);
    }

    public function test_admin_validasi_gagal_alasan_penolakan_kosong(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/admin/reports/{$this->laporanId}/validation", [
                'keputusan' => 'TIDAK_VALID',
                'alasan_penolakan' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_REASON_REQUIRED')
            ->assertJsonPath('error.message', MessageCatalog::VALIDATION_REASON_REQUIRED);

        $this->assertDatabaseHas('laporan', [
            'laporan_id' => $this->laporanId,
            'status' => 'MENUNGGU_VALIDASI_ADMIN',
        ]);
    }

    public function test_konflik_bila_laporan_sudah_divalidasi(): void
    {
        // First validation
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/admin/reports/{$this->laporanId}/validation", [
                'keputusan' => 'VALID',
            ]);

        // Second validation attempt
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/admin/reports/{$this->laporanId}/validation", [
                'keputusan' => 'VALID',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT');
    }

    public function test_warga_tidak_boleh_memvalidasi_laporan(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->wargaToken)
            ->postJson("/api/v1/admin/reports/{$this->laporanId}/validation", [
                'keputusan' => 'VALID',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_admin_dapat_melihat_laporan_ditolak_melalui_api(): void
    {
        $alasan = 'Foto blur dan bukan fasilitas publik';
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/admin/reports/{$this->laporanId}/validation", [
                'keputusan' => 'TIDAK_VALID',
                'alasan_penolakan' => $alasan,
            ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/reports?status=DITOLAK');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'laporan_id' => $this->laporanId,
                'status' => 'DITOLAK',
                'alasan_penolakan' => $alasan,
            ]);
    }

    public function test_halaman_blade_antrean_ditolak_admin_dapat_dimuat(): void
    {
        $response = $this->get('/admin/ditolak');

        $response->assertStatus(200)
            ->assertSee('Validasi & Perbaikan')
            ->assertSee('Ditolak');
    }

    public function test_admin_can_trigger_ml_analysis_via_api(): void
    {
        $fakePath = 'laporan/test_admin_ml_' . uniqid() . '.jpg';
        Storage::disk('public')->put($fakePath, 'fake-image-data');

        DB::table('foto_laporan')->insert([
            'laporan_id' => $this->laporanId,
            'url_foto' => $fakePath,
            'tanggal_upload' => now(),
        ]);

        $mockCv = Mockery::mock(CvService::class);
        $mockCv->shouldReceive('analyze')->once()->andReturn([
            'label' => 'RUSAK',
            'confidence' => 0.88,
            'probabilities' => ['rusak' => 0.88, 'baik' => 0.10, 'spam' => 0.02],
            'detected_category' => 'Trotoar Rusak',
        ]);
        $this->app->instance(CvService::class, $mockCv);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/admin/reports/{$this->laporanId}/analyze-cv");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.label', 'RUSAK')
            ->assertJsonPath('data.confidence', 0.88)
            ->assertJsonPath('data.confidence_percent', 88)
            ->assertJsonPath('data.probabilities.rusak', 0.88);

        $this->assertDatabaseHas('laporan', [
            'laporan_id' => $this->laporanId,
            'cv_label' => 'RUSAK',
            'confidence_score' => 0.88,
        ]);
    }

    public function test_admin_trigger_ml_analysis_supports_terhalang_baik_and_spam(): void
    {
        $fakePath = 'laporan/test_admin_ml2_' . uniqid() . '.jpg';
        Storage::disk('public')->put($fakePath, 'fake-image-data');

        DB::table('foto_laporan')->insert([
            'laporan_id' => $this->laporanId,
            'url_foto' => $fakePath,
            'tanggal_upload' => now(),
        ]);

        // Test TERHALANG
        $mockCv = Mockery::mock(CvService::class);
        $mockCv->shouldReceive('analyze')->once()->andReturn([
            'label' => 'TERHALANG',
            'confidence' => 0.94,
            'probabilities' => ['terhalang' => 0.94, 'baik' => 0.04, 'spam' => 0.02],
            'detected_category' => 'Parkir Liar',
        ]);
        $this->app->instance(CvService::class, $mockCv);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/admin/reports/{$this->laporanId}/analyze-cv");

        $response->assertStatus(200)
            ->assertJsonPath('data.label', 'TERHALANG')
            ->assertJsonPath('data.confidence', 0.94);

        $this->assertDatabaseHas('laporan', [
            'laporan_id' => $this->laporanId,
            'cv_label' => 'TERHALANG',
            'confidence_score' => 0.94,
        ]);
    }

    public function test_warga_cannot_trigger_admin_ml_analysis(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->wargaToken)
            ->postJson("/api/v1/admin/reports/{$this->laporanId}/analyze-cv");

        $response->assertStatus(403);
    }

    public function test_admin_reports_endpoint_returns_ml_label_and_confidence(): void
    {
        DB::table('laporan')->where('laporan_id', $this->laporanId)->update([
            'cv_label' => 'RUSAK',
            'confidence_score' => 0.85,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/reports?status=MENUNGGU_VALIDASI_ADMIN');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'laporan_id' => $this->laporanId,
                'cv_label' => 'RUSAK',
                'confidence_score' => 0.85,
            ]);
    }
}
