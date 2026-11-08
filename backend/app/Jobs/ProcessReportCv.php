<?php

namespace App\Jobs;

use App\Models\Laporan;
use App\Services\CvService;
use App\Services\NotificationService;
use App\Services\SegmentScoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessReportCv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $laporanId;
    public int $tries = 1;
    public int $timeout = 15;

    public function __construct(int $laporanId)
    {
        $this->laporanId = $laporanId;
    }

    public function handle(
        CvService $cvService,
        NotificationService $notificationService,
        SegmentScoreService $segmentScoreService
    ): void {
        // FR-F4-09: Idempotency check with row lock
        $laporan = Laporan::with(['lokasi.segmen', 'kategori', 'fotoLaporan'])->find($this->laporanId);

        if (!$laporan) {
            Log::warning("ProcessReportCv: Laporan #{$this->laporanId} not found");
            return;
        }

        // If status is not DIPROSES, job stops immediately without modifying anything
        if ($laporan->status !== 'DIPROSES') {
            Log::info("ProcessReportCv: Laporan #{$this->laporanId} status is '{$laporan->status}', skipping CV analysis.");
            return;
        }

        $kategoriNama = $laporan->kategori->nama_kategori ?? null;

        // FR-F4-11: If category not in CFG_CV_CATEGORIES, skip CV and route to Admin
        if (!$cvService->isCategorySupported($kategoriNama)) {
            Log::info("ProcessReportCv: Category '{$kategoriNama}' not in CFG_CV_CATEGORIES. Routing to Admin.");
            $this->fallbackToAdmin($laporan, $notificationService, 'Kategori tidak memerlukan analisis otomatis.');
            return;
        }

        $photoPath = $laporan->foto->url_foto ?? ($laporan->fotoLaporan->first()->url_foto ?? null);
        if (!$photoPath) {
            $this->fallbackToAdmin($laporan, $notificationService, 'Foto laporan tidak ditemukan.');
            return;
        }

        // Send to CV Service (Timeout <= 10s per SLA)
        $cvResult = $cvService->analyze($photoPath);

        // FR-F4-05: If CV fails or returns null
        if (!$cvResult) {
            $this->fallbackToAdmin($laporan, $notificationService, 'Analisis CV tidak menghasilkan data valid / timeout.');
            return;
        }

        $label = $cvResult['label'];
        $confidence = $cvResult['confidence'];
        $detectedCategory = $cvResult['detected_category'] ?? ($laporan->kategori->nama_kategori ?? 'Trotoar Rusak');
        $threshold = $cvService->getThreshold();
        $confPct = round($confidence * 100, 1);

        DB::transaction(function () use (
            $laporan,
            $label,
            $confidence,
            $detectedCategory,
            $threshold,
            $confPct,
            $notificationService,
            $segmentScoreService
        ) {
            // Lock row for atomic update
            $locked = Laporan::where('laporan_id', $laporan->laporan_id)->lockForUpdate()->first();
            if ($locked->status !== 'DIPROSES') {
                return;
            }

            // FR-F4-03: label == RUSAK and confidence >= threshold -> Auto-Validated
            if ($label === 'RUSAK' && $confidence >= $threshold) {
                $categoryAdjustedNote = "";
                if ($detectedCategory && $locked->kategori && $detectedCategory !== $locked->kategori->nama_kategori) {
                    $newCat = \App\Models\KategoriMasalah::where('nama_kategori', $detectedCategory)->first();
                    if ($newCat) {
                        $oldName = $locked->kategori->nama_kategori;
                        $locked->kategori_id = $newCat->kategori_id;
                        $categoryAdjustedNote = " (Kategori disesuaikan dari '{$oldName}')";
                    }
                }

                $locked->status = 'TERVALIDASI';
                $locked->cv_label = $label;
                $locked->confidence_score = $confidence;
                $locked->admin_id = null;
                $locked->tanggal_validasi = now();
                $locked->save();

                DB::table('riwayat_status_laporan')->insert([
                    'laporan_id' => $locked->laporan_id,
                    'jenis' => 'VALIDASI',
                    'status' => 'TERVALIDASI',
                    'admin_id' => null,
                    'catatan' => "Tervalidasi otomatis oleh Computer Vision: {$detectedCategory} (Confidence: {$confPct}%){$categoryAdjustedNote}",
                    'waktu' => now(),
                ]);

                // Recalculate WSM score for segment
                if ($locked->lokasi && $locked->lokasi->segmen_id) {
                    $segmentScoreService->recalculate((int) $locked->lokasi->segmen_id);
                }

                // Notify citizen
                $notificationService->notifyAutoValidated($locked);
            } else {
                // FR-F4-04: confidence < threshold OR label in ('BAIK', 'SPAM') -> Route to Admin
                $locked->status = 'MENUNGGU_VALIDASI_ADMIN';
                $locked->cv_label = $label;
                $locked->confidence_score = $confidence;
                $locked->save();

                $detDesc = "";
                if ($label === 'BAIK') {
                    $detDesc = " - Fasilitas terindikasi kondisi normal/baik ({$confPct}%), diteruskan ke verifikasi Admin";
                } elseif ($label === 'SPAM') {
                    $detDesc = " - Terindikasi bukan fasilitas pejalan kaki ({$confPct}%), diteruskan ke verifikasi Admin";
                } else {
                    $detDesc = ($detectedCategory ? " - {$detectedCategory}" : "") . " ({$confPct}%), diteruskan ke verifikasi Admin";
                }

                DB::table('riwayat_status_laporan')->insert([
                    'laporan_id' => $locked->laporan_id,
                    'jenis' => 'VALIDASI',
                    'status' => 'MENUNGGU_VALIDASI_ADMIN',
                    'admin_id' => null,
                    'catatan' => "Hasil CV: {$label}{$detDesc}",
                    'waktu' => now(),
                ]);

                // Notify citizen and admins
                $notificationService->notifyPendingAdmin($locked);
            }
        });
    }

    protected function fallbackToAdmin(Laporan $laporan, NotificationService $notificationService, string $reason): void
    {
        DB::transaction(function () use ($laporan, $notificationService, $reason) {
            $locked = Laporan::where('laporan_id', $laporan->laporan_id)->lockForUpdate()->first();
            if ($locked && $locked->status === 'DIPROSES') {
                $locked->status = 'MENUNGGU_VALIDASI_ADMIN';
                $locked->save();

                DB::table('riwayat_status_laporan')->insert([
                    'laporan_id' => $locked->laporan_id,
                    'jenis' => 'VALIDASI',
                    'status' => 'MENUNGGU_VALIDASI_ADMIN',
                    'admin_id' => null,
                    'catatan' => $reason,
                    'waktu' => now(),
                ]);

                $notificationService->notifyPendingAdmin($locked);
            }
        });
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("ProcessReportCv failed for Laporan #{$this->laporanId}: " . ($exception ? $exception->getMessage() : 'Unknown error'));
        try {
            $laporan = Laporan::find($this->laporanId);
            if ($laporan && $laporan->status === 'DIPROSES') {
                $notificationService = app(NotificationService::class);
                $this->fallbackToAdmin($laporan, $notificationService, 'Proses antrean CV gagal dieksekusi. Diteruskan ke Admin.');
            }
        } catch (\Throwable $t) {
            Log::error("ProcessReportCv: could not execute fallback on fail: " . $t->getMessage());
        }
    }
}
