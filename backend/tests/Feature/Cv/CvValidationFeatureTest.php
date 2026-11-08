<?php

namespace Tests\Feature\Cv;

use App\Jobs\ProcessReportCv;
use App\Models\Laporan;
use App\Models\Warga;
use App\Services\CvService;
use App\Services\SegmentScoreService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CvValidationFeatureTest extends TestCase
{
    use DatabaseTransactions;

    protected Warga $warga;
    protected string $wargaToken;
    protected int $segmenId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->warga = Warga::create([
            'nama' => 'Warga Tester CV',
            'email' => 'warga_cv_' . uniqid() . '@test.com',
            'no_hp' => '62888' . rand(1000000, 9999999),
            'password' => Hash::make('password123'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
        ]);
        $this->wargaToken = $this->warga->createToken('warga-token', ['role:warga'])->plainTextToken;

        $segmen = DB::table('segmen_jalur')->first();
        $this->segmenId = $segmen ? (int) $segmen->segmen_id : 1;
    }

    protected function createReportWithPhoto(string $status = 'DIPROSES', int $kategoriId = 1): int
    {
        $lokasiId = DB::table('lokasi')->insertGetId([
            'latitude' => -7.2663,
            'longitude' => 112.7965,
            'geom' => DB::raw("ST_SetSRID(ST_Point(112.7965, -7.2663), 4326)"),
            'alamat' => 'Jl. Mulyosari Test CV',
            'segmen_id' => $this->segmenId,
        ], 'lokasi_id');

        $laporanId = DB::table('laporan')->insertGetId([
            'warga_id' => $this->warga->warga_id,
            'kategori_id' => $kategoriId,
            'lokasi_id' => $lokasiId,
            'deskripsi' => 'Kerusakan fasilitas pejalan kaki uji CV',
            'status' => $status,
            'confidence_score' => null,
            'cv_label' => null,
            'tanggal_lapor' => now(),
        ], 'laporan_id');

        $fakePath = 'laporan/test_photo_' . uniqid() . '.jpg';
        Storage::disk('public')->put($fakePath, 'fake-image-bytes');

        DB::table('foto_laporan')->insert([
            'laporan_id' => $laporanId,
            'url_foto' => $fakePath,
            'tanggal_upload' => now(),
        ]);

        return $laporanId;
    }

    public function test_cv_job_auto_validates_damaged_report_when_confidence_ge_80(): void
    {
        $laporanId = $this->createReportWithPhoto('DIPROSES', 1);

        $mockCv = Mockery::mock(CvService::class);
        $mockCv->shouldReceive('isCategorySupported')->andReturn(true);
        $mockCv->shouldReceive('getThreshold')->andReturn(0.80);
        $mockCv->shouldReceive('analyze')->once()->andReturn([
            'label' => 'RUSAK',
            'confidence' => 0.88,
            'detected_category' => 'Trotoar Rusak',
        ]);
        $this->app->instance(CvService::class, $mockCv);

        // Dispatch synchronous job
        ProcessReportCv::dispatchSync($laporanId);

        $laporan = Laporan::find($laporanId);
        $this->assertEquals('TERVALIDASI', $laporan->status);
        $this->assertEquals('RUSAK', $laporan->cv_label);
        $this->assertEquals(0.88, (float) $laporan->confidence_score);
        $this->assertNull($laporan->admin_id);
        $this->assertNotNull($laporan->tanggal_validasi);

        // Verify status history
        $this->assertDatabaseHas('riwayat_status_laporan', [
            'laporan_id' => $laporanId,
            'status' => 'TERVALIDASI',
            'jenis' => 'VALIDASI',
        ]);
    }

    public function test_cv_job_auto_adjusts_category_when_defect_category_differs(): void
    {
        // Citizen reported kategori 1: "Trotoar Rusak"
        $laporanId = $this->createReportWithPhoto('DIPROSES', 1);

        $mockCv = Mockery::mock(CvService::class);
        $mockCv->shouldReceive('isCategorySupported')->andReturn(true);
        $mockCv->shouldReceive('getThreshold')->andReturn(0.80);
        $mockCv->shouldReceive('analyze')->once()->andReturn([
            'label' => 'RUSAK',
            'confidence' => 0.95,
            'detected_category' => 'Masalah Drainase',
        ]);
        $this->app->instance(CvService::class, $mockCv);

        ProcessReportCv::dispatchSync($laporanId);

        $laporan = Laporan::with('kategori')->find($laporanId);
        $this->assertEquals('TERVALIDASI', $laporan->status);
        $this->assertEquals('Masalah Drainase', $laporan->kategori->nama_kategori);
        $this->assertEquals(2, $laporan->kategori_id);

        $this->assertDatabaseHas('riwayat_status_laporan', [
            'laporan_id' => $laporanId,
            'status' => 'TERVALIDASI',
        ]);
    }

    public function test_cv_job_routes_to_admin_when_confidence_below_threshold(): void
    {
        $laporanId = $this->createReportWithPhoto('DIPROSES', 1);

        $mockCv = Mockery::mock(CvService::class);
        $mockCv->shouldReceive('isCategorySupported')->andReturn(true);
        $mockCv->shouldReceive('getThreshold')->andReturn(0.80);
        $mockCv->shouldReceive('analyze')->once()->andReturn([
            'label' => 'RUSAK',
            'confidence' => 0.65, // Below 0.80 threshold
            'detected_category' => 'Trotoar Rusak',
        ]);
        $this->app->instance(CvService::class, $mockCv);

        ProcessReportCv::dispatchSync($laporanId);

        $laporan = Laporan::find($laporanId);
        $this->assertEquals('MENUNGGU_VALIDASI_ADMIN', $laporan->status);
        $this->assertEquals('RUSAK', $laporan->cv_label);
        $this->assertEquals(0.65, (float) $laporan->confidence_score);
    }

    public function test_cv_job_routes_to_admin_when_label_is_spam_or_baik(): void
    {
        $laporanId = $this->createReportWithPhoto('DIPROSES', 1);

        $mockCv = Mockery::mock(CvService::class);
        $mockCv->shouldReceive('isCategorySupported')->andReturn(true);
        $mockCv->shouldReceive('getThreshold')->andReturn(0.80);
        $mockCv->shouldReceive('analyze')->once()->andReturn([
            'label' => 'SPAM',
            'confidence' => 0.95,
            'detected_category' => null,
        ]);
        $this->app->instance(CvService::class, $mockCv);

        ProcessReportCv::dispatchSync($laporanId);

        $laporan = Laporan::find($laporanId);
        $this->assertEquals('MENUNGGU_VALIDASI_ADMIN', $laporan->status);
        $this->assertEquals('SPAM', $laporan->cv_label);
    }

    public function test_cv_job_fallback_when_category_not_supported(): void
    {
        $laporanId = $this->createReportWithPhoto('DIPROSES', 1);

        $mockCv = Mockery::mock(CvService::class);
        $mockCv->shouldReceive('isCategorySupported')->andReturn(false);
        $mockCv->shouldNotReceive('analyze');
        $this->app->instance(CvService::class, $mockCv);

        ProcessReportCv::dispatchSync($laporanId);

        $laporan = Laporan::find($laporanId);
        $this->assertEquals('MENUNGGU_VALIDASI_ADMIN', $laporan->status);
    }

    public function test_cv_job_is_idempotent(): void
    {
        $laporanId = $this->createReportWithPhoto('TERVALIDASI', 1);

        $mockCv = Mockery::mock(CvService::class);
        $mockCv->shouldNotReceive('analyze');
        $this->app->instance(CvService::class, $mockCv);

        ProcessReportCv::dispatchSync($laporanId);

        $laporan = Laporan::find($laporanId);
        $this->assertEquals('TERVALIDASI', $laporan->status);
    }

    public function test_citizen_can_view_analysis_page(): void
    {
        $laporanId = $this->createReportWithPhoto('DIPROSES', 1);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->wargaToken)
            ->get("/laporan/{$laporanId}/analisis");

        $response->assertStatus(200);
        $response->assertSee('Hasil Analisis Otomatis');
        $response->assertSee((string) $laporanId);
    }
}
