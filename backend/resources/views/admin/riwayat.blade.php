@extends('layouts.app')

@section('title', 'Riwayat Validasi - PedestriRoute Admin')

@section('content')
<div class="px-5 pt-6 pb-24 flex flex-col flex-1">
    @include('partials.admin-header', ['title' => 'Riwayat Validasi', 'subtitle' => 'Memuat...', 'subtitleId' => 'riwayatSubtitle'])

    <!-- Search & Filter Controls -->
    <div class="space-y-2 mb-3">
        <!-- Text Search -->
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <input type="text" id="searchInput" oninput="renderReports()"
                placeholder="Cari kode laporan, nama jalan, pelapor..."
                class="w-full pl-9 pr-8 py-2 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 transition shadow-sm font-medium" />
            <button type="button" id="btnClearSearch" onclick="clearSearchText()" class="hidden absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- 2-Column Grid: Status Dropdown + Date Picker -->
        <div class="grid grid-cols-2 gap-2">
            <!-- Status Dropdown -->
            <div class="relative">
                <select id="selectStatusFilter" onchange="onStatusFilterChange(this.value)"
                    class="w-full pl-3 pr-8 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm appearance-none cursor-pointer transition">
                    <option value="TERVALIDASI,DITOLAK">Semua Status</option>
                    <option value="TERVALIDASI">Valid</option>
                    <option value="DITOLAK">Ditolak</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Date Picker -->
            <div class="relative flex items-center">
                <input type="date" id="dateFilter" onchange="renderReports()"
                    class="w-full px-2.5 py-2 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm transition font-medium" />
                <button type="button" onclick="clearDateFilter()" id="btnClearDate" class="hidden absolute right-2 text-slate-400 hover:text-slate-600" title="Reset tanggal">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    </div>

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

    let allReports = [];
    const urlParams = new URLSearchParams(window.location.search);
    const initialStatusParam = urlParams.get('status');
    let activeFilter = initialStatusParam ? initialStatusParam.toUpperCase() : 'TERVALIDASI,DITOLAK';
    const selectStatusFilter = document.getElementById('selectStatusFilter');
    if (selectStatusFilter && initialStatusParam) {
        selectStatusFilter.value = activeFilter;
    }

    function clearSearchText() {
        const input = document.getElementById('searchInput');
        if (input) input.value = '';
        renderReports();
    }

    function clearDateFilter() {
        const input = document.getElementById('dateFilter');
        if (input) input.value = '';
        renderReports();
    }

    function onStatusFilterChange(val) {
        activeFilter = val;
        loadReports();
    }

    function renderReports() {
        const container = document.getElementById('reportsList');
        const searchInput = document.getElementById('searchInput');
        const dateInput = document.getElementById('dateFilter');
        const btnClearSearch = document.getElementById('btnClearSearch');
        const btnClearDate = document.getElementById('btnClearDate');

        const searchVal = (searchInput?.value || '').trim().toLowerCase();
        const dateVal = dateInput?.value || '';

        if (btnClearSearch) btnClearSearch.classList.toggle('hidden', !searchVal);
        if (btnClearDate) btnClearDate.classList.toggle('hidden', !dateVal);

        let filtered = allReports;

        // Date Filter (matches tanggal_validasi or tanggal_lapor)
        if (dateVal) {
            filtered = filtered.filter(r => {
                const dVal = r.tanggal_validasi ? r.tanggal_validasi.split('T')[0] : '';
                const dLap = r.tanggal_lapor ? r.tanggal_lapor.split('T')[0] : '';
                return dVal === dateVal || dLap === dateVal;
            });
        }

        // Text Search
        if (searchVal) {
            filtered = filtered.filter(r => {
                const kode = (r.kode_laporan || '').toLowerCase();
                const idStr = String(r.laporan_id || '');
                const alamat = (r.alamat || '').toLowerCase();
                const deskripsi = (r.deskripsi || '').toLowerCase();
                const pelapor = (r.pelapor_nama || '').toLowerCase();
                const kat = (r.kategori || '').toLowerCase();
                const alasan = (r.alasan_penolakan || '').toLowerCase();
                return kode.includes(searchVal) ||
                       idStr.includes(searchVal) ||
                       alamat.includes(searchVal) ||
                       deskripsi.includes(searchVal) ||
                       pelapor.includes(searchVal) ||
                       kat.includes(searchVal) ||
                       alasan.includes(searchVal);
            });
        }

        const subtitleEl = document.getElementById('riwayatSubtitle');
        if (subtitleEl) {
            const hasFilter = searchVal || dateVal;
            subtitleEl.innerText = hasFilter
                ? `${filtered.length} dari ${allReports.length} laporan cocok`
                : `${filtered.length} laporan diproses`;
        }

        if (allReports.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12 bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <span class="text-3xl mb-2 block">📋</span>
                    <h3 class="text-sm font-bold text-slate-800">Belum ada riwayat</h3>
                    <p class="text-xs text-slate-500 mt-1">Laporan yang telah diproses akan muncul di sini.</p>
                </div>`;
            return;
        }

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="text-center py-10 bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <span class="text-2xl mb-1.5 block">🔍</span>
                    <h3 class="text-sm font-bold text-slate-800">Tidak Ada Hasil</h3>
                    <p class="text-xs text-slate-500 mt-1">Tidak ada laporan yang cocok dengan filter atau pencarian.</p>
                    <button type="button" onclick="clearSearchText(); clearDateFilter();" class="mt-3 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                        Reset Pencarian
                    </button>
                </div>`;
            return;
        }

        container.innerHTML = filtered.map(lap => `
            <button type="button" onclick="openDetail(${lap.laporan_id})"
                class="w-full text-left bg-white rounded-2xl p-4 border border-slate-100 shadow-sm flex flex-col gap-2 hover:border-brand-500/40 transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-bold text-slate-800 tracking-tight tabular-nums">${esc(lap.kode_laporan)}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium text-slate-600 bg-slate-100">${esc(lap.kategori)}</span>
                    </div>
                    ${statusBadge(lap.status)}
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-800 truncate mb-0.5">${esc(lap.alamat || 'Alamat tidak tersedia')}</p>
                    <p class="text-xs text-slate-600 line-clamp-2 leading-relaxed">${esc(lap.deskripsi || '-')}</p>
                </div>
                ${lap.status === 'DITOLAK' && lap.alasan_penolakan ? `
                    <div class="p-2.5 bg-rose-50/80 border border-rose-100 rounded-xl text-xs">
                        <span class="text-[10px] font-bold text-rose-700 uppercase tracking-wider block mb-0.5">Alasan Penolakan:</span>
                        <p class="text-rose-900 leading-relaxed font-medium line-clamp-2">${esc(lap.alasan_penolakan)}</p>
                    </div>
                ` : ''}
                <div class="flex items-center justify-between pt-2 border-t border-slate-50 text-[11px] text-slate-400">
                    <span>Pelapor: <strong class="text-slate-600 font-medium">${esc(lap.pelapor_nama)}</strong></span>
                    <span>${fmtDate(lap.tanggal_validasi || lap.tanggal_lapor)}</span>
                </div>
            </button>`).join('');
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

            const json = await res.json();
            allReports = json.data || [];
            allReports.sort((a, b) => new Date(b.tanggal_validasi || b.tanggal_lapor || 0) - new Date(a.tanggal_validasi || a.tanggal_lapor || 0));

            renderReports();
        } catch (e) {
            showToast('Gagal terhubung ke server', 'error');
        }
    }

    loadReports();
</script>
@endpush