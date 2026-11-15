Feature: Navigasi Turn-by-Turn

  Background:
    Given I am logged in as "Warga"

  Scenario: Memulai navigasi berjalan kaki
    Given a walking route exists between the navigation points
    When I request turn-by-turn navigation from the navigation origin to the destination
    Then the response should contain a route polyline
    And the first step should be "depart"
    And the last step should be "arrive"
    And each step should have a distance and an instruction

  Scenario: Mendapatkan rute baru saat keluar jalur
    Given a walking route exists between the navigation points
    When I request turn-by-turn navigation from the navigation origin to the destination
    And I move more than 20 meters away from the route
    Then the system should request a new route from the current position
    And the new route should start at the current position
    And the navigation should continue with a new set of steps

  Scenario: Koordinat navigasi tidak valid
    When I request turn-by-turn navigation with invalid coordinates
    Then the response should return status 422
    And I should see the message "Koordinat atau preferensi rute tidak valid."

  Scenario: GPS perangkat tidak aktif
    Given GPS is disabled on the device
    When I try to start navigation
    Then I should see the message "Aktifkan GPS perangkat untuk memulai navigasi."
