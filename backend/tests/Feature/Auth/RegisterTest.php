<?php

namespace Tests\Feature\Auth;

use App\Mail\RegisterOtpMail;
use App\Models\Warga;
use App\Support\MessageCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Warga::where('email', 'johnmaps@gmail.com')->delete();
        Warga::where('no_hp', '6281234567890')->delete();
    }

    public function test_registrasi_berhasil_dengan_verifikasi_otp(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'nama' => 'John Maps',
            'email' => 'johnmaps@gmail.com',
            'no_hp' => '081234567890',
            'password' => 'warga12345',
            'password_confirmation' => 'warga12345',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.otp_required', true)
            ->assertJsonStructure(['data' => ['challenge_id', 'email_hint', 'expires_in']]);

        $challengeId = $response->json('data.challenge_id');
        $otp = null;

        Mail::assertSent(RegisterOtpMail::class, function ($mail) use (&$otp) {
            $otp = $mail->otp;
            return true;
        });

        $this->assertNotNull($otp);

        // Verify with valid OTP
        $verifyRes = $this->postJson('/api/v1/auth/otp/verify', [
            'challenge_id' => $challengeId,
            'otp' => $otp,
        ]);

        $verifyRes->assertStatus(201)
            ->assertJsonPath('message', MessageCatalog::REG_SUCCESS)
            ->assertJsonStructure(['data' => ['warga_id', 'token', 'role', 'redirect']]);

        $user = Warga::where('email', 'johnmaps@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('6281234567890', $user->no_hp);
        $this->assertTrue(Hash::check('warga12345', $user->password));
        $this->assertNotEquals('warga12345', $user->password);
    }

    public function test_verifikasi_otp_gagal_kode_salah(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'nama' => 'John Maps',
            'email' => 'johnmaps@gmail.com',
            'no_hp' => '081234567890',
            'password' => 'warga12345',
            'password_confirmation' => 'warga12345',
        ]);

        $challengeId = $response->json('data.challenge_id');

        $verifyRes = $this->postJson('/api/v1/auth/otp/verify', [
            'challenge_id' => $challengeId,
            'otp' => '000000',
        ]);

        $verifyRes->assertStatus(422)
            ->assertJsonPath('error.code', 'OTP_INVALID');
    }

    public function test_gagal_format_tidak_valid_atau_kolom_kosong(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'nama' => 'Ahmad Fauzi',
            'email' => 'ahmadfauzi.gmail.com', // invalid email
            'no_hp' => '',                    // empty phone
            'password' => 'warga12345',
            'password_confirmation' => 'warga12345',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'REG_INVALID')
            ->assertJsonPath('error.message', MessageCatalog::REG_INVALID);
    }

    public function test_gagal_email_sudah_terdaftar(): void
    {
        Warga::create([
            'nama' => 'John Existing',
            'email' => 'johnmaps@gmail.com',
            'no_hp' => '628999999999',
            'password' => Hash::make('warga12345'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'nama' => 'John New',
            'email' => 'johnmaps@gmail.com',
            'no_hp' => '081234567890',
            'password' => 'warga12345',
            'password_confirmation' => 'warga12345',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'REG_EMAIL_EXISTS')
            ->assertJsonPath('error.message', MessageCatalog::REG_EMAIL_EXISTS);
    }

    public function test_gagal_nomor_telepon_sudah_terdaftar(): void
    {
        Warga::create([
            'nama' => 'Existing Phone',
            'email' => 'other@gmail.com',
            'no_hp' => '6281234567890',
            'password' => Hash::make('warga12345'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'nama' => 'John Maps',
            'email' => 'johnmaps@gmail.com',
            'no_hp' => '081234567890',
            'password' => 'warga12345',
            'password_confirmation' => 'warga12345',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'REG_PHONE_EXISTS')
            ->assertJsonPath('error.message', MessageCatalog::REG_PHONE_EXISTS);
    }

    public function test_gagal_nomor_yang_sama_dengan_format_berbeda(): void
    {
        Warga::create([
            'nama' => 'Existing Phone',
            'email' => 'other@gmail.com',
            'no_hp' => '6281234567890',
            'password' => Hash::make('warga12345'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
        ]);

        // Trying to register with +6281234567890
        $response = $this->postJson('/api/v1/auth/register', [
            'nama' => 'John Maps',
            'email' => 'johnmaps@gmail.com',
            'no_hp' => '+6281234567890',
            'password' => 'warga12345',
            'password_confirmation' => 'warga12345',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'REG_PHONE_EXISTS')
            ->assertJsonPath('error.message', MessageCatalog::REG_PHONE_EXISTS);
    }

    public function test_gagal_konfirmasi_kata_sandi_tidak_sesuai(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'nama' => 'John Maps',
            'email' => 'johnmaps@gmail.com',
            'no_hp' => '081234567890',
            'password' => 'warga12345',
            'password_confirmation' => 'warga54321',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'REG_PASSWORD_MISMATCH')
            ->assertJsonPath('error.message', MessageCatalog::REG_PASSWORD_MISMATCH);
    }
}
