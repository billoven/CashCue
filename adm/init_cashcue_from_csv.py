#!/usr/bin/env python3
"""
CashCue - Portfolio Initializer Script

This script initializes a CashCue database from a portfolio CSV.
It inserts:
- Broker accounts
- Instruments
- Transactions (BUY)
- Initial realtime_price (current market price at CSV last known date)

Features:
- Uses PyMySQL
- Dry-run mode (--dry-run) prints SQL without executing
- Handles localized numbers like "1,39 €"
- Fully aligned with CashCue DB schema
"""

import argparse
import csv
import pymysql
from datetime import datetime
from decimal import Decimal, InvalidOperation

# -----------------------------
# Utility functions
# -----------------------------

def parse_decimal(value: str):
    """Convert strings like '1,39 €' or '14.96' to Decimal, or None if empty."""
    if not value or value.strip() == "":
        return None
    try:
        clean = value.replace("€", "").replace(",", ".").replace(" ", "").strip()
        return Decimal(clean)
    except (InvalidOperation, ValueError):
        print(f"[WARN] Could not parse decimal from '{value}'")
        return None

def parse_date(value: str):
    """Convert DD/MM/YYYY to date object, or None if empty."""
    if not value or value.strip() == "":
        return None
    try:
        return datetime.strptime(value.strip(), "%d/%m/%Y").date()
    except ValueError:
        print(f"[WARN] Could not parse date from '{value}'")
        return None

# -----------------------------
# Database helpers
# -----------------------------

def connect_db(args):
    """Connect to MySQL/MariaDB using PyMySQL"""
    return pymysql.connect(
        host=args.host,
        user=args.user,
        password=args.password,
        database=args.database,
        port=3306,
        autocommit=False,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor
    )

def execute_sql(cursor, sql, params=None, dry_run=False):
    """Execute SQL or print it if dry-run mode"""
    if dry_run:
        if params:
            formatted = sql
            for p in params:
                if isinstance(p, str):
                    p_fmt = f"'{p}'"
                elif p is None:
                    p_fmt = "NULL"
                else:
                    p_fmt = str(p)
                formatted = formatted.replace("%s", p_fmt, 1)
            print("SQL:", formatted)
        else:
            print("SQL:", sql)
        return None
    cursor.execute(sql, params)
    return cursor.lastrowid

# -----------------------------
# Table insert helpers
# -----------------------------

def get_or_create_account(cursor, broker_name, account_number, account_type, dry_run=False):
    """Insert broker account if not exists, return ID"""
    sql = """
        INSERT INTO broker_account (name, account_number, account_type, currency)
        VALUES (%s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
    """
    params = (broker_name, account_number, account_type, "EUR")
    execute_sql(cursor, sql, params, dry_run)
    if dry_run:
        return None
    cursor.execute("SELECT LAST_INSERT_ID() AS id")
    return cursor.fetchone()["id"]

def get_or_create_instrument(cursor, symbol, label, isin, inst_type, currency, dry_run=False):
    """Insert instrument if not exists, return ID"""
    sql = """
        INSERT INTO instrument (symbol, label, isin, type, currency)
        VALUES (%s, %s, %s, %s, %s)
        ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
    """
    params = (symbol, label, isin, inst_type, currency)
    execute_sql(cursor, sql, params, dry_run)
    if dry_run:
        return None
    cursor.execute("SELECT LAST_INSERT_ID() AS id")
    return cursor.fetchone()["id"]

def insert_transaction(cursor, broker_id, instrument_id, order_type, quantity, price, fees, trade_date, dry_run=False):
    """Insert a buy/sell transaction"""
    sql = """
        INSERT INTO order_transaction (broker_id, instrument_id, order_type, quantity, price, fees, trade_date)
        VALUES (%s, %s, %s, %s, %s, %s, %s)
    """
    params = (broker_id, instrument_id, order_type, quantity, price, fees, trade_date)
    execute_sql(cursor, sql, params, dry_run)

def insert_realtime_price(cursor, instrument_id, price, captured_at, dry_run=False):
    """Insert initial realtime price"""
    sql = """
        INSERT INTO realtime_price (instrument_id, price, captured_at)
        VALUES (%s, %s, %s)
    """
    params = (instrument_id, price, captured_at)
    execute_sql(cursor, sql, params, dry_run)

# -----------------------------
# Main processing
# -----------------------------

def process_csv(args):
    with open(args.csv, newline="", encoding="utf-8") as f:
        reader = csv.DictReader(f, delimiter=";")

        conn = None
        cursor = None
        if not args.dry_run:
            conn = connect_db(args)
            cursor = conn.cursor()

        # Keep track of inserted broker accounts to avoid duplicates
        broker_accounts = {}

        try:
            for row in reader:
                broker_name = row["broker_name"].strip()
                account_number = row["account_number"].strip()
                account_type = row["account_type"].strip() or "PEA"

                symbol = row["instrument_symbol"].strip()
                label = row["instrument_label"].strip()
                isin = row["isin"].strip()
                inst_type = row["type"].strip() or "STOCK"
                currency = row["currency"].strip() or "EUR"

                quantity = parse_decimal(row["quantity"])
                avg_buy_price = parse_decimal(row["avg_buy_price"])
                market_price = parse_decimal(row["current_market_price"])
                fees = parse_decimal(row.get("fees"))
                execution_price = parse_decimal(row.get("Execution_price"))
                buy_date = parse_date(row.get("buy_date"))

                # -----------------------------
                # Create or get broker account ID
                # -----------------------------
                account_key = (broker_name, account_number)
                if account_key not in broker_accounts:
                    account_id = get_or_create_account(cursor, broker_name, account_number, account_type, args.dry_run)
                    broker_accounts[account_key] = account_id
                else:
                    account_id = broker_accounts[account_key]

                # -----------------------------
                # Create or get instrument ID
                # -----------------------------
                instrument_id = get_or_create_instrument(cursor, symbol, label, isin, inst_type, currency, args.dry_run)

                # -----------------------------
                # Insert BUY transaction
                # -----------------------------
                if quantity and avg_buy_price and buy_date:
                    insert_transaction(cursor, account_id, instrument_id, "BUY",
                                       quantity, execution_price or avg_buy_price,
                                       fees or Decimal(0), buy_date, args.dry_run)

                # -----------------------------
                # Insert initial realtime price (CSV market price)
                # -----------------------------
                if market_price:
                    # Timestamp set to Paris market close: 2025-09-05 17:30
                    captured_at = datetime(2025, 9, 5, 17, 30)
                    insert_realtime_price(cursor, instrument_id, market_price, captured_at, args.dry_run)

            if not args.dry_run:
                conn.commit()
                print("[INFO] Database commit successful")
            else:
                print("[INFO] Dry-run completed, no changes committed")

        except Exception as e:
            if not args.dry_run and conn:
                conn.rollback()
            print(f"[ERROR] {e}")
        finally:
            if cursor:
                cursor.close()
            if conn:
                conn.close()

# -----------------------------
# Entry point
# -----------------------------

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Initialize CashCue DB from portfolio CSV")
    parser.add_argument("--csv", required=True, help="Path to portfolio CSV file")
    parser.add_argument("--host", default="localhost", help="Database host")
    parser.add_argument("--user", required=True, help="Database user")
    parser.add_argument("--password", required=True, help="Database password")
    parser.add_argument("--database", required=True, help="Database name")
    parser.add_argument("--dry-run", action="store_true", help="Print SQL without executing")

    args = parser.parse_args()
    process_csv(args)
