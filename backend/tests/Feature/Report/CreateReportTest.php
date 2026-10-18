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
            'email' => 'reporter_' . uniqid() . '@test.com',
            'no_hp' => '62811' . rand(1000000, 9999999),
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

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/reports', [
                'latitude' => $lat,
                'longitude' => $lon,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Trotoar berlubang dan membahayakan pejalan kaki',
                'foto' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', MessageCatalog::REPORT_SENT)
            ->assertJsonPath('data.status', 'MENUNGGU_VALIDASI_ADMIN');

        $laporanId = $response->json('data.laporan_id');
        $this->assertDatabaseHas('laporan', [
            'laporan_id' => $laporanId,
            'status' => 'MENUNGGU_VALIDASI_ADMIN',
            'warga_id' => $this->warga->warga_id,
        ]);

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

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
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

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
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

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
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

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
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
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
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
            'email' => 'other_' . uniqid() . '@test.com',
            'no_hp' => '628999' . rand(100000, 999999),
            'password' => Hash::make('password123'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
        ]);
        $otherToken = $otherWarga->createToken('token2', ['role:warga'])->plainTextToken;

        // Reset memoized auth guard in test container
        $this->app['auth']->forgetGuards();

        $response = $this->withHeader('Authorization', 'Bearer ' . $otherToken)
            ->getJson("/api/v1/reports/{$laporanId}");

        $response->assertStatus(403);
    }
}
