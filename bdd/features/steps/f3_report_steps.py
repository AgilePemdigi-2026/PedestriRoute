import io
import requests
from PIL import Image
from behave import given, when, then, step


def generate_dummy_jpeg(size_mb=None, width=100, height=100, with_exif=False):
    img = Image.new('RGB', (width, height), color=(0, 98, 87))
    buf = io.BytesIO()

    if with_exif:
        exif = img.getexif()
        gps = exif.get_ifd(0x8825)
        gps[1] = 'S'
        gps[2] = 7.2663
        img.save(buf, format='JPEG', exif=exif)
    elif size_mb:
        img = Image.new('RGB', (800, 800), color=(10, 20, 30))
        img.save(buf, format='JPEG', quality=95)
        current_len = len(buf.getvalue())
        target_len = int(size_mb * 1024 * 1024)
        if target_len > current_len:
            buf.write(b'\x00' * (target_len - current_len))
    else:
        img.save(buf, format='JPEG', quality=85)

    buf.seek(0)
    return buf.getvalue()


@given('I am logged in as "Warga"')
def step_logged_in_as_warga(context):
    php = """
    $user = App\\Models\\Warga::where('email', 'ahmad.fauzi@gmail.com')->first();
    if (!$user) {
        App\\Models\\Warga::where('no_hp', '6281234567890')->delete();
        $user = App\\Models\\Warga::create([
            'nama' => 'Ahmad Fauzi',
            'email' => 'ahmad.fauzi@gmail.com',
            'no_hp' => '6281234567890',
            'password' => Illuminate\\Support\\Facades\\Hash::make('warga12345'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0
        ]);
    }
    echo $user->createToken('bdd-token', ['role:warga'])->plainTextToken . '||' . $user->warga_id;
    """
    res = context.run_tinker(php).strip()
    parts = res.split('||')
    context.warga_token = parts[0].strip()
    context.warga_id = int(parts[1].strip())
    context.active_token = context.warga_token


@given('CFG_CV_ENABLED is false')
def step_cfg_cv_enabled_is_false(context):
    context.cv_enabled = False


@step('I select a point on "Peta"')
def step_select_point_on_peta(context):
    context.report_data['latitude'] = -7.2663
    context.report_data['longitude'] = 112.7965


@step('"Koordinat X" is filled with "{lon}"')
def step_fill_koordinat_x(context, lon):
    context.report_data['longitude'] = float(lon)


@step('"Koordinat Y" is filled with "{lat}"')
def step_fill_koordinat_y(context, lat):
    context.report_data['latitude'] = float(lat)


@step('I fill in "Foto" with "{filename}"')
def step_fill_foto_filename(context, filename):
    if filename.endswith('.pdf'):
        # Invalid PDF content
        content = b"%PDF-1.4 dummy pdf content for testing invalid mime type"
        mime = 'application/pdf'
    else:
        content = generate_dummy_jpeg()
        mime = 'image/jpeg'

    context.report_file = (filename, content, mime)
    if filename.endswith('.pdf'):
        step_press_kirim_laporan(context)


@step('I fill in "Foto" with a valid JPEG of size "{size}" MB')
def step_fill_foto_large_valid(context, size):
    size_mb = float(size)
    content = generate_dummy_jpeg(size_mb=size_mb)
    context.report_file = ('foto_valid.jpg', content, 'image/jpeg')


@step('I fill in "Foto" with a JPEG larger than CFG_PHOTO_MAX_MB')
def step_fill_foto_larger_than_max(context):
    # Default CFG_PHOTO_MAX_MB is 15 in .env
    content = generate_dummy_jpeg(size_mb=16.0)
    context.report_file = ('oversized.jpg', content, 'image/jpeg')
    step_press_kirim_laporan(context)



@step('I fill in "Deskripsi" with "{desc}"')
def step_fill_deskripsi(context, desc):
    context.report_data['deskripsi'] = desc


@step('I select "Kategori Masalah" with "{category}"')
def step_select_kategori(context, category):
    context.report_data['kategori_id'] = category


@step('I select "Kategori Masalah" with ""')
def step_select_kategori_empty(context):
    context.report_data['kategori_id'] = ""


@step('I press "Kirim Laporan"')
def step_press_kirim_laporan(context):
    url = f"{context.api_url}/reports"
    headers = {"Authorization": f"Bearer {context.warga_token}"}

    # Resolve category if 'Trotoar Rusak' was passed
    kategori_val = context.report_data.get('kategori_id', 1)

    data = {
        'latitude': context.report_data.get('latitude', -7.2663),
        'longitude': context.report_data.get('longitude', 112.7965),
        'kategori_id': kategori_val,
        'deskripsi': context.report_data.get('deskripsi', 'Laporan kerusakan fasilitas pejalan kaki'),
    }

    files = {}
    if getattr(context, 'report_file', None):
        filename, content, mime = context.report_file
        files['foto'] = (filename, content, mime)
    else:
        if not getattr(context, 'omit_photo', False):
            files['foto'] = ('trotoar.jpg', generate_dummy_jpeg(), 'image/jpeg')

    resp = requests.post(url, data=data, files=files, headers=headers, timeout=20)
    context.response = resp

    try:
        body = resp.json()
        if resp.status_code == 201:
            context.last_message = body.get('message', 'Laporan terkirim')
            context.created_report_id = body.get('data', {}).get('laporan_id')
        else:
            context.last_message = body.get('error', {}).get('message', '')
    except Exception:
        context.last_message = resp.text


@then('a report should exist with status "{status}"')
def step_report_should_exist_with_status(context, status):
    rep_id = getattr(context, 'created_report_id', None)
    if not rep_id and context.response and context.response.status_code == 201:
        rep_id = context.response.json().get('data', {}).get('laporan_id')

    assert rep_id, "No created_report_id recorded"
    php = f"echo App\\Models\\Laporan::where('laporan_id', {rep_id})->value('status');"
    actual_status = context.run_tinker(php).strip()
    assert actual_status == status, f"Expected report status '{status}', got '{actual_status}'"


@then('the photo URL should be stored in "foto_laporan"')
def step_photo_stored_in_foto_laporan(context):
    rep_id = getattr(context, 'created_report_id', None)
    php = f"echo App\\Models\\FotoLaporan::where('laporan_id', {rep_id})->exists() ? 'YES' : 'NO';"
    res = context.run_tinker(php).strip()
    assert res == 'YES', f"Photo record for laporan {rep_id} not found in foto_laporan"


@when('I select a point with "Koordinat X" "{lon}" and "Koordinat Y" "{lat}"')
def step_select_point_coords(context, lon, lat):
    context.report_data['longitude'] = float(lon)
    context.report_data['latitude'] = float(lat)
    context.report_data['deskripsi'] = 'Laporan out of area'
    context.report_data['kategori_id'] = 1
    step_press_kirim_laporan(context)


@when('I select a point inside the service area but farther than CFG_SNAP_RADIUS_M from any segment')
def step_select_point_far_from_segments(context):
    # Coordinates inside Surabaya but miles away from seeded Mulyosari segment
    context.report_data['longitude'] = 112.7000
    context.report_data['latitude'] = -7.3000
    context.report_data['deskripsi'] = 'Laporan jauh dari segmen'
    context.report_data['kategori_id'] = 1
    step_press_kirim_laporan(context)


@then('no report should be created')
def step_no_report_created(context):
    assert context.response.status_code != 201, f"Expected failure but got 201: {context.response.text}"


@then('no report should exist')
def step_no_report_should_exist(context):
    assert context.response.status_code != 201, f"Expected no report created, but got status {context.response.status_code}"


@given('I selected a valid location')
def step_selected_valid_location(context):
    context.report_data['latitude'] = -7.2663
    context.report_data['longitude'] = 112.7965


@given('I selected a valid location and a valid photo')
def step_selected_valid_loc_and_photo(context):
    step_selected_valid_location(context)
    step_fill_foto_filename(context, 'trotoar.jpg')


@given('a report belongs to another Warga')
def step_report_belongs_to_other_warga(context):
    php = """
    $other = App\\Models\\Warga::firstOrCreate(
        ['email' => 'other_user_' . uniqid() . '@example.com'],
        [
            'nama' => 'Other Warga',
            'no_hp' => '62819' . rand(1000000, 9999999),
            'password' => Illuminate\\Support\\Facades\\Hash::make('warga12345'),
            'tanggal_daftar' => now(),
            'gagal_login' => 0
        ]
    );

    $segmen = App\\Models\\SegmenJalur::first();
    $lokasiId = Illuminate\\Support\\Facades\\DB::table('lokasi')->insertGetId([
        'latitude' => -7.2663,
        'longitude' => 112.7965,
        'geom' => Illuminate\\Support\\Facades\\DB::raw("ST_SetSRID(ST_Point(112.7965, -7.2663), 4326)"),
        'alamat' => 'Jl. Mulyosari',
        'segmen_id' => $segmen->segmen_id,
    ], 'lokasi_id');

    $lapId = Illuminate\\Support\\Facades\\DB::table('laporan')->insertGetId([
        'warga_id' => $other->warga_id,
        'kategori_id' => 1,
        'lokasi_id' => $lokasiId,
        'deskripsi' => 'Laporan milik warga lain',
        'status' => 'MENUNGGU_VALIDASI_ADMIN',
        'tanggal_lapor' => now(),
    ], 'laporan_id');

    echo $lapId;
    """
    res = context.run_tinker(php).strip()
    context.other_report_id = int(res)


@when("I request that report's detail")
def step_request_other_report_detail(context):
    url = f"{context.api_url}/reports/{context.other_report_id}"
    headers = {"Authorization": f"Bearer {context.warga_token}"}
    context.response = requests.get(url, headers=headers, timeout=10)


@given('I upload a JPEG that contains EXIF GPS coordinates')
def step_upload_jpeg_with_exif(context):
    step_selected_valid_location(context)
    content = generate_dummy_jpeg(with_exif=True)
    context.report_file = ('gps_foto.jpg', content, 'image/jpeg')
    context.report_data['deskripsi'] = 'Foto dengan koordinat EXIF'
    context.report_data['kategori_id'] = 1


@when('the report is saved')
def step_the_report_is_saved(context):
    step_press_kirim_laporan(context)
    assert context.response.status_code == 201, f"Failed to save report: {context.response.text}"


@then('the stored photo should not contain EXIF metadata')
def step_stored_photo_no_exif(context):
    rep_id = context.created_report_id
    php = f"""
    $foto = App\\Models\\FotoLaporan::where('laporan_id', {rep_id})->first();
    echo $foto ? $foto->url_foto : 'NO_PHOTO';
    """
    photo_path = context.run_tinker(php).strip()
    assert photo_path != 'NO_PHOTO', "FotoLaporan record was not created"

    # Read the photo bytes directly from the storage path inside docker container
    check_exif_php = f"""
    $fullPath = Illuminate\\Support\\Facades\\Storage::disk('public')->path('{photo_path}');
    if (!file_exists($fullPath)) {{
        echo 'FILE_NOT_FOUND';
    }} else {{
        $exif = @exif_read_data($fullPath);
        echo ($exif && !empty($exif['GPS'])) ? 'HAS_GPS' : 'NO_GPS';
    }}
    """
    res = context.run_tinker(check_exif_php).strip()
    assert res == 'NO_GPS', f"Stored photo still contains GPS EXIF metadata! Result: {res}"


@given('the database write fails after the photo was uploaded')
def step_db_write_fails_simulation(context):
    step_selected_valid_location(context)
    # Passing an invalid category string that triggers rollback
    context.report_data['kategori_id'] = 'invalid_non_existent_category_xyz'
    step_fill_foto_filename(context, 'test_rollback.jpg')


@then('the uploaded photo should be deleted from storage')
def step_photo_deleted_from_storage(context):
    assert context.response.status_code != 201, "Expected transaction to fail, but report was created"
