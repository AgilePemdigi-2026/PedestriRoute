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
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f4f7fb;
        }
    </style>
    @stack('styles')
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased flex flex-col items-center justify-start">
    <div class="w-full max-w-md min-h-screen bg-white shadow-xl flex flex-col relative pb-16">
        @yield('content')
    </div>

    <!-- Notification Toast Container -->
    <div id="toast" class="fixed top-5 z-50 transform -translate-x-1/2 left-1/2 transition-all duration-300 opacity-0 pointer-events-none max-w-xs w-full px-4">
        <div id="toast-body" class="bg-slate-900 text-white text-sm px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3">
            <span id="toast-icon">ℹ️</span>
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
                toastIcon.innerText = '⚠️';
            } else if (type === 'success') {
                toastBody.className = 'bg-teal-700 text-white text-sm px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3';
                toastIcon.innerText = '✅';
            } else {
                toastBody.className = 'bg-slate-900 text-white text-sm px-4 py-3 rounded-xl shadow-2xl flex items-center gap-3';
                toastIcon.innerText = 'ℹ️';
            }

            toast.classList.remove('opacity-0', 'pointer-events-none');
            toast.classList.add('opacity-100');

            setTimeout(() => {
                toast.classList.add('opacity-0', 'pointer-events-none');
                toast.classList.remove('opacity-100');
            }, 3500);
        }
    </script>
    @stack('scripts')
</body>
</html>
