# ========================================================
# test_cash_api.py – imports fixes pour datetime
# ========================================================

# Module complet pour accéder à datetime.datetime, datetime.timedelta, etc.
import datetime as dt_module  

# Classe datetime directement accessible pour .now(), .strptime(), etc.
from datetime import datetime, timezone

from decimal import Decimal
import pytest
import json
import time

from cashcue_core import db
from tests.utils import expect_success, expect_ok, sum_cash_db, cash_account_balance, extract_id

API_BASE = None

@pytest.fixture
def cash_transactions(db, test_broker):
    """
    Prepare a deterministic set of cash transactions for API filter tests.

    This fixture inserts a small, controlled dataset of cash movements
    (DEPOSIT, BUY, DIVIDEND, FEES) linked to a single broker account.
    The data is specifically designed to validate filtering logic
    (by transaction type, date range, and amount sign).

    Scope and lifecycle:
    - Executed only for tests that explicitly declare `cash_transactions`
      as a parameter.
    - Data is inserted into the test database using the `db` fixture.
    - All inserted rows are automatically cleaned up after the test
      execution, either by transaction rollback or database reset,
      depending on the `db` fixture implementation.
    - No manual DELETE is required and no production data is affected.

    Important:
    - This fixture must never be used against a production database.
    - The inserted data must remain deterministic; do not use `NOW()`
      or random values.
    """

    broker_account_id = test_broker["broker_account_id"]

    rows = [
        (
            broker_account_id,
            datetime(2024, 1, 10, 10, 0, 0),
            "BUY",
            -1000.00,
            "BUY test"
        ),
        (
            broker_account_id,
            datetime(2024, 1, 15, 15, 30, 0),
            "SELL",
            1200.00,
            "SELL test"
        ),
        (
            broker_account_id,
            datetime(2024, 2, 5, 9, 0, 0),
            "DIVIDEND",
            50.00,
            "DIVIDEND test"
        ),
    ]

    cursor = db.cursor()

    cursor.executemany(
        """
        INSERT INTO cash_transaction
        (broker_account_id, date, type, amount, comment)
        VALUES (%s, %s, %s, %s, %s)
        """,
        rows
    )

    db.commit()
    yield rows


@pytest.fixture
def ensure_cash_account(db, test_broker):
    """
    Assure qu'un cash_account existe pour le broker avant le test.
    """
    broker_account_id = test_broker["broker_account_id"]
    cur = db.cursor()
    cur.execute("SELECT id FROM cash_account WHERE broker_account_id=%s", (broker_account_id,))
    if cur.fetchone() is None:
        cur.execute(
            "INSERT INTO cash_account (broker_account_id, name, initial_balance, current_balance, created_at) "
            "VALUES (%s, %s, 0.0, 0.0, %s)",
            (broker_account_id, f"TEST_ACCOUNT_{broker_account_id}", datetime.now())
        )
        db.commit()
    cur.close()
    yield  # permet d'utiliser la fixture

@pytest.fixture(autouse=True)
def set_api_base():
    global API_BASE
    import os
    API_BASE = os.environ.get("CASHCUE_API_BASE_URL", "http://localhost/cashcue/api")

def post(client, path, payload):
    url = f"{API_BASE}/{path}"
    return client.post(url, json=payload)
 
def get(client, path, params=None):
    url = f"{API_BASE}/{path}"
    return client.get(url, params=params)

def test_add_cancel_and_recreate_order_flow(http_client, db, test_broker):
    broker_account_id = test_broker["broker_account_id"]
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
        "broker_account_id": broker_account_id,
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

    cur.execute("SELECT amount FROM cash_transaction WHERE reference_id = %s", (order_buy_id,))
    ct = cur.fetchone()
    assert round(float(ct["amount"]), 2) == round(-buy_total, 2)

    expected_balance = -buy_total
    assert round(sum_cash_db(cur, broker_account_id), 2) == expected_balance

    # --------------------------------------------------
    # 3) Add SELL order
    # --------------------------------------------------
    sell_qty = 50
    sell_price = 20.0
    sell_fees = 2.0
    sell_cash = sell_qty * sell_price - sell_fees

    resp = post(http_client, "addOrder.php", {
        "broker_account_id": broker_account_id,
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

    cur.execute("SELECT amount FROM cash_transaction WHERE reference_id = %s", (order_sell_id,))
    ct = cur.fetchone()
    assert round(float(ct["amount"]), 2) == round(sell_cash, 2)

    expected_balance += sell_cash
    assert round(sum_cash_db(cur, broker_account_id), 2) == expected_balance

    # --------------------------------------------------
    # 4) Cancel SELL order
    # --------------------------------------------------
    expect_success(
        post(http_client, "cancelOrder.php", {"id": order_sell_id})
    )
    db.commit()

    cur.execute("""
        SELECT amount
        FROM cash_transaction
        WHERE reference_id = %s
        ORDER BY id
    """, (order_sell_id,))
    rows = cur.fetchall()
    assert len(rows) == 2
    assert round(float(rows[0]["amount"]) + float(rows[1]["amount"]), 2) == 0.00

    expected_balance -= sell_cash
    assert round(sum_cash_db(cur, broker_account_id), 2) == expected_balance

    # --------------------------------------------------
    # 5) Recreate SELL order with new price
    # --------------------------------------------------
    new_sell_price = 30.0
    new_sell_cash = sell_qty * new_sell_price - sell_fees

    resp = post(http_client, "addOrder.php", {
        "broker_account_id": broker_account_id,
        "instrument_id": instrument_id,
        "order_type": "SELL",
        "quantity": sell_qty,
        "price": new_sell_price,
        "fees": sell_fees,
        "trade_date": "2025-01-07 12:00:00"
    })
    j = expect_success(resp)
    db.commit()
    new_order_sell_id = extract_id(j, "order_id")

    cur.execute("SELECT amount FROM cash_transaction WHERE reference_id = %s", (new_order_sell_id,))
    ct = cur.fetchone()
    assert round(float(ct["amount"]), 2) == round(new_sell_cash, 2)

    expected_balance += new_sell_cash
    assert round(sum_cash_db(cur, broker_account_id), 2) == expected_balance

    # --------------------------------------------------
    # 6) Cancel BUY order
    # --------------------------------------------------
    expect_success(
        post(http_client, "cancelOrder.php", {"id": order_buy_id})
    )
    db.commit()

    cur.execute("""
        SELECT amount
        FROM cash_transaction
        WHERE reference_id = %s
        ORDER BY id
    """, (order_buy_id,))
    rows = cur.fetchall()
    assert len(rows) == 2
    assert round(float(rows[0]["amount"]) + float(rows[1]["amount"]), 2) == 0.00

    expected_balance += buy_total
    assert round(sum_cash_db(cur, broker_account_id), 2) == round(new_sell_cash, 2)

    # --------------------------------------------------
    # Cleanup
    # --------------------------------------------------
    cur.execute("DELETE FROM instrument WHERE id = %s", (instrument_id,))
    db.commit()
    cur.close()


def test_cancel_order_reverts_cash(http_client, db, test_broker):
    broker_account_id = test_broker["broker_account_id"]
    cur = db.cursor()

    symbol = f"CANCEL_{int(time.time())}"

    cur.execute("""
        INSERT INTO instrument (label, symbol, type, currency)
        VALUES (%s, %s, 'STOCK', 'EUR')
    """, ("TEST_CANCEL", symbol))
    instrument_id = cur.lastrowid
    db.commit()

    # Create BUY order
    resp = post(http_client, "addOrder.php", {
        "broker_account_id": broker_account_id,
        "instrument_id": instrument_id,
        "order_type": "BUY",
        "quantity": 10,
        "price": 100,
        "fees": 0,
        "trade_date": "2025-01-10 10:00:00"
    })
    j = expect_success(resp)
    order_id = extract_id(j, "order_id")
    db.commit()

    # Check initial cash impact
    cur.execute(
        "SELECT amount FROM cash_transaction WHERE reference_id = %s",
        (order_id,)
    )
    assert cur.fetchone() is not None

    # Cancel order (POST API)
    expect_success(
        post(http_client, "cancelOrder.php", {"id": order_id})
    )

    db.commit()

    # Original + reversal must exist
    cur.execute("""
        SELECT amount
        FROM cash_transaction
        WHERE reference_id = %s
        ORDER BY id
    """, (order_id,))
    rows = cur.fetchall()

    assert len(rows) == 2

    original = float(rows[0]["amount"])
    reversal = float(rows[1]["amount"])

    # Neutralisation comptable
    assert round(original + reversal, 2) == 0.00

    # Cash balance unchanged
    assert round(cash_account_balance(cur, broker_account_id), 2) == 0.00

    cur.close()


def test_dividend_flow_full_coverage(http_client, db, test_broker):
    broker_account_id = test_broker["broker_account_id"]
    cur = db.cursor()

    # --------------------------------------------------
    # Ensure broker has a cash account
    # --------------------------------------------------
    cur.execute("UPDATE broker_account SET has_cash_account = 1 WHERE id = %s", (broker_account_id,))
    cur.execute("""
        INSERT IGNORE INTO cash_account (broker_account_id, current_balance)
        VALUES (%s, 0)
    """, (broker_account_id,))
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
        "broker_account_id": broker_account_id,
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
    # F) Cancel dividend → cash reversal
    # --------------------------------------------------
    expect_success(
        post(http_client, "cancelDividend.php", {"id": div_id})
    )
    db.commit()

    cur.execute("""
        SELECT amount
        FROM cash_transaction
        WHERE reference_id = %s
        AND type = 'DIVIDEND'
        ORDER BY id
    """, (div_id,))
    rows = cur.fetchall()

    assert len(rows) == 2

    original = float(rows[0]["amount"])
    reversal = float(rows[1]["amount"])

    # Reversal must neutralize original
    assert round(original + reversal, 2) == 0.00

    # --------------------------------------------------
    # Final balance check
    # --------------------------------------------------
    cur.execute("""
        SELECT current_balance
        FROM cash_account
        WHERE broker_account_id = %s
    """, (broker_account_id,))

    assert round(cash_account_balance(cur, broker_account_id), 2) == 0.00

    cur.close()


def test_get_cash_summary(http_client, db, test_broker):
    """
    Ensure getCashSummary returns a correct cash balance computed from:
        initial_balance + SUM(cash_transaction.amount)
    """

    broker_account_id = test_broker["broker_account_id"]
    cur = db.cursor()

    # ------------------------------------------------------------------
    # 1) Prepare deterministic data
    # ------------------------------------------------------------------

    cur.execute(
        "DELETE FROM cash_transaction WHERE broker_account_id = %s",
        (broker_account_id,)
    )

    cur.execute("""
        UPDATE cash_account
        SET initial_balance = 1000.00
        WHERE broker_account_id = %s
    """, (broker_account_id,))

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
            broker_account_id,
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
        params={"broker_account_id": broker_account_id}
    )

    j = expect_ok(resp)   # ✅ CORRECTION CLÉ

    # ------------------------------------------------------------------
    # 3) Structural validation
    # ------------------------------------------------------------------
    assert j["broker_account_id"] == broker_account_id
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
            ON ct.broker_account_id = ca.broker_account_id
        WHERE ca.broker_account_id = %s
        GROUP BY ca.initial_balance
    """, (broker_account_id,))

    row = cur.fetchone()
    assert row is not None
    assert round(float(row["balance"]), 2) == expected_balance

    cur.close()

def test_get_cash_transactions_with_filters(http_client, cash_transactions, test_broker):
    broker_account_id = test_broker["broker_account_id"]

    resp = get(
        http_client,
        "getCashTransactions.php",
        params={
            "broker_account_id": broker_account_id,
            "from": "2024-01-12",
            "to": "2024-01-31",
            "type": "SELL",
        }
    )

    j = expect_success(resp)

    assert "count" in j
    assert "data" in j
    assert j["count"] == 1

    row = j["data"][0]

    assert row["type"] == "SELL"
    assert row["amount"] == "1200.00" or float(row["amount"]) == 1200.00
    assert row["date"].startswith("2024-01-15")

def test_add_cash_adjustment(http_client, db, test_broker, ensure_cash_account):
    """
    Test l'ajout d'un ajustement de cash valide via POST.
    Vérifie :
    - l'insertion dans cash_transaction
    - la mise à jour du solde du broker
    """
    broker_account_id = test_broker["broker_account_id"]
    amount = 123.45
    comment = "Manual cash adjustment for test"

    resp = post(http_client, "addCashAdjustment.php", {
        "broker_account_id": broker_account_id,
        "amount": amount,
        "comment": comment
    })

    j = expect_success(resp)
    assert j["broker_account_id"] == broker_account_id
    assert float(j["amount"]) == amount

    # Vérification du solde dans la DB
    cur = db.cursor()
    cur.execute("SELECT current_balance FROM cash_account WHERE broker_account_id=%s", (broker_account_id,))
    row = cur.fetchone()
    assert row is not None
    assert abs(float(row[0]) - amount) < 0.01
    cur.close()


def test_add_cash_adjustment_invalid_amount(http_client, db, test_broker, ensure_cash_account):
    """
    Test qu'un ajustement avec amount=0 retourne HTTP 400.
    """
    broker_account_id = test_broker["broker_account_id"]

    resp = post(http_client, "addCashAdjustment.php", {
        "broker_account_id": broker_account_id,
        "amount": 0,
        "comment": "Invalid adjustment"
    })

    assert resp.status_code == 400
    j = resp.json()
    assert "Amount cannot be zero" in j.get("error", "")