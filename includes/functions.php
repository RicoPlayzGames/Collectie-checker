<?php

/**
 * Ensure the database schema is in place.
 *
 * This function is intentionally lightweight and will create any missing tables
 * that are required for the core Collection Checker functionality.
 */
function ensureDbSchema(PDO $pdo): void
{
    // Users table stores authentication information.
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(255) DEFAULT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Backward compatibility: older schema used `password` instead of `password_hash`.
    ensureColumnExists($pdo, 'users', 'password_hash', 'ALTER TABLE `users` ADD COLUMN `password_hash` VARCHAR(255) NULL AFTER `email`');
    if (columnExists($pdo, 'users', 'password')) {
        // Quote legacy column names explicitly to avoid parser conflicts on older MySQL versions.
        $pdo->exec("UPDATE `users` SET `password_hash` = `password` WHERE (`password_hash` IS NULL OR `password_hash` = '') AND `password` IS NOT NULL AND `password` <> ''");
    }

    // Cars table stores collection items associated with a user.
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS cars (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            brand VARCHAR(255) NOT NULL,
            automerk VARCHAR(255) NOT NULL,
            model VARCHAR(255) NOT NULL,
            scale VARCHAR(20) NOT NULL,
            bought_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            estimated_value DECIMAL(12,2) NOT NULL DEFAULT 0,
            details TEXT NULL,
            image_path VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Brands table stores reusable brand names per user.
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS brands (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            search_key VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_brand (user_id, name),
            INDEX idx_user_search (user_id, search_key),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Keep existing deployments compatible by adding newly introduced columns.
    ensureColumnExists($pdo, 'cars', 'details', 'ALTER TABLE cars ADD COLUMN details TEXT NULL');
    ensureColumnExists($pdo, 'cars', 'image_path', 'ALTER TABLE cars ADD COLUMN image_path VARCHAR(255) NULL');
    ensureColumnExists($pdo, 'cars', 'is_favorite', 'ALTER TABLE cars ADD COLUMN is_favorite TINYINT(1) NOT NULL DEFAULT 0');
    ensureColumnExists($pdo, 'cars', 'car_status', "ALTER TABLE cars ADD COLUMN car_status VARCHAR(20) NOT NULL DEFAULT 'owned'");
    ensureColumnExists($pdo, 'cars', 'car_condition', 'ALTER TABLE cars ADD COLUMN car_condition VARCHAR(30) NULL');
    ensureColumnExists($pdo, 'cars', 'model_year', 'ALTER TABLE cars ADD COLUMN model_year SMALLINT NULL');

    // Password resets table supports forgot-password flow.
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            reset_code VARCHAR(10) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_reset_code (reset_code),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

/**
 * Adds a column when it does not already exist.
 */
function ensureColumnExists(PDO $pdo, string $table, string $column, string $alterSql): void
{
    if (!columnExists($pdo, $table, $column)) {
        try {
            $pdo->exec($alterSql);
        } catch (Throwable $e) {
            // Do not break auth/page rendering because of migration SQL; continue with existing schema.
            error_log($e->getMessage() . PHP_EOL, 3, __DIR__ . '/../config/db_errors.log');
        }
    }
}

/**
 * Checks whether a table column exists.
 */
function columnExists(PDO $pdo, string $table, string $column): bool
{
    $escapedColumn = str_replace(['\\', "'"], ['\\\\', "\\'"], $column);
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$escapedColumn'");
    if (!$stmt) {
        return false;
    }
    return (bool)$stmt->fetch();
}

/**
 * Format a price for display.
 */
function formatPrice(float $amount): string
{
    return '€' . number_format($amount, 2, ',', '.');
}

/**
 * Safely escape output for HTML contexts.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
