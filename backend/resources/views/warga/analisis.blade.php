<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Analisis Laporan - PedestriRoute</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { color-scheme: light; --teal: #006257; --teal-dark: #004f46; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f7fb; color: #0f172a; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; line-height: 1.6; }
        [hidden] { display: none !important; }
        a { color: inherit; text-decoration: none; }
        button { font: inherit; cursor: pointer; }
        .app { width: 100%; max-width: 448px; min-height: 100vh; margin: 0 auto; background: #fff; box-shadow: 0 4px 32px #0f172a08; }
        header { display: flex; gap: 12px; align-items: center; padding: 20px; border-bottom: 1px solid #f1f5f9; }
        .back { display: grid; place-items: center; width: 36px; height: 36px; flex-shrink: 0; border-radius: 12px; background: #f1f5f9; color: #475569; }
        header div { min-width: 0; }
        h1 { margin: 0; font-size: 16px; line-height: 1.4; }
        .subtitle { color: #64748b; font-size: 12px; margin: 3px 0 0; overflow-wrap: anywhere; }
        main { display: grid; gap: 16px; padding: 20px 20px max(28px, env(safe-area-inset-bottom)); }
        .state { border: 1px solid #ccebe7; border-radius: 20px; padding: 20px; background: #f0faf8; }
        .state[data-tone="pending"] { background: #fffbeb; border-color: #fde68a; }
        .state[data-tone="rejected"] { background: #fff1f2; border-color: #fecdd3; }
        .state[data-tone="neutral"] { background: #f8fafc; border-color: #e2e8f0; }
        .eyebrow { color: #475569; text-transform: uppercase; letter-spacing: .08em; font-size: 10px; font-weight: 800; margin: 0 0 10px; }
        .state-icon { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 12px; margin-bottom: 12px; color: var(--teal); background: #dff2ee; font-size: 20px; font-weight: 800; }
        .state[data-tone="pending"] .state-icon { color: #92400e; background: #fef3c7; }
        .state[data-tone="rejected"] .state-icon { color: #be123c; background: #ffe4e6; }
        .spinner { display: inline-block; width: 18px; height: 18px; border: 2px solid #00625730; border-top-color: var(--teal); border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        h2 { font-size: 20px; line-height: 1.4; margin: 0; overflow-wrap: anywhere; }
        .state p:last-child { margin: 8px 0 0; color: #475569; font-size: 13px; }
        .fade-in { animation: appear .25s ease-out; }
        @keyframes appear { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
        .result { border: 1px solid #e2e8f0; border-radius: 18px; padding: 16px; min-width: 0; }
        .label { display: block; color: #64748b; font-size: 12px; margin-bottom: 4px; }
        .value { font-size: 14px; font-weight: 800; overflow-wrap: anywhere; }
        .metric { margin-top: 16px; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .metric-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
        .badge { display: inline-block; color: var(--teal); background: #e6f5f3; border-radius: 999px; padding: 4px 10px; font-size: 11px; font-weight: 700; }
        .percent { font-size: 22px; font-weight: 800; color: var(--teal); }
        .track { height: 6px; border-radius: 99px; background: #edf2f7; overflow: hidden; margin-top: 10px; }
        .fill { width: 0; height: 100%; background: #008775; border-radius: inherit; transition: width .3s ease; }
        .hint { margin: 8px 0 0; color: #64748b; font-size: 11px; }
        .actions { display: grid; gap: 10px; }
        .button { display: flex; min-height: 46px; align-items: center; justify-content: center; padding: 11px 14px; border: 1px solid var(--teal); border-radius: 12px; background: var(--teal); color: white; text-align: center; font-size: 12px; font-weight: 800; }
        .button:hover { background: var(--teal-dark); }
        .button.secondary { color: var(--teal); background: white; border-color: #ccebe7; }
        .button.secondary:hover { background: #f0faf8; }
        .notice { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; color: #475569; background: #f8fafc; font-size: 12px; }
        .notice p { margin: 0 0 8px; }
        .retry { padding: 6px 10px; border: 1px solid #ccebe7; border-radius: 8px; background: white; color: var(--teal); font-size: 12px; font-weight: 700; }
        .retry:disabled { opacity: .6; cursor: wait; }
        details { min-width: 0; border: 1px solid #e2e8f0; border-radius: 16px; }
        summary { padding: 14px 16px; font-size: 12px; font-weight: 700; cursor: pointer; color: #475569; }
        .details-body { padding: 0 16px 16px; overflow-wrap: anywhere; }
        .photo { width: 100%; max-height: 240px; object-fit: contain; border-radius: 12px; background: #f8fafc; margin: 8px 0 12px; }
        dl { margin: 0; } dt { color: #64748b; font-size: 11px; margin-top: 12px; } dd { margin: 2px 0 0; font-size: 12px; }
        h3 { font-size: 12px; margin: 18px 0 8px; }
        ul { list-style: none; padding: 0; margin: 0; }
        li { padding: 10px 0; border-top: 1px solid #f1f5f9; font-size: 12px; }
        li p { margin: 3px 0; } time { font-size: 10px; color: #64748b; }
        :focus-visible { outline: 2px solid #008775; outline-offset: 3px; }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation: none !important; transition: none !important; } }
    </style>
</head>
<body>
<div class="app">
    <header>
        <a class="back" href="/riwayat-laporan" aria-label="Kembali ke riwayat laporan">←</a>
        <div><h1>Hasil Analisis Laporan</h1><p id="reportSubtitle" class="subtitle">Laporan #LAP-{{ $id }}</p></div>
    </header>
    <main>
        <section id="statusCard" class="state" data-tone="neutral" aria-labelledby="statusTitle" aria-live="polite" aria-busy="true">
            <p class="eyebrow">Status laporan</p>
            <div id="processingIcon" class="state-icon" aria-hidden="true"><span class="spinner"></span></div>
            <div id="resultIcon" class="state-icon" aria-hidden="true" hidden>✓</div>
            <h2 id="statusTitle">Memuat hasil analisis</h2>
            <p id="statusDescription">Mohon tunggu, kami sedang memeriksa status laporanmu.</p>
        </section>

        <section id="resultCard" class="result" aria-label="Ringkasan hasil analisis" hidden>
            <span id="categoryLabel" class="label">Kategori laporan</span>
            <div id="categoryValue" class="value">Belum tersedia</div>
            <p id="predictionNote" class="hint" hidden></p>
            <div class="metric">
                <span class="label">Tingkat keyakinan AI</span>
                <div class="metric-row"><span id="confidenceBadge" class="badge">Belum tersedia</span><span id="confidencePercent" class="percent">—</span></div>
                <div id="confidenceTrack" class="track" role="progressbar" aria-label="Tingkat keyakinan AI" aria-valuemin="0" aria-valuemax="100" hidden><div id="confidenceBar" class="fill"></div></div>
                <p class="hint">Keyakinan AI terhadap hasil deteksi. Keputusan laporan mengikuti status di atas.</p>
            </div>
        </section>

        <div id="requestNotice" class="notice" role="status" hidden>
            <p id="noticeText"></p><button type="button" id="retryButton" class="retry">Periksa lagi</button>
        </div>

        <nav class="actions" aria-label="Navigasi laporan">
            <a class="button" href="/riwayat-laporan">Lihat Riwayat Laporan</a>
            <a class="button secondary" href="/beranda-warga">Kembali ke Beranda</a>
        </nav>

        <details id="reportDetails" hidden>
            <summary>Lihat foto dan detail laporan</summary>
            <div class="details-body">
                <img id="reportPhoto" class="photo" alt="Foto bukti laporan warga" hidden>
                <p id="photoMessage" class="hint" hidden>Foto tidak tersedia.</p>
                <dl>
                    <dt>Lokasi</dt><dd id="detailAddress">—</dd>
                    <dt>Kategori yang dilaporkan</dt><dd id="detailCategory">—</dd>
                    <dt>Waktu laporan</dt><dd id="detailDate">—</dd>
                    <dt>Deskripsi</dt><dd id="detailDescription">—</dd>
                </dl>
                <h3>Riwayat status</h3><ul id="timelineList"></ul>
            </div>
        </details>
    </main>
</div>
<script>
    const reportId = {{ $id }};
    const POLL_MS = 3000;
    const MAX_POLLS = 20;
    let pollInterval = null;
    let pollCount = 0;
    let inFlight = false;
    let activeRequest = null;
    let latestReport = null;
    let leavingPage = false;
    const el = id => document.getElementById(id);
    const statusLabels = {
        DIPROSES: 'Sedang dianalisis',
        MENUNGGU_VALIDASI_ADMIN: 'Menunggu verifikasi petugas',
        TERVALIDASI: 'Laporan tervalidasi',
        DITOLAK: 'Laporan ditolak',
        SEDANG_DIPERBAIKI: 'Sedang diperbaiki',
        SUDAH_DIPERBAIKI: 'Sudah diperbaiki'
    };
    const predictionLabels = {RUSAK: 'Kerusakan fasilitas', TERHALANG: 'Fasilitas terhalang', BAIK: 'Kondisi terlihat baik', SPAM: 'Foto perlu diperiksa petugas'};

    function stopPolling() {
        if (pollInterval !== null) clearInterval(pollInterval);
        pollInterval = null;
    }

    function showNotice(message) {
        el('noticeText').textContent = message;
        el('requestNotice').hidden = false;
    }

    function formatDate(value) {
        const date = value ? new Date(value) : null;
        return date && !Number.isNaN(date.getTime())
            ? date.toLocaleString('id-ID', {day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'}) : '—';
    }

    function renderReport(data) {
        latestReport = data;
        const processing = data.status === 'DIPROSES';
        const category = typeof data.kategori === 'string' ? data.kategori : data.kategori?.nama || 'Belum tersedia';
        const label = data.cv_label || '';
        const hasScore = data.confidence_score !== null && data.confidence_score !== undefined &&
            data.confidence_score !== '' && Number.isFinite(Number(data.confidence_score)) &&
            Number(data.confidence_score) >= 0 && Number(data.confidence_score) <= 1;
        const score = hasScore ? Number(data.confidence_score) : null;
        const percent = hasScore ? Math.round(score * 100) : null;
        const history = Array.isArray(data.riwayat) ? data.riwayat : [];
        // Prefer the latest validation entry; a manual decision must not be labelled AI.
        const validation = [...history].reverse().find(item => ['TERVALIDASI', 'DITOLAK'].includes(item.status));
        const autoValidated = data.status === 'TERVALIDASI' && validation?.status === 'TERVALIDASI' &&
            /tervalidasi otomatis oleh computer vision/i.test(validation.catatan || '');
        let title = statusLabels[data.status] || 'Status sedang diperbarui';
        let description = 'Lihat perkembangan laporan melalui halaman Riwayat Laporan.';
        let tone = 'neutral';
        let icon = 'i';
        if (processing) {
            description = 'Foto sedang dianalisis. Status diperiksa otomatis setiap 3 detik.';
        } else if (data.status === 'TERVALIDASI') {
            title = autoValidated ? 'Tervalidasi otomatis oleh AI' : 'Laporan tervalidasi';
            description = 'Laporan dinyatakan valid. Pantau perkembangan penanganannya di Riwayat Laporan.';
            tone = 'valid'; icon = '✓';
        } else if (data.status === 'MENUNGGU_VALIDASI_ADMIN') {
            description = label === 'BAIK'
                ? 'Foto tampak dalam kondisi baik. Petugas akan memeriksa kembali laporanmu.'
                : label === 'SPAM'
                    ? 'Foto perlu diperiksa lebih lanjut. Petugas akan menentukan hasil verifikasi.'
                    : 'Laporan sudah diterima. Petugas akan memeriksa hasil analisis sebelum menentukan keputusan.';
            tone = 'pending'; icon = '…';
        } else if (data.status === 'DITOLAK') {
            description = data.alasan_penolakan || 'Lihat alasan dan riwayat verifikasi pada detail laporan.';
            tone = 'rejected'; icon = '×';
        }
        const statusCard = el('statusCard');
        const changed = statusCard.dataset.status !== data.status;
        statusCard.dataset.status = data.status;
        statusCard.dataset.tone = tone;
        statusCard.setAttribute('aria-busy', processing ? 'true' : 'false');
        el('statusTitle').textContent = title;
        el('statusDescription').textContent = description;
        el('processingIcon').hidden = !processing;
        el('resultIcon').hidden = processing;
        el('resultIcon').textContent = icon;
        el('resultCard').hidden = processing;
        if (changed && !processing) {
            statusCard.classList.remove('fade-in');
            requestAnimationFrame(() => statusCard.classList.add('fade-in'));
        }
        el('categoryLabel').textContent = data.status === 'TERVALIDASI' ? 'Kategori terverifikasi' : 'Kategori laporan';
        el('categoryValue').textContent = category;
        el('predictionNote').hidden = !label;
        el('predictionNote').textContent = label ? `Hasil deteksi AI: ${predictionLabels[label] || label}` : '';
        // Presentation labels only; these do not change server validation rules.
        el('confidenceBadge').textContent = !hasScore ? 'Belum tersedia' : score >= 0.8 ? 'Sangat yakin' : score >= 0.6 ? 'Cukup yakin' : 'Keyakinan rendah';
        el('confidencePercent').textContent = hasScore ? `${percent}%` : '—';
        el('confidenceTrack').hidden = !hasScore;
        el('confidenceTrack').setAttribute('aria-valuenow', hasScore ? String(percent) : '0');
        el('confidenceBar').style.width = hasScore ? `${percent}%` : '0%';
        el('reportSubtitle').textContent = `Laporan #LAP-${String(data.laporan_id ?? reportId).padStart(4, '0')}`;
        el('detailAddress').textContent = data.lokasi?.alamat || data.lokasi?.nama_jalan || 'Lokasi tidak tersedia';
        el('detailCategory').textContent = category;
        el('detailDate').textContent = formatDate(data.tanggal_lapor);
        el('detailDescription').textContent = data.deskripsi || 'Tidak ada deskripsi.';
        el('reportDetails').hidden = false;
        const photo = el('reportPhoto');
        if (data.foto_url) {
            photo.onload = () => { photo.hidden = false; el('photoMessage').hidden = true; };
            photo.onerror = () => { photo.hidden = true; el('photoMessage').hidden = false; };
            if (photo.getAttribute('src') !== data.foto_url) photo.src = data.foto_url;
        } else {
            photo.removeAttribute('src'); photo.hidden = true; el('photoMessage').hidden = false;
        }
        const list = el('timelineList');
        list.replaceChildren();
        if (!history.length) {
            const item = document.createElement('li'); item.textContent = 'Belum ada pembaruan status.'; list.append(item);
        }
        for (const entry of history) {
            const item = document.createElement('li');
            const heading = document.createElement('strong'); heading.textContent = statusLabels[entry.status] || entry.status || 'Pembaruan laporan';
            const note = document.createElement('p'); note.textContent = entry.catatan || '';
            const time = document.createElement('time'); time.textContent = formatDate(entry.waktu);
            item.append(heading, note, time); list.append(item);
        }
    }

    async function fetchReportData() {
        if (inFlight || leavingPage) return;
        inFlight = true;
        el('retryButton').disabled = true;
        const request = new AbortController();
        activeRequest = request;
        const timeout = setTimeout(() => request.abort(), 10000);
        try {
            const token = localStorage.getItem('token') || localStorage.getItem('auth_token');
            const headers = {'Accept': 'application/json'};
            if (token) headers.Authorization = `Bearer ${token}`;
            const response = await fetch(`/api/v1/reports/${reportId}`, {headers, signal: request.signal});
            if (response.status === 401) { stopPolling(); window.location.href = '/login'; return; }
            if (response.status === 403 || response.status === 404) {
                stopPolling(); el('statusTitle').textContent = 'Laporan tidak tersedia';
                el('statusDescription').textContent = response.status === 403 ? 'Kamu tidak memiliki akses ke laporan ini.' : 'Laporan yang diminta tidak ditemukan.';
                el('processingIcon').hidden = true;
                el('resultIcon').hidden = false;
                el('statusCard').setAttribute('aria-busy', 'false');
                return;
            }
            if (!response.ok) throw new Error('request_failed');
            const json = await response.json();
            if (!json.data || !json.data.status) throw new Error('invalid_response');
            if (leavingPage) return;
            el('requestNotice').hidden = true;
            renderReport(json.data);
            if (json.data.status !== 'DIPROSES') stopPolling();
        } catch (error) {
            if (leavingPage) return;
            showNotice('Status belum dapat diperbarui. Periksa koneksi atau tekan Periksa lagi.');
            if (!latestReport) {
                el('statusTitle').textContent = 'Hasil belum dapat dimuat';
                el('statusDescription').textContent = 'Kamu tetap bisa memeriksa laporan melalui halaman Riwayat.';
                el('processingIcon').hidden = true;
                el('resultIcon').hidden = false;
                el('statusCard').setAttribute('aria-busy', 'false');
            }
        } finally {
            clearTimeout(timeout);
            if (activeRequest === request) activeRequest = null;
            inFlight = false;
            el('retryButton').disabled = false;
        }
    }

    function startPolling() {
        if (inFlight) return;
        stopPolling();
        pollCount = 0;
        // Create the interval before the first request so a fast result can stop it.
        pollInterval = setInterval(() => {
            pollCount++;
            if (pollCount >= MAX_POLLS) {
                stopPolling();
                showNotice('Analisis masih berlangsung atau status belum tersedia. Periksa lagi atau lihat Riwayat Laporan.');
                return;
            }
            fetchReportData();
        }, POLL_MS);
        fetchReportData();
    }
    el('retryButton').addEventListener('click', startPolling);
    window.addEventListener('pagehide', () => {
        leavingPage = true;
        stopPolling();
        if (activeRequest) activeRequest.abort();
    });
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        leavingPage = false;
        if (!inFlight) startPolling();
    });
    startPolling();
</script>
</body>
</html>
