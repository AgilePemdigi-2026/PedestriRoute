<?php

namespace App\Support;

class MessageCatalog
{
    // Auth & Register Messages (SRS Section 4.5)
    public const REG_SUCCESS = 'Registrasi Berhasil';
    public const REG_INVALID = 'Data yang Anda isi belum lengkap atau formatnya tidak valid';
    public const REG_EMAIL_EXISTS = 'Email Sudah Terdaftar.';
    public const REG_PHONE_EXISTS = 'Nomor Telepon Sudah Terdaftar.';
    public const REG_PASSWORD_MISMATCH = 'Kata Sandi Tidak Sesuai.';
    public const LOGIN_FIELDS_REQUIRED = 'Email/nomor telepon dan kata sandi harus diisi';
    public const LOGIN_INVALID = 'Email/nomor telepon atau kata sandi yang Anda masukkan salah';
    public const LOGIN_LOCKED = 'Akun terkunci sementara karena terlalu banyak percobaan gagal. Coba lagi dalam {menit} menit.';
    public const THROTTLED = 'Terlalu banyak permintaan. Coba lagi beberapa saat lagi.';

    // Report Messages
    public const REPORT_SENT = 'Laporan terkirim';
    public const REPORT_OUT_OF_AREA = 'Lokasi di Luar Area';
    public const REPORT_PHOTO_INVALID = 'Foto Tidak Valid';
    public const REPORT_INCOMPLETE = 'Data Laporan Belum Lengkap';
    public const REPORT_NO_SEGMENT = 'Lokasi terlalu jauh dari jalan yang terdata. Pilih titik yang lebih dekat dengan jalan.';
    public const GPS_PERMISSION_DENIED = 'Izin lokasi ditolak. Aktifkan izin lokasi di pengaturan browser.';

    // Validation & Repair Messages
    public const VALIDATION_REASON_REQUIRED = 'Alasan penolakan harus diisi sebelum status laporan diperbarui';
    public const REPAIR_STATUS_REQUIRED = 'Mohon pilih status perbaikan terlebih dahulu';
    public const REPAIR_STATUS_SAVED = 'Notifikasi Status Berhasil Diperbarui';
    public const REPAIR_SAVE_FAILED = 'Pembaruan status gagal disimpan. Silakan coba lagi.';

    // Heatmap Messages (SRS Section 4.5)
    public const HEATMAP_NO_DATA = 'Data kelayakan jalur belum tersedia untuk area ini.';
    public const HEATMAP_FILTER_EMPTY = 'Tidak ada data laporan yang sesuai dengan filter.';
    public const HEATMAP_ZOOM_IN = 'Perbesar peta untuk melihat semua segmen jalur.';

    // Notification Templates
    public const NOTIF_AUTO_VALIDATED = 'Laporan Anda di {jalan} telah tervalidasi.';
    public const NOTIF_PENDING_ADMIN = 'Laporan Anda di {jalan} menunggu validasi Admin.';
    public const NOTIF_QUEUE_ADMIN = 'Ada laporan baru yang perlu divalidasi: {jalan}.';
    public const NOTIF_VALIDATED = 'Laporan Anda di {jalan} telah tervalidasi.';
    public const NOTIF_REJECTED = 'Laporan Anda di {jalan} ditolak. Alasan: {alasan}.';
    public const NOTIF_REPAIRING = '{jalan} sedang diperbaiki.';
    public const NOTIF_REPAIRED = '{jalan} sudah diperbaiki.';
    public const NOTIF_ROUTE_ALERT = 'Kondisi jalur di {jalan} memburuk. Hati-hati pada rute Anda.';

    public static function format(string $template, array $replacements): string
    {
        $keys = array_map(fn($k) => '{' . $k . '}', array_keys($replacements));
        return str_replace($keys, array_values($replacements), $template);
    }
}
