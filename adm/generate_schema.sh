#!/bin/bash
# Generate schema.sql from current CashCue DB
# Reads config from /etc/cashcue/cashcue.conf

CONFIG_FILE="/etc/cashcue/cashcue.conf"

if [ ! -f "$CONFIG_FILE" ]; then
  echo "[ERROR] Config file not found: $CONFIG_FILE"
  exit 1
fi

# Source dotenv file (must be bash-compatible KEY=VAL lines)
set -a
source "$CONFIG_FILE"
set +a

OUTPUT_FILE="schemaCashCueBD.sql"

echo "[INFO] Exporting schema from database '$DB_NAME' on $DB_HOST:$DB_PORT ..."

mysqldump \
  --no-data \
  --routines \
  --triggers \
  -h "$DB_HOST" \
  -P "${DB_PORT:-3306}" \
  -u "$DB_USER" \
  -p"$DB_PASS" \
  "$DB_NAME" > "$OUTPUT_FILE"


if [ $? -eq 0 ]; then
  echo "[INFO] Schema successfully written to $OUTPUT_FILE"
else
  echo "[ERROR] Schema export failed"
  exit 1
fi
