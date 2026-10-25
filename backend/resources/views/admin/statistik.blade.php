@extends('layouts.app')

@section('title', 'Statistik Laporan - PedestriRoute Admin')

@section('content')
<div class="px-5 pt-6 pb-24 flex flex-col flex-1">
    <!-- Top Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-base font-extrabold text-slate-900 leading-tight">PedestriRoute</h1>
            <p class="text-[10px] text-teal-800 font-semibold tracking-wider">ADMIN PORTAL</p>
        </div>
        <button id="logoutBtn" class="px-3 py-1.5 bg-rose-50 text-rose-600 rounded-xl text-xs font-bold border border-rose-100 hover:bg-rose-100 transition">
            Keluar
        </button>
    </div>

    <!-- Statistik Banner -->
    <div class="bg-gradient-to-r from-teal-800 to-brand-600 rounded-2xl p-4 text-white shadow-md mb-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-bold">Ringkasan Statistik</h2>
                <p class="text-[11px] text-teal-100">Kecamatan Mulyorejo, Surabaya</p>
            </div>
        </div>
        <button type="button" id="btnRefresh" class="p-2 bg-white/20 hover:bg-white/30 rounded-xl transition text-white" title="Segarkan Data">
            <svg id="refreshIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
        </button>
    </div>

    <!-- Loading Skeleton -->
    <div id="statsLoading" class="text-center py-12 text-slate-400 text-xs">
        <svg class="w-8 h-8 mx-auto mb-2 animate-spin text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Memuat data statistik...
    </div>

    <!-- Main Content Area (Hidden initially) -->
    <div id="statsContent" class="hidden space-y-4">
        <!-- 1. Metric Cards Grid (2x2) -->
        <div class="grid grid-cols-2 gap-3">
            <!-- Total Laporan -->
            <div class="bg-white p-3.5 rounded-2xl border border-slate-100 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Laporan</span>
                    <div class="w-6 h-6 rounded-lg bg-teal-50 text-brand-600 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>
                <div>
                    <h3 id="statTotalLaporan" class="text-2xl font-extrabold text-slate-900 leading-none">0</h3>
                    <p class="text-[10px] text-slate-400 mt-1">Seluruh aduan masuk</p>
                </div>
            </div>

            <!-- Menunggu Validasi -->
            <div class="bg-white p-3.5 rounded-2xl border border-amber-100 bg-amber-50/20 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-bold text-amber-700 uppercase tracking-wider">Antrean</span>
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                </div>
                <div>
                    <h3 id="statMenunggu" class="text-2xl font-extrabold text-amber-600 leading-none">0</h3>
                    <p class="text-[10px] text-amber-700 font-medium mt-1">Perlu diverifikasi</p>
                </div>
            </div>

            <!-- Laporan Tervalidasi -->
            <div class="bg-white p-3.5 rounded-2xl border border-emerald-100 bg-emerald-50/20 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider">Tervalidasi</span>
                    <div class="w-6 h-6 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>
                <div>
                    <h3 id="statTervalidasi" class="text-2xl font-extrabold text-emerald-600 leading-none">0</h3>
                    <p id="statPersenValid" class="text-[10px] text-emerald-700 font-medium mt-1">0% dari total</p>
                </div>
            </div>

            <!-- Laporan Ditolak -->
            <div class="bg-white p-3.5 rounded-2xl border border-rose-100 bg-rose-50/20 shadow-sm flex flex-col justify-between">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-bold text-rose-700 uppercase tracking-wider">Ditolak</span>
                    <div class="w-6 h-6 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
                <div>
                    <h3 id="statDitolak" class="text-2xl font-extrabold text-rose-600 leading-none">0</h3>
                    <p class="text-[10px] text-rose-600 font-medium mt-1">Laporan tidak valid</p>
                </div>
            </div>
        </div>

        <!-- 2. Breakdown Kategori Masalah Card -->
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-teal-50 text-brand-600 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-900">Distribusi Kategori Masalah</h3>
                        <p class="text-[10px] text-slate-400">Klasifikasi aduan pejalan kaki</p>
                    </div>
                </div>
            </div>

            <div id="kategoriList" class="space-y-3">
                <!-- Dynamically populated categories -->
            </div>
        </div>

        <!-- 3. Kondisi Jalur & Skor Rata-rata Mulyorejo -->
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-teal-50 text-brand-600 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-900">Segmen Jalur Pejalan Kaki</h3>
                        <p class="text-[10px] text-slate-400">Skor Kelayakan Weighted Sum Model (WSM)</p>
                    </div>
                </div>
                <div class="text-right">
                    <span id="statRataRataSkor" class="text-xs font-extrabold text-brand-600">100.0</span>
                    <span class="text-[10px] text-slate-400 block">Rata-rata</span>
                </div>
            </div>

            <div id="segmenList" class="space-y-2">
                <!-- Dynamically populated segment items -->
            </div>
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

    // Logout handler
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

    // Refresh handler
    document.getElementById('btnRefresh').addEventListener('click', () => {
        const icon = document.getElementById('refreshIcon');
        icon.classList.add('animate-spin');
        loadStatistics().finally(() => {
            setTimeout(() => icon.classList.remove('animate-spin'), 600);
        });
    });

    // Fetch and display statistics
    async function loadStatistics() {
        try {
            const res = await fetch('/api/v1/admin/statistics', {
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
                showToast('Gagal memuat data statistik', 'error');
                return;
            }

            const json = await res.json();
            const data = json.data;
            const summary = data.summary;

            // Update Summary Cards
            document.getElementById('statTotalLaporan').innerText = summary.total_laporan;
            document.getElementById('statMenunggu').innerText = summary.menunggu_validasi;
            document.getElementById('statTervalidasi').innerText = summary.tervalidasi;
            document.getElementById('statDitolak').innerText = summary.ditolak;
            document.getElementById('statPersenValid').innerText = `${summary.persentase_valid}% dari total`;
            document.getElementById('statRataRataSkor').innerText = summary.rata_rata_skor_jalan;

            // Render Categories
            const kategoriContainer = document.getElementById('kategoriList');
            kategoriContainer.innerHTML = '';
            const total = summary.total_laporan || 1;

            if (data.kategori && data.kategori.length > 0) {
                data.kategori.forEach(cat => {
                    const count = parseInt(cat.total_laporan, 10) || 0;
                    const percent = summary.total_laporan > 0 ? Math.round((count / summary.total_laporan) * 100) : 0;

                    const row = document.createElement('div');
                    row.className = 'space-y-1';
                    row.innerHTML = `
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-slate-700 truncate pr-2">${cat.nama_kategori}</span>
                            <span class="font-bold text-slate-900 shrink-0">${count} <span class="text-[10px] text-slate-400 font-normal">(${percent}%)</span></span>
                        </div>
                        <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-brand-600 rounded-full transition-all duration-500" style="width: ${percent}%"></div>
                        </div>
                    `;
                    kategoriContainer.appendChild(row);
                });
            } else {
                kategoriContainer.innerHTML = '<p class="text-xs text-slate-400 text-center py-2">Belum ada data kategori.</p>';
            }

            // Render Segments
            const segmenContainer = document.getElementById('segmenList');
            segmenContainer.innerHTML = '';

            if (data.segmen && data.segmen.length > 0) {
                data.segmen.forEach(seg => {
                    const score = parseFloat(seg.skor_kelayakan);
                    let badgeColor = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                    let statusLabel = 'Baik';

                    if (score < 40) {
                        badgeColor = 'bg-rose-50 text-rose-700 border-rose-200';
                        statusLabel = 'Kritis';
                    } else if (score < 70) {
                        badgeColor = 'bg-amber-50 text-amber-700 border-amber-200';
                        statusLabel = 'Cukup';
                    }

                    const div = document.createElement('div');
                    div.className = 'p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-between';
                    div.innerHTML = `
                        <div class="pr-2 truncate">
                            <p class="text-xs font-bold text-slate-800 truncate">${seg.nama_jalan}</p>
                            <p class="text-[10px] text-slate-400">Panjang: ${Math.round(seg.panjang_m)} meter</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold border ${badgeColor}">
                                ${score.toFixed(1)} / 100 (${statusLabel})
                            </span>
                        </div>
                    `;
                    segmenContainer.appendChild(div);
                });
            } else {
                segmenContainer.innerHTML = '<p class="text-xs text-slate-400 text-center py-2">Belum ada data segmen.</p>';
            }

            document.getElementById('statsLoading').classList.add('hidden');
            document.getElementById('statsContent').classList.remove('hidden');
        } catch (e) {
            console.error('Failed to load stats:', e);
            showToast('Koneksi bermasalah saat memuat statistik', 'error');
        }
    }

    loadStatistics();
</script>
@endpush
