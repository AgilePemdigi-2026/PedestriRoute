@extends('layouts.app')

@section('title', 'Status Laporan & Perbaikan - PedestriRoute Admin')
@section('admin_shell', '1')

@section('content')
<div class="px-5 pt-6 pb-24 md:px-6 lg:px-8 lg:pt-8 lg:pb-10 flex flex-col flex-1 w-full">
    @include('partials.admin-header', ['title' => 'Status Perbaikan', 'subtitle' => 'Memuat laporan...', 'subtitleId' => 'queueSubtitle', 'badgeId' => 'queueBadge'])

    <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_24rem] xl:grid-cols-[minmax(0,1fr)_28rem] lg:gap-6 lg:items-start">
    <div class="min-w-0">
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
                <select id="selectStatusFilter" onchange="setFilter(this.value)"
                    class="w-full pl-3 pr-8 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm appearance-none cursor-pointer transition">
                    <option value="ALL">Semua Status</option>
                    <option value="BELUM">Belum Ditangani</option>
                    <option value="SEDANG_DIPERBAIKI">Sedang Diperbaiki</option>
                    <option value="SUDAH_DIPERBAIKI">Sudah Diperbaiki</option>
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

    <!-- Report List Container -->
    <div id="reportsList" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-1">
        <div class="text-center py-10 text-slate-400 text-xs md:col-span-2 xl:col-span-1">
            <svg class="w-8 h-8 mx-auto mb-2 animate-spin text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Memuat daftar laporan...
        </div>
    </div>
    </div>

    <!-- Panel pratinjau detail (desktop) -->
    <aside id="detailPanel" class="hidden xl:flex xl:flex-col bg-white rounded-2xl border border-slate-100 shadow-sm sticky top-6 max-h-[calc(100vh-3rem)] overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between gap-2">
            <h2 class="text-xs font-extrabold text-slate-800">Pratinjau Laporan</h2>
            <span id="detailPanelHint" class="text-[10px] font-semibold text-slate-400 truncate">Pilih laporan</span>
        </div>
        <div id="detailPanelBody" class="flex-1 overflow-y-auto p-4 text-xs text-slate-500">
            Pilih salah satu laporan untuk melihat detailnya di sini.
        </div>
    </aside>
    </div>
</div>

@include('partials.admin-bottom-nav')
@endsection

@push('scripts')
<script>
    const token = localStorage.getItem('token');
    const role = localStorage.getItem('role');

    if (!token || role !== 'admin') {
        window.location.href = '/login';
    }

    // Logout
    document.getElementById('logoutBtn').addEventListener('click', async () => {
        try {
            await fetch('/api/v1/auth/logout', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token }
            });
        } catch (e) {}
        localStorage.clear();
        window.location.href = '/login';
    });

    let currentFilter = 'ALL';
    let allReports = [];

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

    function setFilter(filter) {
        currentFilter = filter;
        const select = document.getElementById('selectStatusFilter');
        if (select && select.value !== filter) {
            select.value = filter;
        }
        renderReports();
    }

    // Filter awal dari URL, mis. /admin/status-laporan?filter=BELUM
    const VALID_FILTERS = ['ALL', 'BELUM', 'SEDANG_DIPERBAIKI', 'SUDAH_DIPERBAIKI'];
    const initialFilter = new URLSearchParams(window.location.search).get('filter');
    if (VALID_FILTERS.includes(initialFilter)) {
        currentFilter = initialFilter;
        const select = document.getElementById('selectStatusFilter');
        if (select) select.value = initialFilter;
    }

    function formatTime(isoStr) {
        if (!isoStr) return '-';
        const d = new Date(isoStr);
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function getRepairBadge(statusPerbaikan) {
        if (statusPerbaikan === 'SUDAH_DIPERBAIKI') {
            return `<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200/80 rounded-full text-[10.5px] font-semibold whitespace-nowrap shrink-0 shadow-2xs">
                <svg class="w-3 h-3 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <span>Sudah Diperbaiki</span>
            </span>`;
        }
        if (statusPerbaikan === 'SEDANG_DIPERBAIKI') {
            return `<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 bg-sky-50 text-sky-700 border border-sky-200/80 rounded-full text-[10.5px] font-semibold whitespace-nowrap shrink-0 shadow-2xs">
                <svg class="w-3 h-3 text-sky-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                <span>Sedang Diperbaiki</span>
            </span>`;
        }
        return `<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 bg-amber-50 text-amber-700 border border-amber-200/80 rounded-full text-[10.5px] font-semibold whitespace-nowrap shrink-0 shadow-2xs">
            <svg class="w-3 h-3 text-amber-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span>Belum Ditangani</span>
        </span>`;
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

        // 1. Status Filter
        if (currentFilter === 'BELUM') {
            filtered = filtered.filter(r => !r.status_perbaikan);
        } else if (currentFilter === 'SEDANG_DIPERBAIKI') {
            filtered = filtered.filter(r => r.status_perbaikan === 'SEDANG_DIPERBAIKI');
        } else if (currentFilter === 'SUDAH_DIPERBAIKI') {
            filtered = filtered.filter(r => r.status_perbaikan === 'SUDAH_DIPERBAIKI');
        }

        // 2. Date Filter (specific date against tanggal_lapor, tanggal_validasi, or tanggal_perbaikan)
        if (dateVal) {
            filtered = filtered.filter(r => {
                const dLapor = r.tanggal_lapor ? r.tanggal_lapor.split('T')[0] : '';
                const dVal = r.tanggal_validasi ? r.tanggal_validasi.split('T')[0] : '';
                const dPerbaikan = r.tanggal_perbaikan ? r.tanggal_perbaikan.split('T')[0] : '';
                return dLapor === dateVal || dVal === dateVal || dPerbaikan === dateVal;
            });
        }

        // 3. Text Search (kode laporan, nama jalan, pelapor, kategori, deskripsi)
        if (searchVal) {
            filtered = filtered.filter(r => {
                const kode = (r.kode_laporan || '').toLowerCase();
                const idStr = String(r.laporan_id || '');
                const alamat = (r.alamat || '').toLowerCase();
                const deskripsi = (r.deskripsi || '').toLowerCase();
                const pelapor = (r.pelapor_nama || '').toLowerCase();
                const kat = (r.kategori || '').toLowerCase();
                return kode.includes(searchVal) ||
                       idStr.includes(searchVal) ||
                       alamat.includes(searchVal) ||
                       deskripsi.includes(searchVal) ||
                       pelapor.includes(searchVal) ||
                       kat.includes(searchVal);
            });
        }

        const badge = document.getElementById('queueBadge');
        if (badge) badge.innerText = filtered.length;

        const subtitleEl = document.getElementById('queueSubtitle');
        if (subtitleEl) {
            const hasFilter = searchVal || dateVal || currentFilter !== 'ALL';
            subtitleEl.innerText = hasFilter
                ? `${filtered.length} dari ${allReports.length} laporan cocok`
                : `${filtered.length} laporan tervalidasi`;
        }

        if (allReports.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12 bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <span class="text-3xl mb-2 block">✨</span>
                    <h3 class="text-sm font-bold text-slate-800">Tidak Ada Laporan</h3>
                    <p class="text-xs text-slate-500 mt-1">Belum ada laporan yang tervalidasi saat ini.</p>
                </div>
            `;
            resetDetailPanel();
            return;
        }

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="text-center py-10 bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <span class="text-2xl mb-1.5 block">🔍</span>
                    <h3 class="text-sm font-bold text-slate-800">Tidak Ada Hasil</h3>
                    <p class="text-xs text-slate-500 mt-1">Tidak ada laporan yang cocok dengan kriteria filter.</p>
                    <button type="button" onclick="setFilter('ALL'); clearSearchText(); clearDateFilter();" class="mt-3 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                        Reset Filter
                    </button>
                </div>
            `;
            resetDetailPanel();
            return;
        }

        container.innerHTML = filtered.map(r => `
            <div data-report-id="${r.laporan_id}" onclick="selectReport(${r.laporan_id})"
                class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm hover:border-brand-500/40 transition flex flex-col gap-2.5 cursor-pointer">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-1.5 min-w-0">
                        <span class="text-xs font-bold text-slate-800 tracking-tight tabular-nums shrink-0">${r.kode_laporan}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium text-slate-600 bg-slate-100 shrink-0">${r.kategori}</span>
                    </div>
                    <div class="shrink-0 pt-0.5">
                        ${getRepairBadge(r.status_perbaikan)}
                    </div>
                </div>

                <div>
                    <p class="text-xs font-bold text-slate-800 truncate mb-1">${r.alamat || 'Surabaya'}</p>
                    <p class="text-xs text-slate-600 line-clamp-2 leading-relaxed">${r.deskripsi}</p>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-slate-50">
                    <span class="text-[11px] text-slate-400">Oleh <strong class="text-slate-600 font-medium">${r.pelapor_nama}</strong></span>
                    <a href="/admin/status-laporan/${r.laporan_id}" 
                       class="px-3.5 py-1.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1 shadow-sm">
                        <span>Kelola</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
            </div>
        `).join('');

        syncDetailPanel(filtered);
    }

    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[c]));
    }

    let selectedReportId = null;

    function selectReport(id) {
        if (!window.matchMedia('(min-width: 1280px)').matches) return;
        selectedReportId = String(id);
        applySelection();
        loadDetailPreview(id);
    }

    function applySelection() {
        document.querySelectorAll('[data-report-id]').forEach(el => {
            const active = el.dataset.reportId === String(selectedReportId);
            el.classList.toggle('ring-2', active);
            el.classList.toggle('ring-brand-500/40', active);
            el.classList.toggle('border-brand-500/40', active);
        });
    }

    function resetDetailPanel() {
        const body = document.getElementById('detailPanelBody');
        const hint = document.getElementById('detailPanelHint');
        if (body) body.innerHTML = 'Pilih salah satu laporan untuk melihat detailnya di sini.';
        if (hint) hint.innerText = 'Pilih laporan';
        selectedReportId = null;
    }

    function syncDetailPanel(filtered) {
        if (!window.matchMedia('(min-width: 1280px)').matches) return;
        const stillVisible = filtered.some(r => String(r.laporan_id) === String(selectedReportId));
        if (stillVisible) {
            applySelection();
            return;
        }
        if (filtered.length > 0) {
            selectReport(filtered[0].laporan_id);
        } else {
            resetDetailPanel();
        }
    }

    async function loadDetailPreview(id) {
        const body = document.getElementById('detailPanelBody');
        const hint = document.getElementById('detailPanelHint');
        if (!body) return;
        body.innerHTML = '<p class="py-8 text-center text-slate-400">Memuat detail laporan...</p>';
        try {
            const res = await fetch(`/api/v1/reports/${id}`, {
                headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
            });
            if (String(selectedReportId) !== String(id)) return;
            if (!res.ok) throw new Error('failed');
            const report = (await res.json()).data;
            renderDetailPreview(report);
            if (hint) hint.innerText = `#LAP-${String(report.laporan_id).padStart(4, '0')}`;
        } catch (e) {
            if (String(selectedReportId) !== String(id)) return;
            body.innerHTML = `
                <div class="py-8 text-center">
                    <p class="text-rose-500 font-bold mb-2">Gagal memuat detail laporan</p>
                    <button type="button" onclick="selectReport(${id})" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">Coba Lagi</button>
                </div>`;
        }
    }

    function renderDetailPreview(report) {
        const body = document.getElementById('detailPanelBody');
        if (!body) return;
        const item = allReports.find(r => String(r.laporan_id) === String(report.laporan_id)) || {};
        const photo = report.foto_url
            ? `<img src="${esc(report.foto_url)}" alt="Foto laporan" class="w-full h-52 object-cover rounded-xl border border-slate-200 bg-slate-100">`
            : `<div class="w-full h-52 rounded-xl border border-slate-200 bg-slate-100 flex items-center justify-center text-slate-400">Foto tidak tersedia</div>`;
        const repairPhoto = report.foto_perbaikan_url
            ? `<div><p class="text-[10px] font-bold uppercase tracking-wider ${report.status_perbaikan === 'SUDAH_DIPERBAIKI' ? 'text-emerald-700' : 'text-sky-700'} mb-1">Foto Perbaikan</p><img src="${esc(report.foto_perbaikan_url)}" alt="Foto perbaikan" class="w-full h-40 object-cover rounded-xl border border-slate-200"></div>`
            : '';
        const note = report.catatan_perbaikan
            ? `<div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-0.5">Catatan Perbaikan</p><p class="text-slate-700 leading-relaxed">${esc(report.catatan_perbaikan)}</p></div>`
            : '';
        const ml = (report.confidence_score !== null && report.confidence_score !== undefined && report.cv_label)
            ? `<div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5 flex items-center justify-between gap-2"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Verifikasi ML</span><span class="text-[10.5px] font-bold text-slate-700">${esc(String(report.cv_label).toUpperCase())} · ${Math.round(Number(report.confidence_score) * 100)}%</span></div>`
            : '';

        body.innerHTML = `
            <div class="space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-extrabold text-slate-800 tracking-tight tabular-nums">#LAP-${String(report.laporan_id).padStart(4, '0')}</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">${esc(report.kategori?.nama || '-')}</p>
                    </div>
                    <div class="shrink-0">${getRepairBadge(report.status_perbaikan)}</div>
                </div>
                ${photo}
                <div>
                    <p class="text-xs font-bold text-slate-800 leading-snug">${esc(report.lokasi?.alamat || report.lokasi?.nama_jalan || 'Alamat tidak tersedia')}</p>
                    <p class="text-xs text-slate-600 mt-1 leading-relaxed">${esc(report.deskripsi || '-')}</p>
                </div>
                <div class="grid grid-cols-2 gap-2 text-[10.5px]">
                    <div class="rounded-lg bg-slate-50 border border-slate-100 p-2"><span class="block text-slate-400 font-semibold">Pelapor</span><span class="font-bold text-slate-700">${esc(item.pelapor_nama || 'Warga')}</span></div>
                    <div class="rounded-lg bg-slate-50 border border-slate-100 p-2"><span class="block text-slate-400 font-semibold">Tanggal Lapor</span><span class="font-bold text-slate-700">${report.tanggal_lapor ? new Date(report.tanggal_lapor).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric'}) : '-'}</span></div>
                </div>
                ${ml}
                ${note}
                ${repairPhoto}
                <a href="/admin/status-laporan/${report.laporan_id}" class="w-full py-2.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-sm">
                    <span>Kelola Perbaikan</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                </a>
            </div>`;
    }

    async function loadReports() {
        try {
            const res = await fetch('/api/v1/admin/reports?status=TERVALIDASI', {
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json'
                }
            });

            if (!res.ok) {
                if (res.status === 401 || res.status === 403) {
                    window.location.href = '/login';
                    return;
                }
                showToast('Gagal memuat laporan', 'error');
                return;
            }

            const json = await res.json();
            allReports = json.data || [];
            allReports.sort((a, b) => new Date(b.tanggal_lapor || b.tanggal_validasi || 0) - new Date(a.tanggal_lapor || a.tanggal_validasi || 0));

            renderReports();
        } catch (e) {
            showToast('Kesalahan jaringan saat memuat laporan', 'error');
        }
    }

    loadReports();
</script>
@endpush