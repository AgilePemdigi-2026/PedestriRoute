# Reference: SRS PedestriRoute v1.2 Bagian 5.3 (F3: Melaporkan Kondisi Fasilitas) & Sprint 1 Progress Report
Feature: Melaporkan Kondisi Fasilitas Pejalan Kaki
  Sebagai Warga pejalan kaki
  Saya ingin melaporkan kerusakan atau hambatan fasilitas trotoar beserta foto dan titik peta
  Agar kendala dapat divalidasi oleh Admin dan memperbarui informasi rute pejalan kaki

  Background:
    Given I am logged in as "Warga"
    And CFG_CV_ENABLED is false

  @srs @f3
  Scenario: Melaporkan berhasil
    Given I am on the "/lapor-kondisi" page
    And I select a point on "Peta"
    And "Koordinat X" is filled with "112.7965"
    And "Koordinat Y" is filled with "-7.2663"
    And I fill in "Foto" with "trotoar_rusak.jpg"
    And I fill in "Deskripsi" with "Trotoar berlubang dan membahayakan pejalan kaki"
    And I select "Kategori Masalah" with "Trotoar Rusak"
    When I press "Kirim Laporan"
    Then I should see the message "Laporan terkirim"
    And a report should exist with status "MENUNGGU_VALIDASI_ADMIN"
    And the photo URL should be stored in "foto_laporan"

  @srs @f3
  Scenario: Gagal, lokasi di luar area cakupan
    Given I am on the "/lapor-kondisi" page
    When I select a point with "Koordinat X" "106.8456" and "Koordinat Y" "-6.2088"
    Then I should see the message "Lokasi di Luar Area"
    And no report should be created

  @srs @f3
  Scenario: Gagal, tidak ada segmen jalan di dekat titik
    Given I am on the "/lapor-kondisi" page
    When I select a point inside the service area but farther than CFG_SNAP_RADIUS_M from any segment
    Then I should see the message "Lokasi terlalu jauh dari jalan yang terdata. Pilih titik yang lebih dekat dengan jalan."
    And no report should be created

  @srs @f3
  Scenario: Gagal, format foto tidak valid
    Given I selected a valid location
    When I fill in "Foto" with "contoh.pdf"
    Then I should see the message "Foto Tidak Valid"

  @srs @f3
  Scenario: Gagal, ukuran foto melebihi batas
    Given I selected a valid location
    When I fill in "Foto" with a JPEG larger than CFG_PHOTO_MAX_MB
    Then I should see the message "Foto Tidak Valid"

  @srs @f3
  Scenario: Gagal, deskripsi atau kategori kosong
    Given I selected a valid location and a valid photo
    And I select "Kategori Masalah" with ""
    When I press "Kirim Laporan"
    Then I should see the message "Data Laporan Belum Lengkap"

  @srs @f3
  Scenario: Warga lain tidak bisa melihat laporan
    Given a report belongs to another Warga
    When I request that report's detail
    Then I should receive HTTP 403

  @srs @f3
  Scenario: Metadata foto dibuang
    Given I upload a JPEG that contains EXIF GPS coordinates
    When the report is saved
    Then the stored photo should not contain EXIF metadata

  @srs @f3
  Scenario: Penyimpanan gagal tidak meninggalkan berkas
    Given the database write fails after the photo was uploaded
    When I press "Kirim Laporan"
    Then no report should exist
    And the uploaded photo should be deleted from storage

  # Sprint 1 Week 2 Refinement: Support up to 15MB photo upload
  @sprint1_refinement @f3
  Scenario: Melaporkan berhasil dengan foto hingga batas 15MB
    Given I selected a valid location
    And I fill in "Foto" with a valid JPEG of size "8" MB
    And I fill in "Deskripsi" with "Laporan trotoar rusak dengan foto resolusi tinggi"
    And I select "Kategori Masalah" with "Trotoar Rusak"
    When I press "Kirim Laporan"
    Then I should see the message "Laporan terkirim"
    And a report should exist with status "MENUNGGU_VALIDASI_ADMIN"
