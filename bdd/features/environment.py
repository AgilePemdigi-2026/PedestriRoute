import os
import subprocess
import json
import requests

MESSAGE_CATALOG = {
    "REG_SUCCESS": "Registrasi Berhasil",
    "REG_INVALID": "Data yang Anda isi belum lengkap atau formatnya tidak valid",
    "REG_EMAIL_EXISTS": "Email Sudah Terdaftar.",
    "REG_PHONE_EXISTS": "Nomor Telepon Sudah Terdaftar.",
    "REG_PASSWORD_MISMATCH": "Kata Sandi Tidak Sesuai.",
    "LOGIN_FIELDS_REQUIRED": "Email/nomor telepon dan kata sandi harus diisi",
    "LOGIN_INVALID": "Email/nomor telepon atau kata sandi yang Anda masukkan salah",
    "LOGIN_LOCKED": "Akun terkunci sementara karena terlalu banyak percobaan gagal.",
    "THROTTLED": "Terlalu banyak permintaan.",
    "REPORT_SENT": "Laporan terkirim",
    "REPORT_OUT_OF_AREA": "Lokasi di Luar Area",
    "REPORT_PHOTO_INVALID": "Foto Tidak Valid",
    "REPORT_INCOMPLETE": "Data Laporan Belum Lengkap",
    "REPORT_NO_SEGMENT": "Lokasi terlalu jauh dari jalan yang terdata. Pilih titik yang lebih dekat dengan jalan.",
    "GPS_PERMISSION_DENIED": "Izin lokasi ditolak.",
    "VALIDATION_REASON_REQUIRED": "Alasan penolakan harus diisi sebelum status laporan diperbarui",
    "HEATMAP_NO_DATA": "Data kelayakan jalur belum tersedia untuk area ini.",
    "HEATMAP_FILTER_EMPTY": "Tidak ada data laporan yang sesuai dengan filter.",
    "HEATMAP_ZOOM_IN": "Perbesar peta untuk melihat semua segmen jalur.",
    "REPAIR_STATUS_REQUIRED": "Mohon pilih status perbaikan terlebih dahulu",
    "REPAIR_STATUS_SAVED": "Notifikasi Status Berhasil Diperbarui",
    "REPAIR_SAVE_FAILED": "Pembaruan status gagal disimpan. Silakan coba lagi.",
    "NOTIF_AUTO_VALIDATED": "telah tervalidasi",
    "NOTIF_PENDING_ADMIN": "menunggu validasi Admin",
    "NOTIF_QUEUE_ADMIN": "Ada laporan baru yang perlu divalidasi",
    "NOTIF_VALIDATED": "telah tervalidasi",
    "NOTIF_REJECTED": "ditolak",
    "NOTIF_REPAIRING": "sedang diperbaiki",
    "NOTIF_REPAIRED": "sudah diperbaiki",
}


def run_artisan(cmd: str):
    """Executes php artisan command inside pedestriroute_app container."""
    try:
        parts = ["docker", "exec", "pedestriroute_app", "php", "artisan"] + cmd.split()
        res = subprocess.run(parts, capture_output=True, text=True, timeout=30)
        return res.stdout.strip(), res.stderr.strip(), res.returncode
    except Exception as e:
        return "", str(e), 1


def run_tinker(php_code: str) -> str:
    """Executes PHP expression via tinker inside pedestriroute_app container."""
    try:
        cmd = ["docker", "exec", "pedestriroute_app", "php", "artisan", "tinker", f"--execute={php_code}"]
        res = subprocess.run(cmd, capture_output=True, text=True, timeout=30)
        return res.stdout.strip()
    except Exception as e:
        return f"Error: {e}"


def cleanup_test_data(context):
    php = """
    $wargas = App\\Models\\Warga::whereIn('email', [
        'ahmad.fauzi@gmail.com', 'johnmaps@gmail.com', 'andi.wijaya@gmail.com',
        'otp.warga.test@gmail.com', 'other.cv.warga@pedestriroute.test'
    ])->orWhere('email', 'like', '%test%')->get();

    $wargaIds = $wargas->pluck('warga_id')->all();

    if (!empty($wargaIds)) {
        $laporanIds = Illuminate\\Support\\Facades\\DB::table('laporan')
            ->whereIn('warga_id', $wargaIds)
            ->pluck('laporan_id')->all();

        if (!empty($laporanIds)) {
            // Hapus berkas foto fisik sebelum record DB-nya dihapus.
            $fotos = Illuminate\\Support\\Facades\\DB::table('foto_laporan')
                ->whereIn('laporan_id', $laporanIds)->get();
            foreach ($fotos as $foto) {
                Illuminate\\Support\\Facades\\Storage::disk('public')->delete($foto->url_foto);
            }

            $lokasiIds = Illuminate\\Support\\Facades\\DB::table('laporan')
                ->whereIn('laporan_id', $laporanIds)
                ->pluck('lokasi_id')->all();

            Illuminate\\Support\\Facades\\DB::table('notifikasi')
                ->whereIn('laporan_id', $laporanIds)->delete();
            Illuminate\\Support\\Facades\\DB::table('foto_laporan')
                ->whereIn('laporan_id', $laporanIds)->delete();
            Illuminate\\Support\\Facades\\DB::table('riwayat_status_laporan')
                ->whereIn('laporan_id', $laporanIds)->delete();
            Illuminate\\Support\\Facades\\DB::table('laporan')
                ->whereIn('laporan_id', $laporanIds)->delete();
            Illuminate\\Support\\Facades\\DB::table('lokasi')
                ->whereIn('lokasi_id', $lokasiIds)->delete();
        }

        // Bersihkan token Sanctum milik warga test agar tidak menumpuk.
        Illuminate\\Support\\Facades\\DB::table('personal_access_tokens')
            ->where('tokenable_type', 'App\\\\Models\\\\Warga')
            ->whereIn('tokenable_id', $wargaIds)
            ->delete();
    }

    // Segmen khusus F4 dibuat per skenario; bersihkan setelah suite selesai.
    Illuminate\\Support\\Facades\\DB::table('segmen_jalur')
        ->where('nama_jalan', 'like', 'Jl. BDD F4 %')
        ->delete();
    """
    context.run_tinker(php)


def before_all(context):
    context.base_url = os.environ.get("APP_URL", "http://localhost:8000")
    context.api_url = f"{context.base_url}/api/v1"
    context.message_catalog = MESSAGE_CATALOG
    context.run_artisan = run_artisan
    context.run_tinker = run_tinker

    # Verify server availability
    try:
        resp = requests.get(f"{context.api_url}/categories", timeout=5)
        context.server_ready = (resp.status_code == 200)
    except Exception:
        context.server_ready = False

    cleanup_test_data(context)


def before_scenario(context, scenario):
    context.response = None
    context.response_data = {}
    context.last_message = ""
    context.current_path = "/"
    context.redirect_path = None
    context.warga_token = None
    context.admin_token = None
    context.active_token = None

    # Forms
    context.login_data = {}
    context.register_data = {}
    context.report_data = {}
    context.validation_decision = None
    context.alasan_penolakan = None
    context.current_report = None

    # Feature 4 (Computer Vision) state
    context.analysis_snapshot = None
    context.current_report_id = None
    context.current_report_warga_id = None
    context.current_report_segmen_id = None
    context.current_photo_path = None
    context.initial_segment_score = None
    context.other_report_id = None
    context.other_warga_id = None
    context.cv_mock_label = None
    context.cv_mock_confidence = None
    context.cv_mock_timeout = False

    # Clear rate limiter & cache between scenarios
    run_artisan("cache:clear")


def after_scenario(context, scenario):
    if getattr(context, 'db_trigger_installed', False):
        try:
            php = "DB::unprepared('DROP TRIGGER IF EXISTS fail_riwayat_write ON riwayat_status_laporan; DROP FUNCTION IF EXISTS trigger_fail_write();');"
            context.run_tinker(php)
            context.db_trigger_installed = False
        except Exception:
            pass


def after_all(context):
    cleanup_test_data(context)

