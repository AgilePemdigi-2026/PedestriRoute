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
        'deskripsi',
        'kode_label',
        'bobot',
    ];

    protected $casts = [
        'kategori_id' => 'integer',
        'kode_label' => 'integer',
        'bobot' => 'float',
    ];

    public function laporan()
    {
        return $this->hasMany(Laporan::class, 'kategori_id', 'kategori_id');
    }
}
