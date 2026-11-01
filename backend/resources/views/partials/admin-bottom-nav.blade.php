<!-- Bottom Navigation for Admin -->
<div class="fixed bottom-0 max-w-md w-full bg-white border-t border-slate-200 py-2 px-8 flex items-center justify-around z-40">
    <!-- Tab 1: Validasi (Admin) -->
    <a href="/admin/validasi" class="flex flex-col items-center gap-1 transition {{ request()->is('admin/validasi*') || request()->is('admin') ? 'text-brand-600 font-bold' : 'text-slate-400 hover:text-slate-600' }}">
        <div class="w-6 h-6 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <span class="text-[10px] tracking-tight">Validasi</span>
    </a>

    <!-- Tab 2: Status Perbaikan (F9) -->
    <a href="/admin/status-laporan" class="flex flex-col items-center gap-1 transition {{ request()->is('admin/status-laporan*') ? 'text-brand-600 font-bold' : 'text-slate-400 hover:text-slate-600' }}">
        <div class="w-6 h-6 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
        </div>
        <span class="text-[10px] tracking-tight">Perbaikan</span>
    </a>

    <!-- Tab 3: Riwayat -->
    <a href="/admin/riwayat" class="flex flex-col items-center gap-1 transition {{ request()->is('admin/riwayat*') ? 'text-brand-600 font-bold' : 'text-slate-400 hover:text-slate-600' }}">
        <div class="w-6 h-6 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <span class="text-[10px] tracking-tight">Riwayat</span>
    </a>

    <!-- Tab 3: Statistik -->
    <a href="/admin/statistik" class="flex flex-col items-center gap-1 transition {{ request()->is('admin/statistik*') ? 'text-brand-600 font-bold' : 'text-slate-400 hover:text-slate-600' }}">
        <div class="w-6 h-6 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
        </div>
        <span class="text-[10px] tracking-tight">Statistik</span>
    </a>

    <!-- Tab 4: Notifikasi (Stub Sprint 1, aktif penuh di Sprint 3) -->
    <a href="javascript:void(0)" onclick="showToast('Pusat Notifikasi lengkap aktif pada Sprint 3', 'info')" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600 transition">
        <div class="w-6 h-6 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
        </div>
        <span class="text-[10px] tracking-tight">Notifikasi</span>
    </a>
</div>