import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

async function startNotifications() {
    const token = localStorage.getItem('token');

    if (!token || ['/login', '/register', '/verify-otp'].includes(location.pathname)) {
        return;
    }

    let echo = null;
    let stopped = false;
    let refreshing = false;
    let refreshAgain = false;
    const receivedIds = new Set();

    function sessionMatches() {
        return !stopped && localStorage.getItem('token') === token;
    }

    function setConnection(text) {
        document.querySelectorAll('[data-notification-connection]')
            .forEach(element => {
                element.textContent = text;
            });
    }

    function disconnect() {
        stopped = true;
        echo?.disconnect();

        document.querySelectorAll('[data-notification-count]')
            .forEach(element => element.classList.add('hidden'));

        setConnection('Koneksi notifikasi terputus');
    }

    async function api(path) {
        if (!sessionMatches()) {
            disconnect();
            throw new Error('Akun telah berubah.');
        }

        const response = await fetch('/api/v1' + path, {
            headers: {
                Authorization: 'Bearer ' + token,
                Accept: 'application/json',
            },
        });

        if (response.status === 401) {
            disconnect();
            throw new Error('Sesi notifikasi berakhir.');
        }

        if (!response.ok) {
            throw new Error('Gagal mengambil data notifikasi.');
        }

        const result = await response.json();

        if (!sessionMatches()) {
            disconnect();
            throw new Error('Akun telah berubah.');
        }

        return result;
    }

    function renderCount(count) {
        document.querySelectorAll('[data-notification-count]')
            .forEach(element => {
                element.textContent = count > 99 ? '99+' : String(count);
                element.classList.toggle('hidden', count === 0);
            });

        document.querySelectorAll('[data-notification-link]')
            .forEach(element => {
                element.setAttribute(
                    'aria-label',
                    `Notifikasi, ${count} belum dibaca`
                );
            });
    }

    async function refreshCount() {
        if (!sessionMatches()) return;

        if (refreshing) {
            refreshAgain = true;
            return;
        }

        refreshing = true;

        try {
            do {
                refreshAgain = false;

                // Menggunakan unread_count dari endpoint tahap 3.
                const result = await api('/notifications?page=1');
                renderCount(Number(result.meta.unread_count) || 0);
            } while (refreshAgain && sessionMatches());
        } catch (error) {
            console.warn('[Notifikasi]', error.message);
        } finally {
            refreshing = false;
        }
    }

    function refreshPage() {
        window.dispatchEvent(new Event('notifications:changed'));
    }

    try {
        const result = await api('/auth/me');
        const user = result.data;

        if (!['warga', 'admin'].includes(user.role)) {
            return;
        }

        const key = import.meta.env.VITE_REVERB_APP_KEY;

        if (!key) {
            setConnection('Konfigurasi Reverb belum tersedia');
            console.warn('[Notifikasi] VITE_REVERB_APP_KEY belum diatur.');
            return;
        }

        window.Pusher = Pusher;

        const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
        const port = Number(import.meta.env.VITE_REVERB_PORT)
            || (scheme === 'https' ? 443 : 80);

        echo = new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: import.meta.env.VITE_REVERB_HOST || location.hostname,
            wsPort: port,
            wssPort: port,
            forceTLS: scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/api/broadcasting/auth',
            auth: {
                headers: {
                    Authorization: 'Bearer ' + token,
                    Accept: 'application/json',
                },
            },
        });

        setConnection('Menghubungkan notifikasi...');

        echo.connector.pusher.connection.bind('state_change', ({ current }) => {
            if (!sessionMatches()) {
                disconnect();
                return;
            }

            if (['disconnected', 'unavailable', 'failed'].includes(current)) {
                setConnection('Terputus — gunakan Muat ulang sementara');
            } else if (current === 'connecting') {
                setConnection('Menghubungkan notifikasi...');
            }
        });

        echo.private(`notifications.${user.role}.${user.id}`)
            .subscribed(() => {
                if (!sessionMatches()) {
                    disconnect();
                    return;
                }

                setConnection('Notifikasi real-time aktif');
                refreshCount();

                // Ambil ulang data ketika pertama terhubung atau tersambung kembali.
                refreshPage();
            })
            .listen('.notifikasi.dibuat', notification => {
                if (!sessionMatches()) {
                    disconnect();
                    return;
                }

                const id = String(notification.notifikasi_id);

                if (receivedIds.has(id)) return;

                receivedIds.add(id);

                // Batasi penyimpanan ID di memori browser.
                if (receivedIds.size > 100) {
                    receivedIds.delete(receivedIds.values().next().value);
                }

                refreshCount();
                refreshPage();

                if (location.pathname !== '/notifikasi'
                    && typeof window.showToast === 'function') {
                    window.showToast(notification.pesan, 'info');
                }
            })
            .error(error => {
                setConnection('Gagal mengaktifkan channel notifikasi');
                console.warn('[Notifikasi] Otorisasi channel gagal.', error);
            });

        window.addEventListener('notifications:read', refreshCount);

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && sessionMatches()) {
                refreshCount();
                refreshPage();
            }
        });

        window.addEventListener('storage', event => {
            if (event.key === 'token' || event.key === null) {
                if (!sessionMatches()) disconnect();
            }
        });

        window.addEventListener('pagehide', disconnect, { once: true });

        await refreshCount();
    } catch (error) {
        setConnection('Notifikasi real-time belum terhubung');
        console.warn('[Notifikasi]', error.message);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startNotifications, {
        once: true,
    });
} else {
    startNotifications();
}