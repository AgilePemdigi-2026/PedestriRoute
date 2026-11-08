{{-- Modal detail laporan + helper JS, dipakai halaman riwayat warga & admin.
     Memakai GET /api/v1/reports/{id} (ReportController::show). Butuh variabel global `token`. --}}
<style>
    #detailModal {
        position: fixed;
        inset: 0;
        z-index: 50;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        overflow: hidden;
        overscroll-behavior: none;
        padding-top: 12px;
        box-sizing: border-box;
    }
    #detailModal.hidden { display: none; }
    #detailModal .detail-dialog {
        display: flex;
        flex-direction: column;
        width: 100%;
        max-width: 28rem;
        max-height: 90vh;
        max-height: 90dvh;
        min-height: 0;
        overflow: hidden;
        background: white;
        border-radius: 1.5rem 1.5rem 0 0;
        box-shadow: 0 -12px 40px rgb(15 23 42 / 18%);
    }
    #detailModal .detail-header,
    #detailModal .detail-footer { flex: 0 0 auto; background: white; padding: 16px 20px; }
    #detailModal .detail-header { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; }
    #detailModal .detail-footer { border-top: 1px solid #f1f5f9; padding-bottom: max(16px, env(safe-area-inset-bottom)); }
    #detailScroll {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        overscroll-behavior-y: contain;
        -webkit-overflow-scrolling: touch;
        touch-action: pan-y pinch-zoom;
        scrollbar-width: thin;
        scrollbar-color: #94a3b8 transparent;
        scrollbar-gutter: stable;
        padding: 16px 20px;
        overflow-wrap: anywhere;
    }
    #detailScroll::-webkit-scrollbar { width: 6px; }
    #detailScroll::-webkit-scrollbar-track { background: transparent; }
    #detailScroll::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 999px; }
    #detailScroll::-webkit-scrollbar-thumb:hover { background: #64748b; }
    #detailModal .detail-close-action {
        display: block; width: 100%; padding: 10px 16px;
        background: #f1f5f9; color: #334155; border-radius: 12px;
        font-size: 12px; font-weight: 700;
    }
    #detailModal button:focus-visible,
    #detailScroll:focus-visible { outline: 2px solid #008775; outline-offset: -2px; }
</style>
<div id="detailModal" class="hidden bg-slate-900/60 backdrop-blur-sm" aria-hidden="true" onclick="if(event.target===this)closeDetail()">
    <div class="detail-dialog" role="dialog" aria-modal="true" aria-labelledby="detailTitle" tabindex="-1">
        <header class="detail-header">
            <h3 id="detailTitle" class="text-sm font-extrabold text-slate-900">Detail Laporan</h3>
            <button id="detailCloseTop" type="button" onclick="closeDetail()" aria-label="Tutup detail laporan" class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 text-sm font-bold">✕</button>
        </header>
        <div id="detailScroll" tabindex="0" aria-label="Isi detail laporan">
            <div id="detailBody" class="text-xs text-slate-700"></div>
        </div>
        <footer class="detail-footer">
            <button type="button" onclick="closeDetail()" class="detail-close-action">Tutup</button>
        </footer>
    </div>
</div>

<script>
    const STATUS_META = {
        MENUNGGU_VALIDASI_ADMIN: { label: 'Menunggu', cls: 'bg-amber-50 text-amber-800 border-amber-100' },
        DIPROSES:                { label: 'Dianalisis AI', cls: 'bg-sky-50 text-sky-800 border-sky-200' },
        TERVALIDASI:             { label: 'Valid',    cls: 'bg-emerald-50 text-emerald-800 border-emerald-100' },
        DITOLAK:                 { label: 'Ditolak',  cls: 'bg-rose-50 text-rose-800 border-rose-100' },
    };

    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function statusBadge(status) {
        const m = STATUS_META[status] || { label: status, cls: 'bg-slate-50 text-slate-700 border-slate-200' };
        return `<span class="px-2 py-0.5 rounded-full border text-[10px] font-bold ${m.cls}">${esc(m.label)}</span>`;
    }

    function fmtDate(iso) {
        if (!iso) return '-';
        return new Date(iso).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function kodeLaporan(id) {
        return '#LAP-' + String(id).padStart(4, '0');
    }

    const detailModal = document.getElementById('detailModal');
    const detailScroll = document.getElementById('detailScroll');
    let detailScrollLock = null;
    let detailRequest = null;

    function lockDetailBackground() {
        if (detailScrollLock) return;
        const body = document.body;
        const root = document.documentElement;
        const properties = ['position', 'top', 'left', 'width', 'overflow', 'padding-right', 'box-sizing'];
        detailScrollLock = {
            x: window.scrollX, y: window.scrollY,
            focus: document.activeElement,
            bodyStyles: properties.map(name => [name, body.style.getPropertyValue(name), body.style.getPropertyPriority(name)]),
            rootOverflow: [root.style.getPropertyValue('overflow'), root.style.getPropertyPriority('overflow')],
            hadOverflowClass: body.classList.contains('overflow-hidden')
        };
        const gap = window.innerWidth - root.clientWidth;
        const padding = parseFloat(getComputedStyle(body).paddingRight) || 0;
        body.classList.add('overflow-hidden');
        body.style.position = 'fixed';
        body.style.top = `-${detailScrollLock.y}px`;
        body.style.left = `-${detailScrollLock.x}px`;
        body.style.width = '100%';
        body.style.boxSizing = 'border-box';
        body.style.overflow = 'hidden';
        if (gap > 0) body.style.paddingRight = `${padding + gap}px`;
        root.style.overflow = 'hidden';
    }

    function closeDetail() {
        if (detailRequest) { detailRequest.abort(); detailRequest = null; }
        detailModal.classList.add('hidden');
        detailModal.setAttribute('aria-hidden', 'true');
        if (!detailScrollLock) return;
        const state = detailScrollLock;
        detailScrollLock = null;
        for (const [name, value, priority] of state.bodyStyles) {
            if (value) document.body.style.setProperty(name, value, priority);
            else document.body.style.removeProperty(name);
        }
        if (!state.hadOverflowClass) document.body.classList.remove('overflow-hidden');
        const root = document.documentElement;
        if (state.rootOverflow[0]) root.style.setProperty('overflow', ...state.rootOverflow);
        else root.style.removeProperty('overflow');
        if (state.focus?.isConnected) state.focus.focus({preventScroll: true});
        // Override smooth-scroll only while restoring the exact background position.
        const behavior = [root.style.getPropertyValue('scroll-behavior'), root.style.getPropertyPriority('scroll-behavior')];
        root.style.setProperty('scroll-behavior', 'auto', 'important');
        window.scrollTo(state.x, state.y);
        if (behavior[0]) root.style.setProperty('scroll-behavior', ...behavior);
        else root.style.removeProperty('scroll-behavior');
    }

    function detailCanScroll(target, delta) {
        let node = target instanceof Element ? target : target.parentElement;
        while (node && detailScroll.contains(node)) {
            const style = getComputedStyle(node);
            if (/(auto|scroll)/.test(style.overflowY) && node.scrollHeight > node.clientHeight) {
                if (delta < 0 && node.scrollTop > 0) return true;
                if (delta > 0 && node.scrollTop + node.clientHeight < node.scrollHeight - 1) return true;
            }
            node = node.parentElement;
        }
        return false;
    }

    detailModal.addEventListener('wheel', event => {
        if (event.ctrlKey) return; // Preserve browser pinch/zoom gestures.
        const delta = event.deltaY * (event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? detailScroll.clientHeight : 1);
        if (detailScroll.contains(event.target)) {
            if (!detailCanScroll(event.target, delta)) event.preventDefault();
        } else {
            event.preventDefault();
            // Wheel over the fixed header/footer still scrolls the detail body.
            if (event.target !== detailModal) detailScroll.scrollTop += delta;
        }
    }, {passive: false});

    let detailTouchY = null;
    detailModal.addEventListener('touchstart', event => {
        detailTouchY = event.touches.length === 1 ? event.touches[0].clientY : null;
    }, {passive: true});
    detailModal.addEventListener('touchmove', event => {
        if (detailTouchY === null || event.touches.length !== 1) return;
        const y = event.touches[0].clientY;
        const delta = detailTouchY - y;
        detailTouchY = y;
        if (!detailScroll.contains(event.target) || !detailCanScroll(event.target, delta)) event.preventDefault();
    }, {passive: false});

    document.addEventListener('keydown', event => {
        if (!detailScrollLock) return;
        if (event.key === 'Escape') { event.preventDefault(); closeDetail(); return; }
        if (event.key !== 'Tab') return;
        const controls = [...detailModal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex="0"]')]
            .filter(node => node.getClientRects().length);
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });

    async function openDetail(id) {
        const modal = document.getElementById('detailModal');
        const body = document.getElementById('detailBody');
        body.innerHTML = '<p class="text-center text-slate-400 py-8">Memuat detail...</p>';
        if (detailRequest) detailRequest.abort();
        const request = new AbortController();
        detailRequest = request;
        lockDetailBackground();
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        detailScroll.scrollTop = 0;
        document.getElementById('detailCloseTop').focus({preventScroll: true});

        try {
            const res = await fetch(`/api/v1/reports/${id}`, {
                headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' },
                signal: request.signal
            });
            const json = await res.json();
            if (detailRequest !== request) return;
            if (!res.ok) {
                body.innerHTML = `<p class="text-center text-rose-600 py-8">${esc(json.error?.message || 'Gagal memuat detail')}</p>`;
                return;
            }
            const d = json.data;
            const riwayat = (d.riwayat || []).map(r => `
                <li class="flex gap-2">
                    <span class="mt-1 w-2 h-2 rounded-full bg-brand-600 shrink-0"></span>
                    <div>
                        <p class="font-semibold text-slate-800">${esc(STATUS_META[r.status]?.label || r.status)} <span class="font-normal text-slate-400">· ${esc(r.admin_nama)}</span></p>
                        <p class="text-slate-500">${esc(r.catatan)}</p>
                        <p class="text-[10px] text-slate-400">${fmtDate(r.waktu)}</p>
                    </div>
                </li>`).join('');

            body.innerHTML = `
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-1.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold text-brand-700 bg-brand-50 border border-brand-200/60 tracking-tight tabular-nums">${kodeLaporan(d.laporan_id)}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium text-slate-600 bg-slate-100">${esc(d.kategori?.nama || '-')}</span>
                    </div>
                    ${statusBadge(d.status)}
                </div>
                ${d.foto_url ? `<img src="${esc(d.foto_url)}" alt="Foto laporan" class="w-full rounded-2xl mb-3 object-cover max-h-56 shadow-sm border border-slate-100">` : ''}
                ${d.status === 'DIPROSES' ? `
                    <a href="/laporan/${d.laporan_id}/analisis" class="w-full mb-3 py-2.5 px-3 bg-brand-600 hover:bg-brand-700 text-white rounded-xl text-center text-xs font-bold flex items-center justify-center gap-1.5 transition shadow-sm animate-pulse">
                        <i class="fa-solid fa-radar"></i>
                        <span>Lihat Analisis ML Langsung</span>
                    </a>
                ` : ''}
                ${(d.confidence_score !== null && d.confidence_score !== undefined) ? `
                    <div class="mb-3 p-3 rounded-2xl ${d.status === 'TERVALIDASI' ? 'bg-emerald-50 border border-emerald-200 text-emerald-950' : (d.cv_label === 'SPAM' ? 'bg-rose-50 border border-rose-200 text-rose-950' : 'bg-amber-50 border border-amber-200 text-amber-950')} space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-extrabold uppercase tracking-wide flex items-center gap-1.5">
                                <i class="fa-solid fa-robot text-brand-600"></i> Verifikasi ML (YOLOv8)
                            </span>
                            <span class="text-[11px] font-extrabold px-2 py-0.5 rounded-full ${d.status === 'TERVALIDASI' ? 'bg-emerald-100 text-emerald-800' : (d.cv_label === 'SPAM' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800')}">
                                ${Math.round(d.confidence_score * 100)}% Keyakinan
                            </span>
                        </div>
                        <p class="text-xs font-semibold">
                            Hasil Prediksi: <span class="font-bold">${esc(d.cv_label === 'BAIK' ? 'BAIK (Kondisi Normal)' : (d.cv_label === 'SPAM' ? 'SPAM (Non-Fasilitas)' : (d.cv_label === 'TERHALANG' ? 'TERHALANG (Parkir Liar)' : 'RUSAK (Cacat Fisik)')))}</span>
                        </p>
                        ${d.cv_label === 'BAIK' ? `
                            <p class="text-[11px] text-amber-800 pt-0.5">ℹ️ Fasilitas terdeteksi dalam kondisi baik/tidak rusak. Menunggu tinjauan kepastian admin.</p>
                        ` : ''}
                        ${d.cv_label === 'SPAM' ? `
                            <p class="text-[11px] text-rose-800 pt-0.5">⚠️ Foto terindikasi bukan trotoar/fasilitas publik. Menunggu verifikasi admin.</p>
                        ` : ''}
                    </div>
                ` : ''}
                ${d.foto_perbaikan_url ? `
                    <div class="mb-3 p-2.5 bg-slate-50 border border-slate-200 rounded-2xl">
                        <span class="text-[10px] font-bold ${d.status_perbaikan === 'SUDAH_DIPERBAIKI' ? 'text-emerald-700' : 'text-sky-700'} uppercase tracking-wider block mb-1">
                            ${d.status_perbaikan === 'SUDAH_DIPERBAIKI' ? '✓ Bukti Perbaikan Selesai' : '⚡ Bukti Pengerjaan'}
                        </span>
                        <img src="${esc(d.foto_perbaikan_url)}" alt="Foto perbaikan" class="w-full rounded-xl object-cover max-h-52">
                    </div>
                ` : ''}
                <div class="space-y-2 mb-3">
                    <div class="bg-slate-50 rounded-xl p-3">
                        <p class="text-[11px] font-bold text-slate-800 flex items-center gap-1 mb-1">
                            <svg class="w-3.5 h-3.5 text-brand-600 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                            <span>${esc(d.lokasi?.alamat || d.lokasi?.nama_jalan || 'Lokasi tidak tersedia')}</span>
                        </p>
                        <p class="text-xs text-slate-600 leading-relaxed italic">"${esc(d.deskripsi)}"</p>
                    </div>
                    ${d.alasan_penolakan ? `
                        <div class="bg-rose-50 border border-rose-100 rounded-xl p-3 text-rose-800">
                            <span class="text-[10px] uppercase font-bold text-rose-600 block mb-0.5">Alasan Penolakan</span>
                            <p class="text-xs">${esc(d.alasan_penolakan)}</p>
                        </div>
                    ` : ''}
                    <div class="flex items-center justify-between text-[11px] text-slate-400 px-1 pt-1">
                        <span>Dilaporkan: ${fmtDate(d.tanggal_lapor)}</span>
                        ${d.tanggal_validasi ? `<span>Divalidasi: ${fmtDate(d.tanggal_validasi)}</span>` : ''}
                    </div>
                </div>
                ${riwayat ? `<div class="pt-2 border-t border-slate-100"><h4 class="text-[11px] font-bold text-slate-700 mb-2.5">Riwayat Status</h4><ul class="space-y-2.5">${riwayat}</ul></div>` : ''}
            `;
        } catch (e) {
            if (e.name === 'AbortError' || detailRequest !== request) return;
            body.innerHTML = '<p class="text-center text-rose-600 py-8">Gagal terhubung ke server.</p>';
        }
    }
</script>
