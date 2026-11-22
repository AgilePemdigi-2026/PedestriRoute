@php
    $adminNav = [
        ['href' => '/admin/validasi', 'label' => 'Validasi & Perbaikan', 'patterns' => ['admin', 'admin/validasi*', 'admin/status-laporan*', 'admin/ditolak*'],
         'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['href' => '/admin/riwayat', 'label' => 'Riwayat', 'patterns' => ['admin/riwayat*'],
         'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['href' => '/admin/statistik', 'label' => 'Statistik', 'patterns' => ['admin/statistik*'],
         'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
    ];
@endphp

<!-- Sidebar navigasi admin (desktop). Bottom nav tetap dipakai di mobile. -->
<aside class="hidden lg:flex lg:flex-col lg:w-64 lg:shrink-0 bg-brand-800 text-white min-h-screen sticky top-0 h-screen">
    <div class="px-5 py-6 flex items-center gap-3 border-b border-white/10">
        <span class="w-10 h-10 rounded-2xl bg-white/10 flex items-center justify-center text-lg">
            <i class="fa-solid fa-person-walking"></i>
        </span>
        <div class="min-w-0">
            <p class="text-sm font-extrabold leading-tight truncate">PedestriRoute</p>
            <p class="text-[10px] font-semibold uppercase tracking-wider text-white/60">Panel Admin</p>
        </div>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto" aria-label="Navigasi admin">
        @foreach ($adminNav as $tab)
            @php $active = request()->is(...$tab['patterns']); @endphp
            <a href="{{ $tab['href'] }}" @if($active) aria-current="page" @endif
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition {{ $active ? 'bg-white text-brand-700 shadow-sm' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}" />
                </svg>
                <span class="truncate">{{ $tab['label'] }}</span>
            </a>
        @endforeach

        <a href="/notifikasi"
           data-notification-link
           class="relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-white/70 hover:bg-white/10 hover:text-white transition">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span class="flex-1 truncate">Notifikasi</span>
            <span data-notification-count
                  aria-hidden="true"
                  class="hidden min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white text-[9px] font-bold items-center justify-center">
            </span>
        </a>
    </nav>

    <div class="px-5 py-4 border-t border-white/10">
        <p class="text-[10px] font-semibold uppercase tracking-wider text-white/50">Masuk sebagai</p>
        <p class="text-xs font-bold">Administrator</p>
    </div>
</aside>
