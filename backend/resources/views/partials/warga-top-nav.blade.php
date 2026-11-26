@php
    $topNavLinks = [
        ['route' => 'warga.beranda', 'label' => 'Peta', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['route' => 'warga.rute', 'label' => 'Rute', 'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
        ['route' => 'warga.riwayat', 'label' => 'Riwayat', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
    $topNavActive = 'bg-brand-50 text-brand-700';
    $topNavInactive = 'text-slate-500 hover:text-slate-900 hover:bg-slate-100';
@endphp

<header class="hidden md:flex fixed top-0 inset-x-0 h-16 z-40 items-center gap-6 bg-white/95 backdrop-blur border-b border-slate-200 px-6 lg:px-8">
    <a href="{{ route('warga.beranda') }}" class="flex items-center gap-2.5 shrink-0">
        <span class="w-8 h-8 rounded-xl bg-brand-600 text-white flex items-center justify-center font-extrabold text-sm">P</span>
        <span class="font-extrabold text-slate-900 tracking-tight">PedestriRoute</span>
    </a>

    <nav aria-label="Navigasi utama warga" class="flex items-center gap-1">
        @foreach($topNavLinks as $link)
            @php $isActive = request()->routeIs($link['route']); @endphp
            <a href="{{ route($link['route']) }}" @if($isActive) aria-current="page" @endif
                class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-semibold transition {{ $isActive ? $topNavActive : $topNavInactive }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}" />
                </svg>
                <span>{{ $link['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="ml-auto flex items-center gap-2">
        <a href="{{ route('warga.lapor') }}"
            class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-bold shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>Lapor</span>
        </a>

        <a href="{{ route('notifikasi.index') }}" data-notification-link
            class="relative w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition"
            aria-label="Notifikasi">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span data-notification-count aria-hidden="true"
                class="hidden absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white text-[9px] font-bold flex items-center justify-center"></span>
        </a>

        <details data-warga-top-profile class="relative">
            <summary class="list-none cursor-pointer flex items-center gap-2.5 pl-1.5 pr-2.5 py-1 rounded-xl hover:bg-slate-100 transition" aria-label="Menu profil warga">
                <span class="w-8 h-8 rounded-xl overflow-hidden bg-teal-800 text-white flex items-center justify-center font-bold text-xs uppercase shrink-0">
                    <img data-warga-top-avatar src="" alt="" class="w-full h-full object-cover hidden">
                    <span data-warga-top-initial>W</span>
                </span>
                <span class="hidden lg:block max-w-[140px] truncate text-sm font-semibold text-slate-800" data-warga-top-name>Warga</span>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </summary>
            <div class="absolute right-0 top-full mt-2 w-56 bg-white rounded-2xl shadow-xl border border-slate-100 py-2">
                <div class="px-4 py-2 border-b border-slate-100">
                    <p class="text-xs font-bold text-slate-900 truncate" data-warga-top-name>Warga</p>
                    <p class="text-[11px] text-slate-500 truncate mt-0.5" data-warga-top-email></p>
                </div>
                <button type="button" data-warga-top-logout
                    class="w-full text-left px-4 py-2 mt-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 flex items-center gap-2 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Keluar
                </button>
            </div>
        </details>
    </div>
</header>

@once
@push('scripts')
<script>
(() => {
    const menu = document.querySelector('[data-warga-top-profile]');
    if (!menu) return;
    const avatar = menu.querySelector('[data-warga-top-avatar]');
    const initial = menu.querySelector('[data-warga-top-initial]');

    function showInitial() {
        avatar.classList.add('hidden');
        initial.classList.remove('hidden');
    }
    avatar.addEventListener('error', showInitial);

    function updateProfile() {
        try {
            const user = JSON.parse(localStorage.getItem('user') || '{}') || {};
            const name = user.nama || 'Warga';
            menu.querySelectorAll('[data-warga-top-name]').forEach(el => { el.textContent = name; });
            menu.querySelector('[data-warga-top-email]').textContent = user.email || '';
            initial.textContent = name.trim().charAt(0).toUpperCase() || 'W';
            if (user.avatar_url) {
                avatar.src = user.avatar_url;
                avatar.classList.remove('hidden');
                initial.classList.add('hidden');
            } else {
                showInitial();
            }
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
    menu.querySelector('[data-warga-top-logout]').addEventListener('click', async event => {
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
