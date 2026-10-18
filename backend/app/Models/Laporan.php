<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Laporan extends Model
{
    protected $table = 'laporan';
    protected $primaryKey = 'laporan_id';
    public $timestamps = false;

    protected $fillable = [
        'warga_id',
        'kategori_id',
        'lokasi_id',
        'deskripsi',
        'status',
        'confidence_score',
        'cv_label',
        'alasan_penolakan',
        'admin_id',
        'tanggal_lapor',
        'tanggal_validasi',
        'status_perbaikan',
        'tanggal_perbaikan',
    ];

    protected $casts = [
        'confidence_score' => 'float',
        'tanggal_lapor' => 'datetime',
        'tanggal_validasi' => 'datetime',
        'tanggal_perbaikan' => 'datetime',
    ];

    public function warga()
    {
        return $this->belongsTo(Warga::class, 'warga_id', 'warga_id');
    }

    public function kategori()
    {
        return $this->belongsTo(KategoriMasalah::class, 'kategori_id', 'kategori_id');
    }

    public function lokasi()
    {
        return $this->belongsTo(Lokasi::class, 'lokasi_id', 'lokasi_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'admin_id');
    }

    public function foto()
    {
        return $this->hasOne(FotoLaporan::class, 'laporan_id', 'laporan_id');
    }

    public function riwayat()
    {
        return $this->hasMany(RiwayatStatusLaporan::class, 'laporan_id', 'laporan_id')->orderBy('waktu', 'asc');
    }

    public function notifikasi()
    {
        return $this->hasMany(Notifikasi::class, 'laporan_id', 'laporan_id');
    }
}
