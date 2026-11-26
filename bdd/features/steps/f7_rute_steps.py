import json

import requests
from behave import given, then, when


ROUTE_ORIGIN = {"lat": -7.2700, "lng": 112.8070}
ROUTE_DESTINATION = {"lat": -7.2700, "lng": 112.8079}


def request_route(context, origin, destination, preference="safest"):
    url = f"{context.api_url}/routes/recommend"
    headers = {"Authorization": f"Bearer {context.warga_token}"}
    payload = {
        "origin": origin,
        "destination": destination,
        "preference": preference,
    }
    return requests.post(url, headers=headers, json=payload, timeout=20)


@given('walking segments with WSM scores exist around Mulyorejo')
def step_walking_segments_with_wsm_scores(context):
    php = """
    Illuminate\\Support\\Facades\\DB::table('segmen_jalur')
        ->where('nama_jalan', 'like', 'Jl. Rute BDD%')
        ->delete();

    $unique = rand(10000, 99999);
    $segments = [
        ['Jl. Rute BDD Langsung', 'LINESTRING(112.8070 -7.2700, 112.8079 -7.2700)', 25.00],
        ['Jl. Rute BDD Aman A', 'LINESTRING(112.8070 -7.2700, 112.80745 -7.2696)', 96.00],
        ['Jl. Rute BDD Aman B', 'LINESTRING(112.80745 -7.2696, 112.8079 -7.2700)', 96.00],
    ];

    $ids = [];
    foreach ($segments as $index => $segment) {
        $ids[] = Illuminate\\Support\\Facades\\DB::table('segmen_jalur')->insertGetId([
            'osm_way_id' => 98000 + $unique + $index,
            'u_node' => 88000 + $unique + $index,
            'v_node' => 78000 + $unique + $index,
            'nama_jalan' => $segment[0],
            'geom' => Illuminate\\Support\\Facades\\DB::raw("ST_GeogFromText('SRID=4326;" . $segment[1] . "')"),
            'panjang_m' => 120.0,
            'skor_kelayakan' => $segment[2],
            'tanggal_update' => now(),
        ], 'segmen_id');
    }

    echo json_encode($ids);
    """
    output = context.run_tinker(php).strip().splitlines()
    context.route_segment_ids = json.loads(output[-1]) if output else []
    assert len(context.route_segment_ids) == 3, "Gagal menyiapkan segmen uji rute."


@when('I request a walking route with preference "{preference}"')
def step_request_walking_route(context, preference):
    context.response = request_route(
        context,
        ROUTE_ORIGIN,
        ROUTE_DESTINATION,
        preference,
    )
    assert context.response.status_code == 200, context.response.text
    context.route_data = context.response.json()


@then('the route response should contain distance, duration, and polyline')
def step_route_response_has_summary(context):
    data = context.route_data
    assert data.get('success') is True
    assert data.get('distance_m', 0) > 0
    assert data.get('duration_min', 0) > 0
    assert len(data.get('polyline') or []) >= 2
    assert data.get('engine') in {'postgis_astar', 'corridor_fallback'}


@then('every route segment should expose its WSM score')
def step_every_segment_has_wsm_score(context):
    segments = context.route_data.get('segments') or []
    assert segments, "Rute tidak memiliki segmen."
    for segment in segments:
        assert 'score' in segment
        assert 0 <= segment['score'] <= 100
        assert segment['tingkat'] in {'SANGAT_LAYAK', 'PERLU_PERHATIAN', 'TIDAK_LAYAK'}


@then('the safest route should avoid the damaged segment')
def step_safest_route_avoids_damaged_segment(context):
    damaged_id = context.route_segment_ids[0]
    used_ids = [segment.get('segmen_id') for segment in context.route_data.get('segments', [])]
    assert damaged_id not in used_ids, "Rute teraman masih melewati segmen dengan skor rendah."

    context.safest_distance = context.route_data['distance_m']
    context.safest_average_score = context.route_data['summary']['average_score']


@then('the fastest route should use the damaged shortcut')
def step_fastest_route_uses_shortcut(context):
    damaged_id = context.route_segment_ids[0]
    used_ids = [segment.get('segmen_id') for segment in context.route_data.get('segments', [])]
    assert damaged_id in used_ids, "Rute tercepat seharusnya memilih jalur terpendek."
    context.fastest_distance = context.route_data['distance_m']


@then('the fastest route should not be longer than the safest route')
def step_fastest_not_longer_than_safest(context):
    if not hasattr(context, 'safest_distance'):
        safest = request_route(context, ROUTE_ORIGIN, ROUTE_DESTINATION, 'safest')
        assert safest.status_code == 200, safest.text
        context.safest_distance = safest.json()['distance_m']

    assert context.fastest_distance <= context.safest_distance + 0.1


@when('I request a walking route with invalid coordinates')
def step_request_route_with_invalid_coordinates(context):
    url = f"{context.api_url}/routes/recommend"
    headers = {"Authorization": f"Bearer {context.warga_token}"}
    payload = {
        "origin": {"lat": 200, "lng": 112.8070},
        "destination": {"lat": -7.2700},
        "preference": "scenic",
    }
    context.response = requests.post(url, headers=headers, json=payload, timeout=20)


@then('the route response should return status 422')
def step_route_response_status_422(context):
    assert context.response.status_code == 422, f"Expected 422, got {context.response.status_code}: {context.response.text}"
    body = context.response.json()
    assert body.get('error', {}).get('code') == 'INVALID_ROUTE_PARAMETERS'


@when('I request a walking route outside the mapped network')
def step_request_route_outside_network(context):
    context.response = request_route(
        context,
        {"lat": -7.0000, "lng": 112.0000},
        {"lat": -7.0010, "lng": 112.0010},
        'safest',
    )
    assert context.response.status_code == 200, context.response.text
    context.route_data = context.response.json()


@then('the route response should still contain a direct fallback')
def step_route_direct_fallback(context):
    data = context.route_data
    assert data['engine'] == 'direct_fallback'
    assert data['summary']['fallback'] is True
    assert len(data['polyline']) >= 2
    assert 'NETWORK_FALLBACK' in [warning['code'] for warning in data['warnings']]
