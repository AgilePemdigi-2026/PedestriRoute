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
<div class="relative flex-1 overflow-hidden" style="height: calc(100dvh - 4.5rem);">
    <!-- Peta Full Screen -->
    <div id="routeMap" aria-label="Peta rute pejalan kaki"></div>

    <!-- Floating Top Search Card -->
    <div class="absolute inset-x-0 top-0 z-30 px-3 pt-3 pointer-events-none">
        <div class="pointer-events-auto bg-white/95 backdrop-blur-md rounded-2xl border border-slate-200/80 shadow-xl shadow-slate-900/10 p-3 space-y-2.5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-brand-500 animate-pulse"></span>
                    <span class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Rute Pejalan Kaki OSRM</span>
                </div>
                <span id="mapEngineBadge" class="text-[10px] font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">Jalur Nyata</span>
            </div>

            <!-- Form Titik Awal & Titik Tujuan with Swap Button -->
            <div class="relative flex items-center gap-2">
                <div class="flex-1 space-y-1.5 min-w-0">
                    <!-- Titik Awal -->
                    <div class="relative flex items-center bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 focus-within:ring-2 focus-within:ring-brand-500 focus-within:border-brand-500">
                        <span class="w-6 h-6 rounded-full bg-sky-100 text-sky-700 flex items-center justify-center shrink-0 text-xs font-black">A</span>
                        <input id="originInput" type="text" autocomplete="off" placeholder="Titik awal (Lokasi Saya)"
                            class="w-full bg-transparent ml-2 text-xs font-bold text-slate-800 placeholder:text-slate-400 focus:outline-none">
                        <button type="button" id="btnMyGps" title="Gunakan Lokasi GPS Saya"
                            class="p-1 text-sky-600 hover:text-sky-800 shrink-0 rounded-lg hover:bg-sky-50 transition active:scale-95">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="3" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v3m0 14v3M2 12h3m14 0h3" />
                            </svg>
                        </button>
                        <ul id="originList" class="hidden absolute left-0 right-0 top-full mt-1.5 bg-white rounded-2xl shadow-2xl border border-slate-100 py-1.5 z-50 max-h-52 overflow-y-auto custom-scrollbar"></ul>
                    </div>

                    <!-- Titik Tujuan -->
                    <div class="relative flex items-center bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 focus-within:ring-2 focus-within:ring-brand-500 focus-within:border-brand-500">
                        <span class="w-6 h-6 rounded-full bg-brand-100 text-brand-800 flex items-center justify-center shrink-0 text-xs font-black">B</span>
                        <input id="destInput" type="text" autocomplete="off" placeholder="Pilih tujuan di Mulyorejo..."
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
                    class="w-9 h-16 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-600 flex items-center justify-center shrink-0 active:scale-95 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4m6 4v12m0 0l4-4m-4 4l-4-4" />
                    </svg>
                </button>
            </div>

            <!-- Preference Toggle Pills -->
            <div class="grid grid-cols-2 gap-2 pt-0.5">
                <button type="button" data-pref="safest"
                    class="pref-chip h-9 px-3 rounded-xl text-xs font-extrabold border transition flex items-center justify-center gap-1.5 shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span>Teraman (Hindari Bahaya)</span>
                </button>
                <button type="button" data-pref="fastest"
                    class="pref-chip h-9 px-3 rounded-xl text-xs font-extrabold border transition flex items-center justify-center gap-1.5 shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>Tercepat</span>
                </button>
            </div>

            <!-- Quick destination chips -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-[11px] custom-scrollbar">
                <span class="text-slate-400 font-bold shrink-0">Cepat:</span>
                <button type="button" class="quick-chip shrink-0 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-700 font-semibold border border-slate-200 transition" data-index="0">Unair C</button>
                <button type="button" class="quick-chip shrink-0 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-700 font-semibold border border-slate-200 transition" data-index="1">RS Unair</button>
                <button type="button" class="quick-chip shrink-0 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-700 font-semibold border border-slate-200 transition" data-index="4">Puskesmas</button>
                <button type="button" class="quick-chip shrink-0 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-700 font-semibold border border-slate-200 transition" data-index="6">Pasar</button>
                <button type="button" class="quick-chip shrink-0 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-700 font-semibold border border-slate-200 transition" data-index="8">Taman</button>
            </div>
        </div>
    </div>

    <!-- Floating Bottom Summary Sheet -->
    <div id="routeSummary" class="absolute bottom-16 inset-x-3 z-30 transform translate-y-[130%] opacity-0 pointer-events-none bg-white/95 backdrop-blur-md rounded-2xl shadow-2xl border border-slate-200/90 p-4">
        <!-- Top Stats Row -->
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="flex items-baseline gap-2">
                    <span id="sumDuration" class="text-2xl font-black text-brand-800 leading-none">-</span>
                    <span class="text-slate-300">•</span>
                    <span id="sumDistance" class="text-base font-extrabold text-slate-800">-</span>
                </div>
                <p id="sumRouteDesc" class="text-[11px] font-semibold text-slate-500 mt-1">Mengikuti jaringan jalan pejalan kaki resmi</p>
            </div>
            <span id="sumBadge" class="text-[11px] font-extrabold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-lg shrink-0">
                Sangat Layak
            </span>
        </div>

        <!-- Warning / Avoidance Alert Box -->
        <div id="avoidanceAlert" class="hidden mt-2.5 bg-amber-50 border border-amber-200/80 rounded-xl p-2.5 flex items-start gap-2">
            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p id="avoidanceText" class="text-xs font-semibold text-amber-900 leading-snug">-</p>
        </div>

        <!-- Condition Status -->
        <div class="mt-2.5 flex items-center justify-between text-xs text-slate-600 border-t border-slate-100 pt-2">
            <div class="flex items-center gap-2">
                <span id="sumConditionDot" class="w-2.5 h-2.5 rounded-full bg-brand-500 shrink-0"></span>
                <span id="sumCondition" class="font-medium">-</span>
            </div>
            <span id="sumStepCount" class="text-[11px] text-slate-400 font-bold shrink-0">0 manuver</span>
        </div>

        <!-- Action Button -->
        <button type="button" id="btnStartNav"
            class="mt-3.5 w-full h-12 bg-brand-700 hover:bg-brand-800 text-white font-extrabold text-sm rounded-xl shadow-lg shadow-brand-900/20 transition active:scale-[0.98] flex items-center justify-center gap-2">
            <svg class="w-5 h-5 animate-bounce" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="m3 11 19-9-9 19-2-8-8-2z" />
            </svg>
            Mulai Navigasi Turn-by-Turn
        </button>
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

    const routeLayer   = L.layerGroup().addTo(map);
    const pinLayer     = L.layerGroup().addTo(map);
    const obstacleLayer= L.layerGroup().addTo(map);

    let originMarker = null;
    let destMarker   = null;
    let lastRoute    = null;

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
            html: `<div style="width:32px;height:32px;border-radius:50%;background:${color};color:#fff;
                   border:3px solid #fff;box-shadow:0 3px 10px rgba(0,0,0,.35);display:flex;
                   align-items:center;justify-content:center;font-weight:900;font-size:14px">${letter}</div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 16],
        });
    }

    function makeObstaclePin(label) {
        return L.divIcon({
            className: 'route-pin',
            html: `<div style="width:26px;height:26px;border-radius:50%;background:#ea580c;color:#fff;
                   border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);display:flex;
                   align-items:center;justify-content:center;font-size:12px">⚠️</div>`,
            iconSize: [26, 26],
            iconAnchor: [13, 13],
        });
    }

    function setPoint(kind, point) {
        if (!serviceArea.contains(point.lat, point.lng)) {
            showToast('Titik berada di luar area layanan Kecamatan Mulyorejo.', 'error');
            return;
        }
        state[kind] = point;
        const inp = document.getElementById(kind === 'origin' ? 'originInput' : 'destInput');
        inp.value = point.label;

        if (kind === 'dest') {
            document.getElementById('btnClearDest').classList.remove('hidden');
        }

        const old = kind === 'origin' ? originMarker : destMarker;
        if (old) pinLayer.removeLayer(old);

        const marker = L.marker([point.lat, point.lng], {
            icon: makePin(kind === 'origin' ? 'A' : 'B', kind === 'origin' ? '#0284c7' : '#008775'),
        }).addTo(pinLayer);

        if (kind === 'origin') originMarker = marker; else destMarker = marker;

        drawRoute();
    }

    // Quick chips
    document.querySelectorAll('.quick-chip').forEach((btn) => {
        btn.addEventListener('click', () => {
            const idx = parseInt(btn.dataset.index, 10);
            const p = SAMPLE_PLACES[idx];
            if (p) setPoint('dest', { lat: p.lat, lng: p.lng, label: p.name });
        });
    });

    // Clear dest
    document.getElementById('btnClearDest').addEventListener('click', () => {
        state.dest = null;
        document.getElementById('destInput').value = '';
        document.getElementById('btnClearDest').classList.add('hidden');
        if (destMarker) pinLayer.removeLayer(destMarker);
        routeLayer.clearLayers();
        obstacleLayer.clearLayers();
        const card = document.getElementById('routeSummary');
        card.classList.remove('translate-y-0', 'opacity-100');
        card.classList.add('translate-y-[130%]', 'opacity-0', 'pointer-events-none');
    });

    // Swap button
    document.getElementById('btnSwap').addEventListener('click', () => {
        if (!state.origin && !state.dest) return;
        const temp = state.origin;
        state.origin = state.dest;
        state.dest = temp;

        document.getElementById('originInput').value = state.origin ? state.origin.label : '';
        document.getElementById('destInput').value = state.dest ? state.dest.label : '';
        if (state.dest) document.getElementById('btnClearDest').classList.remove('hidden');
        else document.getElementById('btnClearDest').classList.add('hidden');

        pinLayer.clearLayers();
        if (state.origin) {
            originMarker = L.marker([state.origin.lat, state.origin.lng], {
                icon: makePin('A', '#0284c7'),
            }).addTo(pinLayer);
        }
        if (state.dest) {
            destMarker = L.marker([state.dest.lat, state.dest.lng], {
                icon: makePin('B', '#008775'),
            }).addTo(pinLayer);
        }

        drawRoute();
    });

    // GPS Origin
    document.getElementById('btnMyGps').addEventListener('click', () => {
        if (!navigator.geolocation) {
            setPoint('origin', { lat: SAMPLE_PLACES[0].lat, lng: SAMPLE_PLACES[0].lng, label: 'Kampus C UNAIR (Default)' });
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                if (!serviceArea.contains(lat, lng)) {
                    showToast('GPS Anda di luar Mulyorejo. Menggunakan titik awal Kampus C UNAIR.', 'info');
                    setPoint('origin', { lat: SAMPLE_PLACES[0].lat, lng: SAMPLE_PLACES[0].lng, label: 'Kampus C UNAIR (Default)' });
                } else {
                    setPoint('origin', { lat, lng, label: 'Lokasi Saya Saat Ini' });
                }
            },
            () => {
                showToast('Akses GPS ditolak. Menggunakan titik awal Kampus C UNAIR.', 'info');
                setPoint('origin', { lat: SAMPLE_PLACES[0].lat, lng: SAMPLE_PLACES[0].lng, label: 'Kampus C UNAIR (Default)' });
            },
            { enableHighAccuracy: true, timeout: 5000 }
        );
    });

    // Start navigation button
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

    function showSummary(distanceM, durationMin, summaryData = {}, warnings = [], steps = []) {
        document.getElementById('sumDistance').textContent = formatDistance(distanceM);
        document.getElementById('sumDuration').textContent = formatDuration(durationMin);
        document.getElementById('sumStepCount').textContent = `${steps.length} langkah manuver`;

        const repairM = summaryData.repair_distance_m || 0;
        const avgScore = Number(summaryData.average_score ?? 100);
        const dot = document.getElementById('sumConditionDot');
        const text = document.getElementById('sumCondition');
        const badge = document.getElementById('sumBadge');
        const alertBox = document.getElementById('avoidanceAlert');
        const alertText = document.getElementById('avoidanceText');

        // Check if an obstacle was avoided
        const avoidedWarn = warnings.find(w => w.code === 'REPORT_AVOIDED');
        if (avoidedWarn) {
            alertBox.classList.remove('hidden');
            alertText.textContent = avoidedWarn.message;
            badge.textContent = '🛡️ Bahaya Dihindari';
            badge.className = 'text-[11px] font-extrabold text-brand-800 bg-brand-50 border border-brand-200 px-2.5 py-1 rounded-lg shrink-0';
        } else {
            alertBox.classList.add('hidden');
            if (repairM > 0) {
                badge.textContent = '⚠️ Ada Perbaikan';
                badge.className = 'text-[11px] font-extrabold text-orange-800 bg-orange-50 border border-orange-200 px-2.5 py-1 rounded-lg shrink-0';
            } else if (avgScore >= 70) {
                badge.textContent = 'Sangat Layak';
                badge.className = 'text-[11px] font-extrabold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-lg shrink-0';
            } else {
                badge.textContent = 'Perlu Perhatian';
                badge.className = 'text-[11px] font-extrabold text-amber-800 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-lg shrink-0';
            }
        }

        if (repairM > 0) {
            dot.className = 'w-2.5 h-2.5 rounded-full bg-orange-500 shrink-0';
            text.textContent = `Melewati sekitar ${Math.round(repairM)} m jalur perbaikan aktif.`;
        } else if (avgScore >= 70) {
            dot.className = 'w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0';
            text.textContent = `Trotoar baik (Kelayakan ${Math.round(avgScore)}%). Rute aman.`;
        } else {
            dot.className = 'w-2.5 h-2.5 rounded-full bg-amber-500 shrink-0';
            text.textContent = `Kelayakan ${Math.round(avgScore)}%. Berjalanlah dengan hati-hati.`;
        }

        const card = document.getElementById('routeSummary');
        card.classList.remove('translate-y-[130%]', 'opacity-0', 'pointer-events-none');
        card.classList.add('translate-y-0', 'opacity-100');
    }

    async function drawRoute() {
        if (!state.origin || !state.dest) return;
        routeLayer.clearLayers();
        obstacleLayer.clearLayers();

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

                // 2. Draw active repair segments if any
                (data.segments || []).forEach((seg) => {
                    if (seg.repair && Array.isArray(seg.polyline) && seg.polyline.length >= 2) {
                        L.polyline(seg.polyline, {
                            color: '#ea580c',
                            weight: 6,
                            lineCap: 'round',
                            dashArray: '3 10',
                        }).addTo(routeLayer);
                    }
                });

                // 3. Mark avoided report obstacles
                (data.avoided_points || []).forEach((obs) => {
                    if (obs.lat && obs.lng) {
                        const m = L.marker([obs.lat, obs.lng], {
                            icon: makeObstaclePin(),
                        }).addTo(obstacleLayer);
                        m.bindPopup(`<div class="text-xs p-1 font-bold text-slate-800">⚠️ Titik Dihindari:<br><span class="font-normal">${obs.alamat || 'Area Laporan'}</span></div>`);
                    }
                });

                map.fitBounds(L.latLngBounds(data.polyline), {
                    paddingTopLeft: [30, 180],
                    paddingBottomRight: [30, 240],
                    maxZoom: 17
                });

                showSummary(data.distance_m || 0, data.duration_min || 0, data.summary || {}, data.warnings || [], data.steps || []);
                return;
            }
        } catch (_) {}
    }

    function setupAutocomplete(kind, input, list) {
        function close() {
            list.classList.add('hidden');
        }

        function render(showAll = false) {
            const q = showAll ? '' : input.value.trim().toLowerCase();
            const items = SAMPLE_PLACES.filter((p) =>
                !q || p.name.toLowerCase().includes(q) || p.type.toLowerCase().includes(q));

            list.innerHTML = '';
            if (items.length === 0) {
                const li = document.createElement('li');
                li.className = 'px-3 py-2 text-xs text-slate-400';
                li.textContent = 'Lokasi tidak ditemukan di Mulyorejo.';
                list.appendChild(li);
            } else {
                items.forEach((p) => {
                    const li = document.createElement('li');
                    li.className = 'px-3 py-2.5 hover:bg-brand-50 cursor-pointer flex items-center justify-between border-b border-slate-50 last:border-0';
                    li.innerHTML = `
                        <div class="flex flex-col min-w-0 pr-2">
                            <span class="text-xs font-bold text-slate-800 truncate">${p.name}</span>
                            <span class="text-[10px] text-slate-400">${p.type} • Mulyorejo</span>
                        </div>
                        <span class="text-[10px] font-bold text-brand-700 bg-brand-50 border border-brand-200 px-1.5 py-0.5 rounded shrink-0">Pilih</span>
                    `;
                    li.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        setPoint(kind, { lat: p.lat, lng: p.lng, label: p.name });
                        close();
                    });
                    list.appendChild(li);
                });
            }
            list.classList.remove('hidden');
        }

        input.addEventListener('focus', () => render(true));
        input.addEventListener('input', () => render(false));
        input.addEventListener('blur', () => setTimeout(close, 200));
    }

    setupAutocomplete('origin', document.getElementById('originInput'), document.getElementById('originList'));
    setupAutocomplete('dest',   document.getElementById('destInput'),   document.getElementById('destList'));

    // Default origin: Kampus C UNAIR
    setPoint('origin', { lat: SAMPLE_PLACES[0].lat, lng: SAMPLE_PLACES[0].lng, label: SAMPLE_PLACES[0].name });

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
