<?php
$host = 'localhost';
$db_name = 'collection_checker';
$username = 'Midnight_Apex';
$password = '8q4q6M0vpiMxTG9px21u8VcsAFqexu!';
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db_name;charset=$charset",
        $username,
        $password,
        $options
    );
} catch (PDOException $e) {
    error_log($e->getMessage(), 3, __DIR__ . '/db_errors.log');
    die("Database connection failed.");
}