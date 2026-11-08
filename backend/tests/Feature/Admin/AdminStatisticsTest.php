<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Warga;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminStatisticsTest extends TestCase
{
    use DatabaseTransactions;

    protected Admin $admin;
    protected string $adminToken;
    protected Warga $warga;
    protected string $wargaToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'nama' => 'Admin Stat Test',
            'email' => 'admin_stat_' . uniqid() . '@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'tanggal_dibuat' => now(),
            'gagal_login' => 0,
        ]);
        $this->adminToken = $this->admin->createToken('admin_auth', ['role:admin'])->plainTextToken;

        $this->warga = Warga::create([
            'nama' => 'Warga Tester',
            'email' => 'warga_stat_' . uniqid() . '@test.com',
            'no_hp' => '62899' . rand(1000000, 9999999),
            'password' => Hash::make('password123'),
            'role' => 'warga',
            'status_verifikasi' => true,
        ]);
        $this->wargaToken = $this->warga->createToken('warga_auth', ['role:warga'])->plainTextToken;
    }

    public function test_admin_dapat_mengambil_data_statistik(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_laporan',
                        'menunggu_validasi',
                        'tervalidasi',
                        'ditolak',
                        'belum_ditangani',
                        'sedang_diperbaiki',
                        'sudah_diperbaiki',
                        'persentase_valid',
                        'rata_rata_skor_jalan',
                        'total_warga',
                    ],
                    'kategori',
                    'segmen',
                ],
                'meta' => [
                    'area',
                    'timestamp',
                ]
            ]);
    }

    public function test_warga_dilarang_mengakses_statistik_admin(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->wargaToken)
            ->getJson('/api/v1/admin/statistics');

        $response->assertStatus(403);
    }

    public function test_tamu_tanpa_token_dilarang_mengakses_statistik(): void
    {
        $response = $this->getJson('/api/v1/admin/statistics');

        $response->assertStatus(401);
    }

    public function test_halaman_blade_statistik_admin_bisa_dimuat(): void
    {
        $response = $this->get('/admin/statistik');

        $response->assertStatus(200)
            ->assertSee('Ringkasan Statistik')
            ->assertSee('Kecamatan Mulyorejo, Surabaya');
    }
}
