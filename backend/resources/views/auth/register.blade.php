@extends('layouts.app')

@section('title', 'Daftar Akun Baru - PedestriRoute')

@section('content')
<div class="px-6 pt-6 pb-8 flex flex-col flex-1">
    <!-- Top Bar with Back button -->
    <div class="flex items-center w-full mb-4">
        <a href="/login" class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-slate-200 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
    </div>

    <!-- Header Section -->
    <div class="flex flex-col items-center text-center mb-6">
        <div class="w-14 h-14 bg-brand-600 rounded-2xl flex items-center justify-center shadow-lg shadow-teal-900/20 mb-3">
            <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M13.5 5.5c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zM9.8 8.9L7 23h2.1l1.8-8 2.1 2v6h2v-7.5l-2.1-2 .6-3C14.8 12 16.8 13 19 13v-2c-1.9 0-3.5-1-4.3-2.4l-1-1.6c-.4-.6-1-1-1.7-1-.3 0-.5.1-.8.1L6 8.3V13h2V9.6l1.8-.7"/>
            </svg>
        </div>
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Daftar Akun Baru</h1>
        <p class="text-xs text-slate-500 font-medium mt-1 max-w-xs leading-relaxed">
            Bergabung bersama komunitas pejalan kaki untuk kota yang lebih ramah & inklusif.
        </p>
    </div>

    <!-- Registration Form -->
    <form id="registerForm" class="space-y-4">
        <!-- Nama Lengkap -->
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-semibold text-slate-700">Nama Lengkap</label>
                <span class="text-[10px] font-bold text-teal-700">Wajib</span>
            </div>
            <div class="relative flex items-center">
                <span class="absolute left-3.5 text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </span>
                <input type="text" id="nama" name="nama" placeholder="cth. Budi Pratama"
                    class="w-full pl-11 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition" />
            </div>
        </div>

        <!-- Email -->
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-semibold text-slate-700">Email</label>
                <span class="text-[10px] font-bold text-teal-700">Wajib</span>
            </div>
            <div class="relative flex items-center">
                <span class="absolute left-3.5 text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </span>
                <input type="email" id="email" name="email" placeholder="nama@email.com"
                    class="w-full pl-11 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition" />
            </div>
        </div>

        <!-- Nomor Telepon -->
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-semibold text-slate-700">Nomor Telepon</label>
                <span class="text-[10px] font-bold text-teal-700">Wajib</span>
            </div>
            <div class="relative flex items-center">
                <span class="absolute left-3.5 text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                </span>
                <input type="tel" id="no_hp" name="no_hp" placeholder="081234567890"
                    class="w-full pl-11 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition" />
            </div>
        </div>

        <!-- Kata Sandi -->
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="block text-xs font-semibold text-slate-700">Kata Sandi</label>
                <span class="text-[10px] text-slate-500">Min. 8 karakter</span>
            </div>
            <div class="relative flex items-center">
                <span class="absolute left-3.5 text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </span>
                <input type="password" id="password" name="password" placeholder="Kombinasi huruf & angka"
                    class="w-full pl-11 pr-11 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition" />
                <button type="button" id="togglePassword" class="absolute right-3.5 text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Konfirmasi Kata Sandi -->
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">Konfirmasi Kata Sandi</label>
            <div class="relative flex items-center">
                <span class="absolute left-3.5 text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Ulangi kata sandi"
                    class="w-full pl-11 pr-11 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition" />
                <button type="button" id="togglePasswordConfirm" class="absolute right-3.5 text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Terms Agreement Checkbox -->
        <div class="flex items-start gap-2.5 pt-1">
            <input type="checkbox" id="terms" required checked
                class="w-4 h-4 mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
            <label for="terms" class="text-xs text-slate-600 leading-snug">
                Saya menyetujui <span class="text-brand-600 font-semibold">Syarat & Ketentuan</span> serta <span class="text-brand-600 font-semibold">Kebijakan Privasi</span> PedestriRoute.
            </label>
        </div>

        <!-- Submit Button -->
        <button type="submit" id="submitRegisterBtn"
            class="w-full py-3.5 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-xl flex items-center justify-center gap-2 shadow-lg shadow-teal-900/10 transition mt-2">
            <span>Daftar Sekarang</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
        </button>
    </form>

    <!-- Divider -->
    <div class="relative my-4">
        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-200"></div></div>
        <div class="relative flex justify-center text-xs"><span class="px-3 bg-white text-slate-400">atau daftar dengan</span></div>
    </div>

    <!-- Google Button -->
    <a href="/api/v1/auth/google/redirect"
        class="w-full py-2.5 px-4 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-medium rounded-xl flex items-center justify-center gap-3 transition shadow-sm text-sm">
        <svg class="w-5 h-5" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
        </svg>
        <span>Lanjutkan dengan Google</span>
    </a>

    <!-- Facebook Login Button -->
        <a href="/api/v1/auth/facebook/redirect"
            class="w-full mt-2.5 py-3 px-4 bg-[#1877F2] hover:bg-[#166FE5] text-white font-medium rounded-xl flex items-center justify-center gap-3 transition shadow-sm text-sm">
            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
            </svg>
            <span>Lanjutkan dengan Facebook</span>
        </a>

    <!-- Bottom Link -->
    <div class="mt-6 text-center text-sm text-slate-500 font-medium">
        Sudah punya akun?
        <a href="/login" class="text-brand-600 hover:text-brand-700 font-bold ml-1">Masuk di sini</a>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // --- TAMBAHKAN SCRIPT AUTO-FILL DI SINI ---
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const paramNama = urlParams.get('nama');
        const paramEmail = urlParams.get('email');
        const paramInfo = urlParams.get('info');

        // Isi form otomatis jika data tersedia di URL
        if (paramNama) {
            document.getElementById('nama').value = paramNama;
        }
        
        if (paramEmail) {
            const emailInput = document.getElementById('email');
            emailInput.value = paramEmail;
            emailInput.readOnly = true; // Opsional: Kunci email agar tidak bisa diubah karena dari Sosmed
            emailInput.classList.add('bg-slate-100', 'text-slate-500'); // Efek visual terkunci
        }

        // Tampilkan notifikasi jika ini dari proses Social Login
        if (paramInfo === 'social_register') {
            // Gunakan fungsi showToast kamu jika sudah ada, atau alert sementara
            if (typeof showToast === 'function') {
                showToast('Akun belum terdaftar. Silakan lengkapi nomor telepon dan kata sandi.', 'info');
            }
        }
    });
    // --- AKHIR SCRIPT AUTO-FILL ---

    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    togglePassword.addEventListener('click', () => {
        const isPassword = passwordInput.getAttribute('type') === 'password';
        passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
    });

    const togglePasswordConfirm = document.getElementById('togglePasswordConfirm');
    const passwordConfirmInput = document.getElementById('password_confirmation');
    togglePasswordConfirm.addEventListener('click', () => {
        const isPassword = passwordConfirmInput.getAttribute('type') === 'password';
        passwordConfirmInput.setAttribute('type', isPassword ? 'text' : 'password');
    });

    const registerForm = document.getElementById('registerForm');
    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // ... (Kode submit fetch/api kamu tetap sama tidak perlu diubah) ...
        const nama = document.getElementById('nama').value;
        const email = document.getElementById('email').value;
        const no_hp = document.getElementById('no_hp').value;
        const password = document.getElementById('password').value;
        const password_confirmation = document.getElementById('password_confirmation').value;

        try {
            const response = await fetch('/api/v1/auth/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ nama, email, no_hp, password, password_confirmation })
            });

            const resData = await response.json();

            if (!response.ok) {
                const message = resData.error ? resData.error.message : 'Registrasi gagal. Periksa kembali form Anda.';
                showToast(message, 'error');
                return;
            }

            if (resData.data?.otp_required && resData.data?.challenge_id) {
                sessionStorage.setItem('register_otp_challenge', resData.data.challenge_id);
                showToast(resData.message || 'Kode verifikasi telah dikirim ke email Anda.', 'success');
                setTimeout(() => {
                    window.location.href = '/verify-otp';
                }, 600);
                return;
            }

            showToast('Registrasi Berhasil', 'success');
            setTimeout(() => {
                window.location.href = '/login';
            }, 800);
        } catch (err) {
            showToast('Terjadi kesalahan koneksi. Silakan coba lagi.', 'error');
        }
    });
</script>
@endpush
