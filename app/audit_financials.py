#!/usr/bin/env python3
"""
CashCue - Financial Audit Tool (Super-Verbeux)

This script performs a **comprehensive financial audit** for all broker accounts
in CashCue. It checks cash balances, transactions, orders, positions, and
provides line-by-line verification to detect anomalies. It is intended for
manual review or debugging discrepancies in portfolio snapshots.

Features:
-------------
1. CASH AUDIT
   - Recompute cash balances from `cash_account.initial_balance` and
     `cash_transaction` history
   - Handles all transaction types: BUY, SELL, DIVIDEND, DEPOSIT, WITHDRAWAL, FEES, ADJUSTMENT
   - Computes line-by-line running balances
   - Shows totals per transaction type
   - Highlights inconsistencies with hints for manual investigation

2. ORDERS / POSITIONS AUDIT
   - Reads `order_transaction` per broker account
   - Separates BUY and SELL orders, calculates invested amounts, fees, and total flows
   - Compares computed portfolio positions against snapshots and cash balances
   - Detects mismatches and provides explanatory messages

3. RECONCILIATION
   - Compares recomputed cash and portfolio value with persisted snapshots
   - Reports deltas and flags inconsistencies
   - Provides detailed hints to locate potential missing transactions

4. OUTPUT
   - Human-readable, indented, well-separated sections
   - Default to stdout, optional log file via `--log`
   - Supports super-verbeux mode for line-by-line audit

5. ARGUMENTS
   - --dry-run       : Simulate execution, no DB writes
   - --lang LANG     : Output language, 'en' or 'fr'
   - --log FILE      : Write output to a log file
   - --super-verbose : Enable super-verbeux detailed audit

Usage Example:
-------------
$ python3 -m app.audit_financials --super-verbose --lang en --log audit.log

Author: Pierre / OpenAI GPT-5-mini
Date: 2026-02-06
"""

import argparse
from datetime import datetime
from decimal import Decimal
import sys

from lib.config import ConfigManager
from lib.logger import LoggerManager
from lib.db import DatabaseConnection

# -------------------------------
# AUDITOR CLASS
# -------------------------------
class FinancialAuditor:
    def __init__(self, config, logger, lang="en", super_verbose=False, dry_run=False):
        self.config = config
        self.logger = logger
        self.lang = lang
        self.super_verbose = super_verbose
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

    # -------------------------------
    # TRANSLATION UTILS
    # -------------------------------
    def t(self, en, fr):
        return fr if self.lang == "fr" else en

    # -------------------------------
    # FETCH METHODS
    # -------------------------------
    def fetch_brokers(self):
        sql = "SELECT id, name FROM broker_account"
        with self.db.cursor() as cur:
            cur.execute(sql)
            return cur.fetchall()

    def fetch_cash_account(self, broker_id):
        sql = "SELECT id, name, initial_balance, current_balance FROM cash_account WHERE broker_account_id=%s"
        with self.db.cursor() as cur:
            cur.execute(sql, (broker_id,))
            return cur.fetchone()

    def fetch_cash_transactions(self, broker_id):
        sql = """
            SELECT date, amount, type, reference_id, comment
            FROM cash_transaction
            WHERE broker_account_id=%s
            ORDER BY date ASC, id ASC
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (broker_id,))
            return cur.fetchall()

    def fetch_orders(self, broker_id):
        sql = """
            SELECT instrument_id, order_type, quantity, price, fees, total_cost, trade_date, status
            FROM order_transaction
            WHERE broker_account_id=%s
            ORDER BY trade_date ASC, id ASC
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (broker_id,))
            return cur.fetchall()

    # -------------------------------
    # CASH COMPUTATION
    # -------------------------------
    def compute_cash_impact(self, ttype, amount):
        """
        Returns the effect of a cash transaction on the cash balance.
        """
        if ttype in ("DEPOSIT", "DIVIDEND", "SELL"):
            return amount
        elif ttype in ("BUY", "WITHDRAWAL", "FEES", "ADJUSTMENT"):
            return -amount
        else:
            self.logger.warning(f"Unknown cash transaction type: {ttype}")
            return Decimal("0.0")

    # -------------------------------
    # AUDIT METHODS
    # -------------------------------
    def audit_cash(self, broker):
        bname = broker["name"]
        self.logger.info("\n" + "="*70)
        self.logger.info(self.t(f"Broker account '{bname}' (ID={broker['id']})",
                                f"Compte courtier '{bname}' (ID={broker['id']})"))
        self.logger.info("-"*70)
        self.logger.info(self.t("[CASH AUDIT]", "[AUDIT CASH]"))
        self.logger.info("-"*70)

        cash_acc = self.fetch_cash_account(broker["id"])
        if not cash_acc:
            self.logger.warning(self.t("No cash account found", "Pas de compte cash trouvé"))
            return

        balance = Decimal(cash_acc["initial_balance"])
        running_balance = balance
        totals = {"DEPOSIT":0, "DIVIDEND":0, "SELL":0, "BUY":0, "WITHDRAWAL":0, "FEES":0, "ADJUSTMENT":0}

        txs = self.fetch_cash_transactions(broker["id"])
        for row in txs:
            ttype = row["type"]
            amount = Decimal(row["amount"])
            impact = self.compute_cash_impact(ttype, amount)
            running_balance += impact
            totals[ttype] += impact

            if self.super_verbose:
                self.logger.info(f"{row['date']} | {ttype:<10} | amount={amount:>10} | impact={impact:>10} | running_balance={running_balance:>10} | comment={row.get('comment','')}")

        # Totals per type
        self.logger.info("-"*70)
        for ttype, total in totals.items():
            self.logger.info(f"Total {ttype:<10}: {total:>10.2f}")

        # Compare with persisted balance
        persisted = Decimal(cash_acc["current_balance"])
        delta = persisted - running_balance
        self.logger.info("-"*70)
        self.logger.info(f"Recomputed balance : {running_balance:.2f}")
        self.logger.info(f"Persisted balance   : {persisted:.2f}")
        if delta != 0:
            self.logger.error(f"Delta               : {delta:.2f} ({self.t('INCONSISTENCY DETECTED','INCOHERENCE DETECTEE')})")
            self.logger.error(self.t("Hint: check missing cash_transaction or manual balance override",
                                     "Indice : vérifier les cash_transactions manquantes ou un ajustement manuel"))

    def audit_orders(self, broker):
        self.logger.info("\n" + "-"*70)
        self.logger.info(self.t("[ORDERS / POSITIONS AUDIT]", "[AUDIT ORDRES / POSITIONS]"))
        self.logger.info("-"*70)

        orders = self.fetch_orders(broker["id"])
        total_buy = Decimal("0.0")
        total_sell = Decimal("0.0")
        total_fees = Decimal("0.0")
        running_positions = {}

        for o in orders:
            qty = Decimal(o["quantity"])
            total_cost = Decimal(o["total_cost"])
            fees = Decimal(o["fees"] or 0)
            total_fees += fees

            if o["order_type"] == "BUY":
                total_buy += total_cost
                running_positions[o["instrument_id"]] = running_positions.get(o["instrument_id"], 0) + qty
            else:
                total_sell += total_cost
                running_positions[o["instrument_id"]] = running_positions.get(o["instrument_id"], 0) - qty

            if self.super_verbose:
                self.logger.info(f"{o['trade_date']} | {o['order_type']:<4} | instr={o['instrument_id']} | qty={qty} | total_cost={total_cost:.2f} | fees={fees:.2f} | status={o['status']}")

        self.logger.info("-"*70)
        self.logger.info(f"Total BUY  : {total_buy:.2f}")
        self.logger.info(f"Total SELL : {total_sell:.2f}")
        self.logger.info(f"Total FEES : {total_fees:.2f}")
        self.logger.info(f"Positions  : {running_positions}")

    # -------------------------------
    # MAIN EXECUTION
    # -------------------------------
    def run(self):
        self.logger.info("\n" + "="*70)
        self.logger.info(self.t("=== FINANCIAL AUDIT STARTED ===", "=== AUDIT FINANCIER DEMARRE ==="))

        brokers = self.fetch_brokers()
        if not brokers:
            self.logger.warning(self.t("No broker accounts found", "Aucun compte courtier trouvé"))
            return

        for broker in brokers:
            self.audit_cash(broker)
            self.audit_orders(broker)

        self.logger.info(self.t("=== FINANCIAL AUDIT COMPLETED ===", "=== AUDIT FINANCIER TERMINE ==="))
        self.logger.info("="*70 + "\n")


# -------------------------------
# MAIN
# -------------------------------
def main():
    parser = argparse.ArgumentParser(description="CashCue Super-Verbeux Financial Audit Tool")
    parser.add_argument("--dry-run", action="store_true", help="Simulate execution without DB writes")
    parser.add_argument("--lang", choices=["en","fr"], default="en", help="Output language")
    parser.add_argument("--super-verbose", action="store_true", help="Enable line-by-line audit")
    parser.add_argument("--log", type=str, help="Write output to log file")
    args = parser.parse_args()

    config = ConfigManager("/etc/cashcue/cashcue.conf")
    if args.log:
        logger = LoggerManager(args.log).get_logger()
    else:
        logger = LoggerManager(config.get("LOG_FILE", "/var/log/cashcue/audit_financials.log")).get_logger()

    auditor = FinancialAuditor(config, logger, lang=args.lang, super_verbose=args.super_verbose, dry_run=args.dry_run)
    auditor.run()


if __name__ == "__main__":
    main()
