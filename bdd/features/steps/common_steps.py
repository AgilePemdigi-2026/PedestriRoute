import requests
from behave import given, when, then


@given('I am on the "{path}" page')
def step_i_am_on_the_page(context, path):
    context.current_path = path
    # Verify page is accessible via GET
    url = f"{context.base_url}{path}"
    try:
        resp = requests.get(url, timeout=5)
        # Blade pages return 200 (or redirect for guarded pages)
        context.page_status = resp.status_code
    except Exception as e:
        context.page_status = 500


@then('I should be on the "{path}" page')
def step_should_be_on_the_page(context, path):
    expected_path = path.strip()
    actual_path = (context.redirect_path or context.current_path or "").strip()
    assert actual_path == expected_path, f"Expected to be on '{expected_path}', but was on '{actual_path}'"


@then('I should be on "{path}"')
def step_should_be_on_path(context, path):
    step_should_be_on_the_page(context, path)



@then('I should see the message "{message}"')
def step_should_see_message(context, message):
    expected_msg = message.strip()
    actual_msg = context.last_message or ""

    # Check in last_message, response text, or JSON fields
    found = expected_msg in actual_msg
    if not found and context.response is not None:
        try:
            body = context.response.json()
            if isinstance(body, dict):
                msg_in_body = body.get('message') or body.get('error', {}).get('message', '')
                if expected_msg in str(msg_in_body):
                    found = True
        except Exception:
            if expected_msg in context.response.text:
                found = True

    assert found, f"Expected message '{expected_msg}' not found in actual message '{actual_msg}' or response '{getattr(context.response, 'text', '')}'"


@then('I should see a message with key "{key}"')
def step_should_see_message_with_key(context, key):
    expected_text = context.message_catalog.get(key, key)
    actual_msg = context.last_message or ""
    actual_code = ""

    if context.response is not None:
        try:
            body = context.response.json()
            actual_code = body.get('error', {}).get('code', '')
            actual_msg = body.get('error', {}).get('message', actual_msg)
        except Exception:
            pass

    matched = (key == actual_code) or (expected_text in actual_msg)
    assert matched, f"Expected message with key '{key}' ({expected_text}), got code='{actual_code}', msg='{actual_msg}'"


@then('I should receive HTTP {status:d}')
def step_should_receive_http_status(context, status):
    assert context.response is not None, "No HTTP response recorded in context."
    assert context.response.status_code == status, f"Expected HTTP {status}, got {context.response.status_code}: {context.response.text}"


@then('I should receive HTTP {status:d} with code "{code}"')
def step_should_receive_http_status_with_code(context, status, code):
    assert context.response is not None, "No HTTP response recorded."
    assert context.response.status_code == status, f"Expected HTTP {status}, got {context.response.status_code}"
    try:
        body = context.response.json()
        actual_code = body.get('error', {}).get('code', '')
        assert actual_code == code, f"Expected error code '{code}', got '{actual_code}'"
    except Exception:
        assert False, f"Response is not valid JSON with error code: {context.response.text}"


@then('I should receive HTTP {status:d} with message "{message}"')
def step_should_receive_http_status_with_message(context, status, message):
    assert context.response is not None, "No HTTP response recorded."
    assert context.response.status_code == status, f"Expected HTTP {status}, got {context.response.status_code}"
    actual = context.response.text
    try:
        body = context.response.json()
        actual = body.get('message') or body.get('error', {}).get('message', actual)
    except Exception:
        pass
    assert message in actual, f"Expected message '{message}' in '{actual}'"
