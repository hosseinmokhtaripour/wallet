<?php
/**
 * Global application bootstrap.
 *
 * Update DB_* values if your local MySQL setup is different.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'crypto_portfolio_web';
const DB_USER = 'root';
const DB_PASS = '';

/** @var PDO|null $pdo */
$pdo = null;

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    http_response_code(500);
    die('Database connection failed. Please check config.php and MySQL status.');
}

require_once __DIR__ . '/helpers.php';
