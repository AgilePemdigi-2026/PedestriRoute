<?php

namespace Tests\Feature\Notifications;

use App\Events\NotifikasiDibuat;
use App\Models\Notifikasi;
use App\Models\Warga;
use App\Services\NotificationService;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationBroadcastTest extends TestCase
{
    private ?Warga $warga = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDatabaseName() !== 'pedestriroute_testing') {
            throw new \RuntimeException('Jalankan tes pada pedestriroute_testing.');
        }

        Queue::fake();

        $this->warga = Warga::create([
            'nama' => 'Broadcast Test',
            'email' => uniqid('broadcast_').'@example.test',
            'password' => bcrypt('password123'),
        ]);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->warga) {
                while (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }

                Notifikasi::where('penerima_tipe', 'WARGA')
                    ->where('penerima_id', $this->warga->warga_id)
                    ->delete();

                $this->warga->delete();
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_broadcast_is_queued_only_after_commit(): void
    {
        DB::beginTransaction();

        $notification = app(NotificationService::class)->create(
            'WARGA',
            $this->warga->warga_id,
            'STATUS_LAPORAN',
            'Uji commit.'
        );

        Queue::assertNothingPushed();

        DB::commit();

        Queue::assertPushed(
            BroadcastEvent::class,
            function (BroadcastEvent $job) use ($notification): bool {
                if (!($job->event instanceof NotifikasiDibuat)) {
                    return false;
                }

                return $job->event->broadcastWith()['notifikasi_id']
                    === $notification->notifikasi_id;
            }
        );

        $this->assertDatabaseHas('notifikasi', [
            'notifikasi_id' => $notification->notifikasi_id,
        ]);
    }

    public function test_rollback_removes_notification_and_does_not_queue_broadcast(): void
    {
        DB::beginTransaction();

        $notification = app(NotificationService::class)->create(
            'WARGA',
            $this->warga->warga_id,
            'STATUS_LAPORAN',
            'Uji rollback.'
        );

        DB::rollBack();

        Queue::assertNothingPushed();

        $this->assertDatabaseMissing('notifikasi', [
            'notifikasi_id' => $notification->notifikasi_id,
        ]);
    }
}