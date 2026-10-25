<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\FotoLaporan;
use App\Models\KategoriMasalah;
use App\Models\Laporan;
use App\Models\Lokasi;
use App\Models\RiwayatStatusLaporan;
use App\Models\Warga;
use App\Services\NotificationService;
use App\Services\PhotoStorageService;
use App\Support\MessageCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        protected PhotoStorageService $photoService,
        protected NotificationService $notificationService,
    ) {}

    /**
     * Get all categories.
     */
    public function categories(): JsonResponse
    {
        $categories = KategoriMasalah::orderBy('kategori_id')->get(['kategori_id', 'nama_kategori', 'bobot']);
        return response()->json([
            'data' => $categories
        ], 200);
    }

    /**
     * Get summary of current warga's reports (for home screen).
     */
    public function mySummary(Request $request): JsonResponse
    {
        $wargaId = $request->user()->warga_id;

        $total = Laporan::where('warga_id', $wargaId)->count();
        $menunggu = Laporan::where('warga_id', $wargaId)
            ->whereIn('status', ['DIPROSES', 'MENUNGGU_VALIDASI_ADMIN'])
            ->count();
        $tervalidasi = Laporan::where('warga_id', $wargaId)
            ->where('status', 'TERVALIDASI')
            ->count();
        $ditolak = Laporan::where('warga_id', $wargaId)
            ->where('status', 'DITOLAK')
            ->count();

        return response()->json([
            'data' => [
                'total_laporan' => $total,
                'menunggu_validasi' => $menunggu,
                'tervalidasi' => $tervalidasi,
                'ditolak' => $ditolak,
            ]
        ], 200);
    }

    /**
     * Create a new report (F3).
     */
    public function store(Request $request): JsonResponse
    {
        $warga = $request->user();

        // 1. Validate existence of input fields
        $lat = $request->input('latitude');
        $lon = $request->input('longitude');
        $kategoriId = $request->input('kategori_id');
        $deskripsi = trim((string) $request->input('deskripsi', ''));

        // Check incomplete fields (FR-F3-05)
        if ($lat === null || $lon === null || !$kategoriId || $deskripsi === '' || !$request->hasFile('foto')) {
            return response()->json([
                'error' => [
                    'code' => 'REPORT_INCOMPLETE',
                    'message' => MessageCatalog::REPORT_INCOMPLETE,
                ]
            ], 422);
        }

        $lat = (float) $lat;
        $lon = (float) $lon;
        $kategoriId = (int) $kategoriId;

        if (strlen($deskripsi) > (int) env('CFG_DESCRIPTION_MAX', 200)) {
            return response()->json([
                'error' => [
                    'code' => 'REPORT_INCOMPLETE',
                    'message' => MessageCatalog::REPORT_INCOMPLETE,
                ]
            ], 422);
        }

        if (!KategoriMasalah::where('kategori_id', $kategoriId)->exists()) {
            return response()->json([
                'error' => [
                    'code' => 'REPORT_INCOMPLETE',
                    'message' => MessageCatalog::REPORT_INCOMPLETE,
                ]
            ], 422);
        }

        // 2. Validate coordinates within service area (FR-F3-03)
        $minLat = (float) env('CFG_SERVICE_AREA_MIN_LAT', -7.40);
        $maxLat = (float) env('CFG_SERVICE_AREA_MAX_LAT', -7.12);
        $minLon = (float) env('CFG_SERVICE_AREA_MIN_LON', 112.55);
        $maxLon = (float) env('CFG_SERVICE_AREA_MAX_LON', 112.90);

        if ($lat < $minLat || $lat > $maxLat || $lon < $minLon || $lon > $maxLon) {
            return response()->json([
                'error' => [
                    'code' => 'REPORT_OUT_OF_AREA',
                    'message' => MessageCatalog::REPORT_OUT_OF_AREA,
                ]
            ], 422);
        }

        // 3. PostGIS Snapping: Find nearest segment within CFG_SNAP_RADIUS_M (FR-F3-11)
        $snapRadius = (float) env('CFG_SNAP_RADIUS_M', 50);
        $nearestSegment = DB::selectOne("
            SELECT segmen_id, nama_jalan,
                   ST_Distance(geom, ST_SetSRID(ST_Point(?, ?), 4326)::geography) as distance
            FROM segmen_jalur
            WHERE ST_DWithin(geom, ST_SetSRID(ST_Point(?, ?), 4326)::geography, ?)
            ORDER BY distance ASC
            LIMIT 1;
        ", [$lon, $lat, $lon, $lat, $snapRadius]);

        if (!$nearestSegment) {
            return response()->json([
                'error' => [
                    'code' => 'REPORT_NO_SEGMENT',
                    'message' => MessageCatalog::REPORT_NO_SEGMENT,
                ]
            ], 422);
        }

        // 4. Validate and store photo (FR-F3-04, FR-F3-12)
        $photoFile = $request->file('foto');
        try {
            $photoPath = $this->photoService->processAndStore($photoFile);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'code' => 'REPORT_PHOTO_INVALID',
                    'message' => MessageCatalog::REPORT_PHOTO_INVALID,
                ]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'REPORT_PHOTO_INVALID',
                    'message' => MessageCatalog::REPORT_PHOTO_INVALID,
                ]
            ], 422);
        }

        // 5. Database transaction (atomic, FR-F3-13)
        $laporan = null;
        try {
            DB::transaction(function () use ($warga, $lat, $lon, $nearestSegment, $kategoriId, $deskripsi, $photoPath, &$laporan) {
                // Insert lokasi
                $lokasiId = DB::table('lokasi')->insertGetId([
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'geom' => DB::raw("ST_SetSRID(ST_Point({$lon}, {$lat}), 4326)"),
                    'alamat' => $nearestSegment->nama_jalan,
                    'segmen_id' => $nearestSegment->segmen_id,
                ], 'lokasi_id');

                // In Sprint 1 stub: CFG_CV_ENABLED = false -> status langsung MENUNGGU_VALIDASI_ADMIN
                $initialStatus = 'MENUNGGU_VALIDASI_ADMIN';

                // Insert laporan
                $laporanId = DB::table('laporan')->insertGetId([
                    'warga_id' => $warga->warga_id,
                    'kategori_id' => $kategoriId,
                    'lokasi_id' => $lokasiId,
                    'deskripsi' => $deskripsi,
                    'status' => $initialStatus,
                    'confidence_score' => null,
                    'cv_label' => null,
                    'alasan_penolakan' => null,
                    'admin_id' => null,
                    'tanggal_lapor' => now(),
                    'tanggal_validasi' => null,
                    'status_perbaikan' => null,
                    'tanggal_perbaikan' => null,
                ], 'laporan_id');

                // Insert foto_laporan
                DB::table('foto_laporan')->insert([
                    'laporan_id' => $laporanId,
                    'url_foto' => $photoPath,
                    'tanggal_upload' => now(),
                ]);

                // Insert riwayat_status_laporan
                DB::table('riwayat_status_laporan')->insert([
                    'laporan_id' => $laporanId,
                    'jenis' => 'VALIDASI',
                    'status' => $initialStatus,
                    'admin_id' => null,
                    'catatan' => 'Laporan masuk antrean validasi',
                    'waktu' => now(),
                ]);

                $laporan = Laporan::with(['lokasi.segmen', 'kategori'])->find($laporanId);
            });
        } catch (\Exception $e) {
            // Rollback uploaded file if DB transaction fails
            $this->photoService->delete($photoPath);
            return response()->json([
                'error' => [
                    'code' => 'INTERNAL_SERVER_ERROR',
                    'message' => 'Gagal menyimpan laporan. Silakan coba lagi.',
                ]
            ], 500);
        }

        // 6. Send notifications (Sprint 1 stub: DB notifications)
        $this->notificationService->notifyPendingAdmin($laporan);

        return response()->json([
            'data' => [
                'laporan_id' => $laporan->laporan_id,
                'status' => $laporan->status,
            ],
            'message' => MessageCatalog::REPORT_SENT,
        ], 201);
    }

    /**
     * Show report detail (FR-F3-10).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $laporan = Laporan::with(['lokasi.segmen', 'kategori', 'foto', 'riwayat.admin'])->find($id);

        if (!$laporan) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Laporan tidak ditemukan.',
                ]
            ], 404);
        }

        // Ownership check: only owner Warga or Admin can view
        $isWargaOwner = ($user instanceof Warga && (int) $laporan->warga_id === (int) $user->warga_id);
        $isAdmin = ($user instanceof Admin);

        if (!$isWargaOwner && !$isAdmin) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Anda tidak berhak melihat laporan ini.',
                ]
            ], 403);
        }

        // Use the segment already assigned during submission, never re-snap to a new one.
        $segmentMap = null;
        if ($isAdmin && $laporan->lokasi?->segmen_id) {
            $segment = DB::selectOne('
                SELECT s.segmen_id, s.nama_jalan,
                       ST_AsGeoJSON(s.geom::geometry) AS geojson,
                       ST_Distance(s.geom, l.geom) AS distance_m
                FROM segmen_jalur s
                JOIN lokasi l ON l.segmen_id = s.segmen_id
                WHERE l.lokasi_id = ?
            ', [$laporan->lokasi->lokasi_id]);

            if ($segment) {
                $segmentMap = [
                    'segmen_id' => (int) $segment->segmen_id,
                    'nama_jalan' => $segment->nama_jalan,
                    'geojson' => json_decode($segment->geojson, true),
                    'jarak_snapping_m' => $segment->distance_m === null
                        ? null : round((float) $segment->distance_m, 1),
                ];
            }
        }

        // Generate temporary signed URL for photo (FR-F3-14)
        $photoUrl = null;
        if ($laporan->foto) {
            $photoUrl = URL::temporarySignedRoute(
                'report.photo',
                now()->addMinutes(10),
                ['id' => $laporan->laporan_id]
            );
        }

        return response()->json([
            'data' => [
                'laporan_id' => $laporan->laporan_id,
                'status' => $laporan->status,
                'status_perbaikan' => $laporan->status_perbaikan,
                'deskripsi' => $laporan->deskripsi,
                'kategori' => [
                    'id' => $laporan->kategori->kategori_id,
                    'nama' => $laporan->kategori->nama_kategori,
                ],
                'lokasi' => [
                    'latitude' => $laporan->lokasi->latitude,
                    'longitude' => $laporan->lokasi->longitude,
                    'alamat' => $laporan->lokasi->alamat,
                    'segmen_id' => $laporan->lokasi->segmen_id,
                    'nama_jalan' => $laporan->lokasi->segmen->nama_jalan ?? null,
                    ...($isAdmin ? ['segmen' => $segmentMap] : []),
                ],
                'foto_url' => $photoUrl,
                'confidence_score' => $laporan->confidence_score,
                'cv_label' => $laporan->cv_label,
                'alasan_penolakan' => $laporan->alasan_penolakan,
                'tanggal_lapor' => $laporan->tanggal_lapor?->toIso8601String(),
                'tanggal_validasi' => $laporan->tanggal_validasi?->toIso8601String(),
                'riwayat' => $laporan->riwayat->map(function ($r) {
                    return [
                        'jenis' => $r->jenis,
                        'status' => $r->status,
                        'admin_nama' => $r->admin->nama ?? 'Sistem',
                        'catatan' => $r->catatan,
                        'waktu' => $r->waktu?->toIso8601String(),
                    ];
                }),
            ]
        ], 200);
    }

    /**
     * Stream protected photo with signed route verification (FR-F3-14).
     */
    public function photo(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $foto = FotoLaporan::where('laporan_id', $id)->first();
        if (!$foto || !Storage::disk('public')->exists($foto->url_foto)) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Berkas foto tidak ditemukan.',
                ]
            ], 404);
        }

        $path = Storage::disk('public')->path($foto->url_foto);
        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
