Feature: Memperbarui Status Perbaikan Laporan

  Background:
    Given I am logged in as "Admin"
    And a report exists with status "TERVALIDASI" and no repair status

  Scenario: Menjadi sedang diperbaiki
    When I open the report from "/admin/status-laporan"
    And I select "Sedang Diperbaiki" from "Status Perbaikan"
    And I press "Simpan"
    Then the repair status should be "SEDANG_DIPERBAIKI"
    And the Warga should have a notification "Jl. Rusak 1 sedang diperbaiki"
    And I should see the message "Notifikasi Status Berhasil Diperbarui"
    And the segment score should not change

  Scenario: Menjadi sudah diperbaiki
    When I select "Sudah Diperbaiki" and press "Simpan"
    Then the repair status should be "SUDAH_DIPERBAIKI"
    And the segment score of the report location should be recalculated
    And the Warga should have a notification with key "NOTIF_REPAIRED"

  Scenario: Gagal, status belum dipilih
    When I press "Simpan" without selecting "Status Perbaikan"
    Then I should see the message "Mohon pilih status perbaikan terlebih dahulu"
    And the repair status should remain unchanged

  Scenario: Gagal, kesalahan penyimpanan
    Given the database write fails
    When I press "Simpan" with a valid status
    Then I should see the message "Pembaruan status gagal disimpan. Silakan coba lagi."
    And the repair status should remain unchanged

  Scenario: Transisi tidak sah
    Given the repair status is "SUDAH_DIPERBAIKI"
    When I try to set "SEDANG_DIPERBAIKI"
    Then I should receive HTTP 409

  Scenario: Laporan belum tervalidasi
    Given a report has status "MENUNGGU_VALIDASI_ADMIN"
    When I try to set a repair status
    Then I should receive HTTP 409
