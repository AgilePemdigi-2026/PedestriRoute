import requests
from behave import given, when, then


@given('a Warga account exists with email "{email}" and password "{password}"')
def step_warga_account_exists(context, email, password):
    context.user_email = email
    context.user_password = password
    php = f"""
    $user = App\\Models\\Warga::where('email', '{email}')->first();
    if (!$user) {{
        $user = new App\\Models\\Warga();
        $user->nama = 'Ahmad Fauzi';
        $user->email = '{email}';
        $user->no_hp = '6281234567890';
        $user->tanggal_daftar = now();
    }}
    $user->password = Illuminate\\Support\\Facades\\Hash::make('{password}');
    $user->gagal_login = 0;
    $user->terkunci_hingga = null;
    $user->save();
    echo $user->warga_id;
    """
    res = context.run_tinker(php)
    context.warga_id = res


@given('a Warga account exists with email "{email}"')
def step_warga_account_with_email_only(context, email):
    step_warga_account_exists(context, email, 'warga12345')



@given('a Warga account exists with phone "{phone}"')
def step_warga_account_with_phone_exists(context, phone):
    context.user_phone = phone
    php = f"""
    $norm = App\\Support\\PhoneNormalizer::normalize('{phone}');
    $user = App\\Models\\Warga::where('no_hp', $norm)->first();
    if (!$user) {{
        $user = new App\\Models\\Warga();
        $user->nama = 'Warga Phone Test';
        $user->email = 'phone_test_' . uniqid() . '@gmail.com';
        $user->tanggal_daftar = now();
    }}
    $user->no_hp = $norm;
    $user->password = Illuminate\\Support\\Facades\\Hash::make('warga12345');
    $user->gagal_login = 0;
    $user->terkunci_hingga = null;
    $user->save();
    echo $user->warga_id;
    """
    context.run_tinker(php)


@given('an Admin account exists with email "{email}"')
def step_admin_account_exists(context, email):
    context.admin_email = email
    php = f"""
    $admin = App\\Models\\Admin::where('email', '{email}')->first();
    if (!$admin) {{
        $admin = new App\\Models\\Admin();
        $admin->nama = 'Administrator PedestriRoute';
        $admin->email = '{email}';
        $admin->role = 'admin';
        $admin->tanggal_dibuat = now();
    }}
    $admin->password = Illuminate\\Support\\Facades\\Hash::make('adminpassword123');
    $admin->gagal_login = 0;
    $admin->terkunci_hingga = null;
    $admin->save();
    echo $admin->admin_id;
    """
    context.run_tinker(php)


@when('I fill in "Email/Nomor Telepon" with "{value}"')
def step_fill_email_or_phone(context, value):
    context.login_data['identifier'] = value


@when('I fill in "Kata Sandi" with "{value}"')
def step_fill_password(context, value):
    context.login_data['password'] = value


@when('I leave "Kata Sandi" empty and press "Masuk"')
def step_leave_password_empty_and_press_masuk(context):
    context.login_data['password'] = ""
    step_press_masuk(context)


@when('I press "Masuk"')
def step_press_masuk(context):
    url = f"{context.api_url}/auth/login"
    payload = {
        "identifier": context.login_data.get("identifier", ""),
        "password": context.login_data.get("password", ""),
    }
    resp = requests.post(url, json=payload, timeout=10)
    context.response = resp

    try:
        data = resp.json()
        if resp.status_code == 200:
            context.redirect_path = data.get("data", {}).get("redirect")
            context.token = data.get("data", {}).get("token")
            context.last_message = data.get("message", "Login Berhasil")
        else:
            context.last_message = data.get("error", {}).get("message", "")
    except Exception:
        context.last_message = resp.text


@when('I log in with that account')
def step_login_with_that_account(context):
    url = f"{context.api_url}/auth/login"
    payload = {
        "identifier": context.admin_email,
        "password": "adminpassword123",
    }
    resp = requests.post(url, json=payload, timeout=10)
    context.response = resp
    if resp.status_code == 200:
        context.admin_token = resp.json().get("data", {}).get("token")
        context.redirect_path = resp.json().get("data", {}).get("redirect")


@when('I log in with "{identifier}" and the correct password')
def step_login_with_identifier_and_password(context, identifier):
    url = f"{context.api_url}/auth/login"
    payload = {
        "identifier": identifier,
        "password": "warga12345",
    }
    resp = requests.post(url, json=payload, timeout=10)
    context.response = resp
    if resp.status_code == 200:
        context.warga_token = resp.json().get("data", {}).get("token")
        context.redirect_path = resp.json().get("data", {}).get("redirect")


@when('I log in with a wrong password')
def step_login_with_wrong_password(context):
    # Record current gagal_login
    email = getattr(context, 'user_email', 'ahmad.fauzi@gmail.com')
    php_before = f"echo App\\Models\\Warga::where('email', '{email}')->value('gagal_login');"
    context.initial_gagal_login = int(context.run_tinker(php_before) or 0)

    url = f"{context.api_url}/auth/login"
    payload = {
        "identifier": email,
        "password": "passwordSalah123",
    }
    resp = requests.post(url, json=payload, timeout=10)
    context.response = resp
    try:
        context.last_message = resp.json().get("error", {}).get("message", "")
    except Exception:
        context.last_message = resp.text


@given('one IP address has sent 10 login requests within a minute')
def step_ip_sent_10_requests(context):
    url = f"{context.api_url}/auth/login"
    payload = {"identifier": "rate_limit_test@example.com", "password": "wrong"}
    # Hit the rate limiter to reach 10 attempts
    for _ in range(10):
        requests.post(url, json=payload, timeout=5)


@when('it sends another login request')
def step_send_another_login_request(context):
    url = f"{context.api_url}/auth/login"
    payload = {"identifier": "rate_limit_test@example.com", "password": "wrong"}
    context.response = requests.post(url, json=payload, timeout=5)
    try:
        context.last_message = context.response.json().get("error", {}).get("message", "")
    except Exception:
        context.last_message = context.response.text


@then('"gagal_login" for that account should increase by 1')
def step_gagal_login_should_increase(context):
    email = getattr(context, 'user_email', 'ahmad.fauzi@gmail.com')
    php_after = f"echo App\\Models\\Warga::where('email', '{email}')->value('gagal_login');"
    current_gagal = int(context.run_tinker(php_after) or 0)
    expected = context.initial_gagal_login + 1
    assert current_gagal == expected, f"Expected gagal_login={expected}, got {current_gagal}"


@given('a Warga account has failed to log in CFG_MAX_LOGIN_ATTEMPTS times')
def step_account_failed_max_attempts(context):
    email = getattr(context, 'user_email', 'ahmad.fauzi@gmail.com')
    php = f"""
    $user = App\\Models\\Warga::where('email', '{email}')->first();
    if ($user) {{
        $user->gagal_login = 5;
        $user->terkunci_hingga = now()->addMinutes(15);
        $user->save();
    }}
    echo $user ? 'locked' : 'not_found';
    """
    context.run_tinker(php)


@when('I log in with the correct password')
def step_login_with_correct_password(context):
    email = getattr(context, 'user_email', 'ahmad.fauzi@gmail.com')
    url = f"{context.api_url}/auth/login"
    payload = {
        "identifier": email,
        "password": "warga12345",
    }
    context.response = requests.post(url, json=payload, timeout=10)
    try:
        context.last_message = context.response.json().get("error", {}).get("message", "")
    except Exception:
        context.last_message = context.response.text


@then('I should not receive a token')
def step_should_not_receive_token(context):
    data = {}
    if context.response is not None:
        try:
            data = context.response.json().get('data', {})
        except Exception:
            pass
    assert 'token' not in data or data.get('token') is None, f"Expected no token, got: {data.get('token')}"


# OTP Steps for Sprint 1 Week 1 & 2
@given('a valid login challenge exists for user "{email}"')
def step_valid_login_challenge_exists(context, email):
    context.otp_user_email = email
    context.test_otp = "123456"
    php = f"""
    App\\Models\\Warga::where('email', '{email}')->delete();
    $challengeId = (string) Illuminate\\Support\\Str::uuid();
    $payload = [
        'nama' => 'Warga OTP Test',
        'email' => '{email}',
        'no_hp' => '628129999' . rand(1000, 9999),
        'password_hash' => Illuminate\\Support\\Facades\\Hash::make('warga12345'),
        'otp_hash' => Illuminate\\Support\\Facades\\Hash::make('123456'),
        'attempts' => 0,
        'expires_at' => now()->addMinutes(5)->timestamp,
        'resend_at' => now()->addSeconds(60)->timestamp,
    ];
    Illuminate\\Support\\Facades\\Cache::put('register_otp:' . $challengeId, $payload, now()->addMinutes(5));
    echo $challengeId;
    """
    context.challenge_id = context.run_tinker(php)




@when('I submit the correct OTP code')
def step_submit_correct_otp(context):
    url = f"{context.api_url}/auth/otp/verify"
    payload = {
        "challenge_id": context.challenge_id,
        "otp": context.test_otp,
    }
    context.response = requests.post(url, json=payload, timeout=10)


@when('I submit an invalid OTP code "{otp}"')
def step_submit_invalid_otp(context, otp):
    url = f"{context.api_url}/auth/otp/verify"
    payload = {
        "challenge_id": context.challenge_id,
        "otp": otp,
    }
    context.response = requests.post(url, json=payload, timeout=10)


@then('I should receive HTTP {status:d} with an auth token and role "{role}"')
def step_receive_token_and_role(context, status, role):
    assert context.response.status_code == status, f"Expected {status}, got {context.response.status_code}: {context.response.text}"
    body = context.response.json()
    data = body.get('data', {})
    assert data.get('token') is not None, "Auth token was not returned"
    assert data.get('role') == role, f"Expected role '{role}', got '{data.get('role')}'"
