#!/usr/bin/env bash
# =====================================================
# CashCue Docker ENTRYPOINT
# Author: Pierre
# Description:
#   Initialize database, create user, import schema if needed.
#   Waits until MariaDB is ready before executing commands.
# =====================================================

set -e

echo "===================================="
echo " CashCue Docker Test Environment"
echo "===================================="

# --- Basic environment checks ---
: "${ROOT_DB_PASSWORD:?ROOT_DB_PASSWORD not set!}"
: "${DB_USER:?DB_USER not set!}"
: "${DB_PASS:?DB_PASS not set!}"
: "${DB_NAME:?DB_NAME not set!}"
: "${DB_HOST:?DB_HOST not set!}"

# --- Function to wait for DB readiness ---
wait_for_db() {
    echo "Waiting for database $DB_HOST to be ready..."
    until mysql -h "$DB_HOST" -u root -p"$ROOT_DB_PASSWORD" -e "SELECT 1;" &> /dev/null
    do
        echo -n "."
        sleep 2
    done
    echo " Database is ready!"
}

# --- Initialize database ---
init_db() {
    echo "Initializing database $DB_NAME on $DB_HOST..."

    # Create database if not exists
    mysql -h "$DB_HOST" -u root -p"$ROOT_DB_PASSWORD" -e \
        "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

    # Create user if not exists
    mysql -h "$DB_HOST" -u root -p"$ROOT_DB_PASSWORD" -e \
        "CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS';"
    mysql -h "$DB_HOST" -u root -p"$ROOT_DB_PASSWORD" -e \
        "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%';"
    mysql -h "$DB_HOST" -u root -p"$ROOT_DB_PASSWORD" -e "FLUSH PRIVILEGES;"

    # Import schema if DB is empty
    TABLE_COUNT=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -sse \
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';")

    SCHEMA_FILE="/var/www/html/adm/schemaCashCueBD.sql"

    if [ ! -f "$SCHEMA_FILE" ]; then
        echo "Error: schema file $SCHEMA_FILE not found!"
        exit 1
    fi

    if [ "$TABLE_COUNT" -eq 0 ]; then
        echo "Importing CashCue schema..."
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SCHEMA_FILE"
        echo "Schema imported."
    else
        echo "Tables already exist, skipping schema import."
    fi
}

# --- Wait for database to be ready ---
wait_for_db

# --- Initialize database ---
init_db

# --- Execute container command ---
exec "$@"