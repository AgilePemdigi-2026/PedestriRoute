<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notifikasi;
use App\Models\Warga;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Daftar notifikasi terbaru milik user yang login (warga atau admin).
     */
    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 30), 1), 50);

        $items = $this->ownQuery($request)
            ->orderByDesc('tanggal_kirim')
            ->orderByDesc('notifikasi_id')
            ->limit($limit)
            ->get()
            ->map(fn (Notifikasi $n) => $this->format($n));

        return response()->json([
            'data' => $items,
            'meta' => ['unread_count' => $this->countUnread($request)],
        ], 200);
    }

    /**
     * Hanya jumlah notifikasi yang belum dibaca (untuk badge bel).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['unread_count' => $this->countUnread($request)],
        ], 200);
    }

    /**
     * Tandai satu notifikasi sudah dibaca (hanya milik sendiri).
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notif = $this->ownQuery($request)->where('notifikasi_id', $id)->first();

        if (!$notif) {
            return response()->json([
                'error' => [
                    'code' => 'NOTIFICATION_NOT_FOUND',
                    'message' => 'Notifikasi tidak ditemukan.',
                ],
            ], 404);
        }

        if ($notif->status_baca !== 'SUDAH') {
            $notif->status_baca = 'SUDAH';
            $notif->save();
        }

        return response()->json(['data' => $this->format($notif)], 200);
    }

    /**
     * Tandai semua notifikasi milik sendiri sudah dibaca.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $this->ownQuery($request)
            ->where('status_baca', 'BELUM')
            ->update(['status_baca' => 'SUDAH']);

        return response()->json(['data' => ['updated' => $updated]], 200);
    }

    private function countUnread(Request $request): int
    {
        return $this->ownQuery($request)->where('status_baca', 'BELUM')->count();
    }

    /**
     * Query dasar yang selalu terkunci ke penerima (jenis + id) dari token yang dipakai,
     * jadi satu user tidak bisa membaca atau mengubah notifikasi orang lain.
     */
    private function ownQuery(Request $request): Builder
    {
        $user = $request->user();
        $isWarga = $user instanceof Warga;

        return Notifikasi::query()
            ->where('penerima_tipe', $isWarga ? 'WARGA' : 'ADMIN')
            ->where('penerima_id', $isWarga ? $user->warga_id : $user->admin_id);
    }

    private function format(Notifikasi $n): array
    {
        return [
            'notifikasi_id' => $n->notifikasi_id,
            'laporan_id' => $n->laporan_id,
            'tipe' => $n->tipe,
            'pesan' => $n->pesan,
            'status_baca' => $n->status_baca,
            'tanggal_kirim' => optional($n->tanggal_kirim)->toIso8601String(),
        ];
    }
}