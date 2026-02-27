#!/bin/bash
# Install CashCue DB (Docker & normal production compatible)

CONFIG_FILE="/etc/cashcue/cashcue.conf"
SCHEMA_FILE="adm/schemaBD.sql"

# -------------------------
# Load configuration
# -------------------------
if [ ! -f "$CONFIG_FILE" ]; then
    echo "[ERROR] Config file not found: $CONFIG_FILE"
    exit 1
fi

set -a
source "$CONFIG_FILE"
set +a

# -------------------------
# Check required variables
# -------------------------
if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_HOST" ]; then
    echo "[ERROR] Missing database configuration in $CONFIG_FILE"
    exit 1
fi

if [ -z "$ROOT_DB_PASSWORD" ]; then
    echo "[ERROR] ROOT_DB_PASSWORD variable not set. Please set it before running the script."
    exit 1
fi

if [ ! -f "$SCHEMA_FILE" ]; then
    echo "[ERROR] Schema file not found: $SCHEMA_FILE"
    exit 1
fi

# -------------------------
# Set MySQL root command
# -------------------------
MYSQL_CMD="mysql -u root -p${ROOT_DB_PASSWORD}"

# -------------------------
# Create database
# -------------------------
echo "[INFO] Creating database '$DB_NAME'..."
$MYSQL_CMD -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;"

# -------------------------
# Determine user host
# -------------------------
if [ "$DB_HOST" == "127.0.0.1" ] || [ "$DB_HOST" == "localhost" ]; then
    USER_HOST="%"
else
    # LAN prefix: 192.168.17.% for normal networks
    USER_HOST=$(echo "$DB_HOST" | awk -F. '{print $1"."$2"."$3".%"}')
fi

# -------------------------
# Create user & grant privileges
# -------------------------
echo "[INFO] Creating user '$DB_USER'@'$USER_HOST'..."
$MYSQL_CMD -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'${USER_HOST}' IDENTIFIED BY '${DB_PASS}';"
$MYSQL_CMD -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'${USER_HOST}';"
$MYSQL_CMD -e "FLUSH PRIVILEGES;"

# -------------------------
# Import schema
# -------------------------
echo "[INFO] Importing schema from $SCHEMA_FILE..."
$MYSQL_CMD "$DB_NAME" < "$SCHEMA_FILE"

echo "[SUCCESS] Database '$DB_NAME' initialized and user '$DB_USER' created with access from '$USER_HOST'."