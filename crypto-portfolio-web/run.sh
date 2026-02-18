#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCHEMA_FILE="$SCRIPT_DIR/db/schema.sql"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-crypto_portfolio_web}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
APP_PORT="${APP_PORT:-8000}"

if ! command -v mysql >/dev/null 2>&1; then
  echo "Error: mysql client is not installed. Please install MySQL client tools first."
  exit 1
fi

if [[ ! -f "$SCHEMA_FILE" ]]; then
  echo "Error: schema file not found at $SCHEMA_FILE"
  exit 1
fi

echo "==> Initializing database '$DB_NAME' from $SCHEMA_FILE"
MYSQL_ARGS=("-h" "$DB_HOST" "-P" "$DB_PORT" "-u$DB_USER")
if [[ -n "$DB_PASS" ]]; then
  MYSQL_ARGS+=("-p$DB_PASS")
fi
mysql "${MYSQL_ARGS[@]}" < "$SCHEMA_FILE"

echo "==> Database ready. Starting PHP server on http://127.0.0.1:$APP_PORT"
echo "    Press Ctrl+C to stop."
exec php -S "0.0.0.0:$APP_PORT" -t "$SCRIPT_DIR"
