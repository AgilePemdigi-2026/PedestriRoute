<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Warga extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'warga';
    protected $primaryKey = 'warga_id';
    public $timestamps = false;

    protected $fillable = [
        'nama',
        'email',
        'password',
        'no_hp',
        'google_id',
        'avatar_url',
        'tanggal_daftar',
        'gagal_login',
        'terkunci_hingga',
        'notif_jalur_aktif',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'tanggal_daftar' => 'datetime',
        'terkunci_hingga' => 'datetime',
        'notif_jalur_aktif' => 'boolean',
        'gagal_login' => 'integer',
    ];

    public function laporan()
    {
        return $this->hasMany(Laporan::class, 'warga_id', 'warga_id');
    }
}
