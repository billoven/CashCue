#!/bin/bash
# ===========================================
# CashCue - Database & User Initialization
# ===========================================

CONF_FILE="/etc/cashcue/cashcue.conf"

# Load config
if [ ! -f "$CONF_FILE" ]; then
  echo "❌ Config file $CONF_FILE not found!"
  exit 1
fi

# Export vars
set -a
source "$CONF_FILE"
set +a

# Create database, user, and tables
mysql -u admin -p <<EOF
-- Create database
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER IF NOT EXISTS '${DB_USER}'@'${DB_HOST}' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'${DB_HOST}';
FLUSH PRIVILEGES;

-- Use DB
USE \`${DB_NAME}\`;

-- =========================
-- Table: broker_account
-- =========================
CREATE TABLE IF NOT EXISTS broker_account (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    account_number VARCHAR(50) UNIQUE,
    currency CHAR(3) DEFAULT 'EUR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- Table: instrument
-- =========================
CREATE TABLE IF NOT EXISTS instrument (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(20) NOT NULL UNIQUE,
    label VARCHAR(255) NOT NULL,
    isin VARCHAR(20) UNIQUE,
    type ENUM('STOCK','ETF','BOND','FUND','OTHER') DEFAULT 'STOCK',
    currency CHAR(3) DEFAULT 'EUR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- Table: realtime_price
-- =========================
CREATE TABLE IF NOT EXISTS realtime_price (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    instrument_id INT NOT NULL,
    price DECIMAL(12,4) NOT NULL,
    currency CHAR(3) DEFAULT 'EUR',
    captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instrument_id) REFERENCES instrument(id) ON DELETE CASCADE
);

-- =========================
-- Table: daily_price
-- =========================
CREATE TABLE IF NOT EXISTS daily_price (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    instrument_id INT NOT NULL,
    date DATE NOT NULL,
    open_price DECIMAL(12,4),
    high_price DECIMAL(12,4),
    low_price DECIMAL(12,4),
    close_price DECIMAL(12,4),
    volume BIGINT,
    pct_change DECIMAL(6,2),
    UNIQUE (instrument_id, date),
    FOREIGN KEY (instrument_id) REFERENCES instrument(id) ON DELETE CASCADE
);

-- =========================
-- Table: order_transaction
-- =========================
CREATE TABLE IF NOT EXISTS order_transaction (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    broker_id INT NOT NULL,
    instrument_id INT NOT NULL,
    order_type ENUM('BUY','SELL') NOT NULL,
    quantity DECIMAL(12,4) NOT NULL,
    price DECIMAL(12,4) NOT NULL,
    fees DECIMAL(12,4) DEFAULT 0.0,
    total_cost DECIMAL(14,2) GENERATED ALWAYS AS (quantity * price + fees) STORED,
    trade_date DATE NOT NULL,
    settled BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (broker_id) REFERENCES broker_account(id) ON DELETE CASCADE,
    FOREIGN KEY (instrument_id) REFERENCES instrument(id) ON DELETE CASCADE
);

-- =========================
-- Table: dividend
-- =========================
CREATE TABLE IF NOT EXISTS dividend (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    broker_id INT NOT NULL,
    instrument_id INT NOT NULL,
    amount DECIMAL(12,4) NOT NULL,           -- Net dividend received
    gross_amount DECIMAL(12,4),              -- Optional: gross dividend before taxes
    currency CHAR(3) DEFAULT 'EUR',
    payment_date DATE NOT NULL,
    taxes_withheld DECIMAL(12,4) DEFAULT 0.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (broker_id) REFERENCES broker_account(id) ON DELETE CASCADE,
    FOREIGN KEY (instrument_id) REFERENCES instrument(id) ON DELETE CASCADE
);

-- =========================
-- Table: portfolio_snapshot
-- =========================
CREATE TABLE IF NOT EXISTS portfolio_snapshot (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    broker_id INT NOT NULL,
    date DATE NOT NULL,
    total_value DECIMAL(14,2) NOT NULL,       -- Portfolio market value at snapshot
    invested_amount DECIMAL(14,2) NOT NULL,   -- Cumulative invested capital
    unrealized_pl DECIMAL(14,2) DEFAULT 0.0,  -- Unrealized Profit/Loss
    realized_pl DECIMAL(14,2) DEFAULT 0.0,    -- Realized Profit/Loss
    dividends_received DECIMAL(14,2) DEFAULT 0.0,
    cash_balance DECIMAL(14,2) DEFAULT 0.0,
    UNIQUE (broker_id, date),
    FOREIGN KEY (broker_id) REFERENCES broker_account(id) ON DELETE CASCADE
);

EOF

echo "Database ${DB_NAME} and tables created successfully with user ${DB_USER}."