@extends('layouts.app')

@section('title', 'Riwayat Laporan - PedestriRoute')

@section('content')
<div class="px-5 pt-6 pb-20 flex flex-col flex-1">
    <div class="flex items-center gap-2.5 mb-4">
        <a href="/beranda-warga" class="w-8 h-8 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 transition shadow-sm" aria-label="Kembali">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" /></svg>
        </a>
        <div>
            <h1 class="text-base font-extrabold text-slate-900 tracking-tight">Riwayat Laporan</h1>
            <p id="riwayatSubtitle" class="text-xs text-slate-500 font-medium">Memuat...</p>
        </div>
    </div>

    <div id="filterChips" class="flex items-center gap-1.5 mb-3 overflow-x-auto scrollbar-none"></div>
    <div id="reportsList" class="space-y-3"></div>
</div>
<!-- Navigasi Warga: konsisten dengan Beranda; Riwayat aktif. -->
<div class="fixed bottom-0 max-w-md w-full bg-white border-t border-slate-200 py-2 px-6 flex items-center justify-between z-40">
    <a href="/beranda-warga" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600 transition">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2L2 12h3v8h6v-6h2v6h6v-8h3L12 2z"/>
        </svg>
        <span class="text-[10px] tracking-tight">Peta</span>
    </a>
    <a href="javascript:void(0)" onclick="showToast('Rute Pejalan Kaki aktif pada Sprint 3', 'info')" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600 transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
        <span class="text-[10px] tracking-tight">Rute</span>
    </a>
    <a href="/lapor-kondisi" class="flex flex-col items-center gap-1 text-slate-700 hover:text-brand-600 transition">
        <div class="w-10 h-10 -mt-5 bg-brand-600 hover:bg-brand-700 rounded-full flex items-center justify-center text-white shadow-md shadow-teal-900/20 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
        </div>
        <span class="text-[10px] tracking-tight font-bold mt-0.5">Lapor</span>
    </a>
    <a href="/riwayat-laporan" aria-current="page" class="flex flex-col items-center gap-1 text-brand-600 font-bold">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-[10px] tracking-tight">Riwayat</span>
    </a>
    <details id="historyProfileMenu" class="relative">
        <summary class="list-none cursor-pointer flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600 transition" aria-label="Menu profil warga">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
            <span class="text-[10px] tracking-tight">Profil</span>
        </summary>
        <div class="absolute right-0 bottom-full mb-4 w-52 bg-white rounded-2xl shadow-xl border border-slate-200 p-3 text-left">
            <p id="historyProfileName" class="text-xs font-bold text-slate-900 truncate">Warga</p>
            <p id="historyProfileEmail" class="text-[11px] text-slate-500 truncate mt-1"></p>
            <button type="button" id="historyLogout" class="w-full mt-3 rounded-xl bg-rose-50 text-rose-700 py-2 text-xs font-bold">Keluar</button>
        </div>
    </details>
</div>
@include('partials.riwayat-shared')
@endsection

@push('scripts')
<script>
    const token = localStorage.getItem('token');
    if (!token) window.location.href = '/login';

    // Isi menu akun dengan textContent agar data profil tidak menjadi HTML.
    try {
        const profile = JSON.parse(localStorage.getItem('user') || '{}') || {};
        document.getElementById('historyProfileName').textContent = profile.nama || 'Warga';
        document.getElementById('historyProfileEmail').textContent = profile.email || '';
    } catch (_) { /* Tetap tampilkan nama bawaan jika cache profil tidak valid. */ }

    const historyProfileMenu = document.getElementById('historyProfileMenu');
    document.addEventListener('click', event => {
        if (!historyProfileMenu.contains(event.target)) historyProfileMenu.open = false;
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && historyProfileMenu.open) {
            historyProfileMenu.open = false;
            historyProfileMenu.querySelector('summary').focus();
        }
    });
    document.getElementById('historyLogout').addEventListener('click', async event => {
        const button = event.currentTarget;
        button.disabled = true;
        try {
            const res = await fetch('/api/v1/auth/logout', {
                method: 'POST',
                headers: {'Authorization': 'Bearer ' + token, 'Accept': 'application/json'}
            });
            if (!res.ok && res.status !== 401) throw new Error('Logout gagal');
            ['token', 'role', 'user'].forEach(key => localStorage.removeItem(key));
            window.location.href = '/login';
        } catch (_) {
            showToast('Gagal keluar. Silakan coba lagi.', 'error');
            button.disabled = false;
        }
    });

    const FILTERS = [
        { key: '',                          label: 'Semua' },
        { key: 'MENUNGGU_VALIDASI_ADMIN,DIPROSES', label: 'Menunggu' },
        { key: 'TERVALIDASI',               label: 'Valid' },
        { key: 'DITOLAK',                   label: 'Ditolak' },
    ];
    let activeFilter = '';

    function renderChips() {
        document.getElementById('filterChips').innerHTML = FILTERS.map(f => `
            <button type="button" data-key="${f.key}"
                class="px-3 py-1.5 rounded-full text-xs font-bold transition whitespace-nowrap
                ${f.key === activeFilter ? 'bg-brand-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}">
                ${f.label}
            </button>`).join('');
        document.querySelectorAll('#filterChips button').forEach(b =>
            b.addEventListener('click', () => { activeFilter = b.dataset.key; renderChips(); loadReports(); }));
    }

    async function loadReports() {
        const container = document.getElementById('reportsList');
        container.innerHTML = '<p class="text-center py-10 text-slate-400 text-xs">Memuat laporan...</p>';
        try {
            const qs = activeFilter ? `?status=${encodeURIComponent(activeFilter)}` : '';
            const res = await fetch('/api/v1/reports/my-reports' + qs, {
                headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
            });
            if (res.status === 401 || res.status === 403) { window.location.href = '/login'; return; }
            if (!res.ok) { showToast('Gagal memuat riwayat laporan', 'error'); return; }

            const reports = (await res.json()).data;
            document.getElementById('riwayatSubtitle').innerText = `${reports.length} laporan`;

            if (reports.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12 bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                        <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7m16 0v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-5m16 0h-2.586a1 1 0 0 0-.707.293l-2.414 2.414a1 1 0 0 1-.707.293h-3.172a1 1 0 0 1-.707-.293l-2.414-2.414A1 1 0 0 0 6.586 13H4"/>
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-800">Belum ada laporan</h3>
                        <p class="text-xs text-slate-500 mt-1">Laporan yang kamu kirim akan muncul di sini.</p>
                    </div>`;
                return;
            }

            container.innerHTML = reports.map(lap => `
                <button type="button" onclick="openDetail(${lap.laporan_id})"
                    class="w-full text-left bg-white rounded-2xl p-4 border border-slate-100 shadow-sm flex flex-col gap-2 hover:border-brand-500/40 transition">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-brand-700 bg-brand-50 border border-brand-200/60 tracking-tight tabular-nums">${esc(lap.kode_laporan)}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium text-slate-600 bg-slate-100">${esc(lap.kategori)}</span>
                        </div>
                        ${statusBadge(lap.status)}
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-800 truncate mb-0.5">${esc(lap.alamat || 'Alamat tidak tersedia')}</p>
                        <p class="text-xs text-slate-600 line-clamp-2 leading-relaxed">${esc(lap.deskripsi || '-')}</p>
                    </div>
                    <div class="pt-2 border-t border-slate-50 flex items-center justify-between text-[11px]">
                        <span class="text-slate-400">${fmtDate(lap.tanggal_lapor)}</span>
                        ${lap.status === 'DIPROSES' ? `
                            <span class="text-sky-600 font-bold flex items-center gap-1 animate-pulse bg-sky-50 px-2 py-0.5 rounded-full border border-sky-100">
                                <i class="fa-solid fa-spinner fa-spin text-[9px]"></i> Analisis AI
                            </span>
                        ` : (lap.status === 'TERVALIDASI' && lap.confidence_score ? `
                            <span class="text-emerald-700 font-bold flex items-center gap-1 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">
                                <i class="fa-solid fa-check text-[9px]"></i> AI Valid (${Math.round(lap.confidence_score * 100)}%)
                            </span>
                        ` : '')}
                    </div>
                </button>`).join('');
        } catch (e) {
            showToast('Gagal terhubung ke server', 'error');
        }
    }

    renderChips();
    loadReports();
</script>
@endpush
