<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lokasi extends Model
{
    protected $table = 'lokasi';
    protected $primaryKey = 'lokasi_id';
    public $timestamps = false;

    protected $fillable = [
        'latitude',
        'longitude',
        'geom',
        'alamat',
        'segmen_id',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'segmen_id' => 'integer',
    ];

    public function segmen()
    {
        return $this->belongsTo(SegmenJalur::class, 'segmen_id', 'segmen_id');
    }

    public function laporan()
    {
        return $this->hasOne(Laporan::class, 'lokasi_id', 'lokasi_id');
    }
}
