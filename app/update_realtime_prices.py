#!/usr/bin/env python3
"""
CashCue - Realtime Price Updater

This script fetches the latest prices for all instruments in the database
and inserts them into the `realtime_price` table. It can optionally update
daily price info (open/high/low/close).

Features:
- Reads dotenv-style config file (/etc/cashcue/cashcue.conf)
- Robust: retry logic, exception handling
- Logging
- Dry-run mode
- Multi-language compatible configuration
"""

import pymysql
import requests
from bs4 import BeautifulSoup
from datetime import datetime
import time
import logging
import argparse
import os
import sys
import re

# -----------------------------
# Helper: load dotenv-style config
# -----------------------------
def load_dotenv(path):
    config = {}
    if not os.path.exists(path):
        print(f"[ERROR] Configuration file not found: {path}")
        sys.exit(1)
    with open(path, "r") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#"):
                continue
            if "=" in line:
                key, value = line.split("=", 1)
                # Remove inline comments after '#' and strip spaces
                value = value.split("#", 1)[0].strip()
                config[key.strip()] = value
    return config

CONFIG_FILE = "/etc/cashcue/cashcue.conf"
config = load_dotenv(CONFIG_FILE)

# -----------------------------
# Load variables from config
# -----------------------------
DB_HOST = config.get("DB_HOST", "localhost")
DB_PORT = int(config.get("DB_PORT", 3306))
DB_NAME = config.get("DB_NAME")
DB_USER = config.get("DB_USER")
DB_PASS = config.get("DB_PASS")

APP_ENV = config.get("APP_ENV", "production")
APP_LOG_LEVEL = config.get("APP_LOG_LEVEL", "INFO").upper()

BOURSORAMA_URL_PATTERN = config.get(
    "BOURSORAMA_URL_PATTERN",
     "https://bourse.boursobank.com/bourse/{category}/cours/1r{symbol}/"
)

HTTP_TIMEOUT = int(config.get("HTTP_TIMEOUT", 10))
HTTP_RETRIES = int(config.get("HTTP_RETRIES", 3))
LOG_FILE = config.get("LOG_FILE", "/var/log/cashcue/realtime_price.log")
DEFAULT_CURRENCY = config.get("DEFAULT_CURRENCY", "EUR")
UPDATE_DAILY_PRICE = config.get("UPDATE_DAILY_PRICE", "false").lower() == "true"
MARKET_TIMEZONE = config.get("MARKET_TIMEZONE", "Europe/Paris")


# --- Parse command-line arguments ---
parser = argparse.ArgumentParser(description="CashCue Realtime Price Updater")
parser.add_argument(
    "--dry-run",
    action="store_true",
    help="Simulate execution without writing to the database. Overrides config DRY_RUN."
)
args = parser.parse_args()

# --- Determine dry-run mode ---
# Priority: command-line argument > config file
DRY_RUN = args.dry_run or config.get("DRY_RUN", "false").lower() == "true"

# -----------------------------
# Logging setup
# -----------------------------
logging.basicConfig(
    filename=LOG_FILE,
    level=getattr(logging, APP_LOG_LEVEL, logging.INFO),
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S"
)
console_handler = logging.StreamHandler()
console_handler.setLevel(logging.INFO)
console_handler.setFormatter(logging.Formatter("%(asctime)s [%(levelname)s] %(message)s"))
logging.getLogger().addHandler(console_handler)

# -----------------------------
# DB connection helper
# -----------------------------
def connect_db():
    return pymysql.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASS,
        database=DB_NAME,
        port=DB_PORT,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True
    )

# -----------------------------
# Fetch price from URL
# -----------------------------
def fetch_price_from_url(url):
    """
    Fetch the current stock/ETF price from the given URL using regex extraction.

    Retries in case of network issues or temporary failures.
    Returns:
        float: price in numeric form if successful
        None: if price could not be extracted
    """
    for attempt in range(HTTP_RETRIES):
        try:
            if DRY_RUN:
                logging.info(f"[DRY-RUN] url: {url}")
            response = requests.get(url, timeout=HTTP_TIMEOUT)
            if response.status_code == 200:
                html_content = response.text

                # Use the working regex to extract the numeric value
                match = re.search(
                    r'<span class="c-instrument c-instrument--last" data-ist-last>(\d+[.,]\d+)</span>',
                    html_content
                )
                if match:
                    price_str = match.group(1).replace(",", ".").strip()
                    price = float(price_str)
                    return price
                else:
                    logging.warning(f"No price found in page for URL: {url}")
                    return None
            else:
                logging.warning(f"HTTP status {response.status_code} for URL: {url}")
        except requests.RequestException as e:
            logging.warning(f"Attempt {attempt+1}/{HTTP_RETRIES} failed for URL {url}: {e}")
        # wait 1 second before retrying
        time.sleep(1)

    logging.error(f"Failed to fetch price after {HTTP_RETRIES} attempts: {url}")
    return None

# -----------------------------
# Insert price into DB
# -----------------------------
def insert_realtime_price(cursor, instrument_id, price):
    sql = "INSERT INTO realtime_price (instrument_id, price, captured_at) VALUES (%s, %s, %s)"
    captured_at = datetime.now()
    params = (instrument_id, price, captured_at)
    if DRY_RUN:
        logging.info(f"[DRY-RUN] SQL: {sql % params}")
    else:
        cursor.execute(sql, params)

# -----------------------------
# Main function
# -----------------------------
def main():
    parser = argparse.ArgumentParser(description="CashCue Realtime Price Updater")
    parser.add_argument("--dry-run", action="store_true", help="Simulate inserts without executing")
    args = parser.parse_args()

    dry_run = args.dry_run or DRY_RUN
    if dry_run:
        logging.info("=== Running in DRY-RUN mode ===")

    try:
        conn = connect_db()
        cursor = conn.cursor()

        # Fetch all instruments
        cursor.execute("SELECT id, symbol, label FROM instrument")
        instruments = cursor.fetchall()
        if not instruments:
            logging.warning("No instruments found in the database.")
            return

        for instr in instruments:
            symbol = instr["symbol"]
            instrument_id = instr["id"]
            url = BOURSORAMA_URL_PATTERN.format(category="trackers", symbol=symbol)

            price = fetch_price_from_url(url)
            if price is not None:
                logging.info(f"{symbol} ({instr['label']}): {price} EUR")
                if not dry_run:
                    insert_realtime_price(cursor, instrument_id, price)
            else:
                logging.error(f"Could not retrieve price for {symbol}")

        logging.info("=== CashCue Realtime Price Update Completed ===")

    except pymysql.MySQLError as e:
        logging.error(f"Database error: {e}")
    except Exception as e:
        logging.error(f"Unexpected error: {e}")
    finally:
        if 'cursor' in locals() and cursor:
            cursor.close()
        if 'conn' in locals() and conn:
            conn.close()

# -----------------------------
# Entry point
# -----------------------------
if __name__ == "__main__":
    main()
