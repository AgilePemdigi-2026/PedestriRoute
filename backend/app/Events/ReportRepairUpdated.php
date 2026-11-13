<?php

namespace App\Events;

use App\Models\Notifikasi;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ReportRepairUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public int $wargaId;
    public array $data;

    /**
     * @param string $fase 'SEDANG' (mulai diperbaiki) atau 'SELESAI' (sudah diperbaiki)
     */
    public function __construct(Notifikasi $notifikasi, string $fase)
    {
        $this->wargaId = $notifikasi->penerima_id;
        $this->data = [
            'notifikasi_id' => $notifikasi->notifikasi_id,
            'laporan_id' => $notifikasi->laporan_id,
            'tipe' => $notifikasi->tipe,
            'pesan' => $notifikasi->pesan,
            'fase' => $fase,
            'tanggal_kirim' => optional($notifikasi->tanggal_kirim)->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('warga.' . $this->wargaId),
            new Channel('laporan.perbaikan'), // channel publik, bisa didengar siapa saja
        ];
    }

    public function broadcastAs(): string
    {
        return 'report.repair-updated';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}