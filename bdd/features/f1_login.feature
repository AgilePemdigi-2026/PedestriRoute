# Reference: SRS PedestriRoute v1.2 Bagian 5.1 (F1: Login) & Sprint 1 Progress Report
Feature: Login
  Sebagai pengguna sistem (Warga atau Admin)
  Saya ingin dapat masuk ke dalam sistem menggunakan kredensial saya
  Agar saya dapat mengakses fitur sesuai peran saya (Warga ke /beranda-warga, Admin ke /admin)

  @srs @f1
  Scenario: Login Warga berhasil
    Given I am on the "/login" page
    And a Warga account exists with email "ahmad.fauzi@gmail.com" and password "warga12345"
    When I fill in "Email/Nomor Telepon" with "ahmad.fauzi@gmail.com"
    And I fill in "Kata Sandi" with "warga12345"
    And I press "Masuk"
    Then I should be on the "/beranda-warga" page

  @srs @f1
  Scenario: Login Admin berhasil
    Given an Admin account exists with email "admin@pedestriroute.test"
    When I log in with that account
    Then I should be on the "/admin" page

  @srs @f1
  Scenario: Login dengan nomor telepon berformat lain
    Given a Warga account exists with phone "081234567890"
    When I log in with "+6281234567890" and the correct password
    Then I should be on the "/beranda-warga" page

  @srs @f1
  Scenario: Pembatasan laju per IP
    Given one IP address has sent 10 login requests within a minute
    When it sends another login request
    Then I should receive HTTP 429 with code "THROTTLED"

  @srs @f1
  Scenario: Gagal, kolom kosong
    Given I am on the "/login" page
    When I leave "Kata Sandi" empty and press "Masuk"
    Then I should see the message "Email/nomor telepon dan kata sandi harus diisi"
    And I should be on the "/login" page

  @srs @f1
  Scenario: Gagal, akun tidak ditemukan
    Given I am on the "/login" page
    When I fill in "Email/Nomor Telepon" with "belumdaftar@gmail.com"
    And I fill in "Kata Sandi" with "sembarang123"
    And I press "Masuk"
    Then I should see the message "Email/nomor telepon atau kata sandi yang Anda masukkan salah"
    And I should be on the "/login" page

  @srs @f1
  Scenario: Gagal, kata sandi salah dicatat
    Given a Warga account exists with email "ahmad.fauzi@gmail.com"
    When I log in with a wrong password
    Then I should see the message "Email/nomor telepon atau kata sandi yang Anda masukkan salah"
    And "gagal_login" for that account should increase by 1

  @srs @f1
  Scenario: Akun terkunci
    Given a Warga account has failed to log in CFG_MAX_LOGIN_ATTEMPTS times
    When I log in with the correct password
    Then I should see a message with key "LOGIN_LOCKED"
    And I should not receive a token

  # Sprint 1 Week 1 & 2 Extension: Two-Step Email OTP Verification
  @sprint1_refinement @f1 @otp
  Scenario: Verifikasi Two-Step OTP berhasil menerbitkan token
    Given a valid login challenge exists for user "otp.warga.test@gmail.com"
    When I submit the correct OTP code
    Then I should receive HTTP 201 with an auth token and role "warga"

  @sprint1_refinement @f1 @otp
  Scenario: Verifikasi Two-Step OTP gagal karena kode salah
    Given a valid login challenge exists for user "otp.warga.test@gmail.com"
    When I submit an invalid OTP code "000000"
    Then I should receive HTTP 422 with message "Kode OTP salah."
