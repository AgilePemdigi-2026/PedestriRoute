# Laporan Pengujian Robustness & Kelemahan Model Computer Vision

**Proyek:** PedestriRoute (Kelompok 6, Praktikum PPL)
**Issue:** #40 [Sprint 2 / QA]
**Penguji:** Athiyya Crissa
**Tanggal pengujian:** 8 November 2026
**Status:** Temuan awal (sampel kecil, bukan akurasi yang valid secara statistik)

---

## 1. Ringkasan

Pengujian dilakukan langsung ke service CV (`cv-service`, endpoint `POST /analyze`) dengan foto yang dikumpulkan manual dari internet. Service berjalan dalam mode model asli (`mock_mode: false`, 5 model specialist termuat).

Cakupan dipersempit atas kesepakatan tim: 3 dari 5 kategori (drainase, trotoar, penyebrangan), 3-4 foto per kategori, tanpa confusion matrix penuh. Kategori `guiding_block` dan `parkir_liar` **belum diuji**.

Hasil utama:

- Dari 11 foto awal, **8 benar dan 3 salah** (73%).
- Dari 3 kesalahan, 2 foto ternyata bermasalah sebagai input (screenshot dengan border putih; foto hitam-putih close-up). Setelah diuji ulang dengan foto yang diperbaiki, keduanya benar.
- Tersisa **1 kesalahan model yang jelas**: `trotoar_baik_04` (trotoar terlihat rapi, dibaca RUSAK dengan confidence 0,999).
- Temuan lain: confidence rendah pada kondisi basah dan pada kelas BAIK di drainase dan penyebrangan.
- Temuan sistem: alur pelaporan lewat UI belum memanggil CV (lihat bagian 6).

## 2. Metode

- **Input:** foto di `C:\cv-uji` (di luar repo). Nama file `kategori_kondisi_nomor.jpg`; foto kondisi baik memuat kata `baik`.
- **Label seharusnya** ditentukan penguji dari nama file/penilaian visual, bukan dari ground truth resmi.
- **Perintah uji** (per foto):

  ```powershell
  curl.exe -sS -X POST http://localhost:8001/analyze -F "file=@C:\cv-uji\<nama_file>" -F "category=<kategori>"
  ```

- Tiap model memiliki 3 kelas: BAIK, RUSAK, SPAM. Untuk `parkir_liar`, label RUSAK diganti TERHALANG (kategori ini belum diuji).
- Dari kode `cv-service/app/main.py` (hasil pembacaan penguji): ada filter kualitas sebelum model (rata-rata kecerahan < 25 atau terlalu blur langsung berlabel SPAM), sub-tiling kecuali `parkir_liar`, dan fallback mode mock berbasis nama file jika tidak ada model termuat.

## 3. Hasil Pengujian

| File | Kategori | Kondisi | Seharusnya | Hasil CV | Confidence | Status |
|---|---|---|---|---|---|---|
| drainase_siang_01.jpeg | drainase | siang, gorong-gorong rusak | RUSAK | RUSAK | 0,996 | Benar |
| drainase_sampah_02.jpg | drainase | sampah | RUSAK | RUSAK | 0,993 | Benar |
| drainase_basah_03.jpg | drainase | basah/genangan | RUSAK | RUSAK | 0,610 | Benar (conf rendah) |
| drainase_baik_04.jpg | drainase | baik | BAIK | BAIK | 0,558 | Benar (conf rendah) |
| penyebrangan_pudar_01.jpg | penyebrangan | pudar | RUSAK | RUSAK | 0,995 | Benar |
| penyebrangan_miring_02.jpg | penyebrangan | sudut miring | RUSAK | RUSAK | 0,940 | Benar |
| penyebrangan_baik_04.jpg | penyebrangan | baik (hitam-putih, close-up) | BAIK | SPAM | 0,804 | **Salah** |
| trotoar_lubang_01.jpg | trotoar | berlubang (screenshot, border putih) | RUSAK | BAIK | 0,997 | **Salah** |
| trotoar_basah_02.jpg | trotoar | basah | RUSAK | RUSAK | 0,993 | Benar |
| trotoar_malam_03.jpg | trotoar | malam | RUSAK | RUSAK | 0,979 | Benar |
| trotoar_baik_04.jpg | trotoar | baik | BAIK | RUSAK | 0,999 | **Salah** |

**Akurasi awal:** 8 dari 11 (73%).

### Uji ulang untuk kasus input tidak biasa

| File | Perubahan | Hasil CV | Confidence | Status |
|---|---|---|---|---|
| trotoar_lubang_01b.jpg | `trotoar_lubang_01` di-crop tanpa border putih | RUSAK | 0,989 | Benar |
| penyebrangan_baik_05.jpg | foto pengganti: berwarna, ada konteks jalan | BAIK | 0,588 | Benar (conf rendah) |

**Setelah uji ulang:** 10 dari 11 foto valid benar (1 kesalahan: `trotoar_baik_04`). Angka ini bergantung pada penggantian foto uji, sehingga angka awal (73%) tetap dicantumkan.

## 4. Tabel Kesalahan Klasifikasi

| File | Seharusnya | Hasil | baik / rusak / spam | Analisis |
|---|---|---|---|---|
| trotoar_baik_04 | BAIK | RUSAK | 0,001 / 0,999 / 0,0001 | **Kesalahan model.** Foto terlihat rapi (guiding block, tutup drainase, petak abu gelap). Dugaan: tutup drainase atau petak gelap terbaca sebagai kerusakan. Belum diverifikasi. |
| trotoar_lubang_01 | RUSAK | BAIK | 0,997 / 0,002 / 0,001 | **Dipicu format input.** Foto adalah screenshot halaman dokumen dengan border putih lebar dan sudut miring; "lubang" juga tidak jelas. Versi crop (`01b`) menjadi RUSAK 0,989. |
| penyebrangan_baik_04 | BAIK | SPAM | 0,021 / 0,176 / 0,804 | **Input tidak biasa.** Zebra cross hitam-putih, close-up dari atas, tanpa konteks jalan. Belum diketahui apakah SPAM berasal dari filter kualitas atau dari model. |

## 5. Pola Kelemahan

Fakta (terlihat dari output):

1. **Kesalahan yakin pada trotoar.** Dua kesalahan trotoar memiliki confidence 0,997 dan 0,999, sehingga bukan masalah ambang (threshold). Menurunkan atau menaikkan threshold tidak akan memperbaikinya.
2. **Confidence rendah pada kondisi basah/genangan dan kelas BAIK.** Drainase basah 0,610, drainase baik 0,558, penyebrangan baik 0,588 (probabilitas rusak 0,359).
3. **Sensitif terhadap kualitas dan format input.** Foto screenshot dengan border putih salah klasifikasi, dan foto hitam-putih close-up berlabel SPAM.

Dugaan (belum terbukti):

- Border putih adalah penyebab kesalahan `trotoar_lubang_01`. Cropping juga mengubah isi gambar, sehingga penyebabnya belum terpisah dari faktor lain.
- Model cenderung menganggap elemen non-aspal/paving seragam (tutup drainase, petak gelap) sebagai kerusakan pada trotoar.
- Foto malam trotoar (`trotoar_malam_03`) lolos dengan benar, namun foto yang sangat gelap berpotensi terkena filter kecerahan (< 25) dan berlabel SPAM. Kasus ini harus dicatat sebagai "kena filter kualitas", bukan salah model. Belum ada foto yang menguji ini.

Catatan observasi: field `detected_category` bernilai `null` pada beberapa respons dan `"trotoar"` pada respons lain untuk kategori yang dikirim sama. Penyebabnya belum diketahui; field ini sebaiknya tidak dianggap sebagai hasil deteksi sampai kodenya dicek.

## 6. Temuan Sistem: CV Belum Aktif di Alur Laporan

- Laporan uji lewat UI (#55, drainase) masuk dengan `cv_label` dan `confidence_score` kosong, dan log `cv-service` tidak pernah menerima `POST /analyze`.
- Di `backend/.env` terdapat `CFG_CV_ENABLED=false` (dugaan: saklar CV; kode yang membacanya belum diperiksa) dan `QUEUE_CONNECTION=database` (job `ProcessReportCv` memerlukan queue worker).
- Karena itu, pengujian dilakukan langsung ke service CV, bukan lewat form laporan. Hasil di atas menggambarkan model, bukan integrasi end-to-end.

## 7. Keterbatasan Pengujian

- Hanya 3 dari 5 kategori; `guiding_block` dan `parkir_liar` belum diuji.
- Sampel 1-3 foto per kondisi, sehingga tidak valid secara statistik.
- Belum mencakup kondisi silau dan bayangan; sudut miring hanya diuji pada penyebrangan; malam hanya pada trotoar.
- Foto diambil dari internet, label ditentukan penguji, dan tidak ada confusion matrix, analisis FP/FN terukur, maupun uji sensitivitas threshold (disederhanakan atas kesepakatan tim).
- Dua foto uji diganti setelah terbukti bermasalah sebagai input, yang menaikkan hasil akhir.

## 8. Rekomendasi

1. **Tambah data latih trotoar kelas BAIK** yang memuat tutup drainase, guiding block, dan paving berpetak/gelap, untuk menekan false positive RUSAK.
2. **Augmentasi** pencahayaan (redup/silau), sudut miring, grayscale, dan border/padding, supaya model lebih tahan terhadap format input tidak biasa.
3. **Tambah data kondisi basah/genangan**, karena confidence rendah di drainase dan zebra cross.
4. **Preprocessing di service:** crop otomatis border putih/seragam dan validasi rasio foto sebelum masuk model.
5. **Tinjau filter kualitas** (ambang kecerahan 25 dan blur): pastikan foto gelap/hitam-putih yang sah tidak otomatis SPAM, dan bedakan alasan SPAM (filter vs model) di respons API.
6. **Tampilkan confidence rendah ke pengguna/admin:** misalnya tandai laporan dengan confidence di bawah ~0,65 untuk validasi manual.
7. **Aktifkan dan uji alur CV di aplikasi:** cek `CFG_CV_ENABLED`, jalankan queue worker, lalu uji end-to-end.
8. **Lanjutkan pengujian** `guiding_block` dan `parkir_liar`, dengan minimal 5 variasi kondisi per kategori sesuai issue, serta bangun confusion matrix saat dataset uji cukup.

## 9. Lampiran

Foto uji disimpan di luar repo:

- `C:\cv-uji` (seluruh input)
- `C:\cv-berhasil`: `drainase_siang_01`, `drainase_sampah_02`, `drainase_basah_03`, `drainase_baik_04`, `penyebrangan_pudar_01`, `penyebrangan_miring_02`, `trotoar_basah_02`, `trotoar_malam_03`, `trotoar_lubang_01b`, `penyebrangan_baik_05`
- `C:\cv-salah`: `penyebrangan_baik_04`, `trotoar_lubang_01`, `trotoar_baik_04`
