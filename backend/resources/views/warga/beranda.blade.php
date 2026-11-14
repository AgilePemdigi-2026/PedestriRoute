@extends('layouts.app')

@section('title', 'Beranda Warga - PedestriRoute')

@push('styles')
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #map {
        height: 640px;
        max-height: 80vh;
        width: 100%;
        border-radius: 1.25rem;
        z-index: 10;
    }

    .scrollbar-none::-webkit-scrollbar, .no-scrollbar::-webkit-scrollbar { display: none; }
    .scrollbar-none, .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    /* Custom Leaflet Marker Container - Strict Flex Centering */
    .custom-heat-marker {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: transparent !important;
        border: none !important;
    }

    /* Heatmap Marker Dots - Clean, Geometric & Optically Centered */
    .heat-dot-fixed {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: rgba(5, 150, 105, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: transform 0.15s ease;
    }
    .heat-dot-fixed:hover {
        transform: scale(1.15);
    }
    .heat-dot-fixed-core {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background-color: #059669;
        border: 2px solid #ffffff;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.25);
    }

    .heat-dot-repairing {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: rgba(2, 132, 199, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: transform 0.15s ease;
    }
    .heat-dot-repairing:hover {
        transform: scale(1.15);
    }
    .heat-dot-repairing-core {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background-color: #0284c7;
        border: 2px solid #ffffff;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.25);
    }

    .heat-dot-low {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: rgba(217, 119, 6, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: transform 0.15s ease;
    }
    .heat-dot-low:hover {
        transform: scale(1.15);
    }
    .heat-dot-low-core {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background-color: #d97706;
        border: 2px solid #ffffff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }

    .heat-dot-medium {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: rgba(234, 88, 12, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: transform 0.15s ease;
    }
    .heat-dot-medium:hover {
        transform: scale(1.15);
    }
    .heat-dot-medium-core {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background-color: #ea580c;
        border: 2px solid #ffffff;
        color: #ffffff;
        font-size: 11px;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.25);
    }

    .heat-dot-high {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: rgba(220, 38, 38, 0.22);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: transform 0.15s ease;
        animation: heatPulseHigh 2s infinite ease-in-out;
    }
    .heat-dot-high:hover {
        animation-play-state: paused;
        transform: scale(1.15);
    }
    .heat-dot-high-core {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background-color: #dc2626;
        border: 2px solid #ffffff;
        color: #ffffff;
        font-size: 12px;
        font-weight: 900;
        font-variant-numeric: tabular-nums;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        box-shadow: 0 1px 4px rgba(220, 38, 38, 0.4);
    }

    @keyframes heatPulseHigh {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.08); }
    }

    /* Custom Leaflet Popup Card */
    .leaflet-popup-content-wrapper {
        padding: 0 !important;
        border-radius: 1.25rem !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
        border: 1px solid #e2e8f0 !important;
        overflow: hidden !important;
    }
    .leaflet-popup-content {
        margin: 0 !important;
        line-height: 1.4 !important;
    }
    .leaflet-popup-tip {
        background: #ffffff !important;
    }

    /* In-Map Bottom Sheet Animations & Scrollbar */
    #reportBottomSheet {
        transition: transform 0.3s ease-out, opacity 0.2s ease-out;
    }
    #sheetBody::-webkit-scrollbar {
        width: 4px;
    }
    #sheetBody::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }
    #sheetBody::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    /* Selected Marker */
    .heat-marker-selected {
        z-index: 9999 !important;
    }
    .heat-marker-selected .heat-dot-low-core,
    .heat-marker-selected .heat-dot-medium-core,
    .heat-marker-selected .heat-dot-high-core,
    .heat-marker-selected .heat-dot-fixed-core,
    .heat-marker-selected .heat-dot-repairing-core {
        transform: scale(1.2);
        outline: 3px solid #006257;
    }
</style>
@endpush

@section('content')
<div class="px-5 pt-6 pb-20 flex flex-col flex-1">
    <!-- Top Header -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-base font-extrabold text-slate-900 tracking-tight">PedestriRoute</h1>
        </div>

        <div class="flex items-center gap-2">
            <!-- Notification bell (Stub Sprint 1) -->
            <button type="button"
                    data-notification-link
                    onclick="window.location.href='/notifikasi'"
                    class="relative w-8 h-8 rounded-xl bg-slate-100 flex items-center
                        justify-center text-slate-600 hover:bg-slate-200 transition"
                    aria-label="Notifikasi">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
            </button>

            <!-- User Avatar & Dropdown -->
            <div class="relative">
                <button id="userMenuBtn" class="w-8 h-8 rounded-xl overflow-hidden border border-slate-200 focus:outline-none relative flex items-center justify-center bg-teal-800 text-white font-bold text-xs shadow-sm" aria-label="Menu Pengguna">
                    <img id="userAvatar" src="" alt="Avatar" class="w-full h-full object-cover hidden" onerror="this.classList.add('hidden'); document.getElementById('userAvatarFallback')?.classList.remove('hidden');">
                    <span id="userAvatarFallback" class="w-full h-full flex items-center justify-center bg-teal-800 text-white font-bold text-xs uppercase">W</span>
                </button>
                <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-slate-100 py-2 z-50">
                    <div class="px-4 py-2 border-b border-slate-100">
                        <p id="menuUserName" class="text-xs font-bold text-slate-900 truncate">Warga</p>
                        <p id="menuUserEmail" class="text-[11px] text-slate-500 truncate">user@gmail.com</p>
                    </div>
                    <button id="logoutBtn" class="w-full text-left px-4 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 flex items-center gap-2 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Keluar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Active AI Analysis Banner (Appears when counts.diproses > 0) -->
    <div id="aiAnalysisBanner" class="hidden mb-3 p-3 bg-gradient-to-r from-sky-50 to-blue-50 border border-sky-200 rounded-2xl shadow-sm flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-xl bg-sky-500 text-white flex items-center justify-center text-xs animate-pulse">
                <i class="fa-solid fa-radar"></i>
            </span>
            <div>
                <p class="text-xs font-bold text-sky-950">Analisis ML Berjalan</p>
                <p id="aiAnalysisCountText" class="text-[11px] text-sky-700">Aduan sedang diproses Computer Vision</p>
            </div>
        </div>
        <a href="/riwayat-laporan?status=DIPROSES" class="px-2.5 py-1 bg-sky-600 hover:bg-sky-700 text-white text-[11px] font-bold rounded-lg transition shadow-sm shrink-0">
            Lihat
        </a>
    </div>

    <!-- User Welcome & Quick Action Card -->
    <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm mb-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Halo, <span id="greetingName">Warga</span></h2>
            </div>
            <a href="/lapor-kondisi" class="px-3.5 py-2 bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold rounded-xl shadow-sm flex items-center gap-1.5 transition shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                <span>Lapor</span>
            </a>
        </div>

        <!-- Status Laporan Saya Stats -->
        <div class="pt-3 border-t border-slate-50">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-700">Laporan Saya</span>
                <a href="/riwayat-laporan" id="totalReportsCount" class="text-xs font-bold text-brand-600 hover:text-brand-700 transition">
                    Total: 0 →
                </a>
            </div>
            <div class="grid grid-cols-3 gap-2">
                <a href="/riwayat-laporan" class="bg-amber-50/70 border border-amber-100/80 rounded-xl p-2.5 text-center hover:bg-amber-100/60 transition">
                    <p class="text-[11px] font-semibold text-amber-800">Menunggu</p>
                    <p id="countMenunggu" class="text-base font-extrabold text-amber-900 mt-0.5">0</p>
                </a>
                <a href="/riwayat-laporan" class="bg-emerald-50/70 border border-emerald-100/80 rounded-xl p-2.5 text-center hover:bg-emerald-100/60 transition">
                    <p class="text-[11px] font-semibold text-emerald-800">Valid</p>
                    <p id="countTervalidasi" class="text-base font-extrabold text-emerald-900 mt-0.5">0</p>
                </a>
                <a href="/riwayat-laporan" class="bg-rose-50/70 border border-rose-100/80 rounded-xl p-2.5 text-center hover:bg-rose-100/60 transition">
                    <p class="text-[11px] font-semibold text-rose-800">Ditolak</p>
                    <p id="countDitolak" class="text-base font-extrabold text-rose-900 mt-0.5">0</p>
                </a>
            </div>
        </div>
    </div>

    <!-- Map Section (Toggleable: Heatmap / Normal Map) -->
    <div class="mb-4">
        <div class="flex items-center justify-between mb-2.5">
            <div>
                <h3 class="text-xs font-bold text-slate-800 tracking-wide uppercase" id="mapTitle">Peta Heatmap Kelayakan</h3>
                <p class="text-[11px] text-slate-500 mt-0.5" id="mapSubtitle">Kondisi segmen jalur ramah pejalan kaki</p>
            </div>
            <!-- Mode Toggle Switcher -->
            <div class="flex items-center bg-slate-100 p-0.5 rounded-xl border border-slate-200">
                <button type="button" onclick="setMapMode('heatmap')" id="btnModeHeatmap" class="px-2.5 py-1 rounded-lg text-xs font-bold transition bg-brand-600 text-white shadow-sm flex items-center gap-1">
                    <span>Heatmap</span>
                </button>
                <button type="button" onclick="setMapMode('normal')" id="btnModeNormal" class="px-2.5 py-1 rounded-lg text-xs font-bold transition text-slate-600 hover:text-slate-900 flex items-center gap-1">
                    <span>Standar</span>
                </button>
            </div>
        </div>

        <!-- Integrated Status & Category Filter (1 Row Dropdown) -->
        <div class="grid grid-cols-2 gap-2 mb-2.5 transition-all" id="statusFilterGroup">
            <!-- Filter Status Dropdown -->
            <div class="relative">
                <select id="selectStatusFilter" onchange="selectRepairFilter(this.value)"
                    class="w-full pl-3 pr-8 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm appearance-none cursor-pointer transition">
                    <option value="aktif">🟡 Aduan Aktif</option>
                    <option value="sedang">🔵 Sedang Dikerjakan</option>
                    <option value="selesai">🟢 Sudah Diperbaiki</option>
                    <option value="semua">⚪ Semua Status</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Filter Kategori Dropdown -->
            <div class="relative">
                <select id="selectCategoryFilter" onchange="selectCategory(this.value === '' ? null : parseInt(this.value, 10))"
                    class="w-full pl-3 pr-8 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm appearance-none cursor-pointer transition">
                    <option value="">Semua Kategori</option>
                    <option value="1">Trotoar Rusak</option>
                    <option value="2">Masalah Drainase</option>
                    <option value="3">Guiding Block</option>
                    <option value="5">Parkir Liar</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Hidden container to preserve categoryChips reference if needed -->
        <div id="categoryChips" class="hidden"></div>

        <!-- Normal Map Service Area Info Banner (Only for Normal Mode) -->
        <div id="normalMapBanner" class="hidden mb-2 flex items-center gap-1.5 text-xs text-slate-500 px-0.5" role="note">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
            <span>Wilayah Layanan Aktif: <span class="font-medium text-slate-700">Kecamatan Mulyorejo, Surabaya</span></span>
        </div>

        <!-- Map Container with Relative Overlay -->
        <div class="relative rounded-2xl overflow-hidden border border-slate-200 shadow-sm">
            <div id="map"></div>

            <!-- Heatmap Loading Spinner Indicator -->
            <div id="mapLoading" class="hidden absolute top-3 left-12 z-20 bg-white/90 backdrop-blur-md px-2.5 py-1.5 rounded-xl shadow-md border border-slate-100 flex items-center gap-2">
                <svg class="w-3.5 h-3.5 animate-spin text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span class="text-[11px] font-bold text-slate-700">Memperbarui peta...</span>
            </div>

            <!-- Floating Top-Right Map Controls: Lokasi Saya & Toggle Jalur -->
            <div class="absolute top-3 right-3 z-20 flex flex-col gap-2">
                <!-- Lokasi Saya Button -->
                <button type="button" onclick="locateUser()" id="btnLocateUser"
                    class="w-9 h-9 rounded-xl bg-white/95 backdrop-blur-md text-slate-700 hover:text-brand-700 hover:bg-white shadow-md border border-slate-200/80 flex items-center justify-center transition active:scale-95"
                    title="Pusatkan ke lokasi saya" aria-label="Lokasi Saya">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="3" fill="currentColor"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v3m0 14v3M2 12h3m14 0h3"/>
                        <circle cx="12" cy="12" r="7" stroke="currentColor"/>
                    </svg>
                </button>
            </div>

            <!-- Truncated / Zoom In Warning Banner (FR-F6-08) -->
            <div id="mapNotice" class="hidden absolute bottom-3 left-3 right-3 z-20 bg-amber-500/95 text-white px-3 py-2 rounded-xl text-center text-xs font-semibold shadow-lg backdrop-blur-sm">
                Perbesar peta untuk melihat semua segmen jalur.
            </div>

            <!-- Empty Data Notice Overlay (FR-F6-03, FR-F6-04) -->
            <div id="mapEmptyNotice" class="hidden absolute inset-0 z-20 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl p-4 text-center max-w-xs shadow-xl border border-slate-100">
                    <div class="w-10 h-10 bg-slate-100 text-slate-400 rounded-xl flex items-center justify-center mx-auto mb-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21" stroke-linecap="round" stroke-linejoin="round"/>
                            <line x1="9" y1="3" x2="9" y2="18"/>
                            <line x1="15" y1="6" x2="15" y2="21"/>
                        </svg>
                    </div>
                    <p class="text-xs font-bold text-slate-800" id="emptyNoticeTitle">Data tidak tersedia</p>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed" id="emptyNoticeMsg">Data kelayakan jalur belum tersedia untuk area ini.</p>
                    <button onclick="dismissEmptyNotice()" class="mt-3 px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition">
                        Tutup
                    </button>
                </div>
            </div>

            <!-- In-Map Report Bottom Sheet (Opsi B) -->
            <div id="reportBottomSheet" class="absolute bottom-0 inset-x-0 z-30 transform translate-y-full opacity-0 pointer-events-none bg-white rounded-t-3xl shadow-2xl border-t border-slate-200/80 max-h-[60%] flex flex-col transition-all">
                <!-- Grab Handle Bar -->
                <div id="sheetDragHandle" class="w-full pt-2.5 pb-1 flex justify-center cursor-pointer select-none" onclick="closeReportBottomSheet()" title="Tutup lembar aduan">
                    <div class="w-10 h-1 bg-slate-300 rounded-full hover:bg-slate-400 transition"></div>
                </div>

                <!-- Sheet Dynamic Header -->
                <div id="sheetHeader" class="px-4 py-2 border-b border-slate-100 flex items-center justify-between"></div>

                <!-- Sheet Dynamic Scrollable Body -->
                <div id="sheetBody" class="p-4 overflow-y-auto max-h-[calc(640px*0.52)] scrollbar-thin"></div>
            </div>
        </div>

        <!-- Floating Dynamic Legend Card (FR-F6-05, Only for Heatmap Mode) -->
        <div id="legendContainer" class="mt-2.5 bg-white rounded-2xl p-3 border border-slate-100 shadow-sm transition-all">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-1.5">
                    <span class="text-xs font-bold text-slate-700">Kepadatan Titik Aduan</span>
                    <span id="legendFilterHint" class="hidden text-[10px] font-bold text-brand-700 bg-brand-50 border border-brand-200 px-1.5 py-0.2 rounded-md">Filter Aktif</span>
                </div>
                <span id="legendTotalSegments" class="text-xs font-bold text-brand-600">0 titik terpantau</span>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <button type="button" onclick="filterByDensity('low')" id="legendCardLow"
                    class="flex items-center gap-2 bg-amber-50/80 rounded-xl px-2.5 py-1.5 border border-amber-200/80 hover:border-amber-400 transition cursor-pointer text-left active:scale-95"
                    title="Klik untuk filter titik 1 Aduan">
                    <span class="w-3 h-3 rounded-full bg-amber-500 shrink-0"></span>
                    <div class="min-w-0">
                        <span class="text-[11px] font-semibold text-amber-900 block truncate">1 Aduan</span>
                        <strong class="text-xs font-extrabold text-amber-950" id="countSangatLayak">0</strong>
                    </div>
                </button>
                <button type="button" onclick="filterByDensity('med')" id="legendCardMed"
                    class="flex items-center gap-2 bg-orange-50/80 rounded-xl px-2.5 py-1.5 border border-orange-200/80 hover:border-orange-400 transition cursor-pointer text-left active:scale-95"
                    title="Klik untuk filter titik 2–3 Aduan">
                    <span class="w-3 h-3 rounded-full bg-orange-500 shrink-0"></span>
                    <div class="min-w-0">
                        <span class="text-[11px] font-semibold text-orange-900 block truncate">2–3 Aduan</span>
                        <strong class="text-xs font-extrabold text-orange-950" id="countPerluPerhatian">0</strong>
                    </div>
                </button>
                <button type="button" onclick="filterByDensity('high')" id="legendCardHigh"
                    class="flex items-center gap-2 bg-rose-50/80 rounded-xl px-2.5 py-1.5 border border-rose-200/80 hover:border-rose-400 transition cursor-pointer text-left active:scale-95"
                    title="Klik untuk filter titik ≥4 Hotspot">
                    <span class="w-3 h-3 rounded-full bg-rose-600 shrink-0"></span>
                    <div class="min-w-0">
                        <span class="text-[11px] font-semibold text-rose-900 block truncate">≥4 Hotspot</span>
                        <strong class="text-xs font-extrabold text-rose-950" id="countTidakLayak">0</strong>
                    </div>
                </button>
            </div>
        </div>

        <p class="text-[11px] text-slate-400 mt-1.5 text-center">© OpenStreetMap contributors</p>
    </div>
</div>

<!-- Modal Kelengkapan Nomor HP -->
<div id="phoneModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl p-6 w-full max-w-sm shadow-2xl border border-slate-100 text-center">
        <div class="w-12 h-12 bg-teal-100 text-brand-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
            </svg>
        </div>
        <h3 class="text-lg font-bold text-slate-900 mb-1">Lengkapi Nomor Telepon</h3>
        <p class="text-xs text-slate-500 mb-5 leading-relaxed">
            Akun Anda terdaftar via Social Login. Harap masukkan nomor HP untuk kelengkapan identitas laporan.
        </p>

        <form id="updatePhoneForm" class="space-y-4">
            <div class="text-left">
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">Nomor HP / WhatsApp</label>
                <input type="text" id="inputNoHp" placeholder="contoh: 081234567890" required
                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition" />
            </div>
            <button type="submit" id="btnSavePhone"
                class="w-full py-3 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-xl text-sm transition shadow-sm">
                Simpan & Lanjutkan
            </button>
        </form>
    </div>
</div>

<!-- Bottom Navigation Bar matching mockup -->
<div class="fixed bottom-0 max-w-md w-full bg-white border-t border-slate-200 py-2 px-6 flex items-center justify-between z-40">
    <a href="/beranda-warga" class="flex flex-col items-center gap-1 text-brand-600 font-bold">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2L2 12h3v8h6v-6h2v6h6v-8h3L12 2z"/>
        </svg>
        <span class="text-[10px] tracking-tight">Peta</span>
    </a>
    <a href="javascript:void(0)" onclick="showToast('Rute Pejalan Kaki aktif pada Sprint 3', 'info')" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600 transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
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
    <a href="javascript:void(0)" onclick="toggleUserDropdown()" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600 transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        <span class="text-[10px] tracking-tight">Profil</span>
    </a>
</div>
@endsection

@push('scripts')
<!-- Leaflet JS & Leaflet Heat -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
@include('partials.mulyorejo-service-area')
<script>
    // 1. Client Auth Guard (FR-F1-09)
    const token = localStorage.getItem('token');
    if (!token) {
        window.location.href = '/login';
    }

    // 2. Load User Profile & Summary
    function applyUserProfile(user) {
        if (!user) return;
        const displayName = user.nama || 'Warga';
        const initial = displayName.trim().charAt(0).toUpperCase() || 'W';

        document.getElementById('greetingName').innerText = displayName;
        document.getElementById('menuUserName').innerText = displayName;
        document.getElementById('menuUserEmail').innerText = user.email || '';

        const avatarEl = document.getElementById('userAvatar');
        const fallbackEl = document.getElementById('userAvatarFallback');
        if (fallbackEl) {
            fallbackEl.innerText = initial;
        }

        if (avatarEl) {
            if (user.avatar_url) {
                avatarEl.src = user.avatar_url;
                avatarEl.classList.remove('hidden');
                if (fallbackEl) fallbackEl.classList.add('hidden');
            } else {
                avatarEl.classList.add('hidden');
                if (fallbackEl) fallbackEl.classList.remove('hidden');
            }
        }
    }

    try {
        const storedUser = JSON.parse(localStorage.getItem('user'));
        if (storedUser) {
            applyUserProfile(storedUser);
        }
    } catch (e) {}

    async function initBeranda() {
        try {
            const meRes = await fetch('/api/v1/auth/me', {
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json'
                }
            });

            if (!meRes.ok) {
                localStorage.clear();
                window.location.href = '/login';
                return;
            }

            const meData = await meRes.json();
            const user = meData.data;

            if (user.role === 'admin') {
                window.location.href = '/admin/validasi';
                return;
            }

            // Save and apply user profile
            localStorage.setItem('user', JSON.stringify(user));
            applyUserProfile(user);

            // Cek kelengkapan nomor HP (Issue #8)
            if (!user.no_hp) {
                document.getElementById('phoneModal').classList.remove('hidden');
            }

            // Fetch summary
            const summaryRes = await fetch('/api/v1/reports/my-summary', {
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json'
                }
            });

            if (summaryRes.ok) {
                const sData = await summaryRes.json();
                const counts = sData.data;
                document.getElementById('totalReportsCount').innerText = `Total: ${counts.total_laporan}`;
                document.getElementById('countMenunggu').innerText = counts.menunggu_validasi;
                document.getElementById('countTervalidasi').innerText = counts.tervalidasi;
                document.getElementById('countDitolak').innerText = counts.ditolak;

                if (counts.diproses && counts.diproses > 0) {
                    const aiBanner = document.getElementById('aiAnalysisBanner');
                    const aiText = document.getElementById('aiAnalysisCountText');
                    if (aiBanner) aiBanner.classList.remove('hidden');
                    if (aiText) aiText.innerText = `${counts.diproses} aduan sedang dianalisis oleh Computer Vision`;
                }
            }
        } catch (e) {
            console.error('Failed to init beranda:', e);
        }
    }

    initBeranda();

    // Submit handler modal kelengkapan No HP
    const updatePhoneForm = document.getElementById('updatePhoneForm');
    if (updatePhoneForm) {
        updatePhoneForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const noHp = document.getElementById('inputNoHp').value;

            try {
                const response = await fetch('/api/v1/auth/update-phone', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': 'Bearer ' + token
                    },
                    body: JSON.stringify({ no_hp: noHp })
                });

                const resData = await response.json();

                if (!response.ok) {
                    showToast(resData.error ? resData.error.message : 'Gagal menyimpan nomor HP', 'error');
                    return;
                }

                showToast('Nomor HP berhasil disimpan!', 'success');
                document.getElementById('phoneModal').classList.add('hidden');
            } catch (err) {
                showToast('Terjadi kesalahan jaringan.', 'error');
            }
        });
    }

    // 3. User Dropdown Toggle
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');

    function toggleUserDropdown() {
        userDropdown.classList.toggle('hidden');
    }

    userMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleUserDropdown();
    });

    document.addEventListener('click', () => {
        userDropdown.classList.add('hidden');
    });

    // 4. Logout Action
    document.getElementById('logoutBtn').addEventListener('click', async () => {
        try {
            await fetch('/api/v1/auth/logout', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json'
                }
            });
        } catch (e) {}

        localStorage.clear();
        showToast('Berhasil keluar', 'info');
        setTimeout(() => {
            window.location.href = '/login';
        }, 500);
    });

    // 5. Initialize Leaflet Map with Canvas Renderer (FR-F6-07)
    const map = L.map('map', {
        preferCanvas: true,
        attributionControl: false
    }).setView([-7.2663, 112.7965], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);

    const serviceArea = PedestriRouteServiceArea.attach(map);
    setTimeout(() => { map.invalidateSize(); }, 200);

    // Map Mode & Layer Groups (Heatmap, User Location)
    let currentMapMode = 'heatmap';
    const heatmapLayerGroup = L.layerGroup().addTo(map);
    let userLocationMarker = null;
    let selectedCategoryId = null;
    let currentRawClusters = [];
    let currentDensityFilter = null; // null | 'low' | 'med' | 'high'

    // Geolocation handler - Lokasi Saya
    function locateUser() {
        if (!navigator.geolocation) {
            showToast('Perangkat tidak mendukung geolokasi GPS.', 'error');
            return;
        }
        const btn = document.getElementById('btnLocateUser');
        if (btn) btn.classList.add('animate-pulse', 'text-brand-600');

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                if (btn) btn.classList.remove('animate-pulse', 'text-brand-600');
                const lat = pos.coords.latitude;
                const lon = pos.coords.longitude;

                if (userLocationMarker) {
                    map.removeLayer(userLocationMarker);
                }

                const userIcon = L.divIcon({
                    className: 'custom-heat-marker',
                    html: `
                        <div class="relative w-6 h-6 flex items-center justify-center">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-4 w-4 bg-sky-600 border-2 border-white shadow-md"></span>
                        </div>
                    `,
                    iconSize: [24, 24],
                    iconAnchor: [12, 12]
                });

                userLocationMarker = L.marker([lat, lon], { icon: userIcon, zIndexOffset: 1000 }).addTo(map);
                userLocationMarker.bindTooltip('<span class="text-xs font-bold">Lokasi Anda</span>', { permanent: false, direction: 'top' });

                map.flyTo([lat, lon], 17, { duration: 0.6 });

                if (typeof PedestriRouteServiceArea !== 'undefined' && PedestriRouteServiceArea.contains) {
                    if (!PedestriRouteServiceArea.contains(lat, lon)) {
                        showToast('Lokasi Anda di luar area fokus Kecamatan Mulyorejo.', 'info');
                    } else {
                        showToast('Lokasi berhasil ditemukan.', 'success');
                    }
                }
            },
            (err) => {
                if (btn) btn.classList.remove('animate-pulse', 'text-brand-600');
                showToast('Tidak dapat mengakses lokasi GPS. Pastikan izin lokasi aktif.', 'error');
            },
            { enableHighAccuracy: true, timeout: 8000 }
        );
    }

    // Legend Quick Density Filter
    function filterByDensity(level) {
        if (currentDensityFilter === level) {
            currentDensityFilter = null;
        } else {
            currentDensityFilter = level;
        }
        updateLegendFilterUI();
        renderMarkersAndHeat();
    }

    function updateLegendFilterUI() {
        const cardLow = document.getElementById('legendCardLow');
        const cardMed = document.getElementById('legendCardMed');
        const cardHigh = document.getElementById('legendCardHigh');
        const hintEl = document.getElementById('legendFilterHint');

        const baseClassLow = 'flex items-center gap-2 bg-amber-50/80 rounded-xl px-2.5 py-1.5 border transition cursor-pointer text-left active:scale-95';
        const baseClassMed = 'flex items-center gap-2 bg-orange-50/80 rounded-xl px-2.5 py-1.5 border transition cursor-pointer text-left active:scale-95';
        const baseClassHigh = 'flex items-center gap-2 bg-rose-50/80 rounded-xl px-2.5 py-1.5 border transition cursor-pointer text-left active:scale-95';

        if (cardLow) {
            cardLow.className = baseClassLow + (currentDensityFilter === 'low' ? ' border-amber-500 ring-2 ring-amber-400 shadow-sm' : ' border-amber-200/80 hover:border-amber-400');
        }
        if (cardMed) {
            cardMed.className = baseClassMed + (currentDensityFilter === 'med' ? ' border-orange-500 ring-2 ring-orange-400 shadow-sm' : ' border-orange-200/80 hover:border-orange-400');
        }
        if (cardHigh) {
            cardHigh.className = baseClassHigh + (currentDensityFilter === 'high' ? ' border-rose-500 ring-2 ring-rose-400 shadow-sm' : ' border-rose-200/80 hover:border-rose-400');
        }

        if (hintEl) {
            if (currentDensityFilter) {
                hintEl.classList.remove('hidden');
                hintEl.innerText = currentDensityFilter === 'low' ? 'Filter: 1 Aduan' : (currentDensityFilter === 'med' ? 'Filter: 2–3 Aduan' : 'Filter: ≥4 Hotspot');
            } else {
                hintEl.classList.add('hidden');
            }
        }
    }

    function renderMarkersAndHeat() {
        heatmapLayerGroup.clearLayers();
        if (!currentRawClusters || currentRawClusters.length === 0) return;

        let displayClusters = currentRawClusters;
        if (currentDensityFilter === 'low') {
            displayClusters = currentRawClusters.filter(c => c.reports.length === 1);
        } else if (currentDensityFilter === 'med') {
            displayClusters = currentRawClusters.filter(c => c.reports.length >= 2 && c.reports.length <= 3);
        } else if (currentDensityFilter === 'high') {
            displayClusters = currentRawClusters.filter(c => c.reports.length >= 4);
        }

        const currentZoom = (map && typeof map.getZoom === 'function') ? map.getZoom() : 15;

        // 1. Continuous thermal gradient field: ONLY on zoom < 15 to avoid muddy overlap with crisp badges
        if (typeof L.heatLayer === 'function' && displayClusters.length > 0 && currentZoom < 15) {
            try {
                const heatPoints = displayClusters.map(c => {
                    const count = c.reports.length;
                    const allFixed = c.reports.every(r => r.status_perbaikan === 'SUDAH_DIPERBAIKI');
                    let weight = 0.4;
                    if (allFixed) {
                        weight = 0.2;
                    } else if (count >= 4) {
                        weight = 0.95;
                    } else if (count >= 2) {
                        weight = 0.65;
                    }
                    return [c.center.latitude, c.center.longitude, weight];
                });

                const heatCanvas = L.heatLayer(heatPoints, {
                    radius: 28,
                    blur: 18,
                    maxZoom: 16,
                    gradient: {
                        0.2: '#059669',
                        0.45: '#D97706',
                        0.70: '#EA580C',
                        1.0: '#DC2626'
                    }
                });
                heatmapLayerGroup.addLayer(heatCanvas);
            } catch (heatErr) {
                console.warn('Leaflet heatLayer error ignored:', heatErr);
            }
        }

        // 2. Interactive cluster vector markers
        displayClusters.forEach(cluster => {
            const count = cluster.reports.length;
            const lat = cluster.center.latitude;
            const lon = cluster.center.longitude;

            const allFixed = cluster.reports.every(r => r.status_perbaikan === 'SUDAH_DIPERBAIKI');
            const allRepairing = !allFixed && cluster.reports.every(r => r.status_perbaikan === 'SEDANG_DIPERBAIKI');

            let iconClass = 'heat-dot-low';
            let iconHtml = '<div class="heat-dot-low-core"></div>';
            let iconSize = [28, 28];
            let iconAnchor = [14, 14];

            if (allFixed) {
                iconClass = 'heat-dot-fixed';
                iconHtml = '<div class="heat-dot-fixed-core"><svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>';
                iconSize = [32, 32];
                iconAnchor = [16, 16];
            } else if (allRepairing) {
                iconClass = 'heat-dot-repairing';
                iconHtml = '<div class="heat-dot-repairing-core"><svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></div>';
                iconSize = [32, 32];
                iconAnchor = [16, 16];
            } else if (count >= 4) {
                iconClass = 'heat-dot-high';
                iconHtml = `<div class="heat-dot-high-core"><span>${count}</span></div>`;
                iconSize = [42, 42];
                iconAnchor = [21, 21];
            } else if (count >= 2) {
                iconClass = 'heat-dot-medium';
                iconHtml = `<div class="heat-dot-medium-core"><span>${count}</span></div>`;
                iconSize = [34, 34];
                iconAnchor = [17, 17];
            }

            const markerIcon = L.divIcon({
                className: 'custom-heat-marker',
                html: `<div class="${iconClass}">${iconHtml}</div>`,
                iconSize: iconSize,
                iconAnchor: iconAnchor,
            });

            const marker = L.marker([lat, lon], { icon: markerIcon });
            marker.on('click', (e) => {
                if (e && e.originalEvent) {
                    L.DomEvent.stopPropagation(e);
                }
                const z = map.getZoom();
                if (count > 1 && z < 17) {
                    map.flyTo([lat, lon], Math.min(z + 2, 18), { duration: 0.35 });
                } else {
                    openReportBottomSheet(cluster, marker);
                }
            });

            heatmapLayerGroup.addLayer(marker);
        });
    }

    function setMapMode(mode) {
        if (currentMapMode === mode) return;
        currentMapMode = mode;
        closeReportBottomSheet();

        const btnHeatmap = document.getElementById('btnModeHeatmap');
        const btnNormal = document.getElementById('btnModeNormal');
        const statusFilterGroup = document.getElementById('statusFilterGroup');
        const categoryChips = document.getElementById('categoryChips');
        const legendContainer = document.getElementById('legendContainer');
        const normalBanner = document.getElementById('normalMapBanner');
        const mapTitle = document.getElementById('mapTitle');
        const mapSubtitle = document.getElementById('mapSubtitle');
        const noticeEl = document.getElementById('mapNotice');
        const emptyOverlay = document.getElementById('mapEmptyNotice');

        if (mode === 'normal') {
            // Normal Map Mode
            btnNormal.className = 'px-2.5 py-1 rounded-lg text-xs font-bold transition bg-brand-600 text-white shadow-sm flex items-center gap-1';
            btnHeatmap.className = 'px-2.5 py-1 rounded-lg text-xs font-bold transition text-slate-600 hover:text-slate-900 flex items-center gap-1';

            if (statusFilterGroup) statusFilterGroup.classList.add('hidden');
            if (categoryChips) categoryChips.classList.add('hidden');
            legendContainer.classList.add('hidden');
            normalBanner.classList.remove('hidden');

            mapTitle.innerText = 'Peta Wilayah Layanan';
            mapSubtitle.innerText = '';
            mapSubtitle.classList.add('hidden');

            noticeEl.classList.add('hidden');
            emptyOverlay.classList.add('hidden');

            // Remove heatmap layer
            heatmapLayerGroup.clearLayers();
            if (map.hasLayer(heatmapLayerGroup)) {
                map.removeLayer(heatmapLayerGroup);
            }
        } else {
            // Heatmap Mode
            btnHeatmap.className = 'px-2.5 py-1 rounded-lg text-xs font-bold transition bg-brand-600 text-white shadow-sm flex items-center gap-1';
            btnNormal.className = 'px-2.5 py-1 rounded-lg text-xs font-bold transition text-slate-600 hover:text-slate-900 flex items-center gap-1';

            if (statusFilterGroup) statusFilterGroup.classList.remove('hidden');
            if (categoryChips) categoryChips.classList.remove('hidden');
            legendContainer.classList.remove('hidden');
            normalBanner.classList.add('hidden');

            mapTitle.innerText = 'Peta Heatmap Kelayakan';
            mapSubtitle.innerText = 'Kondisi segmen jalur ramah pejalan kaki';
            mapSubtitle.classList.remove('hidden');

            if (!map.hasLayer(heatmapLayerGroup)) {
                map.addLayer(heatmapLayerGroup);
            }

            loadHeatmap();
        }
    }

    // Repair Status filter (Aktif / Selesai / Semua)
    let currentRepairFilter = 'aktif';

    function selectRepairFilter(filter) {
        currentRepairFilter = filter;
        currentDensityFilter = null;
        updateLegendFilterUI();
        closeReportBottomSheet();

        const selectEl = document.getElementById('selectStatusFilter');
        if (selectEl && selectEl.value !== filter) {
            selectEl.value = filter;
        }

        if (currentMapMode === 'heatmap') {
            loadHeatmap();
        }
    }

    // Category selection
    function selectCategory(catId) {
        selectedCategoryId = catId;
        currentDensityFilter = null;
        updateLegendFilterUI();
        closeReportBottomSheet();

        const selectEl = document.getElementById('selectCategoryFilter');
        if (selectEl) {
            selectEl.value = (catId === null || isNaN(catId)) ? '' : String(catId);
        }

        if (currentMapMode === 'heatmap') {
            loadHeatmap();
        }
    }

    function dismissEmptyNotice() {
        document.getElementById('mapEmptyNotice').classList.add('hidden');
    }

    // Active bottom sheet tracking to prevent map reloads from dismissing user's inspection
    let isUserViewingSheet = false;
    let activeSelectedMarker = null;

    function focusOnMarkerUpperZone(marker) {
        if (!marker || !map) return;

        const latLng = marker.getLatLng();
        const mapSize = map.getSize();

        // Target position: horizontally centered, upper quadrant (~26% of container height)
        // so it sits clearly above the bottom sheet (which occupies bottom ~55%)
        const targetY = Math.round(Math.min(mapSize.y * 0.28, Math.max(mapSize.y * 0.22, 110)));
        const targetPoint = L.point(mapSize.x / 2, targetY);

        const currentPoint = map.latLngToContainerPoint(latLng);
        const deltaX = currentPoint.x - targetPoint.x;
        const deltaY = currentPoint.y - targetPoint.y;

        if (Math.abs(deltaX) > 2 || Math.abs(deltaY) > 2) {
            map.panBy([deltaX, deltaY], {
                animate: true,
                duration: 0.45,
                easeLinearity: 0.25
            });
        }
    }

    function closeReportBottomSheet() {
        const sheet = document.getElementById('reportBottomSheet');
        if (sheet) {
            sheet.classList.add('translate-y-full', 'opacity-0', 'pointer-events-none');
            sheet.classList.remove('translate-y-0', 'opacity-100', 'pointer-events-auto');
        }

        if (activeSelectedMarker) {
            const el = activeSelectedMarker.getElement ? activeSelectedMarker.getElement() : activeSelectedMarker._icon;
            if (el) el.classList.remove('heat-marker-selected');
        }
        activeSelectedMarker = null;

        if (isUserViewingSheet) {
            isUserViewingSheet = false;
            debouncedLoadHeatmap();
        }
    }

    // Helper to build and render bottom sheet content for the clicked report/cluster
    function renderReportSheetContent(cluster) {
        const reportsAtSpot = cluster.reports;
        const headerEl = document.getElementById('sheetHeader');
        const bodyEl = document.getElementById('sheetBody');
        if (!headerEl || !bodyEl) return;

        const catIcons = {
            'Trotoar Rusak': `<span class="w-4 h-4 rounded bg-teal-100 text-brand-700 inline-flex items-center justify-center shrink-0"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 21 8.5 3m11.5 18L15.5 3M5.5 15h13M7 9h10M12 21v-6m-2.5 0V9m5 6V9M12 9V3"/></svg></span>`,
            'Masalah Drainase': `<span class="w-4 h-4 rounded bg-sky-100 text-sky-700 inline-flex items-center justify-center shrink-0"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v4m0 10v4M3 12h4m10 0h4"/></svg></span>`,
            'Drainase': `<span class="w-4 h-4 rounded bg-sky-100 text-sky-700 inline-flex items-center justify-center shrink-0"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v4m0 10v4M3 12h4m10 0h4"/></svg></span>`,
            'Guiding Block Rusak': `<span class="w-4 h-4 rounded bg-amber-100 text-amber-700 inline-flex items-center justify-center shrink-0"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="16" cy="4" r="1"/><path stroke-linecap="round" stroke-linejoin="round" d="m18 19 1-7-6 1M5 8l3-3 5.5 3-2.36 3.5M4.24 14.5a5 5 0 0 0 6.88 6M13.76 17.5a5 5 0 0 0-6.88-6"/></svg></span>`,
            'Guiding Block': `<span class="w-4 h-4 rounded bg-amber-100 text-amber-700 inline-flex items-center justify-center shrink-0"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="16" cy="4" r="1"/><path stroke-linecap="round" stroke-linejoin="round" d="m18 19 1-7-6 1M5 8l3-3 5.5 3-2.36 3.5M4.24 14.5a5 5 0 0 0 6.88 6M13.76 17.5a5 5 0 0 0-6.88-6"/></svg></span>`,
            'Penyebrangan Rusak': `<span class="w-4 h-4 rounded bg-emerald-100 text-emerald-700 inline-flex items-center justify-center shrink-0"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect width="10" height="18" x="7" y="3" rx="4"/><circle cx="12" cy="7.5" r="1.5" fill="currentColor"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/><circle cx="12" cy="16.5" r="1.5" fill="currentColor"/></svg></span>`,
            'Penyeberangan': `<span class="w-4 h-4 rounded bg-emerald-100 text-emerald-700 inline-flex items-center justify-center shrink-0"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect width="10" height="18" x="7" y="3" rx="4"/><circle cx="12" cy="7.5" r="1.5" fill="currentColor"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/><circle cx="12" cy="16.5" r="1.5" fill="currentColor"/></svg></span>`,
            'Parkir Liar': `<span class="w-4 h-4 rounded bg-rose-100 text-rose-700 inline-flex items-center justify-center shrink-0"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="m4.93 4.93 14.14 14.14"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7h4a3 3 0 0 1 0 6H9"/></svg></span>`,
        };
        const defaultCatIcon = `<span class="w-4 h-4 rounded bg-slate-100 text-slate-700 inline-flex items-center justify-center shrink-0"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13.5"/><circle cx="12" cy="17.2" r="1.1" fill="currentColor" stroke="none"/></svg></span>`;

        function getReparationBadge(status) {
            if (status === 'SUDAH_DIPERBAIKI') {
                return `<span class="px-2.5 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200/80 text-[10px] font-bold rounded-full inline-flex items-center gap-1 shadow-2xs whitespace-nowrap shrink-0"><svg class="w-3 h-3 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Selesai</span>`;
            }
            if (status === 'SEDANG_DIPERBAIKI') {
                return `<span class="px-2.5 py-0.5 bg-sky-50 text-sky-700 border border-sky-200/80 text-[10px] font-bold rounded-full inline-flex items-center gap-1 shadow-2xs whitespace-nowrap shrink-0"><svg class="w-3 h-3 text-sky-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg> Dikerjakan</span>`;
            }
            return `<span class="px-2.5 py-0.5 bg-amber-50 text-amber-700 border border-amber-200/80 text-[10px] font-bold rounded-full inline-flex items-center gap-1 shadow-2xs whitespace-nowrap shrink-0"><svg class="w-3 h-3 text-amber-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Belum Ditangani</span>`;
        }

        function formatReportDate(isoStr) {
            if (!isoStr) return '-';
            const d = new Date(isoStr);
            return d.toLocaleDateString('id-ID', {
                day: 'numeric', month: 'short', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            }) + ' WIB';
        }

        const closeBtnHtml = `
            <button type="button" onclick="closeReportBottomSheet()" title="Tutup Lembar Aduan" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-slate-800 flex items-center justify-center text-xs font-black transition ml-2 flex-shrink-0 cursor-pointer">
                ✕
            </button>
        `;

        const allRepaired = reportsAtSpot.every(r => r.status_perbaikan === 'SUDAH_DIPERBAIKI');

        if (allRepaired) {
            headerEl.innerHTML = `
                <div class="flex items-center gap-2 min-w-0">
                    <span class="w-6 h-6 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    </span>
                    <div class="truncate">
                        <span class="text-xs font-black text-slate-900 block truncate">Fasilitas Selesai Diperbaiki</span>
                        <span class="text-[9px] text-emerald-700 font-extrabold">Terverifikasi Layak Pejalan Kaki</span>
                    </div>
                </div>
                ${closeBtnHtml}
            `;
        } else if (reportsAtSpot.length > 1) {
            headerEl.innerHTML = `
                <div class="flex items-center gap-2 min-w-0">
                    <span class="w-6 h-6 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 3z"/></svg>
                    </span>
                    <div class="truncate">
                        <span class="text-xs font-black text-slate-900 block truncate">Titik Rawan Hotspot</span>
                        <span class="text-[9px] text-rose-700 font-extrabold">${reportsAtSpot.length} Laporan Aduan Warga</span>
                    </div>
                </div>
                ${closeBtnHtml}
            `;
        } else {
            const single = reportsAtSpot[0];
            headerEl.innerHTML = `
                <div class="flex items-center gap-2 min-w-0">
                    <span class="w-6 h-6 rounded-lg bg-brand-50 text-brand-700 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-brand-600" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>
                    </span>
                    <div class="min-w-0 flex items-center gap-1.5">
                        <span class="text-xs font-bold text-slate-800 tracking-tight tabular-nums">${single.kode_laporan || ('#LAP-' + String(single.laporan_id).padStart(4, '0'))}</span>
                        <span class="text-[10px] text-slate-600 font-bold truncate">${single.kategori_nama}</span>
                    </div>
                </div>
                ${closeBtnHtml}
            `;
        }

        let multiNoticeHtml = '';
        if (reportsAtSpot.length > 1) {
            multiNoticeHtml = `
                <div class="mb-3 px-3 py-1.5 bg-rose-50 border border-rose-200/80 rounded-xl flex items-center justify-between text-[10px] text-rose-900 font-semibold">
                    <span>Daftar ${reportsAtSpot.length} aduan aktif di lokasi ini</span>
                    <span class="text-rose-600 font-bold">Gulir ke bawah ↓</span>
                </div>
            `;
        }

        const itemsHtml = reportsAtSpot.map((rep, idx) => {
            const icon = catIcons[rep.kategori_nama] || defaultCatIcon;
            const isRepaired = (rep.status_perbaikan === 'SUDAH_DIPERBAIKI');
            const isRepairing = (rep.status_perbaikan === 'SEDANG_DIPERBAIKI');

            // Visual Proof Section: Before vs After Photos
            let visualProofHtml = '';
            if (isRepaired && rep.foto_perbaikan_url) {
                visualProofHtml = `
                    <div class="mb-2.5 p-2 bg-slate-50 rounded-xl border border-slate-200">
                        <span class="text-[9px] font-black text-slate-500 uppercase tracking-wider block mb-1.5 text-center">Komparasi Bukti Fisik Lapangan</span>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <span class="text-[8px] font-bold text-rose-700 bg-rose-50 px-1 py-0.5 rounded flex items-center justify-center gap-1 mb-1 truncate text-center"><span class="w-1.5 h-1.5 rounded-full bg-rose-500 inline-block"></span> SEBELUM (Rusak)</span>
                                <div class="w-full h-24 bg-slate-200 rounded-lg overflow-hidden relative shadow-inner">
                                    <img src="${rep.foto_url || 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=300'}" alt="Foto Sebelum" class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div>
                                <span class="text-[8px] font-bold text-emerald-700 bg-emerald-50 px-1 py-0.5 rounded flex items-center justify-center gap-1 mb-1 truncate text-center"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> SESUDAH (Selesai)</span>
                                <div class="w-full h-24 bg-emerald-100 rounded-lg overflow-hidden relative shadow-inner border border-emerald-300">
                                    <img src="${rep.foto_perbaikan_url}" alt="Foto Sesudah" class="w-full h-full object-cover">
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else if (isRepairing && rep.foto_perbaikan_url) {
                visualProofHtml = `
                    <div class="mb-2.5 p-2 bg-sky-50/60 rounded-xl border border-sky-200">
                        <span class="text-[9px] font-black text-sky-800 uppercase tracking-wider block mb-1.5 text-center">Dokumentasi Progres Penanganan</span>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <span class="text-[8px] font-bold text-rose-700 bg-rose-50 px-1 py-0.5 rounded flex items-center justify-center gap-1 mb-1 truncate text-center"><span class="w-1.5 h-1.5 rounded-full bg-rose-500 inline-block"></span> SEBELUM (Rusak)</span>
                                <div class="w-full h-24 bg-slate-200 rounded-lg overflow-hidden relative shadow-inner">
                                    <img src="${rep.foto_url || 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=300'}" alt="Foto Sebelum" class="w-full h-full object-cover">
                                </div>
                            </div>
                            <div>
                                <span class="text-[8px] font-bold text-sky-700 bg-sky-100 px-1 py-0.5 rounded flex items-center justify-center gap-1 mb-1 truncate text-center"><span class="w-1.5 h-1.5 rounded-full bg-sky-500 inline-block"></span> PROSES (Dikerjakan)</span>
                                <div class="w-full h-24 bg-sky-100 rounded-lg overflow-hidden relative shadow-inner border border-sky-300">
                                    <img src="${rep.foto_perbaikan_url}" alt="Foto Pengerjaan Lapangan" class="w-full h-full object-cover">
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            } else if (rep.foto_url) {
                visualProofHtml = `
                    <div class="relative w-full h-32 bg-slate-100 rounded-xl overflow-hidden mb-2.5 shadow-inner">
                        <img src="${rep.foto_url}" alt="Foto Bukti" class="w-full h-full object-cover">
                        <span class="absolute bottom-2 left-2 px-2 py-0.5 bg-black/60 backdrop-blur-md text-white text-[9px] font-bold rounded-md flex items-center gap-1.5">
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                            <span>Foto Aduan Warga</span>
                        </span>
                        ${isRepaired ? '<span class="absolute top-2 right-2 px-2 py-0.5 bg-emerald-600/90 text-white text-[9px] font-extrabold rounded-md shadow-sm flex items-center gap-1"><svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg> Telah Selesai</span>' : ''}
                    </div>
                `;
            } else {
                visualProofHtml = `
                    <div class="w-full h-16 bg-slate-50 border border-dashed border-slate-200 rounded-xl flex items-center justify-center text-slate-400 text-xs font-semibold gap-1.5 mb-2.5">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                        <span>Foto bukti belum dilampirkan</span>
                    </div>
                `;
            }

            // Official verification / audit proof card
            let auditProofHtml = '';
            if (isRepaired) {
                auditProofHtml = `
                    <div class="mb-2 p-2.5 bg-emerald-50 border border-emerald-200 rounded-xl text-left">
                        <div class="flex items-center gap-1.5 mb-1 text-emerald-900">
                            <svg class="w-3.5 h-3.5 text-emerald-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <strong class="text-[10px] font-black uppercase tracking-wider">Bukti Verifikasi Selesai:</strong>
                        </div>
                        <p class="text-[11px] text-emerald-950 font-medium leading-relaxed mb-1.5">
                            "${rep.catatan_perbaikan || 'Fasilitas pejalan kaki telah selesai diperbaiki dan diverifikasi layak oleh unit teknis di lapangan.'}"
                        </p>
                        <div class="flex items-center justify-between text-[9px] text-emerald-800 border-t border-emerald-200/60 pt-1">
                            <span>Oleh: <strong>${rep.verifikator || 'Dinas Terkait (Admin)'}</strong></span>
                            <span>Selesai: ${formatReportDate(rep.tanggal_perbaikan)}</span>
                        </div>
                        <div class="mt-1.5 pt-1 border-t border-emerald-200/60 flex items-center justify-between text-[9px]">
                            <span class="font-bold text-emerald-900">Pemulihan Skor Jalur (WSM):</span>
                            <span class="px-1.5 py-0.5 bg-emerald-600 text-white font-extrabold rounded">Pulih: ${rep.skor_segmen || '100.0'} (Layak)</span>
                        </div>
                    </div>
                `;
            } else if (isRepairing) {
                auditProofHtml = `
                    <div class="mb-2 p-2 bg-sky-50 border border-sky-200 rounded-xl text-left">
                        <div class="flex items-center gap-1.5 mb-0.5 text-sky-900">
                            <svg class="w-3.5 h-3.5 text-sky-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            <strong class="text-[10px] font-black uppercase tracking-wider">Proses Pengerjaan Lapangan:</strong>
                        </div>
                        <p class="text-[11px] text-sky-950 leading-relaxed font-medium">
                            "${rep.catatan_perbaikan || 'Petugas sedang melakukan penanganan fisik perbaikan di lokasi.'}"
                        </p>
                        <div class="flex items-center justify-between text-[9px] text-sky-700 mt-1.5 pt-1 border-t border-sky-200/60">
                            <span>Penanggung jawab: <strong>${rep.verifikator || 'Dinas Terkait'}</strong></span>
                            ${rep.foto_perbaikan_url ? '<span class="text-sky-800 font-bold flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-sky-500 inline-block"></span> Foto progres terlampir</span>' : ''}
                        </div>
                    </div>
                `;
            }

            return `
                <div class="${idx > 0 ? 'pt-3.5 border-t border-slate-200 mt-3.5' : ''}">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-bold text-slate-800 tracking-tight tabular-nums">${rep.kode_laporan || ('#LAP-' + String(rep.laporan_id).padStart(4, '0'))}</span>
                            <span class="px-2 py-0.5 bg-slate-100 text-slate-600 text-[10px] font-semibold rounded-md flex items-center gap-1">${icon} <span>${rep.kategori_nama}</span></span>
                        </div>
                        ${getReparationBadge(rep.status_perbaikan)}
                    </div>

                    ${visualProofHtml}

                    <div class="mb-2">
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Penjelasan Laporan Warga:</span>
                        <p class="text-xs text-slate-700 bg-slate-50 p-2 rounded-xl border border-slate-100 leading-relaxed font-medium">
                            "${rep.deskripsi || 'Tidak ada deskripsi rinci.'}"
                        </p>
                    </div>

                    ${auditProofHtml}

                    <div class="flex items-center justify-between text-[10px] text-slate-400 pt-1">
                        <span class="truncate max-w-[150px] flex items-center gap-1">
                            <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>
                            <span class="truncate">${rep.alamat || 'Surabaya'}</span>
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span>${formatReportDate(rep.tanggal_lapor)}</span>
                        </span>
                    </div>
                </div>
            `;
        }).join('');

        bodyEl.innerHTML = multiNoticeHtml + itemsHtml;
    }

    let lastSheetOpenTimestamp = 0;

    function openReportBottomSheet(cluster, marker) {
        if (!cluster || !cluster.reports || cluster.reports.length === 0) return;
        lastSheetOpenTimestamp = Date.now();

        // Clear previous active marker glow
        if (activeSelectedMarker) {
            const prevEl = activeSelectedMarker.getElement ? activeSelectedMarker.getElement() : activeSelectedMarker._icon;
            if (prevEl) prevEl.classList.remove('heat-marker-selected');
        }

        activeSelectedMarker = marker;
        const currEl = marker.getElement ? marker.getElement() : marker._icon;
        if (currEl) currEl.classList.add('heat-marker-selected');

        isUserViewingSheet = true;

        // Move and focus camera on marker in upper zone
        focusOnMarkerUpperZone(marker);

        // Render content into sheet
        renderReportSheetContent(cluster);

        // Animate sheet in
        const sheet = document.getElementById('reportBottomSheet');
        if (sheet) {
            sheet.classList.remove('translate-y-full', 'opacity-0', 'pointer-events-none');
            sheet.classList.add('translate-y-0', 'opacity-100', 'pointer-events-auto');
        }
    }

    // Dismiss bottom sheet when clicking outside on the map canvas
    map.on('click', (e) => {
        // Do not close if click was on a marker or happened during sheet opening
        if (Date.now() - lastSheetOpenTimestamp < 250) {
            return;
        }
        if (e.originalEvent && (
            e.originalEvent.target.closest('.custom-heat-marker') ||
            e.originalEvent.target.closest('#reportBottomSheet')
        )) {
            return;
        }
        if (isUserViewingSheet) {
            closeReportBottomSheet();
        }
    });

    // Dismiss with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isUserViewingSheet) {
            closeReportBottomSheet();
        }
    });

    // Touch swipe down on drag handle to dismiss
    const dragHandle = document.getElementById('sheetDragHandle');
    if (dragHandle) {
        let touchStartY = 0;
        dragHandle.addEventListener('touchstart', (e) => {
            touchStartY = e.touches[0].clientY;
        }, { passive: true });
        dragHandle.addEventListener('touchend', (e) => {
            const touchEndY = e.changedTouches[0].clientY;
            if (touchEndY - touchStartY > 35) {
                closeReportBottomSheet();
            }
        }, { passive: true });
    }

    // Debounce helper (FR-F6-07: max 1 request per 500ms)
    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Heatmap loader per viewport
    async function loadHeatmap() {
        if (currentMapMode !== 'heatmap') return;

        // Never wipe layers or cancel sheet while user is actively reading
        if (isUserViewingSheet) {
            return;
        }

        const loadingEl = document.getElementById('mapLoading');
        const noticeEl = document.getElementById('mapNotice');
        const emptyOverlay = document.getElementById('mapEmptyNotice');

        if (loadingEl) loadingEl.classList.remove('hidden');

        try {
            const bounds = map.getBounds();
            const params = new URLSearchParams({
                min_lon: bounds.getWest().toFixed(6),
                min_lat: bounds.getSouth().toFixed(6),
                max_lon: bounds.getEast().toFixed(6),
                max_lat: bounds.getNorth().toFixed(6),
                zoom: Math.round(map.getZoom()),
            });

            if (selectedCategoryId !== null) {
                params.set('kategori_id', selectedCategoryId);
            }
            if (currentRepairFilter) {
                params.set('filter_perbaikan', currentRepairFilter);
            }

            const res = await fetch(`/api/v1/heatmap?${params.toString()}`, {
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json'
                }
            });

            if (!res.ok) {
                if (loadingEl) loadingEl.classList.add('hidden');
                return;
            }

            const data = await res.json();
            const features = data.features || [];
            const reports = data.reports || [];
            const meta = data.meta || { counts: { SANGAT_LAYAK: 0, PERLU_PERHATIAN: 0, TIDAK_LAYAK: 0 }, truncated: false };

            // Clear previous heatmap layers
            heatmapLayerGroup.clearLayers();

            // Distance helper (Haversine formula in meters)
            function getDistanceMeters(lat1, lon1, lat2, lon2) {
                const R = 6371e3;
                const dLat = (lat2 - lat1) * Math.PI / 180;
                const dLon = (lon2 - lon1) * Math.PI / 180;
                const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                          Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                          Math.sin(dLon/2) * Math.sin(dLon/2);
                return R * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)));
            }

            // Pixel-based adaptive clustering helper
            function getPixelDistance(lat1, lon1, lat2, lon2) {
                if (map && typeof map.latLngToLayerPoint === 'function') {
                    try {
                        const p1 = map.latLngToLayerPoint([lat1, lon1]);
                        const p2 = map.latLngToLayerPoint([lat2, lon2]);
                        return Math.hypot(p1.x - p2.x, p1.y - p2.y);
                    } catch (err) {
                        // fallback below
                    }
                }
                const distMeters = getDistanceMeters(lat1, lon1, lat2, lon2);
                const currentZoom = (map && typeof map.getZoom === 'function') ? map.getZoom() : 15;
                const metersPerPixel = (40075016 * Math.cos(lat1 * Math.PI / 180)) / Math.pow(2, currentZoom + 8);
                return distMeters / (metersPerPixel || 1);
            }

            // Cluster reports dynamically by screen pixel proximity (threshold 28px)
            // so individual report locations show distinct clickable markers when zoomed in,
            // while grouping into clusters cleanly when zoomed out or touching.
            const clusters = [];
            const clusterPixelThreshold = 28;

            reports.forEach(r => {
                let added = false;
                for (let c of clusters) {
                    const pxDist = getPixelDistance(c.center.latitude, c.center.longitude, r.latitude, r.longitude);
                    if (pxDist <= clusterPixelThreshold) {
                        c.reports.push(r);
                        // Recompute geometric centroid so marker sits accurately among clustered reports
                        const sumLat = c.reports.reduce((acc, curr) => acc + curr.latitude, 0);
                        const sumLon = c.reports.reduce((acc, curr) => acc + curr.longitude, 0);
                        c.center.latitude = sumLat / c.reports.length;
                        c.center.longitude = sumLon / c.reports.length;
                        added = true;
                        break;
                    }
                }
                if (!added) {
                    clusters.push({
                        center: { latitude: r.latitude, longitude: r.longitude },
                        reports: [r],
                    });
                }
            });

            currentRawFeatures = features;
            currentRawClusters = clusters;

            // Calculate density summary for legend
            let countLow = 0;
            let countMed = 0;
            let countHigh = 0;

            clusters.forEach(c => {
                const count = c.reports.length;
                if (count >= 4) {
                    countHigh++;
                } else if (count >= 2) {
                    countMed++;
                } else {
                    countLow++;
                }
            });

            // Update Legend numbers
            document.getElementById('countSangatLayak').innerText = countLow;
            document.getElementById('countPerluPerhatian').innerText = countMed;
            document.getElementById('countTidakLayak').innerText = countHigh;
            document.getElementById('legendTotalSegments').innerText = `${reports.length} aduan (${clusters.length} titik)`;

            // Render Thermal Layer & Interactive Markers (only report points)
            renderMarkersAndHeat();

            // Handle Truncated Notice (FR-F6-08)
            if (meta.truncated) {
                noticeEl.classList.remove('hidden');
            } else {
                noticeEl.classList.add('hidden');
            }

            // Handle Empty Area / Filter (FR-F6-03, FR-F6-04)
            if (reports.length === 0 && features.length === 0) {
                if (selectedCategoryId !== null) {
                    document.getElementById('emptyNoticeTitle').innerText = 'Filter Kosong';
                    document.getElementById('emptyNoticeMsg').innerText = 'Tidak ada data laporan yang sesuai dengan filter.';
                    emptyOverlay.classList.remove('hidden');
                } else {
                    document.getElementById('emptyNoticeTitle').innerText = 'Data Jalur Kosong';
                    document.getElementById('emptyNoticeMsg').innerText = 'Data kelayakan jalur belum tersedia untuk area ini.';
                    emptyOverlay.classList.remove('hidden');
                }
            } else {
                emptyOverlay.classList.add('hidden');
            }

        } catch (e) {
            console.error('Heatmap load error:', e);
        } finally {
            if (loadingEl) loadingEl.classList.add('hidden');
        }
    }

    const debouncedLoadHeatmap = debounce(loadHeatmap, 500);
    map.on('moveend', debouncedLoadHeatmap);
    map.on('zoomend', debouncedLoadHeatmap);

    // Initial heatmap fetch
    loadHeatmap();
</script>
@endpush