<?php

namespace App\Services;

use App\Models\KategoriMasalah;
use App\Models\SegmenJalur;
use Illuminate\Support\Facades\DB;

class SegmentScoreService
{
    /**
     * Recalculate feasibility score of a segment using the Weighted Sum Model (WSM).
     */
    public function recalculate(int $segmenId): float
    {
        return DB::transaction(function () use ($segmenId) {
            // Lock segment row for update to prevent race conditions (SRS Bagian 4.2)
            $segment = SegmenJalur::where('segmen_id', $segmenId)
                ->lockForUpdate()
                ->first();

            if (!$segment) {
                return 100.00;
            }

            $saturation = (int) env('CFG_REPORTS_SATURATION', 3);
            if ($saturation < 1) {
                $saturation = 1;
            }

            // Get all categories with weights
            $categories = KategoriMasalah::all();

            // Count active reports per category for this segment:
            // Laporan aktif: status = 'TERVALIDASI' AND (status_perbaikan IS NULL OR status_perbaikan = 'SEDANG_DIPERBAIKI')
            $activeReportsCounts = DB::table('laporan')
                ->join('lokasi', 'laporan.lokasi_id', '=', 'lokasi.lokasi_id')
                ->where('lokasi.segmen_id', $segmenId)
                ->where('laporan.status', 'TERVALIDASI')
                ->where(function ($query) {
                    $query->whereNull('laporan.status_perbaikan')
                          ->orWhere('laporan.status_perbaikan', 'SEDANG_DIPERBAIKI');
                })
                ->select('laporan.kategori_id', DB::raw('count(*) as count'))
                ->groupBy('laporan.kategori_id')
                ->pluck('count', 'kategori_id');

            $weightedSum = 0.0;
            foreach ($categories as $cat) {
                $count = (int) ($activeReportsCounts[$cat->kategori_id] ?? 0);
                $intensity = min(1.0, $count / $saturation);
                $weightedSum += ($cat->bobot * $intensity);
            }

            $score = 100.0 * (1.0 - $weightedSum);
            $score = round($score, 2);
            $score = max(0.00, min(100.00, $score));

            DB::table('segmen_jalur')
                ->where('segmen_id', $segmenId)
                ->update([
                    'skor_kelayakan' => $score,
                    'tanggal_update' => now(),
                ]);

            return $score;
        });
    }
}
