<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Warga;
use App\Support\MessageCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Clean test users
        Warga::where('email', 'ahmad.fauzi@gmail.com')->delete();
        Warga::where('email', 'phone.test@gmail.com')->delete();
        Warga::where('no_hp', '6281234567890')->delete();
    }

    public function test_warga_login_berhasil(): void
    {
        $warga = Warga::create([
            'nama' => 'Ahmad Fauzi',
            'email' => 'ahmad.fauzi@gmail.com',
            'no_hp' => '6281234567890',
            'password' => Hash::make('warga12345'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'ahmad.fauzi@gmail.com',
            'password' => 'warga12345',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.role', 'warga')
            ->assertJsonPath('data.redirect', '/beranda-warga')
            ->assertJsonStructure(['data' => ['token', 'role', 'redirect']]);
    }

    public function test_admin_login_berhasil(): void
    {
        $adminEmail = 'admin_login_test@pedestriroute.test';
        Admin::where('email', $adminEmail)->delete();

        Admin::create([
            'nama' => 'Admin Test',
            'email' => $adminEmail,
            'password' => Hash::make('admin12345'),
            'role' => 'admin',
            'tanggal_dibuat' => now(),
            'gagal_login' => 0,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => $adminEmail,
            'password' => 'admin12345',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.redirect', '/admin');
    }

    public function test_login_dengan_nomor_telepon_berformat_lain(): void
    {
        Warga::create([
            'nama' => 'User Phone',
            'email' => 'phone.test@gmail.com',
            'no_hp' => '6281234567890',
            'password' => Hash::make('warga12345'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
        ]);

        // Login using +62 format
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => '+6281234567890',
            'password' => 'warga12345',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.role', 'warga');

        // Login using 08 format
        $response2 = $this->postJson('/api/v1/auth/login', [
            'identifier' => '081234567890',
            'password' => 'warga12345',
        ]);

        $response2->assertStatus(200);
    }

    public function test_login_gagal_kolom_kosong(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'ahmad.fauzi@gmail.com',
            'password' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'LOGIN_FIELDS_REQUIRED')
            ->assertJsonPath('error.message', MessageCatalog::LOGIN_FIELDS_REQUIRED);
    }

    public function test_login_gagal_akun_tidak_ditemukan(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'belumdaftar@gmail.com',
            'password' => 'sembarang123',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'LOGIN_INVALID')
            ->assertJsonPath('error.message', MessageCatalog::LOGIN_INVALID);
    }

    public function test_login_gagal_kata_sandi_salah_dicatat(): void
    {
        $warga = Warga::create([
            'nama' => 'Ahmad Fauzi',
            'email' => 'ahmad.fauzi@gmail.com',
            'no_hp' => '6281234567890',
            'password' => Hash::make('warga12345'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'ahmad.fauzi@gmail.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'LOGIN_INVALID')
            ->assertJsonPath('error.message', MessageCatalog::LOGIN_INVALID);

        $this->assertEquals(1, $warga->fresh()->gagal_login);
    }

    public function test_akun_terkunci_setelah_maksimal_gagal(): void
    {
        $warga = Warga::create([
            'nama' => 'Ahmad Fauzi',
            'email' => 'ahmad.fauzi@gmail.com',
            'no_hp' => '6281234567890',
            'password' => Hash::make('warga12345'),
            'tanggal_daftar' => now(),
            'gagal_login' => 5,
            'terkunci_hingga' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'ahmad.fauzi@gmail.com',
            'password' => 'warga12345',
        ]);

        $response->assertStatus(423)
            ->assertJsonPath('error.code', 'LOGIN_LOCKED');
    }
}
