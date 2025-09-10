#!/usr/bin/env python3
"""
CashCue - Daily Price Updater (OOP Refactored)

Calculates OHLC prices and percent change per instrument from `realtime_price` table
and inserts/updates `daily_price` table.

Rules:
- Only one record per instrument_id & date.
- Last execution of the day overrides previous runs.
- pct_change = (close - open) / open * 100
- volume is not updated for now.
"""

import argparse
from datetime import date
from lib.config import ConfigManager
from lib.db import DatabaseConnection
from lib.logger import LoggerManager

# -----------------------------
# Load configuration
# -----------------------------
config = ConfigManager("/etc/cashcue/cashcue.conf")

DB_HOST = config.get("DB_HOST", "localhost")
DB_PORT = int(config.get("DB_PORT", 3306))
DB_NAME = config.get("DB_NAME")
DB_USER = config.get("DB_USER")
DB_PASS = config.get("DB_PASS")

LOG_FILE = config.get("LOG_FILE", "/var/log/cashcue/daily_price.log")
APP_LOG_LEVEL = config.get("APP_LOG_LEVEL", "INFO").upper()

# -----------------------------
# Command-line args
# -----------------------------
parser = argparse.ArgumentParser(description="CashCue Daily Price Updater")
parser.add_argument("--dry-run", action="store_true", help="Simulate DB writes")
args = parser.parse_args()
DRY_RUN = args.dry_run or config.get("DRY_RUN", "false").lower() == "true"

# -----------------------------
# Logging
# -----------------------------
logger_manager = LoggerManager(log_file=LOG_FILE, level=APP_LOG_LEVEL)
logger = logger_manager.get_logger()

if DRY_RUN:
    logger.info("=== Running in DRY-RUN mode ===")

# -----------------------------
# DB Connection
# -----------------------------
db = DatabaseConnection(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT)

# -----------------------------
# Daily Price Updater Class
# -----------------------------
class DailyPriceUpdater:
    def __init__(self, db, logger):
        self.db = db
        self.logger = logger

    def run(self, target_date):
        try:
            cursor = self.db.cursor()
            daily_data = self.compute_daily_prices(cursor, target_date)
            if not daily_data:
                self.logger.info("No data to insert.")
                return

            for inst_id, data in daily_data.items():
                self.logger.info(
                    f"Instrument {inst_id}: O={data['open']}, H={data['high']}, "
                    f"L={data['low']}, C={data['close']}, Δ={data['pct_change']:.2f}%"
                )
                self.upsert_daily_price(cursor, inst_id, target_date, data)

            self.logger.info("=== CashCue Daily Price Update Completed ===")

        except Exception as e:
            self.logger.error("Unexpected error: %s", e)
        finally:
            self.db.close()

    def compute_daily_prices(self, cursor, target_date):
        """
        Calculate OHLC and pct_change from realtime_price for the given date.
        """
        sql = """
            SELECT instrument_id, price, captured_at
            FROM realtime_price
            WHERE DATE(captured_at) = %s
            ORDER BY instrument_id, captured_at
        """
        cursor.execute(sql, (target_date,))
        rows = cursor.fetchall()

        if not rows:
            self.logger.warning(f"No realtime_price data for {target_date}")
            return {}

        daily_data = {}
        for row in rows:
            inst_id = row["instrument_id"]
            price = float(row["price"])

            if inst_id not in daily_data:
                daily_data[inst_id] = {"open": price, "close": price, "high": price, "low": price, "pct_change": 0.0}
            else:
                data = daily_data[inst_id]
                data["close"] = price
                data["high"] = max(data["high"], price)
                data["low"] = min(data["low"], price)

        # Calculate pct_change
        for inst_id, data in daily_data.items():
            open_price = data["open"]
            close_price = data["close"]
            data["pct_change"] = ((close_price - open_price) / open_price * 100) if open_price != 0 else 0.0

        return daily_data

    def upsert_daily_price(self, cursor, inst_id, target_date, data):
        sql = """
            INSERT INTO daily_price
            (instrument_id, date, open_price, high_price, low_price, close_price, pct_change)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                open_price = VALUES(open_price),
                high_price = VALUES(high_price),
                low_price = VALUES(low_price),
                close_price = VALUES(close_price),
                pct_change = VALUES(pct_change)
        """
        params = (
            inst_id,
            target_date,
            round(data["open"], 4),
            round(data["high"], 4),
            round(data["low"], 4),
            round(data["close"], 4),
            round(data["pct_change"], 2),
        )
        if DRY_RUN:
            self.logger.info(f"[DRY-RUN] SQL: {sql.strip()} with {params}")
        else:
            cursor.execute(sql, params)

# -----------------------------
# Entry point
# -----------------------------
if __name__ == "__main__":
    updater = DailyPriceUpdater(db, logger)
    updater.run(date.today())
