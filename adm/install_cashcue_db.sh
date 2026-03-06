#!/usr/bin/env bash
# =====================================================
# CashCue Database Initialization Script
# Author: Pierre
#
# Description:
#   Initializes the CashCue database by:
#     - Loading configuration
#     - Creating database and user
#     - Importing schema if DB is empty
#     - Creating the initial SuperAdmin account
#
# Compatibility:
#     - Native VM deployment (DB_HOST=localhost)
#     - Docker container deployment (DB_HOST=cashcue_db)
# =====================================================

set -euo pipefail

# --------------------------------------------------------------
# Load a dotenv-style config file safely
# Ignores comments and preserves existing environment variables
# Usage: load_dotenv /path/to/cashcue.conf
# --------------------------------------------------------------
load_dotenv() {
    local dotenv_file="$1"
    if [ ! -f "$dotenv_file" ]; then
        echo "[WARN] Dotenv file not found: $dotenv_file"
        return 1
    fi

    while IFS= read -r line || [ -n "$line" ]; do
        # Remove leading/trailing whitespace
        line="$(echo "$line" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
        # Skip empty lines and full-line comments
        [[ -z "$line" || "$line" =~ ^# ]] && continue
        # Remove inline comments after # (unless inside quotes)
        # This sed handles unquoted # for comments
        line="$(echo "$line" | sed -E 's/([^=]+)=([^#]*).*/\1=\2/')"
        # Split key and value
        key="${line%%=*}"
        value="${line#*=}"
        key="$(echo "$key" | tr -d '[:space:]')"
        value="$(echo "$value" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
        # Only export if not already set in environment
        if [ -z "${!key:-}" ]; then
            export "$key=$value"
        fi
    done < "$dotenv_file"
}

# --------------------------------------------------------------
# Load configuration from /etc/cashcue/cashcue.conf
# --------------------------------------------------------------
CONFIG_FILE="/etc/cashcue/cashcue.conf"

if [ ! -f "$CONFIG_FILE" ]; then
    echo "[ERROR] Configuration file not found: $CONFIG_FILE"
    exit 1
fi

echo "[INFO] Loading configuration from $CONFIG_FILE"
load_dotenv "$CONFIG_FILE"

# --------------------------------------------------------------
# Resolve script directory for reliable schema path
# --------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCHEMA_FILE="${SCRIPT_DIR}/schemaCashCueBD.sql"

# --------------------------------------------------------------
# Environment variable fallback (Docker or VM)
# --------------------------------------------------------------
DB_HOST="${DB_HOST:-localhost}"  # localhost on VM, 'cashcue_db' in Docker
DB_NAME="${DB_NAME:-cashcue}"
DB_USER="${DB_USER:-cashcue_user}"
DB_PASS="${DB_PASS:-}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-}"

export DB_HOST DB_NAME DB_USER DB_PASS DB_ROOT_PASSWORD

# --------------------------------------------------------------
# Validate required configuration variables
# --------------------------------------------------------------
REQUIRED_VARS=(DB_HOST DB_NAME DB_USER DB_PASS DB_ROOT_PASSWORD
               CASHCUE_SUPERADMIN_USERNAME CASHCUE_SUPERADMIN_EMAIL CASHCUE_SUPERADMIN_PASSWORD)

for VAR in "${REQUIRED_VARS[@]}"; do
    if [ -z "${!VAR:-}" ]; then
        echo "[ERROR] Missing environment variable: $VAR"
        exit 1
    fi
done

# --------------------------------------------------------------
# Wait for MariaDB to be ready (Docker-safe)
# --------------------------------------------------------------
echo "[INFO] Waiting for MariaDB at $DB_HOST..."
until mariadb -h "$DB_HOST" -u root -p"$DB_ROOT_PASSWORD" -e "SELECT 1;" >/dev/null 2>&1; do
    echo -n "."
    sleep 2
done
echo "[INFO] Database is ready!"

MARIADB_ROOT="mariadb -h $DB_HOST -u root -p$DB_ROOT_PASSWORD"

# --------------------------------------------------------------
# Create database if it does not exist
# --------------------------------------------------------------
echo "[INFO] Creating database $DB_NAME if it does not exist..."
$MARIADB_ROOT -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# --------------------------------------------------------------
# Create user and grant privileges
# --------------------------------------------------------------
echo "[INFO] Creating user $DB_USER and granting privileges..."
$MARIADB_ROOT -e "CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS';"
$MARIADB_ROOT -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%';"
$MARIADB_ROOT -e "FLUSH PRIVILEGES;"

# --------------------------------------------------------------
# Import schema if the database is empty
# --------------------------------------------------------------
TABLE_COUNT=$(mariadb -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -sse \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';")

if [ ! -f "$SCHEMA_FILE" ]; then
    echo "[ERROR] Schema file not found: $SCHEMA_FILE"
    exit 1
fi

if [ "$TABLE_COUNT" -eq 0 ]; then
    echo "[INFO] Importing CashCue schema from $SCHEMA_FILE..."
    mariadb -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SCHEMA_FILE"
    echo "[INFO] Schema imported successfully."
else
    echo "[INFO] Database already contains tables. Skipping schema import."
fi

# SuperAdmin user creation
# Generate password hash using PHP's password_hash function
# Note: This requires PHP to be installed in the container. If not available, consider using a different hashing method or pre-hashing the password.
PASSWORD_HASH=$(php -r "echo password_hash(getenv('CASHCUE_SUPERADMIN_PASSWORD'), PASSWORD_DEFAULT);")

echo "[INFO] Creating SuperAdmin user: $CASHCUE_SUPERADMIN_USERNAME"

mariadb -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<EOF
INSERT IGNORE INTO user (username, email, password_hash, is_super_admin, is_active)
VALUES ('${CASHCUE_SUPERADMIN_USERNAME}',
        '${CASHCUE_SUPERADMIN_EMAIL}',
        '${PASSWORD_HASH}',
        1,
        1);
EOF
echo "[SUCCESS] Database initialization complete."