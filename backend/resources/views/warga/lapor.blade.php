@extends('layouts.app')

@section('title', 'Lapor Fasilitas Pejalan Kaki - PedestriRoute')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #laporMap {
        height: 340px;
        width: 100%;
        border-radius: 0.875rem;
        z-index: 10;
    }

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
        border-radius: 1rem !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
        border: 1px solid #e2e8f0 !important;
        overflow: hidden !important;
    }
    .leaflet-popup-content {
        margin: 0 !important;
        line-height: 1.4 !important;
    }
</style>
@endpush

@section('content')
<div class="px-5 pt-5 pb-24 flex flex-col flex-1">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <a href="/beranda-warga" class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-slate-200 transition" aria-label="Kembali">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="text-center">
            <h1 class="text-sm font-extrabold text-slate-900 leading-tight">Lapor Fasilitas</h1>
        </div>
        <div class="relative">
            <button id="userMenuBtn" class="w-8 h-8 rounded-xl overflow-hidden border border-slate-200 focus:outline-none relative flex items-center justify-center bg-teal-800 text-white font-bold text-xs shadow-sm" aria-label="Menu Pengguna">
                <img id="navAvatar" src="" alt="Avatar" class="w-full h-full object-cover hidden" onerror="this.classList.add('hidden'); document.getElementById('navAvatarFallback')?.classList.remove('hidden');">
                <span id="navAvatarFallback" class="w-full h-full flex items-center justify-center bg-teal-800 text-white font-bold text-xs uppercase">W</span>
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

    <form id="reportForm" class="space-y-3">
        <!-- 1. Lokasi Kejadian Card -->
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm">
            <div class="flex items-center justify-end mb-2">
                <!-- Toggle Heatmap Button -->
                <button type="button" id="btnToggleHeatmap" onclick="toggleLaporHeatmap()"
                    class="px-2.5 py-1 rounded-full text-xs font-bold border transition flex items-center gap-1 bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200"
                    title="Tampilkan titik aduan dan kelayakan jalur di peta">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <span id="heatmapToggleText">Heatmap: Off</span>
                </button>
                <span id="gpsBadge" class="hidden"></span>
            </div>

            <!-- Unified Single Container: Cari Alamat -->
            <div class="relative mb-2.5">
                <div class="relative flex items-center">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-brand-600">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                    </div>
                    <input type="text" id="manualAddressInput" autocomplete="off"
                        placeholder="Ketik alamat atau cari jalan..."
                        class="w-full pl-9 pr-20 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition font-medium" />
                    <div class="absolute inset-y-0 right-0 pr-1.5 flex items-center gap-1">
                        <button type="button" id="btnClearAddress" class="hidden p-1 text-slate-400 hover:text-slate-600 rounded-md" title="Hapus teks pencarian">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        <span id="accuracyBadge" class="hidden"></span>
                        <button type="button" id="btnSearchAddress" class="px-2.5 py-1 bg-brand-600 hover:bg-brand-700 text-white text-[10px] font-bold rounded-lg transition shadow-sm">
                            Cari
                        </button>
                    </div>
                </div>

                <!-- Hidden element to keep alamatText reference safe for any code -->
                <span id="alamatText" class="hidden"></span>

                <!-- Suggestions Dropdown -->
                <div id="addressSuggestions" class="hidden absolute left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 max-h-56 overflow-y-auto divide-y divide-slate-100 text-xs">
                </div>
            </div>

            <!-- Map container with relative overlay -->
            <div class="relative mb-2.5">
                <div id="laporMap" class="border border-slate-100"></div>

                <!-- Heatmap loading spinner indicator -->
                <div id="heatmapLoading" class="hidden absolute top-2.5 right-2.5 z-20 bg-white/90 backdrop-blur-md px-2 py-1 rounded-lg shadow-sm border border-slate-100 flex items-center gap-1.5">
                    <svg class="w-3 h-3 animate-spin text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span class="text-[9px] font-bold text-slate-700">Memuat titik aduan...</span>
                </div>
            </div>

            <!-- Heatmap Mini Legend (Visible when Heatmap is ON) -->
            <div id="laporHeatmapLegend" class="hidden mb-2.5 p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-[10px]">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="font-bold text-slate-600 uppercase tracking-wider text-[9px]">Aduan Aktif (Belum Selesai)</span>
                    <span id="laporHeatmapCount" class="font-extrabold text-brand-700 text-[10px]">0 aduan aktif</span>
                </div>
                <div class="grid grid-cols-3 gap-1.5 text-center font-bold">
                    <div class="bg-emerald-50 text-emerald-800 border border-emerald-100 rounded-lg py-1 px-1 flex items-center justify-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                        <span class="truncate">1 Aduan</span>
                    </div>
                    <div class="bg-amber-50 text-amber-800 border border-amber-100 rounded-lg py-1 px-1 flex items-center justify-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-amber-500 shrink-0"></span>
                        <span class="truncate">2–3 Aduan</span>
                    </div>
                    <div class="bg-rose-50 text-rose-800 border border-rose-100 rounded-lg py-1 px-1 flex items-center justify-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-rose-600 shrink-0"></span>
                        <span class="truncate">≥4 Hotspot</span>
                    </div>
                </div>
            </div>

            <!-- Coordinates & GPS button -->
            <div class="flex items-center gap-2">
                <input type="text" id="coordDisplay" readonly
                    value="-7.2663, 112.7965"
                    class="flex-1 bg-slate-100 border border-slate-200 rounded-xl px-3 py-2 text-xs font-mono text-slate-600 focus:outline-none" />
                <button type="button" id="btnGps"
                    class="px-3.5 py-2 bg-teal-50 hover:bg-teal-100 text-teal-800 border border-teal-200 text-xs font-bold rounded-xl flex items-center gap-1.5 transition shrink-0">
                    <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Gunakan GPS</span>
                </button>
            </div>
        </div>

        <!-- 2. Foto Bukti Card -->
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-slate-800">Foto Bukti</span>
                <span id="photoBadge" class="text-xs font-medium text-slate-400">Maks. 15MB</span>
            </div>

            <input type="file" id="fotoInput" accept="image/jpeg,image/png" class="hidden">
            <input type="file" id="fotoCameraInput" accept="image/*" capture="environment" class="hidden">

            <div class="grid grid-cols-2 gap-2 mb-3">
                <button type="button" id="btnOpenCamera"
                    class="py-2.5 px-3 border border-slate-200 hover:border-brand-500 bg-slate-50 hover:bg-teal-50 rounded-xl flex items-center justify-center gap-2 text-xs font-bold text-slate-700 hover:text-brand-700 transition">
                    <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Ambil Foto</span>
                </button>

                <button type="button" id="btnPickPhoto"
                    class="py-2.5 px-3 border border-slate-200 hover:border-brand-500 bg-slate-50 hover:bg-slate-100 rounded-xl flex items-center justify-center gap-2 text-xs font-bold text-slate-700 transition">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>Pilih Berkas</span>
                </button>
            </div>

            <!-- Preview Box -->
            <div id="previewContainer" class="h-32 bg-slate-50 rounded-xl border border-slate-200 relative overflow-hidden flex items-center justify-center">
                <span id="previewPlaceholder" class="text-xs text-slate-400">Belum ada foto yang dipilih</span>
                <img id="previewImg" class="w-full h-full object-cover hidden" alt="Pratinjau foto bukti" />
                <button type="button" id="btnRemovePhoto"
                    class="hidden absolute top-2 right-2 w-7 h-7 rounded-full bg-slate-900/70 text-white flex items-center justify-center hover:bg-slate-900 transition text-xs font-bold">
                    ✕
                </button>
                <span id="previewFilename" class="hidden absolute bottom-1 left-2 right-2 text-[11px] text-white bg-black/60 px-2 py-0.5 rounded truncate"></span>
            </div>

            <!-- AI Verification Guidance Pill -->
            <div class="mt-2.5 p-2.5 bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-100 rounded-xl flex items-start gap-2 text-xs">
                <span class="w-5 h-5 rounded-md bg-emerald-500/20 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5 font-bold">
                    <i class="fa-solid fa-wand-magic-sparkles text-[10px]"></i>
                </span>
                <div class="text-[11px] leading-snug text-slate-600">
                    <strong class="text-emerald-900 font-bold">Analisis Otomatis AI:</strong>
                    Arahkan foto tegak lurus ke kerusakan trotoar/fasilitas dengan pencahayaan jelas untuk auto-validasi instan (&le; 10 detik).
                </div>
            </div>

            <p id="compressInfo" class="hidden mt-2 text-xs text-teal-700 font-medium"></p>
        </div>

        <!-- 3. Kategori Masalah Card -->
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-slate-800">Kategori Masalah</span>
                <span class="text-[10px] font-bold text-rose-500">*Wajib</span>
            </div>

            <!-- Categories Grid (5 Kategori) -->
            <div id="categoriesGrid" class="grid grid-cols-2 gap-2">
                <!-- 1: Trotoar Rusak -->
                <button type="button" data-id="1" data-slug="trotoar_rusak"
                    class="cat-chip p-3 rounded-xl border-2 border-brand-500 bg-teal-50/70 text-brand-900 text-left transition flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-teal-100 text-brand-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold text-xs text-slate-800 leading-tight">Trotoar Rusak</p>
                        <p class="text-[11px] text-slate-400 truncate mt-0.5">Lubang / amblas</p>
                    </div>
                </button>

                <!-- 2: Masalah Drainase -->
                <button type="button" data-id="2" data-slug="masalah_drainase"
                    class="cat-chip p-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 text-left transition flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-sky-100 text-sky-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold text-xs text-slate-800 leading-tight">Drainase</p>
                        <p class="text-[11px] text-slate-400 truncate mt-0.5">Manhole / saluran</p>
                    </div>
                </button>

                <!-- 3: Guiding Block Rusak -->
                <button type="button" data-id="3" data-slug="guiding_block_rusak"
                    class="cat-chip p-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 text-left transition flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="16" cy="4" r="1"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="m18 19 1-7-6 1M5 8l3-3 5.5 3-2.36 3.5M4.24 14.5a5 5 0 0 0 6.88 6M13.76 17.5a5 5 0 0 0-6.88-6"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold text-xs text-slate-800 leading-tight">Guiding Block</p>
                        <p class="text-[11px] text-slate-400 truncate mt-0.5">Ubin tunanetra</p>
                    </div>
                </button>

                <!-- 4: Penyebrangan Rusak -->
                <button type="button" data-id="4" data-slug="penyebrangan_rusak"
                    class="cat-chip p-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 text-left transition flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect width="10" height="18" x="7" y="3" rx="4"/>
                            <circle cx="12" cy="7.5" r="1.5" fill="currentColor"/>
                            <circle cx="12" cy="12" r="1.5" fill="currentColor"/>
                            <circle cx="12" cy="16.5" r="1.5" fill="currentColor"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold text-xs text-slate-800 leading-tight">Penyeberangan</p>
                        <p class="text-[11px] text-slate-400 truncate mt-0.5">Zebra cross / rambu</p>
                    </div>
                </button>

                <!-- 5: Parkir Liar -->
                <button type="button" data-id="5" data-slug="parkir_liar"
                    class="cat-chip col-span-2 p-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 text-left transition flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.93 4.93 14.14 14.14"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7h4a3 3 0 0 1 0 6H9"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold text-xs text-slate-800 leading-tight">Parkir Liar</p>
                        <p class="text-[11px] text-slate-400 truncate mt-0.5">Kendaraan di trotoar</p>
                    </div>
                </button>
            </div>
        </div>

        <!-- 4. Deskripsi Card -->
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-slate-800">Deskripsi</span>
                <span class="text-[10px] font-mono text-slate-400"><span id="charCount">0</span>/200</span>
            </div>
            <textarea id="deskripsi" rows="3" maxlength="200"
                placeholder="Tuliskan kendala yang Anda temui..."
                class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition leading-relaxed"></textarea>
        </div>

        <!-- Submit Button -->
        <button type="submit" id="btnSubmitReport"
            class="w-full py-3 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-2 shadow-sm transition mt-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
            </svg>
            <span>Kirim Laporan</span>
        </button>

        <p class="text-[11px] text-slate-400 text-center pt-1">
            Laporan diverifikasi dalam 1x24 jam kerja
        </p>
    </form>
</div>

<!-- Modal Pratinjau Foto -->
<div id="photoModal" class="hidden fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4">
    <button type="button" id="btnClosePhotoModal"
        class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/20 text-white text-lg flex items-center justify-center hover:bg-white/30">✕</button>
    <img id="photoModalImg" class="max-w-full max-h-full object-contain rounded-lg" alt="Pratinjau foto bukti">
</div>

<!-- Bottom Navigation Bar -->
<div class="fixed bottom-0 max-w-md w-full bg-white border-t border-slate-200 py-2 px-6 flex items-center justify-between z-40">
    <a href="/beranda-warga" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600 transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2L2 12h3v8h6v-6h2v6h6v-8h3L12 2z"/>
        </svg>
        <span class="text-[10px] tracking-tight">Peta</span>
    </a>
    <a href="javascript:void(0)" onclick="showToast('Rute Pejalan Kaki aktif pada Sprint 3', 'info')" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600 transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
        <span class="text-[10px] tracking-tight">Rute</span>
    </a>
    <a href="/lapor-kondisi" class="flex flex-col items-center gap-1 text-brand-600 font-bold">
        <div class="w-10 h-10 -mt-5 bg-brand-600 hover:bg-brand-700 rounded-full flex items-center justify-center text-white shadow-md shadow-teal-900/20 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
        </div>
        <span class="text-[10px] tracking-tight mt-0.5">Lapor</span>
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
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
@include('partials.mulyorejo-service-area')
<script>
    const token = localStorage.getItem('token');
    if (!token) {
        window.location.href = '/login';
    }

    // User Profile & Dropdown
    function applyUserProfile(user) {
        if (!user) return;
        const displayName = user.nama || 'Warga';
        const initial = displayName.trim().charAt(0).toUpperCase() || 'W';

        document.getElementById('menuUserName').innerText = displayName;
        document.getElementById('menuUserEmail').innerText = user.email || '';

        const avatarEl = document.getElementById('navAvatar');
        const fallbackEl = document.getElementById('navAvatarFallback');
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

    async function syncUserProfile() {
        try {
            const res = await fetch('/api/v1/auth/me', {
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json'
                }
            });
            if (res.ok) {
                const data = await res.json();
                if (data?.data) {
                    localStorage.setItem('user', JSON.stringify(data.data));
                    applyUserProfile(data.data);
                }
            }
        } catch (e) {}
    }
    syncUserProfile();

    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');

    function toggleUserDropdown() {
        if (userDropdown) {
            userDropdown.classList.toggle('hidden');
        }
    }

    if (userMenuBtn) {
        userMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleUserDropdown();
        });
    }

    document.addEventListener('click', () => {
        if (userDropdown) userDropdown.classList.add('hidden');
    });

    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async () => {
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
    }

    // Selected state
    let selectedLat = -7.2663;
    let selectedLon = 112.7965;
    let selectedCategoryId = 1;
    let preparedPhotoBlob = null;
    let preparedPhotoName = 'foto_laporan.jpg';

    // Initialize Map
    const map = L.map('laporMap', {
        preferCanvas: true,
        attributionControl: false
    }).setView([selectedLat, selectedLon], 17);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);

    const serviceArea = PedestriRouteServiceArea.attach(map);

    // Marker Pinpoint - Bebas ditaruh di mana saja oleh pengguna
    let marker = L.marker([selectedLat, selectedLon], { draggable: true, zIndexOffset: 1000 }).addTo(map);

    // ==========================================
    // 1. Toggleable Heatmap Feature
    // ==========================================
    let isHeatmapVisible = false;
    const heatmapLayerGroup = L.layerGroup();

    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    function getDistanceMeters(lat1, lon1, lat2, lon2) {
        const R = 6371e3;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        return R * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)));
    }

    function getPixelDistance(lat1, lon1, lat2, lon2) {
        if (map && typeof map.latLngToLayerPoint === 'function') {
            try {
                const p1 = map.latLngToLayerPoint([lat1, lon1]);
                const p2 = map.latLngToLayerPoint([lat2, lon2]);
                return Math.hypot(p1.x - p2.x, p1.y - p2.y);
            } catch (err) {}
        }
        const distMeters = getDistanceMeters(lat1, lon1, lat2, lon2);
        const currentZoom = (map && typeof map.getZoom === 'function') ? map.getZoom() : 17;
        const metersPerPixel = (40075016 * Math.cos(lat1 * Math.PI / 180)) / Math.pow(2, currentZoom + 8);
        return distMeters / (metersPerPixel || 1);
    }

    async function fetchHeatmapData() {
        if (!isHeatmapVisible) return;

        const loadingEl = document.getElementById('heatmapLoading');
        if (loadingEl) loadingEl.classList.remove('hidden');

        try {
            const bounds = map.getBounds();
            const params = new URLSearchParams({
                min_lon: bounds.getWest().toFixed(6),
                min_lat: bounds.getSouth().toFixed(6),
                max_lon: bounds.getEast().toFixed(6),
                max_lat: bounds.getNorth().toFixed(6),
                zoom: Math.round(map.getZoom()),
                filter_perbaikan: 'aktif'
            });

            const res = await fetch(`/api/v1/heatmap?${params.toString()}`, {
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json'
                }
            });

            if (!res.ok) return;

            const data = await res.json();
            // Hanya tampilkan aduan aktif yang belum selesai diperbaiki
            const reports = (data.reports || []).filter(r => r.status_perbaikan !== 'SUDAH_DIPERBAIKI');

            heatmapLayerGroup.clearLayers();

            // Cluster reports dynamically by screen pixel proximity (threshold 28px)
            const clusters = [];
            const clusterPixelThreshold = 28;

            reports.forEach(r => {
                let added = false;
                for (let c of clusters) {
                    const pxDist = getPixelDistance(c.center.latitude, c.center.longitude, r.latitude, r.longitude);
                    if (pxDist <= clusterPixelThreshold) {
                        c.reports.push(r);
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

            const countEl = document.getElementById('laporHeatmapCount');
            if (countEl) {
                countEl.innerText = `${reports.length} aduan aktif (${clusters.length} titik)`;
            }

            // 1. Render continuous thermal field via Leaflet heatLayer (if loaded)
            if (typeof L.heatLayer === 'function' && clusters.length > 0) {
                try {
                    const heatPoints = clusters.map(c => {
                        const count = c.reports.length;
                        let weight = 0.35;
                        if (count >= 4) {
                            weight = 0.95;
                        } else if (count >= 2) {
                            weight = 0.60;
                        }
                        return [c.center.latitude, c.center.longitude, weight];
                    });

                    const heatCanvas = L.heatLayer(heatPoints, {
                        radius: 24,
                        blur: 16,
                        maxZoom: 17,
                        gradient: {
                            0.2: '#059669',
                            0.45: '#D97706',
                            0.70: '#EA580C',
                            1.0: '#DC2626'
                        }
                    });
                    heatmapLayerGroup.addLayer(heatCanvas);
                } catch (e) {}
            }

            // 2. Render each cluster as an interactive dot
            clusters.forEach(cluster => {
                const count = cluster.reports.length;
                const lat = cluster.center.latitude;
                const lon = cluster.center.longitude;

                let iconClass = 'heat-dot-low';
                let iconHtml = '<div class="heat-dot-low-core"></div>';
                let iconSize = [28, 28];
                let iconAnchor = [14, 14];

                if (count >= 4) {
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

                const heatMarker = L.marker([lat, lon], { icon: markerIcon });

                const firstRep = cluster.reports[0];
                const headerTitle = count > 1 
                    ? `<span class="text-rose-700 font-extrabold">Titik Rawan (${count} Aduan)</span>` 
                    : `<span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-brand-700 bg-brand-50 border border-brand-200/60 tracking-tight tabular-nums">${firstRep.kode_laporan || '#Aduan'}</span>`;
                const safeAddr = (firstRep.alamat || '').replace(/'/g, "\\'");
                const statusBadge = firstRep.status_perbaikan === 'SEDANG_DIPERBAIKI'
                    ? `<span class="px-1.5 py-0.5 bg-sky-100 text-sky-800 text-[9px] font-bold rounded">Sedang Dikerjakan</span>`
                    : `<span class="px-1.5 py-0.5 bg-amber-100 text-amber-800 text-[9px] font-bold rounded">Belum Ditangani</span>`;

                const popupHtml = `
                    <div class="p-3 max-w-[240px]">
                        <div class="flex items-center justify-between text-xs pb-1.5 border-b border-slate-100 mb-1.5">
                            ${headerTitle}
                            ${statusBadge}
                        </div>
                        <p class="text-[11px] font-bold text-slate-800 mb-0.5">${firstRep.kategori_nama || 'Fasilitas Pejalan Kaki'}</p>
                        <p class="text-[10px] text-slate-500 line-clamp-2 leading-relaxed mb-2 font-medium">"${firstRep.deskripsi || 'Tidak ada catatan tambahan.'}"</p>
                        <button type="button" onclick="syncLocationFromHeatmap(${lat}, ${lon}, '${safeAddr}')"
                            class="w-full py-1.5 px-2 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-lg text-[10px] flex items-center justify-center gap-1 shadow-sm transition">
                            <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>
                            <span>Pilih Titik Ini Untuk Laporan</span>
                        </button>
                    </div>
                `;

                heatMarker.bindPopup(popupHtml, { maxWidth: 260 });
                heatmapLayerGroup.addLayer(heatMarker);
            });

        } catch (e) {
            console.error('Heatmap load error on lapor page:', e);
        } finally {
            if (loadingEl) loadingEl.classList.add('hidden');
        }
    }

    const debouncedHeatmap = debounce(() => {
        if (isHeatmapVisible) fetchHeatmapData();
    }, 500);

    map.on('moveend', debouncedHeatmap);
    map.on('zoomend', debouncedHeatmap);

    function toggleLaporHeatmap() {
        isHeatmapVisible = !isHeatmapVisible;
        const btn = document.getElementById('btnToggleHeatmap');
        const text = document.getElementById('heatmapToggleText');
        const legend = document.getElementById('laporHeatmapLegend');

        if (isHeatmapVisible) {
            btn.className = 'px-2.5 py-1 rounded-full text-[10px] font-bold border transition flex items-center gap-1 bg-brand-600 text-white border-brand-600 shadow-sm';
            text.innerText = 'Heatmap: On';
            if (legend) legend.classList.remove('hidden');
            if (!map.hasLayer(heatmapLayerGroup)) {
                map.addLayer(heatmapLayerGroup);
            }
            fetchHeatmapData();
            showToast('Heatmap sebaran aduan aktif', 'info');
        } else {
            btn.className = 'px-2.5 py-1 rounded-full text-[10px] font-bold border transition flex items-center gap-1 bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200';
            text.innerText = 'Heatmap: Off';
            if (legend) legend.classList.add('hidden');
            heatmapLayerGroup.clearLayers();
            if (map.hasLayer(heatmapLayerGroup)) {
                map.removeLayer(heatmapLayerGroup);
            }
            showToast('Heatmap sebaran aduan dimatikan', 'info');
        }
    }

    function syncLocationFromHeatmap(lat, lon, address = '') {
        map.closePopup();
        updateCoordinates(lat, lon);
        map.setView([lat, lon], 17);
        if (address) {
            const manualInput = document.getElementById('manualAddressInput');
            if (manualInput) {
                manualInput.value = address;
                document.getElementById('btnClearAddress')?.classList.remove('hidden');
            }
            const alamatEl = document.getElementById('alamatText');
            if (alamatEl) alamatEl.innerText = address;
        }
        showToast('Titik laporan disinkronkan ke lokasi aduan', 'success');
    }

    // ==========================================
    // 2. Manual Address Search & Autocomplete
    // ==========================================
    let cachedLocalStreets = [];
    async function loadLocalStreets() {
        try {
            const res = await fetch('/api/v1/segments', {
                headers: { 'Accept': 'application/json' }
            });
            if (res.ok) {
                const data = await res.json();
                const features = data?.features || [];
                const seen = new Set();
                features.forEach(f => {
                    const name = f.properties?.nama_jalan;
                    if (name && !seen.has(name)) {
                        seen.add(name);
                        let lat = selectedLat;
                        let lon = selectedLon;
                        if (f.geometry?.coordinates?.length > 0) {
                            const first = f.geometry.coordinates[0];
                            lon = first[0];
                            lat = first[1];
                        }
                        cachedLocalStreets.push({
                            name: name,
                            lat: lat,
                            lon: lon,
                            type: 'Jalan Lokal (Mulyorejo)'
                        });
                    }
                });
            }
        } catch (e) {
            console.warn('Failed to load local segments:', e);
        }

        // Add prominent public landmarks in Mulyorejo area
        const landmarks = [
            { name: 'Universitas Airlangga Kampus C (UNAIR)', lat: -7.2713, lon: 112.7990, type: 'Kampus / Fasilitas Publik' },
            { name: 'Institut Teknologi Sepuluh Nopember (ITS)', lat: -7.2824, lon: 112.7949, type: 'Kampus / Fasilitas Publik' },
            { name: 'Galaxy Mall Surabaya', lat: -7.2755, lon: 112.7806, type: 'Pusat Perbelanjaan' },
            { name: 'Pakuwon City San Antonio', lat: -7.2662, lon: 112.8095, type: 'Kawasan Perumahan' },
            { name: 'Pasar Modern Mulyosari', lat: -7.2618, lon: 112.7954, type: 'Pasar & Kuliner' },
            { name: 'Kantor Kecamatan Mulyorejo', lat: -7.2680, lon: 112.7915, type: 'Kantor Pemerintahan' },
            { name: 'RS Unair (Rumah Sakit Airlangga)', lat: -7.2685, lon: 112.7876, type: 'Rumah Sakit' },
        ];
        landmarks.forEach(l => cachedLocalStreets.push(l));
    }
    loadLocalStreets();

    const manualAddressInput = document.getElementById('manualAddressInput');
    const addressSuggestions = document.getElementById('addressSuggestions');
    const btnClearAddress = document.getElementById('btnClearAddress');
    const btnSearchAddress = document.getElementById('btnSearchAddress');
    let addressSearchTimer = null;
    let addressSearchController = null;

    function renderAddressSuggestions(items) {
        if (!items || items.length === 0) {
            addressSuggestions.innerHTML = `
                <div class="px-3.5 py-3 text-center text-slate-400 text-xs">
                    <p class="font-medium">Alamat tidak ditemukan</p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Coba gunakan nama jalan utama atau geser pin di peta</p>
                </div>
            `;
            addressSuggestions.classList.remove('hidden');
            return;
        }

        addressSuggestions.innerHTML = items.map((item, idx) => `
            <div class="px-3.5 py-2.5 hover:bg-teal-50 cursor-pointer flex items-center justify-between transition group suggestion-item" data-idx="${idx}">
                <div class="flex items-center gap-2.5 min-w-0 pr-2">
                    <span class="w-6 h-6 rounded-lg bg-teal-100 text-brand-700 flex items-center justify-center shrink-0 group-hover:bg-brand-600 group-hover:text-white transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </span>
                    <div class="truncate">
                        <p class="font-bold text-xs text-slate-800 group-hover:text-brand-900 truncate leading-tight">${item.name}</p>
                        <p class="text-[10px] text-slate-400 truncate mt-0.5 leading-tight">${item.detail || item.type || 'Surabaya'}</p>
                    </div>
                </div>
                <span class="px-1.5 py-0.5 text-[9px] font-bold rounded shrink-0 ${item.isMulyorejo ? 'bg-teal-50 text-brand-700 border border-teal-200' : 'bg-slate-100 text-slate-600'}">
                    ${item.isMulyorejo ? 'Mulyorejo' : 'Surabaya'}
                </span>
            </div>
        `).join('');

        addressSuggestions.querySelectorAll('.suggestion-item').forEach(el => {
            el.addEventListener('click', () => {
                const idx = parseInt(el.getAttribute('data-idx'), 10);
                const selected = items[idx];
                if (selected) {
                    applySelectedAddress(selected);
                }
            });
        });

        addressSuggestions.classList.remove('hidden');
    }

    function applySelectedAddress(item) {
        addressSuggestions.classList.add('hidden');
        if (manualAddressInput) manualAddressInput.value = item.name;
        if (btnClearAddress) btnClearAddress.classList.remove('hidden');

        updateCoordinates(item.lat, item.lon, null);
        map.setView([item.lat, item.lon], 17);

        const accuracyBadge = document.getElementById('accuracyBadge');
        if (accuracyBadge) accuracyBadge.innerText = 'Pencarian Alamat';

        const alamatEl = document.getElementById('alamatText');
        if (alamatEl) alamatEl.innerText = item.name;

        // Check against Mulyorejo service area boundary
        if (typeof PedestriRouteServiceArea !== 'undefined' && PedestriRouteServiceArea.contains) {
            const isInside = PedestriRouteServiceArea.contains(item.lat, item.lon);
            if (!isInside) {
                showToast('Catatan: Titik berada di luar batas administratif Kecamatan Mulyorejo', 'info');
            } else {
                showToast('Lokasi peta diperbarui ke ' + item.name, 'success');
            }
        }
    }

    async function executeAddressSearch(query) {
        const q = query.trim().toLowerCase();
        if (q.length < 2) {
            addressSuggestions.classList.add('hidden');
            return;
        }

        const results = [];

        // 1. Check local cache (Mulyorejo segments & landmarks)
        cachedLocalStreets.forEach(s => {
            if (s.name.toLowerCase().includes(q)) {
                results.push({
                    name: s.name,
                    detail: s.type,
                    lat: s.lat,
                    lon: s.lon,
                    isMulyorejo: true
                });
            }
        });

        // 2. Fetch from OpenStreetMap Nominatim for live Surabaya search
        if (addressSearchController) addressSearchController.abort();
        addressSearchController = new AbortController();

        try {
            const nominatimUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query + ' Surabaya')}&viewbox=112.75,-7.30,112.84,-7.24&bounded=0&countrycodes=id&limit=4&addressdetails=1`;
            const res = await fetch(nominatimUrl, {
                headers: { 'Accept': 'application/json' },
                signal: addressSearchController.signal
            });
            if (res.ok) {
                const osmData = await res.json();
                osmData.forEach(item => {
                    const lat = parseFloat(item.lat);
                    const lon = parseFloat(item.lon);
                    const duplicate = results.some(r => Math.abs(r.lat - lat) < 0.0001 && Math.abs(r.lon - lon) < 0.0001);
                    if (!duplicate) {
                        const displayName = item.display_name.split(',')[0];
                        const suburb = item.address?.suburb || item.address?.city_district || 'Surabaya';
                        const isMulyorejo = suburb.toLowerCase().includes('mulyorejo') || 
                                           (typeof PedestriRouteServiceArea !== 'undefined' && PedestriRouteServiceArea.contains && PedestriRouteServiceArea.contains(lat, lon));
                        results.push({
                            name: displayName,
                            detail: item.display_name.split(',').slice(1, 3).join(',').trim(),
                            lat: lat,
                            lon: lon,
                            isMulyorejo: isMulyorejo
                        });
                    }
                });
            }
        } catch (e) {
            if (e.name !== 'AbortError') console.warn('Nominatim search error:', e);
        }

        renderAddressSuggestions(results);
    }

    if (manualAddressInput) {
        manualAddressInput.addEventListener('input', (e) => {
            const val = e.target.value;
            if (val.trim()) {
                btnClearAddress.classList.remove('hidden');
            } else {
                btnClearAddress.classList.add('hidden');
                addressSuggestions.classList.add('hidden');
                return;
            }

            clearTimeout(addressSearchTimer);
            addressSearchTimer = setTimeout(() => {
                executeAddressSearch(val);
            }, 350);
        });

        manualAddressInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(addressSearchTimer);
                executeAddressSearch(manualAddressInput.value);
            }
        });
    }

    if (btnSearchAddress) {
        btnSearchAddress.addEventListener('click', () => {
            if (manualAddressInput && manualAddressInput.value.trim()) {
                executeAddressSearch(manualAddressInput.value);
            }
        });
    }

    if (btnClearAddress) {
        btnClearAddress.addEventListener('click', () => {
            manualAddressInput.value = '';
            btnClearAddress.classList.add('hidden');
            addressSuggestions.classList.add('hidden');
            manualAddressInput.focus();
        });
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#manualAddressInput') && !e.target.closest('#addressSuggestions')) {
            if (addressSuggestions) addressSuggestions.classList.add('hidden');
        }
    });

    // Reverse geocoding Nominatim (FR-F7-11)
    const GEOCODE_DEBOUNCE_MS = 500;      // debounce minimal 500 ms
    const GEOCODE_MIN_INTERVAL_MS = 1000; // maksimal 1 request per detik
    let reverseGeocodeTimer = null;
    let reverseGeocodeController = null;
    let lastGeocodeAt = 0;

    function fetchStreetName(lat, lon) {
        const alamatEl = document.getElementById('alamatText');
        if (!alamatEl) return;

        clearTimeout(reverseGeocodeTimer);
        if (reverseGeocodeController) reverseGeocodeController.abort();

        alamatEl.innerText = 'Mendeteksi alamat...';
        if (manualAddressInput && document.activeElement !== manualAddressInput && !manualAddressInput.value) {
            manualAddressInput.placeholder = 'Mendeteksi alamat...';
        }

        const sinceLast = Date.now() - lastGeocodeAt;
        const wait = Math.max(GEOCODE_DEBOUNCE_MS, GEOCODE_MIN_INTERVAL_MS - sinceLast);

        reverseGeocodeTimer = setTimeout(async () => {
            reverseGeocodeController = new AbortController();
            lastGeocodeAt = Date.now();
            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&zoom=18&addressdetails=1`, {
                    headers: { 'Accept': 'application/json' },
                    signal: reverseGeocodeController.signal
                });
                if (res.ok) {
                    const data = await res.json();
                    const road = data.address?.road || data.address?.pedestrian || data.address?.footway || data.address?.suburb || data.display_name?.split(',')[0];
                    if (road) {
                        const fullAddr = `${road}, Surabaya`;
                        alamatEl.innerText = fullAddr;
                        if (manualAddressInput && document.activeElement !== manualAddressInput) {
                            manualAddressInput.value = fullAddr;
                            btnClearAddress?.classList.remove('hidden');
                        }
                        return;
                    }
                }
            } catch (e) {
                if (e.name === 'AbortError') return; // digantikan request yang lebih baru
            }
            const coordFallback = `Titik Koordinat (${lat.toFixed(4)}, ${lon.toFixed(4)})`;
            alamatEl.innerText = coordFallback;
            if (manualAddressInput && document.activeElement !== manualAddressInput && !manualAddressInput.value) {
                manualAddressInput.value = coordFallback;
                btnClearAddress?.classList.remove('hidden');
            }
        }, wait);
    }

    function updateCoordinates(lat, lon, accuracy = null) {
        selectedLat = parseFloat(lat.toFixed(6));
        selectedLon = parseFloat(lon.toFixed(6));
        const newLatLng = [selectedLat, selectedLon];

        marker.setLatLng(newLatLng);
        marker.setZIndexOffset(1000);

        document.getElementById('coordDisplay').value = `${selectedLat}, ${selectedLon}`;
        
        const accuracyBadge = document.getElementById('accuracyBadge');
        if (accuracy) {
            accuracyBadge.innerText = `GPS (±${Math.round(accuracy)}m)`;
        } else {
            accuracyBadge.innerText = `Titik Terpilih`;
        }

        fetchStreetName(selectedLat, selectedLon);
    }

    // Initial address fetch
    fetchStreetName(selectedLat, selectedLon);

    marker.on('dragend', function (e) {
        const pos = e.target.getLatLng();
        updateCoordinates(pos.lat, pos.lng);
    });

    map.on('click', function (e) {
        updateCoordinates(e.latlng.lat, e.latlng.lng);
    });

    // Gunakan GPS
    document.getElementById('btnGps').addEventListener('click', () => {
        if (!navigator.geolocation) {
            showToast('Browser tidak mendukung geolokasi.', 'error');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const lat = pos.coords.latitude;
                const lon = pos.coords.longitude;
                updateCoordinates(lat, lon, pos.coords.accuracy);
                map.setView([lat, lon], 17);
                document.getElementById('gpsBadge').innerText = 'GPS Terkunci';
                showToast('Lokasi GPS berhasil dikunci', 'success');
            },
            (err) => {
                showToast('Izin lokasi ditolak. Aktifkan izin lokasi di pengaturan browser.', 'error');
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    });

    // Category Chips Selection
    const catChips = document.querySelectorAll('.cat-chip');
    catChips.forEach(chip => {
        chip.addEventListener('click', () => {
            catChips.forEach(c => {
                c.classList.remove('border-brand-500', 'border-2', 'bg-teal-50/70', 'text-brand-900', 'shadow-sm', 'ring-1', 'ring-brand-500/30');
                c.classList.add('border-slate-200', 'bg-slate-50', 'text-slate-700');
            });
            chip.classList.remove('border-slate-200', 'bg-slate-50', 'text-slate-700');
            chip.classList.add('border-brand-500', 'border-2', 'bg-teal-50/70', 'text-brand-900', 'shadow-sm', 'ring-1', 'ring-brand-500/30');
            selectedCategoryId = parseInt(chip.getAttribute('data-id'), 10);
        });
    });

    // Description char counter
    const deskripsiInput = document.getElementById('deskripsi');
    const charCount = document.getElementById('charCount');
    deskripsiInput.addEventListener('input', () => {
        charCount.innerText = deskripsiInput.value.length;
    });

    // Photo file picker and client-side canvas resize
    const btnPickPhoto = document.getElementById('btnPickPhoto');
    const fotoInput = document.getElementById('fotoInput');
    const fotoCameraInput = document.getElementById('fotoCameraInput');
    const btnOpenCamera = document.getElementById('btnOpenCamera');
    const compressInfo = document.getElementById('compressInfo');
    const previewImg = document.getElementById('previewImg');
    const previewPlaceholder = document.getElementById('previewPlaceholder');
    const btnRemovePhoto = document.getElementById('btnRemovePhoto');
    const previewFilename = document.getElementById('previewFilename');
    const photoModal = document.getElementById('photoModal');
    const photoModalImg = document.getElementById('photoModalImg');
    const btnClosePhotoModal = document.getElementById('btnClosePhotoModal');

    const MAX_PHOTO_BYTES = 15 * 1024 * 1024; // 15MB

    function formatSize(bytes) {
        if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        return Math.round(bytes / 1024) + ' KB';
    }

    function processPhoto(file) {
        if (!file) return;

        if (!['image/jpeg', 'image/png'].includes(file.type)) {
            showToast('Foto Tidak Valid. Gunakan format JPG atau PNG.', 'error');
            fotoInput.value = '';
            fotoCameraInput.value = '';
            return;
        }

        if (file.size > MAX_PHOTO_BYTES) {
            showToast('Ukuran foto melebihi batas maksimal 15MB', 'error');
            fotoInput.value = '';
            fotoCameraInput.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = (event) => {
            const img = new Image();
            img.onload = () => {
                const maxPx = 1600;
                let w = img.width;
                let h = img.height;

                if (w > maxPx || h > maxPx) {
                    if (w > h) {
                        h = Math.round((h * maxPx) / w);
                        w = maxPx;
                    } else {
                        w = Math.round((w * maxPx) / h);
                        h = maxPx;
                    }
                }

                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, w, h);

                canvas.toBlob((blob) => {
                    preparedPhotoBlob = blob;
                    preparedPhotoName = file.name.replace(/\.[^/.]+$/, "") + ".jpg";

                    previewImg.src = canvas.toDataURL('image/jpeg', 0.85);
                    previewImg.classList.remove('hidden');
                    previewImg.classList.add('cursor-zoom-in');
                    previewPlaceholder.classList.add('hidden');
                    btnRemovePhoto.classList.remove('hidden');
                    previewFilename.innerText = preparedPhotoName;
                    previewFilename.classList.remove('hidden');
                    document.getElementById('photoBadge').innerText = '1 Terpilih';

                    // Indikator optimasi berkas
                    compressInfo.innerText = `Foto teroptimasi: ${formatSize(file.size)} -> ${formatSize(blob.size)}`;
                    compressInfo.classList.remove('hidden');
                }, 'image/jpeg', 0.85);
            };
            img.src = event.target.result;
        };
        reader.readAsDataURL(file);
    }

    btnPickPhoto.addEventListener('click', () => fotoInput.click());
    btnOpenCamera.addEventListener('click', () => fotoCameraInput.click());
    fotoInput.addEventListener('change', (e) => processPhoto(e.target.files[0]));
    fotoCameraInput.addEventListener('change', (e) => processPhoto(e.target.files[0]));

    // Pratinjau layar penuh
    function openPhotoModal() {
        if (!previewImg.src || previewImg.classList.contains('hidden')) return;
        photoModalImg.src = previewImg.src;
        photoModal.classList.remove('hidden');
    }
    function closePhotoModal() {
        photoModal.classList.add('hidden');
    }
    previewImg.addEventListener('click', openPhotoModal);
    btnClosePhotoModal.addEventListener('click', closePhotoModal);
    photoModal.addEventListener('click', (e) => {
        if (e.target === photoModal) closePhotoModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closePhotoModal();
    });

    btnRemovePhoto.addEventListener('click', () => {
        preparedPhotoBlob = null;
        fotoInput.value = '';
        fotoCameraInput.value = '';
        previewImg.classList.add('hidden');
        previewImg.src = '';
        previewPlaceholder.classList.remove('hidden');
        btnRemovePhoto.classList.add('hidden');
        previewFilename.classList.add('hidden');
        compressInfo.classList.add('hidden');
        document.getElementById('photoBadge').innerText = 'Maks. 15MB';
    });

    // Form Submit
    const reportForm = document.getElementById('reportForm');
    reportForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const deskripsi = deskripsiInput.value.trim();
        if (!deskripsi || !selectedCategoryId) {
            showToast('Data Laporan Belum Lengkap', 'error');
            return;
        }

        if (!preparedPhotoBlob) {
            showToast('Data Laporan Belum Lengkap', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('latitude', selectedLat);
        formData.append('longitude', selectedLon);
        formData.append('kategori_id', selectedCategoryId);
        formData.append('deskripsi', deskripsi);
        formData.append('foto', preparedPhotoBlob, preparedPhotoName);

        const manualAddress = manualAddressInput ? manualAddressInput.value.trim() : '';
        if (manualAddress) {
            formData.append('alamat', manualAddress);
        }

        const btnSubmit = document.getElementById('btnSubmitReport');
        btnSubmit.disabled = true;
        btnSubmit.classList.add('opacity-70');

        try {
            const res = await fetch('/api/v1/reports', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json'
                },
                body: formData
            });

            const resData = await res.json();

            if (!res.ok) {
                const message = resData.error ? resData.error.message : 'Gagal mengirim laporan';
                showToast(message, 'error');
                btnSubmit.disabled = false;
                btnSubmit.classList.remove('opacity-70');
                return;
            }

            showToast('Laporan terkirim', 'success');

            const reportId = resData.data?.laporan_id;

            setTimeout(() => {
                if (reportId) {
                    window.location.href = `/laporan/${reportId}/analisis`;
                } else {
                    window.location.href = '/riwayat-laporan';
                }
            }, 800);
        } catch (err) {
            showToast('Terjadi kesalahan koneksi. Silakan coba lagi.', 'error');
            btnSubmit.disabled = false;
            btnSubmit.classList.remove('opacity-70');
        }
    });
</script>
@endpush