<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    protected $table = 'notifikasi';
    protected $primaryKey = 'notifikasi_id';
    public $timestamps = false;

    protected $fillable = [
        'penerima_tipe',
        'penerima_id',
        'tipe',
        'laporan_id',
        'pesan',
        'status_baca',
        'tanggal_kirim',
    ];

    protected $casts = [
        'penerima_id' => 'integer',
        'laporan_id' => 'integer',
        'tanggal_kirim' => 'datetime',
    ];

    public function laporan()
    {
        return $this->belongsTo(Laporan::class, 'laporan_id', 'laporan_id');
    }
}
