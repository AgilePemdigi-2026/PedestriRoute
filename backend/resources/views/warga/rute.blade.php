@extends('layouts.app')

@section('title', 'Cari Rute - PedestriRoute')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #routeMap { position: absolute; inset: 0; z-index: 10; background: #e2e8f0; }

    .route-pin { background: transparent !important; border: none !important; }
    .route-pin > div { animation: pinPop 0.28s ease-out both; }
    @keyframes pinPop {
        from { opacity: 0; transform: scale(0.4); }
        to   { opacity: 1; transform: scale(1); }
    }
    @media (prefers-reduced-motion: reduce) {
        .route-pin > div { animation: none; }
    }

    #routeSummary { transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.25s ease-out; }
    .custom-scrollbar::-webkit-scrollbar { width: 4px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
</style>
@endpush

@section('content')
<div class="relative flex-1 overflow-hidden h-[calc(100dvh-4.5rem)] md:flex md:flex-none md:h-[calc(100dvh-4rem)]">    <!-- Peta Full Screen -->
    <div id="routeMap" aria-label="Peta rute pejalan kaki" class="md:!relative md:!h-full md:!flex-1"></div>

    <!-- Loading Screen Overlay -->
    <div id="routeLoading" class="hidden absolute inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4 pointer-events-auto">
        <div class="bg-white rounded-3xl shadow-2xl border border-slate-100 p-6 max-w-xs w-full text-center space-y-3">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-brand-50 border border-brand-100 flex items-center justify-center shadow-inner">
                <svg class="w-7 h-7 text-brand-700 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <div>
                <p class="text-sm font-black text-slate-900">Mencari Rute Pejalan Kaki...</p>
                <p class="text-[11px] font-semibold text-slate-500 mt-1">Menganalisis trotoar aman & memetakan area laporan</p>
            </div>
        </div>
    </div>

    <div class="contents md:order-first md:flex md:w-[360px] lg:w-[400px] md:shrink-0 md:flex-col md:justify-start md:gap-4 md:p-4 md:overflow-y-auto md:border-r md:border-slate-200 md:bg-slate-50/50">
    <!-- Floating Top Search Card -->
    <div class="absolute inset-x-0 top-0 z-30 px-3 pt-3 pointer-events-none md:!static md:!inset-auto md:!p-0 md:!pointer-events-auto">
        <div class="pointer-events-auto bg-white/95 backdrop-blur-md rounded-2xl border border-slate-200/80 shadow-lg p-2.5 space-y-2">
            <!-- Form Titik Awal & Titik Tujuan with Swap Button -->
            <div class="relative flex items-center gap-1.5">
                <div class="flex-1 space-y-1.5 min-w-0">
                    <!-- Titik Awal -->
                    <div class="relative flex items-center bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1 focus-within:ring-2 focus-within:ring-brand-500 focus-within:border-brand-500">
                        <span class="w-5 h-5 rounded-full bg-sky-100 text-sky-700 flex items-center justify-center shrink-0 text-[10px] font-black">A</span>
                        <input id="originInput" type="text" autocomplete="off" maxlength="160" aria-label="Alamat titik awal" placeholder="Ketik alamat, jalan, atau lokasi awal…"
                            class="w-full bg-transparent ml-2 text-xs font-bold text-slate-800 placeholder:text-slate-400 focus:outline-none">
                        <button type="button" id="btnMyGps" title="Gunakan Lokasi GPS Saya"
                            class="p-1 text-sky-600 hover:text-sky-800 shrink-0 rounded-lg hover:bg-sky-50 transition active:scale-95">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="3" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v3m0 14v3M2 12h3m14 0h3" />
                            </svg>
                        </button>
                        <ul id="originList" class="hidden absolute left-0 right-0 top-full mt-1.5 bg-white rounded-2xl shadow-2xl border border-slate-100 py-1.5 z-50 max-h-52 overflow-y-auto custom-scrollbar"></ul>
                    </div>

                    <!-- Titik Tujuan -->
                    <div class="relative flex items-center bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1 focus-within:ring-2 focus-within:ring-brand-500 focus-within:border-brand-500">
                        <span class="w-5 h-5 rounded-full bg-brand-100 text-brand-800 flex items-center justify-center shrink-0 text-[10px] font-black">B</span>
                        <input id="destInput" type="text" autocomplete="off" maxlength="160" aria-label="Alamat tujuan" placeholder="Ketik alamat tujuan di Mulyorejo…"
                            class="w-full bg-transparent ml-2 text-xs font-bold text-slate-800 placeholder:text-slate-400 focus:outline-none">
                        <button type="button" id="btnClearDest" title="Hapus Tujuan"
                            class="hidden p-1 text-slate-400 hover:text-slate-600 shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        </button>
                        <ul id="destList" class="hidden absolute left-0 right-0 top-full mt-1.5 bg-white rounded-2xl shadow-2xl border border-slate-100 py-1.5 z-50 max-h-52 overflow-y-auto custom-scrollbar"></ul>
                    </div>
                </div>

                <!-- Swap Button -->
                <button type="button" id="btnSwap" title="Tukar Titik Awal & Tujuan"
                    class="w-8 h-14 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-600 flex items-center justify-center shrink-0 active:scale-95 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 4v12m0 0l4-4m-4 4l-4-4" />
                    </svg>
                </button>
            </div>

            <p class="text-[10px] text-slate-500">Ketik alamat atau nama jalan, lalu pilih rekomendasi lokasi di sekitar Mulyorejo.</p>

            <!-- Preference Toggle Pills -->
            <div class="flex items-center gap-1.5 pt-0.5">
                <div class="grid grid-cols-2 gap-1 flex-1 bg-slate-100 p-0.5 rounded-xl border border-slate-200">
                    <button type="button" data-pref="safest"
                        class="pref-chip h-7 px-2 rounded-lg text-[11px] font-extrabold border transition flex items-center justify-center gap-1 shadow-sm">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <span>Teraman</span>
                    </button>
                    <button type="button" data-pref="fastest"
                        class="pref-chip h-7 px-2 rounded-lg text-[11px] font-extrabold border transition flex items-center justify-center gap-1 shadow-sm">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span>Tercepat</span>
                    </button>
                </div>
                <span id="mapEngineBadge" class="hidden text-[10px] font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-1 rounded-lg">OSRM</span>
            </div>

            <!-- Confirm / Cari Rute Button -->
            <button type="button" id="btnFindRoute"
                class="w-full h-8 bg-brand-700 hover:bg-brand-800 text-white font-extrabold text-xs rounded-xl shadow-sm transition active:scale-[0.98] flex items-center justify-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <span id="btnFindRouteText">Cari Rute Pejalan Kaki</span>
            </button>

            <!-- Quick destination chips -->
            <div id="quickChipsBar" class="flex items-center gap-1 overflow-x-auto pb-0.5 text-[10px] custom-scrollbar">
                <span class="text-slate-400 font-bold shrink-0">Cepat:</span>
                <button type="button" class="quick-chip shrink-0 px-2 py-0.5 rounded-md bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-700 font-semibold border border-slate-200 transition" data-index="0">Unair C</button>
                <button type="button" class="quick-chip shrink-0 px-2 py-0.5 rounded-md bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-700 font-semibold border border-slate-200 transition" data-index="1">RS Unair</button>
                <button type="button" class="quick-chip shrink-0 px-2 py-0.5 rounded-md bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-700 font-semibold border border-slate-200 transition" data-index="4">Puskesmas</button>
                <button type="button" class="quick-chip shrink-0 px-2 py-0.5 rounded-md bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-700 font-semibold border border-slate-200 transition" data-index="6">Pasar</button>
                <button type="button" class="quick-chip shrink-0 px-2 py-0.5 rounded-md bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-700 font-semibold border border-slate-200 transition" data-index="8">Taman</button>
            </div>
            <p class="text-[9px] text-slate-400">Pencarian alamat: © OpenStreetMap contributors · Photon</p>
        </div>
    </div>

    <!-- Floating Bottom Summary Sheet -->
    <div id="routeSummary" class="absolute bottom-16 inset-x-3 z-30 transform translate-y-[130%] opacity-0 pointer-events-none bg-white/95 backdrop-blur-md rounded-2xl shadow-xl border border-slate-200/90 p-3 md:!static md:!inset-auto md:!w-full md:!p-4 md:!translate-y-0">
        <!-- Compact Main Bar: Stats & Mulai Button -->
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0 flex-1">
                <div class="flex items-baseline gap-2">
                    <span id="sumDuration" class="text-xl font-black text-brand-800 leading-none">-</span>
                    <span class="text-slate-300">•</span>
                    <span id="sumDistance" class="text-xs font-extrabold text-slate-700 leading-none">-</span>
                </div>
                <div class="flex items-center gap-2 mt-1">
                    <span id="sumBadge" class="text-[10px] font-extrabold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md shrink-0">
                        Sangat Layak
                    </span>
                    <button type="button" id="btnToggleDetails" class="text-[10px] font-bold text-slate-500 hover:text-brand-700 flex items-center gap-0.5">
                        <span id="toggleDetailsText">Detail</span>
                        <svg id="toggleDetailsIcon" class="w-3 h-3 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Action Button -->
            <button type="button" id="btnStartNav"
                class="shrink-0 h-10 px-4 bg-brand-700 hover:bg-brand-800 text-white font-extrabold text-xs rounded-xl shadow-md shadow-brand-900/20 transition active:scale-[0.98] flex items-center gap-1.5">
                <svg class="w-4 h-4 animate-bounce" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m3 11 19-9-9 19-2-8-8-2z" />
                </svg>
                <span>Mulai</span>
            </button>
        </div>

        <p id="routeRiskNotice" class="hidden mt-2 rounded-lg bg-orange-50 border border-orange-200 p-2 text-[11px] font-semibold text-orange-900" role="status"></p>

        <!-- Collapsible Details Drawer -->
        <div id="summaryDetails" class="hidden mt-2 pt-2 border-t border-slate-100 space-y-2">
            <!-- Warning / Avoidance Alert Box -->
            <div id="avoidanceAlert" class="hidden bg-amber-50 border border-amber-200/80 rounded-xl p-2 flex items-start gap-2">
                <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <p id="avoidanceText" class="text-xs font-semibold text-amber-900 leading-snug">-</p>
            </div>

            <p id="sumRouteDesc" class="text-[11px] font-semibold text-slate-500">Mengikuti jaringan jalan pejalan kaki resmi</p>

            <!-- Condition Status -->
            <div class="flex items-center justify-between text-xs text-slate-600">
                <div class="flex items-center gap-2">
                    <span id="sumConditionDot" class="w-2 h-2 rounded-full bg-brand-500 shrink-0"></span>
                    <span id="sumCondition" class="font-medium text-[11px]">-</span>
                </div>
                <span id="sumStepCount" class="text-[10px] text-slate-400 font-bold shrink-0">0 manuver</span>
            </div>
        </div>
    </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@include('partials.mulyorejo-service-area')
@include('partials.route-hazards')
@include('partials.route-location-search')
<script>
    const token = localStorage.getItem('token');
    if (!token) window.location.href = '/login';

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
        map.fitBounds(serviceArea.bounds, { padding: [16, 16], animate: false });
    }, 200);
    window.addEventListener('resize', () => map.invalidateSize());

    const routeLayer   = L.layerGroup().addTo(map);
    const pinLayer     = L.layerGroup().addTo(map);
    const obstacleLayer= L.layerGroup().addTo(map);
    const hazardLineLayer = L.layerGroup().addTo(map);

    let originMarker = null;
    let destMarker   = null;
    let lastRoute    = null;
    let routeRequest = null;
    let requestVersion = 0;
    let originTouched = false;
    let originRevision = 0;
    const locationSearches = [];

    const state = {
        origin: null,
        dest: null,
        preference: 'safest',
    };

    function formatDistance(meters) {
        const val = Math.max(0, meters);
        if (val < 1000) return `${Math.round(val)} m`;
        return `${(val / 1000).toFixed(1).replace('.', ',')} km`;
    }

    function formatDuration(minutes) {
        const val = Math.max(0, minutes);
        if (val < 1) return '< 1 menit';
        if (val < 60) return `${Math.round(val)} menit`;
        const h = Math.floor(val / 60);
        const m = Math.round(val % 60);
        return m === 0 ? `${h} jam` : `${h} jam ${m} mnt`;
    }

    const CHIP_ON = {
        safest: ['bg-brand-700', 'text-white', 'border-brand-700'],
        fastest: ['bg-amber-500', 'text-white', 'border-amber-500'],
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

    function clearActiveRoute() {
        requestVersion++;
        if (routeRequest) routeRequest.abort();
        hideLoading();
        routeLayer.clearLayers();
        obstacleLayer.clearLayers();
        hazardLineLayer.clearLayers();
        lastRoute = null;
        const card = document.getElementById('routeSummary');
        if (card) {
            card.classList.remove('translate-y-0', 'opacity-100');
            card.classList.add('translate-y-[130%]', 'opacity-0', 'pointer-events-none');
        }
        resetDetailsDrawer();
    }

    document.querySelectorAll('.pref-chip').forEach((c) => {
        c.addEventListener('click', () => {
            state.preference = c.dataset.pref;
            paintChips();
            // If a route was already calculated and displayed, update it with new preference
            if (state.origin && state.dest) {
                drawRoute();
            }
        });
    });
    paintChips();

    function makePin(letter, color) {
        return L.divIcon({
            className: 'route-pin',
            html: `<div style="width:32px;height:32px;border-radius:50%;background:${color};color:#fff;
                   border:3px solid #fff;box-shadow:0 3px 10px rgba(0,0,0,.35);display:flex;
                   align-items:center;justify-content:center;font-weight:900;font-size:14px">${letter}</div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 16],
        });
    }

    function showLoading() {
        const overlay = document.getElementById('routeLoading');
        if (overlay) overlay.classList.remove('hidden');
        const btnText = document.getElementById('btnFindRouteText');
        const btn = document.getElementById('btnFindRoute');
        if (btn) btn.disabled = true;
        if (btnText) btnText.textContent = 'Mencari Rute...';
    }

    function hideLoading() {
        const overlay = document.getElementById('routeLoading');
        if (overlay) overlay.classList.add('hidden');
        const btnText = document.getElementById('btnFindRouteText');
        const btn = document.getElementById('btnFindRoute');
        if (btn) btn.disabled = false;
        if (btnText) btnText.textContent = 'Cari Rute Pejalan Kaki';
    }

    function setPoint(kind, point, autoDraw = false) {
        if (!Number.isFinite(point.lat) || !Number.isFinite(point.lng) || !serviceArea.contains(point.lat, point.lng)) {
            showToast('Titik berada di luar area layanan Kecamatan Mulyorejo.', 'error');
            return false;
        }
        if (kind === 'origin') { originTouched = true; originRevision++; }
        locationSearches.forEach(search => search.close());
        state[kind] = point;
        const inp = document.getElementById(kind === 'origin' ? 'originInput' : 'destInput');
        inp.value = point.label;

        if (kind === 'dest') {
            document.getElementById('btnClearDest').classList.remove('hidden');
        }

        updatePointMarker(kind);

        // Clear any old route to require user to press "Cari Rute" button
        clearActiveRoute();

        if (autoDraw && state.origin && state.dest) {
            drawRoute();
        }
        return true;
    }

    function updatePointMarker(kind) {
        const old = kind === 'origin' ? originMarker : destMarker;
        if (old) pinLayer.removeLayer(old);
        if (kind === 'origin') originMarker = null; else destMarker = null;
        const point = state[kind];
        if (!point) return;

        const marker = L.marker([point.lat, point.lng], {
            icon: makePin(kind === 'origin' ? 'A' : 'B', kind === 'origin' ? '#0284c7' : '#008775'),
            draggable: true,
            title: kind === 'origin' ? 'Geser titik awal' : 'Geser titik tujuan',
        }).addTo(pinLayer);
        marker.on('dragstart', clearActiveRoute);
        marker.on('dragend', () => {
            const position = marker.getLatLng();
            const accepted = setPoint(kind, {
                lat: position.lat, lng: position.lng,
                label: `${kind === 'origin' ? 'Titik awal' : 'Tujuan'} pilihan (${position.lat.toFixed(5)}, ${position.lng.toFixed(5)})`,
            });
            if (!accepted) marker.setLatLng([point.lat, point.lng]);
        });

        if (kind === 'origin') originMarker = marker; else destMarker = marker;
    }

    // Confirm button "Cari Rute"
    const btnFindRoute = document.getElementById('btnFindRoute');
    if (btnFindRoute) {
        btnFindRoute.addEventListener('click', () => {
            if (!state.origin) {
                showToast('Silakan tentukan titik awal terlebih dahulu.', 'info');
                document.getElementById('originInput').focus();
                return;
            }
            if (!state.dest) {
                showToast('Silakan pilih titik tujuan terlebih dahulu.', 'info');
                document.getElementById('destInput').focus();
                return;
            }
            drawRoute();
        });
    }

    // Quick chips
    document.querySelectorAll('.quick-chip').forEach((btn) => {
        btn.addEventListener('click', () => {
            const idx = parseInt(btn.dataset.index, 10);
            const p = SAMPLE_PLACES[idx];
            if (p) setPoint('dest', { lat: p.lat, lng: p.lng, label: p.name }, false);
        });
    });

    function resetDetailsDrawer() {
        const drawer = document.getElementById('summaryDetails');
        const icon = document.getElementById('toggleDetailsIcon');
        const text = document.getElementById('toggleDetailsText');
        if (drawer) drawer.classList.add('hidden');
        if (icon) icon.classList.remove('rotate-180');
        if (text) text.textContent = 'Detail';
    }

    const btnToggleDetails = document.getElementById('btnToggleDetails');
    if (btnToggleDetails) {
        btnToggleDetails.addEventListener('click', () => {
            const drawer = document.getElementById('summaryDetails');
            const icon = document.getElementById('toggleDetailsIcon');
            const text = document.getElementById('toggleDetailsText');
            if (!drawer) return;
            const isHidden = drawer.classList.contains('hidden');
            if (isHidden) {
                drawer.classList.remove('hidden');
                if (icon) icon.classList.add('rotate-180');
                if (text) text.textContent = 'Tutup';
            } else {
                drawer.classList.add('hidden');
                if (icon) icon.classList.remove('rotate-180');
                if (text) text.textContent = 'Detail';
            }
        });
    }

    // Clear dest
    document.getElementById('btnClearDest').addEventListener('click', () => {
        state.dest = null;
        document.getElementById('destInput').value = '';
        document.getElementById('btnClearDest').classList.add('hidden');
        if (destMarker) pinLayer.removeLayer(destMarker);
        clearActiveRoute();
    });

    // Swap button
    document.getElementById('btnSwap').addEventListener('click', () => {
        if (!state.origin && !state.dest) return;
        locationSearches.forEach(search => search.close());
        const temp = state.origin;
        state.origin = state.dest;
        state.dest = temp;

        document.getElementById('originInput').value = state.origin ? state.origin.label : '';
        document.getElementById('destInput').value = state.dest ? state.dest.label : '';
        if (state.dest) document.getElementById('btnClearDest').classList.remove('hidden');
        else document.getElementById('btnClearDest').classList.add('hidden');

        pinLayer.clearLayers();
        originTouched = true;
        originRevision++;
        updatePointMarker('origin');
        updatePointMarker('dest');

        clearActiveRoute();
    });

    // GPS Origin
    document.getElementById('btnMyGps').addEventListener('click', () => {
        const revision = originRevision;
        if (!navigator.geolocation) {
            showToast('GPS tidak tersedia. Ketik alamat titik awal dan pilih rekomendasi.', 'info');
            document.getElementById('originInput').focus();
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                if (revision !== originRevision) return;
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                if (!serviceArea.contains(lat, lng)) {
                    showToast('GPS Anda di luar area layanan. Cari alamat di sekitar Mulyorejo.', 'info');
                    document.getElementById('originInput').focus();
                } else {
                    setPoint('origin', { lat, lng, label: 'Lokasi Anda' }, !!state.dest);
                }
            },
            () => {
                if (revision !== originRevision) return;
                showToast('Akses GPS tidak diizinkan. Ketik alamat titik awal Anda.', 'info');
                document.getElementById('originInput').focus();
            },
            { enableHighAccuracy: true, timeout: 5000 }
        );
    });

    // Start navigation button
    document.getElementById('btnStartNav').addEventListener('click', () => {
        if (!state.origin || !state.dest || !lastRoute) return;
        
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

    function showSummary(distanceM, durationMin, summaryData = {}, warnings = [], steps = []) {
        document.getElementById('sumDistance').textContent = formatDistance(distanceM);
        document.getElementById('sumDuration').textContent = formatDuration(durationMin);
        document.getElementById('sumStepCount').textContent = `${steps.length} langkah manuver`;

        const repairM = summaryData.repair_distance_m || 0;
        const avgScore = summaryData.average_score == null ? null : Number(summaryData.average_score);
        const reportCount = Number(summaryData.report_count || 0);
        const coverage = Number(summaryData.score_coverage || 0);
        const dot = document.getElementById('sumConditionDot');
        const text = document.getElementById('sumCondition');
        const badge = document.getElementById('sumBadge');
        const alertBox = document.getElementById('avoidanceAlert');
        const alertText = document.getElementById('avoidanceText');

        const reportWarning = warnings.find(w => w.code === 'REPORT_ON_ROUTE');
        const snapWarning = warnings.find(w => w.code === 'ENDPOINT_SNAPPED');
        const fallbackWarning = warnings.find(w => w.code === 'SAFEST_AVAILABLE_WITH_REPORTS');
        const riskNotice = document.getElementById('routeRiskNotice');
        riskNotice.classList.toggle('hidden', !fallbackWarning);
        riskNotice.textContent = fallbackWarning?.message || '';
        if (reportWarning) {
            alertBox.classList.remove('hidden');
            alertText.textContent = [reportWarning.message, snapWarning?.message].filter(Boolean).join(' ');
            badge.textContent = fallbackWarning ? `Teraman tersedia · ${reportCount} laporan`
                : (summaryData.endpoint_report_count === reportCount ? 'Laporan di awal/tujuan' : `${reportCount} laporan pada rute`);
            badge.className = 'text-[10px] font-extrabold text-orange-800 bg-orange-50 border border-orange-200 px-2 py-0.5 rounded-md shrink-0';
        } else {
            alertBox.classList.add('hidden');
            if (repairM > 0) {
                badge.textContent = '⚠️ Ada Perbaikan';
                badge.className = 'text-[10px] font-extrabold text-orange-800 bg-orange-50 border border-orange-200 px-2 py-0.5 rounded-md shrink-0';
            } else if (avgScore !== null && avgScore >= 70 && coverage >= 0.8) {
                badge.textContent = 'Sangat Layak';
                badge.className = 'text-[10px] font-extrabold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md shrink-0';
            } else {
                badge.textContent = 'Tanpa laporan pada rute';
                badge.className = 'text-[10px] font-extrabold text-amber-800 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-md shrink-0';
            }
        }
        if (snapWarning && !reportWarning) {
            alertBox.classList.remove('hidden');
            alertText.textContent = snapWarning.message;
        }

        if (reportCount > 0) {
            dot.className = 'w-2 h-2 rounded-full bg-orange-500 shrink-0';
            text.textContent = `Melewati ${reportCount} laporan aktif. Area terdampak bergaris putus-putus.`;
        } else if (repairM > 0) {
            dot.className = 'w-2 h-2 rounded-full bg-orange-500 shrink-0';
            text.textContent = `Melewati sekitar ${Math.round(repairM)} m jalur perbaikan aktif.`;
        } else if (avgScore !== null && avgScore >= 70 && coverage >= 0.8) {
            dot.className = 'w-2 h-2 rounded-full bg-emerald-500 shrink-0';
            text.textContent = `Kelayakan jalur ${Math.round(avgScore)}%. Tidak melewati area laporan aktif.`;
        } else {
            dot.className = 'w-2 h-2 rounded-full bg-amber-500 shrink-0';
            text.textContent = avgScore === null || coverage < 0.8
                ? 'Tidak melewati area laporan aktif. Data kondisi jalur belum lengkap.'
                : `Kelayakan ${Math.round(avgScore)}%. Berjalanlah dengan hati-hati.`;
        }

        resetDetailsDrawer();

        const card = document.getElementById('routeSummary');
        card.classList.remove('translate-y-[130%]', 'opacity-0', 'pointer-events-none');
        card.classList.add('translate-y-0', 'opacity-100');
    }

    async function drawRoute() {
        if (!state.origin || !state.dest) return;
        clearActiveRoute();
        const version = requestVersion;
        routeRequest = new AbortController();
        showLoading();

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
                signal: routeRequest.signal,
            });

            const data = await res.json();
            if (version !== requestVersion) return;

            if (res.ok && data.success && Array.isArray(data.polyline) && data.polyline.length > 1) {
                lastRoute = data;

                // 1. Draw casing and main route line
                L.polyline(data.polyline, {
                    color: '#ffffff',
                    weight: 10,
                    opacity: 0.95,
                    lineCap: 'round',
                    lineJoin: 'round'
                }).addTo(routeLayer);

                L.polyline(data.polyline, {
                    color: '#008775',
                    weight: 6,
                    lineCap: 'round',
                    lineJoin: 'round'
                }).addTo(routeLayer);

                PedestriRouteHazards.render(data, hazardLineLayer, obstacleLayer);
                [['origin', data.snapped_origin], ['destination', data.snapped_destination]].forEach(([kind, point]) => {
                    if (!point || (data.snap_distances_m?.[kind] || 0) <= 40) return;
                    const title = `${kind === 'origin' ? 'Awal' : 'Akhir'} jalur pejalan kaki (${Math.round(data.snap_distances_m[kind])} m dari pin)`;
                    L.circleMarker([point.lat, point.lng], { radius: 6, color: '#0284c7', fillColor: '#fff', fillOpacity: 1, weight: 3 })
                        .addTo(routeLayer).bindTooltip(title);
                });

                map.fitBounds(L.latLngBounds(data.polyline), {
                    paddingTopLeft: [20, 250],
                    paddingBottomRight: [20, 80],
                    maxZoom: 17
                });

                showSummary(data.distance_m || 0, data.duration_min || 0, data.summary || {}, data.warnings || [], data.steps || []);
                return;
            } else {
                showToast(data.error?.message || data.message || 'Rute tidak ditemukan untuk titik yang dipilih.', 'error');
            }
        } catch (error) {
            if (error.name === 'AbortError' || version !== requestVersion) return;
            showToast('Gagal memuat rute pejalan kaki. Coba beberapa saat lagi.', 'error');
        } finally {
            if (version === requestVersion) hideLoading();
        }
    }

    ['origin', 'dest'].forEach(kind => locationSearches.push(PedestriRouteLocationSearch.attach({
        input: document.getElementById(kind === 'origin' ? 'originInput' : 'destInput'),
        list: document.getElementById(kind === 'origin' ? 'originList' : 'destList'),
        kind, token, places: SAMPLE_PLACES, contains: serviceArea.contains,
        onChoose: (kind, place) => {
            if (setPoint(kind, { lat: place.lat, lng: place.lng, label: place.label || place.name })) {
                map.setView([place.lat, place.lng], Math.max(map.getZoom(), 16));
            }
        },
        onEdit: kind => {
            if (kind === 'origin') { originTouched = true; originRevision++; }
            state[kind] = null;
            updatePointMarker(kind);
            clearActiveRoute();
        },
    })));

    // Auto-detect GPS location on startup
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                if (!originTouched && serviceArea.contains(lat, lng)) {
                    setPoint('origin', { lat, lng, label: 'Lokasi Anda' }, false);
                }
            },
            () => {},
            { enableHighAccuracy: true, timeout: 5000 }
        );
    }

    // Check query params if redirected from elsewhere
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('dest_lat') && urlParams.has('dest_lng')) {
        setPoint('dest', {
            lat: parseFloat(urlParams.get('dest_lat')),
            lng: parseFloat(urlParams.get('dest_lng')),
            label: urlParams.get('dest_label') || 'Titik Tujuan'
        });
    }
</script>
@endpush
