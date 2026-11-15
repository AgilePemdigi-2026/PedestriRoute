@extends('layouts.app')

@section('title', 'Cari Rute - PedestriRoute')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #routeMap { position: absolute; inset: 0; z-index: 10; }

    .route-pin { background: transparent !important; border: none !important; }
    .route-pin > div { animation: pinPop 0.3s ease-out both; }
    @keyframes pinPop {
        from { opacity: 0; transform: scale(0.4); }
        to   { opacity: 1; transform: scale(1); }
    }
    @media (prefers-reduced-motion: reduce) {
        .route-pin > div { animation: none; }
    }

    #routeSummary { transition: transform 0.3s ease-out, opacity 0.2s ease-out; }
</style>
@endpush

@section('content')
<div class="flex flex-col flex-1 pb-20">
    <div class="px-5 pt-6 pb-3">
        <h1 class="text-base font-extrabold text-slate-900 tracking-tight">Cari Rute</h1>
    </div>

    <!-- Panel input rute -->
    <div class="px-5 pb-3">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-3 space-y-2">
            <!-- Titik awal -->
            <div class="relative">
                <input id="originInput" type="text" autocomplete="off" placeholder="Titik awal"
                    class="w-full h-11 px-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-brand-500">
                <ul id="originList" class="hidden absolute left-0 right-0 top-full mt-1.5 bg-white rounded-2xl shadow-xl border border-slate-100 py-1.5 z-50 max-h-56 overflow-y-auto"></ul>
            </div>

            <!-- Titik tujuan -->
            <div class="relative">
                <input id="destInput" type="text" autocomplete="off" placeholder="Cari tujuan (kampus, halte, rumah sakit, taman)"
                    class="w-full h-11 px-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-brand-500">
                <ul id="destList" class="hidden absolute left-0 right-0 top-full mt-1.5 bg-white rounded-2xl shadow-xl border border-slate-100 py-1.5 z-50 max-h-56 overflow-y-auto"></ul>
            </div>

            <div class="grid grid-cols-2 gap-2 pt-1">
                <button type="button" data-pref="safest" class="pref-chip h-11 px-3 rounded-xl text-xs font-bold border transition">Rute Paling Aman</button>
                <button type="button" data-pref="fastest" class="pref-chip h-11 px-3 rounded-xl text-xs font-bold border transition">Rute Tercepat</button>
            </div>
        </div>
    </div>

    <!-- Peta + kartu ringkasan -->
    <div class="relative flex-1 min-h-[380px] mx-5 mb-4 rounded-2xl overflow-hidden border border-slate-200 shadow-sm">
        <div id="routeMap"></div>

        <div id="routeSummary" class="absolute bottom-3 inset-x-3 z-30 transform translate-y-[130%] opacity-0 pointer-events-none bg-white rounded-2xl shadow-2xl border border-slate-200/80 p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-baseline gap-2.5 min-w-0">
                    <span id="sumDistance" class="text-xl font-extrabold text-slate-900">-</span>
                    <span class="text-slate-300">•</span>
                    <span id="sumDuration" class="text-sm font-bold text-slate-600">-</span>
                </div>
                <span id="sumBadge" class="text-[10px] font-bold text-brand-800 bg-brand-50 border border-brand-200 px-1.5 py-0.5 rounded-md shrink-0">Rute Optimal</span>
            </div>

            <div class="mt-2 flex items-start gap-2">
                <span id="sumConditionDot" class="w-2.5 h-2.5 rounded-full bg-brand-500 mt-1 shrink-0"></span>
                <p id="sumCondition" class="text-xs text-slate-600 leading-snug">-</p>
            </div>

            <div class="mt-3 flex items-center gap-3 text-[11px] text-slate-500">
                <span class="flex items-center gap-1.5"><span class="w-5 h-1 rounded-full bg-brand-500"></span>Trotoar baik</span>
                <span class="flex items-center gap-1.5"><span class="w-5 border-t-[3px] border-dashed border-orange-500"></span>Dalam perbaikan</span>
            </div>

            <button type="button" id="btnStartNav"
                class="mt-3 w-full h-12 bg-brand-700 hover:bg-brand-800 text-white font-bold text-sm rounded-2xl shadow-sm transition active:scale-[0.98] flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m3 11 19-9-9 19-2-8-8-2z" />
                </svg>
                Mulai Navigasi
            </button>
        </div>
    </div>
</div>

<!-- Bottom Navigation Bar -->
<div class="fixed bottom-0 max-w-md w-full bg-white border-t border-slate-200 py-2 px-6 flex items-center justify-between z-40">
    <a href="/beranda-warga" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600 transition">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2L2 12h3v8h6v-6h2v6h6v-8h3L12 2z"/>
        </svg>
        <span class="text-[10px] tracking-tight">Peta</span>
    </a>
    <a href="/rute-pejalan-kaki" aria-current="page" class="flex flex-col items-center gap-1 text-brand-600 font-bold">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
        <span class="text-[10px] tracking-tight">Rute</span>
    </a>
    <a href="/lapor-kondisi" class="flex flex-col items-center gap-1 text-slate-700 hover:text-brand-600 transition">
        <div class="w-10 h-10 -mt-5 bg-brand-600 hover:bg-brand-700 rounded-full flex items-center justify-center text-white shadow-md shadow-teal-900/20 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
        </div>
        <span class="text-[10px] tracking-tight font-bold mt-0.5">Lapor</span>
    </a>
    <a href="/riwayat-laporan" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600 transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-[10px] tracking-tight">Riwayat</span>
    </a>
    <a href="/notifikasi" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600 transition">
        <span class="relative">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span data-notification-count aria-hidden="true" class="hidden absolute -top-1.5 -right-2 min-w-[16px] h-4 px-1 rounded-full bg-rose-600 text-white text-[9px] font-bold flex items-center justify-center"></span>
        </span>
        <span class="text-[10px] tracking-tight">Notifikasi</span>
    </a>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@include('partials.mulyorejo-service-area')
<script>
    const token = localStorage.getItem('token');
    if (!token) window.location.href = '/login';

    const WALK_SPEED_MPS = 1.25;

    const SAMPLE_PLACES = [
        { name: 'Kampus C Universitas Airlangga', type: 'Kampus',      lat: -7.2672, lng: 112.7846 },
        { name: 'RS Universitas Airlangga',       type: 'Rumah Sakit', lat: -7.2651, lng: 112.7897 },
        { name: 'Halte Bus Kertajaya Indah',      type: 'Halte Bus',   lat: -7.2706, lng: 112.7931 },
        { name: 'Taman Kertajaya Indah',          type: 'Taman',       lat: -7.2688, lng: 112.7992 },
        { name: 'Puskesmas Mulyorejo',            type: 'Faskes',      lat: -7.2625, lng: 112.7958 },
        { name: 'Halte Bus Mulyosari',            type: 'Halte Bus',   lat: -7.2598, lng: 112.8029 },
        { name: 'Pasar Mulyosari',                type: 'Pasar',       lat: -7.2605, lng: 112.7950 },
        { name: 'Masjid Agung Mulyorejo',         type: 'Ibadah',      lat: -7.2660, lng: 112.7965 },
        { name: 'Taman Mulyorejo',                type: 'Taman',       lat: -7.2680, lng: 112.8050 },
        { name: 'Gedung Kuliah Bersama Unair C',  type: 'Kampus',      lat: -7.2657, lng: 112.7862 },
    ];

    const map = L.map('routeMap', { attributionControl: false, zoomControl: false })
        .setView([-7.2663, 112.7965], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

    const serviceArea = PedestriRouteServiceArea.attach(map);
    setTimeout(() => {
        map.invalidateSize();
        map.fitBounds(serviceArea.bounds, { padding: [12, 12], animate: false });
    }, 200);

    const routeLayer  = L.layerGroup().addTo(map);
    const pinLayer    = L.layerGroup().addTo(map);

    let originMarker = null;
    let destMarker   = null;
    let lastRoute    = null;

    const state = {
        origin: null,
        dest: null,
        preference: 'safest',
    };

    function haversine(a, b) {
        const R = 6371000;
        const rad = (d) => d * Math.PI / 180;
        const dLat = rad(b[0] - a[0]);
        const dLng = rad(b[1] - a[1]);
        const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a[0])) * Math.cos(rad(b[0])) * Math.sin(dLng / 2) ** 2;
        return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
    }

    function pathLength(pts) {
        let sum = 0;
        for (let i = 1; i < pts.length; i++) sum += haversine(pts[i - 1], pts[i]);
        return sum;
    }

    function formatDistance(meters) {
        const value = Math.max(0, meters);
        if (value < 1000) return `${Math.round(value)} m`;
        return `${(value / 1000).toFixed(1).replace('.', ',')} km`;
    }

    function formatDuration(minutes) {
        const value = Math.max(0, minutes);
        if (value < 1) return '< 1 menit';
        if (value < 60) return `${Math.round(value)} menit`;
        const h = Math.floor(value / 60);
        const m = Math.round(value % 60);
        return m === 0 ? `${h} jam` : `${h} jam ${m} mnt`;
    }

    const CHIP_ON = {
        safest: ['bg-brand-700', 'text-white', 'border-brand-700'],
        fastest: ['bg-amber-400', 'text-slate-900', 'border-amber-400'],
    };
    const CHIP_OFF = ['bg-white', 'text-slate-600', 'border-slate-200'];

    function paintChips() {
        document.querySelectorAll('.pref-chip').forEach((c) => {
            const p = c.dataset.pref;
            const on = p === state.preference;
            c.classList.remove(...CHIP_OFF, ...CHIP_ON.safest, ...CHIP_ON.fastest);
            c.classList.add(...(on ? CHIP_ON[p] : CHIP_OFF));
        });
    }

    document.querySelectorAll('.pref-chip').forEach((c) => {
        c.addEventListener('click', () => {
            state.preference = c.dataset.pref;
            paintChips();
            drawRoute();
        });
    });
    paintChips();

    function makePin(letter, color) {
        return L.divIcon({
            className: 'route-pin',
            html: `<div style="width:30px;height:30px;border-radius:50%;background:${color};color:#fff;
                   border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);display:flex;
                   align-items:center;justify-content:center;font-weight:800;font-size:13px">${letter}</div>`,
            iconSize: [30, 30],
            iconAnchor: [15, 15],
        });
    }

    function setPoint(kind, point) {
        if (!serviceArea.contains(point.lat, point.lng)) {
            showToast('Titik berada di luar area layanan Kecamatan Mulyorejo.', 'error');
            return;
        }
        state[kind] = point;
        document.getElementById(kind === 'origin' ? 'originInput' : 'destInput').value = point.label;

        const old = kind === 'origin' ? originMarker : destMarker;
        if (old) pinLayer.removeLayer(old);

        const marker = L.marker([point.lat, point.lng], {
            icon: makePin(kind === 'origin' ? 'A' : 'B', kind === 'origin' ? '#0284c7' : '#004f46'),
        }).addTo(pinLayer);

        if (kind === 'origin') originMarker = marker; else destMarker = marker;

        drawRoute();
    }

    function showSummary(distanceM, durationMin, summaryData = {}) {
        document.getElementById('sumDistance').textContent = formatDistance(distanceM);
        document.getElementById('sumDuration').textContent = formatDuration(durationMin);

        const repairM = summaryData.repair_distance_m || 0;
        const avgScore = Number(summaryData.average_score ?? 100);
        const dot = document.getElementById('sumConditionDot');
        const text = document.getElementById('sumCondition');

        if (repairM > 0) {
            dot.className = 'w-2.5 h-2.5 rounded-full bg-orange-500 mt-1 shrink-0';
            text.textContent = `Melewati segmen yang sedang diperbaiki (sekitar ${Math.round(repairM)} m). Hati-hati di bagian bergaris putus.`;
        } else if (avgScore >= 70) {
            dot.className = 'w-2.5 h-2.5 rounded-full bg-brand-500 mt-1 shrink-0';
            text.textContent = `Jalur trotoar dalam kondisi baik (Skor kelayakan ${Math.round(avgScore)}).`;
        } else {
            dot.className = 'w-2.5 h-2.5 rounded-full bg-amber-500 mt-1 shrink-0';
            text.textContent = `Sebagian jalur perlu perhatian (Skor kelayakan ${Math.round(avgScore)}).`;
        }

        const card = document.getElementById('routeSummary');
        card.classList.remove('translate-y-[130%]', 'opacity-0', 'pointer-events-none');
        card.classList.add('translate-y-0', 'opacity-100');
    }

    document.getElementById('btnStartNav').addEventListener('click', () => {
        if (!state.origin || !state.dest) return;
        
        sessionStorage.setItem('pedestriroute:navigation', JSON.stringify({
            origin: state.origin,
            dest: state.dest,
            preference: state.preference,
        }));

        const q = new URLSearchParams({
            origin_lat: state.origin.lat,
            origin_lng: state.origin.lng,
            dest_lat: state.dest.lat,
            dest_lng: state.dest.lng,
            dest_label: state.dest.label || 'Tujuan',
            preference: state.preference,
            start: '1'
        });

        window.location.href = '/navigasi?' + q.toString();
    });

    async function drawRoute() {
        if (!state.origin || !state.dest) return;
        routeLayer.clearLayers();

        try {
            const res = await fetch('/api/v1/routes/recommend', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + token,
                },
                body: JSON.stringify({
                    origin: { lat: state.origin.lat, lng: state.origin.lng },
                    destination: { lat: state.dest.lat, lng: state.dest.lng },
                    preference: state.preference,
                }),
            });

            const data = await res.json();

            if (res.ok && data.success && Array.isArray(data.polyline) && data.polyline.length > 1) {
                lastRoute = data;

                (data.segments || []).forEach((seg) => {
                    if (!Array.isArray(seg.polyline) || seg.polyline.length < 2) return;
                    L.polyline(seg.polyline, { color: '#ffffff', weight: 10, opacity: 0.9, lineCap: 'round' }).addTo(routeLayer);
                    L.polyline(seg.polyline, {
                        color: seg.repair ? '#ea580c' : '#008775',
                        weight: 6,
                        lineCap: 'round',
                        dashArray: seg.repair ? '2 12' : null,
                    }).addTo(routeLayer);
                });

                map.fitBounds(L.latLngBounds(data.polyline), { paddingTopLeft: [28, 28], paddingBottomRight: [28, 230], maxZoom: 18 });
                showSummary(data.distance_m || 0, data.duration_min || 0, data.summary || {});
                return;
            }
        } catch (_) {}

        // Fallback calculation if server engine is offline
        const o = state.origin;
        const d = state.dest;
        const safest = state.preference !== 'fastest';
        const k = safest ? 0.45 : -0.06;
        const dx = d.lng - o.lng;
        const dy = d.lat - o.lat;
        const ctrl = [(o.lat + d.lat) / 2 - dx * k, (o.lng + d.lng) / 2 + dy * k];
        const pts = [];
        for (let i = 0; i <= 16; i++) {
            const t = i / 16;
            const u = 1 - t;
            pts.push([
                u * u * o.lat + 2 * u * t * ctrl[0] + t * t * d.lat,
                u * u * o.lng + 2 * u * t * ctrl[1] + t * t * d.lng,
            ]);
        }
        const total = pathLength(pts);
        L.polyline(pts, { color: '#ffffff', weight: 10, opacity: 0.9, lineCap: 'round' }).addTo(routeLayer);
        L.polyline(pts, { color: '#008775', weight: 6, lineCap: 'round' }).addTo(routeLayer);
        map.fitBounds(L.latLngBounds(pts), { paddingTopLeft: [28, 28], paddingBottomRight: [28, 230], maxZoom: 18 });
        showSummary(total, total / WALK_SPEED_MPS / 60);
    }

    function setupAutocomplete(kind, input, list) {
        function close() {
            list.classList.add('hidden');
        }

        function render(showAll = false) {
            const q = showAll ? '' : input.value.trim().toLowerCase();
            const items = SAMPLE_PLACES.filter((p) =>
                !q || p.name.toLowerCase().includes(q) || p.type.toLowerCase().includes(q));

            list.textContent = '';

            if (!items.length) {
                const li = document.createElement('li');
                li.className = 'px-4 py-3 text-xs text-slate-500';
                li.textContent = 'Tempat tidak ditemukan di area Mulyorejo.';
                list.appendChild(li);
            } else {
                items.forEach((p) => {
                    const li = document.createElement('li');
                    li.className = 'px-4 py-2.5 min-h-[44px] flex items-center justify-between gap-3 cursor-pointer hover:bg-brand-50 transition';

                    const name = document.createElement('span');
                    name.className = 'text-sm font-semibold text-slate-800 truncate';
                    name.textContent = p.name;

                    const type = document.createElement('span');
                    type.className = 'text-[10px] font-bold text-brand-700 bg-brand-50 border border-brand-100 px-1.5 py-0.5 rounded-md shrink-0';
                    type.textContent = p.type;

                    li.append(name, type);
                    li.addEventListener('mousedown', (e) => e.preventDefault());
                    li.addEventListener('click', () => {
                        close();
                        setPoint(kind, { lat: p.lat, lng: p.lng, label: p.name });
                    });
                    list.appendChild(li);
                });
            }

            list.classList.remove('hidden');
        }

        input.addEventListener('focus', () => {
            input.select();
            render(true);
        });
        input.addEventListener('input', () => render(false));
        input.addEventListener('blur', () => setTimeout(close, 120));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') close();
        });
    }

    setupAutocomplete('origin', document.getElementById('originInput'), document.getElementById('originList'));
    setupAutocomplete('dest', document.getElementById('destInput'), document.getElementById('destList'));

    // Default GPS location or Mulyorejo default sample point
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const { latitude, longitude } = pos.coords;
                if (serviceArea.contains(latitude, longitude)) {
                    setPoint('origin', { lat: latitude, lng: longitude, label: 'Lokasi Saya Saat Ini' });
                } else if (!state.origin) {
                    setPoint('origin', { lat: SAMPLE_PLACES[0].lat, lng: SAMPLE_PLACES[0].lng, label: SAMPLE_PLACES[0].name });
                }
            },
            () => {
                if (!state.origin) {
                    setPoint('origin', { lat: SAMPLE_PLACES[0].lat, lng: SAMPLE_PLACES[0].lng, label: SAMPLE_PLACES[0].name });
                }
            },
            { enableHighAccuracy: true, timeout: 6000 }
        );
    } else if (!state.origin) {
        setPoint('origin', { lat: SAMPLE_PLACES[0].lat, lng: SAMPLE_PLACES[0].lng, label: SAMPLE_PLACES[0].name });
    }
</script>
@endpush
