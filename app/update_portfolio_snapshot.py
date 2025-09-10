#!/usr/bin/env python3
"""
CashCue - Portfolio Snapshot Updater (OOD version)

This script calculates daily portfolio snapshots per broker:
- Total portfolio market value
- Invested amount
- Unrealized and realized P/L
- Dividends received
- Cash balance

It writes a single record per broker per day into `portfolio_snapshot`.

Features:
- Object-oriented design
- Uses centralized ConfigManager, LoggerManager, DatabaseConnection
- --dry-run support
"""

import argparse
from datetime import datetime
from decimal import Decimal

from lib.config import ConfigManager
from lib.logger import LoggerManager
from lib.db import DatabaseConnection


class PortfolioSnapshotUpdater:
    def __init__(self, config, logger, dry_run=False):
        self.config = config
        self.logger = LoggerManager().get_logger()
        self.dry_run = dry_run

        # DB connection
        self.db = DatabaseConnection(
            host=config.get("DB_HOST", "localhost"),
            user=config.get("DB_USER"),
            password=config.get("DB_PASS"),
            database=config.get("DB_NAME"),
            port=int(config.get("DB_PORT", 3306))
        )
        self.db.connect()

    def fetch_brokers(self):
        sql = "SELECT id, name FROM broker_account"
        with self.db.cursor() as cur:
            cur.execute(sql)
            return cur.fetchall()

    def fetch_instruments(self, broker_id):
        sql = """
            SELECT o.instrument_id, i.label, i.symbol,
                   SUM(CASE WHEN o.order_type='BUY' THEN o.quantity ELSE 0 END) -
                   SUM(CASE WHEN o.order_type='SELL' THEN o.quantity ELSE 0 END) AS qty_held,
                   SUM(CASE WHEN o.order_type='BUY' THEN o.total_cost ELSE 0 END) AS invested_amount
            FROM order_transaction o
            JOIN instrument i ON i.id=o.instrument_id
            WHERE o.broker_id=%s
            GROUP BY o.instrument_id
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (broker_id,))
            return cur.fetchall()

    def fetch_latest_price(self, instrument_id):
        sql = """
            SELECT price FROM realtime_price
            WHERE instrument_id=%s
            ORDER BY captured_at DESC LIMIT 1
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (instrument_id,))
            row = cur.fetchone()
            return Decimal(str(row["price"])) if row and row["price"] is not None else None

    def fetch_dividends(self, broker_id, today):
        sql = """
            SELECT COALESCE(SUM(amount),0) AS dividends
            FROM dividend
            WHERE broker_id=%s AND payment_date<=%s
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (broker_id, today))
            row = cur.fetchone()
            return Decimal(row["dividends"]) if row else Decimal("0.0")

    def upsert_snapshot(self, broker_id, today, snapshot):
        sql = """
            INSERT INTO portfolio_snapshot
            (broker_id, date, total_value, invested_amount, unrealized_pl, realized_pl, dividends_received, cash_balance)
            VALUES (%s,%s,%s,%s,%s,%s,%s,%s)
            ON DUPLICATE KEY UPDATE
                total_value=VALUES(total_value),
                invested_amount=VALUES(invested_amount),
                unrealized_pl=VALUES(unrealized_pl),
                dividends_received=VALUES(dividends_received),
                cash_balance=VALUES(cash_balance)
        """
        params = (
            broker_id, today,
            snapshot["total_value"], snapshot["invested_amount"], snapshot["unrealized_pl"],
            Decimal("0.0"), snapshot["dividends_received"], snapshot["cash_balance"]
        )

        if self.dry_run:
            self.logger.info(f"[DRY-RUN] SQL: {sql} | Params: {params}")
        else:
            with self.db.cursor() as cur:
                cur.execute(sql, params)

    def run(self):
        today = datetime.now().date()
        self.logger.info(f"=== Starting Portfolio Snapshot Update (DRY_RUN={self.dry_run}) ===")

        brokers = self.fetch_brokers()
        if not brokers:
            self.logger.warning("No brokers found in database.")
            return

        for broker in brokers:
            broker_id = broker["id"]
            broker_name = broker["name"]
            instruments = self.fetch_instruments(broker_id)

            total_value = Decimal("0.0")
            total_invested = Decimal("0.0")
            total_unrealized_pl = Decimal("0.0")

            for instr in instruments:
                qty_held = instr["qty_held"] or 0
                invested_amount = instr["invested_amount"] or 0

                if qty_held <= 0:
                    continue

                market_price = self.fetch_latest_price(instr["instrument_id"])
                if market_price:
                    market_value = Decimal(qty_held) * market_price
                    unrealized_pl = market_value - Decimal(invested_amount)
                    total_value += market_value
                    total_invested += Decimal(invested_amount)
                    total_unrealized_pl += unrealized_pl
                else:
                    self.logger.warning(f"No market price for instrument {instr['symbol']}")

            dividends_received = self.fetch_dividends(broker_id, today)
            cash_balance = Decimal("0.0")  # Future: track cash separately

            snapshot = {
                "total_value": total_value,
                "invested_amount": total_invested,
                "unrealized_pl": total_unrealized_pl,
                "dividends_received": dividends_received,
                "cash_balance": cash_balance,
            }

            self.upsert_snapshot(broker_id, today, snapshot)
            self.logger.info(f"Portfolio snapshot updated for broker '{broker_name}' ({broker_id})")

        self.logger.info("=== Portfolio Snapshot Update Completed ===")


def main():
    parser = argparse.ArgumentParser(description="CashCue Portfolio Snapshot Updater (OOD)")
    parser.add_argument("--dry-run", action="store_true", help="Simulate execution without DB writes")
    args = parser.parse_args()

    # Load config & logger
    config = ConfigManager("/etc/cashcue/cashcue.conf")
    logger = LoggerManager(config.get("LOG_FILE", "/var/log/cashcue/portfolio_snapshot.log"))
    dry_run = args.dry_run or config.get("DRY_RUN", "false").lower() == "true"

    updater = PortfolioSnapshotUpdater(config, logger, dry_run)
    updater.run()


if __name__ == "__main__":
    main()
