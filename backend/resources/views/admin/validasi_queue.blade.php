@extends('layouts.app')

@section('title', 'Antrean Validasi - PedestriRoute Admin')

@section('content')
<div class="px-5 pt-6 pb-20 flex flex-col flex-1">
    <!-- Top Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-base font-extrabold text-slate-900 tracking-tight">Antrean Validasi</h1>
            <p id="queueSubtitle" class="text-xs text-slate-500 font-medium">Memuat antrean...</p>
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

    async function loadQueue() {
        try {
            const res = await fetch('/api/v1/admin/reports?status=MENUNGGU_VALIDASI_ADMIN', {
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
                showToast('Gagal memuat antrean laporan', 'error');
                return;
            }

            const json = await res.json();
            const reports = json.data;

            document.getElementById('queueBadge').innerText = reports.length;
            document.getElementById('queueSubtitle').innerText = `${reports.length} laporan menunggu ditinjau`;

            const container = document.getElementById('reportsList');

            if (reports.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12 bg-white rounded-2xl border border-slate-100 p-6 shadow-sm">
                        <span class="text-3xl mb-2 block">🎉</span>
                        <h3 class="text-sm font-bold text-slate-800">Antrean Bersih</h3>
                        <p class="text-xs text-slate-500 mt-1">Tidak ada laporan yang perlu divalidasi saat ini.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            reports.forEach((lap) => {
                let aiBadge = '';
                if (lap.status === 'DIPROSES') {
                    aiBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold text-sky-700 bg-sky-50 border border-sky-200 animate-pulse"><i class="fa-solid fa-spinner fa-spin text-[9px]"></i> AI: Memproses</span>`;
                } else if (lap.cv_label === 'SPAM') {
                    const pct = lap.confidence_score ? Math.round(lap.confidence_score * 100) : '';
                    aiBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold text-rose-700 bg-rose-50 border border-rose-200"><i class="fa-solid fa-triangle-exclamation text-[9px]"></i> AI: SPAM ${pct ? pct + '%' : ''}</span>`;
                } else if (lap.cv_label === 'BAIK') {
                    const pct = lap.confidence_score ? Math.round(lap.confidence_score * 100) : '';
                    aiBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold text-blue-700 bg-blue-50 border border-blue-200"><i class="fa-solid fa-circle-info text-[9px]"></i> AI: Normal ${pct ? pct + '%' : ''}</span>`;
                } else if (lap.confidence_score) {
                    const pct = Math.round(lap.confidence_score * 100);
                    aiBadge = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200"><i class="fa-solid fa-robot text-[9px]"></i> AI: ${pct}%</span>`;
                }

                html += `
                    <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm flex flex-col gap-2.5 hover:border-brand-500/40 transition">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-brand-700 bg-brand-50 border border-brand-200/60 tracking-tight tabular-nums">${lap.kode_laporan}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold text-slate-600 bg-slate-100">${lap.kategori}</span>
                                ${aiBadge}
                            </div>
                            <span class="text-[11px] text-slate-400 font-medium">
                                ${formatTimeAgo(lap.tanggal_lapor)}
                            </span>
                        </div>

                        <div>
                            <p class="text-xs font-bold text-slate-800 truncate mb-1">
                                ${lap.alamat || 'Alamat tidak tersedia'}
                            </p>
                            <p class="text-xs text-slate-600 line-clamp-2 leading-relaxed">
                                ${lap.deskripsi}
                            </p>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-slate-50">
                            <span class="text-[11px] text-slate-400">Pelapor: <strong class="text-slate-600 font-medium">${lap.pelapor_nama}</strong></span>
                            <a href="/admin/validasi/${lap.laporan_id}"
                               class="px-3.5 py-1.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                <span>Periksa</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        } catch (e) {
            showToast('Gagal terhubung ke server', 'error');
        }
    }

    loadQueue();
</script>
@endpush