<?php

namespace Tests\Feature\Auth;

use App\Models\Warga;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    public function test_google_redirect(): void
    {
        $response = $this->get('/api/v1/auth/google/redirect');
        $response->assertStatus(302);
    }

    public function test_google_callback_creates_warga_and_sets_token(): void
    {
        Warga::where('email', 'mock_warga@gmail.com')->delete();

        $response = $this->get('/api/v1/auth/google/callback?mock=true');
        $response->assertStatus(200);
        $response->assertSee('localStorage.setItem(\'token\'', false);
        $response->assertSee('/beranda-warga');

        $this->assertDatabaseHas('warga', [
            'email' => 'mock_warga@gmail.com',
            'google_id' => 'google_mock_123456',
        ]);
    }
}
