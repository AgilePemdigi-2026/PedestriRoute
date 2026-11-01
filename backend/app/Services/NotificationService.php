<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Laporan;
use App\Models\Notifikasi;
use App\Support\MessageCatalog;

class NotificationService
{
    /**
     * Create a notification record (Sprint 1 DB stub).
     */
    public function create(
        string $penerimaTipe,
        int $penerimaId,
        string $tipe,
        string $pesan,
        ?int $laporanId = null
    ): Notifikasi {
        return Notifikasi::create([
            'penerima_tipe' => $penerimaTipe,
            'penerima_id' => $penerimaId,
            'tipe' => $tipe,
            'laporan_id' => $laporanId,
            'pesan' => $pesan,
            'status_baca' => 'BELUM',
            'tanggal_kirim' => now(),
        ]);
    }

    /**
     * Notify Warga that report is pending Admin validation (Stub F3 / F4).
     */
    public function notifyPendingAdmin(Laporan $laporan): void
    {
        $jalan = $laporan->lokasi->alamat ?? $laporan->lokasi->segmen->nama_jalan ?? 'Surabaya';
        $pesan = MessageCatalog::format(MessageCatalog::NOTIF_PENDING_ADMIN, ['jalan' => $jalan]);

        $this->create('WARGA', $laporan->warga_id, 'STATUS_LAPORAN', $pesan, $laporan->laporan_id);

        // Also notify all admins about new report in validation queue (FR-F10-06)
        $this->notifyAdminsQueue($laporan);
    }

    /**
     * Notify all Admins about report in validation queue.
     */
    public function notifyAdminsQueue(Laporan $laporan): void
    {
        $jalan = $laporan->lokasi->alamat ?? $laporan->lokasi->segmen->nama_jalan ?? 'Surabaya';
        $pesan = MessageCatalog::format(MessageCatalog::NOTIF_QUEUE_ADMIN, ['jalan' => $jalan]);

        $admins = Admin::all();
        foreach ($admins as $admin) {
            $this->create('ADMIN', $admin->admin_id, 'ANTREAN_VALIDASI', $pesan, $laporan->laporan_id);
        }
    }

    /**
     * Notify Warga that report was validated by Admin.
     */
    public function notifyValidated(Laporan $laporan): void
    {
        $jalan = $laporan->lokasi->alamat ?? $laporan->lokasi->segmen->nama_jalan ?? 'Surabaya';
        $pesan = MessageCatalog::format(MessageCatalog::NOTIF_VALIDATED, ['jalan' => $jalan]);

        $this->create('WARGA', $laporan->warga_id, 'STATUS_LAPORAN', $pesan, $laporan->laporan_id);
    }

    /**
     * Notify Warga that report was rejected by Admin.
     */
    public function notifyRejected(Laporan $laporan, string $alasan): void
    {
        $jalan = $laporan->lokasi->alamat ?? $laporan->lokasi->segmen->nama_jalan ?? 'Surabaya';
        $pesan = MessageCatalog::format(MessageCatalog::NOTIF_REJECTED, [
            'jalan' => $jalan,
            'alasan' => $alasan,
        ]);

        $this->create('WARGA', $laporan->warga_id, 'STATUS_LAPORAN', $pesan, $laporan->laporan_id);
    }

    /**
     * Notify Warga that repair is in progress (F9).
     */
    public function notifyRepairing(Laporan $laporan): void
    {
        $jalan = $laporan->lokasi->alamat ?? $laporan->lokasi->segmen->nama_jalan ?? 'Surabaya';
        $pesan = MessageCatalog::format(MessageCatalog::NOTIF_REPAIRING, ['jalan' => $jalan]);

        $this->create('WARGA', $laporan->warga_id, 'STATUS_LAPORAN', $pesan, $laporan->laporan_id);
    }

    /**
     * Notify Warga that repair is completed (F9).
     */
    public function notifyRepaired(Laporan $laporan): void
    {
        $jalan = $laporan->lokasi->alamat ?? $laporan->lokasi->segmen->nama_jalan ?? 'Surabaya';
        $pesan = MessageCatalog::format(MessageCatalog::NOTIF_REPAIRED, ['jalan' => $jalan]);

        $this->create('WARGA', $laporan->warga_id, 'STATUS_LAPORAN', $pesan, $laporan->laporan_id);
    }
}
