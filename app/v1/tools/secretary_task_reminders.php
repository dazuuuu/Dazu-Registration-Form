<?php
// Sends the task reminders: the day before a task is due, and on the day, at 8:00 am Nairobi time (up to 4 hours late), once each, to the secretary who has the task: an in-app notice and phone push (the
// existing notification code) and an email (the existing mail code). Run it from cron, in the same way as staffcalendar_reminders.php:
//   */5 * * * * /usr/bin/php /path/to/app/v1/tools/secretary_task_reminders.php --host=YOUR-DOMAIN --https >> /path/to/app/v1/storage/secretary_task_reminders.log 2>&1
// Options:
//   --host=NAME   the site's domain, used for the links in push and email (needed because cron has no web request)
//   --https       the links start with https://
//   --now="2026-11-20 08:00"   pretend it is this Nairobi time (testing)
//   --dry-run     only print what WOULD be sent; sends nothing and writes nothing
//   --no-send     test mode: does everything except calling push and mail
// It works in Nairobi time (EAT). Every reminder is claimed in secretary_task_reminder_log first, so nothing goes out twice, even when two runs overlap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$o = getopt('', ['host:', 'https', 'now:', 'dry-run', 'no-send']);
$_SERVER['HTTP_HOST'] = $o['host'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
if (isset($o['https'])) $_SERVER['HTTPS'] = 'on';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SecretaryTaskService.php';

$dry = isset($o['dry-run']);
$dir = __DIR__ . '/../storage'; if (!is_dir($dir)) @mkdir($dir, 0775, true);
$lock = @fopen($dir . '/secretary_task_reminders.lock', 'c');
if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) { echo "Another run is still going.\n"; exit(0); }

$db = \Models\Connection::getInstance()->getConnection();
try { $db->query('SELECT 1 FROM secretary_tasks WHERE 1 = 0'); $db->query('SELECT 1 FROM secretary_task_reminder_log WHERE 1 = 0'); }
catch (Throwable $e) { fwrite(STDERR, "Run the migrations first (129 and 130).\n"); exit(1); }
try {
    $now = isset($o['now']) ? new DateTime((string) $o['now'], SecretaryTaskService::tz()) : SecretaryTaskService::now();
} catch (Throwable $e) { fwrite(STDERR, "Bad --now value.\n"); exit(1); }

try {
    $r = (new SecretaryTaskService($db))->reminders($now, ['dry' => $dry, 'no_send' => isset($o['no-send'])]);
} catch (Throwable $e) {
    fwrite(STDERR, '[' . $now->format('Y-m-d H:i') . '] secretary_task_reminders failed: ' . $e->getMessage() . "\n"); exit(1);
}
foreach ($r['lines'] as $l) echo $l . "\n";
echo '[' . $now->format('Y-m-d H:i') . ' EAT] ' . ($dry ? 'DRY RUN: ' : '') . $r['jobs'] . ' due; sent app ' . $r['sent_app'] . ', email ' . $r['sent_email']
   . '; already sent ' . $r['dupe'] . '; failed ' . $r['failed'] . ($dry ? '; would send ' . $r['would'] : '') . "\n";
