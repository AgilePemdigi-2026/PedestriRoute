# Hasil Cek Fungsionalitas F5: Memvalidasi Laporan Manual

- Penguji: Athiyya Crissa (187241022), Kelompok 6
- Tanggal: 18 Oktober 2026
- Lingkungan: localhost:8000 via Docker Compose, branch cisa/validasi-manual (sudah merge main)
- Akun: admin@pedestriroute.test (login dengan OTP email, kode dibaca dari laravel.log), warga Ahmad Fauzi

## Hasil Pengujian

| No | Skenario | Hasil | Catatan |
|:--:|:---|:---:|:---|
| 1 | Antrean menampilkan laporan masuk dari warga (3 laporan) | Lulus | Header "3 Laporan Menunggu Verifikasi" |
| 2 | Keputusan Valid (#LAP-0001) | Lulus | Keputusan tersimpan sesuai ekspektasi |
| 3 | Tidak Valid tanpa alasan | Lulus | Ditolak dengan pesan validasi sesuai SRS ("Alasan penolakan harus diisi sebelum status laporan diperbarui") |
| 4 | Tidak Valid dengan alasan (#LAP-0002) | Lulus | Keputusan tersimpan sesuai ekspektasi |
| 5 | Pembatalan (tombol Kembali, #LAP-0003) | Lulus | Laporan tetap di antrean |
| 6 | Catatan CV belum tersedia (FR-F5-06) | Lulus | Catatan sistem "belum tersedia" tampil di halaman detail |
| 7 | Laporan yang sudah diputuskan diputuskan ulang | Lulus | Ditolak, sistem menampilkan pesan bahwa laporan sudah divalidasi sebelumnya / keputusan tidak sesuai |
| 8 | Akses halaman admin oleh Warga | Lulus | Dialihkan ke halaman login |

## Perbedaan dengan Mockup UI-04
- Navigasi bawah hanya "Validasi Laporan" dan "Tampilan Warga" (mockup: Peta, Rute, Lapor, Notifikasi, Admin)
- Waktu relatif ("10 menit lalu") tidak terlihat di daftar antrean

## Temuan Lain
- Login admin dan warga kini memakai OTP email (fitur tambahan dari F1)

## Kesimpulan
Seluruh skenario F5 yang diuji berjalan sesuai ekspektasi dan tidak ditemukan bug.