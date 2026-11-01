@extends('layouts.app')

@section('title', 'Detail Status Perbaikan - PedestriRoute Admin')

@section('content')
<div class="px-5 pt-6 pb-24 flex flex-col flex-1">
    <!-- Header with Back Button -->
    <div class="flex items-center gap-3 mb-4">
        <a href="/admin/status-laporan" class="w-9 h-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div>
            <h1 class="text-base font-extrabold text-slate-900 leading-tight" id="reportCode">#LAP-....</h1>
            <p class="text-[10px] text-teal-800 font-semibold tracking-wider">PEMBARUAN STATUS PERBAIKAN</p>
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
    <div id="mainContent" class="hidden space-y-4">
        <!-- Photo Preview Card -->
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm overflow-hidden">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                <div>
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Foto Sebelum (Aduan Warga)</span>
                    <div class="w-full h-44 bg-slate-100 rounded-xl overflow-hidden relative shadow-inner">
                        <img id="reportPhoto" src="" alt="Bukti Laporan" class="w-full h-full object-cover">
                        <div class="absolute top-2 left-2 px-2 py-0.5 bg-black/60 backdrop-blur-md rounded-md text-white text-[9px] font-bold" id="badgeCategory">
                            Kategori
                        </div>
                    </div>
                </div>
                <div id="repairPhotoCol" class="hidden">
                    <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider block mb-1">Foto Sesudah (Bukti Perbaikan)</span>
                    <div class="w-full h-44 bg-emerald-50 rounded-xl overflow-hidden relative border border-emerald-200 shadow-inner">
                        <img id="repairPhoto" src="" alt="Bukti Selesai Perbaikan" class="w-full h-full object-cover">
                        <div class="absolute top-2 left-2 px-2 py-0.5 bg-emerald-600/90 backdrop-blur-md rounded-md text-white text-[9px] font-bold">
                            ✓ Terverifikasi Selesai
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-800" id="reportAddress">-</p>
                <p class="text-[11px] text-slate-500 mt-1 leading-relaxed" id="reportDesc">-</p>
                <div id="existingCatatanContainer" class="hidden mt-2 p-2.5 bg-emerald-50 border border-emerald-100 rounded-xl">
                    <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider block mb-0.5">Catatan Resmi Perbaikan:</span>
                    <p class="text-xs text-slate-700 font-medium leading-relaxed" id="existingCatatanText">-</p>
                </div>
            </div>
        </div>

        <!-- Current Status Banner -->
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Status Perbaikan Saat Ini</span>
            <div class="flex items-center justify-between" id="currentStatusContainer">
                <!-- Injected via JS -->
            </div>
        </div>

        <!-- Repair Action Form -->
        <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm" id="formCard">
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3">Tindakan Pembaruan Perbaikan</h3>

            <div class="space-y-3 mb-5">
                <!-- Option 1: Sedang Diperbaiki -->
                <label id="labelSedang" class="block p-3.5 rounded-xl border border-slate-200 cursor-pointer hover:border-brand-500 transition relative">
                    <div class="flex items-start gap-3">
                        <input type="radio" name="repairStatus" value="SEDANG_DIPERBAIKI" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-slate-800">Sedang Diperbaiki</span>
                                <span class="px-2 py-0.5 bg-sky-50 text-sky-700 text-[10px] font-bold rounded-md">Proses Lapangan</span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">Petugas sedang melakukan penanganan fisik di lokasi. Skor jalur tetap dipantau.</p>
                        </div>
                    </div>
                </label>

                <!-- Option 2: Sudah Diperbaiki -->
                <label id="labelSudah" class="block p-3.5 rounded-xl border border-slate-200 cursor-pointer hover:border-brand-500 transition relative">
                    <div class="flex items-start gap-3">
                        <input type="radio" name="repairStatus" value="SUDAH_DIPERBAIKI" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-slate-800">Sudah Diperbaiki</span>
                                <span class="px-2 py-0.5 bg-emerald-50 text-emerald-700 text-[10px] font-bold rounded-md">Pemulihan Skor</span>
                            </div>
                            <p class="text-[11px] text-slate-500 mt-0.5">Fasilitas telah pulih sempurna. Laporan ditutup dan skor kelayakan segmen WSM dipulihkan.</p>
                        </div>
                    </div>
                </label>
            </div>

            <!-- Catatan Resmi Perbaikan -->
            <div class="mb-3.5" id="catatanInputGroup">
                <label class="block text-xs font-bold text-slate-700 mb-1">Catatan Resmi Perbaikan (Opsional)</label>
                <textarea id="inputCatatanPerbaikan" rows="2" placeholder="Contoh: Paving amblas telah diganti dan diratakan semen oleh Satgas..."
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition"></textarea>
            </div>

            <!-- Upload Foto Bukti Perbaikan Selesai -->
            <div class="mb-5" id="fotoInputGroup">
                <label class="block text-xs font-bold text-slate-700 mb-1">Foto Bukti Hasil Perbaikan (Opsional)</label>
                <input type="file" id="inputFotoPerbaikan" accept="image/jpeg,image/png"
                    class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-teal-50 file:text-brand-700 hover:file:bg-teal-100 cursor-pointer">
                <p class="text-[10px] text-slate-400 mt-1">Unggah foto fasilitas setelah selesai dikerjakan sebagai bukti publik transparan.</p>
            </div>

            <div id="disabledNotice" class="hidden mb-4 p-3 bg-slate-50 border border-slate-200 rounded-xl text-center">
                <p class="text-xs font-semibold text-slate-600">Laporan ini telah selesai diperbaiki (status final) dan tidak dapat diubah lagi.</p>
            </div>

            <button type="button" id="btnSubmitRepair" class="w-full py-3 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl text-xs transition shadow-md shadow-teal-900/10 flex items-center justify-center gap-2">
                <span>Simpan Status Perbaikan</span>
            </button>
        </div>

        <!-- Riwayat Perjalanan Status Timeline -->
        <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-4">Riwayat Perjalanan Status</h3>
            <div id="timelineContainer" class="space-y-4">
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

            // Proof photo & note rendering
            if (reportData.foto_perbaikan_url) {
                document.getElementById('repairPhoto').src = reportData.foto_perbaikan_url;
                document.getElementById('repairPhotoCol').classList.remove('hidden');
            } else {
                document.getElementById('repairPhotoCol').classList.add('hidden');
            }

            if (reportData.catatan_perbaikan) {
                document.getElementById('existingCatatanText').innerText = reportData.catatan_perbaikan;
                document.getElementById('existingCatatanContainer').classList.remove('hidden');
            } else {
                document.getElementById('existingCatatanContainer').classList.add('hidden');
            }

            // Current repair status rendering
            const curStatusContainer = document.getElementById('currentStatusContainer');
            const cur = reportData.status_perbaikan;

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
                btn.innerHTML = '<span>Simpan Status Perbaikan</span>';
                return;
            }

            showToast(data.message || 'Notifikasi Status Berhasil Diperbarui', 'success');
            setTimeout(() => {
                loadReportDetail();
            }, 800);
        } catch (err) {
            showToast('Pembaruan status gagal disimpan. Silakan coba lagi.', 'error');
            btn.disabled = false;
            btn.innerHTML = '<span>Simpan Status Perbaikan</span>';
        }
    });

    loadReportDetail();
</script>
@endpush
