<?php
// app/v1/config/db_connect.php — PDO handle for legacy pages

$config = require __DIR__ . '/database.php';

try {
    require_once __DIR__ . '/../lib/DazuPdo.php';
    $pdo = DazuPdo::open($config);   // MySQL or PostgreSQL, from config/database.php
    require_once __DIR__ . '/../helpers/schema_guard.php';
    dazu_schema_guard($pdo);
} catch (PDOException $e) {
    // public/setup.php asks for the database login itself, so it must load even when this connection fails.
    if (defined('DAZU_SETUP')) {
        $pdo = null;
        return;
    }
    http_response_code(500);
    die('Database connection failed. Check the database settings.');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
