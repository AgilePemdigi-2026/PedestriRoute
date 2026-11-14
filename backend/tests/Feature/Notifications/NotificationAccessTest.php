<?php

namespace Tests\Feature\Notifications;

use App\Models\Admin;
use App\Models\Notifikasi;
use App\Models\Warga;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationAccessTest extends TestCase
{
    use DatabaseTransactions;

    private Warga $warga;
    private Warga $other;
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDatabaseName() !== 'pedestriroute_testing') {
            throw new \RuntimeException('Jalankan tes pada pedestriroute_testing.');
        }

        Queue::fake();

        $this->warga = $this->createWarga();
        $this->other = $this->createWarga();

        $this->admin = Admin::create([
            'nama' => 'Admin Test',
            'email' => uniqid('admin_').'@example.test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'testing-key',
            'broadcasting.connections.reverb.secret' => 'testing-secret',
            'broadcasting.connections.reverb.app_id' => 'testing-app',
            'broadcasting.connections.reverb.options.host' => 'localhost',
            'broadcasting.connections.reverb.options.port' => 8080,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);

        require base_path('routes/channels.php');
    }

    private function createWarga(): Warga
    {
        return Warga::create([
            'nama' => 'Warga Test',
            'email' => uniqid('warga_').'@example.test',
            'password' => bcrypt('password123'),
        ]);
    }

    private function notification(
        string $type,
        int $recipient,
        string $read = 'BELUM'
    ): Notifikasi {
        return Notifikasi::create([
            'penerima_tipe' => $type,
            'penerima_id' => $recipient,
            'tipe' => 'STATUS_LAPORAN',
            'pesan' => 'Notifikasi pengujian',
            'status_baca' => $read,
            'tanggal_kirim' => now(),
        ]);
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();

        $this->patchJson('/api/v1/notifications/read-all')
            ->assertUnauthorized();

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-notifications.warga.1',
        ])->assertUnauthorized();
    }

    public function test_warga_only_sees_own_notifications(): void
    {
        $own = $this->notification('WARGA', $this->warga->warga_id);

        $this->notification('WARGA', $this->other->warga_id);

        // ID angka sama tetap tidak boleh membuka notifikasi tipe ADMIN.
        $this->notification('ADMIN', $this->warga->warga_id);

        Sanctum::actingAs($this->warga);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.notifikasi_id', $own->notifikasi_id)
            ->assertJsonPath('meta.unread_count', 1);
    }

    public function test_admin_only_sees_own_notifications(): void
    {
        $own = $this->notification('ADMIN', $this->admin->admin_id);
        $this->notification('WARGA', $this->admin->admin_id);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.notifikasi_id', $own->notifikasi_id);
    }

    public function test_unread_filter_and_mark_read_are_idempotent(): void
    {
        $own = $this->notification('WARGA', $this->warga->warga_id);
        $this->notification('WARGA', $this->warga->warga_id, 'SUDAH');

        Sanctum::actingAs($this->warga);

        $this->getJson('/api/v1/notifications?filter=belum')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $url = "/api/v1/notifications/{$own->notifikasi_id}/read";

        $this->patchJson($url)
            ->assertOk()
            ->assertJsonPath('data.status_baca', 'SUDAH');

        $this->patchJson($url)->assertOk();

        $this->getJson('/api/v1/notifications?filter=belum')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.unread_count', 0);
    }

    public function test_cannot_mark_another_accounts_notification_read(): void
    {
        $other = $this->notification('WARGA', $this->other->warga_id);
        $admin = $this->notification('ADMIN', $this->warga->warga_id);

        Sanctum::actingAs($this->warga);

        foreach ([$other, $admin] as $notification) {
            $this->patchJson(
                "/api/v1/notifications/{$notification->notifikasi_id}/read"
            )->assertNotFound();

            $this->assertSame('BELUM', $notification->fresh()->status_baca);
        }
    }

    public function test_mark_all_only_updates_own_notifications(): void
    {
        $own = $this->notification('WARGA', $this->warga->warga_id);
        $other = $this->notification('WARGA', $this->other->warga_id);
        $admin = $this->notification('ADMIN', $this->warga->warga_id);

        Sanctum::actingAs($this->warga);

        $this->patchJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated_count', 1);

        $this->assertSame('SUDAH', $own->fresh()->status_baca);
        $this->assertSame('BELUM', $other->fresh()->status_baca);
        $this->assertSame('BELUM', $admin->fresh()->status_baca);

        $this->patchJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated_count', 0);
    }

    public function test_pagination_and_invalid_filter(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->notification('WARGA', $this->warga->warga_id);
        }

        Sanctum::actingAs($this->warga);

        $first = $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 21);

        $second = $this->getJson('/api/v1/notifications?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertNotContains(
            $second->json('data.0.notifikasi_id'),
            array_column($first->json('data'), 'notifikasi_id')
        );

        $this->getJson('/api/v1/notifications?filter=invalid')
            ->assertUnprocessable();
    }

    public function test_private_channels_enforce_account_and_role(): void
    {
        Sanctum::actingAs($this->warga);

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-notifications.warga.'.$this->warga->warga_id,
        ])->assertOk()->assertJsonStructure(['auth']);

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-notifications.warga.'.$this->other->warga_id,
        ])->assertForbidden();

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-notifications.admin.'.$this->warga->warga_id,
        ])->assertForbidden();

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-notifications.admin.'.$this->admin->admin_id,
        ])->assertOk()->assertJsonStructure(['auth']);
    }

    private function report(string $status): int
    {
        $category = DB::table('kategori_masalah')->insertGetId([
            'nama_kategori' => uniqid('Kategori notifikasi '),
            'bobot' => 0.4,
        ], 'kategori_id');

        $location = DB::table('lokasi')->insertGetId([
            'latitude' => -7.2663,
            'longitude' => 112.7965,
            'geom' => DB::raw(
                'ST_SetSRID(ST_Point(112.7965,-7.2663),4326)::geography'
            ),
            'alamat' => 'Lokasi uji notifikasi',
        ], 'lokasi_id');

        return DB::table('laporan')->insertGetId([
            'warga_id' => $this->warga->warga_id,
            'kategori_id' => $category,
            'lokasi_id' => $location,
            'deskripsi' => 'Laporan pengujian notifikasi',
            'status' => $status,
            'tanggal_lapor' => now(),
        ], 'laporan_id');
    }

    public function test_validation_and_repair_updates_create_notifications(): void
    {
        $report = $this->report('MENUNGGU_VALIDASI_ADMIN');

        Sanctum::actingAs($this->admin, ['role:admin']);

        $this->postJson("/api/v1/admin/reports/{$report}/validation", [
            'keputusan' => 'VALID',
        ])->assertOk();

        $this->patchJson("/api/v1/admin/reports/{$report}/repair-status", [
            'status_perbaikan' => 'SEDANG_DIPERBAIKI',
        ])->assertOk();

        // Pengulangan status yang sama tidak boleh membuat notifikasi tambahan.
        $this->patchJson("/api/v1/admin/reports/{$report}/repair-status", [
            'status_perbaikan' => 'SEDANG_DIPERBAIKI',
        ])->assertOk();

        $this->patchJson("/api/v1/admin/reports/{$report}/repair-status", [
            'status_perbaikan' => 'SUDAH_DIPERBAIKI',
        ])->assertOk();

        $this->assertSame(3, Notifikasi::query()
            ->where('laporan_id', $report)
            ->where('penerima_tipe', 'WARGA')
            ->where('penerima_id', $this->warga->warga_id)
            ->count());
    }

    public function test_rejection_notifies_report_owner(): void
    {
        $report = $this->report('MENUNGGU_VALIDASI_ADMIN');

        Sanctum::actingAs($this->admin, ['role:admin']);

        $this->postJson("/api/v1/admin/reports/{$report}/validation", [
            'keputusan' => 'TIDAK_VALID',
            'alasan_penolakan' => 'Bukti tidak sesuai.',
        ])->assertOk();

        $this->assertDatabaseHas('notifikasi', [
            'laporan_id' => $report,
            'penerima_tipe' => 'WARGA',
            'penerima_id' => $this->warga->warga_id,
            'status_baca' => 'BELUM',
        ]);
    }
}