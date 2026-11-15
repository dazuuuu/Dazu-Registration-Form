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
if (getenv('DAZU_DB_NAME')) {
    $config['dbname'] = getenv('DAZU_DB_NAME');   // e.g. DAZU_DB_NAME=dazu_scratch to try the migrations on a throw-away database
}

// Create the database first when it is missing (MySQL or PostgreSQL, from config/database.php), then connect to it.
require_once __DIR__ . '/../lib/DazuPdo.php';
try {
    DazuPdo::ensureDatabase($config);
    $pdo = DazuPdo::open($config);
} catch (PDOException $e) {
    fwrite(STDERR, "Connection failed: {$e->getMessage()}
");
    exit(1);
}

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
