@extends('layouts.app')

@section('title', 'Statistik Laporan - PedestriRoute Admin')

@section('content')
<div class="px-5 pt-6 pb-24 flex flex-col flex-1">
    @include('partials.admin-header', ['title' => 'Ringkasan Statistik', 'subtitle' => 'Kecamatan Mulyorejo, Surabaya'])

    <!-- Info pembaruan + tombol segarkan -->
    <div class="flex items-center justify-between -mt-2 mb-4">
        <p id="lastUpdated" class="text-[10px] text-slate-400">Memuat data...</p>
        <button type="button" id="btnRefresh" class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[11px] font-bold text-slate-600 transition">
            <svg id="refreshIcon" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Segarkan
        </button>
    </div>

    <!-- Loading -->
    <div id="statsLoading" class="text-center py-12 text-slate-400 text-xs">
        <svg class="w-8 h-8 mx-auto mb-2 animate-spin text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Memuat data statistik...
    </div>

    <div id="statsContent" class="hidden space-y-4">
        <!-- 1. Kartu KPI -->
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-white p-3.5 rounded-2xl border border-slate-100 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Aduan</span>
                    <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </div>
                </div>
                <h3 id="statTotalLaporan" class="text-2xl font-extrabold text-slate-900 leading-none tabular-nums">0</h3>
                <p class="text-[10px] text-slate-400 mt-1">Seluruh laporan masuk</p>
            </div>

            <div class="bg-amber-50/40 p-3.5 rounded-2xl border border-amber-100 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-bold text-amber-700 uppercase tracking-wider">Menunggu</span>
                    <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                </div>
                <h3 id="statMenunggu" class="text-2xl font-extrabold text-amber-600 leading-none tabular-nums">0</h3>
                <p class="text-[10px] text-amber-700 font-medium mt-1">Menunggu verifikasi</p>
            </div>

            <div class="bg-teal-50/40 p-3.5 rounded-2xl border border-teal-100 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-bold text-brand-700 uppercase tracking-wider">Ditangani</span>
                    <div class="w-7 h-7 rounded-lg bg-teal-100 text-brand-700 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    </div>
                </div>
                <h3 id="statSedang" class="text-2xl font-extrabold text-brand-600 leading-none tabular-nums">0</h3>
                <p class="text-[10px] text-brand-700 font-medium mt-1">Sedang diperbaiki</p>
            </div>

            <div class="bg-emerald-50/40 p-3.5 rounded-2xl border border-emerald-100 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider">Selesai</span>
                    <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    </div>
                </div>
                <h3 id="statSelesai" class="text-2xl font-extrabold text-emerald-600 leading-none tabular-nums">0</h3>
                <p id="statSelesaiNote" class="text-[10px] text-emerald-700 font-medium mt-1">Sudah diperbaiki</p>
            </div>
        </div>

        <!-- 2. Akses cepat -->
        <div class="grid grid-cols-2 gap-3">
            <a href="/admin/validasi" class="flex items-center justify-between gap-2 px-3.5 py-3 rounded-2xl bg-amber-500 hover:bg-amber-600 text-white shadow-sm transition">
                <span class="text-xs font-bold leading-tight">Verifikasi<br>Laporan</span>
                <span id="shortcutValidasiCount" class="min-w-[1.75rem] text-center px-2 py-0.5 bg-white text-amber-700 text-xs font-extrabold rounded-full">0</span>
            </a>
            <a href="/admin/status-laporan?filter=BELUM" class="flex items-center justify-between gap-2 px-3.5 py-3 rounded-2xl bg-brand-600 hover:bg-brand-700 text-white shadow-sm transition">
                <span class="text-xs font-bold leading-tight">Belum<br>Ditangani</span>
                <span id="shortcutPerbaikanCount" class="min-w-[1.75rem] text-center px-2 py-0.5 bg-white text-brand-700 text-xs font-extrabold rounded-full">0</span>
            </a>
        </div>

        <!-- 3. Rasio validasi -->
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-bold text-slate-900">Rasio Validasi</h3>
                <span id="statPersenValid" class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded-full">0% valid</span>
            </div>
            <div id="ratioBar" class="flex w-full h-2.5 rounded-full overflow-hidden bg-slate-100"></div>
            <div id="ratioLegend" class="grid grid-cols-2 gap-x-3 gap-y-1.5 mt-3"></div>
        </div>

        <!-- 4. Distribusi kategori -->
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <h3 class="text-xs font-bold text-slate-900">Distribusi Kategori</h3>
            <p class="text-[10px] text-slate-400 mb-3">Jumlah laporan per kategori masalah</p>
            <div id="kategoriList" class="space-y-3"></div>
        </div>

        <!-- 5. Kondisi segmen jalur -->
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h3 class="text-xs font-bold text-slate-900">Kondisi Segmen Jalur</h3>
                    <p class="text-[10px] text-slate-400">Skor kelayakan, terendah di atas</p>
                </div>
                <div class="text-right">
                    <span id="statRataRataSkor" class="text-sm font-extrabold text-brand-600">100.0</span>
                    <span class="text-[10px] text-slate-400 block">Rata-rata</span>
                </div>
            </div>
            <div id="segmenList" class="space-y-2"></div>
            <button type="button" id="btnToggleSegmen" class="hidden w-full mt-3 py-2 text-xs font-bold text-brand-600 bg-teal-50 hover:bg-teal-100 rounded-xl transition"></button>
        </div>
    </div>
</div>

@include('partials.admin-bottom-nav')
@endsection

@push('scripts')
<script>
    const token = localStorage.getItem('token');
    const role = localStorage.getItem('role');
    const SEGMEN_PREVIEW = 5;
    let segmenData = [];
    let showAllSegmen = false;

    if (!token || role !== 'admin') {
        window.location.href = '/login';
    }

    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function pct(n, total) {
        return total > 0 ? Math.round((n / total) * 100) : 0;
    }

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

    document.getElementById('btnRefresh').addEventListener('click', () => {
        const icon = document.getElementById('refreshIcon');
        icon.classList.add('animate-spin');
        loadStatistics().finally(() => {
            setTimeout(() => icon.classList.remove('animate-spin'), 600);
        });
    });

    document.getElementById('btnToggleSegmen').addEventListener('click', () => {
        showAllSegmen = !showAllSegmen;
        renderSegmen();
    });

    function renderRatio(summary) {
        const total = summary.total_laporan || 0;
        const valid = summary.tervalidasi || 0;
        const menunggu = summary.menunggu_validasi || 0;
        const ditolak = summary.ditolak || 0;
        const lainnya = Math.max(total - valid - menunggu - ditolak, 0);

        const parts = [
            { label: 'Valid',    n: valid,    bar: 'bg-emerald-500', dot: 'bg-emerald-500', href: '/admin/validasi?tab=perbaikan' },
            { label: 'Menunggu', n: menunggu, bar: 'bg-amber-500',   dot: 'bg-amber-500', href: '/admin/validasi?tab=validasi' },
            { label: 'Ditolak',  n: ditolak,  bar: 'bg-rose-500',    dot: 'bg-rose-500', href: '/admin/validasi?tab=ditolak' },
        ];
        if (lainnya > 0) {
            parts.push({ label: 'Diproses', n: lainnya, bar: 'bg-slate-300', dot: 'bg-slate-300', href: '/admin/riwayat?status=DIPROSES' });
        }

        document.getElementById('ratioBar').innerHTML = total > 0
            ? parts.filter(p => p.n > 0).map(p => `<div class="${p.bar}" style="width:${(p.n / total) * 100}%" title="${p.label}: ${p.n}"></div>`).join('')
            : '';

        document.getElementById('ratioLegend').innerHTML = parts.map(p => `
            <a href="${p.href || 'javascript:void(0)'}" class="flex items-center justify-between text-xs p-1.5 rounded-lg hover:bg-slate-50 transition -mx-1.5 group">
                <span class="flex items-center gap-1.5 text-slate-600 group-hover:text-slate-900"><span class="w-2 h-2 rounded-full ${p.dot}"></span>${p.label}</span>
                <span class="font-bold text-slate-900 tabular-nums">${p.n} <span class="text-[10px] text-slate-400 font-normal">(${pct(p.n, total)}%) ↗</span></span>
            </a>`).join('');

        document.getElementById('statPersenValid').innerText = `${summary.persentase_valid ?? pct(valid, total)}% valid`;
    }

    function renderKategori(list, total) {
        const container = document.getElementById('kategoriList');
        if (!list || list.length === 0) {
            container.innerHTML = '<p class="text-xs text-slate-400 text-center py-2">Belum ada data kategori.</p>';
            return;
        }
        const sorted = [...list].sort((a, b) => (parseInt(b.total_laporan, 10) || 0) - (parseInt(a.total_laporan, 10) || 0));
        container.innerHTML = sorted.map(cat => {
            const count = parseInt(cat.total_laporan, 10) || 0;
            const percent = pct(count, total);
            return `
                <div class="space-y-1">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-slate-700 truncate pr-2">${esc(cat.nama_kategori)}</span>
                        <span class="font-bold text-slate-900 shrink-0 tabular-nums">${count} <span class="text-[10px] text-slate-400 font-normal">(${percent}%)</span></span>
                    </div>
                    <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-brand-600 rounded-full transition-all duration-500" style="width:${percent}%"></div>
                    </div>
                </div>`;
        }).join('');
    }

    function renderSegmen() {
        const container = document.getElementById('segmenList');
        const toggle = document.getElementById('btnToggleSegmen');

        if (!segmenData || segmenData.length === 0) {
            container.innerHTML = '<p class="text-xs text-slate-400 text-center py-2">Belum ada data segmen.</p>';
            toggle.classList.add('hidden');
            return;
        }

        const rows = showAllSegmen ? segmenData : segmenData.slice(0, SEGMEN_PREVIEW);
        container.innerHTML = rows.map(seg => {
            const score = parseFloat(seg.skor_kelayakan) || 0;
            let badge = 'bg-emerald-50 text-emerald-700 border-emerald-200';
            let label = 'Baik';
            if (score < 40) {
                badge = 'bg-rose-50 text-rose-700 border-rose-200';
                label = 'Kritis';
            } else if (score < 70) {
                badge = 'bg-amber-50 text-amber-700 border-amber-200';
                label = 'Cukup';
            }
            return `
                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-slate-800 truncate">${esc(seg.nama_jalan)}</p>
                        <p class="text-[10px] text-slate-400">Panjang ${Math.round(seg.panjang_m)} m</p>
                    </div>
                    <span class="shrink-0 px-2 py-0.5 rounded-md text-[10px] font-extrabold border ${badge}">${score.toFixed(1)} · ${label}</span>
                </div>`;
        }).join('');

        if (segmenData.length > SEGMEN_PREVIEW) {
            toggle.classList.remove('hidden');
            toggle.innerText = showAllSegmen ? 'Tampilkan lebih sedikit' : `Tampilkan semua (${segmenData.length})`;
        } else {
            toggle.classList.add('hidden');
        }
    }

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

            const data = (await res.json()).data;
            const s = data.summary;
            const sedang = s.sedang_diperbaiki ?? 0;
            const selesai = s.sudah_diperbaiki ?? 0;
            const belum = s.belum_ditangani ?? 0;

            document.getElementById('statTotalLaporan').innerText = s.total_laporan;
            document.getElementById('statMenunggu').innerText = s.menunggu_validasi;
            document.getElementById('statSedang').innerText = sedang;
            document.getElementById('statSelesai').innerText = selesai;
            document.getElementById('statSelesaiNote').innerText = `${pct(selesai, s.tervalidasi)}% dari laporan valid`;
            document.getElementById('shortcutValidasiCount').innerText = s.menunggu_validasi;
            document.getElementById('shortcutPerbaikanCount').innerText = belum;
            document.getElementById('statRataRataSkor').innerText = s.rata_rata_skor_jalan;

            renderRatio(s);
            renderKategori(data.kategori, s.total_laporan);
            segmenData = data.segmen || [];
            renderSegmen();

            document.getElementById('lastUpdated').innerText =
                'Diperbarui ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
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