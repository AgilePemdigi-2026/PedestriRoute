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

class AdminValidationController extends Controller
{
    public function __construct(
        protected SegmentScoreService $segmentScoreService,
        protected NotificationService $notificationService,
    ) {}

    /**
     * Get validation queue / history for admin (FR-F5-01).
     * Query ?status=A,B (default: MENUNGGU_VALIDASI_ADMIN).
     */
    public function index(Request $request): JsonResponse
    {
        $allowed = ['DIPROSES', 'MENUNGGU_VALIDASI_ADMIN', 'TERVALIDASI', 'DITOLAK'];
        $statuses = array_values(array_intersect(
            array_filter(array_map('trim', explode(',', strtoupper((string) $request->query('status', 'MENUNGGU_VALIDASI_ADMIN'))))),
            $allowed
        ));
        if (! $statuses) {
            $statuses = ['MENUNGGU_VALIDASI_ADMIN'];
        }
        $isQueue = $statuses === ['MENUNGGU_VALIDASI_ADMIN'];

        $repairFilter = strtoupper(trim((string) $request->query('status_perbaikan', $request->query('repair_status', ''))));

        $reportsQuery = Laporan::with(['kategori', 'lokasi.segmen', 'foto', 'warga'])
            ->whereIn('status', $statuses);

        if ($repairFilter === 'BELUM' || $repairFilter === 'NULL') {
            $reportsQuery->whereNull('status_perbaikan');
        } elseif (in_array($repairFilter, ['SEDANG_DIPERBAIKI', 'SUDAH_DIPERBAIKI'], true)) {
            $reportsQuery->where('status_perbaikan', $repairFilter);
        }

        $reports = $reportsQuery
            ->when(
                $isQueue,
                fn ($q) => $q->orderBy('tanggal_lapor', 'asc'),
                fn ($q) => $q->orderByDesc('tanggal_validasi')
            )
            ->get();

        $data = $reports->map(function ($lap) {
            return [
                'laporan_id' => $lap->laporan_id,
                'kode_laporan' => '#LAP-' . str_pad($lap->laporan_id, 4, '0', STR_PAD_LEFT),
                'status' => $lap->status,
                'status_perbaikan' => $lap->status_perbaikan,
                'kategori' => $lap->kategori->nama_kategori,
                'deskripsi' => $lap->deskripsi,
                'alamat' => $lap->lokasi->alamat ?? $lap->lokasi->segmen->nama_jalan ?? 'Surabaya',
                'pelapor_nama' => $lap->warga->nama ?? 'Warga',
                'tanggal_lapor' => $lap->tanggal_lapor?->toIso8601String(),
                'tanggal_validasi' => $lap->tanggal_validasi?->toIso8601String(),
                'tanggal_perbaikan' => $lap->tanggal_perbaikan?->toIso8601String(),
                'alasan_penolakan' => $lap->alasan_penolakan,
                'cv_label' => $lap->cv_label,
                'confidence_score' => $lap->confidence_score,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $reports->count(),
            ]
        ], 200);
    }

    /**
     * Get aggregated statistics for admin dashboard.
     */
    public function statistics(Request $request): JsonResponse
    {
        $totalLaporan = Laporan::count();
        $menungguValidasi = Laporan::where('status', 'MENUNGGU_VALIDASI_ADMIN')->count();
        $tervalidasi = Laporan::where('status', 'TERVALIDASI')->count();
        $ditolak = Laporan::where('status', 'DITOLAK')->count();

        $categories = DB::table('kategori_masalah')
            ->leftJoin('laporan', 'kategori_masalah.kategori_id', '=', 'laporan.kategori_id')
            ->select(
                'kategori_masalah.kategori_id',
                'kategori_masalah.nama_kategori',
                'kategori_masalah.bobot',
                DB::raw('COUNT(laporan.laporan_id) as total_laporan'),
                DB::raw("COUNT(CASE WHEN laporan.status = 'TERVALIDASI' THEN 1 END) as total_tervalidasi")
            )
            ->groupBy('kategori_masalah.kategori_id', 'kategori_masalah.nama_kategori', 'kategori_masalah.bobot')
            ->orderBy('kategori_masalah.kategori_id', 'asc')
            ->get();

        $segments = DB::table('segmen_jalur')
            ->select('segmen_id', 'nama_jalan', 'skor_kelayakan', 'panjang_m')
            ->orderBy('skor_kelayakan', 'asc')
            ->get();

        $avgSkor = $segments->avg('skor_kelayakan') ?? 100.0;
        $totalWarga = DB::table('warga')->count();

        return response()->json([
            'data' => [
                'summary' => [
                    'total_laporan' => $totalLaporan,
                    'menunggu_validasi' => $menungguValidasi,
                    'tervalidasi' => $tervalidasi,
                    'ditolak' => $ditolak,
                    'persentase_valid' => $totalLaporan > 0 ? round(($tervalidasi / $totalLaporan) * 100, 1) : 0,
                    'rata_rata_skor_jalan' => round((float) $avgSkor, 1),
                    'total_warga' => $totalWarga,
                ],
                'kategori' => $categories,
                'segmen' => $segments,
            ],
            'meta' => [
                'area' => 'Kecamatan Mulyorejo, Surabaya',
                'timestamp' => now()->toIso8601String(),
            ]
        ], 200);
    }

    /**
     * Submit review decision (Valid or Tidak Valid) (FR-F5-04, FR-F5-05, FR-F5-08).
     */
    public function validateReport(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        $keputusan = strtoupper(trim((string) $request->input('keputusan', '')));
        $alasanPenolakan = trim((string) $request->input('alasan_penolakan', ''));

        if (!in_array($keputusan, ['VALID', 'TIDAK_VALID'], true)) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_DECISION',
                    'message' => 'Keputusan validasi harus VALID atau TIDAK_VALID.',
                ]
            ], 422);
        }

        if ($keputusan === 'TIDAK_VALID' && $alasanPenolakan === '') {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_REASON_REQUIRED',
                    'message' => MessageCatalog::VALIDATION_REASON_REQUIRED,
                ]
            ], 422);
        }

        $laporan = null;
        try {
            DB::transaction(function () use ($id, $admin, $keputusan, $alasanPenolakan, &$laporan) {
                // Concurrency lock: lockForUpdate() on the report row
                $lap = Laporan::where('laporan_id', $id)->lockForUpdate()->first();

                if (!$lap) {
                    throw new \RuntimeException('NOT_FOUND');
                }

                if ($lap->status !== 'MENUNGGU_VALIDASI_ADMIN') {
                    throw new \RuntimeException('CONFLICT');
                }

                if ($keputusan === 'VALID') {
                    $lap->status = 'TERVALIDASI';
                    $lap->admin_id = $admin->admin_id;
                    $lap->tanggal_validasi = now();
                    $lap->alasan_penolakan = null;
                    $lap->save();

                    DB::table('riwayat_status_laporan')->insert([
                        'laporan_id' => $lap->laporan_id,
                        'jenis' => 'VALIDASI',
                        'status' => 'TERVALIDASI',
                        'admin_id' => $admin->admin_id,
                        'catatan' => 'Laporan dinyatakan VALID oleh Admin.',
                        'waktu' => now(),
                    ]);

                    // Recalculate segment score (WSM)
                    if ($lap->lokasi && $lap->lokasi->segmen_id) {
                        $this->segmentScoreService->recalculate($lap->lokasi->segmen_id);
                    }

                    // Send validated notification to Warga
                    $this->notificationService->notifyValidated($lap);
                } else {
                    $lap->status = 'DITOLAK';
                    $lap->admin_id = $admin->admin_id;
                    $lap->tanggal_validasi = now();
                    $lap->alasan_penolakan = $alasanPenolakan;
                    $lap->save();

                    DB::table('riwayat_status_laporan')->insert([
                        'laporan_id' => $lap->laporan_id,
                        'jenis' => 'VALIDASI',
                        'status' => 'DITOLAK',
                        'admin_id' => $admin->admin_id,
                        'catatan' => 'Ditolak: ' . $alasanPenolakan,
                        'waktu' => now(),
                    ]);

                    // Send rejected notification to Warga
                    $this->notificationService->notifyRejected($lap, $alasanPenolakan);
                }

                $laporan = $lap->load(['lokasi.segmen', 'kategori', 'foto', 'riwayat']);
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
            if ($e->getMessage() === 'CONFLICT') {
                return response()->json([
                    'error' => [
                        'code' => 'CONFLICT',
                        'message' => 'Laporan sudah divalidasi sebelumnya atau status tidak sesuai.',
                    ]
                ], 409);
            }
            throw $e;
        }

        return response()->json([
            'data' => [
                'laporan_id' => $laporan->laporan_id,
                'status' => $laporan->status,
                'tanggal_validasi' => $laporan->tanggal_validasi?->toIso8601String(),
                'alasan_penolakan' => $laporan->alasan_penolakan,
            ],
            'message' => 'Keputusan validasi berhasil disimpan.',
        ], 200);
    }
}