<?php

namespace Tests\Unit\Notifications;

use App\Events\NotifikasiDibuat;
use App\Models\Notifikasi;
use Tests\TestCase;

class NotifikasiDibuatTest extends TestCase
{
    public function test_event_uses_private_recipient_channel_and_expected_payload(): void
    {
        foreach (['WARGA', 'ADMIN'] as $type) {
            $notification = new Notifikasi();

            $notification->forceFill([
                'notifikasi_id' => 10,
                'penerima_tipe' => $type,
                'penerima_id' => 7,
                'laporan_id' => 25,
                'tipe' => 'STATUS_LAPORAN',
                'pesan' => 'Laporan diperbarui.',
                'status_baca' => 'BELUM',
                'tanggal_kirim' => '2026-11-14 12:00:00',
            ]);

            $event = NotifikasiDibuat::fromNotifikasi($notification);

            $this->assertSame(
                'private-notifications.'.strtolower($type).'.7',
                $event->broadcastOn()[0]->name
            );

            $this->assertSame('notifikasi.dibuat', $event->broadcastAs());

            $payload = $event->broadcastWith();

            $this->assertSame(10, $payload['notifikasi_id']);
            $this->assertSame(25, $payload['laporan_id']);
            $this->assertSame('BELUM', $payload['status_baca']);
            $this->assertArrayNotHasKey('penerima_id', $payload);

            // Payload harus tetap menjadi snapshot saat event dibuat.
            $notification->pesan = 'Pesan telah berubah.';

            $this->assertSame(
                'Laporan diperbarui.',
                $event->broadcastWith()['pesan']
            );
        }
    }
}