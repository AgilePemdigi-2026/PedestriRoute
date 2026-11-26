Feature: Mencari Rekomendasi Rute Pejalan Kaki

  Background:
    Given I am logged in as "Warga"

  Scenario: Mendapatkan rute teraman berbasis skor WSM
    Given walking segments with WSM scores exist around Mulyorejo
    When I request a walking route with preference "safest"
    Then the route response should contain distance, duration, and polyline
    And every route segment should expose its WSM score
    And the safest route should avoid the damaged segment

  Scenario: Membandingkan rute tercepat
    Given walking segments with WSM scores exist around Mulyorejo
    When I request a walking route with preference "fastest"
    Then the fastest route should use the damaged shortcut
    And the fastest route should not be longer than the safest route

  Scenario: Koordinat rute tidak valid
    When I request a walking route with invalid coordinates
    Then the route response should return status 422
    And I should see the message "Koordinat atau preferensi rute tidak valid."

  Scenario: Jaringan jalan belum tersedia
    When I request a walking route outside the mapped network
    Then the route response should still contain a direct fallback
