-- Crypto Portfolio Web schema
-- Run with: mysql -u root -p < db/schema.sql

CREATE DATABASE IF NOT EXISTS crypto_portfolio_web
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE crypto_portfolio_web;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    symbol VARCHAR(20) NOT NULL UNIQUE,
    category ENUM('CRYPTO', 'GOLD', 'FIAT') NOT NULL,
    decimals TINYINT UNSIGNED NOT NULL DEFAULT 8,
    INDEX idx_assets_category_symbol (category, symbol)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS portfolios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    asset_id BIGINT UNSIGNED NOT NULL,
    allocation_percentage DECIMAL(6,2) NOT NULL DEFAULT 0,
    initial_investment DECIMAL(18,2) NOT NULL DEFAULT 0,
    dca_per_month DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_portfolios_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_portfolios_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_portfolio_user_asset (user_id, asset_id),
    INDEX idx_portfolios_user (user_id),
    INDEX idx_portfolios_asset (asset_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS price_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id BIGINT UNSIGNED NOT NULL,
    price DECIMAL(20,8) NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_price_history_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    INDEX idx_price_history_asset_recorded (asset_id, recorded_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    asset_id BIGINT UNSIGNED NOT NULL,
    type ENUM('BUY', 'SELL', 'DEPOSIT', 'WITHDRAW') NOT NULL,
    amount DECIMAL(28,10) NOT NULL,
    price DECIMAL(20,8) NOT NULL DEFAULT 0,
    tx_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_transactions_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT,
    INDEX idx_transactions_user_asset_date (user_id, asset_id, tx_date),
    INDEX idx_transactions_asset_date (asset_id, tx_date)
) ENGINE=InnoDB;

-- Starter assets. Extend as needed.
INSERT INTO assets (name, symbol, category, decimals) VALUES
('Bitcoin', 'BTC', 'CRYPTO', 8),
('Ethereum', 'ETH', 'CRYPTO', 8),
('Gold (troy oz)', 'XAU', 'GOLD', 4),
('US Dollar', 'USD', 'FIAT', 2)
ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category), decimals = VALUES(decimals);
