<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

/** @var PDO $pdo */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = null;
}

// Ensure the database schema exists on any page that includes this file.
if ($pdo instanceof PDO) {
    try {
        ensureDbSchema($pdo);
    } catch (Throwable $e) {
        error_log($e->getMessage() . PHP_EOL, 3, __DIR__ . '/../config/db_errors.log');
    }
}

/**
 * Returns the currently authenticated user row, or null if not logged in.
 *
 * @return array|null
 */
function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    global $pdo;

    if (!($pdo instanceof PDO)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, username, email, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

/**
 * Redirects to login.php if the user is not authenticated.
 */
function requireAuth(): void
{
    if (!currentUser()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Logs the current user out.
 */
function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}
