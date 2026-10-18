<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SegmenJalurSeeder extends Seeder
{
    public function run(): void
    {
        $segments = [
            [
                'osm_way_id' => 1001,
                'u_node' => 2001,
                'v_node' => 2002,
                'nama_jalan' => 'Jl. Raya Mulyosari',
                'line_wkt' => 'SRID=4326;LINESTRING(112.7950 -7.2660, 112.7980 -7.2665)',
                'panjang_m' => 335.50,
                'skor_kelayakan' => 100.00,
            ],
            [
                'osm_way_id' => 1002,
                'u_node' => 2002,
                'v_node' => 2003,
                'nama_jalan' => 'Jl. Sutorejo Prima',
                'line_wkt' => 'SRID=4326;LINESTRING(112.7980 -7.2665, 112.8010 -7.2670)',
                'panjang_m' => 335.20,
                'skor_kelayakan' => 100.00,
            ],
            [
                'osm_way_id' => 1003,
                'u_node' => 2003,
                'v_node' => 2004,
                'nama_jalan' => 'Jl. Mulyosari Tengah',
                'line_wkt' => 'SRID=4326;LINESTRING(112.7960 -7.2650, 112.7970 -7.2680)',
                'panjang_m' => 350.00,
                'skor_kelayakan' => 100.00,
            ],
        ];

        foreach ($segments as $seg) {
            DB::statement("
                INSERT INTO segmen_jalur (osm_way_id, u_node, v_node, nama_jalan, geom, panjang_m, skor_kelayakan, tanggal_update)
                VALUES (
                    ?, ?, ?, ?,
                    ST_GeogFromText(?),
                    ?, ?, now()
                )
                ON CONFLICT (osm_way_id, u_node, v_node) DO UPDATE SET
                    nama_jalan = EXCLUDED.nama_jalan,
                    geom = EXCLUDED.geom,
                    panjang_m = EXCLUDED.panjang_m;
            ", [
                $seg['osm_way_id'],
                $seg['u_node'],
                $seg['v_node'],
                $seg['nama_jalan'],
                $seg['line_wkt'],
                $seg['panjang_m'],
                $seg['skor_kelayakan']
            ]);
        }
    }
}
