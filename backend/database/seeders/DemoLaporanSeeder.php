<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Warga;
use App\Services\SegmentScoreService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DemoLaporanSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure representative citizen accounts exist
        $citizensData = [
            ['nama' => 'Hendra Wijaya', 'email' => 'hendra.wijaya@gmail.com', 'no_hp' => '6281234567888'],
            ['nama' => 'Budi Pratama', 'email' => 'budi.pratama@gmail.com', 'no_hp' => '6281234567891'],
            ['nama' => 'Siti Aminah', 'email' => 'siti.aminah@gmail.com', 'no_hp' => '6281234567892'],
            ['nama' => 'Dewi Lestari', 'email' => 'dewi.lestari@gmail.com', 'no_hp' => '6281234567877'],
            ['nama' => 'Rina Kartika', 'email' => 'rina.kartika@gmail.com', 'no_hp' => '6281234567894'],
            ['nama' => 'Dwi Santoso', 'email' => 'dwi.santoso@gmail.com', 'no_hp' => '6281234567895'],
            ['nama' => 'Mega Kusuma', 'email' => 'mega.kusuma@gmail.com', 'no_hp' => '6281234567896'],
            ['nama' => 'Eko Prasetyo', 'email' => 'eko.prasetyo@gmail.com', 'no_hp' => '6281234567897'],
            ['nama' => 'Maya Indah', 'email' => 'maya.indah@gmail.com', 'no_hp' => '6281234567898'],
            ['nama' => 'Rizky Ramadhan', 'email' => 'rizky.ramadhan@gmail.com', 'no_hp' => '6281234567899'],
        ];

        $wargaMap = [];
        foreach ($citizensData as $c) {
            $w = Warga::firstOrCreate(
                ['email' => $c['email']],
                [
                    'nama' => $c['nama'],
                    'no_hp' => $c['no_hp'],
                    'password' => Hash::make('warga12345'),
                    'tanggal_daftar' => now()->subDays(rand(30, 90)),
                    'gagal_login' => 0,
                    'notif_jalur_aktif' => true,
                ]
            );
            $wargaMap[$c['email']] = $w->warga_id;
        }

        // Ensure Admin verifier exists
        $admin = Admin::first();
        $adminId = $admin ? $admin->admin_id : 1;

        // 2. Ensure photo assets exist in storage/app/public/reports
        $this->ensureImage('reports/demo_trotoar_rusak.jpg', [15, 76, 92], 'LAPORAN: TROTOAR RUSAK', 'Paving Amblas / Pecah / Berlubang');
        $this->ensureImage('reports/demo_drainase.jpg', [30, 58, 138], 'LAPORAN: MASALAH DRAINASE', 'Tutup Manhole / Gorong-gorong Rusak');
        $this->ensureImage('reports/demo_guiding_block.jpg', [180, 83, 9], 'LAPORAN: GUIDING BLOCK RUSAK', 'Ubin Pemandu Tunanetra Terkelupas');
        $this->ensureImage('reports/demo_penyeberangan.jpg', [16, 120, 110], 'LAPORAN: PENYEBERANGAN RUSAK', 'Marka Zebra Cross Pudar / Hilang');
        $this->ensureImage('reports/demo_parkir_liar.jpg', [190, 18, 60], 'LAPORAN: PARKIR LIAR', 'Kendaraan Memblokir Jalur Trotoar');
        $this->ensureImage('reports/demo_perbaikan_selesai.jpg', [16, 185, 129], 'HASIL PERBAIKAN SELESAI', 'Fasilitas Pulih & Ramah Pejalan Kaki');

        // 3. Clear existing demo reports to prevent duplication when re-seeding
        $demoDescPrefix = '[Demo-Mulyorejo]';
        $oldDemoIds = DB::table('laporan')
            ->where('deskripsi', 'LIKE', $demoDescPrefix . '%')
            ->pluck('laporan_id')
            ->toArray();

        if (!empty($oldDemoIds)) {
            $oldLokasiIds = DB::table('laporan')->whereIn('laporan_id', $oldDemoIds)->pluck('lokasi_id')->toArray();
            DB::table('riwayat_status_laporan')->whereIn('laporan_id', $oldDemoIds)->delete();
            DB::table('foto_laporan')->whereIn('laporan_id', $oldDemoIds)->delete();
            DB::table('laporan')->whereIn('laporan_id', $oldDemoIds)->delete();
            if (!empty($oldLokasiIds)) {
                DB::table('lokasi')->whereIn('lokasi_id', $oldLokasiIds)->delete();
            }
        }

        // 4. Exact Real Street & Sidewalk Nodes from OpenStreetMap (OSM)
        // All points strictly lie on the sidewalk/curb lines of streets in Mulyorejo, Surabaya.
        $reportsData = [
            // =========================================================================
            // CORRIDOR 1: JL. RAYA MULYOSARI (North-to-South Primary Arterial -> RED HOTSPOT)
            // Real OSM nodes along Way 137474440 & 698528193
            // =========================================================================
            [
                'email' => 'hendra.wijaya@gmail.com',
                'alamat' => 'Jl. Raya Mulyosari Simpang Tengah (Depan Ruko)',
                'lat' => -7.263390,
                'lon' => 112.795522,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Paving trotoar amblas sedalam 15 cm dekat pertigaan jalan, sangat licin saat hujan dan berisiko mencelakai pejalan kaki.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_trotoar_rusak.jpg',
            ],
            [
                'email' => 'budi.pratama@gmail.com',
                'alamat' => 'Jl. Raya Mulyosari No. 18 (Trotoar Timur)',
                'lat' => -7.264117,
                'lon' => 112.795627,
                'kategori_id' => 2, // Masalah Drainase
                'deskripsi' => 'Tutup gorong-gorong beton patah dan berlubang di tengah trotoar pejalan kaki, sudah diberi rambu pembatas perbaikan oleh petugas.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI', // Sedang Diperbaiki
                'foto' => 'reports/demo_drainase.jpg',
            ],
            [
                'email' => 'siti.aminah@gmail.com',
                'alamat' => 'Jl. Raya Mulyosari No. 24 (Depan Kawasan Kuliner)',
                'lat' => -7.265206,
                'lon' => 112.795787,
                'kategori_id' => 5, // Parkir Liar
                'deskripsi' => 'Deretan motor pengunjung ruko parkir memakan seluruh badan trotoar pejalan kaki, pejalan kaki terpaksa turun ke badan jalan berlawanan arah.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_parkir_liar.jpg',
            ],
            [
                'email' => 'rina.kartika@gmail.com',
                'alamat' => 'Jl. Raya Mulyosari No. 32 (Trotoar Barat)',
                'lat' => -7.265352,
                'lon' => 112.795814,
                'kategori_id' => 3, // Guiding Block Rusak
                'deskripsi' => 'Ubin pemandu tunanetra (guiding block) berwarna kuning terkelupas dan hilang sepanjang 5 meter setelah pekerjaan galian pipa.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_guiding_block.jpg',
            ],
            [
                'email' => 'dewi.lestari@gmail.com',
                'alamat' => 'Jl. Raya Mulyosari No. 36 (Depan Bank Mandiri)',
                'lat' => -7.266370,
                'lon' => 112.795990,
                'kategori_id' => 4, // Penyebrangan Rusak
                'deskripsi' => 'Marka zebra cross penyeberangan jalan pudar total tidak terlihat pengendara bermotor dari arah timur, sedang dijadwalkan pengecatan ulang.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI', // Sedang Diperbaiki
                'foto' => 'reports/demo_penyeberangan.jpg',
            ],
            [
                'email' => 'hendra.wijaya@gmail.com',
                'alamat' => 'Jl. Raya Mulyosari No. 30 (Trotoar Barat)',
                'lat' => -7.265800,
                'lon' => 112.795880,
                'kategori_id' => 2, // Masalah Drainase
                'deskripsi' => 'Tutup kontrol drainase trotoar pecah melintang membahayakan pejalan kaki.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_drainase.jpg',
            ],
            [
                'email' => 'siti.aminah@gmail.com',
                'alamat' => 'Jl. Raya Mulyosari No. 42 (Trotoar Timur)',
                'lat' => -7.266800,
                'lon' => 112.796060,
                'kategori_id' => 5, // Parkir Liar
                'deskripsi' => 'Mobil terparkir di atas trotoar depan kantor ruko memblokir akses kursi roda.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_parkir_liar.jpg',
            ],
            [
                'email' => 'rizky.ramadhan@gmail.com',
                'alamat' => 'Jl. Raya Mulyosari No. 28 (Trotoar Barat)',
                'lat' => -7.264600,
                'lon' => 112.795700,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Paving trotoar terlepas dan berlubang membahayakan pejalan kaki.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_trotoar_rusak.jpg',
            ],
            [
                'email' => 'maya.indah@gmail.com',
                'alamat' => 'Jl. Raya Mulyosari Simpang Sutorejo (Depan Apotek)',
                'lat' => -7.267253,
                'lon' => 112.796128,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Paving amblas parah membentuk lubang berdiameter 30 cm membahayakan pejalan kaki terutama lansia.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_trotoar_rusak.jpg',
            ],
            [
                'email' => 'dwi.santoso@gmail.com',
                'alamat' => 'Jl. Raya Mulyosari No. 58 (Trotoar Timur)',
                'lat' => -7.268135,
                'lon' => 112.796271,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Ubin trotoar pecah hancur berhamburan dan material kerikil licin berserakan di jalur pedestrian.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI', // Sedang Diperbaiki
                'foto' => 'reports/demo_trotoar_rusak.jpg',
            ],
            [
                'email' => 'eko.prasetyo@gmail.com',
                'alamat' => 'Jl. Raya Mulyosari No. 72 (Depan Supermarket)',
                'lat' => -7.269406,
                'lon' => 112.796508,
                'kategori_id' => 2, // Masalah Drainase
                'deskripsi' => 'Tutup gorong-gorong pedestrian mampet dan rusak menimbulkan genangan air kotor di trotoar.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_drainase.jpg',
            ],

            // =========================================================================
            // CORRIDOR 2: JL. SUTOREJO PRIMA (Residential & Access Road -> AMBER HOTSPOT)
            // Real OSM nodes along Way 639529827
            // =========================================================================
            [
                'email' => 'dwi.santoso@gmail.com',
                'alamat' => 'Jl. Sutorejo Prima Akses Barat',
                'lat' => -7.265187,
                'lon' => 112.795786,
                'kategori_id' => 3, // Guiding Block Rusak
                'deskripsi' => 'Jalur ubin pemandu difabel terputus akibat penanaman tiang lampu di tepi trotoar tanpa penyesuaian marka ubin pengarah.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_guiding_block.jpg',
            ],
            [
                'email' => 'siti.aminah@gmail.com',
                'alamat' => 'Jl. Sutorejo Prima Tengah',
                'lat' => -7.263218,
                'lon' => 112.795515,
                'kategori_id' => 4, // Penyebrangan Rusak
                'deskripsi' => 'Lampu pelican crossing penyeberangan anak sekolah padam dan tombol penyeberangan rusak tidak merespons saat ditekan.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI', // Sedang Diperbaiki
                'foto' => 'reports/demo_penyeberangan.jpg',
            ],
            [
                'email' => 'rina.kartika@gmail.com',
                'alamat' => 'Jl. Sutorejo Prima Blok B',
                'lat' => -7.262412,
                'lon' => 112.795467,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Paving trotoar perumahan amblas dan bergeser setelah dilalui kendaraan perbaikan pipa PDAM.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_trotoar_rusak.jpg',
            ],
            [
                'email' => 'maya.indah@gmail.com',
                'alamat' => 'Jl. Sutorejo Prima Blok C',
                'lat' => -7.260193,
                'lon' => 112.795360,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Ubin trotoar bergelombang dan retak membahayakan pejalan kaki dan pengguna kursi roda.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI', // Sedang Diperbaiki
                'foto' => 'reports/demo_trotoar_rusak.jpg',
            ],
            [
                'email' => 'eko.prasetyo@gmail.com',
                'alamat' => 'Jl. Sutorejo Prima Dekat Lapangan',
                'lat' => -7.259821,
                'lon' => 112.795000,
                'kategori_id' => 2, // Masalah Drainase
                'deskripsi' => 'Grill besi lubang pembuangan trotoar berkarat dan patah separuh.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI', // Sedang Diperbaiki
                'foto' => 'reports/demo_drainase.jpg',
            ],
            [
                'email' => 'hendra.wijaya@gmail.com',
                'alamat' => 'Jl. Sutorejo Prima Ujung Timur',
                'lat' => -7.264996,
                'lon' => 112.795022,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Paving block terangkat oleh akar pohon perindang menyebabkan jalur pejalan kaki bergelombang dan membahayakan disabilitas.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SUDAH_DIPERBAIKI', // Sudah Diperbaiki
                'catatan_perbaikan' => 'Akar pohon telah dirapikan oleh Dinas Lingkungan Hidup, dan pasangan paving block telah dipasang kembali secara rata oleh Satgas Bina Marga.',
                'foto' => 'reports/demo_trotoar_rusak.jpg',
                'foto_perbaikan' => 'reports/demo_perbaikan_selesai.jpg',
            ],

            // =========================================================================
            // CORRIDOR 3: JL. MULYOSARI TENGAH (Real OSM Way 160182567)
            // =========================================================================
            [
                'email' => 'budi.pratama@gmail.com',
                'alamat' => 'Jl. Mulyosari Tengah Simpang Barat',
                'lat' => -7.263867,
                'lon' => 112.796278,
                'kategori_id' => 5, // Parkir Liar
                'deskripsi' => 'Mobil box pengiriman barang bongkar muat parkir melintang di atas trotoar depan pertokoan kelontong.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_parkir_liar.jpg',
            ],
            [
                'email' => 'eko.prasetyo@gmail.com',
                'alamat' => 'Jl. Mulyosari Tengah No. 25',
                'lat' => -7.264695,
                'lon' => 112.796367,
                'kategori_id' => 2, // Masalah Drainase
                'deskripsi' => 'Saluran air terbuka tanpa grill pelindung di samping trotoar, membahayakan pejalan kaki saat malam hari minim penerangan.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SUDAH_DIPERBAIKI', // Sudah Diperbaiki
                'catatan_perbaikan' => 'Grill besi cor pelindung saluran air telah dipasang secara permanen dan kokoh oleh unit pemeliharaan jalan Kota Surabaya.',
                'foto' => 'reports/demo_drainase.jpg',
                'foto_perbaikan' => 'reports/demo_perbaikan_selesai.jpg',
            ],

            // =========================================================================
            // CORRIDOR 4: JL. RAYA SUTOREJO (Real OSM Way 24884763)
            // =========================================================================
            [
                'email' => 'dwi.santoso@gmail.com',
                'alamat' => 'Jl. Raya Sutorejo Dekat Puskesmas',
                'lat' => -7.260752,
                'lon' => 112.790088,
                'kategori_id' => 5, // Parkir Liar
                'deskripsi' => 'Deretan motor parkir liar di trotoar depan tempat bimbingan belajar menghalangi akses pejalan kaki.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_parkir_liar.jpg',
            ],
            [
                'email' => 'siti.aminah@gmail.com',
                'alamat' => 'Jl. Raya Sutorejo No. 82',
                'lat' => -7.259462,
                'lon' => 112.793390,
                'kategori_id' => 2, // Masalah Drainase
                'deskripsi' => 'Tutup gorong-gorong pedestrian depan puskesmas patah berlubang.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SUDAH_DIPERBAIKI', // Sudah Diperbaiki
                'catatan_perbaikan' => 'Tutup gorong-gorong baru telah dipasang dengan plat beton bertulang standar pedestrian ramah pasien lansia dan difabel.',
                'foto' => 'reports/demo_drainase.jpg',
                'foto_perbaikan' => 'reports/demo_perbaikan_selesai.jpg',
            ],

            // =========================================================================
            // CORRIDOR 5: JL. KAMPUS UNAIR C (Real OSM Way 257897270)
            // =========================================================================
            [
                'email' => 'mega.kusuma@gmail.com',
                'alamat' => 'Jl. Kampus Unair C Gerbang Barat',
                'lat' => -7.270225,
                'lon' => 112.783881,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Trotoar amblas sedalam 20 cm setelah hujan deras di akses masuk pejalan kaki halte barat kampus.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI', // Sedang Diperbaiki
                'foto' => 'reports/demo_trotoar_rusak.jpg',
            ],
            [
                'email' => 'mega.kusuma@gmail.com',
                'alamat' => 'Jl. Kampus Unair C Depan Rektorat',
                'lat' => -7.270388,
                'lon' => 112.785239,
                'kategori_id' => 3, // Guiding Block Rusak
                'deskripsi' => 'Ubin pemandu titik berhenti (warning block) di depan penyeberangan zebra cross rektorat pecah terbelah.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_guiding_block.jpg',
            ],
            [
                'email' => 'hendra.wijaya@gmail.com',
                'alamat' => 'Jl. Kampus Unair C Halte Bus Kampus C',
                'lat' => -7.270580,
                'lon' => 112.786774,
                'kategori_id' => 4, // Penyebrangan Rusak
                'deskripsi' => 'Garis penyeberangan zebra cross pudar tertutup debu dan tambalan aspal jalan raya.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SUDAH_DIPERBAIKI', // Sudah Diperbaiki
                'catatan_perbaikan' => 'Pengecatan ulang marka zebra cross dengan cat thermoplastic reflektif telah diselesaikan oleh Dishub Surabaya.',
                'foto' => 'reports/demo_penyeberangan.jpg',
                'foto_perbaikan' => 'reports/demo_perbaikan_selesai.jpg',
            ],

            // =========================================================================
            // CORRIDOR 6: JL. DHARMAHUSADA INDAH TIMUR (Real OSM Way 36043708)
            // =========================================================================
            [
                'email' => 'eko.prasetyo@gmail.com',
                'alamat' => 'Jl. Dharmahusada Indah Timur No. 35',
                'lat' => -7.271911,
                'lon' => 112.781793,
                'kategori_id' => 2, // Masalah Drainase
                'deskripsi' => 'Tutup gorong-gorong manhole jebol berlubang di jalur pedestrian depan pertokoan Dharmahusada.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SUDAH_DIPERBAIKI', // Sudah Diperbaiki
                'catatan_perbaikan' => 'Tutup manhole beton telah diganti baru menggunakan plat besi cor heavy-duty ramah pejalan kaki.',
                'foto' => 'reports/demo_drainase.jpg',
                'foto_perbaikan' => 'reports/demo_perbaikan_selesai.jpg',
            ],
            [
                'email' => 'dewi.lestari@gmail.com',
                'alamat' => 'Jl. Dharmahusada Indah Timur Depan Ruko',
                'lat' => -7.271896,
                'lon' => 112.781867,
                'kategori_id' => 5, // Parkir Liar
                'deskripsi' => 'Pedagang kaki lima dan parkir motor menutupi ramp kursi roda trotoar pedestrian.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_parkir_liar.jpg',
            ],
            [
                'email' => 'maya.indah@gmail.com',
                'alamat' => 'Jl. Dharmahusada Indah Timur Halte',
                'lat' => -7.271930,
                'lon' => 112.781937,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Keramik jalur pedestrian pecah dan lepas berserakan dekat oprit jembatan penghubung.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI', // Sedang Diperbaiki
                'foto' => 'reports/demo_trotoar_rusak.jpg',
            ],

            // =========================================================================
            // CORRIDOR 7: JL. PAKUWON CITY BOULEVARD (Real OSM Way 307282859)
            // =========================================================================
            [
                'email' => 'maya.indah@gmail.com',
                'alamat' => 'Jl. Pakuwon City Boulevard Barat',
                'lat' => -7.274485,
                'lon' => 112.805007,
                'kategori_id' => 2, // Masalah Drainase
                'deskripsi' => 'Grill besi saluran air di tepi trotoar renggang dan sering menjepit roda kursi roda atau tongkat lansia.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI', // Sedang Diperbaiki
                'foto' => 'reports/demo_drainase.jpg',
            ],
            [
                'email' => 'rizky.ramadhan@gmail.com',
                'alamat' => 'Jl. Pakuwon City Boulevard Depan Mall',
                'lat' => -7.275082,
                'lon' => 112.805746,
                'kategori_id' => 5, // Parkir Liar
                'deskripsi' => 'Ojek online mangkal dan parkir di atas trotoar dekat drop-off mall menghalangi difabel.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_parkir_liar.jpg',
            ],
            [
                'email' => 'budi.pratama@gmail.com',
                'alamat' => 'Jl. Pakuwon City Boulevard Timur',
                'lat' => -7.275469,
                'lon' => 112.805864,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Ubin trotoar pecah akibat beban kendaraan proyek perumahan yang melintas di atas pedestrian.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SUDAH_DIPERBAIKI', // Sudah Diperbaiki
                'catatan_perbaikan' => 'Seluruh ubin pecah telah diganti dengan material granit textured anti-selip dan dipasang bollard pembatas kendaraan.',
                'foto' => 'reports/demo_trotoar_rusak.jpg',
                'foto_perbaikan' => 'reports/demo_perbaikan_selesai.jpg',
            ],

            // =========================================================================
            // CORRIDOR 8: JL. SAN ANTONIO SHOPPING STREET (Real OSM Way 450296827)
            // =========================================================================
            [
                'email' => 'maya.indah@gmail.com',
                'alamat' => 'Jl. San Antonio Shopping Street Blok N',
                'lat' => -7.265590,
                'lon' => 112.804027,
                'kategori_id' => 3, // Guiding Block Rusak
                'deskripsi' => 'Ubin pengarah kuning tunanetra terhalang pot tanaman komersial restoran di sepanjang koridor pejalan kaki.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_guiding_block.jpg',
            ],
            [
                'email' => 'rizky.ramadhan@gmail.com',
                'alamat' => 'Jl. San Antonio Shopping Street Dermaga Danau',
                'lat' => -7.265197,
                'lon' => 112.805415,
                'kategori_id' => 4, // Penyebrangan Rusak
                'deskripsi' => 'Ramp penyeberangan menuju area dermaga danau curam dan licin saat hujan.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SUDAH_DIPERBAIKI', // Sudah Diperbaiki
                'catatan_perbaikan' => 'Ramp telah direkonstruksi dengan kemiringan standar ramah disabilitas 1:12 dilengkapi pegangan stainless steel dan tekstur kasar anti-selip.',
                'foto' => 'reports/demo_penyeberangan.jpg',
                'foto_perbaikan' => 'reports/demo_perbaikan_selesai.jpg',
            ],

            // =========================================================================
            // CORRIDOR 9: JL. LAGUNA REGENCY (Real OSM Way 160221010)
            // =========================================================================
            [
                'email' => 'rina.kartika@gmail.com',
                'alamat' => 'Jl. Laguna Regency Blok F',
                'lat' => -7.276463,
                'lon' => 112.812244,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Paving trotoar perumahan amblas berlubang 10 cm tertutup genangan air saat hujan lebat.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_trotoar_rusak.jpg',
            ],
            [
                'email' => 'dwi.santoso@gmail.com',
                'alamat' => 'Jl. Laguna Regency Dekat Club House',
                'lat' => -7.276913,
                'lon' => 112.811481,
                'kategori_id' => 2, // Masalah Drainase
                'deskripsi' => 'Tutup bak kontrol saluran trotoar retak pecah di tikungan jalan perumahan, sedang dilakukan pengerjaan pengecoran.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI', // Sedang Diperbaiki
                'foto' => 'reports/demo_drainase.jpg',
            ],

            // =========================================================================
            // CORRIDOR 10: JL. KALIJUDAN (Real OSM Way 466326082)
            // =========================================================================
            [
                'email' => 'eko.prasetyo@gmail.com',
                'alamat' => 'Jl. Kalijudan No. 104 Widya Mandala',
                'lat' => -7.256638,
                'lon' => 112.779438,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Trotoar sempit banyak lubang dan permukaan paving tidak rata di depan gerbang kampus Kalijudan.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_trotoar_rusak.jpg',
            ],
            [
                'email' => 'dwi.santoso@gmail.com',
                'alamat' => 'Jl. Kalijudan No. 58 Depan Apotek',
                'lat' => -7.255972,
                'lon' => 112.780351,
                'kategori_id' => 2, // Masalah Drainase
                'deskripsi' => 'Air saluran drainase meluap ke atas trotoar pejalan kaki akibat endapan sampah dan sedimen lumpur.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI', // Sedang Diperbaiki
                'foto' => 'reports/demo_drainase.jpg',
            ],

            // =========================================================================
            // CORRIDOR 11: JL. KALISARI RAYA (Real OSM Way 160221067)
            // =========================================================================
            [
                'email' => 'rina.kartika@gmail.com',
                'alamat' => 'Jl. Kalisari Raya No. 45',
                'lat' => -7.263067,
                'lon' => 112.803170,
                'kategori_id' => 4, // Penyebrangan Rusak
                'deskripsi' => 'Zebra cross penyeberangan depan sekolah dasar pudar total, membahayakan anak sekolah saat menyeberang jalan.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_penyeberangan.jpg',
            ],
            [
                'email' => 'siti.aminah@gmail.com',
                'alamat' => 'Jl. Kalisari Raya Dekat Pasar Kalisari',
                'lat' => -7.263999,
                'lon' => 112.803333,
                'kategori_id' => 5, // Parkir Liar
                'deskripsi' => 'Trotoar dipenuhi gerobak pedagang dan motor parkir liar pembeli pasar, penertiban Satpol PP sedang dikoordinasikan.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI', // Sedang Diperbaiki
                'foto' => 'reports/demo_parkir_liar.jpg',
            ],

            // =========================================================================
            // CORRIDOR 12: JL. KEJAWAN PUTIH TAMBAK (Real OSM Way 581070257)
            // =========================================================================
            [
                'email' => 'rizky.ramadhan@gmail.com',
                'alamat' => 'Jl. Kejawan Putih Tambak No. 18',
                'lat' => -7.276811,
                'lon' => 112.802139,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Batu kasting pembatas trotoar dan aspal copot berserakan ke badan jalan.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SUDAH_DIPERBAIKI', // Sudah Diperbaiki
                'catatan_perbaikan' => 'Kasting beton pengunci telah dipasang ulang dan dicor semen reinforced oleh Satgas Pematusan & Bina Marga.',
                'foto' => 'reports/demo_trotoar_rusak.jpg',
                'foto_perbaikan' => 'reports/demo_perbaikan_selesai.jpg',
            ],
            [
                'email' => 'hendra.wijaya@gmail.com',
                'alamat' => 'Jl. Kejawan Putih Tambak Gerbang Regency',
                'lat' => -7.277467,
                'lon' => 112.802373,
                'kategori_id' => 2, // Masalah Drainase
                'deskripsi' => 'Tutup inlet drainase samping trotoar amblas patah akibat terinjak roda kendaraan.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_drainase.jpg',
            ],

            // =========================================================================
            // CORRIDOR 13: JL. RAYA KERTAJAYA INDAH (Real OSM Way 297150513)
            // =========================================================================
            [
                'email' => 'dewi.lestari@gmail.com',
                'alamat' => 'Jl. Raya Kertajaya Indah No. 50',
                'lat' => -7.280675,
                'lon' => 112.778881,
                'kategori_id' => 3, // Guiding Block Rusak
                'deskripsi' => 'Guiding block ubin pemandu terkelupas sepanjang 8 meter di trotoar utama Kertajaya Indah.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI', // Sedang Diperbaiki
                'foto' => 'reports/demo_guiding_block.jpg',
            ],
            [
                'email' => 'budi.pratama@gmail.com',
                'alamat' => 'Jl. Raya Kertajaya Indah Halte Bundaran ITS',
                'lat' => -7.280703,
                'lon' => 112.780834,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Paving trotoar dekat shelter bus amblas membentuk kubangan saat hujan.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SUDAH_DIPERBAIKI', // Sudah Diperbaiki
                'catatan_perbaikan' => 'Paving amblas telah dibongkar, dipadatkan dengan pasir urug dan semen, serta dipasang kembali dengan elevasi sejajar halte bus.',
                'foto' => 'reports/demo_trotoar_rusak.jpg',
                'foto_perbaikan' => 'reports/demo_perbaikan_selesai.jpg',
            ],

            // =========================================================================
            // CORRIDOR 14: JL. LABANSARI & JL. MULYOREJO BARU (Real OSM Ways 160221095 & 36978405)
            // =========================================================================
            [
                'email' => 'mega.kusuma@gmail.com',
                'alamat' => 'Jl. Labansari No. 15',
                'lat' => -7.256154,
                'lon' => 112.792134,
                'kategori_id' => 4, // Penyebrangan Rusak
                'deskripsi' => 'Rambu petunjuk penyeberangan pejalan kaki roboh dan garis zebra cross pudar tertutup debu.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => null, // Aduan Aktif
                'foto' => 'reports/demo_penyeberangan.jpg',
            ],
            [
                'email' => 'eko.prasetyo@gmail.com',
                'alamat' => 'Jl. Mulyorejo Baru Kantor Kelurahan',
                'lat' => -7.264423,
                'lon' => 112.780790,
                'kategori_id' => 1, // Trotoar Rusak
                'deskripsi' => 'Paving trotoar retak dan bergelombang di jalur depan kantor kelurahan, sedang diperbaiki tim satgas bina marga.',
                'status' => 'TERVALIDASI',
                'status_perbaikan' => 'SEDANG_DIPERBAIKI', // Sedang Diperbaiki
                'foto' => 'reports/demo_trotoar_rusak.jpg',
            ],
        ];

        $recalcSegments = [];

        foreach ($reportsData as $r) {
            $wargaId = $wargaMap[$r['email']] ?? array_values($wargaMap)[0];

            // Match closest segment accurately using PostGIS among official Mulyorejo street segments
            $closestSegment = DB::selectOne("
                SELECT segmen_id, nama_jalan 
                FROM segmen_jalur 
                WHERE osm_way_id <= 1020
                ORDER BY ST_Distance(geom, ST_SetSRID(ST_Point(?, ?), 4326)::geography) ASC 
                LIMIT 1
            ", [$r['lon'], $r['lat']]);

            $segId = $closestSegment ? $closestSegment->segmen_id : 1;

            // Create precise lokasi
            $lokasiId = DB::table('lokasi')->insertGetId([
                'latitude' => $r['lat'],
                'longitude' => $r['lon'],
                'geom' => DB::raw("ST_SetSRID(ST_Point({$r['lon']}, {$r['lat']}), 4326)"),
                'alamat' => $r['alamat'],
                'segmen_id' => $segId,
            ], 'lokasi_id');

            $isRepaired = ($r['status_perbaikan'] === 'SUDAH_DIPERBAIKI');
            $repairPhoto = $isRepaired ? ($r['foto_perbaikan'] ?? 'reports/demo_perbaikan_selesai.jpg') : null;

            // Insert Laporan with prefix for easy idempotency
            $lapId = DB::table('laporan')->insertGetId([
                'warga_id' => $wargaId,
                'kategori_id' => $r['kategori_id'],
                'lokasi_id' => $lokasiId,
                'deskripsi' => $demoDescPrefix . ' ' . $r['deskripsi'],
                'status' => $r['status'],
                'status_perbaikan' => $r['status_perbaikan'],
                'catatan_perbaikan' => $r['catatan_perbaikan'] ?? null,
                'foto_perbaikan' => $repairPhoto,
                'tanggal_lapor' => now()->subDays(rand(2, 20))->subHours(rand(1, 12)),
                'tanggal_validasi' => now()->subDays(rand(1, 10)),
                'tanggal_perbaikan' => $isRepaired ? now()->subHours(rand(4, 48)) : null,
            ], 'laporan_id');

            // Attach original citizen photo proof
            DB::table('foto_laporan')->insert([
                'laporan_id' => $lapId,
                'url_foto' => $r['foto'],
                'tanggal_upload' => now()->subDays(rand(2, 20)),
            ]);

            // Insert audit history for validation
            DB::table('riwayat_status_laporan')->insert([
                'laporan_id' => $lapId,
                'jenis' => 'VALIDASI',
                'status' => 'TERVALIDASI',
                'admin_id' => $adminId,
                'catatan' => 'Laporan telah diverifikasi valid di lapangan oleh verifikator Dishub/Satgas.',
                'waktu' => now()->subDays(rand(1, 10)),
            ]);

            // Insert audit history for reparation if in progress or completed
            if ($r['status_perbaikan']) {
                $statusLabel = ($r['status_perbaikan'] === 'SUDAH_DIPERBAIKI') ? 'Perbaikan Selesai 100%' : 'Pengerjaan Perbaikan Sedang Berlangsung';
                DB::table('riwayat_status_laporan')->insert([
                    'laporan_id' => $lapId,
                    'jenis' => 'PERBAIKAN',
                    'status' => $r['status_perbaikan'],
                    'admin_id' => $adminId,
                    'catatan' => $r['catatan_perbaikan'] ?? $statusLabel,
                    'waktu' => now()->subHours(rand(2, 36)),
                ]);
            }

            $recalcSegments[$segId] = true;
        }

        // 5. Recalculate WSM Feasibility Scores for all affected segments
        $scoreService = app(SegmentScoreService::class);
        foreach (array_keys($recalcSegments) as $segId) {
            $scoreService->recalculate((int) $segId);
        }
    }

    /**
     * Create placeholder photo with text header if it does not already exist.
     */
    private function ensureImage(string $path, array $bgRgb, string $title, string $subtitle): void
    {
        if (Storage::disk('public')->exists($path)) {
            return;
        }

        $width = 640;
        $height = 420;
        $img = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($img, $bgRgb[0], $bgRgb[1], $bgRgb[2]);
        imagefill($img, 0, 0, $bg);

        // Header banner bar
        $bannerColor = imagecolorallocate($img, 15, 23, 42); // slate-900
        imagefilledrectangle($img, 0, 0, $width, 50, $bannerColor);

        $white = imagecolorallocate($img, 255, 255, 255);
        $light = imagecolorallocate($img, 226, 232, 240);
        $accent = imagecolorallocate($img, 251, 191, 36); // amber-400

        imagestring($img, 5, 20, 16, "PEDESTRIROUTE SURABAYA - BUKTI FASILITAS", $accent);
        imagestring($img, 5, 20, 80, $title, $white);
        imagestring($img, 4, 20, 120, $subtitle, $light);
        imagestring($img, 3, 20, 160, "Kecamatan Mulyorejo, Kota Surabaya", $light);
        imagestring($img, 3, 20, $height - 30, "Terverifikasi oleh Sistem PedestriRoute", $white);

        ob_start();
        imagejpeg($img, null, 90);
        $data = ob_get_clean();
        Storage::disk('public')->put($path, $data);
        imagedestroy($img);
    }
}
