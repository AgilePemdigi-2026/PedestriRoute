{{-- Header standar semua halaman admin.
     Parameter: title, subtitle, subtitleId (opsional), badgeId (opsional) --}}
<div class="flex items-center justify-between mb-4">
    <div class="min-w-0">
        <h1 class="text-base font-extrabold text-slate-900 tracking-tight">{{ $title }}</h1>
        <p @if(!empty($subtitleId)) id="{{ $subtitleId }}" @endif class="text-xs text-slate-500 font-medium">{{ $subtitle ?? '' }}</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        @if(!empty($badgeId))
            <span id="{{ $badgeId }}" class="hidden">0</span>
        @endif
        <button id="logoutBtn" type="button" class="text-xs font-semibold text-slate-500 hover:text-rose-600 flex items-center gap-1.5 py-1 px-1.5 rounded-lg hover:bg-slate-100 transition" title="Keluar">
            <svg class="w-4 h-4 text-slate-400 hover:text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span>Keluar</span>
        </button>
    </div>
</div>