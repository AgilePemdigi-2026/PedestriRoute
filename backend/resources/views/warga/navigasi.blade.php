@extends('layouts.app')

@section('title', 'Navigasi Turn-by-Turn - PedestriRoute')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #navMap { position: absolute; inset: 0; z-index: 0; background: #e5e7eb; }

    .nav-pin { background: transparent !important; border: none !important; }
    .nav-pin > div { animation: navPinPop 0.3s ease-out both; }
    @keyframes navPinPop {
        from { opacity: 0; transform: scale(0.4); }
        to   { opacity: 1; transform: scale(1); }
    }

    .user-puck { background: transparent !important; border: none !important; }
    #userArrow { transition: transform 0.18s linear; transform-origin: 50% 50%; }
    .user-puck .pulse {
        position: absolute; inset: -12px; border-radius: 9999px;
        background: rgba(0, 135, 117, 0.18); animation: navPulse 2s ease-out infinite;
    }
    @keyframes navPulse {
        0%   { transform: scale(0.7); opacity: 0.8; }
        70%  { transform: scale(1.5); opacity: 0; }
        100% { transform: scale(1.5); opacity: 0; }
    }

    .sheet-enter { animation: sheetUp 0.25s ease-out both; }
    @keyframes sheetUp {
        from { transform: translateY(24px); opacity: 0; }
        to   { transform: translateY(0); opacity: 1; }
    }

    .spinner {
        width: 22px; height: 22px; border-radius: 9999px;
        border: 3px solid rgba(255, 255, 255, 0.35);
        border-top-color: #fff; animation: navSpin 0.8s linear infinite;
    }
    @keyframes navSpin { to { transform: rotate(360deg); } }

    @media (prefers-reduced-motion: reduce) {
        .nav-pin > div, .user-puck .pulse, .sheet-enter, .spinner { animation: none !important; }
        #userArrow { transition: none; }
    }
</style>
@endpush

@section('content')
<div class="relative flex-1 overflow-hidden bg-slate-100" style="height: calc(100dvh - 4rem);">
    <div id="navMap" aria-label="Peta navigasi pejalan kaki"></div>

    <!-- ===================== SETUP HEADER ===================== -->
    <div id="setupHeader" class="absolute inset-x-0 top-0 z-30 px-4 pt-4">
        <div class="flex items-center gap-2.5">
            <a href="/beranda-warga" aria-label="Kembali ke beranda"
                class="w-11 h-11 shrink-0 rounded-2xl bg-white/95 backdrop-blur border border-slate-200 shadow-sm flex items-center justify-center text-slate-700 active:scale-95 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div class="flex-1 min-w-0 rounded-2xl bg-white/95 backdrop-blur border border-slate-200 shadow-sm px-4 py-2.5">
                <p class="text-[10px] font-bold uppercase tracking-wider text-brand-600">Navigasi Pejalan Kaki</p>
                <p id="headerSubtitle" class="text-sm font-extrabold text-slate-900 truncate">Tentukan tujuan Anda</p>
            </div>
            <button type="button" id="btnVoice" aria-label="Aktifkan atau matikan panduan suara"
                class="w-11 h-11 shrink-0 rounded-2xl bg-white/95 backdrop-blur border border-slate-200 shadow-sm flex items-center justify-center text-brand-700 active:scale-95 transition">
                <svg id="voiceOnIcon" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5 6 9H3v6h3l5 4V5z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13" />
                </svg>
                <svg id="voiceOffIcon" class="w-5 h-5 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5 6 9H3v6h3l5 4V5z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="m16 9 5 6m0-6-5 6" />
                </svg>
            </button>
        </div>
    </div>

    <!-- ===================== SETUP SHEET ===================== -->
    <div id="setupSheet" class="absolute inset-x-0 bottom-0 z-30 px-4 pb-4">
        <div class="sheet-enter bg-white rounded-3xl border border-slate-200 shadow-2xl shadow-slate-900/10 p-4">
            <div class="space-y-2.5">
                <!-- Origin -->
                <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-2xl px-3 py-2.5">
                    <span class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="4" />
                            <circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.6" opacity="0.45" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Titik Awal</p>
                        <p id="originLabel" class="text-sm font-bold text-slate-800 truncate">Mendeteksi lokasi GPS...</p>
                        <p id="gpsStatus" class="text-[10px] text-slate-500 truncate">Izinkan akses lokasi untuk hasil terbaik</p>
                    </div>
                    <button type="button" id="btnLocate" aria-label="Gunakan lokasi saya saat ini"
                        class="w-9 h-9 rounded-xl bg-white border border-slate-200 text-brand-700 flex items-center justify-center shrink-0 active:scale-95 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v3m0 14v3M2 12h3m14 0h3" />
                            <circle cx="12" cy="12" r="6" />
                        </svg>
                    </button>
                </div>

                <!-- Destination -->
                <div class="relative">
                    <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-2xl px-3 py-2.5 focus-within:ring-2 focus-within:ring-brand-500 focus-within:border-brand-500">
                        <span class="w-8 h-8 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11z" />
                                <circle cx="12" cy="10" r="2.4" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <label for="destInput" class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Titik Tujuan</label>
                            <input id="destInput" type="text" autocomplete="off" placeholder="Cari kampus, halte, rumah sakit, taman..."
                                class="w-full bg-transparent text-sm font-bold text-slate-800 placeholder:text-slate-400 focus:outline-none" />
                        </div>
                        <button type="button" id="btnClearDest" aria-label="Hapus tujuan"
                            class="hidden w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
                            </svg>
                        </button>
                    </div>
                    <ul id="destSuggestions"
                        class="hidden absolute left-0 right-0 bottom-full mb-2 bg-white rounded-2xl shadow-2xl border border-slate-100 py-1.5 z-40 max-h-60 overflow-y-auto"></ul>
                </div>

                <!-- Preference -->
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" data-pref="safest" class="pref-chip h-10 rounded-xl text-xs font-extrabold border transition">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-current"></span> Paling Aman
                        </span>
                    </button>
                    <button type="button" data-pref="fastest" class="pref-chip h-10 rounded-xl text-xs font-extrabold border transition">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-current"></span> Tercepat
                        </span>
                    </button>
                </div>

                <!-- Route summary -->
                <div id="routeSummary" class="hidden border-t border-dashed border-slate-200 pt-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-baseline gap-2 min-w-0">
                            <span id="sumDistance" class="text-xl font-extrabold text-slate-900">-</span>
                            <span class="text-slate-300">•</span>
                            <span id="sumDuration" class="text-sm font-bold text-slate-600">-</span>
                        </div>
                        <span id="sumScore" class="text-[10px] font-extrabold text-brand-800 bg-brand-50 border border-brand-100 px-2 py-1 rounded-lg shrink-0">-</span>
                    </div>

                    <div class="mt-2 flex items-start gap-2">
                        <span id="sumConditionDot" class="w-2.5 h-2.5 rounded-full bg-brand-500 mt-1 shrink-0"></span>
                        <p id="sumCondition" class="text-xs text-slate-600 leading-snug">-</p>
                    </div>

                    <div class="mt-2.5 flex items-center gap-3 text-[10px] text-slate-500">
                        <span class="flex items-center gap-1.5"><span class="w-5 h-1 rounded-full bg-brand-500"></span>Trotoar baik</span>
                        <span class="flex items-center gap-1.5"><span class="w-5 border-t-[3px] border-dashed border-orange-500"></span>Perbaikan</span>
                    </div>

                    <button type="button" id="btnStartNav" disabled
                        class="mt-3 w-full h-12 bg-brand-700 disabled:bg-slate-300 disabled:text-slate-500 hover:bg-brand-800 text-white font-extrabold text-sm rounded-2xl shadow-sm transition active:scale-[0.98] flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m3 11 19-9-9 19-2-8-8-2z" />
                        </svg>
                        Mulai Navigasi
                    </button>
                </div>

                <button type="button" id="btnFindRoute"
                    class="w-full h-11 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-2xl shadow-sm transition active:scale-[0.98] flex items-center justify-center gap-2">
                    <span id="findRouteSpinner" class="hidden spinner"></span>
                    <span id="findRouteLabel">Cari Rute</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ===================== NAVIGATION BANNER ===================== -->
    <div id="navBanner" class="hidden absolute inset-x-0 top-0 z-40 px-4 pt-4">
        <div class="bg-brand-800 text-white rounded-3xl shadow-2xl shadow-brand-900/30 px-4 py-3.5">
            <div class="flex items-center gap-3">
                <div id="maneuverIcon" class="w-12 h-12 shrink-0 rounded-2xl bg-white/15 flex items-center justify-center text-white"></div>
                <div class="min-w-0 flex-1" aria-live="polite">
                    <p id="maneuverDistance" class="text-2xl font-extrabold leading-none tracking-tight">-</p>
                    <p id="maneuverInstruction" class="mt-1 text-xs font-semibold leading-snug text-white/90">-</p>
                </div>
                <button type="button" id="btnEndNavTop" aria-label="Akhiri navigasi"
                    class="w-9 h-9 shrink-0 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center active:scale-95 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>
            <div class="mt-3 flex items-center gap-2 text-[10px] font-semibold text-white/80">
                <span id="navStreet" class="truncate">-</span>
                <span class="w-1 h-1 rounded-full bg-white/50 shrink-0"></span>
                <span id="navNextStreet" class="truncate">-</span>
            </div>
        </div>
    </div>

    <!-- ===================== NAVIGATION BOTTOM PANEL ===================== -->
    <div id="navPanel" class="hidden absolute inset-x-0 bottom-0 z-40 px-4 pb-4">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl shadow-slate-900/15 p-4">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Sisa perjalanan</p>
                    <p id="navRemaining" class="text-xl font-extrabold text-slate-900 leading-tight">-</p>
                    <p id="navEta" class="text-[11px] font-semibold text-slate-500">-</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" id="btnDemo" title="Simulasikan pergerakan"
                        class="h-9 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-extrabold flex items-center gap-1.5 active:scale-95 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 3 14 9-14 9V3z" />
                        </svg>
                        <span id="demoLabel">Simulasi</span>
                    </button>
                    <button type="button" id="btnEndNav"
                        class="h-9 px-3 rounded-xl bg-rose-50 text-rose-700 border border-rose-100 text-[10px] font-extrabold flex items-center gap-1.5 active:scale-95 transition">
                        Akhiri
                    </button>
                </div>
            </div>

            <div class="mt-3">
                <div class="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                    <div id="navProgress" class="h-full w-0 rounded-full bg-brand-600 transition-[width] duration-300"></div>
                </div>
                <div class="mt-2 flex items-center justify-between gap-2 text-[10px] font-semibold text-slate-500">
                    <span id="navStatus">Menunggu sinyal GPS...</span>
                    <span id="navHeading">Arah: -</span>
                </div>
            </div>

            <button type="button" id="btnDemoReroute"
                class="hidden mt-2 w-full h-8 rounded-xl border border-dashed border-amber-300 bg-amber-50 text-amber-800 text-[10px] font-extrabold">
                Uji rerouting otomatis
            </button>
        </div>
    </div>

    <!-- ===================== REROUTE OVERLAY ===================== -->
    <div id="rerouteOverlay" class="hidden absolute inset-0 z-50 bg-slate-900/45 backdrop-blur-[2px] flex items-center justify-center px-6">
        <div class="bg-white rounded-3xl shadow-2xl px-6 py-5 w-full max-w-xs text-center">
            <div class="mx-auto w-12 h-12 rounded-2xl bg-brand-50 flex items-center justify-center">
                <div class="spinner !border-brand-200 !border-t-brand-700"></div>
            </div>
            <p id="rerouteTitle" class="mt-3 text-sm font-extrabold text-slate-900">Menghitung ulang rute...</p>
            <p id="rerouteText" class="mt-1 text-xs text-slate-500 leading-snug">Anda terdeteksi keluar dari jalur. Rute baru sedang disiapkan.</p>
        </div>
    </div>

    <!-- ===================== ARRIVAL OVERLAY ===================== -->
    <div id="arrivalOverlay" class="hidden absolute inset-0 z-50 bg-slate-900/45 backdrop-blur-[2px] flex items-end justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full p-5 text-center sheet-enter">
            <div class="mx-auto w-14 h-14 rounded-full bg-brand-50 text-brand-700 flex items-center justify-center">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h2 class="mt-3 text-lg font-extrabold text-slate-900">Anda sudah sampai!</h2>
            <p id="arrivalText" class="mt-1 text-xs text-slate-500 leading-snug">Terima kasih telah berjalan bersama PedestriRoute.</p>
            <div class="mt-4 grid grid-cols-2 gap-2">
                <button type="button" id="btnArrivalClose"
                    class="h-11 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-extrabold transition">
                    Tutup
                </button>
                <a href="/beranda-warga"
                    class="h-11 rounded-2xl bg-brand-700 hover:bg-brand-800 text-white text-xs font-extrabold transition flex items-center justify-center">
                    Ke Beranda
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@include('partials.mulyorejo-service-area')
<script>
(() => {
    'use strict';

    const token = localStorage.getItem('token');
    if (!token) {
        window.location.href = '/login';
    }

    const WALK_SPEED_MPS = 1.25;
    const OFF_ROUTE_METERS = 20;
    const OFF_ROUTE_SECONDS = 3;
    const VOICE_THRESHOLDS = [120, 50, 15];
    const ARRIVAL_RADIUS_M = 15;

    const SAMPLE_PLACES = [
        { name: 'Kampus C Universitas Airlangga', type: 'Kampus', lat: -7.2672, lng: 112.7846 },
        { name: 'RS Universitas Airlangga', type: 'Rumah Sakit', lat: -7.2651, lng: 112.7897 },
        { name: 'Halte Bus Kertajaya Indah', type: 'Halte Bus', lat: -7.2706, lng: 112.7931 },
        { name: 'Taman Kertajaya Indah', type: 'Taman', lat: -7.2688, lng: 112.7992 },
        { name: 'Puskesmas Mulyorejo', type: 'Faskes', lat: -7.2625, lng: 112.7958 },
        { name: 'Halte Bus Mulyosari', type: 'Halte Bus', lat: -7.2598, lng: 112.8029 },
        { name: 'Pasar Mulyosari', type: 'Pasar', lat: -7.2605, lng: 112.7950 },
        { name: 'Masjid Agung Mulyorejo', type: 'Ibadah', lat: -7.2660, lng: 112.7965 },
        { name: 'Taman Mulyorejo', type: 'Taman', lat: -7.2680, lng: 112.8050 },
        { name: 'Gedung Kuliah Bersama Unair C', type: 'Kampus', lat: -7.2657, lng: 112.7862 },
    ];

    // -----------------------------------------------------------------
    // Map
    // -----------------------------------------------------------------
    const map = L.map('navMap', { attributionControl: false, zoomControl: false }).setView([-7.2663, 112.7965], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

    const serviceArea = PedestriRouteServiceArea.attach(map);
    setTimeout(() => {
        map.invalidateSize();
        map.fitBounds(serviceArea.bounds, { padding: [16, 16], animate: false });
    }, 200);

    const routeLayer = L.layerGroup().addTo(map);
    const markerLayer = L.layerGroup().addTo(map);

    // -----------------------------------------------------------------
    // State
    // -----------------------------------------------------------------
    const state = {
        origin: null,
        destination: null,
        preference: 'safest',
        route: null,
        steps: [],
        geometry: null,
        along: 0,
        navigating: false,
        arrived: false,
        rerouting: false,
        watchId: null,
        orientationHandler: null,
        gpsHeading: null,
        compassHeading: null,
        offRouteSince: null,
        offRouteTimer: null,
        rerouteController: null,
        rerouteTimeout: null,
        announced: {},
        spokenNow: {},
        demoActive: false,
        demoTimer: null,
        demoAlong: 0,
        userMarker: null,
        startMarker: null,
        endMarker: null,
        wakeLock: null,
        muted: localStorage.getItem('pedestriroute:nav-muted') === '1',
        busy: false,
        lastPosition: null,
        lastNextStepIndex: null,
        currentBearing: 0,
    };

    const els = {
        setupHeader: document.getElementById('setupHeader'),
        setupSheet: document.getElementById('setupSheet'),
        navBanner: document.getElementById('navBanner'),
        navPanel: document.getElementById('navPanel'),
        destInput: document.getElementById('destInput'),
        destSuggestions: document.getElementById('destSuggestions'),
        btnClearDest: document.getElementById('btnClearDest'),
        originLabel: document.getElementById('originLabel'),
        gpsStatus: document.getElementById('gpsStatus'),
        btnLocate: document.getElementById('btnLocate'),
        btnFindRoute: document.getElementById('btnFindRoute'),
        findRouteLabel: document.getElementById('findRouteLabel'),
        findRouteSpinner: document.getElementById('findRouteSpinner'),
        routeSummary: document.getElementById('routeSummary'),
        sumDistance: document.getElementById('sumDistance'),
        sumDuration: document.getElementById('sumDuration'),
        sumScore: document.getElementById('sumScore'),
        sumCondition: document.getElementById('sumCondition'),
        sumConditionDot: document.getElementById('sumConditionDot'),
        btnStartNav: document.getElementById('btnStartNav'),
        btnVoice: document.getElementById('btnVoice'),
        voiceOnIcon: document.getElementById('voiceOnIcon'),
        voiceOffIcon: document.getElementById('voiceOffIcon'),
        headerSubtitle: document.getElementById('headerSubtitle'),
        maneuverIcon: document.getElementById('maneuverIcon'),
        maneuverDistance: document.getElementById('maneuverDistance'),
        maneuverInstruction: document.getElementById('maneuverInstruction'),
        navStreet: document.getElementById('navStreet'),
        navNextStreet: document.getElementById('navNextStreet'),
        navRemaining: document.getElementById('navRemaining'),
        navEta: document.getElementById('navEta'),
        navProgress: document.getElementById('navProgress'),
        navStatus: document.getElementById('navStatus'),
        navHeading: document.getElementById('navHeading'),
        btnEndNav: document.getElementById('btnEndNav'),
        btnEndNavTop: document.getElementById('btnEndNavTop'),
        btnDemo: document.getElementById('btnDemo'),
        demoLabel: document.getElementById('demoLabel'),
        btnDemoReroute: document.getElementById('btnDemoReroute'),
        rerouteOverlay: document.getElementById('rerouteOverlay'),
        rerouteTitle: document.getElementById('rerouteTitle'),
        rerouteText: document.getElementById('rerouteText'),
        arrivalOverlay: document.getElementById('arrivalOverlay'),
        arrivalText: document.getElementById('arrivalText'),
        btnArrivalClose: document.getElementById('btnArrivalClose'),
    };

    // -----------------------------------------------------------------
    // Formatting helpers
    // -----------------------------------------------------------------
    function haversine(aLat, aLng, bLat, bLng) {
        const R = 6371000;
        const rad = (d) => d * Math.PI / 180;
        const dLat = rad(bLat - aLat);
        const dLng = rad(bLng - aLng);
        const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(aLat)) * Math.cos(rad(bLat)) * Math.sin(dLng / 2) ** 2;
        return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
    }

    function formatDistance(meters) {
        const value = Math.max(0, meters);
        if (value < 1000) {
            if (value < 10) return `${Math.round(value)} m`;
            return `${Math.round(value / 5) * 5} m`;
        }
        return `${(value / 1000).toFixed(1).replace('.', ',')} km`;
    }

    function formatDuration(minutes) {
        const value = Math.max(0, minutes);
        if (value < 1) return '< 1 menit';
        if (value < 60) return `${Math.round(value)} menit`;
        const hours = Math.floor(value / 60);
        const mins = Math.round(value % 60);
        return mins === 0 ? `${hours} jam` : `${hours} jam ${mins} menit`;
    }

    function bearingBetween(a, b) {
        const lat1 = a.lat * Math.PI / 180;
        const lat2 = b.lat * Math.PI / 180;
        const dLng = (b.lng - a.lng) * Math.PI / 180;
        const y = Math.sin(dLng) * Math.cos(lat2);
        const x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLng);
        return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
    }

    function compassLabel(bearing) {
        const directions = ['Utara', 'Timur Laut', 'Timur', 'Tenggara', 'Selatan', 'Barat Daya', 'Barat', 'Barat Laut'];
        if (bearing === null || Number.isNaN(bearing)) return '-';
        return directions[Math.round(((bearing % 360) + 360) % 360 / 45) % 8];
    }

    function setText(el, text) {
        if (el) el.textContent = text;
    }

    // -----------------------------------------------------------------
    // Preference chips
    // -----------------------------------------------------------------
    const CHIP_ON = {
        safest: ['bg-brand-700', 'text-white', 'border-brand-700'],
        fastest: ['bg-amber-400', 'text-slate-900', 'border-amber-400'],
    };
    const CHIP_OFF = ['bg-white', 'text-slate-600', 'border-slate-200'];

    function paintChips() {
        document.querySelectorAll('.pref-chip').forEach((chip) => {
            const pref = chip.dataset.pref;
            const on = pref === state.preference;
            chip.classList.remove(...CHIP_OFF, ...CHIP_ON.safest, ...CHIP_ON.fastest);
            chip.classList.add(...(on ? CHIP_ON[pref] : CHIP_OFF));
            chip.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    document.querySelectorAll('.pref-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            if (state.busy) return;
            state.preference = chip.dataset.pref;
            paintChips();
            if (state.origin && state.destination) findRoute({ silent: true });
        });
    });
    paintChips();

    // -----------------------------------------------------------------
    // Voice guidance
    // -----------------------------------------------------------------
    function paintVoiceButton() {
        els.voiceOnIcon.classList.toggle('hidden', state.muted);
        els.voiceOffIcon.classList.toggle('hidden', !state.muted);
        els.btnVoice.setAttribute('aria-label', state.muted ? 'Aktifkan panduan suara' : 'Matikan panduan suara');
        els.btnVoice.classList.toggle('text-slate-400', state.muted);
        els.btnVoice.classList.toggle('text-brand-700', !state.muted);
    }
    paintVoiceButton();

    els.btnVoice.addEventListener('click', () => {
        state.muted = !state.muted;
        localStorage.setItem('pedestriroute:nav-muted', state.muted ? '1' : '0');
        paintVoiceButton();
        if (state.muted) {
            if ('speechSynthesis' in window) window.speechSynthesis.cancel();
            showToast('Panduan suara dinonaktifkan.', 'info');
        } else {
            showToast('Panduan suara diaktifkan.', 'success');
            speak('Panduan suara diaktifkan.');
        }
    });

    function speak(text, force = false) {
        if (state.muted && !force) return;
        if (!('speechSynthesis' in window) || typeof SpeechSynthesisUtterance === 'undefined') return;
        try {
            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'id-ID';
            utterance.rate = 1;
            utterance.pitch = 1;
            utterance.volume = 1;
            window.speechSynthesis.speak(utterance);
        } catch (_) {
            // Speech synthesis is a progressive enhancement.
        }
    }

    // -----------------------------------------------------------------
    // Markers
    // -----------------------------------------------------------------
    function makePin(letter, background) {
        return L.divIcon({
            className: 'nav-pin',
            html: `<div style="width:32px;height:32px;border-radius:50%;background:${background};color:#fff;
                border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.32);display:flex;
                align-items:center;justify-content:center;font-weight:800;font-size:13px">${letter}</div>`,
            iconSize: [32, 32],
            iconAnchor: [16, 16],
        });
    }

    function createUserMarker(lat, lng) {
        if (!state.userMarker) {
            const icon = L.divIcon({
                className: 'user-puck',
                html: `<div style="position:relative;width:34px;height:34px">
                    <div class="pulse"></div>
                    <div id="userArrow" style="position:relative;width:34px;height:34px;border-radius:9999px;
                        background:#008775;border:3px solid #fff;box-shadow:0 3px 10px rgba(0,0,0,.3);
                        display:flex;align-items:center;justify-content:center;color:#fff">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2 19 21 12 17 5 21 12 2z" />
                        </svg>
                    </div>
                </div>`,
                iconSize: [34, 34],
                iconAnchor: [17, 17],
            });
            state.userMarker = L.marker([lat, lng], { icon, zIndexOffset: 1200, interactive: false }).addTo(markerLayer);
        } else {
            state.userMarker.setLatLng([lat, lng]);
        }
    }

    function updateUserHeading(heading) {
        if (heading !== null && !Number.isNaN(heading)) {
            state.currentBearing = heading;
        }
        const arrow = document.getElementById('userArrow');
        if (arrow) arrow.style.transform = `rotate(${state.currentBearing}deg)`;
    }

    function drawRouteOnMap(route, fit = true) {
        routeLayer.clearLayers();

        (route.segments || []).forEach((segment) => {
            if (!Array.isArray(segment.polyline) || segment.polyline.length < 2) return;

            L.polyline(segment.polyline, {
                color: '#ffffff', weight: 10, opacity: 0.95, lineCap: 'round', lineJoin: 'round', interactive: false,
            }).addTo(routeLayer);

            const color = segment.repair ? '#ea580c' : (segment.score < 70 ? '#f59e0b' : '#008775');
            L.polyline(segment.polyline, {
                color,
                weight: 6,
                opacity: 1,
                lineCap: 'round',
                lineJoin: 'round',
                dashArray: segment.repair ? '2 12' : null,
                interactive: false,
            }).addTo(routeLayer);
        });

        if (markerLayer.getLayers().includes(state.startMarker)) markerLayer.removeLayer(state.startMarker);
        if (markerLayer.getLayers().includes(state.endMarker)) markerLayer.removeLayer(state.endMarker);

        if (route.origin) {
            state.startMarker = L.marker([route.origin.lat, route.origin.lng], {
                icon: makePin('A', '#0284c7'), interactive: false,
            }).addTo(markerLayer);
        }
        if (route.destination) {
            state.endMarker = L.marker([route.destination.lat, route.destination.lng], {
                icon: makePin('B', '#004f46'), interactive: false,
            }).addTo(markerLayer);
        }

        if (fit && route.polyline && route.polyline.length > 1) {
            map.fitBounds(L.latLngBounds(route.polyline), {
                paddingTopLeft: [32, 110],
                paddingBottomRight: [32, 250],
                maxZoom: 17,
                animate: false,
            });
        }
    }

    // -----------------------------------------------------------------
    // Geometry helpers
    // -----------------------------------------------------------------
    function prepareGeometry(polyline) {
        if (!Array.isArray(polyline) || polyline.length < 2) {
            state.geometry = null;
            return;
        }

        const lat0 = polyline[0][0];
        const metersPerLng = 111320 * Math.cos(lat0 * Math.PI / 180);
        const metersPerLat = 110540;
        const points = polyline.map(([lat, lng]) => ({ lat, lng, x: lng * metersPerLng, y: lat * metersPerLat }));
        const cumulative = [0];

        for (let i = 1; i < points.length; i++) {
            const dx = points[i].x - points[i - 1].x;
            const dy = points[i].y - points[i - 1].y;
            cumulative.push(cumulative[i - 1] + Math.hypot(dx, dy));
        }

        state.geometry = { points, cumulative, total: cumulative[cumulative.length - 1] || 0 };
    }

    function projectOnRoute(lat, lng) {
        if (!state.geometry || state.geometry.points.length < 2) {
            return { distance: 0, along: 0, lat, lng, bearing: 0, total: 0 };
        }

        const g = state.geometry;
        const lat0 = g.points[0].lat;
        const metersPerLng = 111320 * Math.cos(lat0 * Math.PI / 180);
        const metersPerLat = 110540;
        const px = lng * metersPerLng;
        const py = lat * metersPerLat;

        let best = { distance: Infinity, along: 0, lat, lng, bearing: 0 };

        for (let i = 0; i < g.points.length - 1; i++) {
            const a = g.points[i];
            const b = g.points[i + 1];
            const dx = b.x - a.x;
            const dy = b.y - a.y;
            const lengthSq = dx * dx + dy * dy;
            let t = lengthSq > 0 ? ((px - a.x) * dx + (py - a.y) * dy) / lengthSq : 0;
            t = Math.max(0, Math.min(1, t));

            const nx = a.x + t * dx;
            const ny = a.y + t * dy;
            const distance = Math.hypot(px - nx, py - ny);

            if (distance < best.distance) {
                const segmentLength = g.cumulative[i + 1] - g.cumulative[i];
                best = {
                    distance,
                    along: g.cumulative[i] + t * segmentLength,
                    lat: a.lat + t * (b.lat - a.lat),
                    lng: a.lng + t * (b.lng - a.lng),
                    bearing: Math.atan2(dx, dy) * 180 / Math.PI,
                };
            }
        }

        return { ...best, total: g.total };
    }

    function pointAtAlong(along) {
        if (!state.geometry) return null;
        const g = state.geometry;
        const target = Math.max(0, Math.min(g.total, along));

        for (let i = 0; i < g.points.length - 1; i++) {
            if (g.cumulative[i + 1] >= target || i === g.points.length - 2) {
                const segmentLength = g.cumulative[i + 1] - g.cumulative[i];
                const t = segmentLength > 0 ? (target - g.cumulative[i]) / segmentLength : 0;
                const a = g.points[i];
                const b = g.points[i + 1];
                return {
                    lat: a.lat + t * (b.lat - a.lat),
                    lng: a.lng + t * (b.lng - a.lng),
                    bearing: Math.atan2(b.x - a.x, b.y - a.y) * 180 / Math.PI,
                };
            }
        }

        return g.points[g.points.length - 1];
    }

    // -----------------------------------------------------------------
    // Destination autocomplete
    // -----------------------------------------------------------------
    function renderSuggestions(showAll = false) {
        const query = showAll ? '' : els.destInput.value.trim().toLowerCase();
        const items = SAMPLE_PLACES.filter((place) =>
            !query
            || place.name.toLowerCase().includes(query)
            || place.type.toLowerCase().includes(query)
        );

        els.destSuggestions.textContent = '';

        if (!items.length) {
            const li = document.createElement('li');
            li.className = 'px-4 py-3 text-xs text-slate-500';
            li.textContent = 'Tempat tidak ditemukan di area Mulyorejo.';
            els.destSuggestions.appendChild(li);
        } else {
            items.forEach((place) => {
                const li = document.createElement('li');
                li.className = 'px-3 py-2.5 min-h-[44px] flex items-center justify-between gap-3 cursor-pointer hover:bg-brand-50 transition';
                li.setAttribute('role', 'option');

                const name = document.createElement('span');
                name.className = 'text-sm font-semibold text-slate-800 truncate';
                name.textContent = place.name;

                const type = document.createElement('span');
                type.className = 'text-[10px] font-bold text-brand-700 bg-brand-50 border border-brand-100 px-1.5 py-0.5 rounded-md shrink-0';
                type.textContent = place.type;

                li.append(name, type);
                li.addEventListener('mousedown', (event) => event.preventDefault());
                li.addEventListener('click', () => {
                    selectDestination(place);
                    closeSuggestions();
                });
                els.destSuggestions.appendChild(li);
            });
        }

        els.destSuggestions.classList.remove('hidden');
    }

    function closeSuggestions() {
        els.destSuggestions.classList.add('hidden');
    }

    els.destInput.addEventListener('focus', () => {
        els.destInput.select();
        renderSuggestions(true);
    });
    els.destInput.addEventListener('input', () => {
        els.btnClearDest.classList.toggle('hidden', els.destInput.value.trim() === '');
        renderSuggestions(false);
    });
    els.destInput.addEventListener('blur', () => setTimeout(closeSuggestions, 140));
    els.destInput.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeSuggestions();
        if (event.key === 'Enter') {
            event.preventDefault();
            const first = SAMPLE_PLACES.find((place) =>
                place.name.toLowerCase().includes(els.destInput.value.trim().toLowerCase())
            );
            if (first) {
                selectDestination(first);
                closeSuggestions();
            }
        }
    });

    els.btnClearDest.addEventListener('click', () => {
        state.destination = null;
        els.destInput.value = '';
        els.btnClearDest.classList.add('hidden');
        els.routeSummary.classList.add('hidden');
        els.btnStartNav.disabled = true;
        routeLayer.clearLayers();
        if (state.startMarker) markerLayer.removeLayer(state.startMarker);
        if (state.endMarker) markerLayer.removeLayer(state.endMarker);
        state.startMarker = null;
        state.endMarker = null;
        state.route = null;
        state.geometry = null;
        setText(els.headerSubtitle, 'Tentukan tujuan Anda');
    });

    function selectDestination(place) {
        if (!serviceArea.contains(place.lat, place.lng)) {
            showToast('Tempat berada di luar area layanan Kecamatan Mulyorejo.', 'error');
            return;
        }

        state.destination = { lat: place.lat, lng: place.lng, label: place.name };
        els.destInput.value = place.name;
        els.btnClearDest.classList.remove('hidden');
        setText(els.headerSubtitle, place.name);
        findRoute();
    }

    // -----------------------------------------------------------------
    // Origin
    // -----------------------------------------------------------------
    function setOrigin(lat, lng, label, accuracy = null) {
        state.origin = { lat, lng, label };
        setText(els.originLabel, label);
        setText(
            els.gpsStatus,
            accuracy !== null
                ? `Akurasi GPS sekitar ${Math.round(accuracy)} meter`
                : 'Siap digunakan untuk navigasi'
        );
    }

    function useFallbackOrigin() {
        const fallback = SAMPLE_PLACES[0];
        setOrigin(fallback.lat, fallback.lng, `${fallback.name} (contoh)`, null);
        showToast('GPS tidak tersedia. Menggunakan titik awal contoh.', 'info');
    }

    function locateUser(showFeedback = false) {
        if (!navigator.geolocation) {
            useFallbackOrigin();
            return;
        }

        els.btnLocate.classList.add('animate-pulse');
        setText(els.gpsStatus, 'Mendeteksi lokasi Anda...');

        navigator.geolocation.getCurrentPosition(
            (position) => {
                els.btnLocate.classList.remove('animate-pulse');
                const { latitude, longitude, accuracy } = position.coords;

                if (!serviceArea.contains(latitude, longitude)) {
                    showToast('Lokasi Anda berada di luar area layanan Kecamatan Mulyorejo.', 'error');
                    setText(els.gpsStatus, 'Lokasi di luar area layanan');
                    if (!state.origin) useFallbackOrigin();
                    return;
                }

                setOrigin(latitude, longitude, 'Lokasi Saya Saat Ini', accuracy);
                if (showFeedback) showToast('Lokasi berhasil diperbarui.', 'success');
                if (state.destination && !state.navigating) findRoute({ silent: true });
            },
            () => {
                els.btnLocate.classList.remove('animate-pulse');
                if (!state.origin) useFallbackOrigin();
                if (showFeedback) showToast('Izin lokasi ditolak. Aktifkan GPS untuk navigasi real-time.', 'error');
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    els.btnLocate.addEventListener('click', () => locateUser(true));

    // -----------------------------------------------------------------
    // Route request
    // -----------------------------------------------------------------
    async function requestRoute(from, to, preference, signal = null) {
        const response = await fetch('/api/v1/routes/recommend', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`,
            },
            body: JSON.stringify({
                origin: { lat: from.lat, lng: from.lng },
                destination: { lat: to.lat, lng: to.lng },
                preference,
            }),
            signal,
        });

        let payload = {};
        try {
            payload = await response.json();
        } catch (_) {
            payload = {};
        }

        if (!response.ok) {
            const message = payload?.error?.message || 'Rute tidak dapat dihitung. Silakan coba lagi.';
            throw new Error(message);
        }

        return payload;
    }

    async function findRoute({ silent = false, autostart = false } = {}) {
        if (!state.origin) {
            if (!silent) showToast('Menunggu lokasi GPS Anda...', 'info');
            return;
        }
        if (!state.destination) {
            if (!silent) showToast('Pilih titik tujuan terlebih dahulu.', 'error');
            return;
        }
        if (state.busy) return;

        state.busy = true;
        els.btnFindRoute.disabled = true;
        els.findRouteSpinner.classList.remove('hidden');
        els.findRouteLabel.textContent = 'Menghitung rute...';
        els.btnStartNav.disabled = true;

        try {
            const route = await requestRoute(state.origin, state.destination, state.preference);
            applyRoute(route);
            if (autostart) startNavigation();
        } catch (error) {
            if (!silent) showToast(error.message || 'Gagal menghitung rute.', 'error');
        } finally {
            state.busy = false;
            els.btnFindRoute.disabled = false;
            els.findRouteSpinner.classList.add('hidden');
            els.findRouteLabel.textContent = 'Cari Rute';
        }
    }

    function applyRoute(route) {
        state.route = route;
        state.steps = Array.isArray(route.steps) ? route.steps : [];
        state.along = 0;
        state.announced = {};
        state.spokenNow = {};
        state.lastNextStepIndex = null;
        state.offRouteSince = null;

        prepareGeometry(route.polyline);
        drawRouteOnMap(route, !state.navigating);
        renderRouteSummary(route);

        els.btnStartNav.disabled = false;
    }

    function renderRouteSummary(route) {
        els.routeSummary.classList.remove('hidden');
        setText(els.sumDistance, formatDistance(route.distance_m || 0));
        setText(els.sumDuration, formatDuration(route.duration_min || 0));

        const summary = route.summary || {};
        const score = Number(summary.average_score ?? 100);
        setText(els.sumScore, `Skor ${score.toFixed(0)}`);

        if ((summary.repair_distance_m || 0) > 0) {
            els.sumConditionDot.className = 'w-2.5 h-2.5 rounded-full bg-orange-500 mt-1 shrink-0';
            setText(
                els.sumCondition,
                `Melewati sekitar ${formatDistance(summary.repair_distance_m)} segmen yang sedang diperbaiki. Hati-hati pada bagian bergaris putus-putus.`
            );
        } else if (score >= 70) {
            els.sumConditionDot.className = 'w-2.5 h-2.5 rounded-full bg-brand-500 mt-1 shrink-0';
            setText(els.sumCondition, 'Sebagian besar jalur berupa trotoar dengan kondisi baik dan aman untuk pejalan kaki.');
        } else {
            els.sumConditionDot.className = 'w-2.5 h-2.5 rounded-full bg-amber-500 mt-1 shrink-0';
            setText(els.sumCondition, 'Sebagian jalur perlu perhatian. Tetap waspada terhadap kondisi trotoar.');
        }
    }

    els.btnFindRoute.addEventListener('click', () => findRoute());
    els.btnStartNav.addEventListener('click', () => startNavigation());

    // -----------------------------------------------------------------
    // Navigation lifecycle
    // -----------------------------------------------------------------
    function requestCompassPermission() {
        if (typeof DeviceOrientationEvent === 'undefined') {
            return Promise.resolve();
        }

        if (typeof DeviceOrientationEvent.requestPermission === 'function') {
            return DeviceOrientationEvent.requestPermission()
                .then((result) => {
                    if (result === 'granted') attachCompass();
                })
                .catch(() => {});
        }

        attachCompass();
        return Promise.resolve();
    }

    function attachCompass() {
        if (state.orientationHandler) return;

        state.orientationHandler = (event) => {
            let heading = null;

            if (typeof event.webkitCompassHeading === 'number') {
                heading = event.webkitCompassHeading;
            } else if (event.absolute && typeof event.alpha === 'number') {
                heading = (360 - event.alpha) % 360;
            } else if (typeof event.alpha === 'number' && event.alpha !== null) {
                heading = (360 - event.alpha) % 360;
            }

            if (heading !== null && Number.isFinite(heading)) {
                state.compassHeading = heading;
                if (!state.gpsHeading) updateUserHeading(heading);
                setText(els.navHeading, `Arah: ${compassLabel(heading)}`);
            }
        };

        window.addEventListener('deviceorientationabsolute', state.orientationHandler, true);
        window.addEventListener('deviceorientation', state.orientationHandler, true);
    }

    function detachCompass() {
        if (!state.orientationHandler) return;
        window.removeEventListener('deviceorientationabsolute', state.orientationHandler, true);
        window.removeEventListener('deviceorientation', state.orientationHandler, true);
        state.orientationHandler = null;
    }

    async function requestWakeLock() {
        try {
            if ('wakeLock' in navigator) {
                state.wakeLock = await navigator.wakeLock.request('screen');
            }
        } catch (_) {
            state.wakeLock = null;
        }
    }

    async function releaseWakeLock() {
        try {
            if (state.wakeLock) await state.wakeLock.release();
        } catch (_) {
            // ignore
        }
        state.wakeLock = null;
    }

    async function startNavigation() {
        if (!state.route) {
            await findRoute({ autostart: true });
            return;
        }

        state.navigating = true;
        state.arrived = false;
        state.offRouteSince = null;
        state.announced = {};
        state.spokenNow = {};

        els.setupHeader.classList.add('hidden');
        els.setupSheet.classList.add('hidden');
        els.navBanner.classList.remove('hidden');
        els.navPanel.classList.remove('hidden');
        els.arrivalOverlay.classList.add('hidden');
        els.rerouteOverlay.classList.add('hidden');

        if (state.origin) createUserMarker(state.origin.lat, state.origin.lng);
        updateUserHeading(state.currentBearing);
        map.setView([state.origin?.lat ?? -7.2663, state.origin?.lng ?? 112.7965], 17, { animate: false });

        prepareGeometry(state.route.polyline);
        drawRouteOnMap(state.route, false);

        await requestCompassPermission();
        await requestWakeLock();

        if (navigator.geolocation) {
            state.watchId = navigator.geolocation.watchPosition(
                onPosition,
                onPositionError,
                { enableHighAccuracy: true, maximumAge: 1000, timeout: 15000 }
            );
        } else {
            showToast('Perangkat tidak mendukung GPS. Gunakan mode simulasi.', 'error');
        }

        const startStep = state.steps[0];
        if (startStep) {
            setText(els.navStatus, 'Navigasi aktif. Ikuti instruksi di layar.');
            speak(startStep.instruction);
        }

        updateNavigationUI(projectOnRoute(state.origin?.lat ?? 0, state.origin?.lng ?? 0));
    }

    function onPosition(position) {
        if (!state.navigating || state.arrived || state.demoActive) return;

        const coords = position.coords;
        const point = { lat: coords.latitude, lng: coords.longitude };

        state.lastPosition = point;
        state.gpsHeading = (typeof coords.heading === 'number' && Number.isFinite(coords.heading)) ? coords.heading : null;

        setText(els.gpsStatus, `Akurasi GPS sekitar ${Math.round(coords.accuracy || 0)} meter`);
        handleLocation(point, {
            heading: state.gpsHeading ?? state.compassHeading,
            accuracy: coords.accuracy || null,
        });
    }

    function onPositionError(error) {
        if (state.arrived) return;

        if (error.code === error.PERMISSION_DENIED) {
            setText(els.navStatus, 'Izin GPS ditolak. Gunakan mode simulasi.');
            showToast('Izin lokasi ditolak. Aktifkan GPS untuk navigasi real-time.', 'error');
        } else {
            setText(els.navStatus, 'Sinyal GPS lemah. Mencari lokasi...');
        }
    }

    function handleLocation(point, options = {}) {
        const projection = projectOnRoute(point.lat, point.lng);
        state.along = projection.along;
        state.lastPosition = point;

        const heading = options.heading ?? (Number.isFinite(projection.bearing) ? projection.bearing : state.currentBearing);
        updateUserHeading(heading);
        createUserMarker(point.lat, point.lng);

        if (!state.demoActive) {
            map.panTo([point.lat, point.lng], { animate: true, duration: 0.35 });
        } else {
            map.panTo([point.lat, point.lng], { animate: true, duration: 0.2 });
        }

        if (Number.isFinite(options.heading)) {
            setText(els.navHeading, `Arah: ${compassLabel(options.heading)}`);
        }

        updateNavigationUI(projection);

        if (!state.arrived) {
            detectOffRoute(point, projection.distance, options);
        }

        if (options.accuracy) {
            setText(
                els.navStatus,
                options.accuracy <= 25
                    ? `GPS aktif • akurasi ${Math.round(options.accuracy)} m`
                    : `Sinyal GPS lemah • akurasi ${Math.round(options.accuracy)} m`
            );
        } else if (state.demoActive) {
            setText(els.navStatus, 'Mode simulasi aktif.');
        }
    }

    function updateNavigationUI(projection) {
        if (!state.route) return;

        const total = state.geometry?.total || state.route.distance_m || 0;
        const along = Math.max(0, Math.min(total, projection.along || 0));
        const remaining = Math.max(0, total - along);

        setText(els.navRemaining, formatDistance(remaining));
        setText(els.navEta, `± ${formatDuration(remaining / WALK_SPEED_MPS / 60)} lagi`);
        els.navProgress.style.width = `${total > 0 ? Math.min(100, (along / total) * 100) : 0}%`;

        const nextStep = findNextStep(along);
        if (!nextStep) return;

        const distanceToNext = Math.max(0, (nextStep.distance_from_start_m || 0) - along);
        const isArrival = nextStep.maneuver === 'arrive';

        setText(els.maneuverDistance, isArrival ? formatDistance(remaining) : formatDistance(distanceToNext));
        setText(els.maneuverInstruction, nextStep.instruction || '-');
        setText(els.navStreet, nextStep.street || 'Jalur pejalan kaki');
        setText(els.navNextStreet, isArrival ? 'Tujuan akhir' : (nextStep.street || 'Lanjutkan perjalanan'));
        els.maneuverIcon.innerHTML = maneuverSvg(nextStep.maneuver);

        if (state.lastNextStepIndex !== nextStep.index) {
            state.lastNextStepIndex = nextStep.index;
            els.navBanner.classList.remove('sheet-enter');
            void els.navBanner.offsetWidth;
            els.navBanner.classList.add('sheet-enter');
        }

        if (!isArrival) {
            announceStep(nextStep, distanceToNext);
        }

        if (remaining <= ARRIVAL_RADIUS_M || (isArrival && distanceToNext <= ARRIVAL_RADIUS_M)) {
            completeNavigation();
        }
    }

    function findNextStep(along) {
        if (!state.steps.length) return null;

        for (const step of state.steps) {
            if (step.maneuver === 'depart') continue;
            if ((step.distance_from_start_m || 0) > along + 1.5) return step;
        }

        return state.steps[state.steps.length - 1];
    }

    function announceStep(step, distanceToNext) {
        if (!step || state.muted) return;

        const index = step.index;
        const already = state.announced[index] ?? Infinity;
        let threshold = null;

        for (const value of VOICE_THRESHOLDS) {
            if (distanceToNext <= value && already > value) {
                threshold = value;
                break;
            }
        }

        if (threshold !== null) {
            state.announced[index] = threshold;
            if (threshold <= 15) {
                if (!state.spokenNow[index]) {
                    state.spokenNow[index] = true;
                    speak(`${spokenInstruction(step)} sekarang.`);
                }
            } else {
                speak(`${spokenInstruction(step)} dalam ${Math.round(threshold / 5) * 5} meter.`);
            }
        }
    }

    function spokenInstruction(step) {
        const street = step.street ? ` ke ${step.street}` : '';
        switch (step.maneuver) {
            case 'left': return `Belok kiri${street}`;
            case 'right': return `Belok kanan${street}`;
            case 'slight_left': return `Belok sedikit ke kiri${street}`;
            case 'slight_right': return `Belok sedikit ke kanan${street}`;
            case 'sharp_left': return `Belok tajam ke kiri${street}`;
            case 'sharp_right': return `Belok tajam ke kanan${street}`;
            case 'uturn': return 'Putar balik';
            case 'arrive': return 'Anda telah tiba di tujuan';
            case 'depart': return step.instruction || 'Mulai perjalanan';
            default: return `Lanjutkan lurus${street}`;
        }
    }

    function maneuverSvg(maneuver) {
        const base = 'class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"';
        switch (maneuver) {
            case 'left':
            case 'slight_left':
            case 'sharp_left':
                return `<svg ${base}><path d="M9 4 4 9l5 5"/><path d="M4 9h8a7 7 0 0 1 7 7v1"/></svg>`;
            case 'right':
            case 'slight_right':
            case 'sharp_right':
                return `<svg ${base}><path d="m15 4 5 5-5 5"/><path d="M20 9h-8a7 7 0 0 0-7 7v1"/></svg>`;
            case 'uturn':
                return `<svg ${base}><path d="M7 20V9a5 5 0 0 1 10 0v11"/><path d="m13 17 4 4 4-4"/></svg>`;
            case 'arrive':
                return `<svg ${base}><path d="M5 21V4h9l-1.5 4L14 12H5"/><path d="M5 21h10"/></svg>`;
            case 'depart':
                return `<svg ${base}><path d="m3 11 19-9-9 19-2-8-8-2z"/></svg>`;
            default:
                return `<svg ${base}><path d="M12 20V4"/><path d="m6 10 6-6 6 6"/></svg>`;
        }
    }

    function detectOffRoute(point, deviation, options = {}) {
        if (state.demoActive || state.arrived || state.rerouting) return;

        if (deviation > OFF_ROUTE_METERS) {
            setText(els.navStatus, `Anda ${Math.round(deviation)} m dari jalur. Kembali ke rute atau tunggu rute baru.`);

            if (state.offRouteSince === null) {
                state.offRouteSince = Date.now();
                showToast('Anda keluar dari jalur. Mencari rute baru...', 'info');
            }

            if (Date.now() - state.offRouteSince >= OFF_ROUTE_SECONDS * 1000 && !state.rerouteController) {
                reroute(point);
            }
        } else {
            if (state.offRouteSince !== null) {
                setText(els.navStatus, 'Kembali ke jalur. Navigasi dilanjutkan.');
            }
            state.offRouteSince = null;
        }
    }

    async function reroute(from) {
        if (state.rerouting || !state.destination) return;

        state.rerouting = true;
        state.offRouteSince = null;
        els.rerouteOverlay.classList.remove('hidden');
        setText(els.rerouteTitle, 'Menghitung ulang rute...');
        setText(els.rerouteText, 'Anda terdeteksi keluar dari jalur. Rute baru sedang disiapkan.');
        speak('Anda keluar dari jalur. Menghitung ulang rute.');

        const controller = new AbortController();
        state.rerouteController = controller;
        state.rerouteTimeout = setTimeout(() => controller.abort(), 8000);

        try {
            const route = await requestRoute(from, state.destination, state.preference, controller.signal);
            applyRoute(route);
            prepareGeometry(route.polyline);
            state.along = projectOnRoute(from.lat, from.lng).along;
            state.announced = {};
            state.spokenNow = {};
            state.lastNextStepIndex = null;
            drawRouteOnMap(route, false);
            updateNavigationUI(projectOnRoute(from.lat, from.lng));
            setText(els.rerouteTitle, 'Rute baru ditemukan');
            setText(els.rerouteText, 'Lanjutkan perjalanan mengikuti instruksi terbaru.');
            showToast('Rute baru ditemukan. Lanjutkan perjalanan.', 'success');
            const next = findNextStep(state.along);
            if (next) speak(next.instruction);
            setTimeout(() => els.rerouteOverlay.classList.add('hidden'), 900);
        } catch (error) {
            showToast(
                error.name === 'AbortError'
                    ? 'Perhitungan rute terlalu lama. Mencoba lagi saat Anda bergerak.'
                    : 'Gagal menghitung ulang rute. Periksa koneksi Anda.',
                'error'
            );
            els.rerouteOverlay.classList.add('hidden');
            state.offRouteSince = null;
        } finally {
            clearTimeout(state.rerouteTimeout);
            state.rerouteTimeout = null;
            state.rerouteController = null;
            state.rerouting = false;
        }
    }

    function completeNavigation() {
        if (state.arrived) return;

        state.arrived = true;
        state.navigating = false;
        stopTracking();

        els.navBanner.classList.add('hidden');
        els.navPanel.classList.add('hidden');
        els.arrivalOverlay.classList.remove('hidden');
        els.navProgress.style.width = '100%';

        const distance = state.route?.distance_m || 0;
        setText(
            els.arrivalText,
            `Anda menempuh sekitar ${formatDistance(distance)}. Terima kasih telah berjalan bersama PedestriRoute.`
        );
        speak('Anda telah tiba di tujuan.');
    }

    function stopTracking() {
        if (state.watchId !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(state.watchId);
            state.watchId = null;
        }

        detachCompass();
        releaseWakeLock();

        if (state.demoTimer) {
            clearInterval(state.demoTimer);
            state.demoTimer = null;
        }

        if (state.offRouteTimer) {
            clearTimeout(state.offRouteTimer);
            state.offRouteTimer = null;
        }

        if (state.rerouteController) {
            state.rerouteController.abort();
            state.rerouteController = null;
        }

        if (state.rerouteTimeout) {
            clearTimeout(state.rerouteTimeout);
            state.rerouteTimeout = null;
        }

        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
    }

    function endNavigation() {
        stopTracking();
        state.navigating = false;
        state.arrived = false;
        state.demoActive = false;
        state.demoAlong = 0;
        state.offRouteSince = null;
        els.demoLabel.textContent = 'Simulasi';
        els.btnDemoReroute.classList.add('hidden');

        els.navBanner.classList.add('hidden');
        els.navPanel.classList.add('hidden');
        els.arrivalOverlay.classList.add('hidden');
        els.rerouteOverlay.classList.add('hidden');
        els.setupHeader.classList.remove('hidden');
        els.setupSheet.classList.remove('hidden');

        if (state.route) {
            drawRouteOnMap(state.route, true);
        }

        showToast('Navigasi diakhiri.', 'info');
    }

    els.btnEndNav.addEventListener('click', endNavigation);
    els.btnEndNavTop.addEventListener('click', endNavigation);
    els.btnArrivalClose.addEventListener('click', () => {
        els.arrivalOverlay.classList.add('hidden');
        endNavigation();
    });

    // -----------------------------------------------------------------
    // Demo mode
    // -----------------------------------------------------------------
    els.btnDemo.addEventListener('click', () => {
        if (!state.navigating) return;

        if (state.demoActive) {
            stopDemo();
            return;
        }

        state.demoActive = true;
        state.demoAlong = state.along || 0;
        els.demoLabel.textContent = 'Hentikan';
        els.btnDemoReroute.classList.remove('hidden');
        setText(els.navStatus, 'Mode simulasi aktif.');
        showToast('Mode simulasi diaktifkan. Posisi bergerak otomatis.', 'info');

        if (state.watchId !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(state.watchId);
            state.watchId = null;
        }

        state.demoTimer = setInterval(() => {
            if (!state.demoActive || !state.geometry) return;

            state.demoAlong = Math.min(state.geometry.total, state.demoAlong + 6);
            const point = pointAtAlong(state.demoAlong);

            if (point) {
                handleLocation({ lat: point.lat, lng: point.lng }, {
                    heading: point.bearing,
                    accuracy: null,
                });
            }

            if (state.demoAlong >= state.geometry.total) {
                stopDemo();
            }
        }, 400);
    });

    function stopDemo() {
        state.demoActive = false;
        if (state.demoTimer) {
            clearInterval(state.demoTimer);
            state.demoTimer = null;
        }
        els.demoLabel.textContent = 'Simulasi';
        els.btnDemoReroute.classList.add('hidden');
        setText(els.navStatus, 'Mode simulasi dihentikan.');

        if (state.navigating && state.watchId === null && navigator.geolocation) {
            state.watchId = navigator.geolocation.watchPosition(
                onPosition,
                onPositionError,
                { enableHighAccuracy: true, maximumAge: 1000, timeout: 15000 }
            );
        }
    }

    els.btnDemoReroute.addEventListener('click', () => {
        if (!state.demoActive) return;
        const point = pointAtAlong(state.along);
        if (point) {
            showToast('Mensimulasikan deviasi 35 meter...', 'info');
            reroute({ lat: point.lat + 0.0003, lng: point.lng + 0.0003 });
        }
    });

    // -----------------------------------------------------------------
    // Handoff from the route search page (F7 UI)
    // -----------------------------------------------------------------
    function readIncomingRoute() {
        const params = new URLSearchParams(window.location.search);
        const destLat = parseFloat(params.get('dest_lat') ?? params.get('destination_lat'));
        const destLng = parseFloat(params.get('dest_lng') ?? params.get('destination_lng'));
        const originLat = parseFloat(params.get('origin_lat'));
        const originLng = parseFloat(params.get('origin_lng'));
        const pref = params.get('preference');
        const autostart = params.get('start') === '1';

        let incoming = null;

        try {
            const stored = sessionStorage.getItem('pedestriroute:navigation');
            if (stored) {
                incoming = JSON.parse(stored);
                sessionStorage.removeItem('pedestriroute:navigation');
            }
        } catch (_) {
            incoming = null;
        }

        if (Number.isFinite(originLat) && Number.isFinite(originLng)) {
            setOrigin(originLat, originLng, 'Titik awal pilihan');
        } else if (incoming?.origin) {
            setOrigin(incoming.origin.lat, incoming.origin.lng, incoming.origin.label || 'Titik awal pilihan');
        }

        if (Number.isFinite(destLat) && Number.isFinite(destLng)) {
            state.destination = { lat: destLat, lng: destLng, label: params.get('dest_label') || 'Tujuan' };
            els.destInput.value = state.destination.label;
            els.btnClearDest.classList.remove('hidden');
            setText(els.headerSubtitle, state.destination.label);
        } else if (incoming?.dest) {
            state.destination = {
                lat: incoming.dest.lat,
                lng: incoming.dest.lng,
                label: incoming.dest.label || 'Tujuan',
            };
            els.destInput.value = state.destination.label;
            els.btnClearDest.classList.remove('hidden');
            setText(els.headerSubtitle, state.destination.label);
        }

        if (pref === 'safest' || pref === 'fastest') {
            state.preference = pref;
            paintChips();
        } else if (incoming?.preference === 'safest' || incoming?.preference === 'fastest') {
            state.preference = incoming.preference;
            paintChips();
        }

        return { hasDestination: Boolean(state.destination), autostart };
    }

    // -----------------------------------------------------------------
    // Boot
    // -----------------------------------------------------------------
    window.addEventListener('pagehide', stopTracking);
    window.addEventListener('beforeunload', stopTracking);

    const incoming = readIncomingRoute();
    locateUser(false);

    if (!incoming.hasDestination) {
        window.setTimeout(() => {
            if (!state.destination) {
                // Keep the sheet usable; the user may still type a destination.
                setText(els.headerSubtitle, 'Tentukan tujuan Anda');
            }
        }, 300);
    } else {
        const waitForOrigin = setInterval(() => {
            if (!state.origin) return;
            clearInterval(waitForOrigin);
            findRoute({ autostart: incoming.autostart });
        }, 250);

        window.setTimeout(() => {
            clearInterval(waitForOrigin);
            if (state.origin) findRoute({ autostart: incoming.autostart });
        }, 6000);
    }
})();
</script>
@endpush
