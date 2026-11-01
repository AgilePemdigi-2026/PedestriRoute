import requests
from behave import given, when, then, step


@given('the email "{email}" is already registered')
def step_email_already_registered(context, email):
    context.registered_email = email
    context.expect_email_exists = True
    php = f"""
    $user = App\\Models\\Warga::where('email', '{email}')->first();
    if (!$user) {{
        $user = new App\\Models\\Warga();
        $user->nama = 'Existing User';
        $user->email = '{email}';
        $user->no_hp = '62812' . rand(1000000, 9999999);
        $user->password = Illuminate\\Support\\Facades\\Hash::make('password123');
        $user->tanggal_daftar = now();
        $user->save();
    }}
    echo $user->warga_id;
    """
    context.run_tinker(php)


@given('the phone "{phone}" is already registered')
def step_phone_already_registered(context, phone):
    context.registered_phone = phone
    php = f"""
    $norm = App\\Support\\PhoneNormalizer::normalize('{phone}');
    $user = App\\Models\\Warga::where('no_hp', $norm)->first();
    if (!$user) {{
        $user = new App\\Models\\Warga();
        $user->nama = 'Existing Phone User';
        $user->email = 'existing_phone_' . uniqid() . '@example.com';
        $user->no_hp = $norm;
        $user->password = Illuminate\\Support\\Facades\\Hash::make('password123');
        $user->tanggal_daftar = now();
        $user->save();
    }}
    echo $user->warga_id;
    """
    context.run_tinker(php)


@step('I fill in "Nama" with "{value}"')
def step_fill_nama(context, value):
    context.register_data['nama'] = value


@step('I fill in "Email" with "{value}"')
def step_fill_email(context, value):
    context.register_data['email'] = value


@step('I fill in "Nomor Telepon" with "{value}"')
def step_fill_no_hp(context, value):
    context.register_data['no_hp'] = value


@step('I fill in "Kata Sandi" with "{value}"')
def step_fill_kata_sandi(context, value):
    context.register_data['password'] = value


@step('I fill in "Konfirmasi Kata Sandi" with "{value}"')
def step_fill_konfirmasi_kata_sandi(context, value):
    context.register_data['password_confirmation'] = value


@step('I leave "Nomor Telepon" empty')
def step_leave_no_hp_empty(context):
    context.register_data['no_hp'] = ""


@step('I press "Daftar"')
def step_press_daftar(context):
    email = context.register_data.get('email', '')

    # Free up 081234567890 and clean up johnmaps ONLY when we are doing a fresh registration
    if email == 'johnmaps@gmail.com' and not getattr(context, 'expect_email_exists', False):
        php = """
        $conflicting = App\\Models\\Warga::where('no_hp', '6281234567890')->where('email', '!=', 'johnmaps@gmail.com')->first();
        if ($conflicting) {
            $conflicting->no_hp = '62812' . rand(1000000, 9999999);
            $conflicting->save();
        }
        App\\Models\\Warga::where('email', 'johnmaps@gmail.com')->delete();
        """
        context.run_tinker(php)

    payload = {
        'nama': context.register_data['nama'] if 'nama' in context.register_data else 'Budi Santoso',
        'email': context.register_data['email'] if 'email' in context.register_data else f"budi_{abs(hash(str(context.register_data))) % 100000}@example.com",
        'no_hp': context.register_data['no_hp'] if 'no_hp' in context.register_data else '0812' + str(10000000 + abs(hash(str(context.register_data))) % 80000000),
        'password': context.register_data.get('password', ''),
        'password_confirmation': context.register_data.get('password_confirmation', ''),
    }

    url = f"{context.api_url}/auth/register"
    resp = requests.post(url, json=payload, timeout=10)
    context.response = resp

    if resp.status_code == 200:
        # Registration initiated, OTP sent (Sprint 1 Two-Step flow)
        data = resp.json().get('data', {})
        challenge_id = data.get('challenge_id')
        context.challenge_id = challenge_id

        # Set known OTP in cache so we can verify it deterministically
        otp = "123456"
        context.run_tinker(f"""
        $cached = Illuminate\\Support\\Facades\\Cache::get('register_otp:{challenge_id}');
        if ($cached) {{
            $cached['otp_hash'] = Illuminate\\Support\\Facades\\Hash::make('{otp}');
            Illuminate\\Support\\Facades\\Cache::put('register_otp:{challenge_id}', $cached, now()->addMinutes(5));
        }}
        """)

        # Complete OTP verification to fulfill the SRS expectation
        verify_url = f"{context.api_url}/auth/otp/verify"
        verify_resp = requests.post(verify_url, json={"challenge_id": challenge_id, "otp": otp}, timeout=10)
        context.response = verify_resp
        if verify_resp.status_code == 201:
            context.last_message = verify_resp.json().get('message', 'Registrasi Berhasil')
            context.redirect_path = "/login"
        else:
            try:
                context.last_message = verify_resp.json().get('error', {}).get('message', '')
            except Exception:
                context.last_message = verify_resp.text
    elif resp.status_code == 422:
        try:
            context.last_message = resp.json().get('error', {}).get('message', '')
        except Exception:
            context.last_message = resp.text
        context.current_path = "/register"
    else:
        try:
            context.last_message = resp.json().get('error', {}).get('message', '')
        except Exception:
            context.last_message = resp.text


@then('the stored password should not equal "{password}"')
def step_stored_password_not_equal(context, password):
    email = context.register_data.get('email', 'johnmaps@gmail.com')
    php = f"""
    $user = App\\Models\\Warga::where('email', '{email}')->first();
    if (!$user) {{
        echo 'NOT_FOUND';
    }} else {{
        $hashMatch = Illuminate\\Support\\Facades\\Hash::check('{password}', $user->password) ? 'MATCH' : 'NO_MATCH';
        echo $user->password . '||' . $hashMatch;
    }}
    """
    res = context.run_tinker(php)
    assert 'NOT_FOUND' not in res, f"User with email '{email}' not found in database"
    parts = res.split('||')
    stored_hash = parts[0]
    hash_match = parts[1] if len(parts) > 1 else 'NO_MATCH'

    assert stored_hash != password, f"Password stored in plain text: {stored_hash}"
    assert hash_match == 'MATCH', "Stored password hash does not verify against original password"


@when('I register with the email "{email}"')
def step_register_with_email(context, email):
    context.register_data = {
        'nama': 'Test User',
        'email': email,
        'no_hp': '0812' + str(10000000 + abs(hash(email)) % 80000000),
        'password': 'warga12345',
        'password_confirmation': 'warga12345',
    }
    step_press_daftar(context)


@when('I register with the phone "{phone}"')
def step_register_with_phone(context, phone):
    context.register_data = {
        'nama': 'Test User Phone',
        'email': f"unique_{abs(hash(phone)) % 100000}@example.com",
        'no_hp': phone,
        'password': 'warga12345',
        'password_confirmation': 'warga12345',
    }
    step_press_daftar(context)


# Sprint 1 Two-Step Extension for Register
@given('I initiate registration for new user "{email}" with phone "{phone}"')
def step_initiate_registration(context, email, phone):
    context.reg_email = email
    context.reg_phone = phone
    context.run_tinker(f"App\\Models\\Warga::where('email', '{email}')->orWhere('no_hp', App\\Support\\PhoneNormalizer::normalize('{phone}'))->delete();")

    url = f"{context.api_url}/auth/register"
    payload = {
        'nama': 'Andi Wijaya',
        'email': email,
        'no_hp': phone,
        'password': 'warga12345',
        'password_confirmation': 'warga12345',
    }
    resp = requests.post(url, json=payload, timeout=10)
    assert resp.status_code == 200, f"Expected 200, got {resp.status_code}: {resp.text}"
    context.reg_challenge_id = resp.json()['data']['challenge_id']


@when('I verify the registration using the sent OTP code')
def step_verify_reg_otp(context):
    challenge_id = context.reg_challenge_id
    # Set known OTP in cache
    otp = "654321"
    context.run_tinker(f"""
    $cached = Illuminate\\Support\\Facades\\Cache::get('register_otp:{challenge_id}');
    if ($cached) {{
        $cached['otp_hash'] = Illuminate\\Support\\Facades\\Hash::make('{otp}');
        Illuminate\\Support\\Facades\\Cache::put('register_otp:{challenge_id}', $cached, now()->addMinutes(5));
    }}
    """)

    url = f"{context.api_url}/auth/otp/verify"
    context.response = requests.post(url, json={"challenge_id": challenge_id, "otp": otp}, timeout=10)


@then('the user "{email}" should exist in database')
def step_user_should_exist_in_db(context, email):
    res = context.run_tinker(f"echo App\\Models\\Warga::where('email', '{email}')->exists() ? 'YES' : 'NO';")
    assert res.strip() == 'YES', f"User with email '{email}' does not exist in database"
