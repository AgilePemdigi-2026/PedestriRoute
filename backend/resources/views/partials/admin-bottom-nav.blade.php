@php
    $adminTabs = [
        ['href' => '/admin/validasi', 'label' => 'Validasi & Perbaikan', 'patterns' => ['admin', 'admin/validasi*', 'admin/status-laporan*'],
         'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['href' => '/admin/riwayat', 'label' => 'Riwayat', 'patterns' => ['admin/riwayat*'],
         'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['href' => '/admin/statistik', 'label' => 'Statistik', 'patterns' => ['admin/statistik*'],
         'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
    ];
@endphp

<!-- Bottom Navigation for Admin -->
<div class="fixed bottom-0 max-w-md w-full bg-white border-t border-slate-200 px-2 pt-1.5 pb-2 flex items-stretch justify-around z-40">
    @foreach ($adminTabs as $tab)
        @php $active = request()->is(...$tab['patterns']); @endphp
        <a href="{{ $tab['href'] }}" @if($active) aria-current="page" @endif
           class="relative flex-1 flex flex-col items-center gap-0.5 pt-1.5 pb-0.5 transition {{ $active ? 'text-brand-600 font-bold' : 'text-slate-400 hover:text-slate-600' }}">
            @if($active)
                <span class="absolute -top-1.5 h-0.5 w-8 rounded-full bg-brand-600"></span>
            @endif
            <span class="w-9 h-6 flex items-center justify-center rounded-full transition {{ $active ? 'bg-brand-50' : '' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tab['icon'] }}" />
                </svg>
            </span>
            <span class="text-[10px] tracking-tight">{{ $tab['label'] }}</span>
        </a>
    @endforeach

    <!-- Notifikasi (stub, aktif penuh di Sprint 3) -->
    <a href="javascript:void(0)" onclick="showToast('Pusat Notifikasi lengkap aktif pada Sprint 3', 'info')"
       class="relative flex-1 flex flex-col items-center gap-0.5 pt-1.5 pb-0.5 text-slate-400 hover:text-slate-600 transition">
        <span class="w-9 h-6 flex items-center justify-center rounded-full">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
        </span>
        <span class="text-[10px] tracking-tight">Notifikasi</span>
    </a>
</div>