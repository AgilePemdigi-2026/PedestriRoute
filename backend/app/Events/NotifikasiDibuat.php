<?php

namespace App\Events;

use App\Models\Notifikasi;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class NotifikasiDibuat implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public int $tries = 3;

    public function __construct(
        private string $penerimaTipe,
        private int $penerimaId,
        private array $payload,
    ) {}

    public static function fromNotifikasi(Notifikasi $notifikasi): self
    {
        return new self(
            $notifikasi->penerima_tipe,
            $notifikasi->penerima_id,
            [
                'notifikasi_id' => $notifikasi->notifikasi_id,
                'laporan_id' => $notifikasi->laporan_id,
                'tipe' => $notifikasi->tipe,
                'pesan' => $notifikasi->pesan,
                'status_baca' => $notifikasi->status_baca,
                'tanggal_kirim' => $notifikasi->tanggal_kirim?->toIso8601String(),
            ],
        );
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'notifications.'.strtolower($this->penerimaTipe).'.'.$this->penerimaId
            ),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notifikasi.dibuat';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }

    public function backoff(): array
    {
        return [5, 15, 30];
    }
}