<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\RegisterOtpMail;
use App\Models\Admin;
use App\Models\Warga;
use App\Support\MessageCatalog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class RegisterOtpController extends Controller
{
    /**
     * Verify OTP and complete Warga registration.
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => 'required|uuid',
            'otp' => 'required|digits:6',
        ]);

        $challengeId = $data['challenge_id'];
        $key = 'register_otp:' . $challengeId;
        $lockKey = 'register_otp_lock:' . $challengeId;

        $lock = Cache::lock($lockKey, 10);
        if (!$lock->get()) {
            return response()->json([
                'error' => [
                    'code' => 'THROTTLED',
                    'message' => 'Permintaan sedang diproses.',
                ]
            ], 429);
        }

        try {
            $challenge = Cache::get($key);

            if (!$challenge || now()->timestamp >= $challenge['expires_at']) {
                Cache::forget($key);
                return response()->json([
                    'error' => [
                        'code' => 'OTP_EXPIRED',
                        'message' => 'Kode OTP kedaluwarsa. Silakan lakukan pendaftaran kembali.',
                    ]
                ], 410);
            }

            if ($challenge['attempts'] >= 5) {
                Cache::forget($key);
                return response()->json([
                    'error' => [
                        'code' => 'OTP_MAX_ATTEMPTS',
                        'message' => 'Batas percobaan OTP tercapai. Silakan lakukan pendaftaran kembali.',
                    ]
                ], 429);
            }

            if (!Hash::check($data['otp'], $challenge['otp_hash'])) {
                $challenge['attempts']++;
                if ($challenge['attempts'] >= 5) {
                    Cache::forget($key);
                    return response()->json([
                        'error' => [
                            'code' => 'OTP_MAX_ATTEMPTS',
                            'message' => 'Batas percobaan OTP tercapai. Silakan lakukan pendaftaran kembali.',
                        ]
                    ], 429);
                }

                Cache::put($key, $challenge, Carbon::createFromTimestamp($challenge['expires_at']));
                return response()->json([
                    'error' => [
                        'code' => 'OTP_INVALID',
                        'message' => 'Kode OTP salah. Sisa percobaan: ' . (5 - $challenge['attempts']),
                    ]
                ], 422);
            }

            // Check if email or phone already exists before creating
            if (Warga::where('email', $challenge['email'])->exists() || Admin::where('email', $challenge['email'])->exists()) {
                Cache::forget($key);
                return response()->json([
                    'error' => [
                        'code' => 'REG_EMAIL_EXISTS',
                        'message' => MessageCatalog::REG_EMAIL_EXISTS,
                    ]
                ], 422);
            }

            if (Warga::where('no_hp', $challenge['no_hp'])->exists()) {
                Cache::forget($key);
                return response()->json([
                    'error' => [
                        'code' => 'REG_PHONE_EXISTS',
                        'message' => MessageCatalog::REG_PHONE_EXISTS,
                    ]
                ], 422);
            }

            // Create Warga account
            $warga = Warga::create([
                'nama' => $challenge['nama'],
                'email' => $challenge['email'],
                'no_hp' => $challenge['no_hp'],
                'password' => $challenge['password_hash'],
                'tanggal_daftar' => now(),
                'gagal_login' => 0,
                'notif_jalur_aktif' => true,
            ]);

            Cache::forget($key);

            // Issue Sanctum token for immediate auto-login
            $token = $warga->createToken('auth-token', ['role:warga'])->plainTextToken;

            return response()->json([
                'data' => [
                    'warga_id' => $warga->warga_id,
                    'token' => $token,
                    'role' => 'warga',
                    'redirect' => '/beranda-warga',
                    'user' => [
                        'id' => $warga->warga_id,
                        'nama' => $warga->nama,
                        'email' => $warga->email,
                        'avatar_url' => null,
                    ],
                ],
                'message' => MessageCatalog::REG_SUCCESS,
            ], 201);
        } finally {
            $lock->release();
        }
    }

    /**
     * Resend registration OTP.
     */
    public function resend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => 'required|uuid',
        ]);

        $challengeId = $data['challenge_id'];
        $key = 'register_otp:' . $challengeId;
        $ipKey = 'otp_resend_ip:' . $request->ip();

        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            return response()->json([
                'error' => [
                    'code' => 'THROTTLED',
                    'message' => MessageCatalog::THROTTLED,
                ]
            ], 429);
        }

        $lockKey = 'register_otp_lock:' . $challengeId;
        $lock = Cache::lock($lockKey, 10);
        if (!$lock->get()) {
            return response()->json([
                'error' => [
                    'code' => 'THROTTLED',
                    'message' => 'Permintaan sedang diproses.',
                ]
            ], 429);
        }

        try {
            $challenge = Cache::get($key);
            if (!$challenge || now()->timestamp >= $challenge['expires_at'] || $challenge['attempts'] >= 5) {
                Cache::forget($key);
                return response()->json([
                    'error' => [
                        'code' => 'OTP_EXPIRED',
                        'message' => 'Sesi OTP berakhir. Silakan lakukan pendaftaran kembali.',
                    ]
                ], 410);
            }

            $wait = $challenge['resend_at'] - now()->timestamp;
            if ($wait > 0) {
                return response()->json([
                    'error' => [
                        'code' => 'THROTTLED',
                        'message' => 'Tunggu ' . $wait . ' detik sebelum mengirim ulang OTP.',
                    ]
                ], 429);
            }

            $otp = (string) random_int(100000, 999999);
            try {
                Mail::to($challenge['email'])->send(new RegisterOtpMail($otp));
            } catch (\Throwable $e) {
                report($e);
                return response()->json([
                    'error' => [
                        'code' => 'OTP_SEND_FAILED',
                        'message' => 'Gagal mengirim ulang kode OTP ke email.',
                    ]
                ], 503);
            }

            $challenge['otp_hash'] = Hash::make($otp);
            $challenge['expires_at'] = now()->addMinutes(5)->timestamp;
            $challenge['resend_at'] = now()->addSeconds(60)->timestamp;
            Cache::put($key, $challenge, now()->addMinutes(5));

            RateLimiter::hit($ipKey, 60);

            return response()->json([
                'data' => [
                    'message' => 'Kode OTP baru telah dikirim ke email Anda.',
                    'resend_after' => 60,
                ]
            ], 200);
        } finally {
            $lock->release();
        }
    }
}
