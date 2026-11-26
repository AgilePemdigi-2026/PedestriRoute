@php
    $adminTabs = [
        ['href' => '/admin/validasi', 'label' => 'Validasi & Perbaikan', 'patterns' => ['admin', 'admin/validasi*', 'admin/status-laporan*', 'admin/ditolak*'],
         'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['href' => '/admin/riwayat', 'label' => 'Riwayat', 'patterns' => ['admin/riwayat*'],
         'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['href' => '/admin/statistik', 'label' => 'Statistik', 'patterns' => ['admin/statistik*'],
         'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
    ];
@endphp

<!-- Bottom Navigation for Admin (Sidebar on Desktop) -->
<nav aria-label="Navigasi utama admin" class="fixed bottom-0 max-w-md w-full bg-white border-t border-slate-200 px-2 pt-1.5 pb-2 flex items-stretch justify-around z-40 md:inset-y-0 md:left-0 md:right-auto md:bottom-auto md:w-60 lg:w-64 md:max-w-none md:flex-col md:items-stretch md:justify-start md:gap-1 md:border-r md:border-t-0 md:px-3 md:py-5 md:overflow-y-auto">
    <div class="hidden md:flex md:items-center md:gap-2.5 md:px-3 md:pb-5 md:mb-2 md:border-b md:border-slate-100">
        <span class="w-8 h-8 rounded-xl bg-brand-600 text-white flex items-center justify-center font-extrabold text-sm shrink-0">P</span>
        <div class="min-w-0">
            <p class="truncate text-sm font-extrabold text-slate-900">PedestriRoute</p>
            <p class="text-[10px] font-semibold text-slate-500">Panel Admin</p>
        </div>
    </div>

    @foreach ($adminTabs as $tab)
        @php $active = request()->is(...$tab['patterns']); @endphp
        <a href="{{ $tab['href'] }}" @if($active) aria-current="page" @endif
           class="relative flex-1 flex flex-col items-center gap-0.5 pt-1.5 pb-0.5 transition md:flex-none md:flex-row md:justify-start md:gap-3 md:px-3 md:py-2.5 md:rounded-xl md:text-sm md:font-semibold {{ $active ? 'text-brand-600 font-bold md:bg-brand-50 md:text-brand-700' : 'text-slate-400 hover:text-slate-600 md:hover:bg-slate-50' }}">
            @if($active)
                <span class="absolute -top-1.5 h-0.5 w-8 rounded-full bg-brand-600 md:hidden"></span>
            @endif
            <span class="w-9 h-6 flex items-center justify-center rounded-full transition md:w-7 md:h-7 md:rounded-lg md:shrink-0 {{ $active ? 'bg-brand-50 md:bg-transparent' : '' }}">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}" />
                </svg>
            </span>
            <span class="text-[10px] md:text-sm tracking-tight truncate">{{ $tab['label'] }}</span>
        </a>
    @endforeach

    <!-- Notifikasi -->
    <a href="/notifikasi"
            data-notification-link
            class="relative flex-1 flex flex-col items-center gap-0.5
                    pt-1.5 pb-0.5 text-slate-400 hover:text-slate-600 md:hover:bg-slate-50 transition
                    md:flex-none md:flex-row md:justify-start md:gap-3 md:px-3 md:py-2.5 md:rounded-xl md:text-sm md:font-semibold md:mt-auto">
        <span class="w-9 h-6 flex items-center justify-center rounded-full md:w-7 md:h-7 md:rounded-lg md:shrink-0">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
        </span>
        <span class="text-[10px] md:text-sm tracking-tight">Notifikasi</span>
        <span data-notification-count
            aria-hidden="true"
            class="hidden absolute top-0 right-2 md:static md:ml-auto min-w-[18px] h-[18px]
                    px-1 rounded-full bg-rose-600 text-white text-[9px]
                    font-bold flex items-center justify-center">
        </span>
    </a>
</nav>