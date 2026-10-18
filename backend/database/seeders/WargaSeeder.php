<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class WargaSeeder extends Seeder
{
    public function run(): void
    {
        $wargaList = [
            [
                'nama' => 'Ahmad Fauzi',
                'email' => 'ahmad.fauzi@gmail.com',
                'no_hp' => '6281234567890',
                'password' => Hash::make('warga12345'),
            ],
            [
                'nama' => 'Budi Pratama',
                'email' => 'budi.pratama@gmail.com',
                'no_hp' => '6281234567891',
                'password' => Hash::make('warga12345'),
            ],
            [
                'nama' => 'Siti Aminah',
                'email' => 'siti.aminah@gmail.com',
                'no_hp' => '6281234567892',
                'password' => Hash::make('warga12345'),
            ],
            [
                'nama' => 'John Maps',
                'email' => 'johnmaps@gmail.com',
                'no_hp' => '6281234567893',
                'password' => Hash::make('warga12345'),
            ],
        ];

        foreach ($wargaList as $w) {
            DB::table('warga')->updateOrInsert(
                ['email' => $w['email']],
                [
                    'nama' => $w['nama'],
                    'no_hp' => $w['no_hp'],
                    'password' => $w['password'],
                    'tanggal_daftar' => now(),
                    'gagal_login' => 0,
                    'terkunci_hingga' => null,
                    'notif_jalur_aktif' => true,
                ]
            );
        }
    }
}
