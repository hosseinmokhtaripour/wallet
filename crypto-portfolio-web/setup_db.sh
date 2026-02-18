#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCHEMA_FILE="$SCRIPT_DIR/db/schema.sql"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

if ! command -v mysql >/dev/null 2>&1; then
  echo "Error: mysql client is not installed."
  exit 1
fi

if [[ ! -f "$SCHEMA_FILE" ]]; then
  echo "Error: schema file not found at $SCHEMA_FILE"
  exit 1
fi

MYSQL_ARGS=("-h" "$DB_HOST" "-P" "$DB_PORT" "-u$DB_USER")
if [[ -n "$DB_PASS" ]]; then
  MYSQL_ARGS+=("-p$DB_PASS")
fi

echo "==> Applying schema: $SCHEMA_FILE"
mysql "${MYSQL_ARGS[@]}" < "$SCHEMA_FILE"
echo "==> Database setup complete."
