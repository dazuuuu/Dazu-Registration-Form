<?php
// Applies the migrations up to and including a number (scratch database only), so a test can see the "before" state of a later migration.
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/migrate_upto.php 090
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME') || !isset($argv[1])) { fwrite(STDERR, "Usage: DAZU_DB_NAME=<db> php migrate_upto.php <number>\n"); exit(2); }
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../services/MigrationService.php';
require_once __DIR__ . '/../lib/DazuPdo.php';
$config = require __DIR__ . '/../config/database.php';
$config['dbname'] = getenv('DAZU_DB_NAME');
DazuPdo::ensureDatabase($config);
$pdo = DazuPdo::open($config);
$tmp = sys_get_temp_dir() . '/mig_upto_' . getmypid();
mkdir($tmp);
foreach (glob(DATABASES_PATH . '/required_migrations/*.sql') as $f) {
    if ((int) basename($f) <= (int) $argv[1]) copy($f, $tmp . '/' . basename($f));
}
$r = (new MigrationService($pdo, $tmp))->applyPending(static function (string $m): void {});
array_map('unlink', glob("$tmp/*")); rmdir($tmp);
echo 'Applied ' . $r['ran'] . " migration(s) up to $argv[1]\n";
exit(empty($r['failed']) ? 0 : 1);
