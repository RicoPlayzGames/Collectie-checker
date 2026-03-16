<?php
$host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'collection_checker';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$credentialAttempts = [
    [
        'username' => getenv('DB_USER') ?: 'Midnight_Apex',
        'password' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '8q4q6M0vpiMxTG9px21u8VcsAFqexu!',
    ],
    [
        'username' => 'root',
        'password' => '',
    ],
];

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

function connectPdo(string $dsn, string $username, string $password, array $options): PDO
{
    return new PDO($dsn, $username, $password, $options);
}

$pdo = null;
$lastError = null;

foreach ($credentialAttempts as $credentials) {
    try {
        $pdo = connectPdo(
            "mysql:host=$host;dbname=$db_name;charset=$charset",
            $credentials['username'],
            $credentials['password'],
            $options
        );
        break;
    } catch (PDOException $e) {
        $errorInfo = $e->errorInfo;
        $mysqlCode = is_array($errorInfo) && isset($errorInfo[1]) ? (int)$errorInfo[1] : 0;

        // Unknown database: try creating it with the same credentials.
        if ($mysqlCode === 1049) {
            try {
                $adminPdo = connectPdo(
                    "mysql:host=$host;charset=$charset",
                    $credentials['username'],
                    $credentials['password'],
                    $options
                );

                $adminPdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET $charset COLLATE utf8mb4_unicode_ci");

                $pdo = connectPdo(
                    "mysql:host=$host;dbname=$db_name;charset=$charset",
                    $credentials['username'],
                    $credentials['password'],
                    $options
                );
                break;
            } catch (PDOException $createDbError) {
                $lastError = $createDbError;
                continue;
            }
        }

        $lastError = $e;
    }
}

if (!$pdo) {
    if ($lastError) {
        error_log($lastError->getMessage() . PHP_EOL, 3, __DIR__ . '/db_errors.log');
    }
    // Leave $pdo as null so callers can return structured responses (JSON/HTML) gracefully.
}