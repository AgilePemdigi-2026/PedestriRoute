{{-- Header standar semua halaman admin.
     Parameter: title, subtitle, subtitleId (opsional), badgeId (opsional) --}}
<div class="flex items-center justify-between mb-4">
    <div class="min-w-0">
        <h1 class="text-base font-extrabold text-slate-900 tracking-tight">{{ $title }}</h1>
        <p @if(!empty($subtitleId)) id="{{ $subtitleId }}" @endif class="text-xs text-slate-500 font-medium">{{ $subtitle ?? '' }}</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        @if(!empty($badgeId))
            <span id="{{ $badgeId }}" class="px-2.5 py-1 bg-teal-50 text-brand-700 text-xs font-extrabold rounded-full border border-teal-100">0</span>
        @endif
        <button id="logoutBtn" type="button" class="px-3 py-1.5 bg-rose-50 text-rose-600 rounded-xl text-xs font-bold border border-rose-100 hover:bg-rose-100 transition">
            Keluar
        </button>
    </div>
</div>