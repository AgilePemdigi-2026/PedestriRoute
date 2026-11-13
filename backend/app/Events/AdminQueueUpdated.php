<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class AdminQueueUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public array $data;

    public function __construct(int $laporanId, string $pesan)
    {
        $this->data = [
            'laporan_id' => $laporanId,
            'tipe' => 'ANTREAN_VALIDASI',
            'pesan' => $pesan,
            'tanggal_kirim' => now()->toIso8601String(),
        ];
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.reports')];
    }

    public function broadcastAs(): string
    {
        return 'admin.queue-updated';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}