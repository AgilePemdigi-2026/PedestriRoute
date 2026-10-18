@extends('layouts.app')

@section('title', 'Antrean Validasi - PedestriRoute Admin')

@section('content')
<div class="px-5 pt-6 pb-20 flex flex-col flex-1">
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

    <!-- Antrean Banner -->
    <div class="bg-gradient-to-r from-teal-800 to-brand-600 rounded-2xl p-4 text-white shadow-md mb-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-bold">Antrean Validasi</h2>
                <p id="queueSubtitle" class="text-xs text-teal-100">Memuat laporan...</p>
            </div>
        </div>
        <span id="queueBadge" class="px-2.5 py-1 bg-white text-brand-800 text-xs font-extrabold rounded-full shadow-sm">
            0
        </span>
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

<!-- Bottom Navigation for Admin -->
<div class="fixed bottom-0 max-w-md w-full bg-white border-t border-slate-200 py-2.5 px-6 flex items-center justify-around z-40">
    <a href="/admin/validasi" class="flex flex-col items-center gap-1 text-brand-600 font-bold">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-[10px]">Validasi Laporan</span>
    </a>
    <a href="/beranda-warga" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <span class="text-[10px]">Tampilan Warga</span>
    </a>
</div>
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
            document.getElementById('queueSubtitle').innerText = `${reports.length} Laporan Menunggu Verifikasi`;

            const container = document.getElementById('reportsList');

            if (reports.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-12 bg-white rounded-2xl border border-slate-100 p-6">
                        <span class="text-3xl mb-2 block">🎉</span>
                        <h3 class="text-sm font-bold text-slate-800">Antrean Bersih</h3>
                        <p class="text-xs text-slate-500 mt-1">Tidak ada laporan yang perlu diverifikasi saat ini.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            reports.forEach((lap) => {
                html += `
                    <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm flex flex-col gap-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-mono font-bold text-teal-800 bg-teal-50 px-2 py-0.5 rounded-md">
                                ${lap.kode_laporan}
                            </span>
                            <span class="text-[10px] font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full">
                                ${lap.kategori}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                            <span class="truncate">${lap.alamat}</span>
                        </p>
                        <p class="text-xs text-slate-700 bg-slate-50 rounded-xl p-2.5 italic">
                            "${lap.deskripsi}"
                        </p>
                        <div class="flex items-center justify-between pt-1">
                            <span class="text-[10px] text-slate-400">Pelapor: ${lap.pelapor_nama}</span>
                            <a href="/admin/validasi/${lap.laporan_id}"
                               class="px-3.5 py-1.5 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                <span>Verifikasi</span>
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
