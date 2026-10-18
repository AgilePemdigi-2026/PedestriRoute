<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'admin';
    protected $primaryKey = 'admin_id';
    public $timestamps = false;

    protected $fillable = [
        'nama',
        'email',
        'password',
        'role',
        'tanggal_dibuat',
        'gagal_login',
        'terkunci_hingga',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'tanggal_dibuat' => 'datetime',
        'terkunci_hingga' => 'datetime',
        'gagal_login' => 'integer',
    ];

    public function laporanValidasi()
    {
        return $this->hasMany(Laporan::class, 'admin_id', 'admin_id');
    }
}
