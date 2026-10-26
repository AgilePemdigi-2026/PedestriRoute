<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KategoriMasalahSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'kategori_id' => 1,
                'kode_label' => 0,
                'nama_kategori' => 'Trotoar Rusak',
                'deskripsi' => 'Paving amblas, paving pecah, retak beton, lubang trotoar',
                'bobot' => 0.300,
            ],
            [
                'kategori_id' => 2,
                'kode_label' => 1,
                'nama_kategori' => 'Masalah Drainase',
                'deskripsi' => 'Tutup gorong-gorong/manhole rusak, patah, atau terbuka',
                'bobot' => 0.250,
            ],
            [
                'kategori_id' => 3,
                'kode_label' => 2,
                'nama_kategori' => 'Guiding Block Rusak',
                'deskripsi' => 'Ubin pemandu tunanetra terkelupas, hilang, atau pecah',
                'bobot' => 0.150,
            ],
            [
                'kategori_id' => 4,
                'kode_label' => 3,
                'nama_kategori' => 'Penyebrangan Rusak',
                'deskripsi' => 'Marka garis putih aus/pudar, atau penyeberangan rusak',
                'bobot' => 0.150,
            ],
            [
                'kategori_id' => 5,
                'kode_label' => 4,
                'nama_kategori' => 'Parkir Liar',
                'deskripsi' => 'Sepeda motor atau mobil di atas fasilitas pejalan kaki',
                'bobot' => 0.150,
            ],
        ];

        // Delete any category beyond 5 if unreferenced
        DB::table('kategori_masalah')->where('kategori_id', '>', 5)->delete();

        foreach ($categories as $cat) {
            DB::table('kategori_masalah')->updateOrInsert(
                ['kategori_id' => $cat['kategori_id']],
                [
                    'kode_label' => $cat['kode_label'],
                    'nama_kategori' => $cat['nama_kategori'],
                    'deskripsi' => $cat['deskripsi'],
                    'bobot' => $cat['bobot'],
                ]
            );
        }
    }
}
