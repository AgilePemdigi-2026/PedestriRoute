<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiwayatStatusLaporan extends Model
{
    protected $table = 'riwayat_status_laporan';
    protected $primaryKey = 'riwayat_id';
    public $timestamps = false;

    protected $fillable = [
        'laporan_id',
        'jenis',
        'status',
        'admin_id',
        'catatan',
        'waktu',
    ];

    protected $casts = [
        'waktu' => 'datetime',
    ];

    public function laporan()
    {
        return $this->belongsTo(Laporan::class, 'laporan_id', 'laporan_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'admin_id');
    }
}
