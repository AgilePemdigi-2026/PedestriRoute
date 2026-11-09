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
                <p class="text-xs font-bold text-slate-800" id="reportAddress">-</p>
                <p class="text-xs text-slate-600 mt-1 leading-relaxed" id="reportDesc">-</p>
                <div id="existingCatatanContainer" class="hidden mt-2.5 p-2.5 bg-emerald-50/70 border border-emerald-100 rounded-xl">
                    <span class="text-[10px] font-bold text-emerald-800 block mb-0.5">Catatan Perbaikan:</span>
                    <p class="text-xs text-slate-700 leading-relaxed" id="existingCatatanText">-</p>
                </div>
                <div id="aiValidationCard" class="hidden mt-2.5 p-2.5 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-emerald-500 text-white flex items-center justify-center text-[10px] font-bold">✓</span>
                        <div>
                            <span class="font-bold text-emerald-950 block text-[11px]">Validasi Otomatis Computer Vision</span>
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

    function formatTime(isoStr) {
        if (!isoStr) return '-';
        const d = new Date(isoStr);
        return d.toLocaleDateString('id-ID', { 
            day: 'numeric', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
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
                    if (aiPill) aiPill.innerText = reportData.cv_label || 'RUSAK';
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
                        <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                        <span class="text-sm font-extrabold text-emerald-800">Sudah Diperbaiki</span>
                    </div>
                    <span class="text-xs text-slate-400">${formatTime(reportData.tanggal_perbaikan)}</span>
                `;
                // Disable form because final
                document.getElementById('btnSubmitRepair').classList.add('hidden');
                document.getElementById('disabledNotice').classList.remove('hidden');
                document.querySelectorAll('input[name="repairStatus"]').forEach(i => i.disabled = true);
            } else if (cur === 'SEDANG_DIPERBAIKI') {
                curStatusContainer.innerHTML = `
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-sky-500"></span>
                        <span class="text-sm font-extrabold text-sky-800">Sedang Diperbaiki</span>
                    </div>
                    <span class="px-2 py-0.5 bg-sky-100 text-sky-800 rounded-md text-[10px] font-bold">Aktif</span>
                `;
                const rSudah = document.querySelector('input[value="SUDAH_DIPERBAIKI"]');
                if (rSudah) rSudah.checked = true;
            } else {
                curStatusContainer.innerHTML = `
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                        <span class="text-sm font-extrabold text-amber-800">Belum Ditangani</span>
                    </div>
                    <span class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded-md text-[10px] font-bold">Menunggu Aksi</span>
                `;
                const rSedang = document.querySelector('input[value="SEDANG_DIPERBAIKI"]');
                if (rSedang) rSedang.checked = true;
            }

            updateFormLabels();

            // Render Timeline
            const tlContainer = document.getElementById('timelineContainer');
            if (reportData.riwayat && reportData.riwayat.length > 0) {
                tlContainer.innerHTML = reportData.riwayat.map((rw, idx) => `
                    <div class="flex items-start gap-3 relative">
                        <div class="w-7 h-7 rounded-full bg-teal-50 border border-brand-200 flex items-center justify-center text-brand-600 flex-shrink-0 z-10">
                            <span class="text-xs font-bold">${idx + 1}</span>
                        </div>
                        <div class="flex-1 pt-0.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-800">${rw.status}</span>
                                <span class="text-[10px] text-slate-400">${formatTime(rw.waktu)}</span>
                            </div>
                            <p class="text-[11px] text-slate-600 mt-0.5">${rw.catatan || '-'}</p>
                            <span class="text-[10px] text-slate-400 mt-1 block">Oleh: ${rw.admin_nama || 'Sistem'}</span>
                        </div>
                    </div>
                `).join('');
            } else {
                tlContainer.innerHTML = `<p class="text-xs text-slate-400 text-center py-2">Belum ada catatan riwayat status.</p>`;
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
        if (!lbl || !hlp) return;

        if (val === 'SEDANG_DIPERBAIKI') {
            lbl.innerText = 'Foto Bukti Pengerjaan (Opsional)';
            hlp.innerText = 'Unggah foto aktivitas penanganan di lapangan.';
        } else {
            lbl.innerText = 'Foto Bukti Selesai (Opsional)';
            hlp.innerText = 'Unggah foto fasilitas setelah perbaikan rampung.';
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
