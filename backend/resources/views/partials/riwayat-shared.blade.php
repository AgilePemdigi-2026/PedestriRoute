{{-- Modal detail laporan + helper JS, dipakai halaman riwayat warga & admin.
     Memakai GET /api/v1/reports/{id} (ReportController::show). Butuh variabel global `token`. --}}
<div id="detailModal" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-end justify-center" onclick="if(event.target===this)closeDetail()">
    <div class="bg-white w-full max-w-md rounded-t-3xl max-h-[88vh] overflow-y-auto p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-extrabold text-slate-900">Detail Laporan</h3>
            <button type="button" onclick="closeDetail()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 text-sm font-bold">✕</button>
        </div>
        <div id="detailBody" class="text-xs text-slate-700"></div>
    </div>
</div>

<script>
    const STATUS_META = {
        MENUNGGU_VALIDASI_ADMIN: { label: 'Menunggu', cls: 'bg-amber-50 text-amber-800 border-amber-100' },
        DIPROSES:                { label: 'Diproses', cls: 'bg-amber-50 text-amber-800 border-amber-100' },
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

    function closeDetail() {
        document.getElementById('detailModal').classList.add('hidden');
    }

    async function openDetail(id) {
        const modal = document.getElementById('detailModal');
        const body = document.getElementById('detailBody');
        body.innerHTML = '<p class="text-center text-slate-400 py-8">Memuat detail...</p>';
        modal.classList.remove('hidden');

        try {
            const res = await fetch(`/api/v1/reports/${id}`, {
                headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
            });
            const json = await res.json();
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
                    <span class="font-mono font-bold text-teal-800 bg-teal-50 px-2 py-0.5 rounded-md">${kodeLaporan(d.laporan_id)}</span>
                    ${statusBadge(d.status)}
                </div>
                ${d.foto_url ? `<img src="${esc(d.foto_url)}" alt="Foto laporan" class="w-full rounded-2xl mb-3 object-cover max-h-64">` : ''}
                <dl class="space-y-2 mb-3">
                    <div><dt class="text-[10px] uppercase font-bold text-slate-400">Kategori</dt><dd>${esc(d.kategori?.nama)}</dd></div>
                    <div><dt class="text-[10px] uppercase font-bold text-slate-400">Lokasi</dt><dd>${esc(d.lokasi?.alamat || d.lokasi?.nama_jalan || '-')}</dd></div>
                    <div><dt class="text-[10px] uppercase font-bold text-slate-400">Deskripsi</dt><dd class="bg-slate-50 rounded-xl p-2.5 italic">"${esc(d.deskripsi)}"</dd></div>
                    <div><dt class="text-[10px] uppercase font-bold text-slate-400">Tanggal lapor</dt><dd>${fmtDate(d.tanggal_lapor)}</dd></div>
                    ${d.tanggal_validasi ? `<div><dt class="text-[10px] uppercase font-bold text-slate-400">Tanggal validasi</dt><dd>${fmtDate(d.tanggal_validasi)}</dd></div>` : ''}
                    ${d.alasan_penolakan ? `<div><dt class="text-[10px] uppercase font-bold text-rose-500">Alasan penolakan</dt><dd class="bg-rose-50 rounded-xl p-2.5 text-rose-800">${esc(d.alasan_penolakan)}</dd></div>` : ''}
                </dl>
                ${riwayat ? `<h4 class="text-[10px] uppercase font-bold text-slate-400 mb-2">Riwayat status</h4><ul class="space-y-2.5">${riwayat}</ul>` : ''}
            `;
        } catch (e) {
            body.innerHTML = '<p class="text-center text-rose-600 py-8">Gagal terhubung ke server.</p>';
        }
    }
</script>
