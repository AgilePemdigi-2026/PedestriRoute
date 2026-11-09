# Reference: SRS PedestriRoute v1.2 Bagian 5 F4 & Multi-Specialist Computer Vision Architecture
Feature: Memvalidasi Laporan dengan Computer Vision
  Sebagai Warga dan Pengelola Sistem
  Saya ingin foto laporan fasilitas pejalan kaki dianalisis oleh Computer Vision (FastAPI)
  Agar laporan dengan keyakinan tinggi dapat tervalidasi secara instan tanpa membebani antrean manual admin

  Background:
    Given CFG_CV_ENABLED is true and CFG_CV_THRESHOLD is 0.80

  @srs @f4
  Scenario: Divalidasi otomatis untuk trotoar rusak dengan keyakinan tinggi
    Given a report exists with status "DIPROSES" in category "Trotoar Rusak"
    And the CV returns label "RUSAK" with confidence 0.87
    When the job "ProcessReportCv" runs
    Then the report status should be "TERVALIDASI"
    And "confidence_score" should be 0.87
    And "cv_label" should be "RUSAK"
    And the segment score of the report location should be recalculated
    And the Warga should have a notification with key "NOTIF_AUTO_VALIDATED"

  @srs @f4
  Scenario: Divalidasi otomatis untuk halangan parkir liar dengan label TERHALANG
    Given a report exists with status "DIPROSES" in category "Parkir Liar"
    And the CV returns label "TERHALANG" with confidence 0.92
    When the job "ProcessReportCv" runs
    Then the report status should be "TERVALIDASI"
    And "confidence_score" should be 0.92
    And "cv_label" should be "TERHALANG"
    And the segment score of the report location should be recalculated
    And the Warga should have a notification with key "NOTIF_AUTO_VALIDATED"

  @srs @f4
  Scenario: Confidence rendah diteruskan ke Admin
    Given a report exists with status "DIPROSES" in category "Masalah Drainase"
    And the CV returns label "RUSAK" with confidence 0.74
    When the job "ProcessReportCv" runs
    Then the report status should be "MENUNGGU_VALIDASI_ADMIN"
    And "confidence_score" should be 0.74
    And the Warga should have a notification with key "NOTIF_PENDING_ADMIN"
    And every Admin should have a notification with key "NOTIF_QUEUE_ADMIN"

  @srs @f4
  Scenario: Foto fasilitas normal tidak lolos otomatis
    Given a report exists with status "DIPROSES" in category "Guiding Block Rusak"
    And the CV returns label "BAIK" with confidence 0.95
    When the job "ProcessReportCv" runs
    Then the report status should be "MENUNGGU_VALIDASI_ADMIN"
    And "cv_label" should be "BAIK"
    And the Warga should have a notification with key "NOTIF_PENDING_ADMIN"

  @srs @f4
  Scenario: Foto non-infrastruktur atau spam tidak lolos otomatis
    Given a report exists with status "DIPROSES" in category "Penyebrangan Rusak"
    And the CV returns label "SPAM" with confidence 0.96
    When the job "ProcessReportCv" runs
    Then the report status should be "MENUNGGU_VALIDASI_ADMIN"
    And "cv_label" should be "SPAM"
    And the Warga should have a notification with key "NOTIF_PENDING_ADMIN"

  @srs @f4
  Scenario: Layanan CV gagal atau timeout
    Given a report exists with status "DIPROSES" in category "Trotoar Rusak"
    And the CV service times out
    When the job "ProcessReportCv" runs
    Then the report status should be "MENUNGGU_VALIDASI_ADMIN"
    And "confidence_score" and "cv_label" should be null
    And the report should still exist

  @srs @f4
  Scenario: Kategori di luar cakupan CV
    Given a report exists with status "DIPROSES" in an unsupported category
    When the job "ProcessReportCv" runs
    Then the CV service should not be called
    And the report status should be "MENUNGGU_VALIDASI_ADMIN"

  @srs @f4
  Scenario: Job berjalan secara idempoten
    Given a report exists with status "DIPROSES" in category "Trotoar Rusak"
    And the report status is already "TERVALIDASI"
    When the job "ProcessReportCv" runs
    Then nothing should change and no duplicate notification should exist

  @srs @f4
  Scenario: Warga pelapor dapat mengakses halaman hasil analisis AI
    Given a report exists with status "DIPROSES" in category "Trotoar Rusak"
    And the CV returns label "RUSAK" with confidence 0.88
    And the job "ProcessReportCv" runs
    When I access the analysis page "/laporan/{id}/analisis" as the reporter
    Then I should see the analysis result with status "TERVALIDASI"
