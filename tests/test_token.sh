#!/usr/bin/env bash
# ------------------------------
# -- Description: Test API Token Validity
# This script tests the validity of an API token by checking it against the database.
# It retrieves the token hash, queries the database for a matching user, and checks if the
# token is valid (not revoked and not expired). It also checks if the associated user has super admin privileges.
# ------------------------------
set -euo pipefail

echo "=============================================="
echo "   CASHCUE — API TOKEN TEST"
echo "=============================================="

if [ $# -ne 1 ]; then
    echo "Usage: $0 <api_token>"
    exit 1
fi

API_TOKEN="$1"

# ------------------------------
# 1️⃣ Load DB config from /etc/cashcue
# ------------------------------
CONF_FILE="/etc/cashcue/cashcue.conf"

get_conf() {
    local key="$1"
    local val
    val=$(sed -n "s/^[[:space:]]*$key[[:space:]]*=\(.*\)/\1/p" "$CONF_FILE" \
            | sed -e 's/^[[:space:]]*//' -e 's/^["'\'']//' -e 's/["'\'']$//')
    echo "$val"
}

DB_HOST=$(get_conf "DB_HOST")
DB_PORT=$(get_conf "DB_PORT")
DB_USER=$(get_conf "DB_USER")
DB_PASS=$(get_conf "DB_PASS")
DB_NAME=$(get_conf "DB_NAME")

MYSQL="mysql -h $DB_HOST -P $DB_PORT -u $DB_USER -p$DB_PASS $DB_NAME -N -B"

echo "Using DB: $DB_USER@$DB_HOST:$DB_PORT/$DB_NAME"
echo ""

# ------------------------------
# 2️⃣ Hash the token
# ------------------------------
TOKEN_HASH=$(echo -n "$API_TOKEN" | sha256sum | awk '{print $1}')

# ------------------------------
# 3️⃣ Query DB to find the associated user
# ------------------------------
read -r USER_ID USER_EMAIL IS_SUPER_ADMIN <<< $($MYSQL <<SQL
SELECT u.id, u.username, u.is_super_admin
FROM user_api_token uat
INNER JOIN user u ON u.id = uat.user_id
WHERE uat.token_hash = '$TOKEN_HASH'
  AND uat.is_revoked = 0
  AND (uat.expires_at IS NULL OR uat.expires_at > NOW())
LIMIT 1;
SQL
)

# ------------------------------
# 4️⃣ Check results
# ------------------------------
if [ -z "$USER_ID" ]; then
    echo "❌ Token invalid or expired"
    exit 1
fi

SUPER_ADMIN_STATUS="No"
if [ "$IS_SUPER_ADMIN" -eq 1 ]; then
    SUPER_ADMIN_STATUS="Yes"
fi

echo "✅ Token is valid!"
echo "User ID: $USER_ID"
echo "Username: $USER_EMAIL"
echo "SUPER_ADMIN: $SUPER_ADMIN_STATUS"