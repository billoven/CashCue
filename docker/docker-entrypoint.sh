#!/usr/bin/env bash
# =====================================================
# CashCue Docker ENTRYPOINT
# Author: Pierre
#
# Responsibilities:
#   - Wait for DB
#   - Create web root symlink if missing
#   - Delegate to container CMD (Apache)
# =====================================================

set -euo pipefail


echo "===================================="
echo " CashCue Docker Entrypoint"
echo "===================================="

# Load configuration from /data/cashcue/conf/cashcue.conf
CONF_FILE="/data/cashcue/conf/cashcue.conf"

if [ -f "$CONF_FILE" ]; then
    echo "[INFO] Loading configuration from $CONF_FILE"
    set -a
    source "$CONF_FILE"
    set +a
else
    echo "[ERROR] Configuration file not found: $CONF_FILE"
    exit 1
fi
# --- Validate essential environment variables ---
: "${DB_HOST:?DB_HOST not set}"
: "${DB_USER:?DB_USER not set}"
: "${DB_PASS:?DB_PASS not set}"
: "${DB_NAME:?DB_NAME not set}"

# -----------------------------------------------------
# Wait until database becomes available
# -----------------------------------------------------
wait_for_db() {
    echo "[INFO] Waiting for database at $DB_HOST..."

    until mariadb -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "SELECT 1;" &>/dev/null; do
        echo "[INFO] Database not ready yet..."
        sleep 2
    done

    echo "[INFO] Database connection OK."
}

wait_for_db

# -----------------------------------------------------
# Ensure /var/www/html exists
# -----------------------------------------------------
mkdir -p /var/www/html

# -----------------------------------------------------
# Create symlink if missing
# -----------------------------------------------------
if [ ! -L /var/www/html/cashcue ]; then
    echo "[INFO] Creating symlink /var/www/html/cashcue -> /data/cashcue/web"
    ln -s /data/cashcue/web /var/www/html/cashcue
else
    echo "[INFO] Symlink already exists"
fi

# -----------------------------------------------------
# Start container CMD (Apache)
# -----------------------------------------------------
echo "[INFO] Starting Apache..."
exec "$@"