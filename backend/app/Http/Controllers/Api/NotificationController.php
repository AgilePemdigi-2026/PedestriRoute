<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Notifikasi;
use App\Models\Warga;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private function owned(Request $request): Builder
    {
        $user = $request->user();

        abort_unless(
            $user instanceof Warga || $user instanceof Admin,
            403
        );

        return Notifikasi::query()
            ->where('penerima_tipe', $user instanceof Warga ? 'WARGA' : 'ADMIN')
            ->where('penerima_id', $user->getAuthIdentifier());
    }

    public function index(Request $request): JsonResponse
    {
        $input = $request->validate([
            'filter' => ['sometimes', 'in:semua,belum'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = $this->owned($request);

        $unreadCount = (clone $query)
            ->where('status_baca', 'BELUM')
            ->count();

        if (($input['filter'] ?? 'semua') === 'belum') {
            $query->where('status_baca', 'BELUM');
        }

        $notifications = $query
            ->select([
                'notifikasi_id',
                'laporan_id',
                'tipe',
                'pesan',
                'status_baca',
                'tanggal_kirim',
            ])
            ->orderByDesc('tanggal_kirim')
            ->orderByDesc('notifikasi_id')
            ->paginate(20);

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = $this->owned($request)
            ->where('notifikasi_id', $id)
            ->firstOrFail();

        if ($notification->status_baca !== 'SUDAH') {
            $notification->update(['status_baca' => 'SUDAH']);
        }

        return response()->json([
            'data' => [
                'notifikasi_id' => $notification->notifikasi_id,
                'status_baca' => 'SUDAH',
            ],
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $this->owned($request)
            ->where('status_baca', 'BELUM')
            ->update(['status_baca' => 'SUDAH']);

        return response()->json([
            'data' => ['updated_count' => $updated],
        ]);
    }
}