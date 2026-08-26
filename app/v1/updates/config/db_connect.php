<?php
// app/v1/config/db_connect.php — PDO handle for legacy pages

$config = require __DIR__ . '/database.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
        $config['username'],
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed. Check app/v1/config/database.php or .env DB_* settings.');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
