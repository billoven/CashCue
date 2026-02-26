#!/usr/bin/env bash
set -euo pipefail

echo "=============================================="
echo "   CASH ACCOUNT — END-TO-END FUNCTIONAL TEST"
echo "=============================================="

# ------------------------------
# 1. Load DB config from /etc/cashcue
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
# 2. Create test broker_account
# ------------------------------

echo "[TEST] Creating test broker_account..."

$MYSQL <<EOF
DELETE FROM broker_account WHERE name = 'TEST_CASH';
EOF

$MYSQL <<EOF
INSERT INTO broker_account (name, account_type, currency, has_cash_account)
VALUES ('TEST_CASH', 'PEA', 'EUR', 1);
EOF

BROKER_ACCOUNT_ID=$($MYSQL -e "SELECT id FROM broker_account WHERE name='TEST_CASH' LIMIT 1;")
echo "  -> broker_account_id = $BROKER_ACCOUNT_ID"

# ------------------------------
# 3. Create cash_account
# ------------------------------

echo "[TEST] Creating cash_account..."

$MYSQL <<EOF
DELETE FROM cash_account WHERE broker_account_id = $BROKER_ACCOUNT_ID;
EOF

$MYSQL <<EOF
INSERT INTO cash_account (broker_account_id, name, initial_balance, current_balance)
VALUES ($BROKER_ACCOUNT_ID, 'Cash TEST', 0.00, 0.00);
EOF

echo "  -> cash_account created"

# ------------------------------
# 4. Clean existing transactions
# ------------------------------

echo "[TEST] Cleaning transactions..."

$MYSQL <<EOF
DELETE FROM cash_transaction WHERE broker_account_id = $BROKER_ACCOUNT_ID;
DELETE FROM order_transaction WHERE broker_account_id = $BROKER_ACCOUNT_ID;
EOF

echo "  -> OK"

# ------------------------------
# 5. Add BUY order (100 @ 10€)
# ------------------------------

echo "[TEST] Calling addOrder.php (BUY 100x10) ..."

ORDER1=$(curl -s -X POST http://localhost/cashcue/api/addOrder.php \
    -H "Content-Type: application/json" \
    -d "{
        \"broker_account_id\": $BROKER_ACCOUNT_ID,
        \"instrument_id\": 1,
        \"order_type\": \"BUY\",
        \"quantity\": 100,
        \"price\": 10.0,
        \"fees\": 2.0,
        \"trade_date\": \"2025-01-05\"
    }")

echo "  -> Response: $ORDER1"
ORDER1_ID=$(echo "$ORDER1" | sed -n 's/.*"order_id":[ ]*\([0-9]*\).*/\1/p')

echo "  -> order_id = $ORDER1_ID"

BAL1=$($MYSQL -e "SELECT current_balance FROM cash_account WHERE broker_account_id=$BROKER_ACCOUNT_ID;")
echo "  -> cash balance after BUY = $BAL1 (expected: -1002.00)"

# ------------------------------
# 6. Add SELL order (50 @ 20€)
# ------------------------------

echo "[TEST] Calling addOrder.php (SELL 50x20) ..."

ORDER2=$(curl -s -X POST http://localhost/cashcue/api/addOrder.php \
    -H "Content-Type: application/json" \
    -d "{
        \"broker_account_id\": $BROKER_ACCOUNT_ID,
        \"instrument_id\": 1,
        \"order_type\": \"SELL\",
        \"quantity\": 50,
        \"price\": 20.0,
        \"fees\": 2.0,
        \"trade_date\": \"2025-01-06\"
    }")

echo "  -> Response: $ORDER2"
ORDER2_ID=$(echo "$ORDER2" | sed -n 's/.*"order_id":[ ]*\([0-9]*\).*/\1/p')

BAL2=$($MYSQL -e "SELECT current_balance FROM cash_account WHERE broker_account_id=$BROKER_ACCOUNT_ID;")
echo "  -> cash balance after SELL = $BAL2 (expected: -1002 + 998 = -4.00)"

# ------------------------------
# 7. Update order 1 (BUY 100@10 → 200@8)
# ------------------------------

echo "[TEST] Updating first order (now BUY 200x8 + fees 1€) ..."

UPDATE1=$(curl -s -X POST http://localhost/cashcue/api/updateOrder.php \
    -H "Content-Type: application/json" \
    -d "{
        \"id\": $ORDER1_ID,
        \"quantity\": 200,
        \"price\": 8.0,
        \"fees\": 1.0
    }")

echo "  -> Response: $UPDATE1"

BAL3=$($MYSQL -e "SELECT current_balance FROM cash_account WHERE broker_account_id=$BROKER_ACCOUNT_ID;")
echo "  -> cash balance after UPDATE = $BAL3 (expected: -(200*8+1) + 998 = -1601 + 998 = -603.00)"

# ------------------------------
# 8. Delete SELL order
# ------------------------------

echo "[TEST] Deleting SELL order ..."

DEL2=$(curl -s "http://localhost/cashcue/api/deleteOrder.php?id=$ORDER2_ID")
echo "  -> Response: $DEL2"

BAL4=$($MYSQL -e "SELECT current_balance FROM cash_account WHERE broker_account_id=$BROKER_ACCOUNT_ID;")
echo "  -> cash balance after DELETE = $BAL4 (expected: -1601.00)"

# ------------------------------
# 9. Final consistency check
# ------------------------------

SUM=$($MYSQL -e "SELECT COALESCE(SUM(amount),0) FROM cash_transaction WHERE broker_account_id=$BROKER_ACCOUNT_ID;")

echo ""
echo "[FINAL CHECK] SUM(cash_transaction.amount) = $SUM"
echo "[FINAL CHECK] cash_account.current_balance = $BAL4"

if [[ "$SUM" == "$BAL4" ]]; then
    echo "✔ SUCCESS: balances match and cash_account is consistent."
else
    echo "✘ ERROR: balances do not match"
    exit 1
fi

echo ""
echo "============================="
echo " ALL CASH TESTS PASSED ✓"
echo "============================="

