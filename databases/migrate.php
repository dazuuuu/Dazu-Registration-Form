<?php
// databases/migrate.php — CLI runner: applies pending *.sql files in databases/migrations
// Usage: php databases/migrate.php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the CLI: php databases/migrate.php');
}

require_once __DIR__ . '/../app/v1/config/env.php';
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

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        filename VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB"
);

$applied = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

// Strips -- line comments while respecting single-quoted strings, so a
// comment like "-- foo; bar" never gets mistaken for a statement boundary.
function strip_sql_comments(string $sql): string {
    $out = '';
    $inString = false;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        if ($inString) {
            $out .= $ch;
            if ($ch === "'" && ($sql[$i + 1] ?? '') === "'") {
                $out .= $sql[++$i];
            } elseif ($ch === "'") {
                $inString = false;
            }
            continue;
        }
        if ($ch === "'") {
            $inString = true;
            $out .= $ch;
            continue;
        }
        if ($ch === '-' && ($sql[$i + 1] ?? '') === '-') {
            $nl = strpos($sql, "\n", $i);
            $i = ($nl === false) ? $len : $nl - 1;
            continue;
        }
        $out .= $ch;
    }
    return $out;
}

$files = glob(__DIR__ . '/migrations/*.sql');
sort($files, SORT_STRING);

$ran = 0;
foreach ($files as $path) {
    $filename = basename($path);
    if (isset($applied[$filename])) {
        continue;
    }

    echo "Applying {$filename} ... ";

    // Every migration targets the single configured database; ignore any
    // stray CREATE DATABASE / USE / comment lines left over in older files.
    $sql = strip_sql_comments(file_get_contents($path));
    $lines = preg_split('/\r?\n/', $sql);
    $lines = array_filter($lines, function ($line) {
        $trimmed = ltrim($line);
        return $trimmed !== ''
            && stripos($trimmed, 'CREATE DATABASE') !== 0
            && stripos($trimmed, 'USE ') !== 0;
    });
    $statements = array_filter(array_map('trim', explode(';', implode("\n", $lines))));

    // MySQL codes for "this already exists" — several migrations are
    // documented backfills that are expected to hit these on a fresh install
    // where a later table/column already has what they're adding.
    $alreadyExistsCodes = [1050, 1060, 1061, 1068, 1022];

    // Note: CREATE/ALTER TABLE cause an implicit commit in MySQL, so a
    // transaction here would not make a whole file atomic anyway.
    $skipped = [];
    try {
        foreach ($statements as $statement) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                $code = $e->errorInfo[1] ?? null;
                if (in_array($code, $alreadyExistsCodes, true)) {
                    $skipped[] = $e->errorInfo[2] ?? $e->getMessage();
                    continue;
                }
                throw $e;
            }
        }
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
        $stmt->execute([$filename]);
        echo $skipped ? "OK (with skips)\n" : "OK\n";
        foreach ($skipped as $message) {
            echo "  (skipped, already applied: {$message})\n";
        }
        $ran++;
    } catch (PDOException $e) {
        echo "FAILED\n";
        fwrite(STDERR, "  {$e->getMessage()}\n");
        exit(1);
    }
}

echo $ran > 0 ? "Done. Applied {$ran} migration(s).\n" : "Nothing to do. Database is up to date.\n";
