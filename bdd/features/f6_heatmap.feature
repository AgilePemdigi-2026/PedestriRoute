Feature: Melihat Peta Heatmap Kelayakan

  Background:
    Given I am logged in as "Warga"

  Scenario: Melihat heatmap berhasil
    Given segments exist in the viewport
    When I open "/beranda-warga" with "Semua Kategori"
    Then I should see segments colored by feasibility level
    And I should see the legend with three levels
    When I zoom in
    Then the map should show a more detailed view
    When I drag the map to another area
    Then the system should request heatmap data for the new viewport

  Scenario: Hanya laporan aktif yang berpengaruh
    Given a segment has score 100
    When a report on that segment becomes "TERVALIDASI"
    Then the segment score should be below 100
    When that report's repair status becomes "SUDAH_DIPERBAIKI"
    Then the segment score should return to 100
    And a report with status "DITOLAK" should never change a segment score

  Scenario: Data heatmap tidak tersedia
    Given no segments exist in the requested viewport
    Then I should see the message "Data kelayakan jalur belum tersedia untuk area ini."

  Scenario: Filter tanpa data
    Given no active report exists for category "Penerangan Minim"
    When I select the "Penerangan Minim" filter
    Then I should see the message "Tidak ada data laporan yang sesuai dengan filter."
    When I select "Semua Kategori"
    Then the heatmap should be shown again

  Scenario: Zoom rendah hanya menampilkan segmen bermasalah
    Given the viewport contains segments and some have a score below 100
    When the client requests the heatmap with a zoom below CFG_HEATMAP_MIN_ZOOM
    Then the response should contain only segments with score below 100
    And "meta.counts" should still count all segments in the viewport
