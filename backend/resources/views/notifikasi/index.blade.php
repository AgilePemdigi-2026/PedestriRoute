@extends('layouts.app')

@section('title', 'Notifikasi - PedestriRoute')

@section('content')
<div class="flex-1 px-5 pt-6 pb-24">
    <header class="flex items-start justify-between gap-3 mb-5">
        <div>
            <h1 class="text-xl font-extrabold text-slate-900">
                Notifikasi
            </h1>
            <p id="notificationSummary"
                class="text-xs text-slate-500 mt-1"
                aria-live="polite">
                Memuat notifikasi...
            </p>
            <p data-notification-connection
                class="text-[11px] text-slate-400 mt-1"
                role="status">
                Menghubungkan notifikasi...
            </p>
        </div>

        <button id="refreshNotifications" type="button"
                class="rounded-xl border border-slate-200 px-3 py-2 text-xs
                       font-bold text-brand-600 hover:bg-brand-50
                       disabled:opacity-50">
            Muat ulang
        </button>
    </header>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <label class="text-xs font-semibold text-slate-600">
            Tampilkan
            <select id="notificationFilter"
                    class="ml-1 rounded-xl border border-slate-200 bg-white
                           px-3 py-2 text-xs focus:outline-none
                           focus:ring-2 focus:ring-brand-500">
                <option value="semua">Semua</option>
                <option value="belum">Belum dibaca</option>
            </select>
        </label>

        <button id="markAllRead" type="button" disabled
                class="text-xs font-bold text-brand-600
                       disabled:opacity-40">
            Tandai semua dibaca
        </button>
    </div>

    <p id="notificationMessage"
       class="hidden rounded-xl bg-slate-50 p-4 text-sm text-slate-600 mb-4"
       role="status"></p>

    <div id="notificationList" class="space-y-3"></div>

    <button id="loadMoreNotifications" type="button"
            class="hidden w-full mt-5 rounded-xl border border-brand-600
                   py-3 text-sm font-bold text-brand-600
                   hover:bg-brand-50 disabled:opacity-50">
        Muat lebih banyak
    </button>
</div>

<!-- Bottom Navigation for Citizen (Default) -->
<nav id="wargaBottomNav" class="fixed bottom-0 max-w-md w-full bg-white border-t border-slate-200 py-2 px-6 flex items-center justify-between z-40" aria-label="Navigasi Warga">
    <a href="/beranda-warga" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600 transition">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2L2 12h3v8h6v-6h2v6h6v-8h3L12 2z"/>
        </svg>
        <span class="text-[10px] tracking-tight">Peta</span>
    </a>
    <a href="/rute-pejalan-kaki" class="flex flex-col items-center gap-1 text-slate-400 hover:text-slate-600 transition">
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
    <a href="/notifikasi" aria-current="page" class="flex flex-col items-center gap-1 text-brand-600 font-bold transition">
        <span class="relative">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span data-notification-count aria-hidden="true" class="hidden absolute -top-1.5 -right-2 min-w-[16px] h-4 px-1 rounded-full bg-rose-600 text-white text-[9px] font-bold flex items-center justify-center"></span>
        </span>
        <span class="text-[10px] tracking-tight">Notifikasi</span>
    </a>
</nav>

<!-- Bottom Navigation for Admin -->
<nav id="adminBottomNav" class="hidden fixed bottom-0 max-w-md w-full bg-white border-t border-slate-200 px-2 pt-1.5 pb-2 flex items-stretch justify-around z-40" aria-label="Navigasi Admin">
    <a href="/admin/validasi" class="relative flex-1 flex flex-col items-center gap-0.5 pt-1.5 pb-0.5 text-slate-400 hover:text-slate-600 transition">
        <span class="w-9 h-6 flex items-center justify-center rounded-full">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </span>
        <span class="text-[10px] tracking-tight">Validasi</span>
    </a>
    <a href="/admin/riwayat" class="relative flex-1 flex flex-col items-center gap-0.5 pt-1.5 pb-0.5 text-slate-400 hover:text-slate-600 transition">
        <span class="w-9 h-6 flex items-center justify-center rounded-full">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </span>
        <span class="text-[10px] tracking-tight">Riwayat</span>
    </a>
    <a href="/admin/statistik" class="relative flex-1 flex flex-col items-center gap-0.5 pt-1.5 pb-0.5 text-slate-400 hover:text-slate-600 transition">
        <span class="w-9 h-6 flex items-center justify-center rounded-full">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
        </span>
        <span class="text-[10px] tracking-tight">Statistik</span>
    </a>
    <a href="/notifikasi" aria-current="page" class="relative flex-1 flex flex-col items-center gap-0.5 pt-1.5 pb-0.5 text-brand-600 font-bold transition">
        <span class="absolute -top-1.5 h-0.5 w-8 rounded-full bg-brand-600"></span>
        <span class="w-9 h-6 flex items-center justify-center rounded-full bg-brand-50">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
        </span>
        <span class="text-[10px] tracking-tight">Notifikasi</span>
        <span data-notification-count aria-hidden="true" class="hidden absolute top-0 right-2 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white text-[9px] font-bold flex items-center justify-center"></span>
    </a>
</nav>
@endsection

@push('scripts')
<script>
(async () => {
    const token = localStorage.getItem('token');

    if (!token) {
        window.location.replace('/login');
        return;
    }

    function syncNavigationRole(role) {
        const isAdmin = role === 'admin';
        const wargaNav = document.getElementById('wargaBottomNav');
        const adminNav = document.getElementById('adminBottomNav');
        if (isAdmin) {
            wargaNav?.classList.add('hidden');
            adminNav?.classList.remove('hidden');
        } else {
            wargaNav?.classList.remove('hidden');
            adminNav?.classList.add('hidden');
        }
    }

    // Immediately reflect cached role so navigation bar is never blank
    syncNavigationRole(localStorage.getItem('role'));

    const list = document.getElementById('notificationList');
    const summary = document.getElementById('notificationSummary');
    const message = document.getElementById('notificationMessage');
    const filter = document.getElementById('notificationFilter');
    const refreshButton = document.getElementById('refreshNotifications');
    const allReadButton = document.getElementById('markAllRead');
    const moreButton = document.getElementById('loadMoreNotifications');

    let currentPage = 0;
    let unreadCount = 0;
    let busy = false;
    let pendingLiveRefresh = false;

    async function api(path, method = 'GET') {
        const response = await fetch('/api/v1' + path, {
            method,
            headers: {
                Authorization: 'Bearer ' + token,
                Accept: 'application/json',
            },
        });

        if (response.status === 401) {
            window.location.replace('/login');
            throw new Error('Sesi login berakhir.');
        }

        const body = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(
                body.error?.message
                || body.message
                || 'Permintaan gagal. Silakan coba lagi.'
            );
        }

        return body;
    }

    function setBusy(value) {
        busy = value;
        filter.disabled = value;
        refreshButton.disabled = value;
        moreButton.disabled = value;
        allReadButton.disabled = value || unreadCount === 0;
    }

    function showMessage(text) {
        if (!text) {
            message.textContent = '';
            message.classList.add('hidden');
            return;
        }

        message.textContent = text;
        message.classList.remove('hidden');
    }

    function formatDate(raw) {
        if (!raw) return '-';

        const parsed = new Date(raw);
        if (Number.isNaN(parsed.getTime())) return raw;

        return parsed.toLocaleString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function renderItem(item) {
        const card = document.createElement('article');
        const unread = !item.dibaca;

        card.className = 'rounded-2xl border p-4 transition '
            + (unread
                ? 'border-brand-200 bg-brand-50/50 shadow-sm'
                : 'border-slate-200 bg-white');

        const heading = document.createElement('div');
        heading.className = 'flex items-start justify-between gap-3';

        const title = document.createElement('h2');
        title.className = 'text-sm font-bold text-slate-900';
        title.textContent = item.judul;

        heading.append(title);

        if (unread) {
            const badge = document.createElement('span');
            badge.className = 'inline-flex items-center rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white shrink-0';
            badge.textContent = 'Baru';
            heading.append(badge);
        }

        const text = document.createElement('p');
        text.className = 'mt-2 text-xs leading-relaxed text-slate-600';
        text.textContent = item.pesan;

        const time = document.createElement('p');
        time.className = 'mt-3 text-xs text-slate-500';
        time.textContent = formatDate(item.tanggal_kirim);

        card.append(heading, text, time);

        if (item.laporan_id) {
            const report = document.createElement('p');
            report.className = 'mt-2 text-xs font-semibold text-slate-500';
            report.textContent = '#LAP-' + String(item.laporan_id).padStart(4, '0');
            card.append(report);
        }

        if (unread) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'mt-3 text-xs font-bold text-brand-600 disabled:opacity-50';
            button.textContent = 'Tandai dibaca';

            button.addEventListener('click', async () => {
                if (busy) return;

                setBusy(true);

                try {
                    await api('/notifications/' + item.notifikasi_id + '/read', 'PATCH');
                    window.dispatchEvent(new Event('notifications:read'));
                } catch (error) {
                    showToast(error.message, 'error');
                    setBusy(false);
                    return;
                }

                setBusy(false);
                await loadNotifications(true);
            });

            card.append(button);
        }

        return card;
    }

    async function loadNotifications(reset = true) {
        if (busy) return;

        if (reset) {
            pendingLiveRefresh = false;
        }

        setBusy(true);
        showMessage(reset ? 'Memuat notifikasi...' : '');

        const page = reset ? 1 : currentPage + 1;
        const query = new URLSearchParams({
            filter: filter.value,
            page: String(page),
        });

        try {
            const result = await api('/notifications?' + query);

            if (reset) {
                list.replaceChildren();
            }

            result.data.forEach(item => list.append(renderItem(item)));

            currentPage = result.meta.current_page;
            unreadCount = result.meta.unread_count;

            summary.textContent = unreadCount > 0
                ? unreadCount + ' notifikasi belum dibaca'
                : 'Semua notifikasi sudah dibaca';

            moreButton.classList.toggle(
                'hidden',
                currentPage >= result.meta.last_page
            );

            showMessage(
                result.meta.total === 0
                    ? (filter.value === 'belum'
                        ? 'Tidak ada notifikasi yang belum dibaca.'
                        : 'Belum ada notifikasi untuk akun ini.')
                    : ''
            );
        } catch (error) {
            showMessage(error.message);
        } finally {
            setBusy(false);

            if (pendingLiveRefresh) {
                pendingLiveRefresh = false;
                queueMicrotask(() => loadNotifications(true));
            }
        }
    }

    refreshButton.addEventListener('click', () => loadNotifications(true));
    filter.addEventListener('change', () => loadNotifications(true));
    moreButton.addEventListener('click', () => loadNotifications(false));

    allReadButton.addEventListener('click', async () => {
        if (busy || unreadCount === 0) return;

        setBusy(true);

        try {
            await api('/notifications/read-all', 'PATCH');
            window.dispatchEvent(new Event('notifications:read'));
        } catch (error) {
            showToast(error.message, 'error');
            setBusy(false);
            return;
        }

        setBusy(false);
        await loadNotifications(true);
    });

    window.addEventListener('notifications:changed', () => {
        if (busy) {
            pendingLiveRefresh = true;
            return;
        }

        loadNotifications(true);
    });

    try {
        const result = await api('/auth/me');
        if (result?.data?.role) {
            localStorage.setItem('role', result.data.role);
            syncNavigationRole(result.data.role);
        }
        await loadNotifications(true);
    } catch (error) {
        summary.textContent = 'Gagal memuat akun';
        showMessage(error.message);
    }
})();
</script>
@endpush
