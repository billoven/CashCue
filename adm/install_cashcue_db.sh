#!/usr/bin/env bash
# =====================================================
# CashCue Database Initialization Script
# Author: Pierre
# Description:
#   Initializes the CashCue database by creating the database,
#   user, and importing the schema. Also creates a super admin user for being able to access to
#   the application after first launch and creates other users if needed.
# =====================================================

set -euo pipefail

SCHEMA_FILE="adm/schemaCashCueBD.sql"

# --------------------------------------------------------------
# Validate required environment variables
# --------------------------------------------------------------
REQUIRED_VARS=(
    DB_NAME DB_USER DB_PASS DB_HOST ROOT_DB_PASSWORD
    CASHCUE_SUPERADMIN_USERNAME
    CASHCUE_SUPERADMIN_EMAIL
    CASHCUE_SUPERADMIN_PASSWORD
)

# Check if all required environment variables are set
for VAR in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!VAR:-}" ]; then
        echo "[ERROR] Missing environment variable: $VAR"
        exit 1
    fi
done

# Check if schema file exists
if [ ! -f "$SCHEMA_FILE" ]; then
    echo "[ERROR] Schema file not found: $SCHEMA_FILE"
    exit 1
fi

# --------------------------------------------------------------
# Wait for MariaDB (Docker-safe)
# --------------------------------------------------------------
echo "[INFO] Waiting for MariaDB at ${DB_HOST}..."
until mysql -h "$DB_HOST" -u root -p"$ROOT_DB_PASSWORD" -e "SELECT 1;" >/dev/null 2>&1; do
    sleep 2
done

MYSQL_ROOT="mysql -h $DB_HOST -u root -p${ROOT_DB_PASSWORD}"

# --------------------------------------------------------------
# Create database
# --------------------------------------------------------------
$MYSQL_ROOT -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;"

# --------------------------------------------------------------
# Create user and grant privileges
# --------------------------------------------------------------
$MYSQL_ROOT -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';"
$MYSQL_ROOT -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';"
$MYSQL_ROOT -e "FLUSH PRIVILEGES;"

# --------------------------------------------------------------
# Import schema
# --------------------------------------------------------------
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SCHEMA_FILE"

# --------------------------------------------------------------
# SuperAdmin cashcue first user creation
# --------------------------------------------------------------
PASSWORD_HASH=$(printf "%s" "$CASHCUE_SUPERADMIN_PASSWORD" | sha256sum | awk '{print $1}')

mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
INSERT INTO user (username, email, password_hash, is_super_admin, is_active)
SELECT * FROM (
    SELECT
        '${CASHCUE_SUPERADMIN_USERNAME}',
        '${CASHCUE_SUPERADMIN_EMAIL}',
        '${PASSWORD_HASH}',
        1,
        1
) AS tmp
WHERE NOT EXISTS (
    SELECT 1 FROM user WHERE username='${CASHCUE_SUPERADMIN_USERNAME}'
) LIMIT 1;
EOF

echo "[SUCCESS] Database initialized."