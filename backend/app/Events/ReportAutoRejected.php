<?php

namespace App\Events;

use App\Models\Notifikasi;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ReportAutoRejected implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public int $wargaId;
    public array $data;

    public function __construct(Notifikasi $notifikasi)
    {
        $this->wargaId = $notifikasi->penerima_id;
        $this->data = [
            'notifikasi_id' => $notifikasi->notifikasi_id,
            'laporan_id' => $notifikasi->laporan_id,
            'tipe' => $notifikasi->tipe,
            'pesan' => $notifikasi->pesan,
            'tanggal_kirim' => optional($notifikasi->tanggal_kirim)->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('warga.' . $this->wargaId)];
    }

    public function broadcastAs(): string
    {
        return 'report.auto-rejected';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}