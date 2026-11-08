import requests
from behave import given, when, then, step


@given('a report exists with status "TERVALIDASI" and no repair status')
def step_report_exists_tervalidasi_no_repair(context):
    php = """
    $warga = App\\Models\\Warga::first();
    $unique = rand(10000, 99999);
    $segId = Illuminate\\Support\\Facades\\DB::table('segmen_jalur')->insertGetId([
        'osm_way_id' => 85000 + $unique,
        'u_node' => 75000 + $unique,
        'v_node' => 65000 + $unique,
        'nama_jalan' => 'Jl. Rusak ' . $unique,
        'geom' => Illuminate\\Support\\Facades\\DB::raw("ST_GeogFromText('SRID=4326;LINESTRING(112.7950 -7.2660, 112.7980 -7.2665)')"),
        'panjang_m' => 300.0,
        'skor_kelayakan' => 100.00,
        'tanggal_update' => now(),
    ], 'segmen_id');

    $lokasiId = Illuminate\\Support\\Facades\\DB::table('lokasi')->insertGetId([
        'latitude' => -7.2660,
        'longitude' => 112.7950,
        'geom' => Illuminate\\Support\\Facades\\DB::raw("ST_SetSRID(ST_Point(112.7950, -7.2660), 4326)"),
        'alamat' => 'Jl. Rusak 1',
        'segmen_id' => $segId,
    ], 'lokasi_id');

    $lapId = Illuminate\\Support\\Facades\\DB::table('laporan')->insertGetId([
        'warga_id' => $warga->warga_id,
        'kategori_id' => 1,
        'lokasi_id' => $lokasiId,
        'deskripsi' => 'Trotoar amblas perlu diperbaiki',
        'status' => 'TERVALIDASI',
        'status_perbaikan' => null,
        'tanggal_lapor' => now(),
        'tanggal_validasi' => now(),
    ], 'laporan_id');

    app(App\\Services\\SegmentScoreService::class)->recalculate($segId);
    echo $lapId . '||' . $segId . '||' . $warga->warga_id;
    """
    res = context.run_tinker(php).strip()
    parts = res.split('||')
    context.f9_report_id = int(parts[0])
    context.f9_segmen_id = int(parts[1])
    context.f9_warga_id = int(parts[2])
    context.current_report_id = context.f9_report_id
    context.current_report_warga_id = context.f9_warga_id
    context.current_report_segmen_id = context.f9_segmen_id


@when('I open the report from "/admin/status-laporan"')
def step_open_report_from_status_laporan(context):
    url = f"{context.api_url}/reports/{context.f9_report_id}"
    headers = {"Authorization": f"Bearer {context.admin_token}"}
    resp = requests.get(url, headers=headers, timeout=10)
    assert resp.status_code == 200, f"Failed to open report: {resp.text}"
    context.f9_report_detail = resp.json().get('data', {})


@when('I select "Sedang Diperbaiki" from "Status Perbaikan"')
def step_select_sedang_diperbaiki(context):
    context.selected_repair_status = "SEDANG_DIPERBAIKI"


@when('I select "Sudah Diperbaiki" and press "Simpan"')
def step_select_sudah_diperbaiki_and_save(context):
    url = f"{context.api_url}/admin/reports/{context.f9_report_id}/repair-status"
    headers = {"Authorization": f"Bearer {context.admin_token}"}
    payload = {"status_perbaikan": "SUDAH_DIPERBAIKI"}
    context.response = requests.patch(url, json=payload, headers=headers, timeout=10)


@when('I press "Simpan" without selecting "Status Perbaikan"')
def step_save_without_status(context):
    url = f"{context.api_url}/admin/reports/{context.f9_report_id}/repair-status"
    headers = {"Authorization": f"Bearer {context.admin_token}"}
    payload = {}
    context.response = requests.patch(url, json=payload, headers=headers, timeout=10)


@when('I press "Simpan"')
def step_press_save_repair_status(context):
    status_to_send = getattr(context, 'selected_repair_status', None)
    url = f"{context.api_url}/admin/reports/{context.f9_report_id}/repair-status"
    headers = {"Authorization": f"Bearer {context.admin_token}"}
    payload = {"status_perbaikan": status_to_send} if status_to_send else {}
    context.response = requests.patch(url, json=payload, headers=headers, timeout=10)


@then('the repair status should be "{status}"')
def step_repair_status_should_be(context, status):
    php = f"echo App\\Models\\Laporan::find({context.f9_report_id})->status_perbaikan;"
    actual_status = context.run_tinker(php).strip()
    assert actual_status == status, f"Expected repair status '{status}', got '{actual_status}'"


@step('the segment score should not change')
def step_segment_score_not_change(context):
    php = f"echo App\\Models\\SegmenJalur::find({context.f9_segmen_id})->skor_kelayakan;"
    score = float(context.run_tinker(php).strip())
    # Should still be < 100 because SEDANG_DIPERBAIKI is still an active report
    assert score < 100.0, f"Expected score to remain < 100, got {score}"


@step('the segment score of the report location should be recalculated')
def step_segment_score_recalculated(context):
    php = f"echo App\\Models\\SegmenJalur::find({context.f9_segmen_id})->skor_kelayakan;"
    score = float(context.run_tinker(php).strip())
    # Should recover back to 100.00
    assert score == 100.0, f"Expected recovered score 100, got {score}"


@step('the repair status should remain unchanged')
def step_repair_status_unchanged(context):
    php = f"echo App\\Models\\Laporan::find({context.f9_report_id})->status_perbaikan ?: 'NULL';"
    actual_status = context.run_tinker(php).strip()
    assert actual_status in ('NULL', ''), f"Expected repair status unchanged (NULL), got '{actual_status}'"


@given('the repair status is "SUDAH_DIPERBAIKI"')
def step_given_status_sudah_diperbaiki(context):
    php = f"""
    Illuminate\\Support\\Facades\\DB::table('laporan')->where('laporan_id', {context.f9_report_id})->update([
        'status_perbaikan' => 'SUDAH_DIPERBAIKI',
        'tanggal_perbaikan' => now(),
    ]);
    """
    context.run_tinker(php)


@when('I try to set "SEDANG_DIPERBAIKI"')
def step_try_set_sedang_diperbaiki(context):
    url = f"{context.api_url}/admin/reports/{context.f9_report_id}/repair-status"
    headers = {"Authorization": f"Bearer {context.admin_token}"}
    payload = {"status_perbaikan": "SEDANG_DIPERBAIKI"}
    context.response = requests.patch(url, json=payload, headers=headers, timeout=10)


@given('a report has status "MENUNGGU_VALIDASI_ADMIN"')
def step_report_status_menunggu_validasi(context):
    php = f"""
    Illuminate\\Support\\Facades\\DB::table('laporan')->where('laporan_id', {context.f9_report_id})->update([
        'status' => 'MENUNGGU_VALIDASI_ADMIN',
        'status_perbaikan' => null,
    ]);
    """
    context.run_tinker(php)


@when('I try to set a repair status')
def step_try_set_repair_status(context):
    url = f"{context.api_url}/admin/reports/{context.f9_report_id}/repair-status"
    headers = {"Authorization": f"Bearer {context.admin_token}"}
    payload = {"status_perbaikan": "SEDANG_DIPERBAIKI"}
    context.response = requests.patch(url, json=payload, headers=headers, timeout=10)


@then('the Warga should have a notification "{message}"')
def step_warga_notification_literal(context, message):
    warga_id = context.current_report_warga_id
    php = f"""
    $notif = App\\Models\\Notifikasi::where('penerima_id', {warga_id})
        ->where('penerima_tipe', 'WARGA')
        ->latest('tanggal_kirim')
        ->first();
    echo $notif ? $notif->pesan : 'NO_NOTIF';
    """
    msg = context.run_tinker(php).strip()
    assert msg != 'NO_NOTIF', f"No notification found in database for warga {warga_id}"
    assert message in msg, f"Expected '{message}' in notification '{msg}'"


@given('the database write fails')
def step_database_write_fails(context):
    php = """
    DB::unprepared("
        CREATE OR REPLACE FUNCTION trigger_fail_write() RETURNS trigger AS '
        BEGIN
            RAISE EXCEPTION ''Simulated DB failure'';
        END;
        ' LANGUAGE plpgsql;
        CREATE OR REPLACE TRIGGER fail_riwayat_write BEFORE INSERT ON riwayat_status_laporan
        FOR EACH ROW EXECUTE FUNCTION trigger_fail_write();
    ");
    """
    context.run_tinker(php)
    context.db_trigger_installed = True


@when('I press "Simpan" with a valid status')
def step_press_save_with_valid_status(context):
    try:
        url = f"{context.api_url}/admin/reports/{context.f9_report_id}/repair-status"
        headers = {"Authorization": f"Bearer {context.admin_token}"}
        payload = {"status_perbaikan": "SEDANG_DIPERBAIKI"}
        context.response = requests.patch(url, json=payload, headers=headers, timeout=10)
    finally:
        if getattr(context, 'db_trigger_installed', False):
            php = "DB::unprepared('DROP TRIGGER IF EXISTS fail_riwayat_write ON riwayat_status_laporan; DROP FUNCTION IF EXISTS trigger_fail_write();');"
            context.run_tinker(php)
            context.db_trigger_installed = False

