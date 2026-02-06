#!/usr/bin/env python3
"""
audit_cash_balance.py
--------------------

CashCue Cash Account Audit Tool

Purpose:
- Recompute the running cash balance from cash_transaction table.
- Compare with cash_account.current_balance.
- Highlight any discrepancies (delta) and provide hints for corrections.
- Optionally, verbose mode shows every transaction line-by-line.
- Reusable: can audit all brokers or a specific broker account.
- Exportable: stdout or log file (future PDF/CSV extensions possible).
- Supports dry-run mode (no DB writes).

Usage:
  python3 -m app.audit_cash_balance [--dry-run] [--super-verbose] [--broker BROKER_ID] [--log LOG_FILE]

Options:
  --dry-run        Simulate without changing DB
  --super-verbose  Show every transaction line
  --broker         Filter on specific broker_account_id
  --log            Output to a log file
"""

import argparse
from decimal import Decimal
from lib.config import ConfigManager
from lib.logger import LoggerManager
from lib.db import DatabaseConnection


class CashAudit:
    def __init__(self, config, logger, dry_run=False, super_verbose=False, broker_filter=None):
        self.config = config
        self.logger = logger
        self.dry_run = dry_run
        self.super_verbose = super_verbose
        self.broker_filter = broker_filter

        self.db = DatabaseConnection(
            host=config.get("DB_HOST", "localhost"),
            user=config.get("DB_USER"),
            password=config.get("DB_PASS"),
            database=config.get("DB_NAME"),
            port=int(config.get("DB_PORT", 3306))
        )
        self.db.connect()

    def fetch_brokers(self):
        sql = "SELECT id, name, current_balance FROM cash_account"
        params = ()
        if self.broker_filter:
            sql += " WHERE id = %s"
            params = (self.broker_filter,)
        with self.db.cursor() as cur:
            cur.execute(sql, params)
            return cur.fetchall()

    def fetch_cash_transactions(self, broker_id):
        sql = """
            SELECT date, type, amount, comment
            FROM cash_transaction
            WHERE broker_account_id = %s
            ORDER BY date ASC
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (broker_id,))
            return cur.fetchall()

    def compute_impact(self, ttype, amount):
        """Compute cash impact per transaction type."""
        if ttype in ("DEPOSIT", "DIVIDEND", "SELL"):
            return amount
        elif ttype in ("BUY", "WITHDRAWAL"):
            return -amount
        elif ttype == "ADJUSTMENT":
            return -amount  # Reflect the adjustment
        else:
            self.logger.warning(f"Unhandled transaction type: {ttype}")
            return Decimal("0.0")

    def audit_broker(self, broker):
        broker_id = broker["id"]
        broker_name = broker["name"]
        persisted_balance = Decimal(broker["current_balance"])

        self.logger.info(f"\n--- Auditing broker '{broker_name}' (ID={broker_id}) ---")

        transactions = self.fetch_cash_transactions(broker_id)
        running_balance = Decimal("0.0")
        totals = {
            "DEPOSIT": Decimal("0.0"),
            "DIVIDEND": Decimal("0.0"),
            "SELL": Decimal("0.0"),
            "BUY": Decimal("0.0"),
            "WITHDRAWAL": Decimal("0.0"),
            "ADJUSTMENT": Decimal("0.0")
        }

        for t in transactions:
            date = t["date"]
            ttype = t["type"]
            amount = Decimal(t["amount"])
            impact = self.compute_impact(ttype, amount)
            running_balance += impact
            if ttype in totals:
                totals[ttype] += amount

            if self.super_verbose:
                self.logger.info(f"{date} | {ttype:<10} | amount={amount:10.2f} | impact={impact:10.2f} | running_balance={running_balance:10.2f} | comment={t.get('comment')}")

        delta = persisted_balance - running_balance

        # Summary
        self.logger.info("------------------------------------------------------")
        self.logger.info(f"Computed balance     : {running_balance:.2f}")
        self.logger.info(f"Persisted balance    : {persisted_balance:.2f}")
        if delta != 0:
            self.logger.error(f"Delta (INCONSISTENCY): {delta:.2f}")
            self.logger.error("Hint: check missing cash_transaction or manual balance override.")
        else:
            self.logger.info("Delta: 0.00 (OK)")

        # Totals per type
        self.logger.info("Transaction totals:")
        for k, v in totals.items():
            self.logger.info(f"  {k:<10}: {v:10.2f}")

        return running_balance, delta

    def run(self):
        brokers = self.fetch_brokers()
        if not brokers:
            self.logger.warning("No brokers found.")
            return

        self.logger.info("=== CASH AUDIT STARTED ===")
        for broker in brokers:
            self.audit_broker(broker)
        self.logger.info("=== CASH AUDIT COMPLETED ===")


def main():
    parser = argparse.ArgumentParser(description="CashCue Cash Account Audit Tool")
    parser.add_argument("--dry-run", action="store_true", help="Simulate execution without DB writes")
    parser.add_argument("--super-verbose", action="store_true", help="Show each transaction line")
    parser.add_argument("--broker", type=int, help="Filter by broker_account_id")
    parser.add_argument("--log", type=str, help="Log file output")
    args = parser.parse_args()

    config = ConfigManager("/etc/cashcue/cashcue.conf")
    logger = LoggerManager(args.log if args.log else "/var/log/cashcue/audit_cash_balance.log").get_logger()
    auditor = CashAudit(
        config=config,
        logger=logger,
        dry_run=args.dry_run,
        super_verbose=args.super_verbose,
        broker_filter=args.broker
    )
    auditor.run()


if __name__ == "__main__":
    main()
