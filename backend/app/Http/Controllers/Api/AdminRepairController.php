<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Laporan;
use App\Services\NotificationService;
use App\Services\SegmentScoreService;
use App\Support\MessageCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Throwable;

class AdminRepairController extends Controller
{
    public function __construct(
        protected SegmentScoreService $segmentScoreService,
        protected NotificationService $notificationService,
    ) {}

    /**
     * Update repair status of a validated report (FR-F9-03, FR-F9-04, FR-F9-05).
     * Endpoint: PATCH /api/v1/admin/reports/{id}/repair-status
     */
    public function updateRepairStatus(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();

        // 1. Validate required input (FR-F9-06)
        if (!$request->has('status_perbaikan') || $request->input('status_perbaikan') === null || trim((string) $request->input('status_perbaikan')) === '') {
            return response()->json([
                'error' => [
                    'code' => 'REPAIR_STATUS_REQUIRED',
                    'message' => MessageCatalog::REPAIR_STATUS_REQUIRED,
                ]
            ], 422);
        }

        $newStatus = strtoupper(trim((string) $request->input('status_perbaikan')));
        $validStatuses = ['SEDANG_DIPERBAIKI', 'SUDAH_DIPERBAIKI'];

        if (!in_array($newStatus, $validStatuses, true)) {
            return response()->json([
                'error' => [
                    'code' => 'CONFLICT',
                    'message' => 'Status perbaikan tidak valid. Hanya menerima SEDANG_DIPERBAIKI atau SUDAH_DIPERBAIKI.',
                ]
            ], 409);
        }

        $catatanInput = trim((string) $request->input('catatan', $request->input('catatan_perbaikan', '')));
        $fotoPerbaikanPath = null;
        if ($request->hasFile('foto_perbaikan')) {
            $file = $request->file('foto_perbaikan');
            $filename = 'repair_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $fotoPerbaikanPath = $file->storeAs('reports', $filename, 'public');
        }

        $updatedReport = null;

        try {
            DB::transaction(function () use ($id, $admin, $newStatus, $catatanInput, $fotoPerbaikanPath, &$updatedReport) {
                // Concurrency lock: lockForUpdate() on the report row
                $lap = Laporan::with(['lokasi.segmen'])
                    ->where('laporan_id', $id)
                    ->lockForUpdate()
                    ->first();

                if (!$lap) {
                    throw new \RuntimeException('NOT_FOUND');
                }

                // Repair status can ONLY be applied to TERVALIDASI reports
                if ($lap->status !== 'TERVALIDASI') {
                    throw new \RuntimeException('NOT_VALIDATED');
                }

                $currentRepairStatus = $lap->status_perbaikan;

                // Validate allowed state machine transitions (SRS Section 4.1):
                // NULL -> SEDANG_DIPERBAIKI
                // NULL -> SUDAH_DIPERBAIKI
                // SEDANG_DIPERBAIKI -> SUDAH_DIPERBAIKI
                // SEDANG_DIPERBAIKI -> SEDANG_DIPERBAIKI (idempotent allow)
                // Any transition from SUDAH_DIPERBAIKI is prohibited (409)
                if ($currentRepairStatus === 'SUDAH_DIPERBAIKI') {
                    throw new \RuntimeException('CONFLICT_CANNOT_REVERT');
                }

                if ($currentRepairStatus === 'SEDANG_DIPERBAIKI' && $newStatus === 'SEDANG_DIPERBAIKI') {
                    // Already in this state, update note/photo if provided
                    if ($catatanInput !== '') {
                        $lap->catatan_perbaikan = $catatanInput;
                    }
                    if ($fotoPerbaikanPath) {
                        $lap->foto_perbaikan = $fotoPerbaikanPath;
                    }
                    $lap->save();
                    $updatedReport = $lap;
                    return;
                }

                $lap->status_perbaikan = $newStatus;
                if ($newStatus === 'SUDAH_DIPERBAIKI') {
                    $lap->tanggal_perbaikan = now();
                }
                if ($catatanInput !== '') {
                    $lap->catatan_perbaikan = $catatanInput;
                }
                if ($fotoPerbaikanPath) {
                    $lap->foto_perbaikan = $fotoPerbaikanPath;
                }
                $lap->save();

                // Insert into riwayat_status_laporan
                $catatanAudit = $catatanInput !== '' 
                    ? $catatanInput 
                    : ($newStatus === 'SUDAH_DIPERBAIKI'
                        ? 'Perbaikan fasilitas telah selesai dikerjakan dan diverifikasi lapangan.'
                        : 'Petugas sedang melakukan penanganan perbaikan fasilitas di lokasi.');

                DB::table('riwayat_status_laporan')->insert([
                    'laporan_id' => $lap->laporan_id,
                    'jenis' => 'PERBAIKAN',
                    'status' => $newStatus,
                    'admin_id' => $admin->admin_id,
                    'catatan' => $catatanAudit,
                    'waktu' => now(),
                ]);

                // If SUDAH_DIPERBAIKI, recalculate segment score (WSM) (FR-F9-04)
                if ($newStatus === 'SUDAH_DIPERBAIKI' && $lap->lokasi && $lap->lokasi->segmen_id) {
                    $this->segmentScoreService->recalculate($lap->lokasi->segmen_id);
                }

                // Notify Warga (FR-F9-05)
                if ($newStatus === 'SEDANG_DIPERBAIKI') {
                    $this->notificationService->notifyRepairing($lap);
                } elseif ($newStatus === 'SUDAH_DIPERBAIKI') {
                    $this->notificationService->notifyRepaired($lap);
                }

                $updatedReport = $lap;
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'NOT_FOUND') {
                return response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Laporan tidak ditemukan.',
                    ]
                ], 404);
            }
            if ($e->getMessage() === 'NOT_VALIDATED') {
                return response()->json([
                    'error' => [
                        'code' => 'CONFLICT',
                        'message' => 'Status perbaikan hanya dapat diperbarui untuk laporan yang telah berstatus TERVALIDASI.',
                    ]
                ], 409);
            }
            if ($e->getMessage() === 'CONFLICT_CANNOT_REVERT') {
                return response()->json([
                    'error' => [
                        'code' => 'CONFLICT',
                        'message' => 'Transisi status perbaikan tidak diizinkan. Laporan yang sudah selesai diperbaiki tidak dapat diubah lagi.',
                    ]
                ], 409);
            }
            throw $e;
        } catch (Throwable $e) {
            return response()->json([
                'error' => [
                    'code' => 'REPAIR_SAVE_FAILED',
                    'message' => MessageCatalog::REPAIR_SAVE_FAILED,
                ]
            ], 500);
        }

        $photoRepairUrl = null;
        if ($updatedReport->foto_perbaikan) {
            $photoRepairUrl = URL::temporarySignedRoute(
                'report.photo',
                now()->addMinutes(60),
                ['id' => $updatedReport->laporan_id, 'type' => 'repair']
            );
        }

        return response()->json([
            'data' => [
                'laporan_id' => $updatedReport->laporan_id,
                'status' => $updatedReport->status,
                'status_perbaikan' => $updatedReport->status_perbaikan,
                'catatan_perbaikan' => $updatedReport->catatan_perbaikan,
                'foto_perbaikan_url' => $photoRepairUrl,
                'tanggal_perbaikan' => $updatedReport->tanggal_perbaikan?->toIso8601String(),
            ],
            'message' => MessageCatalog::REPAIR_STATUS_SAVED,
        ], 200);
    }
}
