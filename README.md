# CashCue
CashCue is a lightweight yet powerful platform designed to help individual investors manage their stock portfolios with real-time tracking, historical analysis, and broker independence.
It is tailored for French PEA (“Plan d’Épargne en Actions”) but flexible enough to support multiple accounts and asset types.

Objectives

Provide a centralized dashboard of all your stock and ETF holdings.

Automate real-time price collection from external data sources (e.g., Boursobank, Yahoo Finance).

Consolidate daily summaries (open, high, low, close, % change).
Track buy/sell orders, including broker fees and realized gains/losses.
Offer clear visibility into the current and historical value of your portfolio.
Be flexible and resilient to changes in data providers.
Remain self-hosted for privacy and control.

🗂️ Core Components

Database (MariaDB/MySQL)

 - Stores instruments, realtime prices, daily summaries, and order history.
 - Optimized for portfolio analytics and dashboard queries.
 - Scheduler (Cron + Python scripts)
 - Runs at configurable intervals (e.g., every 15 minutes).
 - Fetches prices from online sources, updates realtime & daily tables.

Web Application (Dashboard)
 - Displays current portfolio value, realtime charts, historical trends.
 - Provides insights: gains/losses, allocation, performance.
 - Configuration Management

Secure config file in /etc/cashcue/cashcue.conf.

 - Central storage of DB credentials, URLs, intervals, API keys, etc.
 - Readable by Python, PHP, or Shell scripts for cross-compatibility.

🧩 Database Overview

 - instrument → list of stocks/ETFs (symbol, label, category, data source).
 - price_realtime → short-interval prices collected by cron.
 - price_daily → OHLC summary (open, high, low, close, % change).
 - orders → buy/sell operations, including quantity, fees, execution date.

🚀 Future Enhancements

 - Multi-broker account support.
 - Portfolio allocation by sector, country, asset type.
 - Alerts (e.g., % change threshold).

Support for other asset classes (crypto, bonds, funds).

Export (CSV, PDF) for reporting.
