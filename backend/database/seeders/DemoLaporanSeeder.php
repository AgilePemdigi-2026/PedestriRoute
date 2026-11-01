<?php

namespace Database\Seeders;

use App\Models\Laporan;
use App\Models\Warga;
use App\Services\SegmentScoreService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoLaporanSeeder extends Seeder
{
    public function run(): void
    {
        $warga = Warga::firstOrCreate(
            ['email' => 'demo.warga@pedestriroute.test'],
            [
                'nama' => 'Warga Pelapor PedestriRoute',
                'no_hp' => '6289999999999',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'tanggal_daftar' => now(),
                'gagal_login' => 0,
            ]
        );

        // Get an existing photo file from storage/app/public/reports or create a sample placeholder
        $existingPhotos = Storage::disk('public')->files('reports');
        $samplePhoto = !empty($existingPhotos) ? $existingPhotos[0] : null;

        if (!$samplePhoto) {
            // Create a small placeholder if none exists
            $samplePhoto = 'reports/demo_trotoar.jpg';
            if (!Storage::disk('public')->exists($samplePhoto)) {
                $img = imagecreatetruecolor(400, 300);
                $bg = imagecolorallocate($img, 0, 98, 87);
                imagefill($img, 0, 0, $bg);
                ob_start();
                imagejpeg($img);
                $data = ob_get_clean();
                Storage::disk('public')->put($samplePhoto, $data);
                imagedestroy($img);
            }
        }

        $sampleRepairPhoto = 'reports/demo_perbaikan_selesai.jpg';
        if (!Storage::disk('public')->exists($sampleRepairPhoto)) {
            $img2 = imagecreatetruecolor(400, 300);
            $bg2 = imagecolorallocate($img2, 16, 185, 129);
            imagefill($img2, 0, 0, $bg2);
            ob_start();
            imagejpeg($img2);
            $data2 = ob_get_clean();
            Storage::disk('public')->put($sampleRepairPhoto, $data2);
            imagedestroy($img2);
        }

        // Realistic reports across Mulyorejo to show diverse densities (Red, Amber, Green)
        $demoReports = [
            // --- Hotspot 1: Jl. Raya Mulyosari (High Density cluster - 4 reports within 40m -> RED HOTSPOT) ---
            [
                'alamat' => 'Jl. Raya Mulyosari No. 12',
                'lat' => -7.26628,
                'lon' => 112.79648,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Paving trotoar amblas sedalam 15 cm dekat pertigaan, membahayakan pejalan kaki.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null,
                'segmen_id' => 1,
            ],
            [
                'alamat' => 'Jl. Raya Mulyosari No. 18',
                'lat' => -7.26635,
                'lon' => 112.79655,
                'kategori_id' => 2, // Masalah Drainase
                'deskripsi' => 'Tutup gorong-gorong beton patah dan berlubang di badan trotoar.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI',
                'segmen_id' => 1,
            ],
            [
                'alamat' => 'Jl. Raya Mulyosari No. 22',
                'lat' => -7.26642,
                'lon' => 112.79662,
                'kategori_id' => 5, // Parkir Liar
                'deskripsi' => 'Banyak sepeda motor parkir memakan seluruh badan trotoar pejalan kaki.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null,
                'segmen_id' => 1,
            ],
            [
                'alamat' => 'Jl. Raya Mulyosari Depan Ruko',
                'lat' => -7.26648,
                'lon' => 112.79640,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Ubin trotoar pecah berserakan setelah proyek galian kabel.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null,
                'segmen_id' => 1,
            ],

            // --- Hotspot 2: Jl. Sutorejo Prima (Medium Density cluster - 2 reports within 30m -> AMBER HOTSPOT) ---
            [
                'alamat' => 'Jl. Sutorejo Prima Blok A',
                'lat' => -7.26685,
                'lon' => 112.79950,
                'kategori_id' => 3, // Guiding Block
                'deskripsi' => 'Jalur ubin pemandu difabel terkelupas dan hilang sepanjang 4 meter.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null,
                'segmen_id' => 2,
            ],
            [
                'alamat' => 'Jl. Sutorejo Prima Depan Taman',
                'lat' => -7.26690,
                'lon' => 112.79965,
                'kategori_id' => 4, // Penyeberangan Rusak
                'deskripsi' => 'Marka zebra cross penyeberangan sudah pudar total tidak terlihat pengendara.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI',
                'segmen_id' => 2,
            ],

            // --- Single Points: Isolated Reports (Low Density - 1 report alone -> GREEN DOT) ---
            [
                'alamat' => 'Jl. Kampus Unair C Gerbang Utama',
                'lat' => -7.27137,
                'lon' => 112.78753,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Trotoar dekat halte bus kampus amblas sebagian setelah hujan lebat.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null,
                'segmen_id' => 14,
            ],
            [
                'alamat' => 'Jl. Pakuwon City Boulevard Barat',
                'lat' => -7.27550,
                'lon' => 112.81050,
                'kategori_id' => 2, // Masalah Drainase
                'deskripsi' => 'Grill besi saluran air di tepi trotoar renggang dan sering menjepit roda kursi roda.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI',
                'segmen_id' => 10,
            ],
            [
                'alamat' => 'Jl. Mulyosari Tengah No. 5',
                'lat' => -7.26550,
                'lon' => 112.79680,
                'kategori_id' => 5, // Parkir Liar
                'deskripsi' => 'Mobil terparkir di atas trotoar menghalangi akses pejalan kaki.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null,
                'segmen_id' => 3,
            ],

            // --- Selesai Diperbaiki (Bukti Perbaikan Nyata Selesai) ---
            [
                'alamat' => 'Jl. Raya Kertajaya Indah Timur No. 34',
                'lat' => -7.27920,
                'lon' => 112.78120,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Paving amblas parah di depan halte busway.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SUDAH_DIPERBAIKI',
                'catatan_perbaikan' => 'Paving amblas telah diganti baru, dipadatkan, dan dicor semen rata oleh Satgas Dinas SDA & Bina Marga. Jalur telah pulih 100% dan ramah difabel.',
                'segmen_id' => 28,
            ],
            [
                'alamat' => 'Jl. Dharmahusada Indah Timur Dekat Kampus',
                'lat' => -7.27050,
                'lon' => 112.78450,
                'kategori_id' => 2, // Masalah Drainase
                'deskripsi' => 'Tutup manhole gorong-gorong patah dan membahayakan pejalan kaki.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SUDAH_DIPERBAIKI',
                'catatan_perbaikan' => 'Tutup saluran air telah diganti plat besi cor baru berstandar keselamatan pedestrian.',
                'segmen_id' => 42,
            ],
        ];

        $recalcSegments = [];

        foreach ($demoReports as $idx => $r) {
            // Find closest segment dynamically
            $closestSegment = DB::selectOne("
                SELECT segmen_id FROM segmen_jalur 
                ORDER BY ST_Distance(geom, ST_SetSRID(ST_Point(?, ?), 4326)::geography) ASC 
                LIMIT 1
            ", [$r['lon'], $r['lat']]);

            $segId = $closestSegment ? $closestSegment->segmen_id : 1;

            // Find or create lokasi
            $lokasiId = DB::table('lokasi')->insertGetId([
                'latitude' => $r['lat'],
                'longitude' => $r['lon'],
                'geom' => DB::raw("ST_SetSRID(ST_Point({$r['lon']}, {$r['lat']}), 4326)"),
                'alamat' => $r['alamat'],
                'segmen_id' => $segId,
            ], 'lokasi_id');

            // Insert Laporan
            $isRepaired = ($r['status_perbaikan'] === 'SUDAH_DIPERBAIKI');
            $lapId = DB::table('laporan')->insertGetId([
                'warga_id' => $warga->warga_id,
                'kategori_id' => $r['kategori_id'],
                'lokasi_id' => $lokasiId,
                'deskripsi' => $r['deskripsi'],
                'status' => $r['status'],
                'status_perbaikan' => $r['status_perbaikan'],
                'catatan_perbaikan' => $r['catatan_perbaikan'] ?? null,
                'foto_perbaikan' => $isRepaired ? $sampleRepairPhoto : null,
                'tanggal_lapor' => now()->subDays(rand(1, 14))->subHours(rand(1, 12)),
                'tanggal_validasi' => now()->subDays(rand(0, 10)),
                'tanggal_perbaikan' => $isRepaired ? now()->subHours(rand(2, 24)) : null,
            ], 'laporan_id');

            // Attach photo
            if ($samplePhoto) {
                DB::table('foto_laporan')->insert([
                    'laporan_id' => $lapId,
                    'url_foto' => $samplePhoto,
                    'tanggal_upload' => now(),
                ]);
            }

            // Insert audit history
            DB::table('riwayat_status_laporan')->insert([
                'laporan_id' => $lapId,
                'jenis' => 'VALIDASI',
                'status' => 'TERVALIDASI',
                'admin_id' => 1,
                'catatan' => 'Laporan diverifikasi valid oleh sistem/admin.',
                'waktu' => now()->subDays(rand(0, 5)),
            ]);

            if ($r['status_perbaikan']) {
                DB::table('riwayat_status_laporan')->insert([
                    'laporan_id' => $lapId,
                    'jenis' => 'PERBAIKAN',
                    'status' => $r['status_perbaikan'],
                    'admin_id' => 1,
                    'catatan' => 'Status perbaikan: ' . $r['status_perbaikan'],
                    'waktu' => now()->subHours(rand(1, 48)),
                ]);
            }

            $recalcSegments[$segId] = true;
        }

        // Recalculate WSM scores
        $scoreService = app(SegmentScoreService::class);
        foreach (array_keys($recalcSegments) as $segId) {
            $scoreService->recalculate($segId);
        }
    }
}
