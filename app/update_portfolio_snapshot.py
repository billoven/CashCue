#!/usr/bin/env python3
"""
CashCue - Portfolio Snapshot Updater (Pedagogical Version)
==========================================================

PURPOSE
-------
This script captures a DAILY FINANCIAL SNAPSHOT of each broker account.

A snapshot is a *photograph*, not a recalculation engine.

Core principle:
    - CashCue has a dedicated CASH LEDGER
    - CashCue persists CURRENT CASH BALANCE
    - This script MUST NEVER recompute cash

ARCHITECTURAL RULES
-------------------
1. cash_account.current_balance is the ONLY source of truth for cash
2. cash_transaction is the ONLY source of dividends and cash movements
3. order_transaction is the ONLY source for invested capital & realized P/L
4. portfolio_snapshot stores a historical snapshot (append / upsert)

WHAT THIS SCRIPT DOES
---------------------
For each broker_account:
- Reads current cash balance
- Computes invested amount (BUY - SELL)
- Computes total portfolio market value
- Computes unrealized P/L
- Aggregates dividends received
- Stores everything into portfolio_snapshot

WHAT THIS SCRIPT DOES NOT DO
----------------------------
- Does NOT recalculate cash from transactions
- Does NOT repair inconsistencies
- Does NOT update cash_account
- Does NOT mutate business data

If cash is wrong -> FIX THE CASH LEDGER, not the snapshot.

EXECUTION
---------
- Intended to run once per day (after market close)
- Safe to re-run (idempotent via ON DUPLICATE KEY UPDATE)
- DRY-RUN supported

"""

import argparse
from datetime import date
from decimal import Decimal

from lib.config import ConfigManager
from lib.logger import LoggerManager
from lib.db import DatabaseConnection


class PortfolioSnapshotUpdater:
    """
    Handles the creation of daily portfolio snapshots.

    Each broker_account is processed independently.
    """

    def __init__(self, config, logger, dry_run=False):
        self.config = config
        self.logger = logger
        self.dry_run = dry_run

        self.snapshot_date = date.today()

        self.db = DatabaseConnection(
            host=config.get("DB_HOST", "localhost"),
            user=config.get("DB_USER"),
            password=config.get("DB_PASS"),
            database=config.get("DB_NAME"),
            port=int(config.get("DB_PORT", 3306))
        )
        self.db.connect()

    # ------------------------------------------------------------------
    # FETCH: broker accounts
    # ------------------------------------------------------------------

    def fetch_broker_accounts(self):
        """
        Returns all broker accounts.
        """
        sql = "SELECT id, name FROM broker_account"
        with self.db.cursor() as cur:
            cur.execute(sql)
            return cur.fetchall()

    # ------------------------------------------------------------------
    # FETCH: cash (SOURCE OF TRUTH)
    # ------------------------------------------------------------------

    def fetch_cash_balance(self, broker_account_id):
        """
        Reads the OFFICIAL cash balance.

        IMPORTANT:
        This value is already consolidated and persisted.
        It MUST NOT be recomputed here.
        """
        sql = """
            SELECT current_balance
            FROM cash_account
            WHERE broker_account_id = %s
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (broker_account_id,))
            row = cur.fetchone()
            return Decimal(row["current_balance"]) if row else Decimal("0.00")

    # ------------------------------------------------------------------
    # FETCH: dividends
    # ------------------------------------------------------------------

    def fetch_dividends_received(self, broker_account_id):
        """
        Aggregates dividends from the cash ledger.
        """
        sql = """
            SELECT COALESCE(SUM(amount), 0) AS total
            FROM cash_transaction
            WHERE broker_account_id = %s
              AND type = 'DIVIDEND'
              AND date <= %s
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (broker_account_id, self.snapshot_date))
            row = cur.fetchone()
            return Decimal(row["total"])

    # ------------------------------------------------------------------
    # FETCH: invested amount
    # ------------------------------------------------------------------

    def fetch_invested_amount(self, broker_account_id):
        """
        Computes NET invested capital.

        Definition:
            invested_amount = SUM(BUY) - SUM(SELL)

        NOTE:
        This is NOT "total BUY volume".
        This represents capital currently at work.
        """
        sql = """
            SELECT
                COALESCE(SUM(
                    CASE
                        WHEN type = 'BUY'  THEN amount
                        WHEN type = 'SELL' THEN -amount
                        ELSE 0
                    END
                ), 0) AS invested
            FROM order_transaction
            WHERE broker_account_id = %s
              AND date <= %s
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (broker_account_id, self.snapshot_date))
            row = cur.fetchone()
            return Decimal(row["invested"])

    # ------------------------------------------------------------------
    # FETCH: total portfolio market value
    # ------------------------------------------------------------------

    def fetch_total_market_value(self, broker_account_id):
        """
        Computes current market value of holdings.

        Assumes:
        - Positions are derived from order_transaction
        - Latest prices are available in daily_price / realtime_price
        """
        sql = """
            SELECT COALESCE(SUM(p.quantity * pr.price), 0) AS total_value
            FROM position_view p
            JOIN latest_price_view pr ON pr.instrument_id = p.instrument_id
            WHERE p.broker_account_id = %s
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (broker_account_id,))
            row = cur.fetchone()
            return Decimal(row["total_value"])

    # ------------------------------------------------------------------
    # STORE SNAPSHOT
    # ------------------------------------------------------------------

    def store_snapshot(
        self,
        broker_account_id,
        total_value,
        invested_amount,
        unrealized_pl,
        realized_pl,
        dividends_received,
        cash_balance
    ):
        """
        Inserts or updates the portfolio snapshot.
        """
        sql = """
            INSERT INTO portfolio_snapshot
            (broker_account_id, date,
             total_value, invested_amount,
             unrealized_pl, realized_pl,
             dividends_received, cash_balance)
            VALUES (%s,%s,%s,%s,%s,%s,%s,%s)
            ON DUPLICATE KEY UPDATE
                total_value        = VALUES(total_value),
                invested_amount    = VALUES(invested_amount),
                unrealized_pl      = VALUES(unrealized_pl),
                realized_pl        = VALUES(realized_pl),
                dividends_received = VALUES(dividends_received),
                cash_balance       = VALUES(cash_balance)
        """

        params = (
            broker_account_id,
            self.snapshot_date,
            total_value,
            invested_amount,
            unrealized_pl,
            realized_pl,
            dividends_received,
            cash_balance
        )

        if self.dry_run:
            self.logger.info(f"[DRY-RUN] {sql} | {params}")
            return

        with self.db.cursor() as cur:
            cur.execute(sql, params)

    # ------------------------------------------------------------------
    # MAIN PROCESS
    # ------------------------------------------------------------------

    def run(self):
        self.logger.info(
            f"=== Portfolio Snapshot Update started "
            f"(date={self.snapshot_date}, dry_run={self.dry_run}) ==="
        )

        brokers = self.fetch_broker_accounts()

        for broker in brokers:
            bid = broker["id"]
            name = broker["name"]

            self.logger.info(f"Processing broker '{name}' (ID={bid})")

            cash_balance = self.fetch_cash_balance(bid)
            dividends = self.fetch_dividends_received(bid)
            invested = self.fetch_invested_amount(bid)
            total_value = self.fetch_total_market_value(bid)

            unrealized_pl = total_value - invested
            realized_pl = Decimal("0.00")  # placeholder (future extension)

            self.store_snapshot(
                broker_account_id=bid,
                total_value=total_value,
                invested_amount=invested,
                unrealized_pl=unrealized_pl,
                realized_pl=realized_pl,
                dividends_received=dividends,
                cash_balance=cash_balance
            )

            self.logger.info(
                f"Snapshot stored for '{name}' "
                f"(value={total_value}, invested={invested}, cash={cash_balance})"
            )

        self.logger.info("=== Portfolio Snapshot Update completed ===")


# ----------------------------------------------------------------------
# ENTRY POINT
# ----------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(
        description="CashCue Portfolio Snapshot Updater (Pedagogical)"
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Simulate execution without DB writes"
    )
    args = parser.parse_args()

    config = ConfigManager("/etc/cashcue/cashcue.conf")
    logger = LoggerManager(
        config.get("LOG_FILE", "/var/log/cashcue/portfolio_snapshot.log")
    ).get_logger()

    dry_run = args.dry_run or config.get("DRY_RUN", "false").lower() == "true"

    updater = PortfolioSnapshotUpdater(config, logger, dry_run)
    updater.run()


if __name__ == "__main__":
    main()
