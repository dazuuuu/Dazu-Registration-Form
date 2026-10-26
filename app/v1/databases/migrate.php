<?php
// app/v1/databases/migrate.php — CLI runner: applies pending *.sql files in app/v1/databases/required_migrations
// Usage: php app/v1/databases/migrate.php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the CLI: php app/v1/databases/migrate.php');
}

require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../services/MigrationService.php';
$config = require __DIR__ . '/../config/database.php';

// Connect without a database first so CREATE DATABASE IF NOT EXISTS (below) always works.
try {
    $pdo = new PDO(
        "mysql:host={$config['host']};charset={$config['charset']}",
        $config['username'],
        $config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    fwrite(STDERR, "Connection failed: {$e->getMessage()}\n");
    exit(1);
}

$dbName = $config['dbname'];
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4");
$pdo->exec("USE `{$dbName}`");

$migrations = new MigrationService($pdo, DATABASES_PATH . '/required_migrations');
$result = $migrations->applyPending(static function (string $message): void {
    echo $message;
});

if (!empty($result['failed'])) {
    exit(1);
}

echo $result['ran'] > 0
    ? "Done. Applied {$result['ran']} migration(s).\n"
    : "Nothing to do. Database is up to date.\n";
