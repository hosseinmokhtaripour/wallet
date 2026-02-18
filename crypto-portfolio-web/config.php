<?php
/**
 * Global application bootstrap.
 *
 * You can override DB settings via environment variables:
 * DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function env_or_default(string $key, string $default): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

const DB_HOST_DEFAULT = '127.0.0.1';
const DB_PORT_DEFAULT = 3306;
const DB_NAME_DEFAULT = 'crypto_portfolio_web';
const DB_USER_DEFAULT = 'root';
const DB_PASS_DEFAULT = '';

$dbHost = env_or_default('DB_HOST', DB_HOST_DEFAULT);
$dbPort = (int) env_or_default('DB_PORT', (string) DB_PORT_DEFAULT);
$dbName = env_or_default('DB_NAME', DB_NAME_DEFAULT);
$dbUser = env_or_default('DB_USER', DB_USER_DEFAULT);
$dbPass = env_or_default('DB_PASS', DB_PASS_DEFAULT);

/** @var PDO|null $pdo */
$pdo = null;

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    die('Database connection failed. Check MySQL status and DB_* env/config values.');
}

require_once __DIR__ . '/helpers.php';
