<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Warga;
use App\Support\MessageCatalog;
use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Warga / Admin Login (F1).
     */
    public function login(Request $request): JsonResponse
    {
        // 1. IP Rate Limiting (FR-F1-08)
        $ipKey = 'login_ip:' . $request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            return response()->json([
                'error' => [
                    'code' => 'THROTTLED',
                    'message' => MessageCatalog::THROTTLED,
                ]
            ], 429);
        }
        RateLimiter::hit($ipKey, 60);

        // 2. Validate empty fields (FR-F1-02)
        $identifier = trim((string) $request->input('identifier', ''));
        $password = (string) $request->input('password', '');

        if ($identifier === '' || $password === '') {
            return response()->json([
                'error' => [
                    'code' => 'LOGIN_FIELDS_REQUIRED',
                    'message' => MessageCatalog::LOGIN_FIELDS_REQUIRED,
                ]
            ], 422);
        }

        // 3. Search account: by email (lowercase) or normalized phone
        $email = strtolower($identifier);
        $normalizedPhone = PhoneNormalizer::isValid($identifier) ? PhoneNormalizer::normalize($identifier) : $identifier;

        // Search in Warga
        $user = Warga::where('email', $email)
            ->orWhere('no_hp', $normalizedPhone)
            ->first();
        $role = 'warga';

        // Search in Admin if not found in Warga
        if (!$user) {
            $user = Admin::where('email', $email)->first();
            $role = 'admin';
        }

        // 4. Check account lockout (FR-F1-05)
        if ($user && $user->terkunci_hingga && Carbon::now()->lt($user->terkunci_hingga)) {
            $remainingMinutes = Carbon::now()->diffInMinutes($user->terkunci_hingga) + 1;
            $msg = MessageCatalog::format(MessageCatalog::LOGIN_LOCKED, ['menit' => (string)$remainingMinutes]);
            return response()->json([
                'error' => [
                    'code' => 'LOGIN_LOCKED',
                    'message' => $msg,
                ]
            ], 423);
        }

        // 5. Check credential match
        if (!$user || !Hash::check($password, $user->password)) {
            if ($user) {
                $user->gagal_login += 1;
                $maxAttempts = (int) env('CFG_MAX_LOGIN_ATTEMPTS', 5);
                if ($user->gagal_login >= $maxAttempts) {
                    $lockoutMinutes = (int) env('CFG_LOCKOUT_MINUTES', 15);
                    $user->terkunci_hingga = Carbon::now()->addMinutes($lockoutMinutes);
                }
                $user->save();
            }

            return response()->json([
                'error' => [
                    'code' => 'LOGIN_INVALID',
                    'message' => MessageCatalog::LOGIN_INVALID,
                ]
            ], 401);
        }

        // Reset lockout and failed attempts on successful login
        $user->gagal_login = 0;
        $user->terkunci_hingga = null;
        $user->save();

        // 6. Create Sanctum token with role ability
        $token = $user->createToken('auth-token', ['role:' . $role])->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'role' => $role,
                'redirect' => $role === 'admin' ? '/admin' : '/beranda-warga',
                'user' => [
                    'id' => $role === 'admin' ? $user->admin_id : $user->warga_id,
                    'nama' => $user->nama,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url ?? null,
                ],
            ]
        ], 200);
    }

    /**
     * Warga Register (F2).
     */
    public function register(Request $request): JsonResponse
    {
        // 1. IP Rate Limiting (FR-F1-08)
        $ipKey = 'register_ip:' . $request->ip();
        if (RateLimiter::tooManyAttempts($ipKey, 10)) {
            return response()->json([
                'error' => [
                    'code' => 'THROTTLED',
                    'message' => MessageCatalog::THROTTLED,
                ]
            ], 429);
        }
        RateLimiter::hit($ipKey, 60);

        // 2. Validate completeness and required fields (FR-F2-02)
        $nama = trim((string) $request->input('nama', ''));
        $email = trim((string) $request->input('email', ''));
        $noHp = trim((string) $request->input('no_hp', ''));
        $password = (string) $request->input('password', '');
        $passwordConfirmation = (string) $request->input('password_confirmation', '');

        if ($nama === '' || $email === '' || $noHp === '' || $password === '' || $passwordConfirmation === '') {
            return response()->json([
                'error' => [
                    'code' => 'REG_INVALID',
                    'message' => MessageCatalog::REG_INVALID,
                ]
            ], 422);
        }

        if (strlen($nama) > 100) {
            return response()->json([
                'error' => [
                    'code' => 'REG_INVALID',
                    'message' => MessageCatalog::REG_INVALID,
                ]
            ], 422);
        }

        // Email format check
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            return response()->json([
                'error' => [
                    'code' => 'REG_INVALID',
                    'message' => MessageCatalog::REG_INVALID,
                ]
            ], 422);
        }
        $email = strtolower($email);

        // Phone format check
        if (!PhoneNormalizer::isValid($noHp)) {
            return response()->json([
                'error' => [
                    'code' => 'REG_INVALID',
                    'message' => MessageCatalog::REG_INVALID,
                ]
            ], 422);
        }
        $normalizedPhone = PhoneNormalizer::normalize($noHp);

        // 3. Password match check (FR-F2-05)
        if ($password !== $passwordConfirmation) {
            return response()->json([
                'error' => [
                    'code' => 'REG_PASSWORD_MISMATCH',
                    'message' => MessageCatalog::REG_PASSWORD_MISMATCH,
                ]
            ], 422);
        }

        // 4. Password strength check (min 8 chars, letters and numbers)
        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return response()->json([
                'error' => [
                    'code' => 'REG_INVALID',
                    'message' => MessageCatalog::REG_INVALID,
                ]
            ], 422);
        }

        // 5. Unique email check across warga and admin (FR-F2-03)
        $emailExists = Warga::where('email', $email)->exists() || Admin::where('email', $email)->exists();
        if ($emailExists) {
            return response()->json([
                'error' => [
                    'code' => 'REG_EMAIL_EXISTS',
                    'message' => MessageCatalog::REG_EMAIL_EXISTS,
                ]
            ], 422);
        }

        // 6. Unique phone check in warga (FR-F2-04)
        $phoneExists = Warga::where('no_hp', $normalizedPhone)->exists();
        if ($phoneExists) {
            return response()->json([
                'error' => [
                    'code' => 'REG_PHONE_EXISTS',
                    'message' => MessageCatalog::REG_PHONE_EXISTS,
                ]
            ], 422);
        }

        // 7. Store new warga account (FR-F2-06)
        $warga = Warga::create([
            'nama' => $nama,
            'email' => $email,
            'no_hp' => $normalizedPhone,
            'password' => Hash::make($password),
            'tanggal_daftar' => now(),
            'gagal_login' => 0,
            'notif_jalur_aktif' => true,
        ]);

        return response()->json([
            'data' => [
                'warga_id' => $warga->warga_id,
            ],
            'message' => MessageCatalog::REG_SUCCESS,
        ], 201);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request): JsonResponse
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(null, 204);
    }

    /**
     * Get current authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $isWarga = $user instanceof Warga;

        return response()->json([
            'data' => [
                'id' => $isWarga ? $user->warga_id : $user->admin_id,
                'nama' => $user->nama,
                'email' => $user->email,
                'no_hp' => $isWarga ? $user->no_hp : null,
                'role' => $isWarga ? 'warga' : 'admin',
                'avatar_url' => $user->avatar_url ?? null,
            ]
        ]);
    }

    /**
     * Redirect to Google OAuth provider.
     */
    public function googleRedirect()
    {
        if (env('GOOGLE_OAUTH_MOCK', false)) {
            return redirect('/api/v1/auth/google/callback?mock=true');
        }

        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Handle Google OAuth callback.
     */
    public function googleCallback(Request $request)
    {
        if (env('GOOGLE_OAUTH_MOCK', false) && $request->has('mock')) {
            $email = 'mock_warga@gmail.com';
            $nama = 'Warga Google Mock';
            $googleId = 'google_mock_123456';
            $avatar = 'https://ui-avatars.com/api/?name=Warga+Google';
        } else {
            try {
                $googleUser = Socialite::driver('google')->stateless()->user();
                $email = strtolower($googleUser->getEmail());
                $nama = $googleUser->getName() ?? 'Pengguna Google';
                $googleId = $googleUser->getId();
                $avatar = $googleUser->getAvatar();
            } catch (\Exception $e) {
                return redirect('/login?error=google_failed');
            }
        }

        // Email cannot conflict with Admin
        if (Admin::where('email', $email)->exists()) {
            return redirect('/login?error=email_reserved_for_admin');
        }

        // Upsert Warga
        $warga = Warga::where('email', $email)->orWhere('google_id', $googleId)->first();
        if (!$warga) {
            $warga = Warga::create([
                'nama' => $nama,
                'email' => $email,
                'google_id' => $googleId,
                'avatar_url' => $avatar,
                'password' => Hash::make(Str::random(32)),
                'tanggal_daftar' => now(),
                'gagal_login' => 0,
                'notif_jalur_aktif' => true,
            ]);
        } else {
            $warga->google_id = $googleId;
            if ($avatar) {
                $warga->avatar_url = $avatar;
            }
            $warga->save();
        }

        $token = $warga->createToken('auth-token', ['role:warga'])->plainTextToken;

        // Render mini callback script to write token to localStorage and redirect to /beranda-warga
        return response()->make("
            <!DOCTYPE html>
            <html>
            <head><title>Otentikasi Berhasil</title></head>
            <body>
            <script>
                localStorage.setItem('token', " . json_encode($token) . ");
                localStorage.setItem('role', 'warga');
                localStorage.setItem('user', " . json_encode([
                    'id' => $warga->warga_id,
                    'nama' => $warga->nama,
                    'email' => $warga->email,
                    'avatar_url' => $warga->avatar_url,
                ]) . ");
                window.location.href = '/beranda-warga';
            </script>
            <p>Menghubungkan ke PedestriRoute...</p>
            </body>
            </html>
        ", 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Redirect to Facebook OAuth provider.
     */
    public function facebookRedirect()
    {
        if (env('FACEBOOK_OAUTH_MOCK', false)) {
            return redirect('/api/v1/auth/facebook/callback?mock=true');
        }

        return Socialite::driver('facebook')->stateless()->redirect();
    }

    /**
     * Handle Facebook OAuth callback.
     */
    public function facebookCallback(Request $request)
    {
        if (env('FACEBOOK_OAUTH_MOCK', false) && $request->has('mock')) {
            $email = 'mock_fb_warga@facebook.com';
            $nama = 'Warga Facebook Mock';
            $facebookId = 'facebook_mock_123456';
            $avatar = 'https://ui-avatars.com/api/?name=Warga+Facebook';
        } else {
            try {
                $fbUser = Socialite::driver('facebook')->stateless()->user();
                $email = strtolower($fbUser->getEmail() ?? $fbUser->getId() . '@facebook.com');
                $nama = $fbUser->getName() ?? 'Pengguna Facebook';
                $facebookId = $fbUser->getId();
                $avatar = $fbUser->getAvatar();
            } catch (\Exception $e) {
                return redirect('/login?error=facebook_failed');
            }
        }

        if (Admin::where('email', $email)->exists()) {
            return redirect('/login?error=email_reserved_for_admin');
        }

        $warga = Warga::where('email', $email)->orWhere('facebook_id', $facebookId)->first();
        if (!$warga) {
            $warga = Warga::create([
                'nama' => $nama,
                'email' => $email,
                'facebook_id' => $facebookId,
                'avatar_url' => $avatar,
                'password' => Hash::make(Str::random(32)),
                'tanggal_daftar' => now(),
                'gagal_login' => 0,
                'notif_jalur_aktif' => true,
            ]);
        } else {
            $warga->facebook_id = $facebookId;
            if ($avatar) {
                $warga->avatar_url = $avatar;
            }
            $warga->save();
        }

        $token = $warga->createToken('auth-token', ['role:warga'])->plainTextToken;

        return response()->make("
            <!DOCTYPE html>
            <html>
            <head><title>Otentikasi Berhasil</title></head>
            <body>
            <script>
                localStorage.setItem('token', " . json_encode($token) . ");
                localStorage.setItem('role', 'warga');
                localStorage.setItem('user', " . json_encode([
                    'id' => $warga->warga_id,
                    'nama' => $warga->nama,
                    'email' => $warga->email,
                    'avatar_url' => $warga->avatar_url,
                ]) . ");
                window.location.href = '/beranda-warga';
            </script>
            <p>Menghubungkan ke PedestriRoute...</p>
            </body>
            </html>
        ", 200, ['Content-Type' => 'text/html']);
    }
}