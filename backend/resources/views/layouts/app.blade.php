<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>@yield('title', 'PedestriRoute - Navigasi Pejalan Kaki')</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#e6f5f3',
                            100: '#ccebe7',
                            500: '#008775',
                            600: '#006257',
                            700: '#004f46',
                            800: '#003d36',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f4f7fb;
        }
    </style>
    @stack('styles')
</head>
@php
    $isWargaShell = request()->routeIs('warga.*');
    $isAdminShell = request()->routeIs('admin.*');
    $isAuthShell = request()->routeIs('login', 'register', 'legal.*');
@endphp
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased flex flex-col items-center justify-start">
    @if($isWargaShell || $isAdminShell)
        <header class="hidden md:flex fixed top-0 left-0 right-0 md:left-60 lg:left-64 h-16 z-30 items-center justify-between gap-4 bg-white/95 backdrop-blur border-b border-slate-200 px-6">
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ $isAdminShell ? route('admin.validasi.queue') : route('warga.beranda') }}" class="flex items-center gap-2.5 shrink-0">
                    <span class="w-8 h-8 rounded-xl bg-brand-600 text-white flex items-center justify-center font-extrabold text-sm">P</span>
                    <span class="font-extrabold text-slate-900 tracking-tight">PedestriRoute</span>
                </a>
                <span class="hidden lg:block w-px h-5 bg-slate-200"></span>
                <span class="hidden lg:block text-xs font-semibold text-slate-500 truncate">@yield('title')</span>
            </div>
            <a href="{{ route('notifikasi.index') }}" data-notification-link
                class="relative w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition shrink-0"
                aria-label="Notifikasi">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <span data-notification-count aria-hidden="true"
                    class="hidden absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white text-[9px] font-bold flex items-center justify-center"></span>
            </a>
        </header>
    @endif
    <div class="w-full {{ ($isWargaShell || $isAdminShell) ? 'max-w-md md:max-w-none md:shadow-none md:pb-0 md:pt-16 md:pl-60 lg:pl-64' : ($isAuthShell ? 'max-w-md lg:max-w-none lg:shadow-none lg:pb-0' : 'max-w-md') }} min-h-screen bg-white shadow-xl flex flex-col relative pb-16">
        @yield('content')
        @if($isWargaShell)
            @include('partials.warga-bottom-nav', ['navMobileVisibility' => request()->is('navigasi', 'riwayat-laporan', 'lapor-kondisi') ? 'hidden' : 'flex'])
        @endif
    </div>

    <!-- Notification Toast Container -->
    <div id="toast" class="fixed top-5 {{ ($isWargaShell || $isAdminShell) ? 'md:top-20' : '' }} z-50 transform -translate-x-1/2 left-1/2 transition-all duration-300 opacity-0 pointer-events-none max-w-xs w-full px-4">
        <div id="toast-body" class="bg-slate-900 text-white text-sm px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3">
            <span id="toast-icon" class="flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-teal-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                </svg>
            </span>
            <span id="toast-message" class="flex-1 font-medium leading-snug"></span>
        </div>
    </div>

    <script>
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            const toastBody = document.getElementById('toast-body');
            const toastMsg = document.getElementById('toast-message');
            const toastIcon = document.getElementById('toast-icon');

            toastMsg.innerText = message;
            if (type === 'error') {
                toastBody.className = 'bg-rose-600 text-white text-sm px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3';
                toastIcon.innerHTML = '<svg class="w-5 h-5 text-white shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13.5"/><circle cx="12" cy="17.2" r="1.1" fill="currentColor" stroke="none"/></svg>';
            } else if (type === 'success') {
                toastBody.className = 'bg-teal-700 text-white text-sm px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3';
                toastIcon.innerHTML = '<svg class="w-5 h-5 text-white shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
            } else {
                toastBody.className = 'bg-slate-900 text-white text-sm px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3';
                toastIcon.innerHTML = '<svg class="w-5 h-5 text-teal-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
            }

            toast.classList.remove('opacity-0', 'pointer-events-none');
            toast.classList.add('opacity-100');

            setTimeout(() => {
                toast.classList.add('opacity-0', 'pointer-events-none');
                toast.classList.remove('opacity-100');
            }, 3500);
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    </script>
    @vite('resources/js/app.js')
    @stack('scripts')
</body>
</html>
