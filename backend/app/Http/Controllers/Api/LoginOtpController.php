<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\LoginOtpMail;
use App\Models\Admin;
use App\Models\Warga;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class LoginOtpController extends Controller
{
    private function account(array $challenge): Admin|Warga|null
    {
        return ($challenge['role'] ?? '') === 'admin'
            ? Admin::find($challenge['user_id'])
            : Warga::find($challenge['user_id']);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['challenge_id' => 'required|uuid', 'otp' => 'required|digits:6']);
        $key = 'login_otp:' . $data['challenge_id'];
        $lock = Cache::lock('login_otp_lock:' . $data['challenge_id'], 10);
        if (!$lock->get()) {
            return response()->json(['error' => ['message' => 'Permintaan sedang diproses.']], 429);
        }
        try {
            $challenge = Cache::get($key);
            if (!$challenge || now()->timestamp >= $challenge['expires_at']) {
                Cache::forget($key);
                return response()->json(['error' => ['message' => 'OTP kedaluwarsa. Silakan login kembali.']], 410);
            }
            if ($challenge['attempts'] >= 5) {
                Cache::forget($key);
                return response()->json(['error' => ['message' => 'Batas percobaan OTP tercapai. Silakan login kembali.']], 429);
            }
            if (!Hash::check($data['otp'], $challenge['hash'])) {
                $challenge['attempts']++;
                if ($challenge['attempts'] >= 5) {
                    Cache::forget($key);
                    return response()->json(['error' => ['message' => 'Batas percobaan OTP tercapai. Silakan login kembali.']], 429);
                }
                Cache::put($key, $challenge, \Carbon\Carbon::createFromTimestamp($challenge['expires_at']));
                return response()->json(['error' => ['message' => 'Kode OTP salah. Sisa percobaan: ' . (5 - $challenge['attempts'])]], 422);
            }
            $user = $this->account($challenge);
            if (!$user || ($user->terkunci_hingga && now()->lt($user->terkunci_hingga))) {
                Cache::forget($key);
                return response()->json(['error' => ['message' => 'Akun tidak tersedia atau terkunci.']], 403);
            }
            $role = $challenge['role'];
            $token = $user->createToken('auth-token', ['role:' . $role])->plainTextToken;
            $user->gagal_login = 0;
            $user->terkunci_hingga = null;
            $user->save();
            Cache::forget($key);
            return response()->json(['data' => [
                'token' => $token,
                'role' => $role,
                'redirect' => $role === 'admin' ? '/admin' : '/beranda-warga',
                'user' => [
                    'id' => $role === 'admin' ? $user->admin_id : $user->warga_id,
                    'nama' => $user->nama,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url ?? null,
                ],
            ]]);
        } finally {
            $lock->release();
        }
    }

    public function resend(Request $request): JsonResponse
    {
        $data = $request->validate(['challenge_id' => 'required|uuid']);
        $key = 'login_otp:' . $data['challenge_id'];
        $ipKey = 'otp_resend_ip:' . $request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            return response()->json(['error' => ['message' => 'Terlalu banyak permintaan. Coba lagi nanti.']], 429);
        }
        $lock = Cache::lock('login_otp_lock:' . $data['challenge_id'], 10);
        if (!$lock->get()) return response()->json(['error' => ['message' => 'Permintaan sedang diproses.']], 429);
        try {
            $challenge = Cache::get($key);
            if (!$challenge || now()->timestamp >= $challenge['expires_at'] || $challenge['attempts'] >= 5) {
                Cache::forget($key);
                return response()->json(['error' => ['message' => 'Sesi OTP berakhir. Silakan login kembali.']], 410);
            }
            $wait = $challenge['resend_at'] - now()->timestamp;
            if ($wait > 0) return response()->json(['error' => ['message' => 'Tunggu ' . $wait . ' detik sebelum kirim ulang.']], 429);
            $user = $this->account($challenge);
            if (!$user || !filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['error' => ['message' => 'Email akun tidak tersedia.']], 422);
            }
            $otp = (string) random_int(100000, 999999);
            try {
                Mail::to($user->email)->send(new LoginOtpMail($otp));
            } catch (\Throwable $e) {
                report($e);
                return response()->json(['error' => ['message' => 'Gagal mengirim ulang OTP.']], 503);
            }
            $challenge['hash'] = Hash::make($otp);
            $challenge['expires_at'] = now()->addMinutes(5)->timestamp;
            $challenge['resend_at'] = now()->addSeconds(60)->timestamp;
            Cache::put($key, $challenge, now()->addMinutes(5));
            RateLimiter::hit($ipKey, 3600);
            return response()->json(['data' => ['message' => 'OTP baru telah dikirim.', 'resend_after' => 60]]);
        } finally {
            $lock->release();
        }
    }
}
