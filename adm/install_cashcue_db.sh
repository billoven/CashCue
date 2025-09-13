#!/bin/bash
# Install CashCue DB in production

CONFIG_FILE="/etc/cashcue/cashcue.conf"

if [ ! -f "$CONFIG_FILE" ]; then
  echo "[ERROR] Config file not found: $CONFIG_FILE"
  exit 1
fi

# Load config (must be clean KEY=VALUE dotenv format)
set -a
source "$CONFIG_FILE"
set +a

if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_HOST" ]; then
  echo "[ERROR] Missing database configuration in $CONFIG_FILE"
  exit 1
fi

echo "[INFO] Creating database '$DB_NAME'..."
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;"

# Derive LAN mask from DB_HOST (e.g. 192.168.17.10 → 192.168.17.%)
LAN_PREFIX=$(echo "$DB_HOST" | awk -F. '{print $1"."$2"."$3".%"}')

echo "[INFO] Creating user '$DB_USER' with access from '$LAN_PREFIX'..."
mysql -u root -p -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'${LAN_PREFIX}' IDENTIFIED BY '${DB_PASS}';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'${LAN_PREFIX}';"
mysql -u root -p -e "FLUSH PRIVILEGES;"

echo "[INFO] Importing schema from adm/schema.sql..."
mysql -u root -p "$DB_NAME" < adm/schema.sql

echo "[SUCCESS] Database '$DB_NAME' initialized and user '$DB_USER' created with LAN access."
