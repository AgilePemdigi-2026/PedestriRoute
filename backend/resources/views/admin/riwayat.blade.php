@extends('layouts.app')

@section('title', 'Riwayat Validasi - PedestriRoute Admin')

@section('content')
<div class="px-5 pt-6 pb-24 flex flex-col flex-1">
    @include('partials.admin-header', ['title' => 'Riwayat Validasi', 'subtitle' => 'Memuat...', 'subtitleId' => 'riwayatSubtitle'])

    <div id="filterChips" class="flex items-center gap-1.5 mb-3 overflow-x-auto scrollbar-none"></div>
    <div id="reportsList" class="space-y-3"></div>
</div>

@include('partials.admin-bottom-nav')
@include('partials.riwayat-shared')
@endsection

@push('scripts')
<script>
    const token = localStorage.getItem('token');
    const role = localStorage.getItem('role');
    if (!token || role !== 'admin') window.location.href = '/login';

    document.getElementById('logoutBtn').addEventListener('click', async () => {
        try { await fetch('/api/v1/auth/logout', { method: 'POST', headers: { 'Authorization': 'Bearer ' + token } }); } catch (e) {}
        localStorage.clear();
        window.location.href = '/login';
    });

    const FILTERS = [
        { key: 'TERVALIDASI,DITOLAK', label: 'Semua' },
        { key: 'TERVALIDASI',         label: 'Valid' },
        { key: 'DITOLAK',             label: 'Ditolak' },
    ];
    let activeFilter = FILTERS[0].key;

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
            const res = await fetch('/api/v1/admin/reports?status=' + encodeURIComponent(activeFilter), {
                headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
            });
            if (res.status === 401 || res.status === 403) { window.location.href = '/login'; return; }
            if (!res.ok) { showToast('Gagal memuat riwayat', 'error'); return; }

            const reports = (await res.json()).data;
            document.getElementById('riwayatSubtitle').innerText = `${reports.length} laporan diproses`;

            if (reports.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12 bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                        <span class="text-3xl mb-2 block">📋</span>
                        <h3 class="text-sm font-bold text-slate-800">Belum ada riwayat</h3>
                        <p class="text-xs text-slate-500 mt-1">Laporan yang telah diproses akan muncul di sini.</p>
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
                    <div class="flex items-center justify-between pt-2 border-t border-slate-50 text-[11px] text-slate-400">
                        <span>Pelapor: <strong class="text-slate-600 font-medium">${esc(lap.pelapor_nama)}</strong></span>
                        <span>${fmtDate(lap.tanggal_validasi || lap.tanggal_lapor)}</span>
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