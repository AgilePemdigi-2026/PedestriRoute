<?php

namespace App\Services;

use App\Exceptions\RouteUnavailableException;
use Illuminate\Support\Facades\DB;
use Throwable;

class RouteReportRepository
{
    /** Load all active reports so detours outside the original corridor are checked too. */
    public function active(): array
    {
        try {
            return DB::table('laporan')
                ->join('lokasi', 'laporan.lokasi_id', '=', 'lokasi.lokasi_id')
                ->leftJoin('kategori_masalah', 'laporan.kategori_id', '=', 'kategori_masalah.kategori_id')
                ->where('laporan.status', 'TERVALIDASI')
                ->where(fn ($query) => $query->whereNull('laporan.status_perbaikan')->orWhere('laporan.status_perbaikan', 'SEDANG_DIPERBAIKI'))
                ->whereNotNull('lokasi.latitude')
                ->whereNotNull('lokasi.longitude')
                ->select('laporan.laporan_id', 'laporan.status_perbaikan', 'kategori_masalah.nama_kategori', 'lokasi.latitude', 'lokasi.longitude', 'lokasi.alamat', 'lokasi.segmen_id')
                ->get()
                ->map(fn ($row): array => [
                    'laporan_id' => (int) $row->laporan_id,
                    'lat' => (float) $row->latitude,
                    'lng' => (float) $row->longitude,
                    'alamat' => $row->alamat ?? 'Lokasi laporan',
                    'kategori' => $row->nama_kategori ?? 'Kerusakan jalur',
                    'segmen_id' => $row->segmen_id,
                    'repair' => $row->status_perbaikan === 'SEDANG_DIPERBAIKI',
                ])->all();
        } catch (Throwable $exception) {
            report($exception);
            throw new RouteUnavailableException('REPORT_DATA_UNAVAILABLE', 'Data laporan tidak dapat diperiksa. Silakan coba lagi.', 503);
        }
    }
}
