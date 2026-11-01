# Reference: SRS PedestriRoute v1.2 Bagian 5.2 (F2: Register) & Sprint 1 Progress Report
Feature: Register
  Sebagai calon pengguna (Warga)
  Saya ingin mendaftarkan akun baru dengan data diri dan kontak yang valid
  Agar saya dapat masuk dan menggunakan fasilitas pelaporan rute pejalan kaki

  @srs @f2
  Scenario: Registrasi berhasil
    Given I am on the "/register" page
    And I fill in "Nama" with "John Maps"
    And I fill in "Email" with "johnmaps@gmail.com"
    And I fill in "Nomor Telepon" with "081234567890"
    And I fill in "Kata Sandi" with "warga12345"
    And I fill in "Konfirmasi Kata Sandi" with "warga12345"
    When I press "Daftar"
    Then I should see the message "Registrasi Berhasil"
    And I should be on the "/login" page
    And the stored password should not equal "warga12345"

  @srs @f2
  Scenario: Gagal, format tidak valid atau kolom kosong
    Given I am on the "/register" page
    And I fill in "Email" with "ahmadfauzi.gmail.com"
    And I leave "Nomor Telepon" empty
    When I press "Daftar"
    Then I should see the message "Data yang Anda isi belum lengkap atau formatnya tidak valid"
    And I should be on the "/register" page

  @srs @f2
  Scenario: Gagal, email sudah terdaftar
    Given the email "johnmaps@gmail.com" is already registered
    When I register with the email "johnmaps@gmail.com"
    Then I should see the message "Email Sudah Terdaftar."

  @srs @f2
  Scenario: Gagal, nomor telepon sudah terdaftar
    Given the phone "081234567890" is already registered
    When I register with the phone "081234567890"
    Then I should see the message "Nomor Telepon Sudah Terdaftar."

  @srs @f2
  Scenario: Gagal, nomor yang sama dengan format berbeda
    Given the phone "081234567890" is already registered
    When I register with the phone "+6281234567890"
    Then I should see the message "Nomor Telepon Sudah Terdaftar."

  @srs @f2
  Scenario: Gagal, konfirmasi kata sandi tidak sesuai
    Given I am on the "/register" page
    And I fill in "Kata Sandi" with "warga12345"
    And I fill in "Konfirmasi Kata Sandi" with "warga54321"
    When I press "Daftar"
    Then I should see the message "Kata Sandi Tidak Sesuai."
    And I should be on the "/register" page

  # Sprint 1 Week 1 & 2 Extension: Two-Step Email OTP for Registration
  @sprint1_refinement @f2 @otp
  Scenario: Registrasi berhasil dengan alur verifikasi Email OTP
    Given I initiate registration for new user "andi.wijaya@gmail.com" with phone "081299990001"
    When I verify the registration using the sent OTP code
    Then I should receive HTTP 201 with message "Registrasi Berhasil"
    And the user "andi.wijaya@gmail.com" should exist in database
