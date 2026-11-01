# Reference: SRS PedestriRoute v1.2 Bagian 5.5 (F5: Memvalidasi Laporan Manual) & docs/HASIL_CEK_F5.md
Feature: Memvalidasi Laporan secara Manual
  Sebagai Admin / Petugas Verifikasi Dishub
  Saya ingin meninjau antrean aduan fasilitas pejalan kaki dan memberikan keputusan validasi
  Agar laporan yang akurat dapat memengaruhi skor kelayakan segmen jalur pejalan kaki

  Background:
    Given I am logged in as "Admin"
    And a report exists with status "MENUNGGU_VALIDASI_ADMIN"

  @srs @f5
  Scenario: Dinyatakan valid
    Given I open the report from "/admin/validasi"
    When I set the review decision to "Valid" and save
    Then the report status should be "TERVALIDASI"
    And "admin_id" and "tanggal_validasi" should be filled
    And the segment score of the report location should be recalculated
    And the Warga should have a notification with key "NOTIF_VALIDATED"

  @srs @f5
  Scenario: Dinyatakan tidak valid dengan alasan
    When I set the review decision to "Tidak Valid"
    And I fill in "Alasan Penolakan" with "Foto tidak menunjukkan kerusakan fasilitas yang jelas"
    And I save the decision
    Then the report status should be "DITOLAK"
    And the Warga should have a notification with key "NOTIF_REJECTED"

  @srs @f5
  Scenario: Gagal, alasan penolakan kosong
    When I set the review decision to "Tidak Valid"
    And I save the decision without "Alasan Penolakan"
    Then I should see the message "Alasan penolakan harus diisi sebelum status laporan diperbarui"
    And the report status should remain "MENUNGGU_VALIDASI_ADMIN"

  @srs @f5
  Scenario: CV tidak tersedia
    Given the report has no CV result
    When I open the report
    Then I should see a note that the automatic analysis is unavailable
    And I can still decide "Valid" or "Tidak Valid"

  @srs @f5
  Scenario: Pembatalan
    When I open the report and press back without deciding
    Then the report status should remain "MENUNGGU_VALIDASI_ADMIN"
    And I should be on "/admin/validasi"

  @srs @f5
  Scenario: Laporan sudah diputuskan
    Given the report status is "TERVALIDASI"
    When I submit a decision for it
    Then I should receive HTTP 409

  @srs @f5
  Scenario: Warga tidak boleh memutuskan
    Given I am logged in as "Warga"
    When I submit a decision for any report
    Then I should receive HTTP 403

  # Sprint 1 Week 2 Refinement: FIFO validation queue ordering
  @sprint1_refinement @f5
  Scenario: Admin dapat melihat antrean validasi dalam urutan FIFO
    Given multiple reports exist waiting for validation
    When I view the validation queue at "/admin/validasi"
    Then the reports should be listed in chronological order
