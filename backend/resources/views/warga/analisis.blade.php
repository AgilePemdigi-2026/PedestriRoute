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
    <style>
        @keyframes laserScan {
            0% { top: 2%; opacity: 0.8; }
            50% { top: 92%; opacity: 1; }
            100% { top: 2%; opacity: 0.8; }
        }
        .laser-beam {
            position: absolute;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, rgba(16,185,129,0) 0%, rgba(16,185,129,0.9) 50%, rgba(16,185,129,0) 100%);
            box-shadow: 0 0 14px 4px rgba(16,185,129,0.7);
            animation: laserScan 2.4s ease-in-out infinite;
            z-index: 20;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex flex-col items-center">

    <div class="w-full max-w-md bg-white min-h-screen shadow-md flex flex-col">
        <!-- Top App Bar -->
        <header class="bg-white/95 backdrop-blur-md border-b border-slate-100 px-4 py-3.5 flex items-center justify-between sticky top-0 z-30">
            <div class="flex items-center gap-3">
                <a href="/riwayat-laporan" class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-slate-200 transition">
                    <i class="fa-solid fa-arrow-left text-sm"></i>
                </a>
                <div>
                    <h1 class="text-sm font-extrabold text-slate-900 leading-tight">Hasil Analisis Otomatis (AI)</h1>
                    <p id="reportSubtitle" class="text-[11px] text-slate-400 font-medium">Memuat aduan #LAP-{{ $id }}...</p>
                </div>
            </div>
            <span id="headerStatusBadge" class="text-[10px] font-extrabold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                Memeriksa...
            </span>
        </header>

        <!-- Main Content -->
        <main class="flex-1 p-4 space-y-3.5">

            <!-- Photo Preview Card with Scanner HUD -->
            <div class="relative w-full h-64 bg-slate-950 rounded-2xl overflow-hidden shadow-inner flex items-center justify-center border border-slate-200">
                <img id="reportPhoto" src="" alt="Foto Laporan" class="w-full h-full object-cover hidden transition-transform duration-500">
                
                <!-- Loading Skeleton -->
                <div id="photoSkeleton" class="flex flex-col items-center justify-center text-slate-500">
                    <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 text-brand-500"></i>
                    <span class="text-xs font-semibold">Mengambil foto laporan...</span>
                </div>

                <!-- Laser scanning beam (active during DIPROSES) -->
                <div id="laserBeam" class="laser-beam hidden"></div>

                <!-- Viewfinder Corner HUD Markers -->
                <div class="absolute inset-2 pointer-events-none z-10 flex flex-col justify-between">
                    <div class="flex justify-between">
                        <div class="w-3.5 h-3.5 border-t-2 border-l-2 border-emerald-400/80 rounded-tl-sm"></div>
                        <div class="w-3.5 h-3.5 border-t-2 border-r-2 border-emerald-400/80 rounded-tr-sm"></div>
                    </div>
                    <div class="flex justify-between">
                        <div class="w-3.5 h-3.5 border-b-2 border-l-2 border-emerald-400/80 rounded-bl-sm"></div>
                        <div class="w-3.5 h-3.5 border-b-2 border-r-2 border-emerald-400/80 rounded-br-sm"></div>
                    </div>
                </div>

                <!-- Top HUD Badge -->
                <div class="absolute top-3 left-3 z-20 flex items-center gap-1.5 bg-black/60 backdrop-blur-md px-2.5 py-1 rounded-lg border border-white/10 text-white text-[10px] font-bold">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    <span id="hudModelText">YOLOv8n-cls · 7 Classes</span>
                </div>

                <!-- Bottom Photo Overlay Status -->
                <div id="hudFooter" class="absolute bottom-3 inset-x-3 z-20 bg-black/60 backdrop-blur-md px-3 py-1.5 rounded-xl border border-white/10 text-white flex items-center justify-between text-[11px]">
                    <span id="hudStateText" class="font-medium text-emerald-300 flex items-center gap-1.5">
                        <i class="fa-solid fa-eye"></i> Memindai citra...
                    </span>
                    <span id="hudLatencyText" class="text-[10px] text-slate-300">SLA &le; 10s</span>
                </div>
            </div>

            <!-- Processing Pipeline Card (State: DIPROSES) -->
            <div id="processingCard" class="bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-2xl p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-brand-500/20 text-brand-400 flex items-center justify-center">
                            <i class="fa-solid fa-microchip text-xs"></i>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-white">Analisis Computer Vision Aktif</h3>
                            <p class="text-[10px] text-slate-400">Model sedang memproses citra laporan</p>
                        </div>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-brand-500/20 text-brand-300 border border-brand-500/30 flex items-center gap-1">
                        <i class="fa-solid fa-circle-notch fa-spin text-[9px]"></i> Asinkron
                    </span>
                </div>

                <!-- Live Pipeline Stepper -->
                <div class="space-y-2 pt-1 border-t border-slate-700/60 text-xs">
                    <div class="flex items-center justify-between text-slate-300 text-[11px]">
                        <span class="flex items-center gap-2">
                            <span class="w-4 h-4 rounded-full bg-emerald-500 text-slate-950 flex items-center justify-center text-[9px] font-black">✓</span>
                            <span>Pra-pemrosesan citra (224x224 RGB)</span>
                        </span>
                        <span class="text-emerald-400 font-semibold text-[10px]">Selesai</span>
                    </div>

                    <div class="flex items-center justify-between text-slate-300 text-[11px]">
                        <span class="flex items-center gap-2">
                            <span class="w-4 h-4 rounded-full bg-emerald-500 text-slate-950 flex items-center justify-center text-[9px] font-black">✓</span>
                            <span>Filter Heuristik & Anti-Spam (Stage 0)</span>
                        </span>
                        <span class="text-emerald-400 font-semibold text-[10px]">Lolos</span>
                    </div>

                    <div class="flex items-center justify-between text-slate-200 text-[11px]">
                        <span class="flex items-center gap-2">
                            <span class="w-4 h-4 rounded-full bg-brand-500 text-slate-950 flex items-center justify-center text-[9px] font-black animate-pulse">3</span>
                            <span class="font-bold text-white">Klasifikasi Cacat YOLOv8</span>
                        </span>
                        <span class="text-brand-400 font-bold text-[10px] animate-pulse">Berjalan...</span>
                    </div>

                    <div class="flex items-center justify-between text-slate-400 text-[11px]">
                        <span class="flex items-center gap-2">
                            <span class="w-4 h-4 rounded-full bg-slate-700 text-slate-400 flex items-center justify-center text-[9px] font-bold">4</span>
                            <span>Rekalkulasi Indeks Kelayakan WSM</span>
                        </span>
                        <span class="text-slate-500 text-[10px]">Menunggu</span>
                    </div>
                </div>

                <div class="p-2.5 bg-slate-800/80 rounded-xl border border-slate-700 flex items-center gap-2 text-[10px] text-slate-300">
                    <i class="fa-solid fa-clock-rotate-left text-brand-400"></i>
                    <span>Polling otomatis setiap 3 detik. Halaman ini akan diperbarui instan saat hasil selesai.</span>
                </div>
            </div>

            <!-- Analysis Result Card (State: Completed) -->
            <div id="resultCard" class="hidden space-y-3.5">
                
                <!-- Main Verdict Banner -->
                <div id="verdictBannerCard" class="rounded-2xl p-4 border shadow-sm transition-all">
                    <!-- Injected by JS -->
                </div>

                <!-- Confidence Gauge Card -->
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                        <div class="flex items-center gap-1.5">
                            <i class="fa-solid fa-chart-simple text-brand-600 text-xs"></i>
                            <h4 class="text-xs font-bold text-slate-800">Tingkat Keyakinan Model (Confidence)</h4>
                        </div>
                        <span id="verdictScorePill" class="text-xs font-extrabold text-brand-700 bg-brand-50 px-2.5 py-0.5 rounded-full border border-brand-200">
                            0%
                        </span>
                    </div>

                    <!-- Visual Progress Bar -->
                    <div class="space-y-1.5">
                        <div class="w-full bg-slate-100 h-3 rounded-full overflow-hidden p-0.5 border border-slate-200">
                            <div id="confidenceBar" class="bg-gradient-to-r from-emerald-500 to-teal-400 h-full rounded-full transition-all duration-1000 shadow-sm" style="width: 0%"></div>
                        </div>
                        <div class="flex justify-between text-[10px] text-slate-400 font-medium">
                            <span>0%</span>
                            <span class="text-emerald-700 font-bold flex items-center gap-1">
                                <i class="fa-solid fa-flag-checkered text-[9px]"></i> Ambang Auto-Validasi: 80%
                            </span>
                            <span>100%</span>
                        </div>
                    </div>

                    <!-- Category Detection Matching Comparison -->
                    <div class="pt-2 border-t border-slate-100 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-500 font-medium">Kategori Dilaporkan:</span>
                            <span id="reportedCatBadge" class="font-bold text-slate-800 bg-slate-100 px-2 py-0.5 rounded-md">-</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-500 font-medium">Prediksi Computer Vision:</span>
                            <span id="aiCatBadge" class="font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md">-</span>
                        </div>
                    </div>
                </div>

                <!-- Live Audit Trail Log -->
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm space-y-2.5">
                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-list-check text-slate-400"></i>
                        <span>Riwayat Validasi Sistem</span>
                    </h4>
                    <ul id="timelineList" class="space-y-2 text-xs divide-y divide-slate-50">
                        <!-- Populated by JS -->
                    </ul>
                </div>

            </div>

            <!-- Report Details Card -->
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm space-y-2.5">
                <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-info text-slate-400"></i>
                    <span>Informasi Aduan</span>
                </h4>
                
                <div class="flex justify-between text-xs py-1 border-b border-slate-50">
                    <span class="text-slate-500">ID Laporan</span>
                    <span id="detailId" class="font-bold text-slate-800 font-mono">#LAP-{{ $id }}</span>
                </div>

                <div class="flex justify-between text-xs py-1 border-b border-slate-50">
                    <span class="text-slate-500">Lokasi / Jalan</span>
                    <span id="detailAlamat" class="font-semibold text-slate-800 text-right max-w-[65%] truncate">-</span>
                </div>

                <div class="flex justify-between text-xs py-1 border-b border-slate-50">
                    <span class="text-slate-500">Waktu Kirim</span>
                    <span id="detailWaktu" class="font-semibold text-slate-800">-</span>
                </div>

                <div class="pt-1">
                    <span class="text-[11px] text-slate-400 block mb-0.5">Deskripsi Warga:</span>
                    <p id="detailDeskripsi" class="text-xs text-slate-700 italic bg-slate-50 p-2.5 rounded-xl border border-slate-100 leading-relaxed">
                        -
                    </p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="pt-2 space-y-2">
                <a href="/beranda-warga" class="w-full py-3 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl flex items-center justify-center gap-2 shadow-sm transition text-sm">
                    <i class="fa-solid fa-map-location-dot"></i>
                    <span>Lihat di Peta Interaktif</span>
                </a>
                
                <div class="grid grid-cols-2 gap-2">
                    <a href="/riwayat-laporan" class="py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl flex items-center justify-center gap-1.5 transition text-xs">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>Riwayat Saya</span>
                    </a>
                    <a href="/lapor-kondisi" class="py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl flex items-center justify-center gap-1.5 transition text-xs">
                        <i class="fa-solid fa-camera"></i>
                        <span>Lapor Lagi</span>
                    </a>
                </div>
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
                    throw new Error('Gagal memuat detail aduan');
                }

                const json = await res.json();
                const data = json.data;
                renderReport(data);

                // Stop polling if status is no longer DIPROSES
                if (data.status !== 'DIPROSES') {
                    if (pollInterval) {
                        clearInterval(pollInterval);
                        pollInterval = null;
                    }
                }
            } catch (err) {
                console.error("Error polling report:", err);
            }
        }

        function renderReport(data) {
            // Photo rendering
            if (data.foto_url) {
                const img = document.getElementById('reportPhoto');
                img.src = data.foto_url;
                img.classList.remove('hidden');
                document.getElementById('photoSkeleton').classList.add('hidden');
            }

            // Report Meta Details
            const kategoriNama = data.kategori?.nama || data.kategori || 'Fasilitas Pejalan Kaki';
            document.getElementById('reportSubtitle').innerText = `Aduan #LAP-${String(data.laporan_id).padStart(4, '0')} · ${kategoriNama}`;
            document.getElementById('detailAlamat').innerText = data.lokasi?.alamat || data.lokasi?.nama_jalan || 'Surabaya';
            document.getElementById('detailDeskripsi').innerText = `"${data.deskripsi || 'Tidak ada deskripsi'}"`;
            document.getElementById('reportedCatBadge').innerText = kategoriNama;

            if (data.tanggal_lapor) {
                const d = new Date(data.tanggal_lapor);
                document.getElementById('detailWaktu').innerText = d.toLocaleDateString('id-ID', {
                    day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
                });
            }

            // Timeline audit
            if (data.riwayat && data.riwayat.length > 0) {
                const tl = document.getElementById('timelineList');
                tl.innerHTML = data.riwayat.map(r => `
                    <li class="pt-2 flex items-start gap-2">
                        <span class="w-2 h-2 rounded-full bg-brand-600 mt-1 shrink-0"></span>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-800 text-[11px]">${r.status}</span>
                                <span class="text-[10px] text-slate-400">${r.waktu ? new Date(r.waktu).toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'}) : '-'}</span>
                            </div>
                            <p class="text-[11px] text-slate-600 mt-0.5 leading-snug">${r.catatan}</p>
                        </div>
                    </li>
                `).join('');
            }

            // State Handling: DIPROSES vs Completed
            const processingCard = document.getElementById('processingCard');
            const resultCard = document.getElementById('resultCard');
            const laserBeam = document.getElementById('laserBeam');
            const headerBadge = document.getElementById('headerStatusBadge');
            const hudState = document.getElementById('hudStateText');

            if (data.status === 'DIPROSES') {
                processingCard.classList.remove('hidden');
                resultCard.classList.add('hidden');
                laserBeam.classList.remove('hidden');

                headerBadge.className = 'text-[10px] font-extrabold px-2.5 py-1 rounded-full bg-sky-100 text-sky-800 border border-sky-200 animate-pulse';
                headerBadge.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Sedang Dianalisis';
                hudState.innerHTML = '<i class="fa-solid fa-radar text-emerald-400 animate-spin"></i> Mengevaluasi Citra...';
            } else {
                processingCard.classList.add('hidden');
                resultCard.classList.remove('hidden');
                laserBeam.classList.add('hidden');

                const conf = (data.confidence_score !== null && data.confidence_score !== undefined) 
                    ? Math.round(data.confidence_score * 100) 
                    : 0;
                const label = data.cv_label || 'TERTUNDA';

                document.getElementById('verdictScorePill').innerText = `${conf}%`;
                document.getElementById('confidenceBar').style.width = `${Math.min(100, Math.max(5, conf))}%`;
                document.getElementById('aiCatBadge').innerText = `${label} (${conf}%)`;

                const vCard = document.getElementById('verdictBannerCard');

                if (data.status === 'TERVALIDASI') {
                    headerBadge.className = 'text-[10px] font-extrabold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200';
                    headerBadge.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Valid (AI)';
                    hudState.innerHTML = `<i class="fa-solid fa-circle-check text-emerald-400"></i> Terverifikasi (${conf}%)`;

                    vCard.className = 'bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-200 rounded-2xl p-4 shadow-sm space-y-2';
                    vCard.innerHTML = `
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-emerald-500 text-white flex items-center justify-center text-lg shadow-sm shrink-0">
                                <i class="fa-solid fa-shield-check"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-extrabold text-emerald-950">Aduan Tervalidasi Otomatis (AI)</h3>
                                <p class="text-[11px] text-emerald-700">Tingkat keyakinan model mencapai ${conf}% (&ge; ambang batas 80%)</p>
                            </div>
                        </div>
                        <div class="p-3 bg-white/80 backdrop-blur-sm rounded-xl border border-emerald-100 text-xs text-emerald-900 leading-relaxed space-y-1">
                            <p><strong>Dampak Langsung:</strong> Kerusakan fasilitas pada segmen ini telah dikonfirmasi dan <em>Indeks Kelayakan Jalur (WSM)</em> telah otomatis diperbarui pada Heatmap Kota.</p>
                            <p class="text-[11px] text-emerald-700 pt-0.5">Petugas Dinas Perhubungan menerima laporan ini dalam antrean pengerjaan perbaikan.</p>
                        </div>
                    `;
                } else if (data.status === 'MENUNGGU_VALIDASI_ADMIN') {
                    headerBadge.className = 'text-[10px] font-extrabold px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 border border-amber-200';
                    headerBadge.innerHTML = '<i class="fa-solid fa-user-clock mr-1"></i> Tinjauan Admin';
                    hudState.innerHTML = `<i class="fa-solid fa-user-check text-amber-400"></i> Menunggu Admin (${conf}%)`;

                    const isSpam = label === 'SPAM';
                    const isBaik = label === 'BAIK';

                    let subtitle = `Skor AI ${conf}% memerlukan verifikasi visual langsung oleh petugas.`;
                    if (isSpam) subtitle = `AI menandai foto sebagai non-fasilitas pejalan kaki (${conf}%). Petugas akan memeriksa manual.`;
                    if (isBaik) subtitle = `AI mendeteksi kemungkinan fasilitas dalam kondisi normal (${conf}%). Diteruskan untuk kepastian.`;

                    vCard.className = 'bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-200 rounded-2xl p-4 shadow-sm space-y-2';
                    vCard.innerHTML = `
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center text-lg shadow-sm shrink-0">
                                <i class="fa-solid fa-clipboard-question"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-extrabold text-amber-950">Diteruskan ke Verifikasi Petugas</h3>
                                <p class="text-[11px] text-amber-800">${subtitle}</p>
                            </div>
                        </div>
                        <div class="p-3 bg-white/80 backdrop-blur-sm rounded-xl border border-amber-100 text-xs text-amber-900 leading-relaxed">
                            Aduan Anda tetap tersimpan dengan aman dan akan diverifikasi langsung oleh Admin Dinas Perhubungan Surabaya. Anda dapat memantau perkembangannya melalui menu Riwayat Laporan.
                        </div>
                    `;
                } else if (data.status === 'DITOLAK') {
                    headerBadge.className = 'text-[10px] font-extrabold px-2.5 py-1 rounded-full bg-rose-100 text-rose-800 border border-rose-200';
                    headerBadge.innerHTML = '<i class="fa-solid fa-xmark mr-1"></i> Ditolak';
                    hudState.innerHTML = `<i class="fa-solid fa-circle-xmark text-rose-400"></i> Laporan Ditolak`;

                    vCard.className = 'bg-rose-50 border border-rose-200 rounded-2xl p-4 shadow-sm space-y-2';
                    vCard.innerHTML = `
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-rose-500 text-white flex items-center justify-center text-lg shadow-sm shrink-0">
                                <i class="fa-solid fa-ban"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-extrabold text-rose-950">Aduan Ditolak</h3>
                                <p class="text-[11px] text-rose-700">Tidak memenuhi kriteria kerusakan fasilitas publik</p>
                            </div>
                        </div>
                        <div class="p-3 bg-white/80 rounded-xl border border-rose-100 text-xs text-rose-900 leading-relaxed">
                            <strong>Alasan Penolakan:</strong> ${data.alasan_penolakan || 'Foto tidak memenuhi kriteria kerusakan infrastruktur trotoar atau gambar tidak jelas.'}
                        </div>
                    `;
                }
            }
        }

        // Start polling (FR-F4-07: Poll every 3 seconds)
        fetchReportData();
        pollInterval = setInterval(() => {
            pollCount++;
            if (pollCount >= maxPolls) {
                if (pollInterval) clearInterval(pollInterval);
            } else {
                fetchReportData();
            }
        }, 3000);
    </script>
</body>
</html>
