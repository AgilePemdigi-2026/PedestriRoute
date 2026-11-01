@extends('layouts.app')

@section('title', 'Login - PedestriRoute')

@section('content')
<div class="px-6 pt-12 pb-8 flex flex-col items-center flex-1 justify-center">
    <!-- Brand Icon -->
    <div class="w-16 h-16 bg-brand-600 rounded-2xl flex items-center justify-center shadow-lg shadow-teal-900/20 mb-4">
        <!-- Walking pedestrian icon -->
        <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 24 24">
            <path d="M13.5 5.5c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zM9.8 8.9L7 23h2.1l1.8-8 2.1 2v6h2v-7.5l-2.1-2 .6-3C14.8 12 16.8 13 19 13v-2c-1.9 0-3.5-1-4.3-2.4l-1-1.6c-.4-.6-1-1-1.7-1-.3 0-.5.1-.8.1L6 8.3V13h2V9.6l1.8-.7"/>
        </svg>
    </div>

    <!-- Title & Tagline -->
    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight text-center">PedestriRoute</h1>
    <p class="text-sm text-slate-500 font-medium mt-1 mb-8 text-center">Navigasi Pejalan Kaki Nyaman & Aman</p>

    <!-- Main Card -->
    <div class="w-full bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
        
        @if(request()->query('error') == 'account_not_registered')
            <div style="background-color: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; padding: 12px; border-radius: 8px; margin-bottom: 16px; text-align: center; font-size: 14px;">
                <strong>Akses Ditolak:</strong> Akun belum terdaftar. Silakan lakukan registrasi terlebih dahulu.
            </div>
        @elseif(request()->query('error') == 'facebook_failed' || request()->query('error') == 'google_failed')
            <div style="background-color: #fee2e2; border: 1px solid #ef4444; color: #b91c1c; padding: 12px; border-radius: 8px; margin-bottom: 16px; text-align: center; font-size: 14px;">
                <strong>Terjadi Kesalahan:</strong> Gagal terhubung ke layanan otentikasi. Silakan coba lagi.
            </div>
        @endif

        <form id="loginForm" class="space-y-4">

    <!-- Bottom Link -->
    <div class="mt-8 text-center text-sm text-slate-500 font-medium">
        Belum punya akun?
        <a href="/register" class="text-brand-600 hover:text-brand-700 font-bold ml-1">Daftar</a>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Check URL parameters for OAuth errors
    const urlParams = new URLSearchParams(window.location.search);
    const oauthError = urlParams.get('error');
    if (oauthError) {
        if (oauthError === 'account_not_registered') {
            showToast('Akun belum terdaftar. Silakan lakukan registrasi.', 'error');
        } else if (oauthError === 'facebook_failed') {
            showToast('Login dengan Facebook dibatalkan atau gagal.', 'error');
        } else if (oauthError === 'google_failed') {
            showToast('Login dengan Google dibatalkan atau gagal.', 'error');
        } else if (oauthError === 'email_reserved_for_admin') {
            showToast('Email ini terdaftar sebagai Admin. Silakan login manual.', 'error');
        } else {
            showToast('Autentikasi gagal. Silakan coba lagi.', 'error');
        }
        // Clean URL parameter without reloading
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    // Password visibility toggle
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    togglePassword.addEventListener('click', () => {
        const isPassword = passwordInput.getAttribute('type') === 'password';
        passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
    });

    // Form submit handler
    const loginForm = document.getElementById('loginForm');
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const identifier = document.getElementById('identifier').value.trim();
        const password = document.getElementById('password').value.trim();

        try {
            const response = await fetch('/api/v1/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ identifier, password })
            });

            const resData = await response.json();

            if (!response.ok) {
                const message = resData.error ? resData.error.message : 'Login gagal. Periksa kembali data Anda.';
                showToast(message, 'error');
                return;
            }

            if (resData.data?.token) {
                localStorage.setItem('token', resData.data.token);
                localStorage.setItem('role', resData.data.role);
                localStorage.setItem('user', JSON.stringify(resData.data.user));
                showToast('Login berhasil!', 'success');
                setTimeout(() => {
                    window.location.href = resData.data.redirect || '/beranda-warga';
                }, 400);
                return;
            }
            showToast('Respons login tidak sesuai. Silakan coba lagi.', 'error');
        } catch (err) {
            showToast('Terjadi kesalahan koneksi. Silakan coba lagi.', 'error');
        }
    });
</script>
@endpush
