# Crypto Portfolio Web

A PHP + MySQL app for tracking multi-user crypto, gold, and fiat portfolios with DCA, transaction history, and P/L analysis.

## Quick start (automatic)

From the `crypto-portfolio-web` directory:

```bash
./run.sh
```

This command will:
1. Run the MySQL schema in `db/schema.sql`.
2. Start the PHP built-in web server on `http://127.0.0.1:8000`.

## Database-only setup

```bash
./setup_db.sh
```

## Transaction management

- Dashboard includes a BUY/SELL transaction form.
- BUY updates holdings and invested capital.
- SELL validates available holdings and updates holdings automatically.
- Dashboard includes BUY and SELL transaction tables with optional asset/date filters.

## Environment variables

You can override DB/server settings:

- `DB_HOST` (default: `127.0.0.1`)
- `DB_PORT` (default: `3306`)
- `DB_NAME` (default: `crypto_portfolio_web`)
- `DB_USER` (default: `root`)
- `DB_PASS` (default: empty)
- `APP_PORT` (default: `8000`, used by `run.sh`)

Example:

```bash
DB_USER=myuser DB_PASS=mypassword APP_PORT=8080 ./run.sh
```

## Manual start (without scripts)

```bash
mysql -u root -p < db/schema.sql
php -S 0.0.0.0:8000
```

## Notes

- `config.php` reads the same `DB_*` environment variables so app/runtime setup stays in sync.
- The asset files for Bootstrap and Chart.js are lightweight CDN loaders.
