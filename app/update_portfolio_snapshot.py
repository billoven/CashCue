##!/usr/bin/env python3
"""
CashCue – Portfolio Snapshot Updater
====================================

Purpose
-------
This script computes and stores DAILY portfolio snapshots per broker account
into the `portfolio_snapshot` table.

A snapshot represents the *financial state of the portfolio at a given date*,
not a historical cash-flow report.

For each broker_account and date, the script computes:
- total_value        : Market value of currently held instruments
- invested_amount    : Cost basis of OPEN positions (capital still exposed)
- unrealized_pl      : Latent P/L = total_value - invested_amount
- realized_pl        : Currently NOT computed here (reserved for future FIFO logic)
- dividends_received : Cumulated dividends paid up to snapshot date
- cash_balance       : Cash balance as computed by recalc_cash_balances

IMPORTANT FINANCIAL DEFINITIONS
-------------------------------
- invested_amount ≠ total BUY amount
  invested_amount is the NET cost basis of positions still held.
  Total historical BUY amounts (e.g. PEA cap usage) must be computed elsewhere.

- Snapshot date uses the last known market price <= snapshot date.
  This guarantees temporal consistency.

Assumptions
-----------
- order_transaction contains:
    - order_type in ('BUY', 'SELL')
    - quantity
    - total_cost (including fees)
- instrument.status is meaningful:
    - only ACTIVE or SUSPENDED instruments are valued
- cash_balance is computed externally by app.recalc_cash_balances
- realized P/L will be handled by a dedicated process in the future

Safety
------
- --dry-run prevents any DB writes
- cash_balance is NEVER overwritten with zero blindly

Author
------
CashCue project – financial logic validated for production usage

IMPORTANT
---------
Cash balances must be recalculated before snapshot persistence.
This requirement is satisfied when update_portfolio_snapshot.py is executed with the --with-cash option.
"""

import argparse
import subprocess
from datetime import datetime, date
from decimal import Decimal

from lib.config import ConfigManager
from lib.logger import LoggerManager
from lib.db import DatabaseConnection


class PortfolioSnapshotUpdater:
    """
    Computes and upserts daily portfolio snapshots per broker account.
    """

    def __init__(self, config, logger, dry_run: bool = False):
        self.config = config
        self.logger = logger.get_logger()
        self.dry_run = dry_run

        self.db = DatabaseConnection(
            host=config.get("DB_HOST", "localhost"),
            user=config.get("DB_USER"),
            password=config.get("DB_PASS"),
            database=config.get("DB_NAME"),
            port=int(config.get("DB_PORT", 3306)),
        )
        self.db.connect()

    # ---------------------------------------------------------
    # DATA FETCHING
    # ---------------------------------------------------------

    def fetch_brokers(self):
        sql = "SELECT id, name FROM broker_account"
        with self.db.cursor() as cur:
            cur.execute(sql)
            return cur.fetchall()

    def fetch_instruments(self, broker_account_id: int):
        """
        Returns only OPEN positions with a positive quantity held.
        invested_amount represents the NET cost basis of remaining positions.
        """
        sql = """
            SELECT
                o.instrument_id,
                i.label,
                i.symbol,

                SUM(
                    CASE
                        WHEN o.order_type = 'BUY'  THEN o.quantity
                        WHEN o.order_type = 'SELL' THEN -o.quantity
                        ELSE 0
                    END
                ) AS qty_held,

                SUM(
                    CASE
                        WHEN o.order_type = 'BUY'  THEN o.total_cost
                        WHEN o.order_type = 'SELL' THEN -o.total_cost
                        ELSE 0
                    END
                ) AS invested_amount

            FROM order_transaction o
            JOIN instrument i ON i.id = o.instrument_id

            WHERE o.broker_account_id = %s
              AND i.status IN ('ACTIVE', 'SUSPENDED')

            GROUP BY o.instrument_id
            HAVING qty_held > 0
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (broker_account_id,))
            return cur.fetchall()

    def fetch_latest_price(self, instrument_id: int, snapshot_date: date):
        """
        Returns the last known market price <= snapshot date.
        Ensures temporal consistency of the snapshot.
        """
        sql = """
            SELECT price
            FROM realtime_price
            WHERE instrument_id = %s
              AND captured_at <= %s
            ORDER BY captured_at DESC
            LIMIT 1
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (instrument_id, snapshot_date))
            row = cur.fetchone()

            if row and row["price"] is not None:
                return Decimal(str(row["price"]))

            return None

    def fetch_dividends(self, broker_account_id: int, snapshot_date: date):
        """
        Returns total dividends paid up to snapshot date.
        Dividends are assumed to be NET (after taxes).
        """
        sql = """
            SELECT COALESCE(SUM(amount), 0) AS dividends
            FROM dividend
            WHERE broker_account_id = %s
              AND payment_date <= %s
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (broker_account_id, snapshot_date))
            row = cur.fetchone()
            return Decimal(row["dividends"]) if row else Decimal("0.00")

    def fetch_last_cash_balance(self, broker_account_id: int):
        """
        Reads the latest known cash balance.
        Prevents accidental overwrite with zero.
        """
        sql = """
            SELECT cash_balance
            FROM portfolio_snapshot
            WHERE broker_account_id = %s
            ORDER BY date DESC
            LIMIT 1
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (broker_account_id,))
            row = cur.fetchone()

            if row and row["cash_balance"] is not None:
                return Decimal(row["cash_balance"])

            return Decimal("0.00")

    # ---------------------------------------------------------
    # SNAPSHOT WRITE
    # ---------------------------------------------------------

    def upsert_snapshot(self, broker_account_id: int, snapshot_date: date, snapshot: dict):
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
            snapshot_date,
            snapshot["total_value"],
            snapshot["invested_amount"],
            snapshot["unrealized_pl"],
            snapshot["realized_pl"],
            snapshot["dividends_received"],
            snapshot["cash_balance"],
        )

        if self.dry_run:
            self.logger.info(f"[DRY-RUN] {sql} | {params}")
            return

        with self.db.cursor() as cur:
            cur.execute(sql, params)

    # ---------------------------------------------------------
    # MAIN LOGIC
    # ---------------------------------------------------------

    def run(self):
        snapshot_date = datetime.now().date()
        self.logger.info(
            f"=== Portfolio Snapshot Update started (date={snapshot_date}, dry_run={self.dry_run}) ==="
        )

        brokers = self.fetch_brokers()
        if not brokers:
            self.logger.warning("No broker accounts found.")
            return

        for broker in brokers:
            broker_id = broker["id"]
            broker_name = broker["name"]

            total_value = Decimal("0.00")
            total_invested = Decimal("0.00")
            total_unrealized = Decimal("0.00")

            instruments = self.fetch_instruments(broker_id)

            for instr in instruments:
                qty = Decimal(instr["qty_held"])
                invested = Decimal(instr["invested_amount"])

                price = self.fetch_latest_price(instr["instrument_id"], snapshot_date)
                if price is None:
                    self.logger.warning(
                        f"No market price for {instr['symbol']} (broker={broker_name})"
                    )
                    continue

                market_value = qty * price
                unrealized = market_value - invested

                total_value += market_value
                total_invested += invested
                total_unrealized += unrealized

            dividends = self.fetch_dividends(broker_id, snapshot_date)
            cash_balance = self.fetch_last_cash_balance(broker_id)

            snapshot = {
                "total_value": total_value,
                "invested_amount": total_invested,
                "unrealized_pl": total_unrealized,
                "realized_pl": Decimal("0.00"),  # Explicitly not computed here
                "dividends_received": dividends,
                "cash_balance": cash_balance,
            }

            self.upsert_snapshot(broker_id, snapshot_date, snapshot)

            self.logger.info(
                f"Snapshot stored for broker '{broker_name}' "
                f"(value={total_value}, invested={total_invested})"
            )

        self.logger.info("=== Portfolio Snapshot Update completed ===")


# ---------------------------------------------------------
# CLI ENTRY POINT
# ---------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(
        description="CashCue – Daily Portfolio Snapshot Updater"
    )
    parser.add_argument("--dry-run", action="store_true", help="No DB write")
    parser.add_argument(
        "--with-cash",
        action="store_true",
        help="Run cash recalculation before snapshot",
    )

    args = parser.parse_args()

    config = ConfigManager("/etc/cashcue/cashcue.conf")
    logger = LoggerManager(config.get("LOG_FILE", "/var/log/cashcue/portfolio_snapshot.log"))

    dry_run = args.dry_run or config.get("DRY_RUN", "false").lower() == "true"

    # ---------------------------------------------------------
    # Optional: recompute cash BEFORE snapshot
    # ---------------------------------------------------------
    if args.with_cash:
        logger.get_logger().info("Running cash balance recalculation...")
        cmd = [
            "/opt/cashcue/venv/bin/python3",
            "-m",
            "app.recalc_cash_balances",
        ]
        if dry_run:
            cmd.append("--dry-run")

        subprocess.run(cmd, capture_output=True, text=True)

    updater = PortfolioSnapshotUpdater(config, logger, dry_run)
    updater.run()


if __name__ == "__main__":
    main()
