<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FotoLaporan extends Model
{
    protected $table = 'foto_laporan';
    protected $primaryKey = 'foto_id';
    public $timestamps = false;

    protected $fillable = [
        'laporan_id',
        'url_foto',
        'tanggal_upload',
    ];

    protected $casts = [
        'tanggal_upload' => 'datetime',
    ];

    public function laporan()
    {
        return $this->belongsTo(Laporan::class, 'laporan_id', 'laporan_id');
    }
}
