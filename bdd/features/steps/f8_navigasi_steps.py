import requests
from behave import given, when, then


NAVIGATION_ORIGIN = (-7.2660, 112.7950)
NAVIGATION_DESTINATION = (-7.2665, 112.7970)


def request_route(context, origin, destination, preference="safest"):
    url = f"{context.api_url}/routes/recommend"
    headers = {"Authorization": f"Bearer {context.warga_token}"}
    payload = {
        "origin": {"lat": origin[0], "lng": origin[1]},
        "destination": {"lat": destination[0], "lng": destination[1]},
        "preference": preference,
    }
    return requests.post(url, headers=headers, json=payload, timeout=15)


@given('a walking route exists between the navigation points')
def step_walking_route_exists(context):
    php = """
    $exists = Illuminate\\Support\\Facades\\DB::table('segmen_jalur')
        ->where('nama_jalan', 'like', 'Jl. Navigasi BDD%')
        ->exists();
    if (!$exists) {
        $unique = rand(10000, 99999);
        Illuminate\\Support\\Facades\\DB::table('segmen_jalur')->insert([
            'osm_way_id' => 96000 + $unique,
            'u_node' => 86000 + $unique,
            'v_node' => 76000 + $unique,
            'nama_jalan' => 'Jl. Navigasi BDD ' . $unique,
            'geom' => Illuminate\\Support\\Facades\\DB::raw("ST_GeogFromText('SRID=4326;LINESTRING(112.7950 -7.2660, 112.7960 -7.2660, 112.7970 -7.2665)')"),
            'panjang_m' => 220.0,
            'skor_kelayakan' => 90.00,
            'tanggal_update' => now(),
        ]);
    }
    echo 'ok';
    """
    context.run_tinker(php)


@when('I request turn-by-turn navigation from the navigation origin to the destination')
def step_request_navigation(context):
    context.response = request_route(context, NAVIGATION_ORIGIN, NAVIGATION_DESTINATION)
    assert context.response.status_code == 200, context.response.text
    context.route_data = context.response.json()


@then('the response should contain a route polyline')
def step_response_contains_polyline(context):
    polyline = context.route_data.get('polyline') or []
    assert len(polyline) >= 2, "Route polyline should have at least two points."


@then('the first step should be "{step_type}"')
def step_first_step_type(context, step_type):
    steps = context.route_data.get('steps') or []
    assert steps, "Route did not contain any steps."
    assert steps[0]['type'] == step_type, f"Expected first step {step_type}, got {steps[0]['type']}"


@then('the last step should be "{step_type}"')
def step_last_step_type(context, step_type):
    steps = context.route_data.get('steps') or []
    assert steps, "Route did not contain any steps."
    assert steps[-1]['type'] == step_type, f"Expected last step {step_type}, got {steps[-1]['type']}"


@then('each step should have a distance and an instruction')
def step_steps_have_distance_and_instruction(context):
    for step in context.route_data.get('steps', []):
        assert step.get('instruction'), f"Step {step.get('index')} is missing an instruction."
        assert 'distance_m' in step, f"Step {step.get('index')} is missing distance_m."


@when('I move more than 20 meters away from the route')
def step_move_off_route(context):
    context.previous_route_id = context.route_data.get('route_id')
    # Move roughly 40 m east of the original position to simulate going off-route.
    context.off_route_position = (NAVIGATION_ORIGIN[0], round(NAVIGATION_ORIGIN[1] + 0.00036, 6))


@then('the system should request a new route from the current position')
def step_request_reroute(context):
    context.response = request_route(context, context.off_route_position, NAVIGATION_DESTINATION)
    assert context.response.status_code == 200, context.response.text
    context.reroute_data = context.response.json()
    assert context.reroute_data.get('route_id') != context.previous_route_id, "Reroute should produce a new route id."


@then('the new route should start at the current position')
def step_new_route_starts_at_current_position(context):
    origin = context.reroute_data.get('origin', {})
    assert abs(origin.get('lat', 0) - context.off_route_position[0]) < 1e-6
    assert abs(origin.get('lng', 0) - context.off_route_position[1]) < 1e-6


@then('the navigation should continue with a new set of steps')
def step_new_route_has_steps(context):
    steps = context.reroute_data.get('steps') or []
    assert len(steps) >= 2, "Rerouted navigation should contain depart and arrive steps."
    assert steps[0]['type'] == 'depart'
    assert steps[-1]['type'] == 'arrive'


@when('I request turn-by-turn navigation with invalid coordinates')
def step_request_invalid_coordinates(context):
    url = f"{context.api_url}/routes/recommend"
    headers = {"Authorization": f"Bearer {context.warga_token}"}
    payload = {
        "origin": {"lat": 200, "lng": 112.7950},
        "destination": {"lat": -7.2665},
    }
    context.response = requests.post(url, headers=headers, json=payload, timeout=15)


@then('the response should return status 422')
def step_response_status_422(context):
    assert context.response.status_code == 422, f"Expected 422, got {context.response.status_code}"


@given('GPS is disabled on the device')
def step_gps_disabled(context):
    context.gps_enabled = False
    context.last_message = context.message_catalog.get("NAV_GPS_REQUIRED")


@when('I try to start navigation')
def step_try_start_navigation(context):
    # Mirrors the browser behaviour when navigator.geolocation is unavailable.
    if not getattr(context, 'gps_enabled', False):
        context.last_message = context.message_catalog.get("NAV_GPS_REQUIRED")
