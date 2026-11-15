<?php
// Self-study: the cron job (app/v1/tools/selfstudy_expire.php) leaves its last run time for Settings > Self-study.
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/selfstudy_cron_test.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SelfStudyProgress.php';
$db = \Models\Connection::getInstance()->getConnection();
$fails = 0;
function check(string $name, $cond, string $extra = ''): void { global $fails; if ($cond) { echo "ok   $name\n"; return; } $fails++; echo "FAIL $name" . ($extra !== '' ? "  ($extra)" : '') . "\n"; }

$db->exec("DELETE FROM progress_settings WHERE setting_key = 'ss_cron_last_run'");
$now = strtotime('2026-11-13 12:00:00 +03:00');
check('before the first run there is no time, and it counts as "not run"', ssd_cron_last($db) === null && ssd_cron_status($db, $now)['late'] === true);
ssd_cron_beat($db, $now - 120);
check('a run 2 minutes ago is shown and is on time', ssd_cron_last($db) === $now - 120 && ssd_cron_status($db, $now)['late'] === false);
check('exactly 10 minutes is still on time, 10 minutes and a second is late', ssd_cron_status($db, $now - 120 + 600)['late'] === false && ssd_cron_status($db, $now - 120 + 601)['late'] === true);
ssd_cron_beat($db, $now - 3600);
check('a run an hour ago is late', ssd_cron_status($db, $now)['late'] === true);
ssd_cron_beat($db, $now - 60); ssd_cron_beat($db, $now - 30);
check('a new run replaces the old time (one row only)', ssd_cron_last($db) === $now - 30 && (int) $db->query("SELECT COUNT(*) FROM progress_settings WHERE setting_key = 'ss_cron_last_run'")->fetchColumn() === 1);
check('the heartbeat is not read as one of the comment numbers', !array_key_exists('cron_last_run', ssd_settings($db)) && ssd_settings($db)['failed_in_row'] === 2);
$db->exec("DELETE FROM progress_settings WHERE setting_key LIKE 'ss\\_%' AND setting_key <> 'ss_cron_last_run'");
check('"Use the standard numbers" on the Settings page keeps the heartbeat', ssd_cron_last($db) === $now - 30);

// the real script, as the cron job would run it
$php = escapeshellarg(PHP_BINARY); $script = escapeshellarg(realpath(__DIR__ . '/../tools/selfstudy_expire.php'));
$db->exec("DELETE FROM progress_settings WHERE setting_key = 'ss_cron_last_run'");
$ext = ''; foreach (['pdo_mysql', 'pdo_pgsql', 'mbstring', 'gd'] as $e) if (extension_loaded($e)) $ext .= ' -d extension=' . $e;
$out = shell_exec("$php$ext $script 2>&1");
check('running the script prints how many exams it submitted', is_string($out) && str_contains($out, 'attempt(s) submitted'), (string) $out);
$last = ssd_cron_last($db);
check('and it leaves a fresh run time (so Settings shows "running on time")', $last !== null && abs(time() - $last) < 60 && ssd_cron_status($db)['late'] === false);
$db->exec("DELETE FROM progress_settings WHERE setting_key = 'ss_cron_last_run'");
echo $fails ? "\n$fails FAILED\n" : "\nAll passed\n";
exit($fails ? 1 : 0);
