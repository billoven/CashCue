from datetime import datetime
from decimal import Decimal
import pytest
import json
from tests.utils import expect_success, sum_cash_db, cash_account_balance, extract_id

API_BASE = None

@pytest.fixture(autouse=True)
def set_api_base():
    global API_BASE
    import os
    API_BASE = os.environ.get("CASHCUE_API_BASE_URL", "http://localhost/cashcue/api")

def post(client, path, payload):
    url = f"{API_BASE}/{path}"
    return client.post(url, data=json.dumps(payload))

def get(client, path, params=None):
    url = f"{API_BASE}/{path}"
    return client.get(url, params=params)

def test_add_update_delete_order_flow(http_client, db, test_broker):
    broker_id = test_broker["broker_id"]
    cur = db.cursor()

    # Generate a unique symbol to avoid duplicates
    ts = datetime.now().strftime("%Y%m%d%H%M%S")
    symbol = f"TEST{ts}"

    # 1) Create instrument
    cur.execute("""
        INSERT INTO instrument (label, symbol, type, currency)
        VALUES ('TEST_INSTRUMENT', %s, 'STOCK', 'EUR')
    """, (symbol,))
    instrument_id = cur.lastrowid
    db.commit()

    # 2) Add BUY order to create holdings (quantity >= 50 for later SELL)
    buy_qty = 100
    buy_price = 10.0
    buy_fees = 2.0
    resp = post(http_client, "addOrder.php", {
        "broker_id": broker_id,
        "instrument_id": instrument_id,
        "order_type": "BUY",
        "quantity": buy_qty,
        "price": buy_price,
        "fees": buy_fees,
        "trade_date": "2025-01-05 12:00:00"
    })
    j = expect_success(resp)
    order_buy_id = extract_id(j, "order_id")

    # Verify BUY order and cash transaction
    cur.execute("SELECT * FROM order_transaction WHERE id = %s", (order_buy_id,))
    assert cur.fetchone() is not None
    cur.execute("SELECT * FROM cash_transaction WHERE reference_id = %s AND type='BUY'", (order_buy_id,))
    ct_buy = cur.fetchone()
    assert ct_buy is not None
    assert round(float(Decimal(ct_buy["amount"])), 2) == round(-(buy_qty*buy_price + buy_fees), 2)

    # Check aggregated cash balance
    expected_balance = -(buy_qty*buy_price + buy_fees)
    sum_db = sum_cash_db(cur, broker_id)
    b = cash_account_balance(cur, broker_id)
    assert round(sum_db, 2) == round(expected_balance, 2)
    assert round(b, 2) == round(expected_balance, 2)

    # 3) Add SELL order (50 x 20, fees=2), can sell because holdings exist
    sell_qty = 50
    sell_price = 20.0
    sell_fees = 2.0
    resp = post(http_client, "addOrder.php", {
        "broker_id": broker_id,
        "instrument_id": instrument_id,  # same instrument as BUY
        "order_type": "SELL",
        "quantity": sell_qty,
        "price": sell_price,
        "fees": sell_fees,
        "trade_date": "2025-01-06 12:00:00"
    })
    j = expect_success(resp)
    order_sell_id = extract_id(j, "order_id")

    # Verify cash transaction for SELL
    cur.execute("SELECT * FROM cash_transaction WHERE reference_id = %s AND type='SELL'", (order_sell_id,))
    ct_sell = cur.fetchone()
    assert ct_sell is not None, f"SELL cash transaction not created. Response: {j}"
    assert round(float(Decimal(ct_sell["amount"])), 2) == round((sell_qty*sell_price - sell_fees), 2)

    # Aggregated balance after SELL
    expected_balance = -(buy_qty*buy_price + buy_fees) + (sell_qty*sell_price - sell_fees)
    sum_db = sum_cash_db(cur, broker_id)
    b = cash_account_balance(cur, broker_id)
    assert round(sum_db, 2) == round(expected_balance, 2)
    assert round(b, 2) == round(expected_balance, 2)

    # 4) Update SELL order price -> new amount
    new_sell_price = 30.0
    resp = post(http_client, "updateOrder.php", {
        "id": order_sell_id,
        "price": new_sell_price,
        "quantity": sell_qty
    })
    j = expect_success(resp)

    cur.execute("SELECT * FROM cash_transaction WHERE reference_id = %s AND type='SELL'", (order_sell_id,))
    rows = cur.fetchall()
    assert len(rows) == 1
    assert round(float(Decimal(rows[0]["amount"])), 2) == round((sell_qty*new_sell_price - sell_fees), 2)

    # Aggregated balance after price update
    expected_balance = -(buy_qty*buy_price + buy_fees) + (sell_qty*new_sell_price - sell_fees)
    sum_db = sum_cash_db(cur, broker_id)
    b = cash_account_balance(cur, broker_id)
    assert round(sum_db, 2) == round(expected_balance, 2)
    assert round(b, 2) == round(expected_balance, 2)

    # 5) Update order type SELL -> BUY (fees = 1)
    resp = post(http_client, "updateOrder.php", {
        "id": order_sell_id,
        "order_type": "BUY",
        "quantity": sell_qty,
        "price": new_sell_price,
        "fees": 1.0
    })
    j = expect_success(resp)

    cur.execute("SELECT * FROM cash_transaction WHERE reference_id = %s", (order_sell_id,))
    rows = cur.fetchall()
    assert len(rows) == 1
    assert round(float(Decimal(rows[0]["amount"])), 2) == round(-(sell_qty*new_sell_price + 1.0), 2)

    # Aggregated balance after type update
    expected_balance = -(buy_qty*buy_price + buy_fees) + (-(sell_qty*new_sell_price + 1.0))
    sum_db = sum_cash_db(cur, broker_id)
    b = cash_account_balance(cur, broker_id)
    assert round(sum_db, 2) == round(expected_balance, 2)
    assert round(b, 2) == round(expected_balance, 2)

    # 6) Delete BUY order
    resp = get(http_client, "deleteOrder.php", params={"id": order_buy_id})
    j = expect_success(resp)
    cur.execute("SELECT * FROM cash_transaction WHERE reference_id = %s", (order_buy_id,))
    assert cur.fetchone() is None

    # Final aggregated balance
    expected_balance = -(sell_qty*new_sell_price + 1.0)
    sum_db = sum_cash_db(cur, broker_id)
    b = cash_account_balance(cur, broker_id)
    assert round(sum_db, 2) == round(expected_balance, 2)
    assert round(b, 2) == round(expected_balance, 2)

    # Cleanup
    cur.execute("DELETE FROM instrument WHERE id = %s", (instrument_id,))
    db.commit()
    cur.close()

def test_dividend_flow(http_client, db, test_broker):
    broker_id = test_broker["broker_id"]
    cur = db.cursor()

    # add dividend with gross & taxes -> amount computed as gross - taxes
    resp = post(http_client, "addDividend.php", {
        "broker_id": broker_id,
        "instrument_id": 9999,
        "gross_amount": 100.00,
        "taxes_withheld": 20.00,
        "payment_date": "2025-01-10"
    })
    j = expect_success(resp)
    div_id = extract_id(j, "dividend_id")

    cur.execute("SELECT * FROM cash_transaction WHERE reference_id = %s AND type='DIVIDEND'", (div_id,))
    ct = cur.fetchone()
    assert ct is not None
    assert round(float(Decimal(ct["amount"])), 2) == round(100.00 - 20.00, 2)

    # update dividend amount explicit
    resp = post(http_client, "updateDividend.php", {"id": div_id, "amount": 60.00})
    j = expect_success(resp)

    cur.execute("SELECT * FROM cash_transaction WHERE reference_id = %s AND type='DIVIDEND'", (div_id,))
    ct2 = cur.fetchone()
    assert ct2 is not None
    assert round(float(ct2["amount"]), 2) == round(60.00, 2)

    # delete dividend
    resp = get(http_client, "deleteDividend.php", params={"id": div_id})
    j = expect_success(resp)
    cur.execute("SELECT * FROM cash_transaction WHERE reference_id = %s", (div_id,))
    assert cur.fetchone() is None

    cur.close()
