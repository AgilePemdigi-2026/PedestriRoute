<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Analisis AI - PedestriRoute Surabaya</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eefcf5',
                            100: '#d7f7e6',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex flex-col items-center">

    <div class="w-full max-w-md bg-white min-h-screen shadow-md flex flex-col">
        <!-- Top App Bar -->
        <header class="bg-white border-b border-slate-100 px-4 py-3.5 flex items-center gap-3 sticky top-0 z-30">
            <a href="/riwayat-laporan" class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-slate-200 transition">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-base font-bold text-slate-800 leading-tight">Hasil Analisis Otomatis (AI)</h1>
                <p id="reportSubtitle" class="text-xs text-slate-500">Laporan #LAP-{{ $id }}</p>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 p-4 space-y-4">

            <!-- Photo Preview Card -->
            <div class="relative w-full h-56 bg-slate-900 rounded-2xl overflow-hidden shadow-inner flex items-center justify-center">
                <img id="reportPhoto" src="" alt="Foto Laporan" class="w-full h-full object-cover hidden">
                <div id="photoSkeleton" class="flex flex-col items-center justify-center text-slate-400">
                    <i class="fa-solid fa-image text-3xl mb-2 animate-pulse"></i>
                    <span class="text-xs">Memuat foto laporan...</span>
                </div>
            </div>

            <!-- Polling Banner (State: DIPROSES) -->
            <div id="processingCard" class="bg-blue-50 border border-blue-200 rounded-2xl p-4 flex items-start gap-3">
                <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0 mt-0.5">
                    <i class="fa-solid fa-circle-notch fa-spin text-sm"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-blue-900">Sedang Menganalisis Foto...</h3>
                    <p class="text-xs text-blue-700 mt-1 leading-relaxed">
                        Layanan Computer Vision sedang memeriksa kondisi kerusakan fasilitas pejalan kaki secara asinkron. Estimasi waktu &le; 10 detik.
                    </p>
                </div>
            </div>

            <!-- Analysis Result Card (State: Finished) -->
            <div id="resultCard" class="hidden bg-white border border-slate-200 rounded-2xl p-4 shadow-sm space-y-4">
                
                <!-- Status Badge Header -->
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <span class="text-xs font-semibold text-slate-500">Status Validasi</span>
                    <span id="statusBadge" class="text-xs font-bold px-3 py-1 rounded-full bg-slate-100 text-slate-700">
                        Memuat...
                    </span>
                </div>

                <!-- AI Verdict -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500">Prediksi Model AI:</span>
                        <span id="verdictLabel" class="text-sm font-extrabold text-slate-800">-</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500">Skor Keyakinan (Confidence):</span>
                        <span id="verdictScore" class="text-sm font-extrabold text-brand-600">0%</span>
                    </div>

                    <!-- Progress bar -->
                    <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                        <div id="confidenceBar" class="bg-brand-500 h-full rounded-full transition-all duration-700" style="width: 0%"></div>
                    </div>

                    <div class="flex justify-between text-[10px] text-slate-400">
                        <span>0%</span>
                        <span class="font-semibold text-slate-500">Nilai Ambang Auto-Validasi: 80%</span>
                        <span>100%</span>
                    </div>
                </div>

                <!-- Explanatory Banner -->
                <div id="verdictBanner" class="p-3 rounded-xl text-xs leading-relaxed">
                    <!-- Dynamic text injected by JS -->
                </div>
            </div>

            <!-- Details Card -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm space-y-3">
                <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Detail Aduan</h4>
                
                <div class="flex justify-between text-xs py-1 border-b border-slate-50">
                    <span class="text-slate-500">Kategori</span>
                    <span id="detailKategori" class="font-semibold text-slate-800">-</span>
                </div>

                <div class="flex justify-between text-xs py-1 border-b border-slate-50">
                    <span class="text-slate-500">Lokasi / Jalan</span>
                    <span id="detailAlamat" class="font-semibold text-slate-800 text-right max-w-[65%]" truncate>-</span>
                </div>

                <div class="flex justify-between text-xs py-1">
                    <span class="text-slate-500">Waktu Lapor</span>
                    <span id="detailWaktu" class="font-semibold text-slate-800">-</span>
                </div>
            </div>

            <!-- Navigation Action -->
            <div class="pt-2">
                <a href="/beranda-warga" class="w-full py-3 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl flex items-center justify-center gap-2 shadow-sm transition text-sm">
                    <i class="fa-solid fa-map-location-dot"></i>
                    Lihat Peta Heatmap
                </a>
            </div>

        </main>
    </div>

    <script>
        const reportId = {{ $id }};
        let pollInterval = null;
        let pollCount = 0;
        const maxPolls = 20; // 20 x 3s = 60s max polling (FR-F4-07)

        async function fetchReportData() {
            try {
                const token = localStorage.getItem('token') || localStorage.getItem('auth_token');
                const headers = { 'Accept': 'application/json' };
                if (token) headers['Authorization'] = `Bearer ${token}`;

                const res = await fetch(`/api/v1/reports/${reportId}`, { headers });
                if (!res.ok) {
                    if (res.status === 401) {
                        window.location.href = '/login';
                        return;
                    }
                    throw new Error('Failed to load report data');
                }

                const json = await res.json();
                const data = json.data;
                renderReport(data);

                // Stop polling if status is no longer DIPROSES
                if (data.status !== 'DIPROSES') {
                    if (pollInterval) clearInterval(pollInterval);
                }
            } catch (err) {
                console.error("Error polling report:", err);
            }
        }

        function renderReport(data) {
            // Photo
            if (data.foto_url) {
                const img = document.getElementById('reportPhoto');
                img.src = data.foto_url;
                img.classList.remove('hidden');
                document.getElementById('photoSkeleton').classList.add('hidden');
            }

            // Details
            document.getElementById('detailKategori').innerText = data.kategori || '-';
            document.getElementById('detailAlamat').innerText = data.lokasi?.alamat || data.lokasi?.nama_jalan || 'Surabaya';
            if (data.tanggal_lapor) {
                const d = new Date(data.tanggal_lapor);
                document.getElementById('detailWaktu').innerText = d.toLocaleDateString('id-ID', {
                    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
                });
            }

            // State Handling
            const processingCard = document.getElementById('processingCard');
            const resultCard = document.getElementById('resultCard');

            if (data.status === 'DIPROSES') {
                processingCard.classList.remove('hidden');
                resultCard.classList.add('hidden');
            } else {
                processingCard.classList.add('hidden');
                resultCard.classList.remove('hidden');

                const statusBadge = document.getElementById('statusBadge');
                const verdictLabel = document.getElementById('verdictLabel');
                const verdictScore = document.getElementById('verdictScore');
                const confidenceBar = document.getElementById('confidenceBar');
                const verdictBanner = document.getElementById('verdictBanner');

                const conf = (data.confidence_score !== null && data.confidence_score !== undefined) 
                    ? Math.round(data.confidence_score * 100) 
                    : 0;
                const label = data.cv_label || 'TERTUNDA';

                verdictScore.innerText = `${conf}%`;
                confidenceBar.style.width = `${conf}%`;

                if (data.status === 'TERVALIDASI') {
                    statusBadge.className = 'text-xs font-bold px-3 py-1 rounded-full bg-emerald-100 text-emerald-800';
                    statusBadge.innerHTML = '<i class="fa-solid fa-check-circle mr-1"></i> Tervalidasi Otomatis (AI)';
                    verdictLabel.innerText = `RUSAK (${data.kategori})`;
                    verdictLabel.className = 'text-sm font-extrabold text-emerald-700';

                    verdictBanner.className = 'p-3 rounded-xl text-xs leading-relaxed bg-emerald-50 text-emerald-800 border border-emerald-200';
                    verdictBanner.innerHTML = `<strong>Tervalidasi Otomatis:</strong> Skor keyakinan model (${conf}%) melampaui batas ambang 80%. Skor kelayakan segmen jalan telah diperbarui secara langsung.`;
                } else if (data.status === 'MENUNGGU_VALIDASI_ADMIN') {
                    statusBadge.className = 'text-xs font-bold px-3 py-1 rounded-full bg-amber-100 text-amber-800';
                    statusBadge.innerHTML = '<i class="fa-solid fa-user-clock mr-1"></i> Menunggu Verifikasi Admin';
                    verdictLabel.innerText = label;
                    verdictLabel.className = 'text-sm font-extrabold text-amber-700';

                    verdictBanner.className = 'p-3 rounded-xl text-xs leading-relaxed bg-amber-50 text-amber-800 border border-amber-200';
                    verdictBanner.innerHTML = `<strong>Diteruskan ke Admin Dishub:</strong> Hasil deteksi (${conf}%) memerlukan verifikasi visual langsung oleh petugas sebelum disetujui.`;
                } else if (data.status === 'DITOLAK') {
                    statusBadge.className = 'text-xs font-bold px-3 py-1 rounded-full bg-rose-100 text-rose-800';
                    statusBadge.innerHTML = '<i class="fa-solid fa-times-circle mr-1"></i> Laporan Ditolak';
                    verdictLabel.innerText = label;
                    verdictLabel.className = 'text-sm font-extrabold text-rose-700';

                    verdictBanner.className = 'p-3 rounded-xl text-xs leading-relaxed bg-rose-50 text-rose-800 border border-rose-200';
                    verdictBanner.innerText = `Laporan ditolak. Alasan: ${data.alasan_penolakan || 'Tidak memenuhi kriteria aduan fasilitas pejalan kaki.'}`;
                }
            }
        }

        // Start polling (FR-F4-07: Poll every 3 seconds)
        fetchReportData();
        pollInterval = setInterval(() => {
            pollCount++;
            if (pollCount >= maxPolls) {
                clearInterval(pollInterval);
            } else {
                fetchReportData();
            }
        }, 3000);
    </script>
</body>
</html>
