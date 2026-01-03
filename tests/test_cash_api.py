from datetime import datetime, timezone
from decimal import Decimal
import pytest
import json
from cashcue_core import db
from tests.utils import expect_success, expect_ok, sum_cash_db, cash_account_balance, extract_id

API_BASE = None

@pytest.fixture(autouse=True)
def set_api_base():
    global API_BASE
    import os
    API_BASE = os.environ.get("CASHCUE_API_BASE_URL", "http://localhost/cashcue/api")

#def post(client, path, payload):
#   url = f"{API_BASE}/{path}"
#
#    return client.post(
#        url,
#        data=json.dumps(payload),
#        headers={"Content-Type": "application/json"}
#    )

def post(client, path, payload):
    url = f"{API_BASE}/{path}"
    return client.post(url, json=payload)

    

def get(client, path, params=None):
    url = f"{API_BASE}/{path}"
    return client.get(url, params=params)

def test_add_update_delete_order_flow(http_client, db, test_broker):
    broker_id = test_broker["broker_id"]
    cur = db.cursor()

    # --------------------------------------------------
    # 1) Create instrument
    # --------------------------------------------------
    ts = datetime.now().strftime("%Y%m%d%H%M%S")
    symbol = f"TEST{ts}"

    cur.execute("""
        INSERT INTO instrument (label, symbol, type, currency)
        VALUES ('TEST_INSTRUMENT', %s, 'STOCK', 'EUR')
    """, (symbol,))
    instrument_id = cur.lastrowid
    db.commit()

    # --------------------------------------------------
    # 2) Add BUY order
    # --------------------------------------------------
    buy_qty = 100
    buy_price = 10.0
    buy_fees = 2.0
    buy_total = buy_qty * buy_price + buy_fees

    resp = post(http_client, "addOrder.php", {
        "broker_account_id": broker_id,
        "instrument_id": instrument_id,
        "order_type": "BUY",
        "quantity": buy_qty,
        "price": buy_price,
        "fees": buy_fees,
        "trade_date": "2025-01-05 12:00:00"
    })
    j = expect_success(resp)
    db.commit()
    order_buy_id = extract_id(j, "order_id")

    cur.execute("""
        SELECT order_type, quantity, price, fees, total_cost
        FROM order_transaction
        WHERE id = %s
    """, (order_buy_id,))
    ot = cur.fetchone()

    assert ot["order_type"] == "BUY"
    assert round(float(ot["total_cost"]), 2) == round(buy_total, 2)

    cur.execute("SELECT amount FROM cash_transaction WHERE reference_id = %s", (order_buy_id,))
    ct = cur.fetchone()
    assert round(float(ct["amount"]), 2) == round(-buy_total, 2)

    expected_balance = -buy_total
    assert round(sum_cash_db(cur, broker_id), 2) == round(expected_balance, 2)
    assert round(cash_account_balance(cur, broker_id), 2) == round(expected_balance, 2)

    # --------------------------------------------------
    # 3) Add SELL order
    # --------------------------------------------------
    sell_qty = 50
    sell_price = 20.0
    sell_fees = 2.0
    sell_total = sell_qty * sell_price + sell_fees
    sell_cash = sell_qty * sell_price - sell_fees

    resp = post(http_client, "addOrder.php", {
        "broker_account_id": broker_id,
        "instrument_id": instrument_id,
        "order_type": "SELL",
        "quantity": sell_qty,
        "price": sell_price,
        "fees": sell_fees,
        "trade_date": "2025-01-06 12:00:00"
    })
    j = expect_success(resp)
    db.commit()
    order_sell_id = extract_id(j, "order_id")

    cur.execute("""
        SELECT order_type, total_cost
        FROM order_transaction
        WHERE id = %s
    """, (order_sell_id,))
    ot = cur.fetchone()

    assert ot["order_type"] == "SELL"
    assert round(float(ot["total_cost"]), 2) == round(sell_total, 2)

    cur.execute("SELECT amount FROM cash_transaction WHERE reference_id = %s", (order_sell_id,))
    ct = cur.fetchone()
    assert round(float(ct["amount"]), 2) == round(sell_cash, 2)

    expected_balance += sell_cash
    assert round(sum_cash_db(cur, broker_id), 2) == round(expected_balance, 2)

    # --------------------------------------------------
    # 4) Update SELL price
    # --------------------------------------------------
    new_sell_price = 30.0
    new_sell_total = sell_qty * new_sell_price + sell_fees
    new_sell_cash = sell_qty * new_sell_price - sell_fees

    resp = post(http_client, "updateOrder.php", {
        "id": order_sell_id,
        "price": new_sell_price
    })
    expect_success(resp)
    db.commit()

    cur.execute("""
        SELECT price, total_cost
        FROM order_transaction
        WHERE id = %s
    """, (order_sell_id,))
    ot = cur.fetchone()

    assert round(float(ot["price"]), 2) == round(new_sell_price, 2)
    assert round(float(ot["total_cost"]), 2) == round(new_sell_total, 2)

    cur.execute("SELECT amount FROM cash_transaction WHERE reference_id = %s", (order_sell_id,))
    ct = cur.fetchone()
    assert round(float(ct["amount"]), 2) == round(new_sell_cash, 2)

    expected_balance = -buy_total + new_sell_cash
    assert round(sum_cash_db(cur, broker_id), 2) == round(expected_balance, 2)

    # --------------------------------------------------
    # 5) Change SELL -> BUY
    # --------------------------------------------------
    new_fees = 1.0
    buy2_total = sell_qty * new_sell_price + new_fees
    buy2_cash = -buy2_total

    resp = post(http_client, "updateOrder.php", {
        "id": order_sell_id,
        "order_type": "BUY",
        "fees": new_fees
    })
    expect_success(resp)
    db.commit()

    cur.execute("SELECT order_type, total_cost FROM order_transaction WHERE id = %s", (order_sell_id,))
    ot = cur.fetchone()
    assert ot["order_type"] == "BUY"
    assert round(float(ot["total_cost"]), 2) == round(buy2_total, 2)

    cur.execute("SELECT amount FROM cash_transaction WHERE reference_id = %s", (order_sell_id,))
    ct = cur.fetchone()
    assert round(float(ct["amount"]), 2) == round(buy2_cash, 2)

    expected_balance = -buy_total + buy2_cash
    assert round(sum_cash_db(cur, broker_id), 2) == round(expected_balance, 2)

    # --------------------------------------------------
    # 6) Delete initial BUY order
    # --------------------------------------------------
    resp = get(http_client, "deleteOrder.php", params={"id": order_buy_id})
    expect_success(resp)
    db.commit()

    cur.execute("SELECT 1 FROM cash_transaction WHERE reference_id = %s", (order_buy_id,))
    assert cur.fetchone() is None

    expected_balance = buy2_cash
    assert round(sum_cash_db(cur, broker_id), 2) == round(expected_balance, 2)

    # --------------------------------------------------
    # Cleanup
    # --------------------------------------------------
    cur.execute("DELETE FROM instrument WHERE id = %s", (instrument_id,))
    db.commit()
    cur.close()

def test_dividend_flow_full_coverage(http_client, db, test_broker):
    broker_id = test_broker["broker_id"]
    cur = db.cursor()

    # --------------------------------------------------
    # Ensure broker has a cash account
    # --------------------------------------------------
    cur.execute("UPDATE broker_account SET has_cash_account = 1 WHERE id = %s", (broker_id,))
    cur.execute("""
        INSERT IGNORE INTO cash_account (broker_id, current_balance)
        VALUES (%s, 0)
    """, (broker_id,))
    db.commit()

    # --------------------------------------------------
    # 0) Create instrument
    # --------------------------------------------------
    ts = datetime.now(timezone.utc).strftime("%Y%m%d%H%M%S")
    symbol = f"DIV{ts}"
    cur.execute("""
        INSERT INTO instrument (label, symbol, type, currency)
        VALUES ('TEST_DIV_INSTRUMENT', %s, 'STOCK', 'EUR')
    """, (symbol,))
    instrument_id = cur.lastrowid
    db.commit()

    # --------------------------------------------------
    # A) Create dividend (gross 100, taxes 20 → net 80)
    # --------------------------------------------------
    resp = post(http_client, "addDividend.php", {
        "broker_id": broker_id,
        "instrument_id": instrument_id,
        "gross_amount": 100.00,
        "taxes_withheld": 20.00,
        "payment_date": "2025-01-10"
    })
    j = expect_success(resp)
    div_id = extract_id(j, "dividend_id")

    db.commit()

    cur.execute("""
        SELECT amount
        FROM cash_transaction
        WHERE reference_id = %s AND type = 'DIVIDEND'
    """, (div_id,))
    assert round(float(cur.fetchone()["amount"]), 2) == 80.00

    # --------------------------------------------------
    # B) Update explicit net amount → cash = 60
    # --------------------------------------------------
    expect_success(post(http_client, "updateDividend.php", {
        "id": div_id,
        "amount": 60.00
    }))

    db.commit()

    cur.execute("""
        SELECT amount
        FROM cash_transaction
        WHERE reference_id = %s AND type='DIVIDEND'
    """, (div_id,))
    assert round(float(cur.fetchone()["amount"]), 2) == 60.00

    # --------------------------------------------------
    # C) Update gross only (gross 200, taxes unchanged → net 180)
    # --------------------------------------------------
    expect_success(post(http_client, "updateDividend.php", {
        "id": div_id,
        "gross_amount": 200.00
    }))

    db.commit()

    cur.execute("""
        SELECT
            d.gross_amount,
            d.taxes_withheld,
            ct.amount AS cash_amount
        FROM dividend d
        JOIN cash_transaction ct
          ON ct.reference_id = d.id
         AND ct.type = 'DIVIDEND'
        WHERE d.id = %s
    """, (div_id,))

    row = cur.fetchone()
    assert row is not None
    assert round(float(row["gross_amount"]), 2) == 200.00
    assert round(float(row["taxes_withheld"]), 2) == 20.00
    assert round(float(row["cash_amount"]), 2) == 180.00

    # --------------------------------------------------
    # D) Update taxes only (gross 200, taxes 50 → net 150)
    # --------------------------------------------------
    expect_success(post(http_client, "updateDividend.php", {
        "id": div_id,
        "taxes_withheld": 50.00
    }))

    db.commit()

    cur.execute("""
        SELECT amount
        FROM cash_transaction
        WHERE reference_id = %s AND type='DIVIDEND'
    """, (div_id,))
    assert round(float(cur.fetchone()["amount"]), 2) == 150.00

    # --------------------------------------------------
    # E) Update non-cash field only → cash recalculated identically
    # --------------------------------------------------
    expect_success(post(http_client, "updateDividend.php", {
        "id": div_id,
        "payment_date": "2025-02-01"
    }))

    db.commit()

    cur.execute("""
        SELECT amount
        FROM cash_transaction
        WHERE reference_id = %s AND type='DIVIDEND'
    """, (div_id,))
    assert round(float(cur.fetchone()["amount"]), 2) == 150.00

    # --------------------------------------------------
    # F) Delete dividend → cash removed
    # --------------------------------------------------
    expect_success(get(http_client, "deleteDividend.php", params={"id": div_id}))

    db.commit()

    cur.execute("""
        SELECT *
        FROM cash_transaction
        WHERE reference_id = %s
    """, (div_id,))
    assert cur.fetchone() is None

    # --------------------------------------------------
    # Final balance check
    # --------------------------------------------------
    cur.execute("""
        SELECT current_balance
        FROM cash_account
        WHERE broker_id = %s
    """, (broker_id,))
    assert round(float(cur.fetchone()["current_balance"]), 2) == 0.00

    cur.close()


def test_get_cash_summary(http_client, db, test_broker):
    """
    Ensure getCashSummary returns a correct cash balance computed from:
        initial_balance + SUM(cash_transaction.amount)
    """

    broker_id = test_broker["broker_id"]
    cur = db.cursor()

    # ------------------------------------------------------------------
    # 1) Prepare deterministic data
    # ------------------------------------------------------------------

    cur.execute(
        "DELETE FROM cash_transaction WHERE broker_account_id = %s",
        (broker_id,)
    )

    cur.execute("""
        UPDATE cash_account
        SET initial_balance = 1000.00
        WHERE broker_id = %s
    """, (broker_id,))

    movements = [
        ("DEPOSIT",     500.00),
        ("WITHDRAWAL", -200.00),
        ("DIVIDEND",     50.00),
    ]

    for tx_type, amount in movements:
        cur.execute("""
            INSERT INTO cash_transaction (
                broker_account_id,
                date,
                amount,
                type
            ) VALUES (%s, %s, %s, %s)
        """, (
            broker_id,
            "2025-01-15 12:00:00",
            amount,
            tx_type
        ))

    db.commit()

    expected_balance = 1350.00

    # ------------------------------------------------------------------
    # 2) Call API
    # ------------------------------------------------------------------
    resp = get(
        http_client,
        "getCashSummary.php",
        params={"broker_id": broker_id}
    )

    j = expect_ok(resp)   # ✅ CORRECTION CLÉ

    # ------------------------------------------------------------------
    # 3) Structural validation
    # ------------------------------------------------------------------
    assert j["broker_id"] == broker_id
    assert "currency" in j
    assert "current_balance" in j
    assert "initial_balance" in j
    assert "status" in j

    assert isinstance(j["current_balance"], (int, float, str))
    assert isinstance(j["initial_balance"], (int, float, str))

    assert j["status"] in ("positive", "negative", "zero")

    # ------------------------------------------------------------------
    # 4) Business validation
    # ------------------------------------------------------------------
    assert round(float(j["initial_balance"]), 2) == 1000.00
    assert round(float(j["current_balance"]), 2) == expected_balance
    assert j["status"] == "positive"

    # ------------------------------------------------------------------
    # 5) Cross-check with database aggregation
    # ------------------------------------------------------------------
    cur.execute("""
        SELECT
            ca.initial_balance +
            COALESCE(SUM(ct.amount), 0) AS balance
        FROM cash_account ca
        LEFT JOIN cash_transaction ct
            ON ct.broker_account_id = ca.broker_id
        WHERE ca.broker_id = %s
        GROUP BY ca.initial_balance
    """, (broker_id,))

    row = cur.fetchone()
    assert row is not None
    assert round(float(row["balance"]), 2) == expected_balance

    cur.close()



def test_get_cash_transactions(http_client, db, test_broker):
    """
    Ensure that cash transactions API returns a list of ledger entries
    for the given broker account.
    """
    broker_id = test_broker["broker_id"]

    resp = get(http_client, "getCashTransactions.php", params={
        "broker_id": broker_id
    })
    j = expect_success(resp)

    assert isinstance(j, list)

    # If transactions exist, validate expected fields
    if len(j) > 0:
        row = j[0]
        assert "transaction_date" in row
        assert "type" in row
        assert "amount" in row


def test_add_cash_adjustment(http_client, db, test_broker):
    """
    Create a manual cash adjustment and verify:
    - cash_transaction is inserted
    - cash_account balance is updated accordingly
    """
    broker_id = test_broker["broker_id"]
    cur = db.cursor()

    adjustment_amount = 123.45

    # Add cash adjustment
    resp = post(http_client, "addCashAdjustment.php", {
        "broker_id": broker_id,
        "amount": adjustment_amount,
        "comment": "Manual cash adjustment for test"
    })
    j = expect_success(resp)

    assert j["broker_id"] == broker_id
    assert round(float(j["amount"]), 2) == round(adjustment_amount, 2)

    # Verify adjustment exists in cash_transaction
    cur.execute("""
        SELECT * FROM cash_transaction
        WHERE broker_account_id = %s AND type = 'ADJUSTMENT'
        ORDER BY id DESC
        LIMIT 1
    """, (broker_id,))
    ct = cur.fetchone()
    assert ct is not None
    assert round(float(ct["amount"]), 2) == round(adjustment_amount, 2)

    # ADJUSTMENT should not reference an order or dividend
    assert ct["reference_id"] is None

    # Verify aggregated balance consistency
    sum_db = sum_cash_db(cur, broker_id)
    b = cash_account_balance(cur, broker_id)

    assert round(sum_db, 2) == round(b, 2)

    cur.close()

def test_add_cash_adjustment_invalid_amount(http_client, test_broker):
    resp = post(http_client, "addCashAdjustment.php", {
        "broker_id": test_broker["broker_id"],
        "amount": 0
    })
    assert resp.status_code == 400
