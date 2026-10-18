<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SegmenJalur extends Model
{
    protected $table = 'segmen_jalur';
    protected $primaryKey = 'segmen_id';
    public $timestamps = false;

    protected $fillable = [
        'osm_way_id',
        'u_node',
        'v_node',
        'nama_jalan',
        'geom',
        'panjang_m',
        'skor_kelayakan',
        'tanggal_update',
    ];

    protected $casts = [
        'panjang_m' => 'float',
        'skor_kelayakan' => 'float',
        'tanggal_update' => 'datetime',
    ];

    public function lokasi()
    {
        return $this->hasMany(Lokasi::class, 'segmen_id', 'segmen_id');
    }
}
