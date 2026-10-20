<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Warga;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FacebookLoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Warga::where('email', 'mock_fb_warga@facebook.com')->delete();
        Warga::where('facebook_id', 'facebook_mock_123456')->delete();
        Admin::where('email', 'mock_fb_warga@facebook.com')->delete();
    }

    public function test_facebook_redirect(): void
    {
        $response = $this->get('/api/v1/auth/facebook/redirect');
        $response->assertStatus(302);
    }

    public function test_facebook_callback_creates_warga_and_sets_token(): void
    {
        $response = $this->get('/api/v1/auth/facebook/callback?mock=true');
        $response->assertStatus(200);
        $response->assertSee("localStorage.setItem('token'", false);
        $response->assertSee('/beranda-warga');

        $this->assertDatabaseHas('warga', [
            'email' => 'mock_fb_warga@facebook.com',
            'facebook_id' => 'facebook_mock_123456',
        ]);
    }

    public function test_facebook_callback_existing_warga_updates_facebook_id(): void
    {
        $warga = Warga::create([
            'nama' => 'Existing Facebook Warga',
            'email' => 'mock_fb_warga@facebook.com',
            'password' => Hash::make('password123'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
        ]);

        $response = $this->get('/api/v1/auth/facebook/callback?mock=true');
        $response->assertStatus(200);
        $response->assertSee("localStorage.setItem('token'", false);

        $this->assertEquals('facebook_mock_123456', $warga->fresh()->facebook_id);
    }

    public function test_facebook_callback_rejects_admin_email(): void
    {
        Admin::create([
            'nama' => 'Admin Test Conflict',
            'email' => 'mock_fb_warga@facebook.com',
            'password' => Hash::make('admin12345'),
            'role' => 'admin',
            'tanggal_dibuat' => now(),
            'gagal_login' => 0,
        ]);

        $response = $this->get('/api/v1/auth/facebook/callback?mock=true');
        $response->assertStatus(302);
        $response->assertRedirect('/login?error=email_reserved_for_admin');
    }
}
