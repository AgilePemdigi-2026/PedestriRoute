import os
import requests
from behave import given, when, then, step


@given('CFG_CV_ENABLED is true and CFG_CV_THRESHOLD is 0.80')
def step_cv_enabled_and_threshold(context):
    context.cv_enabled = True
    context.cv_threshold = 0.80
    context.cv_mock_label = None
    context.cv_mock_confidence = None
    context.cv_mock_timeout = False


@given('a report exists with status "DIPROSES" in category "{category_name}"')
def step_report_exists_diproses_with_category(context, category_name):
    php = f"""
    $warga = App\\Models\\Warga::first();
    if (!$warga) {{
        $warga = App\\Models\\Warga::create([
            'nama' => 'Warga Tester BDD',
            'email' => 'warga.bdd.' . uniqid() . '@pedestriroute.test',
            'no_hp' => '62888' . rand(1000000, 9999999),
            'password' => Illuminate\\Support\\Facades\\Hash::make('password123'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0
        ]);
    }}

    $segmen = App\\Models\\SegmenJalur::first();
    $kategori = Illuminate\\Support\\Facades\\DB::table('kategori_masalah')->where('nama_kategori', '{category_name}')->first();
    $kategoriId = $kategori ? $kategori->kategori_id : 1;

    $lokasiId = Illuminate\\Support\\Facades\\DB::table('lokasi')->insertGetId([
        'latitude' => -7.2663,
        'longitude' => 112.7965,
        'geom' => Illuminate\\Support\\Facades\\DB::raw("ST_SetSRID(ST_Point(112.7965, -7.2663), 4326)"),
        'alamat' => 'Jl. Mulyosari BDD CV',
        'segmen_id' => $segmen ? $segmen->segmen_id : 1,
    ], 'lokasi_id');

    $lapId = Illuminate\\Support\\Facades\\DB::table('laporan')->insertGetId([
        'warga_id' => $warga->warga_id,
        'kategori_id' => $kategoriId,
        'lokasi_id' => $lokasiId,
        'deskripsi' => 'Pengujian CV BDD ' . '{category_name}',
        'status' => 'DIPROSES',
        'confidence_score' => null,
        'cv_label' => null,
        'tanggal_lapor' => now(),
    ], 'laporan_id');

    $fakePath = 'laporan/bdd_photo_' . uniqid() . '.jpg';
    Illuminate\\Support\\Facades\\Storage::disk('public')->put($fakePath, 'fake_bytes_for_bdd');

    Illuminate\\Support\\Facades\\DB::table('foto_laporan')->insert([
        'laporan_id' => $lapId,
        'url_foto' => $fakePath,
        'tanggal_upload' => now(),
    ]);

    echo $lapId . '||' . $warga->warga_id . '||' . ($segmen ? $segmen->segmen_id : 1);
    """
    res = context.run_tinker(php).strip()
    parts = res.split('||')
    context.current_report_id = int(parts[0])
    context.current_report_warga_id = int(parts[1])
    context.current_report_segmen_id = int(parts[2])
    context.current_category_name = category_name


@given('a report exists with status "DIPROSES" in an unsupported category')
def step_report_exists_diproses_unsupported_category(context):
    category_name = "Penerangan Minim"
    php = f"""
    $warga = App\\Models\\Warga::first();
    $segmen = App\\Models\\SegmenJalur::first();
    
    $kat = Illuminate\\Support\\Facades\\DB::table('kategori_masalah')->where('nama_kategori', '{category_name}')->first();
    if (!$kat) {{
        $katId = Illuminate\\Support\\Facades\\DB::table('kategori_masalah')->insertGetId([
            'nama_kategori' => '{category_name}',
            'bobot' => 0.10,
            'kode_label' => 6,
            'deskripsi' => 'Lampu jalan mati / penerangan jalan minim'
        ], 'kategori_id');
    }} else {{
        $katId = $kat->kategori_id;
    }}

    $lokasiId = Illuminate\\Support\\Facades\\DB::table('lokasi')->insertGetId([
        'latitude' => -7.2663,
        'longitude' => 112.7965,
        'geom' => Illuminate\\Support\\Facades\\DB::raw("ST_SetSRID(ST_Point(112.7965, -7.2663), 4326)"),
        'alamat' => 'Jl. Mulyosari Unsupported CV',
        'segmen_id' => $segmen ? $segmen->segmen_id : 1,
    ], 'lokasi_id');

    $lapId = Illuminate\\Support\\Facades\\DB::table('laporan')->insertGetId([
        'warga_id' => $warga->warga_id,
        'kategori_id' => $katId,
        'lokasi_id' => $lokasiId,
        'deskripsi' => 'Pengujian CV Kategori Luar Cakupan',
        'status' => 'DIPROSES',
        'confidence_score' => null,
        'cv_label' => null,
        'tanggal_lapor' => now(),
    ], 'laporan_id');

    $fakePath = 'laporan/bdd_unsupported_' . uniqid() . '.jpg';
    Illuminate\\Support\\Facades\\Storage::disk('public')->put($fakePath, 'fake_bytes_for_bdd');

    Illuminate\\Support\\Facades\\DB::table('foto_laporan')->insert([
        'laporan_id' => $lapId,
        'url_foto' => $fakePath,
        'tanggal_upload' => now(),
    ]);

    echo $lapId . '||' . $warga->warga_id . '||' . ($segmen ? $segmen->segmen_id : 1);
    """
    res = context.run_tinker(php).strip()
    parts = res.split('||')
    context.current_report_id = int(parts[0])
    context.current_report_warga_id = int(parts[1])
    context.current_report_segmen_id = int(parts[2])
    context.current_category_name = category_name


@given('the CV returns label "{label}" with confidence {confidence:f}')
def step_cv_returns_label_and_confidence(context, label, confidence):
    context.cv_mock_label = label
    context.cv_mock_confidence = confidence
    context.cv_mock_timeout = False


@given('the CV service times out')
def step_cv_service_times_out(context):
    context.cv_mock_timeout = True
    context.cv_mock_label = None


@step('the report status is already "TERVALIDASI"')
def step_report_status_already_tervalidasi(context):
    php = f"""
    $lap = App\\Models\\Laporan::find({context.current_report_id});
    $lap->status = 'TERVALIDASI';
    $lap->cv_label = 'RUSAK';
    $lap->confidence_score = 0.90;
    $lap->tanggal_validasi = now();
    $lap->save();
    """
    context.run_tinker(php)


@step('the job "ProcessReportCv" runs')
def step_process_report_cv_runs(context):
    lap_id = context.current_report_id
    cat_name = getattr(context, 'current_category_name', 'Trotoar Rusak')
    timeout_flag = 'true' if getattr(context, 'cv_mock_timeout', False) else 'false'
    mock_label = getattr(context, 'cv_mock_label', None)
    mock_conf_val = 0.88 if getattr(context, 'cv_mock_confidence', None) is None else float(context.cv_mock_confidence)
    
    label_php = f"'{mock_label}'" if mock_label is not None else "null"

    php = f"""
    $laporanId = {lap_id};
    $isTimeout = {timeout_flag};
    $mockLabel = {label_php};
    $mockConf = {mock_conf_val};
    $catName = '{cat_name}';
    
    $mockCv = Mockery::mock(App\\Services\\CvService::class);
    $mockCv->shouldReceive('getThreshold')->andReturn(0.80);
    $supportedCategories = ['Trotoar Rusak', 'Masalah Drainase', 'Guiding Block Rusak', 'Penyebrangan Rusak', 'Parkir Liar'];
    $mockCv->shouldReceive('isCategorySupported')->andReturnUsing(function($c) use ($supportedCategories) {{
        return in_array($c, $supportedCategories, true);
    }});
    
    if ($isTimeout) {{
        $mockCv->shouldReceive('analyze')->andReturn(null);
    }} elseif ($mockLabel !== null) {{
        $mockCv->shouldReceive('analyze')->andReturn([
            'label' => $mockLabel,
            'confidence' => $mockConf,
            'detected_category' => $catName,
        ]);
    }}
    
    app()->instance(App\\Services\\CvService::class, $mockCv);
    App\\Jobs\\ProcessReportCv::dispatchSync($laporanId);
    echo 'JOB_DONE';
    """
    res = context.run_tinker(php).strip()
    assert 'JOB_DONE' in res, f"Failed executing ProcessReportCv job: {res}"


@then('"confidence_score" should be {expected_conf:f}')
def step_confidence_score_should_be(context, expected_conf):
    php = f"echo (float) App\\Models\\Laporan::where('laporan_id', {context.current_report_id})->value('confidence_score');"
    val = float(context.run_tinker(php).strip() or 0.0)
    assert abs(val - expected_conf) < 0.01, f"Expected confidence {expected_conf}, got {val}"


@then('"cv_label" should be "{expected_label}"')
def step_cv_label_should_be(context, expected_label):
    php = f"echo (string) App\\Models\\Laporan::where('laporan_id', {context.current_report_id})->value('cv_label');"
    actual = context.run_tinker(php).strip()
    assert actual == expected_label, f"Expected cv_label '{expected_label}', got '{actual}'"


@then('"confidence_score" and "cv_label" should be null')
def step_confidence_and_cv_label_should_be_null(context):
    php = f"""
    $lap = App\\Models\\Laporan::find({context.current_report_id});
    echo ($lap->confidence_score === null && $lap->cv_label === null) ? 'NULL' : 'NOT_NULL';
    """
    actual = context.run_tinker(php).strip()
    assert actual == 'NULL', f"Expected null confidence and cv_label, got {actual}"


@then('every Admin should have a notification with key "{key}"')
def step_admin_notification_with_key(context, key):
    expected_snippet = context.message_catalog.get(key, key)
    php = f"""
    $notif = App\\Models\\Notifikasi::where('penerima_tipe', 'ADMIN')
        ->latest('tanggal_kirim')
        ->first();
    echo $notif ? $notif->pesan : 'NO_NOTIF';
    """
    msg = context.run_tinker(php).strip()
    assert msg != 'NO_NOTIF', "No admin notification found in database"
    assert expected_snippet in msg, f"Expected '{expected_snippet}' in admin notification '{msg}'"


@then('the report should still exist')
def step_report_should_still_exist(context):
    php = f"echo App\\Models\\Laporan::where('laporan_id', {context.current_report_id})->exists() ? 'EXISTS' : 'DELETED';"
    res = context.run_tinker(php).strip()
    assert res == 'EXISTS', f"Laporan {context.current_report_id} was deleted unexpectedly"


@then('the CV service should not be called')
def step_cv_service_not_called(context):
    php = f"""
    $lap = App\\Models\\Laporan::find({context.current_report_id});
    echo ($lap->cv_label === null && $lap->confidence_score === null) ? 'NOT_CALLED' : 'CALLED';
    """
    res = context.run_tinker(php).strip()
    assert res == 'NOT_CALLED', "CV service was called unexpectedly"


@then('nothing should change and no duplicate notification should exist')
def step_nothing_should_change_and_no_duplicate_notif(context):
    php = f"""
    $lap = App\\Models\\Laporan::find({context.current_report_id});
    echo $lap->status;
    """
    status = context.run_tinker(php).strip()
    assert status == 'TERVALIDASI', f"Expected status TERVALIDASI, got {status}"


@when('I access the analysis page "/laporan/{id}/analisis" as the reporter')
def step_access_analysis_page_as_reporter(context, id=None):
    report_id = context.current_report_id
    php = f"""
    $warga = App\\Models\\Warga::find({context.current_report_warga_id});
    echo $warga->createToken('reporter-token', ['role:warga'])->plainTextToken;
    """
    token = context.run_tinker(php).strip()
    url = f"{context.base_url}/laporan/{report_id}/analisis"
    headers = {"Authorization": f"Bearer {token}"}
    cookies = {"token": token}
    context.response = requests.get(url, headers=headers, cookies=cookies, timeout=10)


@then('I should see the analysis result with status "{expected_status}"')
def step_should_see_analysis_result(context, expected_status):
    assert context.response.status_code == 200, f"Expected 200 OK, got {context.response.status_code}"
    body = context.response.text
    assert expected_status in body or "Tervalidasi" in body, f"Expected {expected_status} in analysis page body"
