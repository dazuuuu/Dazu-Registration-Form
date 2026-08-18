<?php
// databases/migrate.php — CLI runner: applies pending *.sql files in databases/required_migrations
// Usage: php databases/migrate.php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the CLI: php databases/migrate.php');
}

require_once __DIR__ . '/../app/v1/config/env.php';
require_once __DIR__ . '/../app/v1/services/MigrationService.php';
$config = require __DIR__ . '/../app/v1/config/database.php';

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

$migrations = new MigrationService($pdo, __DIR__ . '/required_migrations');
$result = $migrations->applyPending(static function (string $message): void {
    echo $message;
});

if (!empty($result['failed'])) {
    exit(1);
}

echo $result['ran'] > 0
    ? "Done. Applied {$result['ran']} migration(s).\n"
    : "Nothing to do. Database is up to date.\n";
