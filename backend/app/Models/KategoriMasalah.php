<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KategoriMasalah extends Model
{
    protected $table = 'kategori_masalah';
    protected $primaryKey = 'kategori_id';
    public $timestamps = false;

    protected $fillable = [
        'nama_kategori',
        'bobot',
    ];

    protected $casts = [
        'bobot' => 'float',
    ];

    public function laporan()
    {
        return $this->hasMany(Laporan::class, 'kategori_id', 'kategori_id');
    }
}
