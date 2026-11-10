@extends('layouts.app')

@section('title', 'Detail Status Perbaikan - PedestriRoute Admin')

@section('content')
<div class="px-5 pt-6 pb-24 flex flex-col flex-1">
    <!-- Header with Back Button -->
    <div class="flex items-center gap-2.5 mb-4">
        <a href="/admin/status-laporan" class="w-8 h-8 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 transition shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <div class="flex items-center gap-1.5">
                <span id="reportCode" class="text-sm font-extrabold text-slate-800 tracking-tight tabular-nums">#LAP-....</span>
            </div>
            <p class="text-[10px] text-slate-500 font-semibold tracking-wide mt-0.5">Status Perbaikan</p>
        </div>
    </div>

    <!-- Loading State -->
    <div id="loadingState" class="text-center py-12">
        <svg class="w-8 h-8 mx-auto mb-2 animate-spin text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        <p class="text-xs text-slate-400">Memuat detail laporan...</p>
    </div>

    <!-- Main Content Container (hidden until loaded) -->
    <div id="mainContent" class="hidden space-y-3">
        <!-- Photo Preview Card -->
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm overflow-hidden">
            <div id="photoGrid" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 mb-3">
                <div class="flex flex-col" id="reportPhotoCol">
                    <div class="h-5 flex items-center mb-1.5">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider truncate">Foto Aduan</span>
                    </div>
                    <div id="reportPhotoWrapper" class="w-full h-44 bg-slate-100 rounded-2xl overflow-hidden relative border border-slate-200/80 shadow-inner">
                        <img id="reportPhoto" src="" alt="Bukti Laporan" class="w-full h-full object-cover">
                        <div class="absolute top-2.5 left-2.5 px-2 py-0.5 bg-black/60 backdrop-blur-md rounded-md text-white text-[9px] font-bold" id="badgeCategory">
                            Kategori
                        </div>
                    </div>
                </div>
                <div id="repairPhotoCol" class="hidden flex-col">
                    <div class="h-5 flex items-center mb-1.5">
                        <span id="repairPhotoTitle" class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider truncate">Foto Sesudah</span>
                    </div>
                    <div id="repairPhotoWrapper" class="w-full h-44 bg-emerald-50 rounded-2xl overflow-hidden relative border border-emerald-200 shadow-inner">
                        <img id="repairPhoto" src="" alt="Bukti Selesai Perbaikan" class="w-full h-full object-cover">
                        <div id="repairPhotoBadge" class="absolute top-2.5 left-2.5 px-2 py-0.5 bg-emerald-600/90 backdrop-blur-md rounded-md text-white text-[9px] font-bold">
                            ✓ Selesai
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <div class="flex items-start gap-1.5 mb-1.5">
                    <svg class="w-4 h-4 text-brand-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    <p class="text-xs font-bold text-slate-800 leading-snug" id="reportAddress">-</p>
                </div>
                <p class="text-xs text-slate-600 bg-slate-50 border border-slate-100 rounded-xl p-2.5 leading-relaxed" id="reportDesc">-</p>
                
                <div id="existingCatatanContainer" class="hidden mt-2.5 p-3 bg-slate-50 border border-slate-200/80 rounded-xl">
                    <div class="flex items-center gap-1.5 mb-1 text-slate-500">
                        <svg class="w-3.5 h-3.5 text-brand-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-600">Catatan Perbaikan Terakhir</span>
                    </div>
                    <p class="text-xs text-slate-700 leading-relaxed font-normal" id="existingCatatanText">-</p>
                </div>

                <div id="aiValidationCard" class="hidden mt-2.5 p-2.5 bg-emerald-50/70 border border-emerald-200/80 rounded-xl flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-emerald-500 text-white flex items-center justify-center text-[10px] font-bold">✓</span>
                        <div>
                            <span class="font-bold text-emerald-950 block text-[11px]">Validasi Otomatis Machine Learning (YOLOv8)</span>
                            <span id="aiValidationSubtitle" class="text-[10px] text-emerald-700">Skor keyakinan: -</span>
                        </div>
                    </div>
                    <span id="aiLabelPill" class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800">
                        RUSAK
                    </span>
                </div>
            </div>
        </div>

        <!-- Current Status Banner -->
        <div class="bg-white rounded-2xl p-3.5 border border-slate-100 shadow-sm flex items-center justify-between">
            <span class="text-[10px] font-medium text-slate-400">Status Saat Ini</span>
            <div id="currentStatusContainer" class="flex items-center gap-2">
                <!-- Injected via JS -->
            </div>
        </div>

        <!-- Repair Action Form -->
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm" id="formCard">
            <h3 class="text-xs font-bold text-slate-800 mb-3">Perbarui Status</h3>

            <div class="space-y-2 mb-4">
                <!-- Option 1: Sedang Diperbaiki -->
                <label id="labelSedang" class="block p-3 rounded-xl border border-slate-200 cursor-pointer hover:border-brand-500 transition relative">
                    <div class="flex items-start gap-2.5">
                        <input type="radio" name="repairStatus" value="SEDANG_DIPERBAIKI" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-bold text-slate-800">Sedang Diperbaiki</span>
                                <span class="px-1.5 py-0.2 bg-sky-50 text-sky-700 text-[10px] font-semibold rounded">Proses</span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">Petugas sedang menangani perbaikan di lokasi.</p>
                        </div>
                    </div>
                </label>

                <!-- Option 2: Sudah Diperbaiki -->
                <label id="labelSudah" class="block p-3 rounded-xl border border-slate-200 cursor-pointer hover:border-brand-500 transition relative">
                    <div class="flex items-start gap-2.5">
                        <input type="radio" name="repairStatus" value="SUDAH_DIPERBAIKI" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                        <div>
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-bold text-slate-800">Sudah Diperbaiki</span>
                                <span class="px-1.5 py-0.2 bg-emerald-50 text-emerald-700 text-[10px] font-semibold rounded">Selesai</span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">Fasilitas sudah pulih dan dapat digunakan kembali.</p>
                        </div>
                    </div>
                </label>
            </div>

            <!-- Catatan Resmi Perbaikan -->
            <div class="mb-3" id="catatanInputGroup">
                <label class="block text-xs font-semibold text-slate-700 mb-1">Catatan (Opsional)</label>
                <textarea id="inputCatatanPerbaikan" rows="2" placeholder="Tuliskan catatan perbaikan..."
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition"></textarea>
            </div>

            <!-- Upload Foto Bukti Perbaikan -->
            <div class="mb-4" id="fotoInputGroup">
                <label id="labelFotoPerbaikan" class="block text-xs font-semibold text-slate-700 mb-1">Foto Bukti (Opsional)</label>
                <input type="file" id="inputFotoPerbaikan" accept="image/jpeg,image/png"
                    class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-teal-50 file:text-brand-700 hover:file:bg-teal-100 cursor-pointer">
                <p id="helpFotoPerbaikan" class="text-[10px] text-slate-400 mt-1">Unggah dokumentasi hasil perbaikan lapangan.</p>
            </div>

            <div id="disabledNotice" class="hidden mb-3 p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-center">
                <p class="text-xs font-semibold text-slate-600">Perbaikan telah selesai dan status sudah final.</p>
            </div>

            <button type="button" id="btnSubmitRepair" class="w-full py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl text-xs transition shadow-sm flex items-center justify-center gap-2">
                <span>Simpan Status</span>
            </button>
        </div>

        <!-- Riwayat Perjalanan Status Timeline -->
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm">
            <h3 class="text-xs font-bold text-slate-800 mb-3">Riwayat Penanganan</h3>
            <div id="timelineContainer" class="space-y-3">
                <!-- Timeline items injected via JS -->
            </div>
        </div>
    </div>
</div>

@include('partials.admin-bottom-nav')
@endsection

@push('scripts')
<script>
    const reportId = {{ $id }};
    const token = localStorage.getItem('token');
    const role = localStorage.getItem('role');

    if (!token || role !== 'admin') {
        window.location.href = '/login';
    }

    let reportData = null;

    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[c]));
    }

    function formatTime(isoStr) {
        if (!isoStr) return '-';
        const d = new Date(isoStr);
        return d.toLocaleDateString('id-ID', { 
            day: 'numeric', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    }

    function formatStatusProgress(status) {
        if (!status) return {
            label: 'Status Tidak Diketahui',
            badgeCls: 'bg-slate-50 text-slate-700 border-slate-200',
            dotCls: 'bg-slate-400'
        };

        const map = {
            'SEDANG_DIPERBAIKI': {
                label: 'Sedang Diperbaiki',
                badgeCls: 'bg-sky-50 text-sky-700 border-sky-200/80',
                dotCls: 'bg-sky-500'
            },
            'SUDAH_DIPERBAIKI': {
                label: 'Sudah Diperbaiki',
                badgeCls: 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
                dotCls: 'bg-emerald-500'
            },
            'BELUM_DITANGANI': {
                label: 'Belum Ditangani',
                badgeCls: 'bg-amber-50 text-amber-700 border-amber-200/80',
                dotCls: 'bg-amber-500'
            },
            'TERVALIDASI': {
                label: 'Tervalidasi',
                badgeCls: 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
                dotCls: 'bg-emerald-500'
            },
            'MENUNGGU_VALIDASI_ADMIN': {
                label: 'Menunggu Validasi Admin',
                badgeCls: 'bg-amber-50 text-amber-700 border-amber-200/80',
                dotCls: 'bg-amber-500'
            },
            'DIPROSES': {
                label: 'Dianalisis AI',
                badgeCls: 'bg-sky-50 text-sky-700 border-sky-200/80',
                dotCls: 'bg-sky-500'
            },
            'DITOLAK': {
                label: 'Ditolak',
                badgeCls: 'bg-rose-50 text-rose-700 border-rose-200/80',
                dotCls: 'bg-rose-500'
            }
        };

        if (map[status]) return map[status];

        // Format any uppercase or snake_case status into Title Case
        const formatted = String(status)
            .toLowerCase()
            .split('_')
            .map(w => w.charAt(0).toUpperCase() + w.slice(1))
            .join(' ');

        return {
            label: formatted,
            badgeCls: 'bg-slate-50 text-slate-700 border-slate-200',
            dotCls: 'bg-slate-400'
        };
    }

    async function loadReportDetail() {
        try {
            const res = await fetch(`/api/v1/reports/${reportId}`, {
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
                showToast('Laporan tidak ditemukan', 'error');
                setTimeout(() => window.location.href = '/admin/status-laporan', 1500);
                return;
            }

            const json = await res.json();
            reportData = json.data;

            // Populate view
            document.getElementById('reportCode').innerText = `#LAP-${String(reportData.laporan_id).padStart(4, '0')}`;
            document.getElementById('badgeCategory').innerText = reportData.kategori ? reportData.kategori.nama : 'Laporan';
            document.getElementById('reportAddress').innerText = (reportData.lokasi && reportData.lokasi.alamat) ? reportData.lokasi.alamat : 'Surabaya';
            document.getElementById('reportDesc').innerText = reportData.deskripsi || '-';

            if (reportData.foto_url) {
                document.getElementById('reportPhoto').src = reportData.foto_url;
            } else {
                document.getElementById('reportPhoto').src = 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=600&auto=format&fit=crop&q=80';
            }

            if (reportData.confidence_score !== null && reportData.confidence_score !== undefined) {
                const aiCard = document.getElementById('aiValidationCard');
                const aiSub = document.getElementById('aiValidationSubtitle');
                const aiPill = document.getElementById('aiLabelPill');
                if (aiCard) {
                    aiCard.classList.remove('hidden');
                    const pct = Math.round(reportData.confidence_score * 100);
                    if (aiSub) aiSub.innerText = `Skor keyakinan model YOLOv8: ${pct}%`;
                    const lbl = (reportData.cv_label || 'RUSAK').toUpperCase();
                    if (aiPill) {
                        if (lbl === 'TERHALANG') {
                            aiPill.innerText = 'TERHALANG (Parkir Liar)';
                            aiPill.className = 'text-[10px] font-bold px-2 py-0.5 rounded-md bg-amber-100 text-amber-800';
                        } else if (lbl === 'BAIK') {
                            aiPill.innerText = 'BAIK (Normal)';
                            aiPill.className = 'text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800';
                        } else if (lbl === 'SPAM') {
                            aiPill.innerText = 'SPAM';
                            aiPill.className = 'text-[10px] font-bold px-2 py-0.5 rounded-md bg-rose-100 text-rose-800';
                        } else {
                            aiPill.innerText = 'RUSAK (Cacat Fisik)';
                            aiPill.className = 'text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800';
                        }
                    }
                }
            }

            // Current repair status rendering
            const curStatusContainer = document.getElementById('currentStatusContainer');
            const cur = reportData.status_perbaikan;

            // Proof photo & note rendering
            const photoGrid = document.getElementById('photoGrid');
            const repairPhotoCol = document.getElementById('repairPhotoCol');
            const reportPhotoWrapper = document.getElementById('reportPhotoWrapper');

            if (reportData.foto_perbaikan_url) {
                document.getElementById('repairPhoto').src = reportData.foto_perbaikan_url;
                repairPhotoCol.classList.remove('hidden');
                repairPhotoCol.classList.add('flex');
                if (photoGrid) {
                    photoGrid.className = 'grid grid-cols-2 gap-2.5 mb-3';
                }
                if (reportPhotoWrapper) {
                    reportPhotoWrapper.className = 'w-full h-44 bg-slate-100 rounded-2xl overflow-hidden relative border border-slate-200/80 shadow-inner';
                }

                const photoTitle = document.getElementById('repairPhotoTitle');
                const photoBadge = document.getElementById('repairPhotoBadge');
                const photoWrapper = document.getElementById('repairPhotoWrapper');
                if (cur === 'SEDANG_DIPERBAIKI') {
                    if (photoTitle) {
                        photoTitle.innerText = 'Foto Pengerjaan';
                        photoTitle.className = 'text-[10px] font-bold text-sky-700 uppercase tracking-wider truncate';
                    }
                    if (photoBadge) {
                        photoBadge.innerText = '⚡ Sedang Dikerjakan';
                        photoBadge.className = 'absolute top-2.5 left-2.5 px-2 py-0.5 bg-sky-600/90 backdrop-blur-md rounded-md text-white text-[9px] font-bold';
                    }
                    if (photoWrapper) {
                        photoWrapper.className = 'w-full h-44 bg-sky-50 rounded-2xl overflow-hidden relative border border-sky-200 shadow-inner';
                    }
                } else {
                    if (photoTitle) {
                        photoTitle.innerText = 'Foto Sesudah';
                        photoTitle.className = 'text-[10px] font-bold text-emerald-700 uppercase tracking-wider truncate';
                    }
                    if (photoBadge) {
                        photoBadge.innerText = '✓ Terverifikasi Selesai';
                        photoBadge.className = 'absolute top-2.5 left-2.5 px-2 py-0.5 bg-emerald-600/90 backdrop-blur-md rounded-md text-white text-[9px] font-bold';
                    }
                    if (photoWrapper) {
                        photoWrapper.className = 'w-full h-44 bg-emerald-50 rounded-2xl overflow-hidden relative border border-emerald-200 shadow-inner';
                    }
                }
            } else {
                repairPhotoCol.classList.add('hidden');
                repairPhotoCol.classList.remove('flex');
                if (photoGrid) {
                    photoGrid.className = 'grid grid-cols-1 gap-2.5 mb-3';
                }
                if (reportPhotoWrapper) {
                    reportPhotoWrapper.className = 'w-full h-52 bg-slate-100 rounded-2xl overflow-hidden relative border border-slate-200/80 shadow-inner';
                }
            }

            if (reportData.catatan_perbaikan) {
                document.getElementById('existingCatatanText').innerText = reportData.catatan_perbaikan;
                document.getElementById('existingCatatanContainer').classList.remove('hidden');
            } else {
                document.getElementById('existingCatatanContainer').classList.add('hidden');
            }

            if (cur === 'SUDAH_DIPERBAIKI') {
                curStatusContainer.innerHTML = `
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <span class="text-sm font-extrabold text-emerald-800">Sudah Diperbaiki</span>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200/80 rounded-full text-[10.5px] font-semibold whitespace-nowrap shadow-2xs">
                        <svg class="w-3 h-3 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span>Selesai</span>
                    </span>
                `;
                // Disable form because final
                document.getElementById('btnSubmitRepair').classList.add('hidden');
                document.getElementById('disabledNotice').classList.remove('hidden');
                document.querySelectorAll('input[name="repairStatus"]').forEach(i => i.disabled = true);
            } else if (cur === 'SEDANG_DIPERBAIKI') {
                curStatusContainer.innerHTML = `
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-sky-500"></span>
                        <span class="text-sm font-extrabold text-sky-800">Sedang Diperbaiki</span>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 bg-sky-50 text-sky-700 border border-sky-200/80 rounded-full text-[10.5px] font-semibold whitespace-nowrap shadow-2xs">
                        <svg class="w-3 h-3 text-sky-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                        <span>Proses</span>
                    </span>
                `;
                const rSudah = document.querySelector('input[value="SUDAH_DIPERBAIKI"]');
                if (rSudah) rSudah.checked = true;
            } else {
                curStatusContainer.innerHTML = `
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                        <span class="text-sm font-extrabold text-amber-800">Belum Ditangani</span>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 bg-amber-50 text-amber-700 border border-amber-200/80 rounded-full text-[10.5px] font-semibold whitespace-nowrap shadow-2xs">
                        <svg class="w-3 h-3 text-amber-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span>Menunggu</span>
                    </span>
                `;
                const rSedang = document.querySelector('input[value="SEDANG_DIPERBAIKI"]');
                if (rSedang) rSedang.checked = true;
            }

            updateFormLabels();

            // Render Timeline
            const tlContainer = document.getElementById('timelineContainer');
            if (reportData.riwayat && reportData.riwayat.length > 0) {
                const total = reportData.riwayat.length;
                tlContainer.innerHTML = reportData.riwayat.map((rw, idx) => {
                    const meta = formatStatusProgress(rw.status);
                    const isLast = idx === total - 1;
                    const jenisLabel = rw.jenis === 'PERBAIKAN' ? 'Perbaikan' : (rw.jenis === 'VALIDASI' ? 'Validasi' : 'Sistem');
                    return `
                    <div class="flex items-start gap-3 relative ${!isLast ? 'pb-4' : ''}">
                        ${!isLast ? `<span class="absolute left-3.5 top-7 bottom-0 w-0.5 -ml-[1px] bg-slate-200"></span>` : ''}
                        <div class="w-7 h-7 rounded-full bg-white border border-slate-200 shadow-xs flex items-center justify-center flex-shrink-0 z-10">
                            <span class="w-2.5 h-2.5 rounded-full ${meta.dotCls}"></span>
                        </div>
                        <div class="flex-1 pt-0.5 min-w-0">
                            <div class="flex items-center justify-between gap-2 flex-wrap mb-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-xs font-bold text-slate-800">${esc(meta.label)}</span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold border ${meta.badgeCls}">
                                        ${esc(jenisLabel)}
                                    </span>
                                </div>
                                <span class="text-[10px] text-slate-400 shrink-0 font-medium">${formatTime(rw.waktu)}</span>
                            </div>
                            ${rw.catatan ? `<p class="text-xs text-slate-600 bg-slate-50/80 border border-slate-100 rounded-xl p-2.5 leading-relaxed mb-1.5">${esc(rw.catatan)}</p>` : ''}
                            <div class="flex items-center gap-1.5 text-[10px] text-slate-400">
                                <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <span>Oleh: <strong class="text-slate-600 font-semibold">${esc(rw.admin_nama || 'Sistem')}</strong></span>
                            </div>
                        </div>
                    </div>`;
                }).join('');
            } else {
                tlContainer.innerHTML = `<p class="text-xs text-slate-400 text-center py-4">Belum ada catatan riwayat status.</p>`;
            }

            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('mainContent').classList.remove('hidden');
        } catch (e) {
            showToast('Gagal memuat detail laporan', 'error');
        }
    }

    function updateFormLabels() {
        const selected = document.querySelector('input[name="repairStatus"]:checked');
        const val = selected ? selected.value : '';
        const lbl = document.getElementById('labelFotoPerbaikan');
        const hlp = document.getElementById('helpFotoPerbaikan');
        if (lbl && hlp) {
            if (val === 'SEDANG_DIPERBAIKI') {
                lbl.innerText = 'Foto Bukti Pengerjaan (Opsional)';
                hlp.innerText = 'Unggah foto aktivitas penanganan di lapangan.';
            } else {
                lbl.innerText = 'Foto Bukti Selesai (Opsional)';
                hlp.innerText = 'Unggah foto fasilitas setelah perbaikan rampung.';
            }
        }

        const labelSedang = document.getElementById('labelSedang');
        const labelSudah = document.getElementById('labelSudah');
        if (labelSedang && labelSudah) {
            if (val === 'SEDANG_DIPERBAIKI') {
                labelSedang.className = 'block p-3 rounded-xl border-2 border-sky-500 bg-sky-50/20 cursor-pointer transition relative';
                labelSudah.className = 'block p-3 rounded-xl border border-slate-200 cursor-pointer hover:border-brand-500 transition relative';
            } else if (val === 'SUDAH_DIPERBAIKI') {
                labelSedang.className = 'block p-3 rounded-xl border border-slate-200 cursor-pointer hover:border-brand-500 transition relative';
                labelSudah.className = 'block p-3 rounded-xl border-2 border-emerald-500 bg-emerald-50/20 cursor-pointer transition relative';
            } else {
                labelSedang.className = 'block p-3 rounded-xl border border-slate-200 cursor-pointer hover:border-brand-500 transition relative';
                labelSudah.className = 'block p-3 rounded-xl border border-slate-200 cursor-pointer hover:border-brand-500 transition relative';
            }
        }
    }

    document.querySelectorAll('input[name="repairStatus"]').forEach(radio => {
        radio.addEventListener('change', updateFormLabels);
    });

    // Submit repair status
    document.getElementById('btnSubmitRepair').addEventListener('click', async () => {
        const selected = document.querySelector('input[name="repairStatus"]:checked');
        if (!selected) {
            showToast('Mohon pilih status perbaikan terlebih dahulu', 'error');
            return;
        }

        const newStatus = selected.value;
        const btn = document.getElementById('btnSubmitRepair');
        btn.disabled = true;
        btn.innerHTML = `<svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg><span>Menyimpan...</span>`;

        const formData = new FormData();
        formData.append('status_perbaikan', newStatus);
        const catatanVal = document.getElementById('inputCatatanPerbaikan') ? document.getElementById('inputCatatanPerbaikan').value.trim() : '';
        if (catatanVal) formData.append('catatan', catatanVal);
        const fotoInput = document.getElementById('inputFotoPerbaikan');
        if (fotoInput && fotoInput.files[0]) {
            formData.append('foto_perbaikan', fotoInput.files[0]);
        }

        try {
            const res = await fetch(`/api/v1/admin/reports/${reportId}/repair-status`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await res.json();

            if (!res.ok) {
                const errMsg = (data.error && data.error.message) ? data.error.message : 'Pembaruan status gagal disimpan. Silakan coba lagi.';
                showToast(errMsg, 'error');
                btn.disabled = false;
                btn.innerHTML = '<span>Simpan Status</span>';
                return;
            }

            showToast(data.message || 'Notifikasi Status Berhasil Diperbarui', 'success');
            setTimeout(() => {
                loadReportDetail();
            }, 800);
        } catch (err) {
            showToast('Pembaruan status gagal disimpan. Silakan coba lagi.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<span>Simpan Status</span>';
        }
    });

    loadReportDetail();
</script>
@endpush
