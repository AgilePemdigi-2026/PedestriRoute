@extends('layouts.app')

@section('title', 'Riwayat Laporan - PedestriRoute')

@section('content')
<div class="px-5 pt-6 pb-20 flex flex-col flex-1">
    <div class="flex items-center gap-3 mb-4">
        <a href="/beranda-warga" class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-slate-200 transition" aria-label="Kembali">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
        </a>
        <div>
            <h1 class="text-base font-extrabold text-slate-900 leading-tight">Riwayat Laporan</h1>
            <p id="riwayatSubtitle" class="text-[10px] text-teal-800 font-semibold tracking-wide">Memuat...</p>
        </div>
    </div>

    <div id="filterChips" class="flex gap-2 mb-4 overflow-x-auto"></div>
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
                class="px-3.5 py-1.5 rounded-full text-xs font-bold border transition whitespace-nowrap
                ${f.key === activeFilter ? 'bg-brand-600 text-white border-brand-600' : 'bg-white text-slate-500 border-slate-200'}">
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
                    <div class="text-center py-12 bg-white rounded-2xl border border-slate-100 p-6">
                        <span class="text-3xl mb-2 block">📭</span>
                        <h3 class="text-sm font-bold text-slate-800">Belum ada laporan</h3>
                        <p class="text-xs text-slate-500 mt-1">Laporan yang kamu kirim akan muncul di sini.</p>
                    </div>`;
                return;
            }

            container.innerHTML = reports.map(lap => `
                <button type="button" onclick="openDetail(${lap.laporan_id})"
                    class="w-full text-left bg-white rounded-2xl p-4 border border-slate-100 shadow-sm flex flex-col gap-2 hover:border-brand-500 transition">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-mono font-bold text-teal-800 bg-teal-50 px-2 py-0.5 rounded-md">${esc(lap.kode_laporan)}</span>
                        ${statusBadge(lap.status)}
                    </div>
                    <p class="text-xs font-bold text-slate-800">${esc(lap.kategori)}</p>
                    <p class="text-xs text-slate-500 truncate">${esc(lap.alamat)}</p>
                    <p class="text-[10px] text-slate-400">${fmtDate(lap.tanggal_lapor)}</p>
                </button>`).join('');
        } catch (e) {
            showToast('Gagal terhubung ke server', 'error');
        }
    }

    renderChips();
    loadReports();
</script>
@endpush
