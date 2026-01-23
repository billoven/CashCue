#!/usr/bin/env python3
"""
CashCue - Cash Balance Recalculator (OOD version)

This script recalculates cash balances for broker accounts that have
`has_cash_account = 1`.

It computes cumulative cash movements based on `cash_transaction` and
writes the resulting balance into:
- cash_account.cash_balance
- portfolio_snapshot.cash_balance (latest date)

Features:
- Object-oriented design
- Same structure and style as update_portfolio_snapshot.py
- Uses ConfigManager, LoggerManager, DatabaseConnection
- --dry-run support
"""

import argparse
from datetime import datetime
from decimal import Decimal

from lib.config import ConfigManager
from lib.logger import LoggerManager
from lib.db import DatabaseConnection


class CashBalanceRecalculator:
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

    # ---------------------------------------------------------
    #  FETCH METHODS
    # ---------------------------------------------------------

    def fetch_brokers_with_cash(self):
        sql = """
            SELECT id, name 
            FROM broker_account 
            WHERE has_cash_account = 1
        """
        with self.db.cursor() as cur:
            cur.execute(sql)
            return cur.fetchall()

    def fetch_cash_transactions(self, broker_account_id):
        sql = """
            SELECT amount, type
            FROM cash_transaction
            WHERE broker_account_id = %s
            ORDER BY date ASC
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (broker_account_id,))
            return cur.fetchall()

    def fetch_latest_snapshot_date(self):
        sql = "SELECT MAX(date) AS latest_date FROM portfolio_snapshot"
        with self.db.cursor() as cur:
            cur.execute(sql)
            row = cur.fetchone()
            return row["latest_date"] if row and row["latest_date"] else None

    # ---------------------------------------------------------
    #  CASH COMPUTATION
    # ---------------------------------------------------------

    def compute_cash_effect(self, amount, ttype):
        if ttype in ("DEPOSIT", "DIVIDEND", "SELL"):
            return amount
        elif ttype in ("BUY", "WITHDRAWAL", "FEES", "ADJUSTMENT"):
            return -amount
        else:
            self.logger.warning(f"Unhandled transaction type: {ttype}")
            return Decimal("0.0")

    # ---------------------------------------------------------
    #  UPDATE METHODS
    # ---------------------------------------------------------

    def update_cash_account(self, broker_account_id, cash_balance):
        sql = """
            UPDATE cash_account
            SET current_balance = %s
            WHERE broker_account_id = %s
        """
        params = (cash_balance, broker_account_id)

        if self.dry_run:
            self.logger.info(f"[DRY-RUN] SQL: {sql} | Params: {params}")
            return

        with self.db.cursor() as cur:
            cur.execute(sql, params)

    def update_snapshot_cash(self, broker_account_id, latest_date, cash_balance):
        sql = """
            UPDATE portfolio_snapshot
            SET cash_balance = %s
            WHERE broker_account_id = %s AND date = %s
        """
        params = (cash_balance, broker_account_id, latest_date)

        if self.dry_run:
            self.logger.info(f"[DRY-RUN] SQL: {sql} | Params: {params}")
            return

        with self.db.cursor() as cur:
            cur.execute(sql, params)

    # ---------------------------------------------------------
    #  MAIN LOGIC
    # ---------------------------------------------------------

    def run(self):
        self.logger.info(f"=== Starting Cash Balance Recalculation (DRY_RUN={self.dry_run}) ===")

        latest_date = self.fetch_latest_snapshot_date()
        if not latest_date:
            self.logger.warning("No portfolio_snapshot available. Exiting.")
            return

        brokers = self.fetch_brokers_with_cash()
        if not brokers:
            self.logger.warning("No broker accounts with has_cash_account=1.")
            return

        for broker in brokers:
            broker_account_id = broker["id"]
            broker_name = broker["name"]
            self.logger.info(f"Processing broker_account '{broker_name}' (ID={broker_account_id})")

            rows = self.fetch_cash_transactions(broker_account_id)

            cash_balance = Decimal("0.0")
            for row in rows:
                amount = Decimal(row["amount"])
                impact = self.compute_cash_effect(amount, row["type"])
                cash_balance += impact

            self.logger.info(f"Computed cash balance for '{broker_name}': {cash_balance}")

            self.update_cash_account(broker_account_id, cash_balance)
            self.update_snapshot_cash(broker_account_id, latest_date, cash_balance)

            self.logger.info(f"Updated cash_account & portfolio_snapshot cash for '{broker_name}'")

        self.logger.info("=== Cash Balance Recalculation Completed ===")


# ---------------------------------------------------------
# MAIN
# ---------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(description="CashCue Cash Balance Recalculator (OOD)")
    parser.add_argument("--dry-run", action="store_true", help="Simulate execution without DB writes")
    args = parser.parse_args()

    config = ConfigManager("/etc/cashcue/cashcue.conf")
    logger = LoggerManager(config.get("LOG_FILE", "/var/log/cashcue/cash_recalc.log"))
    dry_run = args.dry_run or config.get("DRY_RUN", "false").lower() == "true"

    worker = CashBalanceRecalculator(config, logger, dry_run)
    worker.run()


if __name__ == "__main__":
    main()
