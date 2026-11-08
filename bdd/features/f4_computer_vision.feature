# Referensi: SRS PedestriRoute v1.2 Bagian 5.4 (F4: Memvalidasi Laporan dengan Computer Vision)
# Issue #41 — Sprint 2
Feature: Memvalidasi Laporan dengan Computer Vision
  Sebagai Sistem (CV Pipeline)
  Saya ingin menganalisis foto laporan secara otomatis menggunakan model Computer Vision
  Agar laporan yang nyata dapat langsung tervalidasi tanpa campur tangan Admin,
  sedangkan laporan yang meragukan atau foto spam diarahkan ke validasi manual Admin

  Background:
    Given saya masuk sebagai "Warga"
    And sebuah laporan baru ada dengan status "DIPROSES"

  @srs @f4
  Scenario: Auto-validasi berhasil — model mendeteksi kerusakan nyata (RUSAK)
    Given layanan CV mengembalikan label "RUSAK" dengan confidence 0.92
    When sistem memanggil endpoint analisis laporan
    Then status laporan harus berubah menjadi "TERVALIDASI"
    And skor kelayakan segmen pada lokasi laporan harus dihitung ulang
    And warga harus menerima notifikasi dengan kata kunci "NOTIF_AUTO_VALIDATED"

  @srs @f4
  Scenario: Auto-validasi berhasil — model mendeteksi halangan parkir liar (TERHALANG)
    Given layanan CV mengembalikan label "TERHALANG" dengan confidence 0.85
    When sistem memanggil endpoint analisis laporan
    Then status laporan harus berubah menjadi "TERVALIDASI"
    And warga harus menerima notifikasi dengan kata kunci "NOTIF_AUTO_VALIDATED"

  @srs @f4
  Scenario: Fallback ke Admin — confidence di bawah ambang batas
    Given layanan CV mengembalikan label "RUSAK" dengan confidence 0.65
    When sistem memanggil endpoint analisis laporan
    Then status laporan harus berubah menjadi "MENUNGGU_VALIDASI_ADMIN"
    And warga harus menerima notifikasi dengan kata kunci "NOTIF_PENDING_ADMIN"
    And Admin harus menerima notifikasi dengan kata kunci "NOTIF_QUEUE_ADMIN"

  @srs @f4
  Scenario: Pencegahan false positive — foto berlabel BAIK atau SPAM tidak auto-tervalidasi
    Given layanan CV mengembalikan label "BAIK" dengan confidence 0.95
    When sistem memanggil endpoint analisis laporan
    Then status laporan harus berubah menjadi "MENUNGGU_VALIDASI_ADMIN"
    And skor kelayakan segmen TIDAK boleh berubah

  @srs @f4
  Scenario: Fallback aman — layanan CV timeout atau down, data tidak hilang
    Given layanan CV tidak merespons dalam 10 detik
    When sistem memanggil endpoint analisis laporan
    Then laporan harus tetap ada di basis data
    And status laporan harus berubah menjadi "MENUNGGU_VALIDASI_ADMIN"
    And tidak ada foto yang dihapus dari penyimpanan

  @srs @f4
  Scenario: Otorisasi akses halaman analisis laporan
    Given terdapat laporan milik warga lain
    When warga yang bukan pemilik laporan mengakses "/laporan/{id}/analisis"
    Then sistem harus mengembalikan HTTP 403
    When Admin mengakses "/laporan/{id}/analisis"
    Then sistem harus mengembalikan HTTP 200
