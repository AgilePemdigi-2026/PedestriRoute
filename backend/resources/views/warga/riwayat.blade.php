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
@include('partials.riwayat-shared')
@endsection

@push('scripts')
<script>
    const token = localStorage.getItem('token');
    if (!token) window.location.href = '/login';

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
