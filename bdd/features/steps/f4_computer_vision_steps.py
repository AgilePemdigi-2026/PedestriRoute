"""
Step Definitions untuk Feature 4: Memvalidasi Laporan dengan Computer Vision
Issue #41 — Sprint 2 — PedestriRoute

Strategi mock (disesuaikan dengan arsitektur Docker):
  Library `responses` tidak bisa meng-intercept HTTP call yang dilakukan OLEH
  Laravel (di dalam container) ke CV service. Oleh karena itu, hasil inferensi
  CV disimulasikan dengan menyuntikkan implementasi `CvService` palsu ke
  container Laravel melalui tinker, lalu menjalankan job pipeline yang sama
  dengan yang dipakai aplikasi (`ProcessReportCv::dispatchSync`).

  Alur setiap skenario:
  1. Seed warga, segmen uji terisolasi, laporan berstatus 'DIPROSES', dan foto.
  2. When: mock CvService + dispatch ProcessReportCv, lalu ambil snapshot state
     (status, label, confidence, skor segmen, notifikasi, integritas data)
     dalam satu panggilan tinker.
  3. Then: seluruh asersi membaca snapshot tersebut — tidak ada query tambahan
     sehingga suite tetap cepat meski dijalankan lewat Docker/WSL.

  Skenario timeout disimulasikan dengan `CvService::analyze()` mengembalikan
  null, persis seperti yang dilakukan CvService asli ketika koneksi ke layanan
  CV gagal atau melewati batas 10 detik.
"""

import json

import requests
from behave import given, then, when


# ---------------------------------------------------------------------------
# Konstanta
# ---------------------------------------------------------------------------
CV_CONFIDENCE_THRESHOLD = 0.80  # sesuai SRS F4


# ---------------------------------------------------------------------------
# Helper
# ---------------------------------------------------------------------------
def _php_string(value: str) -> str:
    """Ubah string Python menjadi literal string PHP yang aman."""
    escaped = str(value).replace("\\", "\\\\").replace("'", "\\'")
    return f"'{escaped}'"


def _snapshot(context):
    snapshot = getattr(context, "analysis_snapshot", None)
    assert snapshot, (
        "[F4] Snapshot hasil analisis belum tersedia. "
        "Pastikan langkah 'sistem memanggil endpoint analisis laporan' sudah dijalankan."
    )
    return snapshot


def _create_pending_report(context):
    """
    Seed laporan berstatus 'DIPROSES' milik warga test.

    Segmen dibuat khusus untuk setiap skenario agar skor kelayakan tidak
    terkontaminasi laporan lain yang sudah ada di database demo.
    """
    php = r"""
    $warga = App\Models\Warga::where('email', 'ahmad.fauzi@gmail.com')->first();
    if (!$warga) {
        throw new RuntimeException('Warga test belum dibuat.');
    }

    $suffix = uniqid('', true);
    $segmenId = Illuminate\Support\Facades\DB::table('segmen_jalur')->insertGetId([
        'osm_way_id' => random_int(1000000000, 2000000000),
        'u_node'     => random_int(1000000000, 2000000000),
        'v_node'     => random_int(1000000000, 2000000000),
        'nama_jalan' => 'Jl. BDD F4 ' . $suffix,
        'geom'       => Illuminate\Support\Facades\DB::raw(
            "ST_GeogFromText('SRID=4326;LINESTRING(112.7965 -7.2663, 112.7966 -7.2664)')"
        ),
        'panjang_m'      => 50,
        'skor_kelayakan' => 100,
        'tanggal_update' => now(),
    ], 'segmen_id');

    $lokasiId = Illuminate\Support\Facades\DB::table('lokasi')->insertGetId([
        'latitude'  => -7.2663,
        'longitude' => 112.7965,
        'geom'      => Illuminate\Support\Facades\DB::raw("ST_SetSRID(ST_Point(112.7965, -7.2663), 4326)"),
        'alamat'    => 'Jl. Mulyosari (CV BDD Test)',
        'segmen_id' => $segmenId,
    ], 'lokasi_id');

    $laporanId = Illuminate\Support\Facades\DB::table('laporan')->insertGetId([
        'warga_id'      => $warga->warga_id,
        'kategori_id'   => 1,
        'lokasi_id'     => $lokasiId,
        'deskripsi'     => 'Trotoar rusak - CV BDD Test',
        'status'        => 'DIPROSES',
        'tanggal_lapor' => now(),
    ], 'laporan_id');

    // Foto nyata di storage agar skenario timeout dapat memverifikasi
    // bahwa berkas tidak ikut terhapus ketika pipeline gagal.
    $photoPath = 'laporan/bdd_f4_' . $suffix . '.jpg';
    Illuminate\Support\Facades\Storage::disk('public')->put($photoPath, 'fake-image-bytes');
    Illuminate\Support\Facades\DB::table('foto_laporan')->insert([
        'laporan_id'     => $laporanId,
        'url_foto'       => $photoPath,
        'tanggal_upload' => now(),
    ]);

    $initialScore = (float) Illuminate\Support\Facades\DB::table('segmen_jalur')
        ->where('segmen_id', $segmenId)
        ->value('skor_kelayakan');

    echo $laporanId . '||' . $warga->warga_id . '||' . $segmenId . '||' . $photoPath . '||' . $initialScore;
    """
    res = context.run_tinker(php).strip()
    parts = res.split("||")
    assert len(parts) == 5, f"[F4] Gagal menyiapkan laporan uji. Output tinker: {res!r}"

    context.current_report_id = int(parts[0])
    context.current_report_warga_id = int(parts[1])
    context.current_report_segmen_id = int(parts[2])
    context.current_photo_path = parts[3]
    context.initial_segment_score = float(parts[4])


# ---------------------------------------------------------------------------
# BACKGROUND STEPS
# ---------------------------------------------------------------------------
@given('saya masuk sebagai "Warga"')
def step_saya_masuk_sebagai_warga(context):
    """Login sebagai warga test standar dan simpan token."""
    php = r"""
    $user = App\Models\Warga::where('email', 'ahmad.fauzi@gmail.com')->first();
    if (!$user) {
        App\Models\Warga::where('no_hp', '6281234567890')->delete();
        $user = App\Models\Warga::create([
            'nama'           => 'Ahmad Fauzi',
            'email'          => 'ahmad.fauzi@gmail.com',
            'no_hp'          => '6281234567890',
            'password'       => Illuminate\Support\Facades\Hash::make('warga12345'),
            'tanggal_daftar' => now(),
            'gagal_login'    => 0,
        ]);
    }
    echo $user->createToken('bdd-cv-token', ['role:warga'])->plainTextToken . '||' . $user->warga_id;
    """
    res = context.run_tinker(php).strip()
    parts = res.split("||")
    context.warga_token = parts[0].strip()
    context.warga_id = int(parts[1].strip())
    context.active_token = context.warga_token


@given('sebuah laporan baru ada dengan status "DIPROSES"')
def step_laporan_baru_diproses(context):
    """Seed laporan berstatus DIPROSES ke DB via tinker."""
    _create_pending_report(context)


# ---------------------------------------------------------------------------
# GIVEN — konfigurasi mock hasil CV (disimpan ke context)
# ---------------------------------------------------------------------------
@given('layanan CV mengembalikan label "{label}" dengan confidence {confidence:f}')
def step_cv_returns_label_confidence(context, label, confidence):
    """
    Simpan konfigurasi hasil CV ke context. Nilai ini akan dipakai pada langkah
    When untuk membangun CvService palsu yang dijalankan di dalam Laravel.
    """
    context.cv_mock_label = label
    context.cv_mock_confidence = float(confidence)
    context.cv_mock_timeout = False


@given('layanan CV tidak merespons dalam 10 detik')
def step_cv_timeout(context):
    """Simulasi timeout: CvService::analyze() mengembalikan null."""
    context.cv_mock_label = None
    context.cv_mock_confidence = None
    context.cv_mock_timeout = True


@given('terdapat laporan milik warga lain')
def step_laporan_milik_warga_lain(context):
    """Buat warga kedua dan laporan miliknya untuk uji otorisasi (Skenario 6)."""
    php = r"""
    $other = App\Models\Warga::firstOrCreate(
        ['email' => 'other.cv.warga@pedestriroute.test'],
        [
            'nama'           => 'Warga Lain CV',
            'no_hp'          => '62819' . rand(1000000, 9999999),
            'password'       => Illuminate\Support\Facades\Hash::make('warga12345'),
            'tanggal_daftar' => now(),
            'gagal_login'    => 0,
        ]
    );

    $segmen = App\Models\SegmenJalur::first();
    $lokasiId = Illuminate\Support\Facades\DB::table('lokasi')->insertGetId([
        'latitude'  => -7.2663,
        'longitude' => 112.7965,
        'geom'      => Illuminate\Support\Facades\DB::raw("ST_SetSRID(ST_Point(112.7965, -7.2663), 4326)"),
        'alamat'    => 'Jl. Mulyosari (Akses Test)',
        'segmen_id' => $segmen->segmen_id,
    ], 'lokasi_id');

    $lapId = Illuminate\Support\Facades\DB::table('laporan')->insertGetId([
        'warga_id'      => $other->warga_id,
        'kategori_id'   => 1,
        'lokasi_id'     => $lokasiId,
        'deskripsi'     => 'Laporan warga lain untuk uji otorisasi CV',
        'status'        => 'MENUNGGU_VALIDASI_ADMIN',
        'tanggal_lapor' => now(),
    ], 'laporan_id');

    echo $lapId . '||' . $other->warga_id;
    """
    res = context.run_tinker(php).strip()
    parts = res.split("||")
    context.other_report_id = int(parts[0])
    context.other_warga_id = int(parts[1])


# ---------------------------------------------------------------------------
# WHEN — jalankan pipeline analisis CV (mock CvService + job Laravel)
# ---------------------------------------------------------------------------
@when('sistem memanggil endpoint analisis laporan')
def step_sistem_memanggil_endpoint_analisis(context):
    """
    Jalankan pipeline analisis yang sama dengan aplikasi:
      1. Ganti binding CvService di container dengan implementasi palsu yang
         mengembalikan label/confidence sesuai skenario.
      2. Dispatch ProcessReportCv secara sinkron.
      3. Ambil snapshot lengkap state pasca-analisis dalam satu query.

    Ini menyimulasikan respons endpoint/queue CV tanpa membutuhkan container
    cv-service tetap hidup selama pengujian BDD.
    """
    report_id = context.current_report_id
    is_timeout = getattr(context, "cv_mock_timeout", False)

    if is_timeout:
        # CvService asli mengembalikan null saat timeout / koneksi gagal.
        analyze_body = "return null;"
    else:
        label = _php_string(context.cv_mock_label)
        confidence = repr(context.cv_mock_confidence)
        analyze_body = (
            f'return ["label" => {label}, "confidence" => {confidence}, '
            f'"detected_category" => $category];'
        )

    php = f"""
    $reportId = {report_id};
    $mock = new class extends App\\Services\\CvService {{
        public function isCategorySupported(?string $categoryName): bool {{ return true; }}
        public function getThreshold(): float {{ return {CV_CONFIDENCE_THRESHOLD}; }}
        public function analyze(string $photoPath, ?string $category = null): ?array {{ {analyze_body} }}
    }};
    app()->instance(App\\Services\\CvService::class, $mock);
    App\\Jobs\\ProcessReportCv::dispatchSync($reportId);

    $lap = App\\Models\\Laporan::with('lokasi.segmen')->find($reportId);
    $segmen = ($lap && $lap->lokasi) ? App\\Models\\SegmenJalur::find($lap->lokasi->segmen_id) : null;
    $foto = App\\Models\\FotoLaporan::where('laporan_id', $reportId)->first();
    $wargaNotifs = App\\Models\\Notifikasi::where('laporan_id', $reportId)
        ->where('penerima_tipe', 'WARGA')->pluck('pesan')->all();
    $adminNotifs = App\\Models\\Notifikasi::where('laporan_id', $reportId)
        ->where('penerima_tipe', 'ADMIN')->pluck('pesan')->all();
    $photoPath = {_php_string(context.current_photo_path)};

    echo json_encode([
        'status'            => $lap ? $lap->status : null,
        'cv_label'          => $lap ? $lap->cv_label : null,
        'confidence_score'  => $lap ? $lap->confidence_score : null,
        'score'             => $segmen ? (float) $segmen->skor_kelayakan : null,
        'report_exists'     => (bool) $lap,
        'foto_exists'       => (bool) $foto,
        'photo_file_exists' => Illuminate\\Support\\Facades\\Storage::disk('public')->exists($photoPath),
        'warga_notifs'      => $wargaNotifs,
        'admin_notifs'      => $adminNotifs,
    ]);
    """
    raw = context.run_tinker(php).strip()
    try:
        context.analysis_snapshot = json.loads(raw)
    except json.JSONDecodeError as exc:
        raise AssertionError(f"[F4] Output tinker bukan JSON valid: {raw!r}") from exc


# ---------------------------------------------------------------------------
# WHEN — akses otorisasi (Skenario 6)
# ---------------------------------------------------------------------------
@when('warga yang bukan pemilik laporan mengakses "/laporan/{id}/analisis"')
def step_warga_lain_akses_analisis(context, id):
    """
    Warga yang sudah login mencoba mengakses data analisis laporan bukan miliknya.

    Halaman `/laporan/{id}/analisis` adalah shell Blade yang mengambil detail
    laporan lewat API. Otorisasi sesungguhnya ditegakkan oleh endpoint detail
    laporan, sehingga asersi HTTP dilakukan pada endpoint tersebut.
    """
    report_id = context.other_report_id
    headers = {"Authorization": f"Bearer {context.warga_token}"}
    url = f"{context.api_url}/reports/{report_id}"
    context.current_path = f"/laporan/{report_id}/analisis"
    context.response = requests.get(url, headers=headers, timeout=10)


@when('Admin mengakses "/laporan/{id}/analisis"')
def step_admin_akses_analisis(context, id):
    """Admin mengakses data analisis laporan milik warga lain."""
    if not getattr(context, "admin_token", None):
        php = r"""
        $admin = App\Models\Admin::firstOrCreate(
            ['email' => 'admin@pedestriroute.test'],
            [
                'nama'           => 'Administrator PedestriRoute',
                'role'           => 'admin',
                'password'       => Illuminate\Support\Facades\Hash::make('adminpassword123'),
                'tanggal_dibuat' => now(),
                'gagal_login'    => 0,
            ]
        );
        echo $admin->createToken('bdd-admin-cv-token', ['role:admin'])->plainTextToken . '||' . $admin->admin_id;
        """
        res = context.run_tinker(php).strip()
        parts = res.split("||")
        context.admin_token = parts[0].strip()
        context.admin_id = int(parts[1].strip())

    report_id = context.other_report_id
    headers = {"Authorization": f"Bearer {context.admin_token}"}
    url = f"{context.api_url}/reports/{report_id}"
    context.current_path = f"/laporan/{report_id}/analisis"
    context.response = requests.get(url, headers=headers, timeout=10)


# ---------------------------------------------------------------------------
# THEN — asersi status laporan
# ---------------------------------------------------------------------------
@then('status laporan harus berubah menjadi "{expected_status}"')
def step_status_laporan_harus_berubah(context, expected_status):
    """Periksa status laporan dari snapshot pasca-analisis."""
    snapshot = _snapshot(context)
    actual_status = snapshot.get("status")
    assert actual_status == expected_status, (
        f"[F4] Status laporan {context.current_report_id}: diharapkan '{expected_status}', "
        f"tapi mendapat '{actual_status}'."
    )


# ---------------------------------------------------------------------------
# THEN — asersi notifikasi warga
# ---------------------------------------------------------------------------
@then('warga harus menerima notifikasi dengan kata kunci "{key}"')
def step_warga_menerima_notifikasi(context, key):
    """Cek notifikasi warga pada laporan yang sedang diuji."""
    snapshot = _snapshot(context)
    messages = snapshot.get("warga_notifs") or []
    expected_snippet = context.message_catalog.get(key, key)
    assert messages, (
        f"[F4] Tidak ada notifikasi untuk warga_id={context.current_report_warga_id} "
        f"pada laporan {context.current_report_id}. Diharapkan berisi '{expected_snippet}'."
    )
    assert any(expected_snippet in msg for msg in messages), (
        f"[F4] Notifikasi warga tidak mengandung '{expected_snippet}'. "
        f"Pesan aktual: {messages!r}"
    )


# ---------------------------------------------------------------------------
# THEN — asersi notifikasi admin
# ---------------------------------------------------------------------------
@then('Admin harus menerima notifikasi dengan kata kunci "{key}"')
def step_admin_menerima_notifikasi(context, key):
    """Cek notifikasi admin pada laporan yang sedang diuji."""
    snapshot = _snapshot(context)
    messages = snapshot.get("admin_notifs") or []
    expected_snippet = context.message_catalog.get(key, key)
    assert messages, (
        f"[F4] Tidak ada notifikasi admin pada laporan {context.current_report_id}. "
        f"Diharapkan berisi '{expected_snippet}'."
    )
    assert any(expected_snippet in msg for msg in messages), (
        f"[F4] Notifikasi admin tidak mengandung '{expected_snippet}'. "
        f"Pesan aktual: {messages!r}"
    )


# ---------------------------------------------------------------------------
# THEN — asersi skor segmen
# ---------------------------------------------------------------------------
@then('skor kelayakan segmen pada lokasi laporan harus dihitung ulang')
def step_skor_segmen_dihitung_ulang(context):
    """Setelah TERVALIDASI, skor segmen harus turun dari kondisi awal."""
    snapshot = _snapshot(context)
    initial = context.initial_segment_score
    actual = snapshot.get("score")
    assert actual is not None, "[F4] Skor segmen tidak ditemukan pada snapshot."
    assert actual < initial, (
        f"[F4] Skor kelayakan segmen {context.current_report_segmen_id} seharusnya "
        f"dihitung ulang (< {initial}), tapi mendapat {actual}"
    )


@then('skor kelayakan segmen TIDAK boleh berubah')
def step_skor_segmen_tidak_berubah(context):
    """Untuk laporan BAIK/SPAM yang tidak tervalidasi, skor segmen harus tetap."""
    snapshot = _snapshot(context)
    initial = context.initial_segment_score
    actual = snapshot.get("score")
    assert actual is not None, "[F4] Skor segmen tidak ditemukan pada snapshot."
    assert abs(actual - initial) < 1e-6, (
        f"[F4] Skor kelayakan segmen {context.current_report_segmen_id} seharusnya tetap "
        f"{initial} untuk laporan false positive (BAIK/SPAM), tapi mendapat {actual}"
    )


# ---------------------------------------------------------------------------
# THEN — asersi fallback timeout: data integrity
# ---------------------------------------------------------------------------
@then('laporan harus tetap ada di basis data')
def step_laporan_tetap_ada(context):
    """Pastikan laporan tidak terhapus meskipun CV service timeout."""
    snapshot = _snapshot(context)
    assert snapshot.get("report_exists") is True, (
        f"[F4] Laporan {context.current_report_id} tidak ditemukan di DB setelah CV timeout — "
        f"data hilang! Periksa error handling di pipeline CV."
    )


@then('tidak ada foto yang dihapus dari penyimpanan')
def step_foto_tidak_terhapus(context):
    """Pastikan record FotoLaporan dan berkas fisiknya tetap ada."""
    snapshot = _snapshot(context)
    assert snapshot.get("foto_exists") is True, (
        f"[F4] Record FotoLaporan untuk laporan {context.current_report_id} terhapus saat "
        f"CV timeout — ini pelanggaran data integrity!"
    )
    assert snapshot.get("photo_file_exists") is True, (
        f"[F4] Berkas foto '{context.current_photo_path}' terhapus dari storage saat CV timeout — "
        f"ini pelanggaran data integrity!"
    )


# ---------------------------------------------------------------------------
# THEN — asersi HTTP status (otorisasi)
# ---------------------------------------------------------------------------
@then('sistem harus mengembalikan HTTP {status:d}')
def step_sistem_harus_mengembalikan_http(context, status):
    """Verifikasi HTTP status code dari respons terakhir."""
    assert context.response is not None, "[F4] Tidak ada HTTP response yang tercatat."
    assert context.response.status_code == status, (
        f"[F4] Diharapkan HTTP {status}, tapi mendapat "
        f"{context.response.status_code}: {context.response.text}"
    )
