@extends('layouts.app')

@section('title', 'Lapor Fasilitas Pejalan Kaki - PedestriRoute')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #laporMap {
        height: 200px;
        width: 100%;
        border-radius: 0.875rem;
        z-index: 10;
    }
</style>
@endpush

@section('content')
<div class="px-5 pt-5 pb-24 flex flex-col flex-1">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
        <a href="/beranda-warga" class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-slate-200 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
            </svg>
        </a>
        <div class="text-center">
            <h1 class="text-sm font-extrabold text-slate-900 leading-tight">PedestriRoute</h1>
            <p class="text-[10px] text-teal-800 font-semibold tracking-wider">Lapor</p>
        </div>
        <div class="relative">
            <button id="userMenuBtn" class="w-9 h-9 rounded-full overflow-hidden border-2 border-brand-500 focus:outline-none">
                <img id="navAvatar" src="https://ui-avatars.com/api/?name=Warga&background=006257&color=fff" alt="Avatar" class="w-full h-full object-cover">
            </button>
            <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-slate-100 py-2 z-50">
                <div class="px-4 py-2 border-b border-slate-100">
                    <p id="menuUserName" class="text-xs font-bold text-slate-900 truncate">Warga</p>
                    <p id="menuUserEmail" class="text-[10px] text-slate-500 truncate">user@gmail.com</p>
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

    <!-- Title Badge & Narrative -->
    <div class="mb-4">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-teal-50 border border-teal-200 rounded-full mb-2">
            <svg class="w-3.5 h-3.5 text-teal-700" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6v-5c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
            </svg>
            <span class="text-[10px] font-bold text-teal-800 uppercase tracking-wider">Partisipasi Warga</span>
        </div>
        <h2 class="text-xl font-extrabold text-slate-900 tracking-tight">Lapor Fasilitas Pejalan Kaki</h2>
        <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed">
            Bantu sesama pejalan kaki dengan mendata trotoar rusak atau fasilitas yang terhalang.
        </p>
    </div>

    <form id="reportForm" class="space-y-4">
        <!-- 1. Lokasi Kejadian Card -->
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-2.5">
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-brand-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    <span class="text-xs font-bold text-slate-800">Lokasi Kejadian</span>
                </div>
                <span id="gpsBadge" class="text-[10px] font-bold text-teal-800 bg-teal-50 px-2 py-0.5 rounded-full border border-teal-100">
                    GPS Siap
                </span>
            </div>

            <!-- Map container -->
            <div class="mb-2 rounded-xl border border-teal-200 bg-teal-50 px-3 py-2" role="note">
                <p class="text-[10px] font-bold text-teal-800">Wilayah Layanan Aktif: Kecamatan Mulyorejo, Surabaya</p>
                <p class="text-[10px] text-slate-500 mt-0.5">Batas wilayah administratif resmi Kecamatan Mulyorejo.</p>
            </div>
            <div id="laporMap" class="mb-3 border border-slate-100"></div>

            <!-- Address bar with accuracy -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-2.5 flex items-center justify-between mb-2.5 text-xs text-slate-700">
                <div class="flex items-center gap-2 truncate pr-2">
                    <span class="w-2 h-2 rounded-full bg-brand-600 shrink-0"></span>
                    <span id="alamatText" class="truncate font-medium">Jl. Raya Mulyosari, Mulyorejo, Surabaya</span>
                </div>
                <span id="accuracyBadge" class="text-[10px] font-semibold text-brand-700 bg-teal-50 border border-teal-200 px-2 py-0.5 rounded shrink-0">
                    Radius Snapping 50m
                </span>
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
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-brand-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M4 4h3l2-2h6l2 2h3a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2zm8 3a5 5 0 100 10 5 5 0 000-10zm0 2a3 3 0 110 6 3 3 0 010-6z"/>
                    </svg>
                    <span class="text-xs font-bold text-slate-800">Foto Bukti</span>
                </div>
                <span id="photoBadge" class="text-[10px] font-semibold text-slate-400">Maks. 15MB</span>
            </div>

            <input type="file" id="fotoInput" accept="image/jpeg,image/png" class="hidden">
            <input type="file" id="fotoCameraInput" accept="image/*" capture="environment" class="hidden">

            <!-- Dua Pilihan: Ambil Foto (Kamera Langsung) atau Unggah dari Galeri/Penyimpanan -->
            <div class="grid grid-cols-2 gap-2.5 mb-3">
                <button type="button" id="btnOpenCamera"
                    class="py-3 px-2 border-2 border-dashed border-teal-200 hover:border-brand-500 bg-teal-50/50 hover:bg-teal-50 rounded-2xl flex flex-col items-center justify-center text-center transition group">
                    <div class="w-9 h-9 rounded-full bg-teal-100 group-hover:bg-teal-200 text-brand-600 flex items-center justify-center mb-1.5 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <span class="text-xs font-bold text-slate-800">Ambil Foto</span>
                    <span class="text-[10px] text-slate-500">Kamera langsung</span>
                </button>

                <button type="button" id="btnPickPhoto"
                    class="py-3 px-2 border-2 border-dashed border-slate-200 hover:border-brand-500 bg-slate-50 hover:bg-slate-100 rounded-2xl flex flex-col items-center justify-center text-center transition group">
                    <div class="w-9 h-9 rounded-full bg-slate-200 group-hover:bg-slate-300 text-slate-700 flex items-center justify-center mb-1.5 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <span class="text-xs font-bold text-slate-800">Unggah Foto</span>
                    <span class="text-[10px] text-slate-500">Pilih dari berkas</span>
                </button>
            </div>

            <!-- Preview Box (Full Width) -->
            <div id="previewContainer" class="h-36 bg-slate-100 rounded-2xl border border-slate-200 relative overflow-hidden flex items-center justify-center">
                <span id="previewPlaceholder" class="text-xs text-slate-400 font-medium">Belum ada foto yang dipilih</span>
                <img id="previewImg" class="w-full h-full object-cover hidden" alt="Pratinjau foto bukti" />
                <button type="button" id="btnRemovePhoto"
                    class="hidden absolute top-2 right-2 w-7 h-7 rounded-full bg-slate-900/70 text-white flex items-center justify-center hover:bg-slate-900 transition">
                    ✕
                </button>
                <span id="previewFilename" class="hidden absolute bottom-1 left-2 right-2 text-[10px] text-white bg-black/60 px-1.5 py-0.5 rounded truncate"></span>
            </div>

            <!-- Indikator optimasi berkas -->
            <p id="compressInfo" class="hidden mt-2 text-[11px] text-teal-700 font-medium"></p>
            <p class="text-[10px] text-slate-400 mt-1">Format JPG atau PNG (Maks. 15MB). Ketuk foto pratinjau untuk melihat ukuran penuh.</p>
        </div>

        <!-- 3. Kategori Masalah Card -->
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-brand-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l4 8h-8l4-8zm0 13a2 2 0 100-4 2 2 0 000 4zm-7 7l4-8H1l4 8zm14 0l4-8h-8l4 8z"/>
                    </svg>
                    <span class="text-xs font-bold text-slate-800">Kategori Masalah</span>
                </div>
                <span class="text-[10px] font-bold text-rose-500">*Wajib</span>
            </div>

            <!-- Categories Grid 2x2 -->
            <div id="categoriesGrid" class="grid grid-cols-2 gap-2.5">
                <button type="button" data-id="1" class="cat-chip py-3 px-3 rounded-xl border border-teal-200 bg-teal-50 text-brand-800 font-bold text-xs flex items-center gap-2 transition text-left">
                    <span class="text-base">🛠️</span>
                    <span class="truncate">Trotoar Rusak</span>
                </button>
                <button type="button" data-id="2" class="cat-chip py-3 px-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 font-semibold text-xs flex items-center gap-2 transition text-left hover:bg-slate-100">
                    <span class="text-base">🚦</span>
                    <span class="truncate">Penyeberangan</span>
                </button>
                <button type="button" data-id="3" class="cat-chip py-3 px-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 font-semibold text-xs flex items-center gap-2 transition text-left hover:bg-slate-100">
                    <span class="text-base">💡</span>
                    <span class="truncate">Penerangan Minim</span>
                </button>
                <button type="button" data-id="4" class="cat-chip py-3 px-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 font-semibold text-xs flex items-center gap-2 transition text-left hover:bg-slate-100">
                    <span class="text-base">🚫</span>
                    <span class="truncate">Halangan Fisik</span>
                </button>
            </div>
        </div>

        <!-- 4. Deskripsi Card -->
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                    </svg>
                    <span class="text-xs font-bold text-slate-800">Deskripsi</span>
                </div>
                <span class="text-[10px] font-mono text-slate-400"><span id="charCount">0</span>/200</span>
            </div>
            <textarea id="deskripsi" rows="3" maxlength="200"
                placeholder="Deskripsi singkat (contoh: Ubin pemandu tunanetra amblas)..."
                class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition leading-relaxed"></textarea>
        </div>

        <!-- Submit Button -->
        <button type="submit" id="btnSubmitReport"
            class="w-full py-3.5 bg-brand-600 hover:bg-brand-700 text-white font-bold rounded-xl flex items-center justify-center gap-2 shadow-lg shadow-teal-900/10 transition mt-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
            </svg>
            <span>Kirim Laporan</span>
        </button>

        <p class="text-[11px] text-slate-500 flex items-center justify-center gap-1.5 pt-1">
            <svg class="w-4 h-4 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            <span>Laporan diverifikasi dalam 1x24 jam oleh Dishub</span>
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
<div class="fixed bottom-0 max-w-md w-full bg-white border-t border-slate-200 py-2.5 px-6 flex items-center justify-between z-40">
    <a href="/beranda-warga" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2L2 12h3v8h6v-6h2v6h6v-8h3L12 2z"/>
        </svg>
        <span class="text-[10px]">Peta</span>
    </a>
    <a href="javascript:void(0)" onclick="showToast('Rute Pejalan Kaki aktif pada Sprint 3', 'info')" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
        <span class="text-[10px] font-medium">Rute</span>
    </a>
    <a href="/lapor-kondisi" class="flex flex-col items-center gap-1 text-brand-600 font-bold">
        <div class="w-10 h-10 -mt-5 bg-brand-600 rounded-full flex items-center justify-center text-white shadow-lg shadow-teal-900/30">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
        </div>
        <span class="text-[10px] mt-0.5">Lapor</span>
    </a>
    <a href="javascript:void(0)" onclick="showToast('Notifikasi aktif pada Sprint 3', 'info')" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        <span class="text-[10px] font-medium">Notifikasi</span>
    </a>
    <a href="javascript:void(0)" onclick="toggleUserDropdown()" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        <span class="text-[10px] font-medium">Profil</span>
    </a>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@include('partials.mulyorejo-service-area')
<script>
    const token = localStorage.getItem('token');
    if (!token) {
        window.location.href = '/login';
    }

    // User Profile & Dropdown
    function applyUserProfile(user) {
        if (!user) return;
        document.getElementById('menuUserName').innerText = user.nama || 'Warga';
        document.getElementById('menuUserEmail').innerText = user.email || '';
        const avatarEl = document.getElementById('navAvatar');
        if (avatarEl) {
            if (user.avatar_url) {
                avatarEl.src = user.avatar_url;
            } else {
                avatarEl.src = `https://ui-avatars.com/api/?name=${encodeURIComponent(user.nama || 'Warga')}&background=006257&color=fff`;
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

    // Marker Pinpoint
    let marker = L.marker([selectedLat, selectedLon], { draggable: true }).addTo(map);

    // Layer Indikator Radius Snapping 50m
    let snappingCircle = L.circle([selectedLat, selectedLon], {
        radius: 50,
        color: '#006257',
        weight: 1.5,
        fillColor: '#008775',
        fillOpacity: 0.15,
        dashArray: '4, 6'
    }).addTo(map);

    // Reverse geocoding Nominatim (FR-F7-11)
    const GEOCODE_DEBOUNCE_MS = 500;      // debounce minimal 500 ms
    const GEOCODE_MIN_INTERVAL_MS = 1000; // maksimal 1 request per detik
    let reverseGeocodeTimer = null;
    let reverseGeocodeController = null;
    let lastGeocodeAt = 0;

    // Perkiraan batas wilayah Kota Surabaya
    function isInsideSurabaya(lat, lon) {
        return lat >= -7.40 && lat <= -7.10 && lon >= 112.55 && lon <= 112.90;
    }

    function fetchStreetName(lat, lon) {
        const alamatEl = document.getElementById('alamatText');
        if (!alamatEl) return;

        clearTimeout(reverseGeocodeTimer);
        if (reverseGeocodeController) reverseGeocodeController.abort();

        if (!isInsideSurabaya(lat, lon)) {
            alamatEl.innerText = `Di luar wilayah layanan Surabaya (${lat.toFixed(4)}, ${lon.toFixed(4)})`;
            return;
        }

        alamatEl.innerText = 'Mendeteksi alamat...';

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
                        alamatEl.innerText = `${road}, Surabaya`;
                        return;
                    }
                }
            } catch (e) {
                if (e.name === 'AbortError') return; // digantikan request yang lebih baru
            }
            alamatEl.innerText = `Titik Koordinat (${lat.toFixed(4)}, ${lon.toFixed(4)})`;
        }, wait);
    }

    function updateCoordinates(lat, lon, accuracy = null) {
        selectedLat = parseFloat(lat.toFixed(6));
        selectedLon = parseFloat(lon.toFixed(6));
        const newLatLng = [selectedLat, selectedLon];

        marker.setLatLng(newLatLng);
        snappingCircle.setLatLng(newLatLng); // Update posisi radius snapping 50m

        document.getElementById('coordDisplay').value = `${selectedLat}, ${selectedLon}`;
        
        const accuracyBadge = document.getElementById('accuracyBadge');
        if (accuracy) {
            accuracyBadge.innerText = `GPS (±${Math.round(accuracy)}m) • Snap 50m`;
        } else {
            accuracyBadge.innerText = `Radius Snapping 50m`;
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
                if (!serviceArea.navigationBounds.contains([lat, lon])) {
                    document.getElementById('gpsBadge').innerText = 'GPS di luar Mulyorejo';
                    showToast('Lokasi GPS berada di luar wilayah layanan Mulyorejo. Pilih titik laporan pada peta.', 'error');
                    return;
                }
                updateCoordinates(lat, lon, pos.coords.accuracy);
                map.setView([lat, lon], 17);
                document.getElementById('gpsBadge').innerText = 'GPS Terkunci';
                showToast('Lokasi GPS & Radius 50m terdistribusi', 'success');
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
                c.className = 'cat-chip py-3 px-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 font-semibold text-xs flex items-center gap-2 transition text-left hover:bg-slate-100';
            });
            chip.className = 'cat-chip py-3 px-3 rounded-xl border border-teal-200 bg-teal-50 text-brand-800 font-bold text-xs flex items-center gap-2 transition text-left';
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
            setTimeout(() => {
                window.location.href = '/beranda-warga';
            }, 800);
        } catch (err) {
            showToast('Terjadi kesalahan koneksi. Silakan coba lagi.', 'error');
            btnSubmit.disabled = false;
            btnSubmit.classList.remove('opacity-70');
        }
    });
</script>
@endpush