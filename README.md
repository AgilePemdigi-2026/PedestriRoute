# 🚶 PedestriRoute

**PedestriRoute** adalah platform Web GIS pemantauan kondisi fasilitas pejalan kaki dan sistem pendukung keputusan rute jalan kaki yang ramah, aman, dan inklusif di Kota Surabaya (dengan koridor percontohan wilayah Kecamatan Mulyorejo dan sekitarnya).

Platform ini dirancang untuk memberdayakan partisipasi aktif warga dalam melaporkan berbagai kerusakan maupun halangan fisik pada fasilitas pejalan kaki. Setiap aduan dianalisis secara cerdas oleh layanan **Computer Vision (FastAPI)** dan/atau diverifikasi administratif oleh petugas berwenang, yang secara dinamis memengaruhi perhitungan skor kelayakan jalur pejalan kaki menggunakan algoritma **Weighted Sum Model (WSM)**.

---

## 🌟 Fitur Utama

### 🎯 Sprint 1
1. **Autentikasi & Registrasi Fleksibel (F1 & F2):**
   - Masuk (*login*) menggunakan **Email** atau **Nomor Telepon** (mendukung berbagai format penulisan lokal `08...`, `+628...`, `628...`).
   - Lapisan keamanan **Two-Step Verification (Email OTP)** 6-digit untuk perlindungan akun warga.
   - Pendaftaran akun Warga baru dengan enkripsi kata sandi yang aman.
   - Proteksi penguncian akun otomatis setelah 5 kali percobaan gagal dan pembatasan laju (*rate limiting*).
2. **Multiple Social Login (Google & Facebook OAuth 2.0):**
   - Memungkinkan warga masuk atau mendaftar dengan satu ketukan menggunakan akun Google maupun Facebook OAuth.
3. **Pelaporan Kondisi Fasilitas Pejalan Kaki (F3):**
   - Penandaan lokasi presisi berbasis peta interaktif Leaflet, reverse geocoding OpenStreetMap Nominatim, dan geolokasi GPS perangkat.
   - Validasi geospasial (*PostGIS Snapping*) yang memastikan laporan terikat pada segmen jalan dalam radius 50 meter.
   - Unggah foto bukti kamera langsung atau galeri (hingga 15MB) dengan pembersihan metadata EXIF demi privasi warga.
   - Pilihan kategori masalah: *Trotoar Rusak*, *Drainase / Penutup Saluran*, *Guiding Block Disabilitas*, *Penerangan Minim*, dan *Halangan Fisik / Parkir Liar*.
4. **Validasi Laporan Manual oleh Admin (F5):**
   - Dasbor antrean verifikasi untuk admin/petugas dinas terkait.
   - Tampilan rincian laporan lengkap dengan foto resolusi tinggi, peta mini interaktif, dan riwayat status.
   - Eksekusi keputusan *Valid* atau *Tidak Valid* (dengan kewajiban mencantumkan alasan penolakan).
   - Perhitungan ulang skor kelayakan segmen jalan otomatis menggunakan algoritma **Weighted Sum Model (WSM)** saat laporan dinyatakan valid.
5. **Beranda Warga Interaktif (Home Screen):**
   - Ringkasan status aduan warga secara waktu nyata (*Menunggu*, *Valid*, *Ditolak*).
   - Peta interaktif Leaflet dengan batas wilayah administratif resmi Kecamatan Mulyorejo yang bersih dan presisi.

---

### 🚀 Sprint 2
6. **Validasi Laporan Otomatis dengan Computer Vision (F4):**
   - **Microservice FastAPI Mandiri (`cv-service`):** Berjalan di container Docker terisolasi port 8001 dengan runtime inferensi *ONNX Runtime*.
   - **Arsitektur 5 Model Spesialis YOLOv8s-cls (384x384):** Menggunakan model klasifikasi terlatih terpisah untuk setiap kategori fasilitas guna mencapai akurasi tinggi:
     - *Trotoar Rusak* (94.5% val acc)
     - *Drainase / Penutup Saluran* (89.5% val acc — mendukung manhole bulat dan manhole kotak besi cor Surabaya)
     - *Guiding Block Disabilitas* (99.6% val acc — jalur braille terkelupas/hilang)
     - *Parkir Liar / Hambatan Trotoar* (97.8% val acc — klasifikasi khusus berlabel **`TERHALANG`**)
     - *Penyebrangan / Zebra Cross* (96.7% val acc — marka pudar/rusak)
   - **Multi-Crop Spatial Pooling:** Teknik pemotongan sub-area citra (*bottom half*, *lower center*) untuk mendeteksi retakan lokal tanpa tertipu oleh bayangan pohon lebat.
   - **Pemrosesan Asinkron via Queue Worker (`ProcessReportCv`):** Job queue worker Laravel membaca foto dan mengevaluasi status secara asinkron dengan proteksi idempotensi dan timeout 10 detik.
   - **Auto-Validasi & Triage Cerdas:**
     - Jika terdeteksi cacat fisik (`RUSAK` atau `TERHALANG`) dengan tingkat keyakinan $\ge 80\%$, laporan otomatis berstatus `TERVALIDASI` dan memicu kalkulasi pemulihan skor jalan WSM.
     - Jika keyakinan $< 80\%$ atau terdeteksi `BAIK` / `SPAM`, laporan dialihkan aman ke antrean verifikasi manual admin (`MENUNGGU_VALIDASI_ADMIN`).
   - **Antarmuka Analisis Warga (`/laporan/{id}/analisis`):** Menampilkan kartu analisis visual, animasi bilah progres evaluasi, badge status verifikasi, persentase keyakinan, dan mekanisme *auto-polling* berkala setiap 3 detik.
7. **Peta Heatmap Kelayakan Pejalan Kaki (F6):**
   - Endpoint spasial PostGIS berperforma tinggi (`GET /api/v1/heatmap`) dengan filter *spatial bounding box* viewport (`ST_Intersects` + `ST_MakeEnvelope`) dan simplifikasi geometri adaptif (`ST_Simplify`).
   - Visualisasi Leaflet Canvas dengan **Dynamic Density Heat Dots**:
     - 🟢 *Kepadatan Rendah (1 Aduan)*: Titik hijau dengan halo radial lembut.
     - 🟡 *Kepadatan Sedang (2–3 Aduan)*: Titik kuning/oranye dengan badge jumlah.
     - 🔴 *Kepadatan Tinggi (≥ 4 Aduan)*: Titik merah berdenyut (*heat pulse*) penanda hotspot rawan.
     - 🟢 *Selesai Diperbaiki*: Titik hijau zamrud dengan ikon centang (`✓`).
   - **Modal Bukti Perbaikan Transparan (*Proof of Reparation*):** Komparasi visual berdampingan foto *Before vs After*, nama verifikator, tanggal selesai, dan catatan dinas.
   - Filter interaktif kategori fasilitas dan status perbaikan (*Aduan Aktif*, *Sudah Diperbaiki*, *Semua Status*).
8. **Pembaruan Status Validasi & Perbaikan Laporan (F9):**
   - State machine transisi perbaikan: `TERVALIDASI` $\rightarrow$ `SEDANG_DIPERBAIKI` $\rightarrow$ `SUDAH_DIPERBAIKI` dengan proteksi *concurrency lock* (`lockForUpdate()`).
   - Unggah bukti fisik foto pengerjaan lapangan (`foto_perbaikan`) dan catatan teknis dinas.
   - **Pemulihan Otomatis Skor Jalan (WSM):** Saat status berubah menjadi `SUDAH_DIPERBAIKI`, skor kelayakan segmen jalan otomatis pulih kembali ke kondisi prima (100).
   - Antarmuka manajemen perbaikan admin di `/admin/status-laporan` dan pengiriman notifikasi pembaruan ke warga.

---

### 🔮 Sprint 3 (Roadmap Selanjutnya)
9. **Rekomendasi Rute Pejalan Kaki Ramah Disabilitas (F7):** Pencarian rute pejalan kaki terpendek dan teraman berbasis skor kelayakan jalan WSM dan preferensi difabel.
10. **Sistem Navigasi Turn-by-Turn (F8):** Panduan navigasi langkah demi langkah berbasis koordinat GPS pengguna.
11. **Sistem Notifikasi Terpadu (F10):** Notifikasi *push real-time* menggunakan protokol WebSocket (Laravel Reverb).

---

## 🛠️ Tech Stack

| Lapisan Sistem | Teknologi / Pustaka | Deskripsi & Peran |
| :--- | :--- | :--- |
| **Backend REST API** | **Laravel 12 (PHP 8.3)** | Routing RESTful, State Machine, Eloquent ORM, Autentikasi Sanctum & Socialite, WSM Engine |
| **Basis Data Spasial** | **PostgreSQL 16 + PostGIS 3.4** | Penyimpanan spasial, pengindeksan GIST, operasi geospasial (`ST_Intersects`, `ST_Snap`, `ST_Distance`) |
| **Microservice AI / CV** | **FastAPI + Python 3.11 + ONNX Runtime** | Microservice mandiri inferensi citra berkecepatan tinggi (~20-50ms) menggunakan CPU Execution Provider |
| **Arsitektur Model CV** | **Ultralytics YOLOv8s-cls (384x384)** | 5 model klasifikasi spesialis per kategori fasilitas dengan spatial pooling adaptif |
| **Antrean Asinkron (Queue)** | **Laravel Database Queue Worker** | Pemrosesan background job asinkron (`ProcessReportCv`) dalam container `pedestriroute_queue` |
| **Frontend & Templating** | **Blade + Tailwind CSS + Alpine.js** | Antarmuka pengguna *mobile-first* yang responsif, interaktif, dan ringan |
| **Peta Digital & GIS** | **Leaflet.js (Canvas) + OSM Nominatim** | Visualisasi peta, canvas renderer, cluster density heat dots, dan reverse geocoding alamat |
| **Web Server & Proxy** | **Nginx (Alpine)** | Reverse proxy HTTP/FastCGI, penanganan berkas statis, dan kompresi gzip |
| **Pengujian Otomatis** | **PHPUnit (68 tests) + Behave BDD (10 scenarios)** | Pengujian fitur, unit testing (911 assertions), serta pengujian skenario penerimaan berbasis Gherkin |
| **Orkestrasi Lingkungan** | **Docker Compose** | Manajemen container multi-layanan (`db`, `app`, `nginx`, `queue`, `cv-service`) |

---

## 🤖 Spesifikasi Model Computer Vision

Model inferensi dibekukan ke format **ONNX** dan disimpan di direktori [`cv-service/models/`](cv-service/models/):

| Kategori Fasilitas | Berkas Model ONNX | Resolusi Input | Akurasi Validasi | Karakteristik Khusus |
| :--- | :--- | :---: | :---: | :--- |
| **Trotoar Rusak** | `trotoar_classifier.onnx` | 384 $\times$ 384 | **94.5%** | Mendeteksi paving pecah, ambles, lubang, dan permukaan bergelombang dengan multi-crop spatial pooling. |
| **Drainase / Saluran** | `drainase_classifier.onnx` | 384 $\times$ 384 | **89.5%** | Mendukung penutup manhole bulat dan manhole kotak besi cor (Dinas Bina Marga & Pelindo III Surabaya). |
| **Guiding Block** | `guiding_block_classifier.onnx` | 384 $\times$ 384 | **99.6%** | Jalur difabel braille rusak, terkelupas, atau terputus dengan proteksi terhadap bayangan tajam. |
| **Parkir Liar** | `parkir_liar_classifier.onnx` | 384 $\times$ 384 | **97.8%** | Hambatan fisik trotoar dengan label khusus **`TERHALANG`**. Mobil melintas di jalan raya tidak dianggap melanggar. |
| **Penyebrangan Jalan** | `penyebrangan_classifier.onnx` | 384 $\times$ 384 | **96.7%** | Marka zebra cross yang pudar, terkelupas, atau aus yang membahayakan penyeberang jalan. |

---

## 📁 Struktur Repositori

```text
PedestriRoute/
├── backend/                       # Aplikasi Utama Laravel 12
│   ├── app/                       # Controllers, Models, Jobs (ProcessReportCv), Services
│   ├── database/                  # Migrasi tabel PostGIS, Seeder (DemoLaporanSeeder)
│   ├── resources/views/           # Antarmuka Warga & Admin (Blade, Tailwind CSS, Alpine.js)
│   └── tests/                     # 68 Skenario PHPUnit Automated Tests (Feature & Unit)
├── cv-service/                    # Microservice Computer Vision (FastAPI)
│   ├── app/                       # Logika routing inferensi & spatial pooling (main.py)
│   ├── models/                    # 5 Model Spesialis ONNX (trotoar, drainase, guiding, dll.)
│   ├── Dockerfile                 # Image Python 3.11 + ONNX Runtime
│   └── requirements.txt           # Dependensi FastAPI, ONNXRuntime, Pillow, Numpy
├── bdd/                           # Pengujian Acceptance Criteria (Python Behave)
│   ├── features/                  # Skenario Gherkin (f6_heatmap, f9_status_perbaikan, dll.)
│   ├── features/steps/            # Step definitions pengujian perilaku sistem
│   └── run_bdd.sh                 # Skrip eksekusi otomasi BDD
├── infra/                         # Infrastruktur Multi-Container Docker
│   ├── docker/                    # Dockerfile PHP 8.3 & konfigurasi Nginx vhost
│   └── docker-compose.yml         # Konfigurasi orkestrasi: db, app, nginx, queue, cv-service
├── scripts/                       # Skrip kurasi dataset & pipeline pelatihan YOLOv8
├── docs/                          # Dokumentasi teknis, SRS v1.2, dan panduan fitur
├── Project_Context/               # Dokumentasi Sprint (Burndown Chart, Progress Reports)
├── README.md                      # Dokumentasi utama proyek
└── .gitignore                     # Aturan pengecualian berkas rahasia, cache, & dataset mentah
```

---

## 🚀 Panduan Memulai Cepat (Quick Start)

### 1. Prasyarat Sistem
- **Docker Engine** (versi $\ge 24.0$) dan **Docker Compose** (versi $\ge 2.20$)
- **Git**

### 2. Kloning Repositori & Setup Environment
```bash
# Kloning repositori
git clone https://github.com/AgilePemdigi-2026/PedestriRoute.git
cd PedestriRoute

# Salin konfigurasi environment backend
cp backend/.env.example backend/.env
```

### 3. Menjalankan Layanan Docker
Jalankan seluruh layanan (Database PostGIS, PHP-FPM, Web Server Nginx, Queue Worker, dan CV Microservice):
```bash
docker compose -f infra/docker-compose.yml up -d --build
```

### 4. Setup Database, Migrasi, & Data Seeder
```bash
# Generate application key
docker exec pedestriroute_app php artisan key:generate

# Jalankan migrasi tabel PostGIS dan seeder data awal Mulyorejo
docker exec pedestriroute_app php artisan migrate:fresh --seed

# (Opsional) Jalankan seeder data aduan realistis untuk melihat peta heatmap dinamis
docker exec pedestriroute_app php artisan db:seed --class=DemoLaporanSeeder
```

### 5. Mengakses Layanan
- **Aplikasi Web PedestriRoute:** [`http://localhost:8000`](http://localhost:8000)
  - Halaman Beranda Warga: [`http://localhost:8000/beranda-warga`](http://localhost:8000/beranda-warga)
  - Halaman Validasi Admin: [`http://localhost:8000/admin/validasi`](http://localhost:8000/admin/validasi)
  - Halaman Status Perbaikan: [`http://localhost:8000/admin/status-laporan`](http://localhost:8000/admin/status-laporan)
  - Halaman Statistik Admin: [`http://localhost:8000/admin/statistik`](http://localhost:8000/admin/statistik)
- **Computer Vision OpenAPI Docs:** [`http://localhost:8001/docs`](http://localhost:8001/docs)
- **Computer Vision Health Check:** [`http://localhost:8001/health`](http://localhost:8001/health)

---

## 🧪 Menjalankan Pengujian Otomatis

### A. Pengujian Unit & Feature (PHPUnit)
Eksekusi 68 skenario pengujian otomatis di dalam container Docker:
```bash
docker exec pedestriroute_app php artisan test
```
*Status: 68 passed (911 assertions), 100% Green.*

### B. Pengujian Acceptance Criteria BDD (Behave)
Jalankan otomasi pengujian skenario Gherkin:
```bash
./bdd/run_bdd.sh
```
*Status: 10 scenarios passed (60 steps passed), 100% Green.*

---

## 👥 Tim Pengembang (Kelompok 6 - PPL Praktikum I2)

| Nama Pengembang | NIM | Peran Utama |
| :--- | :---: | :--- |
| **Gede Moreno Maximilliano** | 187241024 | Fullstack Developer, GIS Architect, & CV Pipeline Specialist |
| **Athiyya Crissa** | 187241022 | Frontend & UI/UX Developer, Leaflet Spatial Visualization, & BDD QA |
| **Aditya Rangga Wisnuwardhana** | 187241104 | GIS Spatial Boundary Engineer, Dataset Curation, & Model Testing |
| **Mohammad Andana Warih** | 187241040 | Backend Engineer, Repair State Machine, & Async Queue Worker |

---

## 📄 Lisensi
Hak Cipta © 2026 **Kelompok 6 - Pembangunan Perangkat Lunak, Universitas Airlangga**.  
Dilisensikan di bawah [MIT License](LICENSE).
