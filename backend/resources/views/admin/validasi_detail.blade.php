@extends('layouts.app')

@section('title', 'Validasi Laporan - PedestriRoute Admin')

@section('content')
<div class="px-5 pt-5 pb-20 flex flex-col flex-1">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-sm font-extrabold text-slate-900 leading-tight">PedestriRoute</h1>
            <p class="text-[10px] text-teal-800 font-semibold tracking-wider">ADMIN</p>
        </div>
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
            </div>
            <div class="w-8 h-8 rounded-full overflow-hidden border border-slate-200">
                <img src="https://ui-avatars.com/api/?name=Admin&background=006257&color=fff" class="w-full h-full object-cover">
            </div>
        </div>
    </div>

    <!-- Antrean Header Card -->
    <div class="bg-teal-50 border border-teal-100 rounded-2xl p-3.5 mb-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-teal-800 text-white flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
            </div>
            <div>
                <h2 class="text-xs font-bold text-slate-900">Antrean Validasi</h2>
                <p class="text-[10px] text-teal-800 font-medium">Laporan Menunggu Verifikasi</p>
            </div>
        </div>
        <span class="px-2.5 py-1 bg-white text-teal-800 text-[10px] font-extrabold rounded-full border border-teal-200">
            • Review Aktif
        </span>
    </div>

    <!-- Meta row -->
    <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-2">
            <span id="kodeLaporan" class="text-xs font-mono font-bold text-teal-800 bg-teal-50 px-2 py-0.5 rounded-lg border border-teal-200">
                #LAP-{{ $id }}
            </span>
            <span class="text-[11px] text-slate-500 flex items-center gap-1">
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span id="timeAgo">Baru saja</span>
            </span>
        </div>
        <span id="categoryBadge" class="text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-100 px-2.5 py-0.5 rounded-full">
            Trotoar
        </span>
    </div>

    <!-- Location Address -->
    <p class="text-xs font-semibold text-slate-700 flex items-center gap-1.5 mb-3">
        <svg class="w-4 h-4 text-brand-600 shrink-0" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
        </svg>
        <span id="reportAddress" class="truncate">Memuat alamat lokasi...</span>
    </p>

    <!-- Photo Container -->
    <div class="w-full h-56 bg-slate-100 rounded-2xl relative overflow-hidden mb-3 border border-slate-200 shadow-sm">
        <img id="reportPhoto" src="" class="w-full h-full object-cover hidden" alt="Foto Laporan" />
        <div id="photoSkeleton" class="w-full h-full flex items-center justify-center text-xs text-slate-400">
            Memuat foto...
        </div>

        <span class="absolute top-2.5 left-2.5 px-2.5 py-1 bg-black/60 backdrop-blur-sm text-white text-[10px] font-bold rounded-lg flex items-center gap-1">
            📷 Foto Warga
        </span>

        <button type="button" id="btnZoomPhoto"
            class="absolute bottom-2.5 right-2.5 px-3 py-1.5 bg-white/90 hover:bg-white text-slate-800 text-xs font-bold rounded-xl shadow flex items-center gap-1.5 transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7" />
            </svg>
            <span>Perbesar</span>
        </button>
    </div>

    <!-- Deskripsi Pelapor Card -->
    <div class="bg-teal-50/50 border border-teal-100 rounded-2xl p-3 mb-3">
        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800 mb-1">
            <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
            </svg>
            <span>Deskripsi Pelapor</span>
        </div>
        <p id="reportDesc" class="text-xs text-slate-700 leading-relaxed italic">
            "Memuat deskripsi..."
        </p>
    </div>

    <!-- Computer Vision Analysis Section -->
    <div class="bg-white rounded-2xl p-3.5 border border-slate-100 shadow-sm mb-4">
        <div class="flex items-center justify-between mb-2.5">
            <div class="flex items-center gap-1.5">
                <span class="text-base">🤖</span>
                <span class="text-xs font-bold text-slate-800">Analisis Computer Vision</span>
            </div>
            <span id="cvBadge" class="text-[10px] font-bold text-amber-800 bg-amber-50 border border-amber-100 px-2 py-0.5 rounded-full">
                Review Manual (Sprint 1)
            </span>
        </div>

        <div class="grid grid-cols-2 gap-2 mb-2.5">
            <div class="bg-slate-50 border border-slate-100 rounded-xl p-2.5">
                <p class="text-[10px] text-slate-500 font-semibold mb-0.5">Skor Validitas</p>
                <p id="cvScoreText" class="text-sm font-extrabold text-slate-800">Manual / N/A</p>
                <div class="w-full bg-slate-200 h-1.5 rounded-full mt-1.5 overflow-hidden">
                    <div id="cvProgressBar" class="bg-brand-600 h-full rounded-full" style="width: 0%"></div>
                </div>
            </div>
            <div class="bg-slate-50 border border-slate-100 rounded-xl p-2.5">
                <p class="text-[10px] text-slate-500 font-semibold mb-0.5">Prediksi Status</p>
                <p id="cvLabelText" class="text-xs font-bold text-slate-700">Verifikasi Visual</p>
                <p class="text-[10px] text-slate-400 truncate mt-1">Oleh Admin Dishub</p>
            </div>
        </div>

        <!-- System Callout Note (FR-F5-06) -->
        <div class="bg-sky-50 border border-sky-100 rounded-xl p-2.5 flex items-start gap-2">
            <svg class="w-4 h-4 text-sky-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-[10px] text-sky-900 leading-snug font-medium">
                Catatan Sistem: Analisis otomatis tidak tersedia atau memerlukan validasi manual sesuai regulasi Sprint 1. Admin menentukan keputusan akhir.
            </p>
        </div>
    </div>

    <!-- Admin Decision Box -->
    <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm mb-4">
        <h3 class="text-xs font-extrabold text-slate-900 mb-0.5">Keputusan Admin</h3>
        <p class="text-[11px] text-slate-500 mb-3">Tentukan status verifikasi laporan untuk pembaruan data navigasi publik.</p>

        <!-- Decision Radio Cards -->
        <div class="grid grid-cols-2 gap-3 mb-3">
            <button type="button" id="cardValid"
                class="decision-card p-3 rounded-2xl border-2 border-slate-200 bg-slate-50 hover:bg-slate-100 text-center transition flex flex-col items-center gap-1">
                <div class="w-8 h-8 rounded-full bg-teal-100 text-teal-800 flex items-center justify-center font-bold">
                    ✓
                </div>
                <span class="text-xs font-extrabold text-slate-800">Valid</span>
                <span class="text-[10px] text-slate-500">Terima Laporan</span>
            </button>

            <button type="button" id="cardTidakValid"
                class="decision-card p-3 rounded-2xl border-2 border-slate-200 bg-slate-50 hover:bg-slate-100 text-center transition flex flex-col items-center gap-1">
                <div class="w-8 h-8 rounded-full bg-rose-100 text-rose-800 flex items-center justify-center font-bold">
                    ✕
                </div>
                <span class="text-xs font-extrabold text-slate-800">Tidak Valid</span>
                <span class="text-[10px] text-slate-500">Tolak Laporan</span>
            </button>
        </div>

        <!-- Rejection Reason Container (Mandatory if Tidak Valid) -->
        <div id="rejectionContainer" class="hidden mb-3">
            <label class="block text-xs font-bold text-rose-700 mb-1">
                Alasan Penolakan <span class="text-rose-500">*Wajib diisi</span>
            </label>
            <textarea id="alasanPenolakan" rows="2"
                placeholder="Tulis alasan spesifik (misal: Foto tidak menunjukkan kerusakan fasilitas pejalan kaki)..."
                class="w-full p-2.5 bg-rose-50/50 border border-rose-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-rose-500 transition"></textarea>
        </div>

        <!-- Action Buttons -->
        <button type="button" id="btnSubmitDecision"
            class="w-full py-3 bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold rounded-xl flex items-center justify-center gap-2 shadow-md shadow-teal-900/10 transition mb-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            <span>Simpan Keputusan</span>
        </button>

        <a href="/admin/validasi"
            class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl flex items-center justify-center gap-1.5 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span>Kembali</span>
        </a>
    </div>
</div>

<!-- Lightbox Modal for Photo Zoom -->
<div id="photoLightbox" class="fixed inset-0 z-50 bg-black/90 flex flex-col items-center justify-center p-4 hidden">
    <button type="button" id="btnCloseLightbox" class="absolute top-4 right-4 text-white p-2 text-2xl font-bold">
        ✕
    </button>
    <img id="lightboxImg" src="" class="max-w-full max-h-[85vh] rounded-xl object-contain shadow-2xl" />
</div>
@endsection

@push('scripts')
<script>
    const reportId = {{ $id }};
    const token = localStorage.getItem('token');
    const role = localStorage.getItem('role');

    if (!token || role !== 'admin') {
        window.location.href = '/login';
    }

    let selectedDecision = null; // 'VALID' or 'TIDAK_VALID'
    let currentPhotoUrl = '';

    const cardValid = document.getElementById('cardValid');
    const cardTidakValid = document.getElementById('cardTidakValid');
    const rejectionContainer = document.getElementById('rejectionContainer');
    const alasanPenolakanInput = document.getElementById('alasanPenolakan');

    cardValid.addEventListener('click', () => {
        selectedDecision = 'VALID';
        cardValid.className = 'decision-card p-3 rounded-2xl border-2 border-teal-600 bg-teal-50 text-center transition flex flex-col items-center gap-1 shadow-sm';
        cardTidakValid.className = 'decision-card p-3 rounded-2xl border-2 border-slate-200 bg-slate-50 hover:bg-slate-100 text-center transition flex flex-col items-center gap-1';
        rejectionContainer.classList.add('hidden');
    });

    cardTidakValid.addEventListener('click', () => {
        selectedDecision = 'TIDAK_VALID';
        cardTidakValid.className = 'decision-card p-3 rounded-2xl border-2 border-rose-600 bg-rose-50 text-center transition flex flex-col items-center gap-1 shadow-sm';
        cardValid.className = 'decision-card p-3 rounded-2xl border-2 border-slate-200 bg-slate-50 hover:bg-slate-100 text-center transition flex flex-col items-center gap-1';
        rejectionContainer.classList.remove('hidden');
        alasanPenolakanInput.focus();
    });

    // Lightbox handlers
    const btnZoomPhoto = document.getElementById('btnZoomPhoto');
    const photoLightbox = document.getElementById('photoLightbox');
    const lightboxImg = document.getElementById('lightboxImg');
    const btnCloseLightbox = document.getElementById('btnCloseLightbox');

    btnZoomPhoto.addEventListener('click', () => {
        if (currentPhotoUrl) {
            lightboxImg.src = currentPhotoUrl;
            photoLightbox.classList.remove('hidden');
        }
    });

    btnCloseLightbox.addEventListener('click', () => {
        photoLightbox.classList.add('hidden');
    });

    photoLightbox.addEventListener('click', (e) => {
        if (e.target === photoLightbox) {
            photoLightbox.classList.add('hidden');
        }
    });

    // Load Report Detail
    async function loadReportDetail() {
        try {
            const res = await fetch(`/api/v1/reports/${reportId}`, {
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json'
                }
            });

            if (!res.ok) {
                showToast('Laporan tidak ditemukan atau Anda tidak memiliki akses.', 'error');
                setTimeout(() => window.location.href = '/admin/validasi', 1000);
                return;
            }

            const json = await res.json();
            const report = json.data;

            document.getElementById('kodeLaporan').innerText = `#LAP-2024-${String(report.laporan_id).padStart(3, '0')}`;
            document.getElementById('categoryBadge').innerText = report.kategori.nama;
            document.getElementById('reportAddress').innerText = report.lokasi.alamat || report.lokasi.nama_jalan || 'Surabaya';
            document.getElementById('reportDesc').innerText = `"${report.deskripsi}"`;

            if (report.foto_url) {
                currentPhotoUrl = report.foto_url;
                const photoImg = document.getElementById('reportPhoto');
                photoImg.src = report.foto_url;
                photoImg.onload = () => {
                    photoImg.classList.remove('hidden');
                    document.getElementById('photoSkeleton').classList.add('hidden');
                };
            }

            if (report.confidence_score) {
                const percent = Math.round(report.confidence_score * 100);
                document.getElementById('cvScoreText').innerText = `${percent}% / 100%`;
                document.getElementById('cvProgressBar').style.width = `${percent}%`;
            }
            if (report.cv_label) {
                document.getElementById('cvLabelText').innerText = report.cv_label;
            }
        } catch (e) {
            showToast('Gagal memuat detail laporan', 'error');
        }
    }

    loadReportDetail();

    // Submit Decision
    document.getElementById('btnSubmitDecision').addEventListener('click', async () => {
        if (!selectedDecision) {
            showToast('Silakan pilih keputusan: Valid atau Tidak Valid.', 'error');
            return;
        }

        const alasan = alasanPenolakanInput.value.trim();
        if (selectedDecision === 'TIDAK_VALID' && !alasan) {
            // FR-F5-05: Pesan persis Katalog Pesan
            showToast('Alasan penolakan harus diisi sebelum status laporan diperbarui', 'error');
            return;
        }

        const btn = document.getElementById('btnSubmitDecision');
        btn.disabled = true;
        btn.classList.add('opacity-70');

        try {
            const res = await fetch(`/api/v1/admin/reports/${reportId}/validation`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    keputusan: selectedDecision,
                    alasan_penolakan: alasan
                })
            });

            const resData = await res.json();

            if (!res.ok) {
                const message = resData.error ? resData.error.message : 'Gagal menyimpan keputusan.';
                showToast(message, 'error');
                btn.disabled = false;
                btn.classList.remove('opacity-70');
                return;
            }

            showToast('Keputusan validasi berhasil disimpan', 'success');
            setTimeout(() => {
                window.location.href = '/admin/validasi';
            }, 800);
        } catch (e) {
            showToast('Terjadi kesalahan koneksi', 'error');
            btn.disabled = false;
            btn.classList.remove('opacity-70');
        }
    });
</script>
@endpush
