<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\FotoLaporan;
use App\Models\KategoriMasalah;
use App\Models\Laporan;
use App\Models\Lokasi;
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
        $categories = KategoriMasalah::orderBy('kategori_id')->get([
            'kategori_id',
            'kode_label',
            'nama_kategori',
            'deskripsi',
            'bobot',
        ]);

        return response()->json([
            'data' => $categories,
        ], 200);
    }

    /**
     * Get all pedestrian segments as GeoJSON FeatureCollection.
     */
    public function segments(): JsonResponse
    {
        $segments = DB::select("
            SELECT segmen_id, osm_way_id, nama_jalan, panjang_m, skor_kelayakan,
                   ST_AsGeoJSON(geom::geometry) as geojson
            FROM segmen_jalur
            ORDER BY segmen_id ASC;
        ");

        $features = array_map(function ($seg) {
            return [
                'type' => 'Feature',
                'geometry' => json_decode($seg->geojson),
                'properties' => [
                    'segmen_id' => $seg->segmen_id,
                    'nama_jalan' => $seg->nama_jalan,
                    'panjang_m' => (float) $seg->panjang_m,
                    'skor_kelayakan' => (float) $seg->skor_kelayakan,
                ],
            ];
        }, $segments);

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
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
            ],
        ], 200);
    }

    /**
     * Riwayat laporan milik warga yang login. Filter: ?status=A,B
     */
    public function myReports(Request $request): JsonResponse
    {
        $allowed = ['DIPROSES', 'MENUNGGU_VALIDASI_ADMIN', 'TERVALIDASI', 'DITOLAK'];
        $statuses = array_values(array_intersect(
            array_filter(array_map('trim', explode(',', strtoupper((string) $request->query('status', ''))))),
            $allowed
        ));

        $query = Laporan::with(['kategori', 'lokasi.segmen'])
            ->where('warga_id', $request->user()->warga_id);

        if ($statuses) {
            $query->whereIn('status', $statuses);
        }

        $data = $query->orderByDesc('tanggal_lapor')->get()->map(fn ($lap) => [
            'laporan_id' => $lap->laporan_id,
            'kode_laporan' => '#LAP-' . str_pad($lap->laporan_id, 4, '0', STR_PAD_LEFT),
            'status' => $lap->status,
            'kategori' => $lap->kategori->nama_kategori,
            'alamat' => $lap->lokasi->alamat ?? $lap->lokasi->segmen->nama_jalan ?? 'Surabaya',
            'tanggal_lapor' => $lap->tanggal_lapor?->toIso8601String(),
            'tanggal_validasi' => $lap->tanggal_validasi?->toIso8601String(),
        ]);

        return response()->json(['data' => $data, 'meta' => ['total' => $data->count()]], 200);
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
        $kategoriInput = $request->input('kategori_id');
        $deskripsi = trim((string) $request->input('deskripsi', ''));

        // Check incomplete fields (FR-F3-05) - note: '0' or 0 is valid for 0-indexed categories
        if ($lat === null || $lon === null || $kategoriInput === null || $kategoriInput === '' || $deskripsi === '' || ! $request->hasFile('foto')) {
            return response()->json([
                'error' => [
                    'code' => 'REPORT_INCOMPLETE',
                    'message' => MessageCatalog::REPORT_INCOMPLETE,
                ],
            ], 422);
        }

        $lat = (float) $lat;
        $lon = (float) $lon;

        if (strlen($deskripsi) > (int) env('CFG_DESCRIPTION_MAX', 200)) {
            return response()->json([
                'error' => [
                    'code' => 'REPORT_INCOMPLETE',
                    'message' => MessageCatalog::REPORT_INCOMPLETE,
                ],
            ], 422);
        }

        // Resolve category by ID (1..5), 0-based code (0..4), or slug/name
        $kategori = null;
        if (is_numeric($kategoriInput)) {
            $num = (int) $kategoriInput;
            $kategori = KategoriMasalah::where('kategori_id', $num)
                ->orWhere('kode_label', $num)
                ->first();
        }
        if (! $kategori && is_string($kategoriInput)) {
            $slug = strtolower(trim($kategoriInput));
            $kategori = KategoriMasalah::where('nama_kategori', $slug)
                ->orWhere('nama_kategori', str_replace('_', ' ', $slug))
                ->orWhere('nama_kategori', ucwords(str_replace('_', ' ', $slug)))
                ->first();

            if (! $kategori) {
                // Synonyms & model label aliases
                if (in_array($slug, ['drainase_terbuka', 'drainase', 'masalah_drainase'])) {
                    $kategori = KategoriMasalah::where('nama_kategori', 'like', '%Drainase%')->first();
                } elseif (in_array($slug, ['zebra_cross_utuh', 'zebra_cross_pudar', 'penyebrangan_rusak', 'penyeberangan_rusak', 'penyeberangan', 'penyebrangan'])) {
                    $kategori = KategoriMasalah::where('nama_kategori', 'like', '%Penyebrangan%')
                        ->orWhere('nama_kategori', 'like', '%Penyeberangan%')
                        ->first();
                } elseif (in_array($slug, ['kendaraan_halangan', 'parkir_liar', 'halangan_fisik'])) {
                    $kategori = KategoriMasalah::where('nama_kategori', 'like', '%Parkir%')
                        ->orWhere('nama_kategori', 'like', '%Halangan%')
                        ->first();
                }
            }
        }

        if (! $kategori) {
            return response()->json([
                'error' => [
                    'code' => 'REPORT_INCOMPLETE',
                    'message' => MessageCatalog::REPORT_INCOMPLETE,
                ],
            ], 422);
        }

        $kategoriId = $kategori->kategori_id;

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
                ],
            ], 422);
        }

        // 3. PostGIS Snapping: Hubungkan dengan segmen jalan terdekat (toleransi hingga 5000m)
        $nearestSegment = DB::selectOne('
            SELECT segmen_id, nama_jalan,
                   ST_Distance(geom, ST_SetSRID(ST_Point(?, ?), 4326)::geography) as distance
            FROM segmen_jalur
            ORDER BY distance ASC
            LIMIT 1;
        ', [$lon, $lat]);

        // Jika jarak ke seluruh jalan terdata lebih dari 5 km (misal titik uji di tengah lautan Selat Madura)
        $maxAllowedDistance = (float) env('CFG_MAX_ROAD_DISTANCE_M', 5000);
        if (! $nearestSegment || $nearestSegment->distance > $maxAllowedDistance) {
            return response()->json([
                'error' => [
                    'code' => 'REPORT_NO_SEGMENT',
                    'message' => MessageCatalog::REPORT_NO_SEGMENT,
                ],
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
                ],
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'REPORT_PHOTO_INVALID',
                    'message' => MessageCatalog::REPORT_PHOTO_INVALID,
                ],
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
                ],
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

        if (! $laporan) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Laporan tidak ditemukan.',
                ],
            ], 404);
        }

        // Ownership check: only owner Warga or Admin can view
        $isWargaOwner = ($user instanceof Warga && (int) $laporan->warga_id === (int) $user->warga_id);
        $isAdmin = ($user instanceof Admin);

        if (! $isWargaOwner && ! $isAdmin) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Anda tidak berhak melihat laporan ini.',
                ],
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

        $photoRepairUrl = null;
        if ($laporan->foto_perbaikan) {
            $photoRepairUrl = URL::temporarySignedRoute(
                'report.photo',
                now()->addMinutes(10),
                ['id' => $laporan->laporan_id, 'type' => 'repair']
            );
        }

        return response()->json([
            'data' => [
                'laporan_id' => $laporan->laporan_id,
                'status' => $laporan->status,
                'status_perbaikan' => $laporan->status_perbaikan,
                'catatan_perbaikan' => $laporan->catatan_perbaikan,
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
                'foto_perbaikan_url' => $photoRepairUrl,
                'confidence_score' => $laporan->confidence_score,
                'cv_label' => $laporan->cv_label,
                'alasan_penolakan' => $laporan->alasan_penolakan,
                'tanggal_lapor' => $laporan->tanggal_lapor?->toIso8601String(),
                'tanggal_validasi' => $laporan->tanggal_validasi?->toIso8601String(),
                'tanggal_perbaikan' => $laporan->tanggal_perbaikan?->toIso8601String(),
                'riwayat' => $laporan->riwayat->map(function ($r) {
                    return [
                        'jenis' => $r->jenis,
                        'status' => $r->status,
                        'admin_nama' => $r->admin->nama ?? 'Sistem',
                        'catatan' => $r->catatan,
                        'waktu' => $r->waktu?->toIso8601String(),
                    ];
                }),
            ],
        ], 200);
    }

    /**
     * Stream protected photo with signed route verification (FR-F3-14).
     */
    public function photo(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        if ($request->query('type') === 'repair') {
            $lap = Laporan::find($id);
            if (! $lap || ! $lap->foto_perbaikan || ! Storage::disk('public')->exists($lap->foto_perbaikan)) {
                return response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Berkas foto bukti perbaikan tidak ditemukan.',
                    ],
                ], 404);
            }

            $path = Storage::disk('public')->path($lap->foto_perbaikan);

            return response()->file($path, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'private, max-age=600',
            ]);
        }

        $foto = FotoLaporan::where('laporan_id', $id)->first();
        if (! $foto || ! Storage::disk('public')->exists($foto->url_foto)) {
            return response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Berkas foto tidak ditemukan.',
                ],
            ], 404);
        }

        $path = Storage::disk('public')->path($foto->url_foto);

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}