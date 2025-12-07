# tests/conftest.py
import os
import sys
import pytest
import requests
import pymysql
from decimal import Decimal
from datetime import datetime

# =============================================================================
#  Add project root to PYTHONPATH
# =============================================================================

PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
if PROJECT_ROOT not in sys.path:
    sys.path.insert(0, PROJECT_ROOT)

# Imported after path fix
from lib.config import ConfigManager


# =============================================================================
#  FIXTURE: Load config
# =============================================================================
@pytest.fixture(scope="session")
def config():
    """
    Load configuration from CASHCUE_CONFIG_FILE or /etc/cashcue/cashcue.conf.
    """
    config_path = os.environ.get("CASHCUE_CONFIG_FILE", "/etc/cashcue/cashcue.conf")

    if not os.path.exists(config_path):
        raise RuntimeError(
            f"[conftest] Config file not found: {config_path}"
        )

    cfg = ConfigManager()
    return cfg


# =============================================================================
#  FIXTURE: Database connection (PyMySQL)
# =============================================================================
@pytest.fixture(scope="session")
def db(config):
    conn = pymysql.connect(
        host=config.get("DB_HOST", "localhost"),
        user=config.get("DB_USER"),
        password=config.get("DB_PASS"),
        database=config.get("DB_NAME"),
        port=int(config.get("DB_PORT", 3306)),
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=False,
    )
    yield conn
    conn.close()


@pytest.fixture
def cursor(db):
    with db.cursor() as cur:
        yield cur


# =============================================================================
#  FIXTURE: HTTP client for API calls
# =============================================================================
@pytest.fixture(scope="session")
def http_client():
    """
    A simple HTTP session with JSON headers.
    """
    s = requests.Session()
    s.headers.update({"Content-Type": "application/json"})
    yield s
    s.close()


# =============================================================================
#  FIXTURE: Helper to compare Decimal values with 2-digit precision
# =============================================================================
@pytest.fixture
def D():
    """
    Convert values into Decimal with 2 digits.
    """
    def _to_decimal(x):
        if isinstance(x, Decimal):
            value = x
        else:
            value = Decimal(str(x))
        return value.quantize(Decimal("0.01"))
    return _to_decimal


# =============================================================================
#  FIXTURE: Create a test broker_account + cash_account
# =============================================================================
@pytest.fixture(scope="function")
def test_broker(db):
    """
    Creates a dedicated test broker with a cash account and cleans everything afterward.
    """
    cur = db.cursor()

    ts = datetime.utcnow().strftime("%Y%m%d%H%M%S")
    name = f"TEST_CASH_{ts}"

    # Create broker account
    cur.execute("""
        INSERT INTO broker_account (name, account_type, currency, created_at, has_cash_account)
        VALUES (%s, 'PEA', 'EUR', NOW(), 1)
    """, (name,))
    broker_id = cur.lastrowid

    # Create associated cash account
    cur.execute("""
        INSERT INTO cash_account (broker_id, name, initial_balance, current_balance, created_at)
        VALUES (%s, %s, 0.00, 0.00, NOW())
    """, (broker_id, f"Cash {name}"))

    db.commit()

    yield {
        "broker_id": broker_id,
        "broker_name": name,
    }

    # -------------------------------------------------------------------------
    # CLEANUP
    # -------------------------------------------------------------------------
    cur.execute("DELETE FROM cash_transaction WHERE broker_account_id = %s", (broker_id,))
    cur.execute("DELETE FROM order_transaction WHERE broker_id = %s", (broker_id,))
    cur.execute("DELETE FROM dividend WHERE broker_id = %s", (broker_id,))
    cur.execute("DELETE FROM cash_account WHERE broker_id = %s", (broker_id,))
    cur.execute("DELETE FROM broker_account WHERE id = %s", (broker_id,))

    db.commit()
    cur.close()
