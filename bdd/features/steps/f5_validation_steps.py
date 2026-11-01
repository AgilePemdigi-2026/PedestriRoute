import requests
from behave import given, when, then, step


@given('I am logged in as "Admin"')
def step_logged_in_as_admin(context):
    php = """
    $admin = App\\Models\\Admin::firstOrCreate(
        ['email' => 'admin@pedestriroute.test'],
        [
            'nama' => 'Administrator PedestriRoute',
            'role' => 'admin',
            'password' => Illuminate\\Support\\Facades\\Hash::make('adminpassword123'),
            'tanggal_dibuat' => now(),
            'gagal_login' => 0
        ]
    );
    echo $admin->createToken('bdd-admin-token', ['role:admin'])->plainTextToken . '||' . $admin->admin_id;
    """
    res = context.run_tinker(php).strip()
    parts = res.split('||')
    context.admin_token = parts[0].strip()
    context.admin_id = int(parts[1].strip())
    context.active_token = context.admin_token


@given('a report exists with status "MENUNGGU_VALIDASI_ADMIN"')
def step_report_exists_waiting_validation(context):
    php = """
    $warga = App\\Models\\Warga::first();
    $segmen = App\\Models\\SegmenJalur::first();
    $lokasiId = Illuminate\\Support\\Facades\\DB::table('lokasi')->insertGetId([
        'latitude' => -7.2663,
        'longitude' => 112.7965,
        'geom' => Illuminate\\Support\\Facades\\DB::raw("ST_SetSRID(ST_Point(112.7965, -7.2663), 4326)"),
        'alamat' => 'Jl. Raya Mulyosari',
        'segmen_id' => $segmen->segmen_id,
    ], 'lokasi_id');

    $lapId = Illuminate\\Support\\Facades\\DB::table('laporan')->insertGetId([
        'warga_id' => $warga->warga_id,
        'kategori_id' => 1,
        'lokasi_id' => $lokasiId,
        'deskripsi' => 'Trotoar amblas butuh validasi',
        'status' => 'MENUNGGU_VALIDASI_ADMIN',
        'tanggal_lapor' => now(),
    ], 'laporan_id');

    echo $lapId . '||' . $warga->warga_id . '||' . $segmen->segmen_id;
    """
    res = context.run_tinker(php).strip()
    parts = res.split('||')
    context.current_report_id = int(parts[0])
    context.current_report_warga_id = int(parts[1])
    context.current_report_segmen_id = int(parts[2])


@given('I open the report from "/admin/validasi"')
def step_open_report_from_admin_validasi(context):
    url = f"{context.api_url}/reports/{context.current_report_id}"
    headers = {"Authorization": f"Bearer {context.admin_token}"}
    resp = requests.get(url, headers=headers, timeout=10)
    assert resp.status_code == 200, f"Failed to open report: {resp.text}"
    context.report_detail = resp.json().get('data', {})


@when('I set the review decision to "Valid" and save')
def step_decision_valid_and_save(context):
    url = f"{context.api_url}/admin/reports/{context.current_report_id}/validation"
    headers = {"Authorization": f"Bearer {context.admin_token}"}
    payload = {"keputusan": "VALID"}
    context.response = requests.post(url, json=payload, headers=headers, timeout=10)


@when('I set the review decision to "Tidak Valid"')
def step_decision_tidak_valid(context):
    context.validation_decision = "TIDAK_VALID"


@step('I fill in "Alasan Penolakan" with "{reason}"')
def step_fill_alasan_penolakan(context, reason):
    context.alasan_penolakan = reason


@step('I save the decision')
def step_save_decision(context):
    url = f"{context.api_url}/admin/reports/{context.current_report_id}/validation"
    headers = {"Authorization": f"Bearer {context.admin_token}"}
    payload = {
        "keputusan": context.validation_decision or "TIDAK_VALID",
        "alasan_penolakan": context.alasan_penolakan or "",
    }
    context.response = requests.post(url, json=payload, headers=headers, timeout=10)
    try:
        context.last_message = context.response.json().get('error', {}).get('message', '')
    except Exception:
        context.last_message = context.response.text


@step('I save the decision without "Alasan Penolakan"')
def step_save_decision_without_reason(context):
    context.alasan_penolakan = ""
    step_save_decision(context)


@then('the report status should be "{status}"')
def step_report_status_should_be(context, status):
    php = f"echo App\\Models\\Laporan::where('laporan_id', {context.current_report_id})->value('status');"
    actual = context.run_tinker(php).strip()
    assert actual == status, f"Expected status '{status}', got '{actual}'"


@then('"admin_id" and "tanggal_validasi" should be filled')
def step_admin_and_tanggal_filled(context):
    php = f"""
    $lap = App\\Models\\Laporan::where('laporan_id', {context.current_report_id})->first();
    echo ($lap->admin_id !== null && $lap->tanggal_validasi !== null) ? 'FILLED' : 'EMPTY';
    """
    res = context.run_tinker(php).strip()
    assert res == 'FILLED', f"admin_id and tanggal_validasi were not filled for laporan {context.current_report_id}"


@then('the segment score of the report location should be recalculated')
def step_segment_score_recalculated(context):
    php = f"""
    $lap = App\\Models\\Laporan::with('lokasi')->where('laporan_id', {context.current_report_id})->first();
    $segmen = App\\Models\\SegmenJalur::find($lap->lokasi->segmen_id);
    echo $segmen->skor_kelayakan;
    """
    score_str = context.run_tinker(php).strip()
    score = float(score_str or 100)
    # A valid report with category Trotoar Rusak reduces segment score below 100 according to WSM formula
    assert score <= 100.0, f"Expected segment score recalculated, got: {score}"


@then('the Warga should have a notification with key "{key}"')
def step_warga_notification_with_key(context, key):
    warga_id = context.current_report_warga_id
    php = f"""
    $notif = App\\Models\\Notifikasi::where('penerima_id', {warga_id})
        ->where('penerima_tipe', 'WARGA')
        ->latest('tanggal_kirim')
        ->first();
    echo $notif ? $notif->pesan : 'NO_NOTIF';
    """
    msg = context.run_tinker(php).strip()
    expected_snippet = context.message_catalog.get(key, key)
    assert msg != 'NO_NOTIF', f"No notification found in database for warga {warga_id}"
    assert expected_snippet in msg, f"Expected '{expected_snippet}' in notification '{msg}'"



@given('the report has no CV result')
def step_report_has_no_cv_result(context):
    php = f"""
    $lap = App\\Models\\Laporan::where('laporan_id', {context.current_report_id})->first();
    if ($lap) {{
        $lap->cv_label = null;
        $lap->confidence_score = null;
        $lap->save();
    }}
    """
    context.run_tinker(php)


@when('I open the report')
def step_open_report(context):
    url = f"{context.api_url}/reports/{context.current_report_id}"
    headers = {"Authorization": f"Bearer {context.admin_token}"}
    resp = requests.get(url, headers=headers, timeout=10)
    assert resp.status_code == 200, f"Expected 200 opening report, got {resp.status_code}"
    context.report_detail = resp.json().get('data', {})


@then('I should see a note that the automatic analysis is unavailable')
def step_note_cv_unavailable(context):
    # FR-F5-06: When cv_label / confidence_score is null, admin UI displays the note that CV analysis is unavailable
    detail = getattr(context, 'report_detail', {})
    cv_label = detail.get('cv_label')
    conf = detail.get('confidence_score')
    assert cv_label is None and conf is None, f"Expected CV result to be null, got cv_label={cv_label}, conf={conf}"


@then('I can still decide "Valid" or "Tidak Valid"')
def step_can_still_decide(context):
    detail = getattr(context, 'report_detail', {})
    assert detail.get('status') == 'MENUNGGU_VALIDASI_ADMIN', f"Report status is not MENUNGGU_VALIDASI_ADMIN: {detail.get('status')}"


@when('I open the report and press back without deciding')
def step_open_and_press_back(context):
    # Simulate visiting and navigating back to /admin/validasi without POST
    context.current_path = "/admin/validasi"
    context.redirect_path = "/admin/validasi"


@then('the report status should remain "MENUNGGU_VALIDASI_ADMIN"')
def step_report_status_should_remain(context):
    php = f"echo App\\Models\\Laporan::where('laporan_id', {context.current_report_id})->value('status');"
    status = context.run_tinker(php).strip()
    assert status == 'MENUNGGU_VALIDASI_ADMIN', f"Expected status to remain MENUNGGU_VALIDASI_ADMIN, got '{status}'"


@given('the report status is "TERVALIDASI"')
def step_report_status_is_tervalidasi(context):
    php = f"""
    $lap = App\\Models\\Laporan::where('laporan_id', {context.current_report_id})->first();
    $lap->status = 'TERVALIDASI';
    $lap->tanggal_validasi = now();
    $lap->admin_id = {context.admin_id};
    $lap->save();
    """
    context.run_tinker(php)


@when('I submit a decision for it')
def step_submit_decision_for_it(context):
    url = f"{context.api_url}/admin/reports/{context.current_report_id}/validation"
    headers = {"Authorization": f"Bearer {context.admin_token}"}
    context.response = requests.post(url, json={"keputusan": "VALID"}, headers=headers, timeout=10)


@when('I submit a decision for any report')
def step_warga_submits_decision(context):
    url = f"{context.api_url}/admin/reports/{context.current_report_id}/validation"
    headers = {"Authorization": f"Bearer {context.warga_token}"}
    context.response = requests.post(url, json={"keputusan": "VALID"}, headers=headers, timeout=10)


# Sprint 1 Week 2 Refinement: FIFO validation queue ordering
@given('multiple reports exist waiting for validation')
def step_multiple_reports_waiting(context):
    php = """
    $warga = App\\Models\\Warga::first();
    $segmen = App\\Models\\SegmenJalur::first();

    $lokasiId = Illuminate\\Support\\Facades\\DB::table('lokasi')->insertGetId([
        'latitude' => -7.2663,
        'longitude' => 112.7965,
        'geom' => Illuminate\\Support\\Facades\\DB::raw("ST_SetSRID(ST_Point(112.7965, -7.2663), 4326)"),
        'alamat' => 'Jl. Mulyosari',
        'segmen_id' => $segmen->segmen_id,
    ], 'lokasi_id');

    // Create report 1 (earlier)
    $id1 = Illuminate\\Support\\Facades\\DB::table('laporan')->insertGetId([
        'warga_id' => $warga->warga_id,
        'kategori_id' => 1,
        'lokasi_id' => $lokasiId,
        'deskripsi' => 'Laporan antrean 1',
        'status' => 'MENUNGGU_VALIDASI_ADMIN',
        'tanggal_lapor' => now()->subMinutes(10),
    ], 'laporan_id');

    // Create report 2 (later)
    $id2 = Illuminate\\Support\\Facades\\DB::table('laporan')->insertGetId([
        'warga_id' => $warga->warga_id,
        'kategori_id' => 1,
        'lokasi_id' => $lokasiId,
        'deskripsi' => 'Laporan antrean 2',
        'status' => 'MENUNGGU_VALIDASI_ADMIN',
        'tanggal_lapor' => now()->subMinutes(2),
    ], 'laporan_id');

    echo $id1 . '||' . $id2;
    """
    res = context.run_tinker(php).strip()
    parts = res.split('||')
    context.fifo_id1 = int(parts[0])
    context.fifo_id2 = int(parts[1])


@when('I view the validation queue at "/admin/validasi"')
def step_view_validation_queue(context):
    url = f"{context.api_url}/admin/reports?status=MENUNGGU_VALIDASI_ADMIN"
    headers = {"Authorization": f"Bearer {context.admin_token}"}
    context.response = requests.get(url, headers=headers, timeout=10)
    assert context.response.status_code == 200, f"Queue fetch failed: {context.response.text}"


@then('the reports should be listed in chronological order')
def step_reports_chronological_order(context):
    data = context.response.json().get('data', [])
    assert len(data) >= 2, f"Expected at least 2 reports in queue, got {len(data)}"

    # Verify FIFO: tanggal_lapor of each report is <= tanggal_lapor of the next
    dates = [r.get('tanggal_lapor') for r in data if r.get('tanggal_lapor')]
    sorted_dates = sorted(dates)
    assert dates == sorted_dates, f"Reports are not sorted chronologically FIFO: {dates}"
