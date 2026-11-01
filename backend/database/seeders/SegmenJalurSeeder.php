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
            [
                'osm_way_id' => 1004,
                'u_node' => 2004,
                'v_node' => 2005,
                'nama_jalan' => 'Jl. Kejawan Putih Tambak',
                'line_wkt' => 'SRID=4326;LINESTRING(112.8080 -7.2750, 112.8200 -7.2720)',
                'panjang_m' => 1350.00,
                'skor_kelayakan' => 100.00,
            ],
            [
                'osm_way_id' => 1005,
                'u_node' => 2005,
                'v_node' => 2006,
                'nama_jalan' => 'Jl. Kalisari Raya',
                'line_wkt' => 'SRID=4326;LINESTRING(112.7950 -7.2580, 112.8050 -7.2600)',
                'panjang_m' => 1120.00,
                'skor_kelayakan' => 100.00,
            ],
            [
                'osm_way_id' => 1006,
                'u_node' => 2006,
                'v_node' => 2007,
                'nama_jalan' => 'Jl. Kalijudan',
                'line_wkt' => 'SRID=4326;LINESTRING(112.7750 -7.2550, 112.7880 -7.2580)',
                'panjang_m' => 1450.00,
                'skor_kelayakan' => 100.00,
            ],
            [
                'osm_way_id' => 1007,
                'u_node' => 2007,
                'v_node' => 2008,
                'nama_jalan' => 'Jl. Raya Kertajaya Indah',
                'line_wkt' => 'SRID=4326;LINESTRING(112.7750 -7.2780, 112.7850 -7.2800)',
                'panjang_m' => 1130.00,
                'skor_kelayakan' => 100.00,
            ],
            [
                'osm_way_id' => 1008,
                'u_node' => 2008,
                'v_node' => 2009,
                'nama_jalan' => 'Jl. Manyar Sabrangan',
                'line_wkt' => 'SRID=4326;LINESTRING(112.7660 -7.2820, 112.7750 -7.2800)',
                'panjang_m' => 1020.00,
                'skor_kelayakan' => 100.00,
            ],
            [
                'osm_way_id' => 1009,
                'u_node' => 2009,
                'v_node' => 2010,
                'nama_jalan' => 'Jl. Raya Sutorejo',
                'line_wkt' => 'SRID=4326;LINESTRING(112.7880 -7.2650, 112.7980 -7.2630)',
                'panjang_m' => 1100.00,
                'skor_kelayakan' => 100.00,
            ],
            [
                'osm_way_id' => 1010,
                'u_node' => 2010,
                'v_node' => 2011,
                'nama_jalan' => 'Jl. Pakuwon City Boulevard',
                'line_wkt' => 'SRID=4326;LINESTRING(112.8050 -7.2760, 112.8150 -7.2740)',
                'panjang_m' => 1150.00,
                'skor_kelayakan' => 100.00,
            ],
            [
                'osm_way_id' => 1011,
                'u_node' => 2011,
                'v_node' => 2012,
                'nama_jalan' => 'Jl. San Antonio Shopping Street',
                'line_wkt' => 'SRID=4326;LINESTRING(112.8150 -7.2730, 112.8220 -7.2700)',
                'panjang_m' => 840.00,
                'skor_kelayakan' => 100.00,
            ],
            [
                'osm_way_id' => 1012,
                'u_node' => 2012,
                'v_node' => 2013,
                'nama_jalan' => 'Jl. Laguna Regency',
                'line_wkt' => 'SRID=4326;LINESTRING(112.8120 -7.2680, 112.8200 -7.2650)',
                'panjang_m' => 950.00,
                'skor_kelayakan' => 100.00,
            ],
            [
                'osm_way_id' => 1013,
                'u_node' => 2013,
                'v_node' => 2014,
                'nama_jalan' => 'Jl. Dharmahusada Indah Timur',
                'line_wkt' => 'SRID=4326;LINESTRING(112.7780 -7.2720, 112.7950 -7.2660)',
                'panjang_m' => 1980.00,
                'skor_kelayakan' => 100.00,
            ],
            [
                'osm_way_id' => 1014,
                'u_node' => 2014,
                'v_node' => 2015,
                'nama_jalan' => 'Jl. Kampus Unair C',
                'line_wkt' => 'SRID=4326;LINESTRING(112.7820 -7.2720, 112.7880 -7.2700)',
                'panjang_m' => 710.00,
                'skor_kelayakan' => 100.00,
            ],
            [
                'osm_way_id' => 1015,
                'u_node' => 2015,
                'v_node' => 2016,
                'nama_jalan' => 'Jl. Labansari',
                'line_wkt' => 'SRID=4326;LINESTRING(112.7980 -7.2600, 112.8050 -7.2630)',
                'panjang_m' => 830.00,
                'skor_kelayakan' => 100.00,
            ],
            [
                'osm_way_id' => 1016,
                'u_node' => 2016,
                'v_node' => 2017,
                'nama_jalan' => 'Jl. Kalijudan Taruna',
                'line_wkt' => 'SRID=4326;LINESTRING(112.7800 -7.2530, 112.7880 -7.2550)',
                'panjang_m' => 910.00,
                'skor_kelayakan' => 100.00,
            ],
            [
                'osm_way_id' => 1017,
                'u_node' => 2017,
                'v_node' => 2018,
                'nama_jalan' => 'Jl. Mulyorejo Baru',
                'line_wkt' => 'SRID=4326;LINESTRING(112.7800 -7.2680, 112.7880 -7.2660)',
                'panjang_m' => 900.00,
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
