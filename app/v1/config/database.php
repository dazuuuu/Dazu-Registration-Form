<?php
// app/v1/config/database.php — single DB config (also used by db_connect.php)

require_once __DIR__ . '/env.php';

return [
    'host'     => dazu_env('DB_HOST', 'localhost'),
    'dbname'   => dazu_env('DB_NAME', 'registration_system'),
    'username' => dazu_env('DB_USER', 'root'),
    'password' => dazu_env('DB_PASS', 'mysql'),
    'charset'  => 'utf8mb4',
];
