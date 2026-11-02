<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KategoriMasalah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

class HeatmapController extends Controller
{
    /**
     * Get GeoJSON heatmap of segments within viewport (FR-F6-01 to FR-F6-08).
     * Endpoint: GET /api/v1/heatmap
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Validate query parameters
        $validator = Validator::make($request->all(), [
            'min_lon' => 'required|numeric|between:-180,180',
            'min_lat' => 'required|numeric|between:-90,90',
            'max_lon' => 'required|numeric|between:-180,180',
            'max_lat' => 'required|numeric|between:-90,90',
            'zoom' => 'required|numeric|min:0|max:24',
            'kategori_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_PARAMETERS',
                    'message' => 'Parameter bounding box atau zoom tidak valid.',
                    'fields' => $validator->errors()->toArray(),
                ]
            ], 422);
        }

        $minLon = (float) $request->query('min_lon');
        $minLat = (float) $request->query('min_lat');
        $maxLon = (float) $request->query('max_lon');
        $maxLat = (float) $request->query('max_lat');
        $zoom = (float) $request->query('zoom');
        $kategoriId = ($request->has('kategori_id') && $request->query('kategori_id') !== null && $request->query('kategori_id') !== '') 
            ? (int) $request->query('kategori_id') 
            : null;
        $repairFilter = strtolower(trim((string) $request->query('filter_perbaikan', $request->query('repair_status', 'aktif'))));

        $minZoom = (int) env('CFG_HEATMAP_MIN_ZOOM', 14);
        $maxFeatures = (int) env('CFG_HEATMAP_MAX_FEATURES', 5000);
        $saturation = (int) env('CFG_REPORTS_SATURATION', 3);
        if ($saturation < 1) {
            $saturation = 1;
        }

        // Determine simplification tolerance based on zoom level (FR-F6-08)
        $tolerance = 0.000005;
        if ($zoom < 11) {
            $tolerance = 0.001;
        } elseif ($zoom < 13) {
            $tolerance = 0.0004;
        } elseif ($zoom < 15) {
            $tolerance = 0.0001;
        }

        // If kategori_id is given, ensure category exists
        if ($kategoriId !== null) {
            $categoryExists = KategoriMasalah::where('kategori_id', $kategoriId)->exists();
            if (!$categoryExists) {
                return response()->json([
                    'type' => 'FeatureCollection',
                    'features' => [],
                    'reports' => [],
                    'meta' => [
                        'counts' => [
                            'SANGAT_LAYAK' => 0,
                            'PERLU_PERHATIAN' => 0,
                            'TIDAK_LAYAK' => 0,
                        ],
                        'truncated' => false,
                    ]
                ], 200);
            }

            return $this->handleCategoryFiltered(
                $minLon, $minLat, $maxLon, $maxLat,
                $kategoriId, $tolerance, $maxFeatures, $saturation, $repairFilter
            );
        }

        // --- General Heatmap (All Categories) ---

        // 2. Count total segments per feasibility level across the ENTIRE viewport (FR-F6-05, FR-F6-08)
        $countsRow = DB::selectOne("
            SELECT 
                COUNT(CASE WHEN skor_kelayakan >= 70 THEN 1 END) as count_sangat_layak,
                COUNT(CASE WHEN skor_kelayakan >= 40 AND skor_kelayakan < 70 THEN 1 END) as count_perlu_perhatian,
                COUNT(CASE WHEN skor_kelayakan < 40 THEN 1 END) as count_tidak_layak,
                COUNT(*) as total_viewport
            FROM segmen_jalur s
            WHERE ST_Intersects(s.geom, ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography)
        ", [$minLon, $minLat, $maxLon, $maxLat]);

        $countSangat = (int) ($countsRow->count_sangat_layak ?? 0);
        $countPerlu = (int) ($countsRow->count_perlu_perhatian ?? 0);
        $countTidak = (int) ($countsRow->count_tidak_layak ?? 0);
        $totalViewport = (int) ($countsRow->total_viewport ?? 0);

        if ($totalViewport === 0) {
            return response()->json([
                'type' => 'FeatureCollection',
                'features' => [],
                'reports' => [],
                'meta' => [
                    'counts' => [
                        'SANGAT_LAYAK' => 0,
                        'PERLU_PERHATIAN' => 0,
                        'TIDAK_LAYAK' => 0,
                    ],
                    'truncated' => false,
                ]
            ], 200);
        }

        // 3. Query segments for features list
        // On zoom < CFG_HEATMAP_MIN_ZOOM (14): only return segments with score < 100 (FR-F6-08)
        $lowZoomOnlyProblematic = ($zoom < $minZoom);

        $sql = "
            SELECT 
                s.segmen_id,
                s.nama_jalan,
                s.skor_kelayakan,
                COALESCE(act.active_count, 0) as jumlah_laporan_aktif,
                ST_AsGeoJSON(ST_Simplify(s.geom::geometry, ?)) as geojson
            FROM segmen_jalur s
            LEFT JOIN (
                SELECT l.segmen_id, COUNT(lap.laporan_id) as active_count
                FROM laporan lap
                JOIN lokasi l ON lap.lokasi_id = l.lokasi_id
                WHERE lap.status = 'TERVALIDASI'
                  AND (lap.status_perbaikan IS NULL OR lap.status_perbaikan = 'SEDANG_DIPERBAIKI')
                GROUP BY l.segmen_id
            ) act ON act.segmen_id = s.segmen_id
            WHERE ST_Intersects(s.geom, ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography)
        ";

        $params = [$tolerance, $minLon, $minLat, $maxLon, $maxLat];

        if ($lowZoomOnlyProblematic) {
            $sql .= " AND s.skor_kelayakan < 100 ";
        }

        // Fetch up to maxFeatures + 1 to detect truncation
        $sql .= " ORDER BY s.skor_kelayakan ASC, s.segmen_id ASC LIMIT ? ";
        $params[] = $maxFeatures + 1;

        $rows = DB::select($sql, $params);

        $isTruncated = count($rows) > $maxFeatures;
        if ($isTruncated) {
            $rows = array_slice($rows, 0, $maxFeatures);
        }

        $features = [];
        foreach ($rows as $row) {
            $score = (float) $row->skor_kelayakan;
            $tingkat = $this->determineLevel($score);
            $geometry = json_decode($row->geojson, true);

            $features[] = [
                'type' => 'Feature',
                'geometry' => $geometry,
                'properties' => [
                    'segmen_id' => (int) $row->segmen_id,
                    'nama_jalan' => $row->nama_jalan,
                    'skor' => $score,
                    'tingkat' => $tingkat,
                    'jumlah_laporan_aktif' => (int) $row->jumlah_laporan_aktif,
                ],
            ];
        }

        $reports = $this->getReportPoints($minLon, $minLat, $maxLon, $maxLat, null, $repairFilter);

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
            'reports' => $reports,
            'meta' => [
                'counts' => [
                    'SANGAT_LAYAK' => $countSangat,
                    'PERLU_PERHATIAN' => $countPerlu,
                    'TIDAK_LAYAK' => $countTidak,
                ],
                'truncated' => $isTruncated,
            ]
        ], 200);
    }

    /**
     * Handle category-specific heatmap filter (SRS Section 4.2).
     */
    protected function handleCategoryFiltered(
        float $minLon, float $minLat, float $maxLon, float $maxLat,
        int $kategoriId, float $tolerance, int $maxFeatures, int $saturation, string $repairFilter = 'aktif'
    ): JsonResponse {
        $sql = "
            SELECT 
                s.segmen_id,
                s.nama_jalan,
                act.count_k as jumlah_laporan_aktif,
                ST_AsGeoJSON(ST_Simplify(s.geom::geometry, ?)) as geojson
            FROM segmen_jalur s
            JOIN (
                SELECT l.segmen_id, COUNT(lap.laporan_id) as count_k
                FROM laporan lap
                JOIN lokasi l ON lap.lokasi_id = l.lokasi_id
                WHERE lap.kategori_id = ?
                  AND lap.status = 'TERVALIDASI'
                  AND (lap.status_perbaikan IS NULL OR lap.status_perbaikan = 'SEDANG_DIPERBAIKI')
                GROUP BY l.segmen_id
            ) act ON act.segmen_id = s.segmen_id
            WHERE ST_Intersects(s.geom, ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography)
        ";

        $rows = DB::select($sql, [$tolerance, $kategoriId, $minLon, $minLat, $maxLon, $maxLat]);

        if (empty($rows)) {
            return response()->json([
                'type' => 'FeatureCollection',
                'features' => [],
                'reports' => [],
                'meta' => [
                    'counts' => [
                        'SANGAT_LAYAK' => 0,
                        'PERLU_PERHATIAN' => 0,
                        'TIDAK_LAYAK' => 0,
                    ],
                    'truncated' => false,
                ]
            ], 200);
        }

        $countSangat = 0;
        $countPerlu = 0;
        $countTidak = 0;

        $processedFeatures = [];
        foreach ($rows as $row) {
            $ck = (int) $row->jumlah_laporan_aktif;
            $ik = min(1.0, $ck / $saturation);
            $score = round(100.0 * (1.0 - $ik), 2);
            $tingkat = $this->determineLevel($score);

            if ($tingkat === 'SANGAT_LAYAK') {
                $countSangat++;
            } elseif ($tingkat === 'PERLU_PERHATIAN') {
                $countPerlu++;
            } else {
                $countTidak++;
            }

            $processedFeatures[] = [
                'type' => 'Feature',
                'geometry' => json_decode($row->geojson, true),
                'properties' => [
                    'segmen_id' => (int) $row->segmen_id,
                    'nama_jalan' => $row->nama_jalan,
                    'skor' => $score,
                    'tingkat' => $tingkat,
                    'jumlah_laporan_aktif' => $ck,
                ],
                '_sort_score' => $score,
                '_sort_id' => (int) $row->segmen_id,
            ];
        }

        // Sort by score ascending, segmen_id ascending
        usort($processedFeatures, function ($a, $b) {
            if ($a['_sort_score'] !== $b['_sort_score']) {
                return $a['_sort_score'] <=> $b['_sort_score'];
            }
            return $a['_sort_id'] <=> $b['_sort_id'];
        });

        $isTruncated = count($processedFeatures) > $maxFeatures;
        if ($isTruncated) {
            $processedFeatures = array_slice($processedFeatures, 0, $maxFeatures);
        }

        // Remove sort keys
        $features = array_map(function ($item) {
            unset($item['_sort_score'], $item['_sort_id']);
            return $item;
        }, $processedFeatures);

        $reports = $this->getReportPoints($minLon, $minLat, $maxLon, $maxLat, $kategoriId, $repairFilter);

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
            'reports' => $reports,
            'meta' => [
                'counts' => [
                    'SANGAT_LAYAK' => $countSangat,
                    'PERLU_PERHATIAN' => $countPerlu,
                    'TIDAK_LAYAK' => $countTidak,
                ],
                'truncated' => $isTruncated,
            ]
        ], 200);
    }

    /**
     * Get active or repaired report points with density count for visual heatmap dots.
     */
    protected function getReportPoints(float $minLon, float $minLat, float $maxLon, float $maxLat, ?int $kategoriId = null, string $repairFilter = 'aktif'): array
    {
        $statusCondition = " AND (lap.status_perbaikan IS NULL OR lap.status_perbaikan = 'SEDANG_DIPERBAIKI') ";
        if ($repairFilter === 'selesai' || $repairFilter === 'sudah_diperbaiki') {
            $statusCondition = " AND lap.status_perbaikan = 'SUDAH_DIPERBAIKI' ";
        } elseif ($repairFilter === 'sedang' || $repairFilter === 'sedang_diperbaiki') {
            $statusCondition = " AND lap.status_perbaikan = 'SEDANG_DIPERBAIKI' ";
        } elseif ($repairFilter === 'semua') {
            $statusCondition = "";
        }

        $sql = "
            SELECT 
                lap.laporan_id,
                lap.deskripsi,
                lap.status,
                lap.status_perbaikan,
                lap.tanggal_lapor,
                lap.tanggal_perbaikan,
                lap.catatan_perbaikan,
                lap.foto_perbaikan,
                km.kategori_id,
                km.nama_kategori,
                lok.latitude,
                lok.longitude,
                lok.alamat,
                fot.url_foto,
                seg.nama_jalan as segmen_nama_jalan,
                seg.skor_kelayakan as skor_segmen,
                (
                    SELECT rsl.catatan 
                    FROM riwayat_status_laporan rsl 
                    WHERE rsl.laporan_id = lap.laporan_id AND rsl.jenis = 'PERBAIKAN' 
                    ORDER BY rsl.waktu DESC LIMIT 1
                ) as catatan_audit,
                (
                    SELECT adm.nama 
                    FROM riwayat_status_laporan rsl2 
                    JOIN admin adm ON rsl2.admin_id = adm.admin_id 
                    WHERE rsl2.laporan_id = lap.laporan_id AND rsl2.jenis = 'PERBAIKAN' 
                    ORDER BY rsl2.waktu DESC LIMIT 1
                ) as verifikator_nama,
                (
                    SELECT COUNT(*)
                    FROM laporan l2
                    JOIN lokasi lok2 ON l2.lokasi_id = lok2.lokasi_id
                    WHERE l2.status = 'TERVALIDASI'
                      AND (l2.status_perbaikan IS NULL OR l2.status_perbaikan = 'SEDANG_DIPERBAIKI')
                      AND ST_DWithin(lok2.geom, lok.geom, 60)
                ) as density_count
            FROM laporan lap
            JOIN lokasi lok ON lap.lokasi_id = lok.lokasi_id
            LEFT JOIN segmen_jalur seg ON lok.segmen_id = seg.segmen_id
            JOIN kategori_masalah km ON lap.kategori_id = km.kategori_id
            LEFT JOIN foto_laporan fot ON fot.laporan_id = lap.laporan_id
            WHERE lap.status = 'TERVALIDASI'
              {$statusCondition}
              AND ST_Intersects(lok.geom, ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography)
        ";

        $params = [$minLon, $minLat, $maxLon, $maxLat];

        if ($kategoriId !== null) {
            $sql .= " AND lap.kategori_id = ? ";
            $params[] = $kategoriId;
        }

        $sql .= " ORDER BY density_count DESC, lap.laporan_id DESC LIMIT 500 ";

        $rows = DB::select($sql, $params);

        return array_map(function ($r) {
            $density = (int) $r->density_count;
            $heatLevel = $density >= 3 ? 'HIGH' : ($density >= 2 ? 'MEDIUM' : 'LOW');
            $intensity = min(1.0, max(0.3, $density / 3.0));
            $isFixed = ($r->status_perbaikan === 'SUDAH_DIPERBAIKI');

            $photoBeforeUrl = $r->url_foto ? URL::temporarySignedRoute('report.photo', now()->addMinutes(60), ['id' => $r->laporan_id]) : null;
            $photoAfterUrl = $r->foto_perbaikan ? URL::temporarySignedRoute('report.photo', now()->addMinutes(60), ['id' => $r->laporan_id, 'type' => 'repair']) : null;

            return [
                'laporan_id' => (int) $r->laporan_id,
                'kode_laporan' => '#LAP-' . str_pad($r->laporan_id, 4, '0', STR_PAD_LEFT),
                'latitude' => (float) $r->latitude,
                'longitude' => (float) $r->longitude,
                'kategori_id' => (int) $r->kategori_id,
                'kategori_nama' => $r->nama_kategori,
                'deskripsi' => $r->deskripsi,
                'alamat' => $r->alamat ?? $r->segmen_nama_jalan ?? 'Surabaya',
                'status' => $r->status,
                'status_perbaikan' => $r->status_perbaikan,
                'is_fixed' => $isFixed,
                'catatan_perbaikan' => $r->catatan_perbaikan ?: ($r->catatan_audit ?: ($isFixed ? 'Perbaikan fasilitas telah selesai dikerjakan dan diverifikasi lapangan.' : null)),
                'verifikator' => $r->verifikator_nama ?: 'Dinas Terkait (Admin)',
                'tanggal_lapor' => $r->tanggal_lapor,
                'tanggal_perbaikan' => $r->tanggal_perbaikan,
                'foto_url' => $photoBeforeUrl,
                'foto_perbaikan_url' => $photoAfterUrl,
                'skor_segmen' => $r->skor_segmen !== null ? round((float) $r->skor_segmen, 1) : 100.0,
                'density_count' => $density,
                'intensity' => $intensity,
                'heat_level' => $heatLevel,
            ];
        }, $rows);
    }

    /**
     * Determine feasibility level based on score (SRS Section 4.2).
     */
    protected function determineLevel(float $score): string
    {
        $goodLevel = (float) env('CFG_LEVEL_GOOD', 70);
        $warnLevel = (float) env('CFG_LEVEL_WARN', 40);

        if ($score >= $goodLevel) {
            return 'SANGAT_LAYAK';
        }
        if ($score >= $warnLevel) {
            return 'PERLU_PERHATIAN';
        }
        return 'TIDAK_LAYAK';
    }
}
