<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KategoriMasalahSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['nama_kategori' => 'Trotoar Rusak', 'bobot' => 0.400],
            ['nama_kategori' => 'Penyeberangan', 'bobot' => 0.250],
            ['nama_kategori' => 'Penerangan Minim', 'bobot' => 0.200],
            ['nama_kategori' => 'Halangan Fisik', 'bobot' => 0.150],
        ];

        foreach ($categories as $cat) {
            DB::table('kategori_masalah')->updateOrInsert(
                ['nama_kategori' => $cat['nama_kategori']],
                ['bobot' => $cat['bobot']]
            );
        }
    }
}
