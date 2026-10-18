# 🚶 PedestriRoute

**PedestriRoute** adalah platform Web GIS pemantauan kondisi fasilitas pejalan kaki dan sistem pendukung keputusan rute jalan kaki yang ramah, aman, dan inklusif di Kota Surabaya (dengan fokus area awal wilayah Mulyosari dan sekitarnya).

Proyek ini dirancang untuk memberdayakan partisipasi aktif warga dalam melaporkan berbagai kerusakan maupun halangan fisik pada fasilitas pejalan kaki, yang kemudian diverifikasi secara administratif dan memengaruhi perhitungan skor kelayakan jalur jalan secara dinamis.

---

## 🌟 Fitur Utama (Sprint 1)

1. **Autentikasi & Registrasi Fleksibel (F1 & F2):**
   - Masuk (*login*) menggunakan **Email** atau **Nomor Telepon** (mendukung berbagai format penulisan lokal `08...`, `+628...`, `628...`).
   - Pendaftaran akun Warga baru dengan enkripsi kata sandi yang aman.
   - Fitur proteksi penguncian akun otomatis setelah 5 kali percobaan gagal dan pembatasan laju (*rate limiting*).
2. **Integrasi Google OAuth 2.0:**
   - Memungkinkan warga masuk dengan satu ketukan menggunakan akun Google.
3. **Pelaporan Kondisi Fasilitas Pejalan Kaki (F3):**
   - Penandaan lokasi presisi berbasis peta interaktif OpenStreetMap / Leaflet dan geolokasi GPS perangkat.
   - Validasi geospasial (*PostGIS Snapping*) yang memastikan laporan terikat pada segmen jalan dalam radius 50 meter.
   - Unggah foto bukti dengan kompresi otomatis di sisi peramban dan pembersihan metadata EXIF/GPS demi privasi warga.
   - Pilihan 4 kategori masalah: *Trotoar Rusak*, *Penyeberangan*, *Penerangan Minim*, dan *Halangan Fisik*.
4. **Validasi Laporan Manual oleh Admin (F5):**
   - Dasbor antrean verifikasi untuk admin/petugas berwenang (Dishub).
   - Tampilan rincian laporan lengkap dengan foto beresolusi tinggi, pratinjau peta, dan catatan sistem analisis visual.
   - Eksekusi keputusan *Valid* atau *Tidak Valid* (dengan kewajiban mencantumkan alasan penolakan).
   - Perhitungan ulang skor kelayakan segmen jalan otomatis menggunakan algoritma **Weighted Sum Model (WSM)** saat laporan dinyatakan valid.
5. **Beranda Warga Interaktif (Home Screen):**
   - Ringkasan status aduan warga secara waktu nyata (*Menunggu*, *Valid*, *Ditolak*).
   - Peta dasar Leaflet untuk pemantauan rute pejalan kaki.

---

## 🛠️ Tech Stack

| Komponen | Teknologi | Deskripsi |
| :--- | :--- | :--- |
| **Backend REST API** | Laravel 12/13 (PHP 8.3) | Framework API, routing, kontrol akses peran, Sanctum, dan Socialite |
| **Basis Data Spasial** | PostgreSQL 16 + PostGIS 3.4 | Penyimpanan data relasional dan operasi geometris spasial (`LineString`, `Point`) |
| **Frontend** | Blade + Tailwind CSS + Alpine.js | Antarmuka pengguna *mobile-first* yang cepat dan ringan |
| **Peta Digital** | Leaflet.js + OpenStreetMap | Visualisasi peta dan interaksi titik lokasi |
| **Web Server & Reverse Proxy** | Nginx (Alpine) | Penanganan lalu lintas HTTP, reverse proxy FastCGI, dan kompresi gzip |
| **Orkestrasi Lingkungan** | Docker Compose | Menjamin konsistensi lingkungan pengembangan antar pengembang |

---

## 📁 Struktur Repositori

```text
PedestriRoute/
├── backend/                  # Aplikasi Laravel (API Controllers, Models, Views, Migrations)
│   ├── app/                  # Logika aplikasi dan domain services
│   ├── database/             # Migrasi tabel PostGIS dan seeder data awal
│   ├── resources/views/      # Tampilan antarmuka Blade
│   └── tests/                # Pengujian otomatis berbasis skenario fitur
├── infra/                    # Konfigurasi container Docker
│   ├── docker/               # Dockerfile PHP-FPM dan konfigurasi vhost Nginx
│   └── docker-compose.yml    # Definisi service: db (PostGIS), app (PHP), nginx
├── README.md                 # Informasi umum proyek
└── .gitignore                # Aturan pengecualian berkas rahasia & lokal
```
