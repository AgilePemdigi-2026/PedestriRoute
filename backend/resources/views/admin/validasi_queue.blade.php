@extends('layouts.app')

@section('title', 'Validasi & Perbaikan - PedestriRoute Admin')

@section('content')
<div class="px-5 pt-6 pb-24 flex flex-col flex-1">
    @include('partials.admin-header', ['title' => 'Validasi & Perbaikan', 'subtitle' => 'Memuat data...', 'subtitleId' => 'queueSubtitle'])

    <!-- Segmented Control: Tab Validasi vs Perbaikan vs Ditolak -->
    <div class="flex p-1 bg-slate-100 rounded-xl mb-3 gap-1">
        <button type="button" id="tabValidasiBtn" onclick="switchTab('validasi')"
            class="flex-1 py-2 text-xs font-bold rounded-lg transition flex items-center justify-center gap-1.5 shadow-sm bg-white text-slate-800">
            <span>Antrean</span>
            <span id="tabValidasiCount" class="px-1.5 py-0.5 rounded-full text-[10px] bg-amber-100 text-amber-800 font-extrabold leading-none">0</span>
        </button>
        <button type="button" id="tabPerbaikanBtn" onclick="switchTab('perbaikan')"
            class="flex-1 py-2 text-xs font-bold rounded-lg transition flex items-center justify-center gap-1.5 text-slate-500 hover:text-slate-700">
            <span>Perbaikan</span>
            <span id="tabPerbaikanCount" class="px-1.5 py-0.5 rounded-full text-[10px] bg-slate-200 text-slate-700 font-extrabold leading-none">0</span>
        </button>
        <button type="button" id="tabDitolakBtn" onclick="switchTab('ditolak')"
            class="flex-1 py-2 text-xs font-bold rounded-lg transition flex items-center justify-center gap-1.5 text-slate-500 hover:text-slate-700">
            <span>Ditolak</span>
            <span id="tabDitolakCount" class="px-1.5 py-0.5 rounded-full text-[10px] bg-rose-100 text-rose-800 font-extrabold leading-none">0</span>
        </button>
    </div>

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

        <!-- Secondary Filter: Status Perbaikan Dropdown + Date Picker -->
        <div class="grid grid-cols-2 gap-2" id="filterSecondaryRow">
            <!-- Status Perbaikan Dropdown (only visible on Perbaikan tab) -->
            <div id="repairDropdownWrapper" class="hidden relative">
                <select id="selectRepairStatusFilter" onchange="onRepairStatusChange(this.value)"
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

            <!-- Date Picker (full width on Validasi tab, 1-col on Perbaikan tab) -->
            <div id="dateFilterWrapper" class="col-span-2 relative flex items-center">
                <input type="date" id="dateFilter" onchange="renderReports()"
                    class="w-full px-2.5 py-2 bg-white border border-slate-200 rounded-xl text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm transition font-medium" />
                <button type="button" onclick="clearDateFilter()" id="btnClearDate" class="hidden absolute right-2 text-slate-400 hover:text-slate-600" title="Reset tanggal">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Report List Container -->
    <div id="reportsList" class="space-y-3">
        <div class="text-center py-10 text-slate-400 text-xs">
            <svg class="w-8 h-8 mx-auto mb-2 animate-spin text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Memuat daftar laporan...
        </div>
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

    function formatTimeAgo(dateString) {
        if (!dateString) return 'Baru saja';
        const date = new Date(dateString);
        const now = new Date();
        const diffSec = Math.floor((now - date) / 1000);
        if (diffSec < 60) return 'Baru saja';
        const diffMin = Math.floor(diffSec / 60);
        if (diffMin < 60) return `${diffMin} menit lalu`;
        const diffHour = Math.floor(diffMin / 60);
        if (diffHour < 24) return `${diffHour} jam lalu`;
        const diffDay = Math.floor(diffHour / 24);
        if (diffDay < 30) return `${diffDay} hari lalu`;
        const diffMonth = Math.floor(diffDay / 30);
        if (diffMonth < 12) return `${diffMonth} bulan lalu`;
        return `${Math.floor(diffMonth / 12)} tahun lalu`;
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

    function getRepairBadge(statusPerbaikan) {
        if (statusPerbaikan === 'SUDAH_DIPERBAIKI') {
            return `<span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-full text-[10px] font-bold">Sudah Diperbaiki</span>`;
        }
        if (statusPerbaikan === 'SEDANG_DIPERBAIKI') {
            return `<span class="px-2 py-0.5 bg-sky-100 text-sky-800 rounded-full text-[10px] font-bold">Sedang Diperbaiki</span>`;
        }
        return `<span class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded-full text-[10px] font-bold">Belum Ditangani</span>`;
    }

    // Active tab management: 'validasi', 'perbaikan', or 'ditolak'
    const urlParams = new URLSearchParams(window.location.search);
    const initialTabParam = "{{ $initialTab ?? '' }}";
    let currentTab = initialTabParam || urlParams.get('tab') || (window.location.pathname.includes('status-laporan') ? 'perbaikan' : (window.location.pathname.includes('ditolak') ? 'ditolak' : 'validasi'));
    let repairFilter = 'ALL';

    let validasiReports = [];
    let perbaikanReports = [];
    let ditolakReports = [];

    function switchTab(tab) {
        currentTab = tab;
        const validasiBtn = document.getElementById('tabValidasiBtn');
        const perbaikanBtn = document.getElementById('tabPerbaikanBtn');
        const ditolakBtn = document.getElementById('tabDitolakBtn');
        const repairDropdownWrapper = document.getElementById('repairDropdownWrapper');
        const dateFilterWrapper = document.getElementById('dateFilterWrapper');

        const activeClass = 'flex-1 py-2 text-xs font-bold rounded-lg transition flex items-center justify-center gap-1.5 shadow-sm bg-white text-slate-800';
        const inactiveClass = 'flex-1 py-2 text-xs font-bold rounded-lg transition flex items-center justify-center gap-1.5 text-slate-500 hover:text-slate-700';

        if (validasiBtn) validasiBtn.className = tab === 'validasi' ? activeClass : inactiveClass;
        if (perbaikanBtn) perbaikanBtn.className = tab === 'perbaikan' ? activeClass : inactiveClass;
        if (ditolakBtn) ditolakBtn.className = tab === 'ditolak' ? activeClass : inactiveClass;

        if (tab === 'perbaikan') {
            repairDropdownWrapper.classList.remove('hidden');
            dateFilterWrapper.classList.remove('col-span-2');
        } else {
            repairDropdownWrapper.classList.add('hidden');
            dateFilterWrapper.classList.add('col-span-2');
        }

        // Update URL query without full reload
        const newUrl = new URL(window.location.href);
        newUrl.searchParams.set('tab', tab);
        window.history.replaceState({}, '', newUrl);

        renderReports();
    }

    function onRepairStatusChange(val) {
        repairFilter = val;
        renderReports();
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

        const isVal = currentTab === 'validasi';
        const isDit = currentTab === 'ditolak';

        let source;
        if (isVal) {
            source = validasiReports;
        } else if (isDit) {
            source = ditolakReports;
        } else {
            source = perbaikanReports;
        }

        let filtered = source.slice();

        // Status perbaikan filter (if on perbaikan tab)
        if (currentTab === 'perbaikan' && repairFilter !== 'ALL') {
            if (repairFilter === 'BELUM') {
                filtered = filtered.filter(r => !r.status_perbaikan);
            } else if (repairFilter === 'SEDANG_DIPERBAIKI') {
                filtered = filtered.filter(r => r.status_perbaikan === 'SEDANG_DIPERBAIKI');
            } else if (repairFilter === 'SUDAH_DIPERBAIKI') {
                filtered = filtered.filter(r => r.status_perbaikan === 'SUDAH_DIPERBAIKI');
            }
        }

        // Date Filter
        if (dateVal) {
            filtered = filtered.filter(r => {
                const dLapor = r.tanggal_lapor ? r.tanggal_lapor.split('T')[0] : '';
                const dVal = r.tanggal_validasi ? r.tanggal_validasi.split('T')[0] : '';
                const dPerbaikan = r.tanggal_perbaikan ? r.tanggal_perbaikan.split('T')[0] : '';
                return dLapor === dateVal || dVal === dateVal || dPerbaikan === dateVal;
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

        // Subtitle
        const subtitleEl = document.getElementById('queueSubtitle');
        if (subtitleEl) {
            const hasFilter = searchVal || dateVal || (currentTab === 'perbaikan' && repairFilter !== 'ALL');
            if (hasFilter) {
                subtitleEl.innerText = `${filtered.length} dari ${source.length} laporan cocok`;
            } else {
                if (isVal) {
                    subtitleEl.innerText = `${filtered.length} laporan menunggu ditinjau`;
                } else if (isDit) {
                    subtitleEl.innerText = `${filtered.length} laporan tidak valid / ditolak`;
                } else {
                    subtitleEl.innerText = `${filtered.length} laporan tervalidasi`;
                }
            }
        }

        // Empty states
        if (source.length === 0) {
            let icon = '🎉';
            let title = 'Antrean Bersih';
            let desc = 'Tidak ada laporan yang perlu divalidasi saat ini.';
            if (currentTab === 'perbaikan') {
                icon = '✨';
                title = 'Tidak Ada Laporan';
                desc = 'Belum ada laporan yang tervalidasi saat ini.';
            } else if (isDit) {
                icon = '🛡️';
                title = 'Tidak Ada Laporan Ditolak';
                desc = 'Belum ada laporan yang ditolak atau berstatus tidak valid.';
            }

            container.innerHTML = `
                <div class="text-center py-12 bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <span class="text-3xl mb-2 block">${icon}</span>
                    <h3 class="text-sm font-bold text-slate-800">${title}</h3>
                    <p class="text-xs text-slate-500 mt-1">${desc}</p>
                </div>
            `;
            return;
        }

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="text-center py-10 bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <span class="text-2xl mb-1.5 block">🔍</span>
                    <h3 class="text-sm font-bold text-slate-800">Tidak Ada Hasil</h3>
                    <p class="text-xs text-slate-500 mt-1">Tidak ada laporan yang cocok dengan kriteria filter.</p>
                    <button type="button" onclick="clearSearchText(); clearDateFilter();" class="mt-3 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
                        Reset Filter
                    </button>
                </div>
            `;
            return;
        }

        // Render Cards
        container.innerHTML = filtered.map(r => `
            <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm flex flex-col gap-2.5 ${isDit ? 'hover:border-rose-400/50' : 'hover:border-brand-500/40'} transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-bold text-slate-800 tracking-tight tabular-nums">${esc(r.kode_laporan)}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium text-slate-600 bg-slate-100">${esc(r.kategori)}</span>
                    </div>
                    ${isVal
                        ? `<span class="text-[11px] text-slate-400 font-medium">${formatTimeAgo(r.tanggal_lapor)}</span>`
                        : (isDit
                            ? `<span class="px-2 py-0.5 rounded-full border text-[10px] font-bold bg-rose-50 text-rose-800 border-rose-200/80">✕ Tidak Valid</span>`
                            : getRepairBadge(r.status_perbaikan))}
                </div>

                <div>
                    <p class="text-xs font-bold text-slate-800 truncate mb-1">
                        ${esc(r.alamat || 'Alamat tidak tersedia')}
                    </p>
                    <p class="text-xs text-slate-600 line-clamp-2 leading-relaxed">
                        ${esc(r.deskripsi || '-')}
                    </p>
                </div>

                ${isDit && r.alasan_penolakan ? `
                    <div class="p-2.5 bg-rose-50/80 border border-rose-100 rounded-xl">
                        <span class="text-[10px] font-bold text-rose-700 uppercase tracking-wider block mb-0.5">Alasan Penolakan:</span>
                        <p class="text-xs text-rose-900 leading-relaxed font-medium">${esc(r.alasan_penolakan)}</p>
                    </div>
                ` : ''}

                <div class="flex items-center justify-between pt-2 border-t border-slate-50 text-[11px]">
                    <span class="text-slate-400">Pelapor: <strong class="text-slate-600 font-medium">${esc(r.pelapor_nama || 'Warga')}</strong></span>
                    ${isVal ? `
                        <a href="/admin/validasi/${r.laporan_id}"
                           class="px-3.5 py-1.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1 shadow-sm">
                            <span>Periksa</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    ` : (isDit ? `
                        <a href="/admin/validasi/${r.laporan_id}"
                           class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1 shadow-xs">
                            <span>Periksa</span>
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    ` : `
                        <a href="/admin/status-laporan/${r.laporan_id}"
                           class="px-3.5 py-1.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1 shadow-sm">
                            <span>Kelola</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    `)}
                </div>
            </div>
        `).join('');
    }

    async function loadAllData() {
        try {
            const [resVal, resPer, resDit] = await Promise.all([
                fetch('/api/v1/admin/reports?status=MENUNGGU_VALIDASI_ADMIN', {
                    headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
                }),
                fetch('/api/v1/admin/reports?status=TERVALIDASI', {
                    headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
                }),
                fetch('/api/v1/admin/reports?status=DITOLAK', {
                    headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
                })
            ]);

            if (resVal.status === 401 || resPer.status === 401 || resDit.status === 401) {
                window.location.href = '/login';
                return;
            }

            if (resVal.ok) {
                const jsonVal = await resVal.json();
                validasiReports = (jsonVal.data || []).slice();
                validasiReports.sort((a, b) => new Date(b.tanggal_lapor || 0) - new Date(a.tanggal_lapor || 0));
                const countVal = document.getElementById('tabValidasiCount');
                if (countVal) countVal.innerText = validasiReports.length;
            }

            if (resPer.ok) {
                const jsonPer = await resPer.json();
                perbaikanReports = (jsonPer.data || []).slice();
                perbaikanReports.sort((a, b) => new Date(b.tanggal_lapor || 0) - new Date(a.tanggal_lapor || 0));
                const countPer = document.getElementById('tabPerbaikanCount');
                if (countPer) countPer.innerText = perbaikanReports.length;
            }

            if (resDit && resDit.ok) {
                const jsonDit = await resDit.json();
                ditolakReports = (jsonDit.data || []).slice();
                ditolakReports.sort((a, b) => new Date(b.tanggal_validasi || b.tanggal_lapor || 0) - new Date(a.tanggal_validasi || a.tanggal_lapor || 0));
                const countDit = document.getElementById('tabDitolakCount');
                if (countDit) countDit.innerText = ditolakReports.length;
            }

            switchTab(currentTab);
        } catch (e) {
            document.getElementById('reportsList').innerHTML = `
                <div class="text-center py-10 bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <p class="text-xs text-rose-500 font-bold mb-2">Gagal memuat data dari server</p>
                    <button type="button" onclick="loadAllData()" class="px-3 py-1.5 bg-brand-600 text-white rounded-xl text-xs font-bold shadow-sm">Coba Lagi</button>
                </div>
            `;
        }
    }

    loadAllData();
</script>
@endpush
