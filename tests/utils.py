import json
import re

def expect_success(resp):
    """
    resp is requests.Response
    raise AssertionError with helpful message if not OK.
    """
    try:
        body = resp.text.strip()
        code = resp.status_code
    except Exception as e:
        raise AssertionError(f"HTTP request failed: {e}")

    if code != 200:
        raise AssertionError(f"API returned HTTP {code}. Body: {body}")

    if not body:
        raise AssertionError("API returned empty body (check server logs).")

    try:
        j = resp.json()
    except Exception as e:
        raise AssertionError(f"Response is not valid JSON: {e}. Body: {body}")

    if not isinstance(j, dict):
        raise AssertionError(f"Response JSON is not an object: {j}")

    if not j.get("success", False):
        raise AssertionError(f"API returned success=false: {j}")

    return j

def sum_cash_db(cursor, broker_id):
    cursor.execute("SELECT COALESCE(SUM(amount),0) AS s FROM cash_transaction WHERE broker_account_id = %s", (broker_id,))
    return float(cursor.fetchone()["s"] or 0.0)

def cash_account_balance(cursor, broker_id):
    cursor.execute("SELECT COALESCE(current_balance,0) AS b FROM cash_account WHERE broker_id = %s LIMIT 1", (broker_id,))
    row = cursor.fetchone()
    return float(row["b"] or 0.0) if row else 0.0

def extract_id(j, key="order_id"):
    if key in j:
        return int(j[key])
    # try common keys
    for k in ("order_id","dividend_id","id"):
        if k in j:
            return int(j[k])
    raise AssertionError(f"No id found in response JSON: {j}")
