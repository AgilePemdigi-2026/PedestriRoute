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
            [
                'nama' => 'Rina Kartika',
                'email' => 'rina.kartika@gmail.com',
                'no_hp' => '6281234567894',
                'password' => Hash::make('warga12345'),
            ],
            [
                'nama' => 'Dwi Santoso',
                'email' => 'dwi.santoso@gmail.com',
                'no_hp' => '6281234567895',
                'password' => Hash::make('warga12345'),
            ],
            [
                'nama' => 'Mega Kusuma',
                'email' => 'mega.kusuma@gmail.com',
                'no_hp' => '6281234567896',
                'password' => Hash::make('warga12345'),
            ],
            [
                'nama' => 'Eko Prasetyo',
                'email' => 'eko.prasetyo@gmail.com',
                'no_hp' => '6281234567897',
                'password' => Hash::make('warga12345'),
            ],
            [
                'nama' => 'Maya Indah',
                'email' => 'maya.indah@gmail.com',
                'no_hp' => '6281234567898',
                'password' => Hash::make('warga12345'),
            ],
            [
                'nama' => 'Rizky Ramadhan',
                'email' => 'rizky.ramadhan@gmail.com',
                'no_hp' => '6281234567899',
                'password' => Hash::make('warga12345'),
            ],
            [
                'nama' => 'Hendra Wijaya',
                'email' => 'hendra.wijaya@gmail.com',
                'no_hp' => '6281234567888',
                'password' => Hash::make('warga12345'),
            ],
            [
                'nama' => 'Dewi Lestari',
                'email' => 'dewi.lestari@gmail.com',
                'no_hp' => '6281234567877',
                'password' => Hash::make('warga12345'),
            ],
        ];

        foreach ($wargaList as $w) {
            // Reconcile if another user has this phone number with a different email
            DB::table('warga')
                ->where('no_hp', $w['no_hp'])
                ->where('email', '!=', $w['email'])
                ->update(['no_hp' => '62899' . rand(1000000, 9999999)]);

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
