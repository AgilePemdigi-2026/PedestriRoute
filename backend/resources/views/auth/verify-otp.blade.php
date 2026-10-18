@extends('layouts.app')
@section('title', 'Verifikasi OTP - PedestriRoute')
@section('content')
<div class="px-6 pt-12 pb-8 flex flex-col items-center flex-1 justify-center">
    <div class="w-16 h-16 bg-brand-600 rounded-2xl flex items-center justify-center shadow-lg shadow-teal-900/20 mb-4">
        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4zM4 7l8 6 8-6"/></svg>
    </div>
    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight text-center">PedestriRoute</h1>
    <p class="text-sm text-slate-500 font-medium mt-1 mb-8 text-center">Verifikasi Email</p>
    <div class="w-full bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
        <p class="text-sm text-slate-600 text-center mb-5">Masukkan kode 6 digit yang dikirim ke email akun Anda.</p>
        <form id="otpForm" class="space-y-4">
            <div><label for="otp" class="block text-xs font-semibold text-slate-700 mb-1.5">Kode OTP</label>
                <input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required placeholder="123456"
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm text-center tracking-widest focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition" />
            </div>
            <button id="verifyBtn" type="submit" class="w-full py-3.5 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-xl shadow-lg shadow-teal-900/10 transition">Verifikasi OTP</button>
        </form>
        <p class="text-xs text-slate-500 text-center mt-4">Kode berlaku selama 5 menit.</p>
        <button id="resendBtn" type="button" disabled class="w-full mt-3 text-sm font-semibold text-brand-600 disabled:text-slate-400">Kirim ulang OTP (60 detik)</button>
    </div>
    <a href="/login" class="mt-8 text-sm text-brand-600 font-bold">Kembali ke Login</a>
</div>
@endsection
@push('scripts')
<script>
(() => {
    const challenge = sessionStorage.getItem('login_otp_challenge');
    if (!challenge) { window.location.replace('/login'); return; }
    const form = document.getElementById('otpForm');
    const verifyBtn = document.getElementById('verifyBtn');
    const resendBtn = document.getElementById('resendBtn');
    const otpInput = document.getElementById('otp');
    otpInput.addEventListener('input', () => { otpInput.value = otpInput.value.replace(/\D/g, '').slice(0, 6); });
    let remaining = 60;
    const timer = setInterval(() => {
        remaining = Math.max(0, remaining - 1);
        resendBtn.disabled = remaining > 0;
        resendBtn.textContent = remaining ? `Kirim ulang OTP (${remaining} detik)` : 'Kirim ulang OTP';
    }, 1000);
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (otpInput.value.length !== 6) { showToast('Masukkan 6 digit OTP.', 'error'); return; }
        verifyBtn.disabled = true;
        verifyBtn.textContent = 'Memverifikasi...';
        try {
            const response = await fetch('/api/v1/auth/otp/verify', {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'}, body:JSON.stringify({challenge_id:challenge,otp:otpInput.value})});
            const result = await response.json();
            if (!response.ok) {
                showToast(result.error?.message || result.message || 'OTP tidak valid.', 'error');
                if ([410,429].includes(response.status)) { sessionStorage.removeItem('login_otp_challenge'); }
                return;
            }
            localStorage.setItem('token', result.data.token);
            localStorage.setItem('role', result.data.role);
            localStorage.setItem('user', JSON.stringify(result.data.user));
            sessionStorage.removeItem('login_otp_challenge');
            showToast('Login berhasil!', 'success');
            window.location.href = result.data.redirect;
        } catch (error) { showToast('Terjadi kesalahan koneksi.', 'error'); }
        finally { verifyBtn.disabled = false; verifyBtn.textContent = 'Verifikasi OTP'; }
    });
    resendBtn.addEventListener('click', async () => {
        resendBtn.disabled = true;
        try {
            const response = await fetch('/api/v1/auth/otp/resend', {method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({challenge_id:challenge})});
            const result = await response.json();
            if (!response.ok) { showToast(result.error?.message || result.message || 'Gagal mengirim ulang.', 'error'); return; }
            remaining = 60;
            showToast('OTP baru telah dikirim.', 'success');
        } catch (error) { showToast('Terjadi kesalahan koneksi.', 'error'); }
        finally { resendBtn.disabled = remaining > 0; }
    });
})();
</script>
@endpush
