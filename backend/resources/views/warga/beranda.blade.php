@extends('layouts.app')

@section('title', 'Beranda Warga - PedestriRoute')

@push('styles')
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #map {
        height: 240px;
        width: 100%;
        border-radius: 1rem;
        z-index: 10;
    }
</style>
@endpush

@section('content')
<div class="px-5 pt-6 pb-20 flex flex-col flex-1">
    <!-- Top Header -->
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 bg-brand-600 rounded-xl flex items-center justify-center shadow-md shadow-teal-900/10">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M13.5 5.5c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zM9.8 8.9L7 23h2.1l1.8-8 2.1 2v6h2v-7.5l-2.1-2 .6-3C14.8 12 16.8 13 19 13v-2c-1.9 0-3.5-1-4.3-2.4l-1-1.6c-.4-.6-1-1-1.7-1-.3 0-.5.1-.8.1L6 8.3V13h2V9.6l1.8-.7"/>
                </svg>
            </div>
            <div>
                <h1 class="text-base font-extrabold text-slate-900 leading-tight">PedestriRoute</h1>
                <p class="text-[10px] text-teal-800 font-semibold tracking-wide">SURABAYA PEJALAN KAKI</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <!-- Notification bell (Stub Sprint 1) -->
            <button onclick="showToast('Pusat Notifikasi lengkap akan aktif di Sprint 3', 'info')" class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-slate-200 transition relative">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
            </button>

            <!-- User Avatar & Logout Dropdown -->
            <div class="relative">
                <button id="userMenuBtn" class="w-9 h-9 rounded-full overflow-hidden border-2 border-brand-500 focus:outline-none">
                    <img id="userAvatar" src="https://ui-avatars.com/api/?name=Warga&background=006257&color=fff" alt="Avatar" class="w-full h-full object-cover">
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
    </div>

    <!-- Greeting Banner -->
    <div class="bg-gradient-to-r from-brand-700 to-brand-600 rounded-3xl p-5 text-white shadow-lg shadow-teal-900/15 mb-5 relative overflow-hidden">
        <div class="relative z-10">
            <span class="inline-block px-2.5 py-0.5 bg-white/20 backdrop-blur-md rounded-full text-[10px] font-bold tracking-wider mb-2">WARGA SURABAYA</span>
            <h2 class="text-xl font-extrabold tracking-tight">Halo, <span id="greetingName">Warga</span>! 👋</h2>
            <p class="text-xs text-teal-100 mt-1 max-w-xs leading-relaxed">
                Mari bersama pantau dan wujudkan fasilitas jalur pejalan kaki yang aman & nyaman.
            </p>
        </div>
        <div class="absolute -right-4 -bottom-6 w-28 h-28 bg-white/10 rounded-full blur-xl pointer-events-none"></div>
    </div>

    <!-- Quick Action: Lapor Fasilitas -->
    <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm mb-5 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 bg-teal-50 rounded-xl flex items-center justify-center text-brand-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-800">Lapor Kondisi Jalur</h3>
                <p class="text-xs text-slate-500">Trotoar rusak atau halangan jalan?</p>
            </div>
        </div>
        <a href="/lapor-kondisi" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-xs font-bold rounded-xl shadow-md shadow-teal-900/10 flex items-center gap-1.5 transition">
            <span>Lapor</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
        </a>
    </div>

    <!-- Status Laporan Saya Summary -->
    <div class="mb-5">
        <div class="flex items-center justify-between mb-2.5">
            <h3 class="text-xs font-bold text-slate-700 tracking-wide uppercase">Status Laporan Saya</h3>
            <span id="totalReportsCount" class="text-xs font-bold text-brand-600">Total: 0</span>
        </div>
        <div class="grid grid-cols-3 gap-2.5">
            <div class="bg-amber-50/70 border border-amber-100 rounded-2xl p-3 text-center">
                <p class="text-[10px] font-bold text-amber-800 uppercase tracking-wider mb-1">Menunggu</p>
                <p id="countMenunggu" class="text-xl font-extrabold text-amber-900">0</p>
            </div>
            <div class="bg-emerald-50/70 border border-emerald-100 rounded-2xl p-3 text-center">
                <p class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider mb-1">Valid</p>
                <p id="countTervalidasi" class="text-xl font-extrabold text-emerald-900">0</p>
            </div>
            <div class="bg-rose-50/70 border border-rose-100 rounded-2xl p-3 text-center">
                <p class="text-[10px] font-bold text-rose-800 uppercase tracking-wider mb-1">Ditolak</p>
                <p id="countDitolak" class="text-xl font-extrabold text-rose-900">0</p>
            </div>
        </div>
    </div>

    <!-- Map Preview (Mulyorejo / Surabaya) -->
    <div class="mb-2">
        <div class="flex items-center justify-between mb-2.5">
            <h3 class="text-xs font-bold text-slate-700 tracking-wide uppercase">Peta Wilayah Layanan (Surabaya)</h3>
            <span class="text-[10px] font-semibold text-slate-400">Area Mulyorejo</span>
        </div>
        <div class="mb-2 rounded-xl border border-teal-200 bg-teal-50 px-3 py-2" role="note">
            <p class="text-[10px] font-bold text-teal-800">Wilayah Layanan Aktif: Kecamatan Mulyorejo, Surabaya</p>
            <p class="text-[10px] text-slate-500 mt-0.5">Batas wilayah administratif resmi Kecamatan Mulyorejo.</p>
        </div>
        <div id="map"></div>
        <p class="text-[10px] text-slate-400 mt-1.5 text-center">© OpenStreetMap contributors</p>
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
                class="w-full py-3 bg-brand-600 hover:bg-brand-700 text-white font-semibold rounded-xl text-sm transition shadow-lg shadow-teal-900/10">
                Simpan & Lanjutkan
            </button>
        </form>
    </div>
</div>

<!-- Bottom Navigation Bar matching mockup -->
<div class="fixed bottom-0 max-w-md w-full bg-white border-t border-slate-200 py-2.5 px-6 flex items-center justify-between z-40">
    <a href="/beranda-warga" class="flex flex-col items-center gap-1 text-brand-600 font-bold">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2L2 12h3v8h6v-6h2v6h6v-8h3L12 2z"/>
        </svg>
        <span class="text-[10px]">Peta</span>
    </a>
    <a href="javascript:void(0)" onclick="showToast('Rute Pejalan Kaki aktif pada Sprint 3', 'info')" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
        </svg>
        <span class="text-[10px] font-medium">Rute</span>
    </a>
    <a href="/lapor-kondisi" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600">
        <div class="w-10 h-10 -mt-5 bg-brand-600 rounded-full flex items-center justify-center text-white shadow-lg shadow-teal-900/30">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
        </div>
        <span class="text-[10px] font-medium mt-0.5">Lapor</span>
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
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
        document.getElementById('greetingName').innerText = user.nama || 'Warga';
        document.getElementById('menuUserName').innerText = user.nama || 'Warga';
        document.getElementById('menuUserEmail').innerText = user.email || '';
        const avatarEl = document.getElementById('userAvatar');
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

    // 5. Initialize Leaflet Map (Mulyosari / Surabaya: -7.2663, 112.7965)
    const map = L.map('map', {
        preferCanvas: true,
        attributionControl: false
    }).setView([-7.2663, 112.7965], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);

    const serviceArea = PedestriRouteServiceArea.attach(map);

    // Initial marker on Mulyorejo
    L.circleMarker([-7.2663, 112.7965], {
        radius: 8,
        color: '#006257',
        fillColor: '#008775',
        fillOpacity: 0.9
    }).addTo(map).bindPopup('<b>Kecamatan Mulyorejo, Surabaya</b><br>Siap melayani pemantauan rute pejalan kaki.');
</script>
@endpush