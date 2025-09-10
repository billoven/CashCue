#!/usr/bin/env python3
"""
CashCue - Realtime Price Updater (OOP Refactored)

Fetches the latest prices for all instruments and updates `realtime_price` table.
Also captures "capital échangé" percentage if available.

Uses:
- LoggerManager (centralized logging)
- DatabaseConnection (robust DB access)
- ConfigManager (dotenv-style configuration)
"""

import argparse
import re
import time
from datetime import datetime

import requests
from bs4 import BeautifulSoup

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

BOURSORAMA_URL_PATTERN = config.get(
    "BOURSORAMA_URL_PATTERN",
    "https://bourse.boursobank.com/bourse/{category}/cours/1r{symbol}/"
)

HTTP_TIMEOUT = int(config.get("HTTP_TIMEOUT", 10))
HTTP_RETRIES = int(config.get("HTTP_RETRIES", 3))
LOG_FILE = config.get("LOG_FILE", "/var/log/cashcue/realtime_price.log")
APP_LOG_LEVEL = config.get("APP_LOG_LEVEL", "INFO").upper()

DEFAULT_CURRENCY = config.get("DEFAULT_CURRENCY", "EUR")

# -----------------------------
# Command-line args
# -----------------------------
parser = argparse.ArgumentParser(description="CashCue Realtime Price Updater")
parser.add_argument(
    "--dry-run",
    action="store_true",
    help="Simulate execution without writing to the database. Overrides config DRY_RUN."
)
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
# Helper functions
# -----------------------------
def fetch_price_from_html(html_content):
    """
    Extract numeric price from HTML using regex.
    Returns float or None if not found.
    """
    match = re.search(
        r'<span class="c-instrument c-instrument--last" data-ist-last>(\d+[.,]\d+)</span>.*<span class="c-faceplate__price-currency"> EUR</span>',
        html_content
    )
    if match:
        return float(match.group(1).replace(",", ".").strip())
    return None


def fetch_capital_exchanged_from_html(html_content):
    """
    Extract 'capital échangé' percentage from HTML.
    Returns float (0.02 for 0.02%) or None if not found.
    """
    if not html_content:
        return None

    # Primary regex search
    pattern = re.compile(
        r'capital\s+échang(?:é|e)\b.*?<p[^>]*class=["\'][^"\']*c-list-info__value[^"\']*["\'][^>]*>\s*([\d\.,]+)\s*%',
        re.IGNORECASE | re.DOTALL
    )
    m = pattern.search(html_content)
    if m:
        try:
            return float(m.group(1).replace(",", ".").strip())
        except ValueError:
            logger.warning("Failed converting capital exchanged to float: '%s'", m.group(1))
            return None

    # Fallback: parse all <p class="c-list-info__value"> for percentage
    soup = BeautifulSoup(html_content, "html.parser")
    for p in soup.find_all("p", class_=lambda c: c and "c-list-info__value" in c):
        txt = p.get_text()
        m2 = re.search(r'([\d\.,]+)\s*%', txt)
        if m2:
            try:
                return float(m2.group(1).replace(",", ".").strip())
            except ValueError:
                logger.warning("Fallback failed converting percent '%s'", m2.group(1))
                return None
    return None


def insert_realtime_price(cursor, instrument_id, price, capital_exchanged):
    """
    Insert a new price record into the database.
    """
    sql = """
        INSERT INTO realtime_price (instrument_id, price, captured_at, capital_exchanged_percent)
        VALUES (%s, %s, %s, %s)
    """
    params = (instrument_id, price, datetime.now(), capital_exchanged)
    if DRY_RUN:
        logger.info(f"[DRY-RUN] SQL: {sql.strip()} with {params}")
    else:
        cursor.execute(sql, params)

# -----------------------------
# Main updater class
# -----------------------------
class RealtimePriceUpdater:
    def __init__(self, db, logger):
        self.db = db
        self.logger = logger

    def run(self):
        try:
            cursor = self.db.cursor()
            cursor.execute("SELECT id, symbol, label FROM instrument")
            instruments = cursor.fetchall()
            if not instruments:
                self.logger.warning("No instruments found in DB")
                return

            for instr in instruments:
                self.update_instrument(instr, cursor)

        except Exception as e:
            self.logger.error("Unexpected error: %s", e)
        finally:
            self.db.close()
            self.logger.info("=== Realtime Price Update Completed ===")

    def update_instrument(self, instr, cursor):
        symbol = instr["symbol"]
        instrument_id = instr["id"]
        url = BOURSORAMA_URL_PATTERN.format(category="trackers", symbol=symbol)

        for attempt in range(HTTP_RETRIES):
            try:
                if DRY_RUN:
                    self.logger.info(f"[DRY-RUN] Fetching URL: {url}")
                response = requests.get(url, timeout=HTTP_TIMEOUT)
                if response.status_code != 200:
                    self.logger.warning(f"HTTP {response.status_code} for {url}")
                    time.sleep(1)
                    continue

                html_content = response.text
                price = fetch_price_from_html(html_content)
                if price is None:
                    self.logger.error(f"No price found for {symbol}")
                    break  # stop retries if price not found

                capital_exchanged = fetch_capital_exchanged_from_html(html_content)
                self.logger.info(f"{symbol} ({instr['label']}): {price} EUR, capital_exchanged={capital_exchanged}")

                if not DRY_RUN:
                    insert_realtime_price(cursor, instrument_id, price, capital_exchanged)

                break  # successful fetch, exit retry loop

            except requests.RequestException as e:
                self.logger.warning(f"Attempt {attempt+1}/{HTTP_RETRIES} failed for {symbol}: {e}")
                time.sleep(1)
        else:
            self.logger.error(f"Failed to fetch {symbol} after {HTTP_RETRIES} attempts")


# -----------------------------
# Entry point
# -----------------------------
if __name__ == "__main__":
    updater = RealtimePriceUpdater(db, logger)
    updater.run()
