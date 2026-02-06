#!/usr/bin/env python3
"""
CashCue - Portfolio Snapshot Updater (Full Pedagogical Version)
==============================================================

PURPOSE
-------
This script captures a DAILY FINANCIAL SNAPSHOT of each broker account.

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
- Computes realized P/L
- Aggregates dividends received
- Stores everything into portfolio_snapshot

WHAT THIS SCRIPT DOES NOT DO
----------------------------
- Does NOT recalculate cash from transactions
- Does NOT repair inconsistencies
- Does NOT update cash_account
- Does NOT mutate business data

EXECUTION
---------
- Intended to run once per day (after market close)
- Safe to re-run (idempotent via ON DUPLICATE KEY UPDATE)
- DRY-RUN supported
"""

import argparse
from datetime import date
from decimal import Decimal
from collections import defaultdict

from lib.config import ConfigManager
from lib.logger import LoggerManager
from lib.db import DatabaseConnection


class PortfolioSnapshotUpdater:
    """
    Handles the creation of daily portfolio snapshots.
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
        sql = "SELECT id, name FROM broker_account"
        with self.db.cursor() as cur:
            cur.execute(sql)
            return cur.fetchall()

    # ------------------------------------------------------------------
    # FETCH: cash balance
    # ------------------------------------------------------------------

    def fetch_cash_balance(self, broker_account_id):
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
        sql = """
            SELECT COALESCE(SUM(amount),0) AS total
            FROM cash_transaction
            WHERE broker_account_id=%s
              AND type='DIVIDEND'
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
        sql = """
            SELECT
                COALESCE(SUM(
                    CASE WHEN order_type='BUY' THEN quantity*price
                         WHEN order_type='SELL' THEN -quantity*price
                         ELSE 0
                    END
                ),0) AS invested
            FROM order_transaction
            WHERE broker_account_id=%s
              AND trade_date <= %s
        """
        with self.db.cursor() as cur:
            cur.execute(sql, (broker_account_id, self.snapshot_date))
            row = cur.fetchone()
            return Decimal(row["invested"])

    # ------------------------------------------------------------------
    # FETCH: positions, unrealized and realized P/L
    # ------------------------------------------------------------------

    def fetch_positions_and_pl(self, broker_account_id):
        """
        Returns positions, unrealized PL, realized PL
        """
        positions = defaultdict(Decimal)
        unrealized_pl = Decimal("0.00")
        realized_pl = Decimal("0.00")

        # Step 1: Fetch all orders
        sql_orders = """
            SELECT instrument_id, order_type, quantity, price, trade_date
            FROM order_transaction
            WHERE broker_account_id=%s
            ORDER BY trade_date ASC
        """
        with self.db.cursor() as cur:
            cur.execute(sql_orders, (broker_account_id,))
            rows = cur.fetchall()

        # FIFO lot tracking per instrument
        fifo_lots = defaultdict(list)

        for row in rows:
            instr = row["instrument_id"]
            qty = Decimal(row["quantity"])
            price = Decimal(row["price"])
            typ = row["order_type"]

            if typ == "BUY":
                fifo_lots[instr].append({"qty": qty, "price": price})
                positions[instr] += qty
            elif typ == "SELL":
                positions[instr] -= qty
                # Compute realized P/L
                remaining = qty
                while remaining > 0 and fifo_lots[instr]:
                    lot = fifo_lots[instr][0]
                    lot_qty = lot["qty"]
                    lot_price = lot["price"]
                    if lot_qty <= remaining:
                        realized_pl += (price - lot_price) * lot_qty
                        remaining -= lot_qty
                        fifo_lots[instr].pop(0)
                    else:
                        realized_pl += (price - lot_price) * remaining
                        lot["qty"] -= remaining
                        remaining = 0

        # Step 2: Fetch latest price for each instrument to compute unrealized PL
        for instr, net_qty in positions.items():
            if net_qty == 0:
                continue
            sql_price = """
            SELECT COALESCE(
                    (SELECT close_price FROM daily_price dp
                        WHERE dp.instrument_id=%s
                        ORDER BY dp.date DESC LIMIT 1),
                    (SELECT price FROM realtime_price rp
                        WHERE rp.instrument_id=%s
                        ORDER BY rp.captured_at DESC LIMIT 1),
                    0
                ) AS last_price
            """
            with self.db.cursor() as cur:
                cur.execute(sql_price, (instr, instr))
                row = cur.fetchone()
                market_price = Decimal(row["last_price"])
            # Average cost from remaining FIFO lots
            lots = fifo_lots.get(instr, [])
            total_cost = sum(lot["qty"]*lot["price"] for lot in lots)
            total_qty = sum(lot["qty"] for lot in lots) or 1
            avg_cost = total_cost / total_qty
            unrealized_pl += (market_price - avg_cost) * net_qty

        return positions, unrealized_pl, realized_pl

    # ------------------------------------------------------------------
    # STORE SNAPSHOT
    # ------------------------------------------------------------------

    def store_snapshot(self, broker_account_id, total_value, invested_amount,
                       unrealized_pl, realized_pl, dividends_received, cash_balance):
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
        params = (broker_account_id, self.snapshot_date, total_value,
                  invested_amount, unrealized_pl, realized_pl,
                  dividends_received, cash_balance)
        if self.dry_run:
            self.logger.info(f"[DRY-RUN] {sql} | {params}")
            return
        with self.db.cursor() as cur:
            cur.execute(sql, params)

    # ------------------------------------------------------------------
    # MAIN RUN
    # ------------------------------------------------------------------

    def run(self):
        self.logger.info(f"=== Portfolio Snapshot Update started "
                         f"(date={self.snapshot_date}, dry_run={self.dry_run}) ===")

        brokers = self.fetch_broker_accounts()
        for broker in brokers:
            bid = broker["id"]
            name = broker["name"]
            self.logger.info(f"Processing broker '{name}' (ID={bid})")

            cash_balance = self.fetch_cash_balance(bid)
            dividends = self.fetch_dividends_received(bid)
            invested = self.fetch_invested_amount(bid)
            positions, unrealized_pl, realized_pl = self.fetch_positions_and_pl(bid)
            total_value = invested + unrealized_pl

            self.store_snapshot(
                broker_account_id=bid,
                total_value=total_value,
                invested_amount=invested,
                unrealized_pl=unrealized_pl,
                realized_pl=realized_pl,
                dividends_received=dividends,
                cash_balance=cash_balance
            )

            self.logger.info(f"Snapshot stored for '{name}': "
                             f"value={total_value}, invested={invested}, "
                             f"cash={cash_balance}, unrealized_pl={unrealized_pl}, "
                             f"realized_pl={realized_pl}")

        self.logger.info("=== Portfolio Snapshot Update completed ===")


# ----------------------------------------------------------------------
# ENTRY POINT
# ----------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(
        description="CashCue Portfolio Snapshot Updater (Full Pedagogical)"
    )
    parser.add_argument("--dry-run", action="store_true",
                        help="Simulate execution without DB writes")
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
