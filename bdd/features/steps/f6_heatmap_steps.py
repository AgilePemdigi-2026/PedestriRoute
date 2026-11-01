import requests
from behave import given, when, then, step


@given('segments exist in the viewport')
def step_segments_exist(context):
    php = """
    $count = App\\Models\\SegmenJalur::count();
    if ($count == 0) {
        Illuminate\\Support\\Facades\\Artisan::call('db:seed', ['--class' => 'SegmenJalurSeeder']);
    }
    echo App\\Models\\SegmenJalur::count();
    """
    count = int(context.run_tinker(php).strip())
    assert count > 0, "No segments found in database."


@when('I open "/beranda-warga" with "Semua Kategori"')
def step_open_beranda_semua_kategori(context):
    context.current_path = "/beranda-warga"
    # Call heatmap API with default viewport in Mulyosari
    url = f"{context.api_url}/heatmap?min_lon=112.75&min_lat=-7.30&max_lon=112.85&max_lat=-7.24&zoom=15"
    headers = {"Authorization": f"Bearer {context.warga_token}"}
    context.response = requests.get(url, headers=headers, timeout=10)
    assert context.response.status_code == 200, f"Heatmap request failed: {context.response.text}"
    context.heatmap_data = context.response.json()


@then('I should see segments colored by feasibility level')
def step_see_segments_colored(context):
    features = context.heatmap_data.get('features', [])
    assert len(features) > 0, "No features returned in heatmap."
    valid_levels = {'SANGAT_LAYAK', 'PERLU_PERHATIAN', 'TIDAK_LAYAK'}
    for f in features:
        props = f.get('properties', {})
        assert 'tingkat' in props, "Feature missing 'tingkat' property."
        assert props['tingkat'] in valid_levels, f"Invalid tingkat: {props['tingkat']}"
        assert 'skor' in props, "Feature missing 'skor' property."


@then('I should see the legend with three levels')
def step_see_legend_three_levels(context):
    meta_counts = context.heatmap_data.get('meta', {}).get('counts', {})
    assert 'SANGAT_LAYAK' in meta_counts, "Missing SANGAT_LAYAK in meta.counts"
    assert 'PERLU_PERHATIAN' in meta_counts, "Missing PERLU_PERHATIAN in meta.counts"
    assert 'TIDAK_LAYAK' in meta_counts, "Missing TIDAK_LAYAK in meta.counts"


@when('I zoom in')
def step_zoom_in(context):
    url = f"{context.api_url}/heatmap?min_lon=112.79&min_lat=-7.27&max_lon=112.81&max_lat=-7.26&zoom=17"
    headers = {"Authorization": f"Bearer {context.warga_token}"}
    context.response = requests.get(url, headers=headers, timeout=10)
    assert context.response.status_code == 200
    context.heatmap_zoomed = context.response.json()


@then('the map should show a more detailed view')
def step_map_more_detailed(context):
    assert context.response.status_code == 200
    assert 'features' in context.heatmap_zoomed


@when('I drag the map to another area')
def step_drag_map(context):
    url = f"{context.api_url}/heatmap?min_lon=112.76&min_lat=-7.29&max_lon=112.78&max_lat=-7.27&zoom=15"
    headers = {"Authorization": f"Bearer {context.warga_token}"}
    context.response = requests.get(url, headers=headers, timeout=10)
    assert context.response.status_code == 200
    context.heatmap_dragged = context.response.json()


@then('the system should request heatmap data for the new viewport')
def step_request_heatmap_for_new_viewport(context):
    assert context.heatmap_dragged is not None
    assert 'features' in context.heatmap_dragged
    assert 'meta' in context.heatmap_dragged


@given('a segment has score 100')
def step_segment_has_score_100(context):
    php = """
    $unique = rand(10000, 99999);
    $segId = Illuminate\\Support\\Facades\\DB::table('segmen_jalur')->insertGetId([
        'osm_way_id' => 80000 + $unique,
        'u_node' => 70000 + $unique,
        'v_node' => 60000 + $unique,
        'nama_jalan' => 'Jl. Segmen Uji ' . $unique,
        'geom' => Illuminate\\Support\\Facades\\DB::raw("ST_GeogFromText('SRID=4326;LINESTRING(112.7950 -7.2660, 112.7980 -7.2665)')"),
        'panjang_m' => 300.0,
        'skor_kelayakan' => 100.00,
        'tanggal_update' => now(),
    ], 'segmen_id');
    echo $segId;
    """
    context.test_segmen_id = int(context.run_tinker(php).strip())


@when('a report on that segment becomes "TERVALIDASI"')
def step_report_becomes_tervalidasi(context):
    php = f"""
    $warga = App\\Models\\Warga::first();
    $lokasiId = Illuminate\\Support\\Facades\\DB::table('lokasi')->insertGetId([
        'latitude' => -7.2660,
        'longitude' => 112.7950,
        'geom' => Illuminate\\Support\\Facades\\DB::raw("ST_SetSRID(ST_Point(112.7950, -7.2660), 4326)"),
        'alamat' => 'Jl. Segmen Uji',
        'segmen_id' => {context.test_segmen_id},
    ], 'lokasi_id');

    $lapId = Illuminate\\Support\\Facades\\DB::table('laporan')->insertGetId([
        'warga_id' => $warga->warga_id,
        'kategori_id' => 1,
        'lokasi_id' => $lokasiId,
        'deskripsi' => 'Trotoar amblas butuh validasi',
        'status' => 'TERVALIDASI',
        'tanggal_lapor' => now(),
        'tanggal_validasi' => now(),
    ], 'laporan_id');

    app(App\\Services\\SegmentScoreService::class)->recalculate({context.test_segmen_id});
    echo $lapId;
    """
    context.test_laporan_id = int(context.run_tinker(php).strip())


@then('the segment score should be below 100')
def step_segment_score_below_100(context):
    php = f"echo App\\Models\\SegmenJalur::find({context.test_segmen_id})->skor_kelayakan;"
    score = float(context.run_tinker(php).strip())
    assert score < 100.0, f"Expected score below 100, got {score}"


@when('that report\'s repair status becomes "SUDAH_DIPERBAIKI"')
def step_report_becomes_sudah_diperbaiki(context):
    php = f"""
    Illuminate\\Support\\Facades\\DB::table('laporan')->where('laporan_id', {context.test_laporan_id})->update([
        'status_perbaikan' => 'SUDAH_DIPERBAIKI',
        'tanggal_perbaikan' => now(),
    ]);
    app(App\\Services\\SegmentScoreService::class)->recalculate({context.test_segmen_id});
    """
    context.run_tinker(php)


@then('the segment score should return to 100')
def step_segment_score_returns_to_100(context):
    php = f"echo App\\Models\\SegmenJalur::find({context.test_segmen_id})->skor_kelayakan;"
    score = float(context.run_tinker(php).strip())
    assert score == 100.0, f"Expected score 100, got {score}"


@step('a report with status "DITOLAK" should never change a segment score')
def step_report_ditolak_does_not_change_score(context):
    php = f"""
    $warga = App\\Models\\Warga::first();
    $lokasiId = Illuminate\\Support\\Facades\\DB::table('lokasi')->insertGetId([
        'latitude' => -7.2660,
        'longitude' => 112.7950,
        'geom' => Illuminate\\Support\\Facades\\DB::raw("ST_SetSRID(ST_Point(112.7950, -7.2660), 4326)"),
        'alamat' => 'Jl. Segmen Uji Ditolak',
        'segmen_id' => {context.test_segmen_id},
    ], 'lokasi_id');

    Illuminate\\Support\\Facades\\DB::table('laporan')->insert([
        'warga_id' => $warga->warga_id,
        'kategori_id' => 1,
        'lokasi_id' => $lokasiId,
        'deskripsi' => 'Laporan ditolak',
        'status' => 'DITOLAK',
        'alasan_penolakan' => 'Foto tidak jelas',
        'tanggal_lapor' => now(),
        'tanggal_validasi' => now(),
    ]);

    app(App\\Services\\SegmentScoreService::class)->recalculate({context.test_segmen_id});
    echo App\\Models\\SegmenJalur::find({context.test_segmen_id})->skor_kelayakan;
    """
    score = float(context.run_tinker(php).strip())
    assert score == 100.0, f"Expected score 100 after DITOLAK report, got {score}"


@given('no segments exist in the requested viewport')
def step_no_segments_in_viewport(context):
    # Pacific ocean coordinates
    url = f"{context.api_url}/heatmap?min_lon=0.0&min_lat=0.0&max_lon=0.1&max_lat=0.1&zoom=15"
    headers = {"Authorization": f"Bearer {context.warga_token}"}
    context.response = requests.get(url, headers=headers, timeout=10)
    assert context.response.status_code == 200
    data = context.response.json()
    assert len(data.get('features', [])) == 0
    # Simulate UI message trigger
    context.last_message = context.message_catalog.get("HEATMAP_NO_DATA")


@given('no active report exists for category "{category_name}"')
def step_no_active_report_for_category(context, category_name):
    php = f"""
    $cat = App\\Models\\KategoriMasalah::where('nama_kategori', '{category_name}')->first();
    if ($cat) {{
        Illuminate\\Support\\Facades\\DB::table('laporan')
            ->where('kategori_id', $cat->kategori_id)
            ->where('status', 'TERVALIDASI')
            ->update(['status_perbaikan' => 'SUDAH_DIPERBAIKI']);
        echo $cat->kategori_id;
    }} else {{
        echo '99999';
    }}
    """
    context.filter_category_id = int(context.run_tinker(php).strip())


@when('I select the "{category_name}" filter')
def step_select_category_filter(context, category_name):
    cat_id = getattr(context, 'filter_category_id', None)
    if not cat_id:
        php = f"""
        $cat = App\\Models\\KategoriMasalah::where('nama_kategori', '{category_name}')->first();
        echo $cat ? $cat->kategori_id : 999;
        """
        cat_id = int(context.run_tinker(php).strip())
    url = f"{context.api_url}/heatmap?min_lon=112.75&min_lat=-7.30&max_lon=112.85&max_lat=-7.24&zoom=15&kategori_id={cat_id}"
    headers = {"Authorization": f"Bearer {context.warga_token}"}
    context.response = requests.get(url, headers=headers, timeout=10)
    assert context.response.status_code == 200
    data = context.response.json()
    if len(data.get('features', [])) == 0:
        context.last_message = context.message_catalog.get("HEATMAP_FILTER_EMPTY")


@when('I select "Semua Kategori"')
def step_select_semua_kategori(context):
    url = f"{context.api_url}/heatmap?min_lon=112.75&min_lat=-7.30&max_lon=112.85&max_lat=-7.24&zoom=15"
    headers = {"Authorization": f"Bearer {context.warga_token}"}
    context.response = requests.get(url, headers=headers, timeout=10)
    assert context.response.status_code == 200
    context.heatmap_data = context.response.json()


@then('the heatmap should be shown again')
def step_heatmap_shown_again(context):
    assert len(context.heatmap_data.get('features', [])) > 0


@given('the viewport contains segments and some have a score below 100')
def step_viewport_contains_segments_some_below_100(context):
    # Ensure there is at least one segment with score < 100
    php = """
    $damaged = App\\Models\\SegmenJalur::where('skor_kelayakan', '<', 100)->first();
    if (!$damaged) {
        $seg = App\\Models\\SegmenJalur::first();
        $warga = App\\Models\\Warga::first();
        $lokasiId = Illuminate\\Support\\Facades\\DB::table('lokasi')->insertGetId([
            'latitude' => -7.2660,
            'longitude' => 112.7950,
            'geom' => Illuminate\\Support\\Facades\\DB::raw("ST_SetSRID(ST_Point(112.7950, -7.2660), 4326)"),
            'alamat' => 'Jl. Low Zoom Area',
            'segmen_id' => $seg->segmen_id,
        ], 'lokasi_id');

        Illuminate\\Support\\Facades\\DB::table('laporan')->insert([
            'warga_id' => $warga->warga_id,
            'kategori_id' => 1,
            'lokasi_id' => $lokasiId,
            'deskripsi' => 'Laporan rusak low zoom',
            'status' => 'TERVALIDASI',
            'tanggal_lapor' => now(),
            'tanggal_validasi' => now(),
        ]);
        app(App\\Services\\SegmentScoreService::class)->recalculate($seg->segmen_id);
    }
    """
    context.run_tinker(php)


@when('the client requests the heatmap with a zoom below CFG_HEATMAP_MIN_ZOOM')
def step_request_heatmap_low_zoom(context):
    # Zoom 12 is below default min zoom (14)
    url = f"{context.api_url}/heatmap?min_lon=112.75&min_lat=-7.30&max_lon=112.85&max_lat=-7.24&zoom=12"
    headers = {"Authorization": f"Bearer {context.warga_token}"}
    context.response = requests.get(url, headers=headers, timeout=10)
    assert context.response.status_code == 200
    context.low_zoom_data = context.response.json()


@then('the response should contain only segments with score below 100')
def step_response_contains_only_score_below_100(context):
    features = context.low_zoom_data.get('features', [])
    assert len(features) > 0, "Expected at least one problematic segment."
    for f in features:
        score = f.get('properties', {}).get('skor', 100.0)
        assert score < 100.0, f"Expected segment score < 100, got {score}"


@step('"meta.counts" should still count all segments in the viewport')
def step_meta_counts_counts_all(context):
    counts = context.low_zoom_data.get('meta', {}).get('counts', {})
    total_counted = counts.get('SANGAT_LAYAK', 0) + counts.get('PERLU_PERHATIAN', 0) + counts.get('TIDAK_LAYAK', 0)
    features_count = len(context.low_zoom_data.get('features', []))
    assert total_counted >= features_count, f"Total counted in meta ({total_counted}) should be >= features returned ({features_count})"
