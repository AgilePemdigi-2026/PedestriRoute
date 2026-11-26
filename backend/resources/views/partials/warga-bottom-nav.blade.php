@php
    $navMobileVisibility = $navMobileVisibility ?? 'flex';
    $navLinkDesktop = 'md:flex-row md:justify-start md:gap-3 md:px-3 md:py-2.5 md:rounded-xl md:text-sm md:font-semibold';
    $navInactive = 'flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600 md:hover:bg-slate-50 transition ' . $navLinkDesktop;
    $navActive = 'flex flex-col items-center gap-1 text-brand-600 font-bold md:bg-brand-50 md:text-brand-700 ' . $navLinkDesktop;
@endphp

<nav aria-label="Navigasi utama warga" class="fixed bottom-0 max-w-md w-full bg-white border-t border-slate-200 py-2 px-6 {{ $navMobileVisibility }} items-center justify-between z-40 md:inset-y-0 md:left-0 md:right-auto md:bottom-auto md:w-60 lg:w-64 md:max-w-none md:flex md:flex-col md:items-stretch md:justify-start md:gap-1 md:border-r md:border-t-0 md:px-3 md:py-5 md:overflow-y-auto">
    <div class="hidden md:flex md:items-center md:gap-2.5 md:px-3 md:pb-5 md:mb-2 md:border-b md:border-slate-100">
        <span class="w-8 h-8 rounded-xl bg-brand-600 text-white flex items-center justify-center font-extrabold text-sm shrink-0">P</span>
        <div class="min-w-0">
            <p class="truncate text-sm font-extrabold text-slate-900">PedestriRoute</p>
            <p class="text-[10px] font-semibold text-slate-500">Panel Warga</p>
        </div>
    </div>
    <a href="{{ route('warga.beranda') }}" @if(request()->routeIs('warga.beranda')) aria-current="page" @endif
        class="{{ request()->routeIs('warga.beranda') ? $navActive : $navInactive }}">
        <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 2L2 12h3v8h6v-6h2v6h6v-8h3L12 2z"/>
        </svg>
        <span class="text-[10px] md:text-sm tracking-tight">Peta</span>
    </a>
    <a href="{{ route('warga.rute') }}" @if(request()->routeIs('warga.rute')) aria-current="page" @endif
        class="{{ request()->routeIs('warga.rute') ? $navActive : $navInactive }}">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
        <span class="text-[10px] md:text-sm tracking-tight">Rute</span>
    </a>
    <a href="{{ route('warga.lapor') }}" @if(request()->routeIs('warga.lapor')) aria-current="page" @endif
        class="flex flex-col items-center gap-1 transition md:flex-row md:justify-start md:gap-3 md:px-3 md:py-2.5 md:rounded-xl md:text-sm md:font-semibold {{ request()->routeIs('warga.lapor') ? 'text-brand-600 font-bold md:bg-brand-50 md:text-brand-700' : 'text-slate-700 hover:text-brand-600 md:hover:bg-slate-50' }}">
        <span class="w-10 h-10 -mt-5 bg-brand-600 hover:bg-brand-700 rounded-full flex items-center justify-center text-white shadow-md shadow-teal-900/20 transition md:mt-0 md:w-7 md:h-7 md:rounded-lg md:shrink-0">
            <svg class="w-5 h-5 md:w-4 md:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
        </span>
        <span class="text-[10px] md:text-sm tracking-tight font-bold md:font-semibold mt-0.5 md:mt-0">Lapor</span>
    </a>
    <a href="{{ route('warga.riwayat') }}" @if(request()->routeIs('warga.riwayat')) aria-current="page" @endif
        class="{{ request()->routeIs('warga.riwayat') ? $navActive : $navInactive }}">
        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-[10px] md:text-sm tracking-tight">Riwayat</span>
    </a>
    <details data-warga-nav-profile class="relative md:mt-auto">
        <summary class="list-none cursor-pointer {{ $navInactive }}" aria-label="Menu profil warga">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span class="text-[10px] md:text-sm tracking-tight">Profil</span>
        </summary>
        <div class="absolute right-0 bottom-full mb-4 w-52 bg-white rounded-2xl shadow-xl border border-slate-200 p-3 text-left md:static md:mt-2 md:mb-0 md:w-full md:rounded-xl md:border md:border-slate-200/80 md:p-3 md:shadow-none md:bg-slate-50">
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
