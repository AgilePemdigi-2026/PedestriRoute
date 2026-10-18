<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');

        // 1. Table warga
        DB::statement("
            CREATE TABLE IF NOT EXISTS warga (
                warga_id SERIAL PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NULL,
                no_hp VARCHAR(20) NULL UNIQUE,
                google_id VARCHAR(100) NULL UNIQUE,
                avatar_url VARCHAR(255) NULL,
                tanggal_daftar TIMESTAMPTZ NOT NULL DEFAULT now(),
                gagal_login INT NOT NULL DEFAULT 0,
                terkunci_hingga TIMESTAMPTZ NULL,
                notif_jalur_aktif BOOLEAN NOT NULL DEFAULT TRUE
            );
        ");

        // 2. Table admin
        DB::statement("
            CREATE TABLE IF NOT EXISTS admin (
                admin_id SERIAL PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT 'admin',
                tanggal_dibuat TIMESTAMPTZ NOT NULL DEFAULT now(),
                gagal_login INT NOT NULL DEFAULT 0,
                terkunci_hingga TIMESTAMPTZ NULL
            );
        ");

        // 3. Table kategori_masalah
        DB::statement("
            CREATE TABLE IF NOT EXISTS kategori_masalah (
                kategori_id SERIAL PRIMARY KEY,
                nama_kategori VARCHAR(100) NOT NULL UNIQUE,
                bobot NUMERIC(4,3) NOT NULL
            );
        ");

        // 4. Table segmen_jalur
        DB::statement("
            CREATE TABLE IF NOT EXISTS segmen_jalur (
                segmen_id SERIAL PRIMARY KEY,
                osm_way_id BIGINT NULL,
                u_node BIGINT NULL,
                v_node BIGINT NULL,
                nama_jalan VARCHAR(255) NULL,
                geom GEOGRAPHY(LineString, 4326) NOT NULL,
                panjang_m NUMERIC(10,2) NULL,
                skor_kelayakan NUMERIC(5,2) NOT NULL DEFAULT 100 CHECK (skor_kelayakan BETWEEN 0 AND 100),
                tanggal_update TIMESTAMPTZ NOT NULL DEFAULT now()
            );
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_segmen_jalur_geom ON segmen_jalur USING GIST (geom);");
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS idx_segmen_jalur_osm_nodes ON segmen_jalur (osm_way_id, u_node, v_node);");

        // 5. Table lokasi
        DB::statement("
            CREATE TABLE IF NOT EXISTS lokasi (
                lokasi_id SERIAL PRIMARY KEY,
                latitude DOUBLE PRECISION NOT NULL,
                longitude DOUBLE PRECISION NOT NULL,
                geom GEOGRAPHY(Point, 4326) NOT NULL,
                alamat VARCHAR(255) NULL,
                segmen_id INT REFERENCES segmen_jalur(segmen_id) ON DELETE SET NULL
            );
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_lokasi_geom ON lokasi USING GIST (geom);");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_lokasi_segmen_id ON lokasi (segmen_id);");

        // 6. Table laporan
        DB::statement("
            CREATE TABLE IF NOT EXISTS laporan (
                laporan_id SERIAL PRIMARY KEY,
                warga_id INT NOT NULL REFERENCES warga(warga_id),
                kategori_id INT NOT NULL REFERENCES kategori_masalah(kategori_id),
                lokasi_id INT NOT NULL REFERENCES lokasi(lokasi_id),
                deskripsi TEXT NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'DIPROSES'
                    CHECK (status IN ('DIPROSES','MENUNGGU_VALIDASI_ADMIN','TERVALIDASI','DITOLAK')),
                confidence_score NUMERIC(5,2) NULL,
                cv_label VARCHAR(20) NULL,
                alasan_penolakan TEXT NULL,
                admin_id INT REFERENCES admin(admin_id) ON DELETE SET NULL,
                tanggal_lapor TIMESTAMPTZ NOT NULL DEFAULT now(),
                tanggal_validasi TIMESTAMPTZ NULL,
                status_perbaikan VARCHAR(30) NULL
                    CHECK (status_perbaikan IN ('SEDANG_DIPERBAIKI','SUDAH_DIPERBAIKI')),
                tanggal_perbaikan TIMESTAMPTZ NULL
            );
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_laporan_status_perbaikan ON laporan (status, status_perbaikan);");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_laporan_warga_id ON laporan (warga_id);");

        // 7. Table foto_laporan
        DB::statement("
            CREATE TABLE IF NOT EXISTS foto_laporan (
                foto_id SERIAL PRIMARY KEY,
                laporan_id INT NOT NULL REFERENCES laporan(laporan_id) ON DELETE CASCADE,
                url_foto VARCHAR(255) NOT NULL,
                tanggal_upload TIMESTAMPTZ NOT NULL DEFAULT now()
            );
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_foto_laporan_laporan_id ON foto_laporan (laporan_id);");

        // 8. Table riwayat_status_laporan
        DB::statement("
            CREATE TABLE IF NOT EXISTS riwayat_status_laporan (
                riwayat_id SERIAL PRIMARY KEY,
                laporan_id INT NOT NULL REFERENCES laporan(laporan_id) ON DELETE CASCADE,
                jenis VARCHAR(15) NOT NULL CHECK (jenis IN ('VALIDASI','PERBAIKAN')),
                status VARCHAR(30) NOT NULL,
                admin_id INT REFERENCES admin(admin_id) ON DELETE SET NULL,
                catatan TEXT NULL,
                waktu TIMESTAMPTZ NOT NULL DEFAULT now()
            );
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_riwayat_laporan_id ON riwayat_status_laporan (laporan_id);");

        // 9. Table notifikasi
        DB::statement("
            CREATE TABLE IF NOT EXISTS notifikasi (
                notifikasi_id SERIAL PRIMARY KEY,
                penerima_tipe VARCHAR(10) NOT NULL CHECK (penerima_tipe IN ('WARGA','ADMIN')),
                penerima_id INT NOT NULL,
                tipe VARCHAR(20) NOT NULL CHECK (tipe IN ('STATUS_LAPORAN','KONDISI_JALUR','ANTREAN_VALIDASI')),
                laporan_id INT REFERENCES laporan(laporan_id) ON DELETE SET NULL,
                pesan VARCHAR(255) NOT NULL,
                status_baca VARCHAR(10) NOT NULL DEFAULT 'BELUM' CHECK (status_baca IN ('BELUM','SUDAH')),
                tanggal_kirim TIMESTAMPTZ NOT NULL DEFAULT now()
            );
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_notifikasi_penerima ON notifikasi (penerima_tipe, penerima_id, status_baca);");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS notifikasi CASCADE;");
        DB::statement("DROP TABLE IF EXISTS riwayat_status_laporan CASCADE;");
        DB::statement("DROP TABLE IF EXISTS foto_laporan CASCADE;");
        DB::statement("DROP TABLE IF EXISTS laporan CASCADE;");
        DB::statement("DROP TABLE IF EXISTS lokasi CASCADE;");
        DB::statement("DROP TABLE IF EXISTS segmen_jalur CASCADE;");
        DB::statement("DROP TABLE IF EXISTS kategori_masalah CASCADE;");
        DB::statement("DROP TABLE IF EXISTS admin CASCADE;");
        DB::statement("DROP TABLE IF EXISTS warga CASCADE;");
    }
};
