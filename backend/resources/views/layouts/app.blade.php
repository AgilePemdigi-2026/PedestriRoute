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
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased flex flex-col items-center justify-start">
    <div class="w-full max-w-md min-h-screen bg-white shadow-xl flex flex-col relative pb-16">
        @yield('content')
    </div>

    <!-- Notification Toast Container -->
    <div id="toast" class="fixed top-5 z-50 transform -translate-x-1/2 left-1/2 transition-all duration-300 opacity-0 pointer-events-none max-w-xs w-full px-4">
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

    <!-- Real-time notifikasi (F10): Pusher JS + Laravel Echo -->
    <script src="https://unpkg.com/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
    <script src="https://unpkg.com/laravel-echo@1.16.1/dist/echo.iife.js"></script>
    <script>
        (function () {
            const token = localStorage.getItem('token');
            if (!token) return; // belum login (mis. halaman login): lewati semuanya

            const authHeaders = { Authorization: 'Bearer ' + token, Accept: 'application/json' };
            const seen = new Set();
            let role = null;
            let badge, panel, listEl;

            // ---------- helpers ----------
            function targetUrl(n) {
                if (!n.laporan_id) return null;
                return role === 'admin'
                    ? '/admin/validasi/' + n.laporan_id
                    : '/laporan/' + n.laporan_id + '/analisis';
            }

            function fmtTime(iso) {
                if (!iso) return '';
                return new Date(iso).toLocaleString('id-ID', {
                    day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
                });
            }

            function setBadge(count) {
                if (!badge) return;
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = count > 0 ? 'flex' : 'none';
            }

            // PATCH dulu; kalau server menjawab 405, coba POST
            async function sendAction(url) {
                let res = await fetch(url, { method: 'PATCH', headers: authHeaders });
                if (res.status === 405) res = await fetch(url, { method: 'POST', headers: authHeaders });
                return res;
            }

            // ---------- UI bel ----------
            function buildBell() {
                // Pakai bel bawaan halaman kalau ada (beranda warga); kalau tidak, bikin bel floating (admin).
                let btn = document.getElementById('notifBellBtn');
                if (!btn) {
                    btn = document.createElement('button');
                    btn.type = 'button';
                    btn.setAttribute('aria-label', 'Notifikasi');
                    btn.style.cssText = 'position:fixed;top:12px;right:12px;z-index:40;width:42px;height:42px;'
                        + 'border-radius:9999px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.2);display:flex;'
                        + 'align-items:center;justify-content:center;border:0;cursor:pointer;';
                    btn.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#374151" '
                        + 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
                        + '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>'
                        + '<path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
                    document.body.appendChild(btn);
                }

                badge = document.createElement('span');
                badge.style.cssText = 'position:absolute;top:-6px;right:-6px;min-width:18px;height:18px;padding:0 4px;'
                    + 'border-radius:9999px;background:#dc2626;color:#fff;font-size:11px;font-weight:700;'
                    + 'display:none;align-items:center;justify-content:center;pointer-events:none;';
                btn.appendChild(badge);

                panel = document.createElement('div');
                panel.style.cssText = 'display:none;position:fixed;z-index:60;width:min(320px,calc(100vw - 24px));'
                    + 'max-height:70vh;overflow:auto;background:#fff;border-radius:12px;'
                    + 'box-shadow:0 8px 24px rgba(0,0,0,.2);';

                const head = document.createElement('div');
                head.style.cssText = 'display:flex;justify-content:space-between;align-items:center;padding:10px 12px;'
                    + 'border-bottom:1px solid #e5e7eb;font-weight:600;font-size:14px;color:#111827;';
                head.innerHTML = '<span>Notifikasi</span>';

                const readAll = document.createElement('button');
                readAll.type = 'button';
                readAll.textContent = 'Tandai semua dibaca';
                readAll.style.cssText = 'font-size:12px;font-weight:500;color:#008775;background:none;border:0;cursor:pointer;';
                readAll.addEventListener('click', async () => {
                    const res = await sendAction('/api/v1/notifications/read-all');
                    if (res.ok) loadList();
                });
                head.appendChild(readAll);

                listEl = document.createElement('div');
                panel.appendChild(head);
                panel.appendChild(listEl);
                document.body.appendChild(panel);

                btn.addEventListener('click', () => {
                    const open = panel.style.display === 'block';
                    if (open) { panel.style.display = 'none'; return; }
                    const r = btn.getBoundingClientRect();
                    panel.style.top = (r.bottom + 8) + 'px';
                    panel.style.right = Math.max(12, window.innerWidth - r.right) + 'px';
                    panel.style.display = 'block';
                });
                document.addEventListener('click', (e) => {
                    if (!panel.contains(e.target) && !btn.contains(e.target)) panel.style.display = 'none';
                });
            }

            function renderList(items) {
                listEl.innerHTML = '';
                if (!items.length) {
                    const empty = document.createElement('div');
                    empty.textContent = 'Belum ada notifikasi';
                    empty.style.cssText = 'padding:16px;text-align:center;font-size:13px;color:#6b7280;';
                    listEl.appendChild(empty);
                    return;
                }
                items.forEach((n) => {
                    const unread = n.status_baca === 'BELUM';
                    const row = document.createElement('div');
                    row.style.cssText = 'padding:10px 12px;border-bottom:1px solid #f3f4f6;cursor:pointer;'
                        + (unread ? 'background:#e6f5f3;' : '');

                    const msg = document.createElement('div');
                    msg.textContent = n.pesan;
                    msg.style.cssText = 'font-size:13px;color:#111827;' + (unread ? 'font-weight:600;' : '');

                    const time = document.createElement('div');
                    time.textContent = fmtTime(n.tanggal_kirim);
                    time.style.cssText = 'font-size:11px;color:#6b7280;margin-top:2px;';

                    row.appendChild(msg);
                    row.appendChild(time);
                    row.addEventListener('click', async () => {
                        if (unread) await sendAction('/api/v1/notifications/' + n.notifikasi_id + '/read');
                        const url = targetUrl(n);
                        if (url) window.location.href = url;
                        else loadList();
                    });
                    listEl.appendChild(row);
                });
            }

            async function loadList() {
                try {
                    const res = await fetch('/api/v1/notifications?limit=20', { headers: authHeaders });
                    if (!res.ok) return;
                    const json = await res.json();
                    renderList(json.data || []);
                    setBadge((json.meta && json.meta.unread_count) || 0);
                } catch (e) {
                    console.warn('[Notif] gagal memuat:', e);
                }
            }

            // ---------- toast yang bisa diklik ----------
            function clickableToast(payload) {
                if (typeof showToast === 'function') showToast(payload.pesan, 'success');
                const t = document.getElementById('toast');
                const url = targetUrl(payload);
                if (t) {
                    t.style.cursor = url ? 'pointer' : '';
                    t.onclick = url ? () => { window.location.href = url; } : null;
                }
            }

            // ---------- satu pintu untuk semua event real-time ----------
            function onRealtime(name, payload) {
                console.log('[Echo]', name, payload);
                if (payload.notifikasi_id) {
                    if (seen.has(payload.notifikasi_id)) return; // anti dobel
                    seen.add(payload.notifikasi_id);
                }
                clickableToast(payload);
                loadList(); // sumber kebenaran tetap server
                window.dispatchEvent(new CustomEvent('realtime-notification', { detail: { name, payload } }));
            }

            // ---------- init ----------
            fetch('/api/v1/auth/me', { headers: authHeaders })
                .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
                .then(({ data }) => {
                    role = data.role;
                    buildBell();
                    loadList();

                    window.Pusher = Pusher;

                    const echo = new Echo({
                        broadcaster: 'reverb',
                        key: @json(config('broadcasting.connections.reverb.key')),
                        wsHost: window.location.hostname, // jangan pakai REVERB_HOST: di container nilainya "reverb"
                        wsPort: 8080,
                        wssPort: 8080,
                        forceTLS: false,
                        enabledTransports: ['ws', 'wss'],
                        authEndpoint: '/api/v1/broadcasting/auth',
                        auth: { headers: authHeaders },
                    });
                    window.Echo = echo;

                    echo.connector.pusher.connection.bind('state_change', (s) => {
                        console.log('[Echo] koneksi:', s.previous, '->', s.current);
                    });

                    if (role === 'warga') {
                        echo.private('warga.' + data.id)
                            .listen('.report.validated', (e) => onRealtime('report.validated', e))
                            .listen('.report.auto-rejected', (e) => onRealtime('report.auto-rejected', e))
                            .listen('.report.repair-updated', (e) => onRealtime('report.repair-updated', e));
                    } else if (role === 'admin') {
                        echo.private('admin.reports')
                            .listen('.admin.queue-updated', (e) => onRealtime('admin.queue-updated', e));
                    }
                })
                .catch((err) => console.warn('[Echo] tidak aktif:', err));
        })();
    </script>
    @stack('scripts')
</body>
</html>