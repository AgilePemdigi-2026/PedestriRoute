<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Analisis ML - PedestriRoute</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --teal: #006257;
            --teal-dark: #004f46;
            --brand-600: #008775;
            --rose-bg: #fff1f2;
            --rose-border: #fecdd3;
            --rose-text: #9f1239;
            --emerald-bg: #ecfdf5;
            --emerald-border: #a7f3d0;
            --emerald-text: #065f46;
            --amber-bg: #fffbeb;
            --amber-border: #fde68a;
            --amber-text: #92400e;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f7fb; color: #0f172a; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; line-height: 1.6; }
        [hidden] { display: none !important; }
        a { color: inherit; text-decoration: none; }
        button { font: inherit; cursor: pointer; }
        .app { width: 100%; max-width: 448px; min-height: 100vh; margin: 0 auto; background: #fff; box-shadow: 0 4px 32px #0f172a08; }
        header { display: flex; gap: 12px; align-items: center; padding: 20px; border-bottom: 1px solid #f1f5f9; }
        .back { display: grid; place-items: center; width: 36px; height: 36px; flex-shrink: 0; border-radius: 12px; background: #f1f5f9; color: #475569; font-weight: bold; }
        header div { min-width: 0; }
        h1 { margin: 0; font-size: 16px; font-weight: 800; line-height: 1.4; }
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
        h2 { font-size: 20px; line-height: 1.4; margin: 0; overflow-wrap: anywhere; font-weight: 800; }
        .state p:last-child { margin: 8px 0 0; color: #475569; font-size: 13px; }
        .fade-in { animation: appear .25s ease-out; }
        @keyframes appear { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
        .result { border: 1px solid #e2e8f0; border-radius: 18px; padding: 18px; min-width: 0; background: #ffffff; }

        /* Prominent Condition Card (Rusak / Baik / Terhalang) */
        .condition-card {
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .condition-card[data-condition="RUSAK"] {
            background: var(--rose-bg);
            border-color: var(--rose-border);
            color: var(--rose-text);
        }
        .condition-card[data-condition="BAIK"] {
            background: var(--emerald-bg);
            border-color: var(--emerald-border);
            color: var(--emerald-text);
        }
        .condition-card[data-condition="TERHALANG"] {
            background: var(--amber-bg);
            border-color: var(--amber-border);
            color: var(--amber-text);
        }
        .condition-card[data-condition="SPAM"] {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #334155;
        }
        .condition-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }
        .condition-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .condition-card[data-condition="RUSAK"] .condition-badge {
            background: #ffe4e6;
            color: #be123c;
            border: 1px solid #fecdd3;
        }
        .condition-card[data-condition="BAIK"] .condition-badge {
            background: #d1fae5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .condition-card[data-condition="TERHALANG"] .condition-badge {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .condition-card[data-condition="SPAM"] .condition-badge {
            background: #e2e8f0;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        .condition-title {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .condition-desc {
            margin: 6px 0 0;
            font-size: 12px;
            line-height: 1.5;
            opacity: 0.95;
        }

        .label { display: block; color: #64748b; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
        .value { font-size: 14px; font-weight: 800; overflow-wrap: anywhere; color: #0f172a; }
        .metric { margin-top: 16px; padding-top: 16px; border-top: 1px solid #f1f5f9; }
        .metric-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
        .badge { display: inline-block; color: var(--teal); background: #e6f5f3; border-radius: 999px; padding: 4px 10px; font-size: 11px; font-weight: 700; }
        .badge[data-level="high"] { background: #d1fae5; color: #047857; border: 1px solid #a7f3d0; }
        .badge[data-level="mid"] { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .badge[data-level="low"] { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .percent { font-size: 22px; font-weight: 800; color: var(--teal); }
        .track { height: 8px; border-radius: 99px; background: #edf2f7; overflow: hidden; margin-top: 10px; }
        .fill { width: 0; height: 100%; background: #008775; border-radius: inherit; transition: width .3s ease; }
        .hint { margin: 8px 0 0; color: #64748b; font-size: 11px; line-height: 1.4; }
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
        <div>
            <h1>Hasil Analisis ML</h1>
            <p id="reportSubtitle" class="subtitle">Hasil Analisis Otomatis &bull; Laporan #LAP-{{ $id }}</p>
        </div>
    </header>
    <main>
        <section id="statusCard" class="state" data-tone="neutral" aria-labelledby="statusTitle" aria-live="polite" aria-busy="true">
            <p class="eyebrow">Status Laporan</p>
            <div id="processingIcon" class="state-icon" aria-hidden="true"><span class="spinner"></span></div>
            <div id="resultIcon" class="state-icon" aria-hidden="true" hidden>✓</div>
            <h2 id="statusTitle">Memproses Analisis ML</h2>
            <p id="statusDescription">Mohon tunggu, foto laporanmu sedang dianalisis oleh model Machine Learning.</p>
        </section>

        <section id="resultCard" class="result" aria-label="Ringkasan hasil analisis ML" hidden>
            <!-- Prominent Condition Box: RUSAK vs BAIK vs TERHALANG vs SPAM -->
            <div id="conditionCard" class="condition-card" data-condition="RUSAK">
                <div class="condition-header">
                    <span class="label" style="margin: 0;">Hasil Deteksi Kondisi</span>
                    <span id="conditionBadge" class="condition-badge">RUSAK</span>
                </div>
                <div id="conditionTitle" class="condition-title">Kondisi: Terdeteksi Rusak</div>
                <p id="conditionDesc" class="condition-desc">Model Machine Learning mengklasifikasikan fasilitas dalam kondisi mengalami kerusakan fisik.</p>
            </div>

            <!-- Kategori Informasi -->
            <div>
                <span id="categoryLabel" class="label">Kategori Fasilitas</span>
                <div id="categoryValue" class="value">Belum tersedia</div>
            </div>

            <!-- ML Confidence Metric -->
            <div class="metric">
                <div class="metric-row">
                    <div>
                        <span class="label">Tingkat Keyakinan ML (YOLOv8)</span>
                        <span id="confidenceBadge" class="badge">Belum tersedia</span>
                    </div>
                    <span id="confidencePercent" class="percent">—</span>
                </div>
                <div id="confidenceTrack" class="track" role="progressbar" aria-label="Tingkat keyakinan ML" aria-valuemin="0" aria-valuemax="100" hidden>
                    <div id="confidenceBar" class="fill"></div>
                </div>
                <p id="confidenceHint" class="hint">Keyakinan model Machine Learning terhadap hasil klasifikasi kondisi.</p>
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
        DIPROSES: 'Sedang dianalisis ML',
        MENUNGGU_VALIDASI_ADMIN: 'Menunggu verifikasi petugas',
        TERVALIDASI: 'Laporan tervalidasi',
        DITOLAK: 'Laporan ditolak',
        SEDANG_DIPERBAIKI: 'Sedang diperbaiki',
        SUDAH_DIPERBAIKI: 'Sudah diperbaiki'
    };

    const conditionConfig = {
        RUSAK: {
            badge: 'RUSAK',
            title: 'Kondisi: Terdeteksi Rusak',
            desc: 'Model Machine Learning mendeteksi adanya kerusakan fisik pada fasilitas pejalan kaki ini.',
            tone: 'RUSAK',
            hint: 'Tingkat keyakinan model terhadap indikasi kerusakan fisik fasilitas.'
        },
        BAIK: {
            badge: 'BAIK',
            title: 'Kondisi: Terdeteksi Baik (Normal)',
            desc: 'Model Machine Learning mengklasifikasikan fasilitas dalam kondisi baik atau tidak ditemukan kerusakan yang signifikan.',
            tone: 'BAIK',
            hint: 'Fasilitas terindikasi dalam kondisi normal. Laporan diteruskan ke petugas untuk verifikasi.'
        },
        TERHALANG: {
            badge: 'TERHALANG',
            title: 'Kondisi: Terdeteksi Terhalang',
            desc: 'Model Machine Learning mendeteksi fasilitas terhalang oleh parkir liar, PKL, atau hambatan lainnya.',
            tone: 'TERHALANG',
            hint: 'Tingkat keyakinan model terhadap indikasi halangan pada jalur pejalan kaki.'
        },
        SPAM: {
            badge: 'TIDAK RELEVAN',
            title: 'Kondisi: Bukan Fasilitas / Tidak Relevan',
            desc: 'Model Machine Learning mengindikasikan foto bukan merupakan fasilitas pejalan kaki yang valid.',
            tone: 'SPAM',
            hint: 'Foto perlu diperiksa lebih lanjut secara manual oleh petugas dinas.'
        }
    };

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
        const validation = [...history].reverse().find(item => ['TERVALIDASI', 'DITOLAK'].includes(item.status));
        const autoValidated = data.status === 'TERVALIDASI' && validation?.status === 'TERVALIDASI' &&
            /tervalidasi otomatis oleh computer vision/i.test(validation.catatan || '');

        let title = statusLabels[data.status] || 'Status sedang diperbarui';
        let description = 'Lihat perkembangan laporan melalui halaman Riwayat Laporan.';
        let tone = 'neutral';
        let icon = 'i';

        if (processing) {
            description = 'Foto sedang dianalisis oleh model Machine Learning. Status diperiksa otomatis setiap 3 detik.';
        } else if (data.status === 'TERVALIDASI') {
            title = autoValidated ? 'Tervalidasi Otomatis oleh ML' : 'Laporan Tervalidasi';
            description = autoValidated
                ? `Kondisi terdeteksi ${label || 'RUSAK'} dengan keyakinan ${percent}% (≥80%). Laporan langsung disetujui sistem.`
                : 'Laporan telah diverifikasi valid oleh petugas dinas dan masuk ke antrean perbaikan.';
            tone = 'valid'; icon = '✓';
        } else if (data.status === 'MENUNGGU_VALIDASI_ADMIN') {
            description = label === 'BAIK'
                ? 'Model ML mengklasifikasikan kondisi Baik/Normal. Petugas dinas akan memeriksa kembali laporanmu.'
                : label === 'SPAM'
                    ? 'Foto terindikasi tidak relevan. Petugas dinas akan memverifikasi secara langsung.'
                    : (hasScore && score < 0.8)
                        ? `Model ML mendeteksi kondisi ${label || 'Rusak'}, namun keyakinan (${percent}%) di bawah 80%. Petugas akan memverifikasi terlebih dahulu.`
                        : 'Laporan sudah diterima. Petugas dinas akan memeriksa hasil analisis sebelum menentukan keputusan.';
            tone = 'pending'; icon = '…';
        } else if (data.status === 'DITOLAK') {
            description = data.alasan_penolakan || 'Laporan tidak disetujui petugas. Lihat alasan pada riwayat status.';
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

        // Prominent Condition Box (Rusak vs Baik vs Terhalang)
        const conditionCard = el('conditionCard');
        if (label && conditionConfig[label]) {
            const cfg = conditionConfig[label];
            conditionCard.hidden = false;
            conditionCard.dataset.condition = cfg.tone;
            el('conditionBadge').textContent = cfg.badge;
            el('conditionTitle').textContent = cfg.title;
            el('conditionDesc').textContent = cfg.desc;
            el('confidenceHint').textContent = cfg.hint;
        } else if (data.status === 'TERVALIDASI') {
            conditionCard.hidden = false;
            conditionCard.dataset.condition = 'RUSAK';
            el('conditionBadge').textContent = 'RUSAK';
            el('conditionTitle').textContent = 'Kondisi: Terverifikasi Rusak';
            el('conditionDesc').textContent = 'Fasilitas terkonfirmasi mengalami kerusakan dan telah divalidasi sistem.';
            el('confidenceHint').textContent = 'Laporan terverifikasi valid dan diteruskan ke dinas terkait.';
        } else {
            conditionCard.hidden = true;
        }

        el('categoryLabel').textContent = data.status === 'TERVALIDASI' ? 'Kategori Terverifikasi' : 'Kategori Fasilitas';
        el('categoryValue').textContent = category;

        // Confidence Metrics
        if (hasScore) {
            let badgeText = 'Keyakinan Rendah';
            let badgeLevel = 'low';
            if (score >= 0.8) {
                badgeText = 'Sangat Yakin (≥80%)';
                badgeLevel = 'high';
            } else if (score >= 0.6) {
                badgeText = 'Cukup Yakin (60%-79%)';
                badgeLevel = 'mid';
            }
            el('confidenceBadge').textContent = badgeText;
            el('confidenceBadge').dataset.level = badgeLevel;
            el('confidencePercent').textContent = `${percent}%`;
            el('confidenceTrack').hidden = false;
            el('confidenceTrack').setAttribute('aria-valuenow', String(percent));
            el('confidenceBar').style.width = `${percent}%`;
        } else {
            el('confidenceBadge').textContent = 'Belum tersedia';
            el('confidenceBadge').removeAttribute('data-level');
            el('confidencePercent').textContent = '—';
            el('confidenceTrack').hidden = true;
        }

        el('reportSubtitle').textContent = `Hasil Analisis Otomatis \u2022 Laporan #LAP-${String(data.laporan_id ?? reportId).padStart(4, '0')}`;
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
                stopPolling();
                el('statusTitle').textContent = 'Laporan tidak tersedia';
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
