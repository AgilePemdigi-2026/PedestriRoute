<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            [
                'nama' => env('ADMIN_DEFAULT_NAME', 'Administrator PedestriRoute'),
                'email' => strtolower(env('ADMIN_DEFAULT_EMAIL', 'admin@pedestriroute.test')),
                'password' => Hash::make(env('ADMIN_DEFAULT_PASSWORD', 'adminpassword123')),
                'role' => 'admin',
            ],
            [
                'nama' => 'Petugas Verifikasi Dishub',
                'email' => 'validator@pedestriroute.test',
                'password' => Hash::make('admin12345'),
                'role' => 'admin',
            ],
        ];

        foreach ($admins as $admin) {
            DB::table('admin')->updateOrInsert(
                ['email' => $admin['email']],
                [
                    'nama' => $admin['nama'],
                    'password' => $admin['password'],
                    'role' => $admin['role'],
                    'gagal_login' => 0,
                    'terkunci_hingga' => null,
                ]
            );
        }
    }
}
