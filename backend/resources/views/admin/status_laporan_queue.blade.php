@extends('layouts.app')

@section('title', 'Status Laporan & Perbaikan - PedestriRoute Admin')

@section('content')
<div class="px-5 pt-6 pb-20 flex flex-col flex-1">
    <!-- Top Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-base font-extrabold text-slate-900 tracking-tight">Status Perbaikan</h1>
            <p id="queueSubtitle" class="text-xs text-slate-500 font-medium">Memuat laporan...</p>
        </div>
        <div class="flex items-center gap-2">
            <span id="queueBadge" class="px-2.5 py-1 bg-teal-50 text-brand-700 text-xs font-extrabold rounded-full border border-teal-100">
                0
            </span>
            <button id="logoutBtn" class="px-3 py-1.5 bg-rose-50 text-rose-600 rounded-xl text-xs font-bold border border-rose-100 hover:bg-rose-100 transition">
                Keluar
            </button>
        </div>
    </div>

    <!-- Status Filters -->
    <div class="flex items-center gap-1.5 overflow-x-auto pb-2 mb-3 scrollbar-none">
        <button onclick="setFilter('ALL')" id="filter-ALL" class="filter-chip px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap bg-brand-600 text-white shadow-sm transition">
            Semua
        </button>
        <button onclick="setFilter('BELUM')" id="filter-BELUM" class="filter-chip px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
            Belum Ditangani
        </button>
        <button onclick="setFilter('SEDANG_DIPERBAIKI')" id="filter-SEDANG_DIPERBAIKI" class="filter-chip px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
            Sedang Diperbaiki
        </button>
        <button onclick="setFilter('SUDAH_DIPERBAIKI')" id="filter-SUDAH_DIPERBAIKI" class="filter-chip px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
            Sudah Diperbaiki
        </button>
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

    let currentFilter = 'ALL';
    let allReports = [];

    function setFilter(filter) {
        currentFilter = filter;
        document.querySelectorAll('.filter-chip').forEach(btn => {
            btn.className = 'filter-chip px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap bg-slate-100 text-slate-600 hover:bg-slate-200 transition';
        });
        const activeBtn = document.getElementById('filter-' + filter);
        if (activeBtn) {
            activeBtn.className = 'filter-chip px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap bg-brand-600 text-white shadow-sm transition';
        }
        renderReports();
    }

    function formatTime(isoStr) {
        if (!isoStr) return '-';
        const d = new Date(isoStr);
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
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

    function renderReports() {
        const container = document.getElementById('reportsList');
        
        let filtered = allReports;
        if (currentFilter === 'BELUM') {
            filtered = allReports.filter(r => !r.status_perbaikan);
        } else if (currentFilter === 'SEDANG_DIPERBAIKI') {
            filtered = allReports.filter(r => r.status_perbaikan === 'SEDANG_DIPERBAIKI');
        } else if (currentFilter === 'SUDAH_DIPERBAIKI') {
            filtered = allReports.filter(r => r.status_perbaikan === 'SUDAH_DIPERBAIKI');
        }

        document.getElementById('queueBadge').innerText = filtered.length;
        document.getElementById('queueSubtitle').innerText = `${filtered.length} laporan tervalidasi`;

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="text-center py-12 bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                    <span class="text-3xl mb-2 block">✨</span>
                    <h3 class="text-sm font-bold text-slate-800">Tidak Ada Laporan</h3>
                    <p class="text-xs text-slate-500 mt-1">Tidak ada laporan dengan status ini.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = filtered.map(r => `
            <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm hover:border-brand-500/40 transition flex flex-col gap-2.5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-brand-700 bg-brand-50 border border-brand-200/60 tracking-tight tabular-nums">${r.kode_laporan}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium text-slate-600 bg-slate-100">${r.kategori}</span>
                    </div>
                    ${getRepairBadge(r.status_perbaikan)}
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
            allReports = json.data;
            renderReports();
        } catch (e) {
            showToast('Kesalahan jaringan saat memuat laporan', 'error');
        }
    }

    loadReports();
</script>
@endpush
