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

<nav id="notificationNavigation"
     class="hidden fixed bottom-0 w-full max-w-md bg-white
            border-t border-slate-200 px-3 py-3 z-40"
     aria-label="Navigasi utama">
    <div class="flex items-center justify-around gap-2">
        <a id="notificationHome"
           class="text-xs font-semibold text-slate-500"></a>

        <a id="notificationSecondary"
           class="text-xs font-semibold text-slate-500"></a>

        <a id="notificationHistory"
           class="text-xs font-semibold text-slate-500">
            Riwayat
        </a>

        <a href="/notifikasi" aria-current="page"
           class="text-xs font-bold text-brand-600">
            Notifikasi
        </a>
    </div>
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
        list.setAttribute('aria-busy', String(value));

        list.querySelectorAll('button').forEach(button => {
            button.disabled = value;
        });
    }

    function showMessage(text) {
        message.textContent = text;
        message.classList.toggle('hidden', !text);
    }

    function formatDate(value) {
        const date = new Date(value);

        if (!value || Number.isNaN(date.getTime())) {
            return '';
        }

        return new Intl.DateTimeFormat('id-ID', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(date);
    }

    function renderItem(item) {
        const unread = item.status_baca === 'BELUM';

        const card = document.createElement('article');
        card.className = unread
            ? 'rounded-2xl border border-teal-200 bg-brand-50 p-4'
            : 'rounded-2xl border border-slate-200 bg-white p-4';

        const heading = document.createElement('div');
        heading.className = 'flex items-start justify-between gap-3';

        const title = document.createElement('p');
        title.className = 'text-xs font-bold text-brand-600';

        const titles = {
            STATUS_LAPORAN: 'Pembaruan laporan',
            ANTREAN_VALIDASI: 'Antrean validasi',
            KONDISI_JALUR: 'Kondisi jalur',
        };

        title.textContent = titles[item.tipe] || 'Notifikasi';

        const badge = document.createElement('span');
        badge.className = 'shrink-0 text-[10px] font-bold text-slate-500';
        badge.textContent = unread ? 'Baru' : 'Dibaca';

        heading.append(title, badge);

        const text = document.createElement('p');
        text.className = 'mt-2 text-sm leading-relaxed text-slate-800 break-words';
        text.textContent = item.pesan;

        const time = document.createElement('time');
        time.className = 'block mt-3 text-xs text-slate-500';
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
        const isAdmin = result.data.role === 'admin';

        const home = document.getElementById('notificationHome');
        home.href = isAdmin ? '/admin/validasi' : '/beranda-warga';
        home.textContent = isAdmin ? 'Validasi' : 'Peta';

        const secondary = document.getElementById('notificationSecondary');
        secondary.href = isAdmin ? '/admin/statistik' : '/lapor-kondisi';
        secondary.textContent = isAdmin ? 'Statistik' : 'Lapor';

        document.getElementById('notificationHistory').href = isAdmin
            ? '/admin/riwayat'
            : '/riwayat-laporan';

        document.getElementById('notificationNavigation')
            .classList.remove('hidden');

        await loadNotifications(true);
    } catch (error) {
        summary.textContent = 'Gagal memuat akun';
        showMessage(error.message);
    }
})();
</script>
@endpush