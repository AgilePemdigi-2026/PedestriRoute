@extends('layouts.app')

@section('title', 'Validasi Laporan - PedestriRoute Admin')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #reportMap { height: 240px; width: 100%; z-index: 0; }
</style>
@endpush

@section('content')
<div class="px-5 pt-5 pb-20 flex flex-col flex-1">
    <!-- Header with Back Button -->
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2.5">
            <a href="/admin/validasi" class="w-8 h-8 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-50 transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-1.5">
                    <span id="kodeLaporan" class="text-sm font-extrabold text-slate-800 tracking-tight tabular-nums">
                        #LAP-{{ str_pad($id, 4, '0', STR_PAD_LEFT) }}
                    </span>
                </div>
                <div class="flex items-center gap-1.5 text-[11px] text-slate-400 mt-1">
                    <span id="timeAgo">Baru saja</span>
                    <span>•</span>
                    <span id="categoryBadge" class="font-semibold text-slate-600">Trotoar</span>
                </div>
            </div>
        </div>
        <div>
            <span id="headerStatusBadge" class="px-2.5 py-1 bg-amber-50 text-amber-700 text-[10px] font-bold rounded-full border border-amber-200/60">
                Menunggu
            </span>
            <span id="headerTitle" class="hidden">Antrean Validasi</span>
            <span id="headerSubtitle" class="hidden">Menunggu Verifikasi</span>
        </div>
    </div>

    <!-- Photo Container -->
    <div class="w-full h-52 bg-slate-100 rounded-2xl relative overflow-hidden mb-3 border border-slate-200 shadow-sm">
        <img id="reportPhoto" src="" class="w-full h-full object-cover hidden" alt="Foto Laporan" />
        <div id="photoSkeleton" class="w-full h-full flex items-center justify-center text-xs text-slate-400">
            Memuat foto...
        </div>

        <button type="button" id="btnZoomPhoto"
            class="absolute bottom-2.5 right-2.5 px-3 py-1.5 bg-black/60 hover:bg-black/75 backdrop-blur-sm text-white text-xs font-bold rounded-xl shadow flex items-center gap-1.5 transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7" />
            </svg>
            <span>Perbesar</span>
        </button>
    </div>

    <!-- Info & Deskripsi Card -->
    <div class="bg-white rounded-2xl p-3.5 border border-slate-100 shadow-sm mb-3">
        <div class="flex items-start gap-1.5 mb-2">
            <svg class="w-4 h-4 text-brand-600 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
            </svg>
            <p id="reportAddress" class="text-xs font-semibold text-slate-800 leading-snug">
                Memuat lokasi...
            </p>
        </div>
        <p id="reportDesc" class="text-xs text-slate-600 bg-slate-50 rounded-xl p-2.5 leading-relaxed">
            "Memuat deskripsi..."
        </p>
    </div>

    <!-- Peta Lokasi Card -->
    <section class="bg-white rounded-2xl border border-slate-100 shadow-sm mb-3 overflow-hidden" aria-labelledby="reportMapTitle">
        <div class="px-3.5 py-2.5 flex items-center justify-between border-b border-slate-50">
            <h3 id="reportMapTitle" class="text-xs font-bold text-slate-800">Peta Lokasi</h3>
            <span id="reportCoordinates" class="text-[10px] text-slate-400 font-mono">Memuat koordinat...</span>
        </div>
        <div id="reportMap" class="hidden" aria-label="Peta titik laporan dan segmen jalan"></div>
        <p id="reportMapStatus" role="status" class="p-3 text-xs text-slate-400">Memuat peta...</p>
        <div class="p-2.5 bg-slate-50/80 border-t border-slate-100 flex items-center justify-between gap-2">
            <p id="reportSnapInfo" class="text-[11px] text-slate-600 truncate" role="status">Memuat segmen...</p>
            <div class="flex items-center gap-1.5 shrink-0">
                <a id="externalMapsLink" target="_blank" rel="noopener noreferrer" class="hidden px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-700 text-[10px] font-bold hover:bg-slate-50">Maps ↗</a>
                <a id="streetViewLink" target="_blank" rel="noopener noreferrer" class="hidden px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-700 text-[10px] font-bold hover:bg-slate-50">Street View ↗</a>
            </div>
        </div>
    </section>

    <!-- Machine Learning Verification Section -->
    <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm mb-3">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 rounded-lg bg-teal-50 border border-teal-200 text-brand-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-robot"></i>
                </span>
                <div>
                    <h3 class="text-xs font-extrabold text-slate-800">Verifikasi Machine Learning (YOLOv8)</h3>
                    <p class="text-[10px] text-slate-400">Protokol Evaluasi & Klasifikasi Kerusakan</p>
                </div>
            </div>
            <div class="flex items-center gap-1.5">
                <span id="cvBadge" class="text-[10px] font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-full border border-slate-200">
                    Memuat...
                </span>
                <button type="button" id="btnTriggerMl" onclick="triggerMlAnalysis()" title="Jalankan / Ulangi analisis Machine Learning"
                    class="px-2 py-1 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 hover:text-brand-600 text-[10px] font-bold rounded-lg transition inline-flex items-center gap-1 shadow-2xs cursor-pointer">
                    <i class="fa-solid fa-arrows-rotate text-[10px]" id="iconTriggerMl"></i>
                    <span id="textTriggerMl">Jalankan ML</span>
                </button>
            </div>
        </div>

        <!-- Prominent Condition Card (Same format as user page: RUSAK / BAIK / TERHALANG / SPAM) -->
        <div id="conditionCard" class="rounded-xl p-3 border mb-3 bg-slate-50 border-slate-200 text-slate-800 transition">
            <div class="flex items-center justify-between gap-2 mb-1">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Hasil Deteksi Kondisi</span>
                <span id="conditionBadge" class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-extrabold uppercase tracking-wide bg-slate-200 text-slate-800 border border-slate-300">
                    MEMUAT...
                </span>
            </div>
            <h4 id="conditionTitle" class="text-xs font-bold text-slate-900 mb-1">Memeriksa kondisi foto...</h4>
            <p id="conditionDesc" class="text-[11px] text-slate-600 leading-snug">Menghubungkan ke layanan Machine Learning...</p>
        </div>

        <!-- ML Confidence Metric -->
        <div class="pt-2 border-t border-slate-100 space-y-1.5">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Tingkat Keyakinan ML (YOLOv8)</span>
                    <span id="confidenceBadge" class="inline-block mt-0.5 px-2 py-0.2 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                        Belum tersedia
                    </span>
                </div>
                <span id="confidencePercent" class="text-lg font-extrabold text-slate-800 tabular-nums">—</span>
            </div>
            <div id="confidenceTrack" class="w-full bg-slate-100 h-2 rounded-full overflow-hidden border border-slate-200/60">
                <div id="confidenceBar" class="bg-brand-600 h-full rounded-full transition-all duration-500" style="width: 0%"></div>
            </div>
            <p id="confidenceHint" class="text-[10.5px] text-slate-500 leading-relaxed">
                Tingkat keyakinan model Machine Learning terhadap hasil klasifikasi kondisi.
            </p>
        </div>

        <!-- Dynamic Quick Action Suggestion from ML -->
        <div id="mlQuickActionContainer" class="hidden mt-3 pt-2.5 border-t border-slate-100 flex flex-wrap items-center gap-2">
            <!-- Injected via JavaScript -->
        </div>
    </div>

    <!-- Admin Decision Box -->
    <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm mb-4">
        <h3 class="text-xs font-extrabold text-slate-900 mb-2">Keputusan Validasi</h3>

        <!-- Decision Radio Cards -->
        <div class="grid grid-cols-2 gap-2.5 mb-3">
            <button type="button" id="cardValid"
                class="decision-card p-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-center transition flex items-center justify-center gap-2">
                <span class="w-5 h-5 rounded-full bg-teal-100 text-teal-800 flex items-center justify-center text-xs font-bold">✓</span>
                <span class="text-xs font-bold text-slate-800">Valid</span>
            </button>

            <button type="button" id="cardTidakValid"
                class="decision-card p-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-center transition flex items-center justify-center gap-2">
                <span class="w-5 h-5 rounded-full bg-rose-100 text-rose-800 flex items-center justify-center text-xs font-bold">✕</span>
                <span class="text-xs font-bold text-slate-800">Tidak Valid</span>
            </button>
        </div>

        <!-- Status History Banner (if already validated/rejected) -->
        <div id="statusHistoryBanner" class="hidden mb-3 rounded-xl p-3 text-xs leading-relaxed font-medium"></div>

        <!-- Rejection Reason Container (Mandatory if Tidak Valid) -->
        <div id="rejectionContainer" class="hidden mb-3">
            <label class="block text-xs font-bold text-rose-700 mb-1">
                Alasan Penolakan <span id="rejectionRequiredBadge" class="text-rose-500 text-[10px] font-normal">*Wajib</span>
            </label>
            <textarea id="alasanPenolakan" rows="2"
                placeholder="Contoh: Foto tidak menunjukkan kerusakan fasilitas..."
                class="w-full p-2.5 bg-rose-50/50 border border-rose-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-rose-500 transition"></textarea>
        </div>

        <!-- Action Buttons -->
        <button type="button" disabled id="btnDisabledDecision"
            class="hidden w-full py-2.5 text-white text-xs font-bold rounded-xl flex items-center justify-center gap-2 mb-2 cursor-not-allowed shadow-sm"></button>

        <button type="button" id="btnSubmitDecision"
            class="w-full py-2.5 bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold rounded-xl flex items-center justify-center gap-2 shadow-sm transition mb-2">
            <span>Simpan Keputusan</span>
        </button>

        <a href="/admin/validasi"
            class="w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-xl flex items-center justify-center gap-1 transition">
            <span>Kembali ke Antrean</span>
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

@include('partials.admin-bottom-nav')
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
        cardValid.className = 'decision-card p-3 rounded-xl border-2 border-brand-600 bg-teal-50 text-center transition flex items-center justify-center gap-2 shadow-sm';
        cardTidakValid.className = 'decision-card p-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-center transition flex items-center justify-center gap-2';
        rejectionContainer.classList.add('hidden');
    });

    cardTidakValid.addEventListener('click', () => {
        selectedDecision = 'TIDAK_VALID';
        cardTidakValid.className = 'decision-card p-3 rounded-xl border-2 border-rose-600 bg-rose-50 text-center transition flex items-center justify-center gap-2 shadow-sm';
        cardValid.className = 'decision-card p-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-center transition flex items-center justify-center gap-2';
        rejectionContainer.classList.remove('hidden');
        alasanPenolakanInput.focus();
    });

    window.quickRejectNormal = function() {
        cardTidakValid.click();
        alasanPenolakanInput.value = 'Berdasarkan foto yang dilampirkan, fasilitas dalam kondisi baik / tidak ditemukan indikasi kerusakan.';
        cardTidakValid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    window.quickRejectSpam = function() {
        cardTidakValid.click();
        alasanPenolakanInput.value = 'Foto yang diunggah tidak memuat fasilitas pejalan kaki publik (terdeteksi spam / non-infrastruktur).';
        cardTidakValid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    window.quickApproveRusak = function() {
        cardValid.click();
        cardValid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Keputusan Valid dipilih sesuai hasil deteksi kerusakan ML.', 'info');
    };

    window.quickApproveTerhalang = function() {
        cardValid.click();
        cardValid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Keputusan Valid dipilih sesuai hasil deteksi halangan ML.', 'info');
    };

    // Lightbox handlers
    const btnZoomPhoto = document.getElementById('btnZoomPhoto');
    const photoLightbox = document.getElementById('photoLightbox');
    const lightboxImg = document.getElementById('lightboxImg');
    const btnCloseLightbox = document.getElementById('btnCloseLightbox');

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

    let reportMap = null;

    function renderReportMap(location) {
        const status = document.getElementById('reportMapStatus');
        const coordinates = document.getElementById('reportCoordinates');
        const snapInfo = document.getElementById('reportSnapInfo');
        const mapElement = document.getElementById('reportMap');
        const mapsLink = document.getElementById('externalMapsLink');
        const streetLink = document.getElementById('streetViewLink');
        if (reportMap) { reportMap.remove(); reportMap = null; }
        mapElement.classList.add('hidden');
        status.classList.remove('hidden');
        for (const link of [mapsLink, streetLink]) {
            link.removeAttribute('href');
            link.classList.add('hidden');
        }
        const numeric = value => (typeof value === 'number' ||
            (typeof value === 'string' && value.trim() !== '')) && Number.isFinite(Number(value));
        if (!numeric(location?.latitude) || !numeric(location?.longitude) ||
            Math.abs(Number(location.latitude)) > 90 || Math.abs(Number(location.longitude)) > 180) {
            coordinates.textContent = 'Koordinat tidak tersedia';
            status.textContent = 'Peta tidak dapat ditampilkan karena koordinat laporan tidak valid.';
            snapInfo.textContent = 'Informasi segmen tidak tersedia.';
            return;
        }
        const lat = Number(location.latitude);
        const lng = Number(location.longitude);
        const coordinatePair = `${lat},${lng}`;
        coordinates.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        mapsLink.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(coordinatePair)}`;
        streetLink.href = `https://www.google.com/maps/@?api=1&map_action=pano&viewpoint=${encodeURIComponent(coordinatePair)}`;
        mapsLink.classList.remove('hidden');
        streetLink.classList.remove('hidden');
        const segment = location.segmen;
        snapInfo.textContent = segment
            ? (numeric(segment.jarak_snapping_m) && Number(segment.jarak_snapping_m) >= 0
                ? `Tersnap ${Number(segment.jarak_snapping_m).toLocaleString('id-ID', {minimumFractionDigits: 1, maximumFractionDigits: 1})} m ke ${segment.nama_jalan || 'segmen jalan'}`
                : `Segmen: ${segment.nama_jalan || 'jalan tanpa nama'}`)
            : 'Laporan belum memiliki data segmen jalan.';
        if (typeof L === 'undefined') {
            status.textContent = 'Peta gagal dimuat. Gunakan tombol Google Maps untuk melihat lokasi.';
            return;
        }
        try {
            mapElement.classList.remove('hidden');
            reportMap = L.map(mapElement, {scrollWheelZoom: false}).setView([lat, lng], 18);
            const tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(reportMap);
            status.classList.add('hidden');
            tiles.on('tileerror', () => {
                status.textContent = 'Sebagian peta dasar gagal dimuat. Gunakan Google Maps bila diperlukan.';
                status.classList.remove('hidden');
            });
            L.marker([lat, lng]).addTo(reportMap).bindPopup('Titik laporan warga');
            requestAnimationFrame(() => reportMap?.invalidateSize());
        } catch (error) {
            if (reportMap) { reportMap.remove(); reportMap = null; }
            mapElement.classList.add('hidden');
            status.textContent = 'Peta gagal ditampilkan. Gunakan tombol Google Maps untuk melihat lokasi.';
            status.classList.remove('hidden');
        }
    }

    const conditionConfig = {
        RUSAK: {
            badge: 'RUSAK',
            badgeClass: 'bg-rose-50 text-rose-800 border border-rose-200',
            cardClass: 'bg-rose-50/70 border-rose-200 text-rose-950',
            title: 'Kondisi: Terdeteksi Rusak',
            desc: 'Model Machine Learning mendeteksi adanya kerusakan fisik pada fasilitas pejalan kaki ini.',
            hint: 'Tingkat keyakinan model terhadap indikasi kerusakan fisik fasilitas pejalan kaki.',
        },
        BAIK: {
            badge: 'BAIK (NORMAL)',
            badgeClass: 'bg-emerald-50 text-emerald-800 border border-emerald-200',
            cardClass: 'bg-emerald-50/70 border-emerald-200 text-emerald-950',
            title: 'Kondisi: Terdeteksi Baik (Normal)',
            desc: 'Model Machine Learning mengklasifikasikan fasilitas dalam kondisi baik atau tidak ditemukan kerusakan yang signifikan.',
            hint: 'Fasilitas terindikasi dalam kondisi normal. Petugas disarankan memeriksa kepastian laporan.',
        },
        TERHALANG: {
            badge: 'TERHALANG',
            badgeClass: 'bg-amber-50 text-amber-800 border border-amber-200',
            cardClass: 'bg-amber-50/70 border-amber-200 text-amber-950',
            title: 'Kondisi: Terdeteksi Terhalang',
            desc: 'Model Machine Learning mendeteksi fasilitas terhalang oleh parkir liar, PKL, atau hambatan lainnya.',
            hint: 'Tingkat keyakinan model terhadap indikasi halangan pada jalur pejalan kaki.',
        },
        SPAM: {
            badge: 'TIDAK RELEVAN / SPAM',
            badgeClass: 'bg-rose-100 text-rose-900 border border-rose-300',
            cardClass: 'bg-rose-50/70 border-rose-200 text-rose-950',
            title: 'Kondisi: Bukan Fasilitas / Tidak Relevan',
            desc: 'Model Machine Learning mengindikasikan foto bukan merupakan fasilitas pejalan kaki yang valid.',
            hint: 'Foto terindikasi tidak relevan. Petugas disarankan menolak laporan.',
        }
    };

    let detailPollTimer = null;
    let detailPollCount = 0;
    function checkDetailPolling(status) {
        if (status === 'DIPROSES') {
            if (!detailPollTimer) {
                detailPollCount = 0;
                detailPollTimer = setInterval(async () => {
                    detailPollCount++;
                    if (detailPollCount >= 15) {
                        clearInterval(detailPollTimer);
                        detailPollTimer = null;
                        return;
                    }
                    await loadReportDetail();
                }, 3000);
            }
        } else {
            if (detailPollTimer) {
                clearInterval(detailPollTimer);
                detailPollTimer = null;
            }
        }
    }

    async function triggerMlAnalysis() {
        const btn = document.getElementById('btnTriggerMl');
        const icon = document.getElementById('iconTriggerMl');
        const text = document.getElementById('textTriggerMl');
        if (btn) btn.disabled = true;
        if (icon) icon.classList.add('fa-spin');
        if (text) text.innerText = 'Memproses...';

        try {
            const res = await fetch(`/api/v1/admin/reports/${reportId}/analyze-cv`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });

            if (!res.ok) {
                const errData = await res.json().catch(() => ({}));
                showToast(errData.error?.message || 'Gagal menjalankan analisis Machine Learning.', 'error');
                return;
            }

            const json = await res.json();
            showToast('Analisis Machine Learning berhasil dijalankan!', 'success');
            await loadReportDetail();
        } catch (e) {
            showToast('Gagal terhubung ke microservice Machine Learning.', 'error');
        } finally {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('fa-spin');
            if (text) text.innerText = 'Analisis Ulang';
        }
    }

    function renderMlSection(report) {
        const condCard = document.getElementById('conditionCard');
        const condBadge = document.getElementById('conditionBadge');
        const condTitle = document.getElementById('conditionTitle');
        const condDesc = document.getElementById('conditionDesc');
        const cvBadge = document.getElementById('cvBadge');
        const confBadge = document.getElementById('confidenceBadge');
        const confPercent = document.getElementById('confidencePercent');
        const confBar = document.getElementById('confidenceBar');
        const confHint = document.getElementById('confidenceHint');
        const quickContainer = document.getElementById('mlQuickActionContainer');
        const textTrigger = document.getElementById('textTriggerMl');

        const hasScore = report.confidence_score !== null && report.confidence_score !== undefined &&
            report.confidence_score !== '' && Number.isFinite(Number(report.confidence_score));
        const score = hasScore ? Number(report.confidence_score) : null;
        const percent = hasScore ? Math.round(score * 100) : null;
        const label = report.cv_label ? String(report.cv_label).toUpperCase() : null;
        const isProcessing = report.status === 'DIPROSES';

        if (textTrigger) {
            textTrigger.innerText = hasScore ? 'Analisis Ulang' : 'Jalankan ML';
        }

        if (isProcessing) {
            cvBadge.innerText = 'Sedang Dianalisis';
            cvBadge.className = 'text-[10px] font-bold text-sky-700 bg-sky-50 border border-sky-200 px-2 py-0.5 rounded-full animate-pulse';
            condCard.className = 'rounded-xl p-3 border mb-3 bg-sky-50/70 border-sky-200 text-sky-950';
            condBadge.innerText = 'PROSES ML';
            condBadge.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-extrabold uppercase tracking-wide bg-sky-100 text-sky-800 border border-sky-200';
            condTitle.innerText = 'Foto Sedang Dianalisis Model YOLOv8...';
            condDesc.innerText = 'Evaluasi microservice Computer Vision sedang berjalan. Halaman akan memeriksa status secara berkala.';
            confBadge.innerText = 'Sedang Berjalan';
            confBadge.className = 'inline-block mt-0.5 px-2 py-0.2 rounded-full text-[10px] font-bold bg-sky-100 text-sky-700 border border-sky-200';
            confPercent.innerText = '...';
            confBar.style.width = '60%';
            confBar.className = 'bg-sky-500 h-full rounded-full animate-pulse';
            confHint.innerText = 'Mengevaluasi segmentasi dan probabilitas fitur visual pejalan kaki...';
            if (quickContainer) quickContainer.classList.add('hidden');
            return;
        }

        if (hasScore && label && conditionConfig[label]) {
            const cfg = conditionConfig[label];
            condCard.className = `rounded-xl p-3 border mb-3 ${cfg.cardClass}`;
            condBadge.innerText = cfg.badge;
            condBadge.className = `inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-extrabold uppercase tracking-wide ${cfg.badgeClass}`;
            condTitle.innerText = cfg.title;
            condDesc.innerText = cfg.desc;
            confHint.innerText = cfg.hint;

            let levelText = 'Keyakinan Rendah (<60%)';
            let levelBadgeCls = 'bg-slate-100 text-slate-700 border border-slate-200';
            let barColor = 'bg-slate-400';

            if (score >= 0.80) {
                levelText = 'Sangat Yakin (≥80%)';
                levelBadgeCls = 'bg-emerald-50 text-emerald-800 border border-emerald-200';
                barColor = 'bg-brand-600';
            } else if (score >= 0.60) {
                levelText = 'Cukup Yakin (60%-79%)';
                levelBadgeCls = 'bg-amber-50 text-amber-800 border border-amber-200';
                barColor = 'bg-amber-500';
            }

            cvBadge.innerText = percent >= 80 ? '✓ Terverifikasi ML' : 'Tinjauan ML';
            cvBadge.className = `text-[10px] font-bold ${percent >= 80 ? 'text-emerald-800 bg-emerald-50 border border-emerald-200' : 'text-amber-800 bg-amber-50 border border-amber-200'} px-2 py-0.5 rounded-full`;

            confBadge.innerText = levelText;
            confBadge.className = `inline-block mt-0.5 px-2 py-0.2 rounded-full text-[10px] font-bold ${levelBadgeCls}`;
            confPercent.innerText = `${percent}%`;
            confBar.style.width = `${percent}%`;
            confBar.className = `${barColor} h-full rounded-full transition-all duration-500`;

            // Quick actions if pending review
            if (quickContainer) {
                if (report.status === 'MENUNGGU_VALIDASI_ADMIN') {
                    quickContainer.classList.remove('hidden');
                    let actionHtml = `<span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block w-full mb-1">Rekomendasi Keputusan Admin:</span>`;
                    if (label === 'SPAM') {
                        actionHtml += `
                            <button type="button" onclick="quickRejectSpam()" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-xs transition inline-flex items-center gap-1.5 cursor-pointer">
                                <i class="fa-solid fa-ban"></i> Tolak Cepat (Foto SPAM)
                            </button>
                        `;
                    } else if (label === 'BAIK') {
                        actionHtml += `
                            <button type="button" onclick="quickRejectNormal()" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-xs transition inline-flex items-center gap-1.5 cursor-pointer">
                                <i class="fa-solid fa-ban"></i> Tolak Cepat (Kondisi Normal)
                            </button>
                        `;
                    } else if (label === 'TERHALANG') {
                        actionHtml += `
                            <button type="button" onclick="quickApproveTerhalang()" class="px-3 py-1.5 bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs rounded-xl shadow-xs transition inline-flex items-center gap-1.5 cursor-pointer">
                                <i class="fa-solid fa-check"></i> Setujui Laporan (Halangan Terverifikasi)
                            </button>
                        `;
                    } else { // RUSAK
                        actionHtml += `
                            <button type="button" onclick="quickApproveRusak()" class="px-3 py-1.5 bg-brand-600 hover:bg-brand-700 text-white font-bold text-xs rounded-xl shadow-xs transition inline-flex items-center gap-1.5 cursor-pointer">
                                <i class="fa-solid fa-check"></i> Setujui Laporan (Kerusakan Terverifikasi)
                            </button>
                        `;
                    }
                    quickContainer.innerHTML = actionHtml;
                } else {
                    quickContainer.classList.add('hidden');
                }
            }
        } else {
            // No ML analysis yet
            cvBadge.innerText = 'Tinjauan Manual';
            cvBadge.className = 'text-[10px] font-bold text-slate-600 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-full';
            condCard.className = 'rounded-xl p-3 border mb-3 bg-slate-50 border-slate-200 text-slate-700';
            condBadge.innerText = 'BELUM DIANALISIS';
            condBadge.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-extrabold uppercase tracking-wide bg-slate-200 text-slate-700 border border-slate-300';
            condTitle.innerText = 'Analisis otomatis belum dijalankan';
            condDesc.innerText = 'Analisis otomatis tidak tersedia. Silakan validasi laporan secara manual atau klik tombol "Jalankan ML" di atas.';
            confBadge.innerText = 'Belum tersedia';
            confBadge.className = 'inline-block mt-0.5 px-2 py-0.2 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200';
            confPercent.innerText = '—';
            confBar.style.width = '0%';
            confHint.innerText = 'Analisis otomatis tidak tersedia. Silakan validasi laporan secara manual.';
            if (quickContainer) quickContainer.classList.add('hidden');
        }
    }

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

            document.getElementById('kodeLaporan').innerText = `#LAP-${String(report.laporan_id).padStart(4, '0')}`;
            document.getElementById('timeAgo').innerText = formatTimeAgo(report.tanggal_lapor);
            document.getElementById('categoryBadge').innerText = report.kategori.nama;
            document.getElementById('reportAddress').innerText = report.lokasi?.alamat || report.lokasi?.nama_jalan || 'Alamat tidak tersedia';
            document.getElementById('reportDesc').innerText = `"${report.deskripsi}"`;

            renderReportMap(report.lokasi);

            if (report.foto_url) {
                currentPhotoUrl = report.foto_url;
                const photoImg = document.getElementById('reportPhoto');
                photoImg.src = report.foto_url;
                photoImg.onload = () => {
                    photoImg.classList.remove('hidden');
                    document.getElementById('photoSkeleton').classList.add('hidden');
                };
                photoImg.onerror = () => {
                    document.getElementById('photoSkeleton').innerText = 'Foto berkas tidak ditemukan di server.';
                };
            } else {
                document.getElementById('photoSkeleton').innerText = 'Foto tidak tersedia.';
            }

            renderMlSection(report);
            checkDetailPolling(report.status);

            // Check if report has already been reviewed (read-only view)
            const headerSubtitle = document.getElementById('headerSubtitle');
            const headerStatusBadge = document.getElementById('headerStatusBadge');
            const statusHistoryBanner = document.getElementById('statusHistoryBanner');
            const btnSubmitDecision = document.getElementById('btnSubmitDecision');
            const btnDisabledDecision = document.getElementById('btnDisabledDecision');
            const rejectionRequiredBadge = document.getElementById('rejectionRequiredBadge');

            if (report.status === 'TERVALIDASI') {
                headerStatusBadge.className = 'px-2.5 py-1 bg-emerald-100 text-emerald-800 text-[10px] font-extrabold rounded-full border border-emerald-200';
                headerStatusBadge.innerText = '✓ Valid';
                headerSubtitle.innerText = 'Laporan telah diverifikasi Valid';

                selectedDecision = 'VALID';
                cardValid.className = 'decision-card p-3 rounded-xl border-2 border-emerald-600 bg-emerald-50 text-center flex items-center justify-center gap-2 shadow-sm pointer-events-none';
                cardTidakValid.className = 'decision-card p-3 rounded-xl border border-slate-200 bg-slate-50 opacity-40 text-center flex items-center justify-center gap-2 pointer-events-none';

                const formattedTime = report.tanggal_validasi ? new Date(report.tanggal_validasi).toLocaleString('id-ID', {day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute:'2-digit'}) : '';
                statusHistoryBanner.className = 'mb-3 rounded-xl p-3 text-xs leading-relaxed font-semibold bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-start gap-2';
                statusHistoryBanner.innerHTML = `
                    <span class="text-base mt-0.5">✅</span>
                    <div>Laporan dinyatakan <strong>VALID</strong>${formattedTime ? ' pada ' + formattedTime : ''}. Skor segmen telah diperbarui.</div>
                `;
                statusHistoryBanner.classList.remove('hidden');

                btnSubmitDecision.classList.add('hidden');
                btnDisabledDecision.className = 'w-full py-2.5 bg-emerald-600 text-white text-xs font-bold rounded-xl flex items-center justify-center gap-2 mb-2 cursor-not-allowed shadow-sm';
                btnDisabledDecision.innerText = '✓ Status: VALID';
                btnDisabledDecision.classList.remove('hidden');
            } else if (report.status === 'DITOLAK') {
                headerStatusBadge.className = 'px-2.5 py-1 bg-rose-100 text-rose-800 text-[10px] font-extrabold rounded-full border border-rose-200';
                headerStatusBadge.innerText = '✕ Ditolak';
                headerSubtitle.innerText = 'Laporan ditolak';

                const backBtn = document.querySelector('a[href="/admin/validasi"]');
                if (backBtn) {
                    backBtn.href = '/admin/validasi?tab=ditolak';
                }

                selectedDecision = 'TIDAK_VALID';
                cardTidakValid.className = 'decision-card p-3 rounded-xl border-2 border-rose-600 bg-rose-50 text-center flex items-center justify-center gap-2 shadow-sm pointer-events-none';
                cardValid.className = 'decision-card p-3 rounded-xl border border-slate-200 bg-slate-50 opacity-40 text-center flex items-center justify-center gap-2 pointer-events-none';

                rejectionContainer.classList.remove('hidden');
                if (rejectionRequiredBadge) rejectionRequiredBadge.classList.add('hidden');
                alasanPenolakanInput.value = report.alasan_penolakan || 'Tidak ada alasan tercatat.';
                alasanPenolakanInput.readOnly = true;

                const formattedTime = report.tanggal_validasi ? new Date(report.tanggal_validasi).toLocaleString('id-ID', {day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute:'2-digit'}) : '';
                statusHistoryBanner.className = 'mb-3 rounded-xl p-3 text-xs leading-relaxed font-semibold bg-rose-50 border border-rose-200 text-rose-900 flex items-start gap-2';
                statusHistoryBanner.innerHTML = `
                    <span class="text-base mt-0.5">❌</span>
                    <div>Laporan dinyatakan <strong>DITOLAK</strong>${formattedTime ? ' pada ' + formattedTime : ''}.</div>
                `;
                statusHistoryBanner.classList.remove('hidden');

                btnSubmitDecision.classList.add('hidden');
                btnDisabledDecision.className = 'w-full py-2.5 bg-rose-600 text-white text-xs font-bold rounded-xl flex items-center justify-center gap-2 mb-2 cursor-not-allowed shadow-sm';
                btnDisabledDecision.innerText = '✕ Status: DITOLAK';
                btnDisabledDecision.classList.remove('hidden');
            } else if (report.status === 'DIPROSES') {
                headerStatusBadge.className = 'px-2.5 py-1 bg-sky-100 text-sky-800 text-[10px] font-extrabold rounded-full border border-sky-200 animate-pulse';
                headerStatusBadge.innerText = '⚡ Proses ML';
                headerSubtitle.innerText = 'Foto sedang dievaluasi oleh Machine Learning';
                btnSubmitDecision.classList.remove('hidden');
                btnDisabledDecision.classList.add('hidden');
                statusHistoryBanner.classList.add('hidden');
            } else {
                headerStatusBadge.className = 'px-2.5 py-1 bg-amber-100 text-amber-800 text-[10px] font-extrabold rounded-full border border-amber-200';
                headerStatusBadge.innerText = 'Menunggu Validasi';
                headerSubtitle.innerText = 'Tinjau bukti foto dan keputusan Machine Learning';
                btnSubmitDecision.classList.remove('hidden');
                btnDisabledDecision.classList.add('hidden');
                statusHistoryBanner.classList.add('hidden');
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