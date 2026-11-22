@php
    $navInactive = 'flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600 transition';
    $navActive = 'flex flex-col items-center gap-1 text-brand-600 font-bold';
@endphp

<nav aria-label="Navigasi utama warga" class="fixed bottom-0 max-w-md w-full bg-white border-t border-slate-200 py-2 px-6 flex items-center justify-between z-40">
    <a href="{{ route('warga.beranda') }}" @if(request()->routeIs('warga.beranda')) aria-current="page" @endif
        class="{{ request()->routeIs('warga.beranda') ? $navActive : $navInactive }}">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 2L2 12h3v8h6v-6h2v6h6v-8h3L12 2z"/>
        </svg>
        <span class="text-[10px] tracking-tight">Peta</span>
    </a>
    <a href="{{ route('warga.rute') }}" @if(request()->routeIs('warga.rute')) aria-current="page" @endif
        class="{{ request()->routeIs('warga.rute') ? $navActive : $navInactive }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
        <span class="text-[10px] tracking-tight">Rute</span>
    </a>
    <a href="{{ route('warga.lapor') }}" class="flex flex-col items-center gap-1 text-slate-700 hover:text-brand-600 transition">
        <span class="w-10 h-10 -mt-5 bg-brand-600 hover:bg-brand-700 rounded-full flex items-center justify-center text-white shadow-md shadow-teal-900/20 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
        </span>
        <span class="text-[10px] tracking-tight font-bold mt-0.5">Lapor</span>
    </a>
    <a href="{{ route('warga.riwayat') }}" @if(request()->routeIs('warga.riwayat')) aria-current="page" @endif
        class="{{ request()->routeIs('warga.riwayat') ? $navActive : $navInactive }}">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-[10px] tracking-tight">Riwayat</span>
    </a>
    <details data-warga-nav-profile class="relative">
        <summary class="list-none cursor-pointer {{ $navInactive }}" aria-label="Menu profil warga">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span class="text-[10px] tracking-tight">Profil</span>
        </summary>
        <div class="absolute right-0 bottom-full mb-4 w-52 bg-white rounded-2xl shadow-xl border border-slate-200 p-3 text-left">
            <p data-warga-nav-name class="text-xs font-bold text-slate-900 truncate">Warga</p>
            <p data-warga-nav-email class="text-[11px] text-slate-500 truncate mt-1"></p>
            <a href="{{ route('notifikasi.index') }}" data-notification-link class="mt-3 flex items-center justify-between text-xs font-semibold text-slate-700 hover:text-brand-600">
                <span>Notifikasi</span>
                <span data-notification-count aria-hidden="true" class="hidden min-w-[16px] h-4 px-1 rounded-full bg-rose-600 text-white text-[9px] font-bold flex items-center justify-center"></span>
            </a>
            <button type="button" data-warga-nav-logout class="w-full mt-3 rounded-xl bg-rose-50 text-rose-700 py-2 text-xs font-bold">Keluar</button>
        </div>
    </details>
</nav>

@once
@push('scripts')
<script>
(() => {
    const menu = document.querySelector('[data-warga-nav-profile]');
    if (!menu) return;
    function updateProfile() {
        try {
            const user = JSON.parse(localStorage.getItem('user') || '{}') || {};
            menu.querySelector('[data-warga-nav-name]').textContent = user.nama || 'Warga';
            menu.querySelector('[data-warga-nav-email]').textContent = user.email || '';
        } catch (_) { /* Keep the default profile label if cached data is invalid. */ }
    }
    updateProfile();
    menu.addEventListener('toggle', () => { if (menu.open) updateProfile(); });
    document.addEventListener('click', event => { if (!menu.contains(event.target)) menu.open = false; });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            menu.querySelector('summary').focus();
        }
    });
    menu.querySelector('[data-warga-nav-logout]').addEventListener('click', async event => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
            const token = localStorage.getItem('token');
            if (token) {
                const response = await fetch('/api/v1/auth/logout', {
                    method: 'POST', headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
                });
                if (!response.ok && response.status !== 401) throw new Error('Logout gagal');
            }
            ['token', 'role', 'user'].forEach(key => localStorage.removeItem(key));
            window.location.href = '/login';
        } catch (_) {
            showToast('Gagal keluar. Silakan coba lagi.', 'error');
            button.disabled = false;
        }
    });
})();
</script>
@endpush
@endonce
