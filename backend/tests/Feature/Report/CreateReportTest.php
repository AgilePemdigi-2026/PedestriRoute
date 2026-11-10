<?php

namespace Tests\Feature\Report;

use App\Models\Warga;
use App\Support\MessageCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateReportTest extends TestCase
{
    use DatabaseTransactions;

    protected Warga $warga;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->warga = Warga::create([
            'nama' => 'Test Reporter',
            'email' => 'reporter_'.uniqid().'@test.com',
            'no_hp' => '62811'.rand(1000000, 9999999),
            'password' => Hash::make('password123'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
        ]);

        $this->token = $this->warga->createToken('test-token', ['role:warga'])->plainTextToken;
    }

    public function test_melaporkan_kondisi_fasilitas_berhasil(): void
    {
        // Valid coordinate in Mulyosari near seeded segment
        $lon = 112.7965;
        $lat = -7.2663;

        $file = UploadedFile::fake()->image('trotoar_rusak.jpg', 600, 600);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/reports', [
                'latitude' => $lat,
                'longitude' => $lon,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Trotoar berlubang dan membahayakan pejalan kaki',
                'foto' => $file,
            ]);

        $expectedStatus = config('services.cv.enabled') ? 'DIPROSES' : 'MENUNGGU_VALIDASI_ADMIN';

        $response->assertStatus(201)
            ->assertJsonPath('message', MessageCatalog::REPORT_SENT)
            ->assertJsonPath('data.status', $expectedStatus);

        $laporanId = $response->json('data.laporan_id');
        $this->assertDatabaseHas('laporan', [
            'laporan_id' => $laporanId,
            'warga_id' => $this->warga->warga_id,
        ]);
        $laporanDb = \Illuminate\Support\Facades\DB::table('laporan')->where('laporan_id', $laporanId)->first();
        $this->assertTrue(in_array($laporanDb->status, ['DIPROSES', 'TERVALIDASI', 'MENUNGGU_VALIDASI_ADMIN', 'DITOLAK']));

        $this->assertDatabaseHas('foto_laporan', [
            'laporan_id' => $laporanId,
        ]);

        $this->assertDatabaseHas('notifikasi', [
            'penerima_tipe' => 'WARGA',
            'penerima_id' => $this->warga->warga_id,
        ]);
    }

    public function test_lapor_gagal_lokasi_di_luar_area(): void
    {
        $file = UploadedFile::fake()->image('trotoar.jpg');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/reports', [
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'kategori_id' => 1,
                'deskripsi' => 'Trotoar rusak Jakarta',
                'foto' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'REPORT_OUT_OF_AREA')
            ->assertJsonPath('error.message', MessageCatalog::REPORT_OUT_OF_AREA);
    }

    public function test_lapor_gagal_tidak_ada_segmen_di_dekat_titik(): void
    {
        $file = UploadedFile::fake()->image('trotoar.jpg');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/reports', [
                'latitude' => -7.1800,
                'longitude' => 112.8800,
                'kategori_id' => 1,
                'deskripsi' => 'Titik jauh dari jalan',
                'foto' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'REPORT_NO_SEGMENT')
            ->assertJsonPath('error.message', MessageCatalog::REPORT_NO_SEGMENT);
    }

    public function test_lapor_gagal_format_foto_tidak_valid(): void
    {
        $file = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/reports', [
                'latitude' => -7.2663,
                'longitude' => 112.7965,
                'kategori_id' => 1,
                'deskripsi' => 'Deskripsi valid',
                'foto' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'REPORT_PHOTO_INVALID')
            ->assertJsonPath('error.message', MessageCatalog::REPORT_PHOTO_INVALID);
    }

    public function test_lapor_gagal_ukuran_foto_melebihi_15mb(): void
    {
        $file = UploadedFile::fake()->create('trotoar_besar.jpg', 16 * 1024, 'image/jpeg');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/reports', [
                'latitude' => -7.2663,
                'longitude' => 112.7965,
                'kategori_id' => 1,
                'deskripsi' => 'Deskripsi valid',
                'foto' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'REPORT_PHOTO_INVALID')
            ->assertJsonPath('error.message', MessageCatalog::REPORT_PHOTO_INVALID);
    }

    public function test_lapor_gagal_deskripsi_atau_kategori_kosong(): void
    {
        $file = UploadedFile::fake()->image('trotoar.jpg');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/reports', [
                'latitude' => -7.2663,
                'longitude' => 112.7965,
                'kategori_id' => '',
                'deskripsi' => '',
                'foto' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'REPORT_INCOMPLETE')
            ->assertJsonPath('error.message', MessageCatalog::REPORT_INCOMPLETE);
    }

    public function test_warga_lain_tidak_bisa_melihat_laporan(): void
    {
        $file = UploadedFile::fake()->image('trotoar.jpg');
        $createRes = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/reports', [
                'latitude' => -7.2663,
                'longitude' => 112.7965,
                'kategori_id' => 1,
                'deskripsi' => 'Laporan milik warga 1',
                'foto' => $file,
            ]);
        $laporanId = $createRes->json('data.laporan_id');

        // Other warga
        $otherWarga = Warga::create([
            'nama' => 'Other Warga',
            'email' => 'other_'.uniqid().'@test.com',
            'no_hp' => '628999'.rand(100000, 999999),
            'password' => Hash::make('password123'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
        ]);
        $otherToken = $otherWarga->createToken('token2', ['role:warga'])->plainTextToken;

        // Reset memoized auth guard in test container
        $this->app['auth']->forgetGuards();

        $response = $this->withHeader('Authorization', 'Bearer '.$otherToken)
            ->getJson("/api/v1/reports/{$laporanId}");

        $response->assertStatus(403);
    }

    public function test_endpoint_kategori_mengembalikan_5_kategori_fasilitas(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.nama_kategori', 'Trotoar Rusak')
            ->assertJsonPath('data.0.kode_label', 0)
            ->assertJsonPath('data.1.nama_kategori', 'Masalah Drainase')
            ->assertJsonPath('data.2.nama_kategori', 'Guiding Block Rusak')
            ->assertJsonPath('data.3.nama_kategori', 'Penyebrangan Rusak')
            ->assertJsonPath('data.4.nama_kategori', 'Parkir Liar');
    }

    public function test_pelaporan_dengan_slug_kategori_atau_kode_label(): void
    {
        $file = UploadedFile::fake()->image('drainase.jpg');

        // Test with slug 'masalah_drainase' and alias 'drainase_terbuka'
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/reports', [
                'latitude' => -7.2663,
                'longitude' => 112.7965,
                'kategori_id' => 'masalah_drainase',
                'deskripsi' => 'Tutup gorong-gorong terbuka',
                'foto' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', MessageCatalog::REPORT_SENT);

        // Test with 0-based code 0 (Trotoar Rusak)
        $file2 = UploadedFile::fake()->image('trotoar0.jpg');
        $response2 = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/reports', [
                'latitude' => -7.2663,
                'longitude' => 112.7965,
                'kategori_id' => 0,
                'deskripsi' => 'Paving amblas kode 0',
                'foto' => $file2,
            ]);

        $response2->assertStatus(201)
            ->assertJsonPath('message', MessageCatalog::REPORT_SENT);

        // Test with parkir liar alias
        $file3 = UploadedFile::fake()->image('parkir.jpg');
        $response3 = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/reports', [
                'latitude' => -7.2663,
                'longitude' => 112.7965,
                'kategori_id' => 'parkir_liar',
                'deskripsi' => 'Sepeda motor parkir di atas trotoar',
                'foto' => $file3,
            ]);

        $response3->assertStatus(201)
            ->assertJsonPath('message', MessageCatalog::REPORT_SENT);
    }

    public function test_pelaporan_dengan_alamat_manual_tersimpan_di_lokasi(): void
    {
        $file = UploadedFile::fake()->image('trotoar_manual_alamat.jpg');
        $customAddress = 'Jl. Sutorejo Prima Utara No. 12, Mulyorejo, Surabaya';

        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/reports', [
                'latitude' => -7.2663,
                'longitude' => 112.7965,
                'kategori_id' => 1,
                'deskripsi' => 'Trotoar amblas di depan ruko',
                'alamat' => $customAddress,
                'foto' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', MessageCatalog::REPORT_SENT);

        $laporanId = $response->json('data.laporan_id');

        $this->assertDatabaseHas('lokasi', [
            'alamat' => $customAddress,
        ]);

        $detailResponse = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/reports/'.$laporanId);

        $detailResponse->assertStatus(200)
            ->assertJsonPath('data.lokasi.alamat', $customAddress);
    }
}
