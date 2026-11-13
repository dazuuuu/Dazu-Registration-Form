<?php
// Sends the Staff Calendar reminders (push, email and the in-app "Reminders" list). Run it from cron every minute, in the same way as selfstudy_expire.php:
//   * * * * * /usr/bin/php /path/to/app/v1/tools/staffcalendar_reminders.php --host=YOUR-DOMAIN --https >> /path/to/app/v1/storage/staffcalendar_reminders.log 2>&1
// Options:
//   --host=NAME   the site's domain, used for the links in push and email (needed because cron has no web request)
//   --https       the links start with https://
//   --now="2026-11-20 08:00"   pretend it is this Nairobi time (testing)
//   --dry-run     only print what WOULD be sent; sends nothing and writes nothing
//   --no-send     test mode: does everything except calling push and mail
// It works in Nairobi time (EAT). Every reminder is claimed in staff_calendar_reminder_log first, so nothing goes out twice, even when two runs overlap.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$o = getopt('', ['host:', 'https', 'now:', 'dry-run', 'no-send']);
$_SERVER['HTTP_HOST'] = $o['host'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost');
if (isset($o['https'])) $_SERVER['HTTPS'] = 'on';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/StaffCalendar.php';
require_once __DIR__ . '/../services/WebPush.php';
require_once __DIR__ . '/../services/StaffReminders.php';

$dry = isset($o['dry-run']);
// one run at a time (a second run that starts while this one is still going just leaves; the unique key is the second safety net)
$dir = __DIR__ . '/../storage'; if (!is_dir($dir)) @mkdir($dir, 0775, true);
$lock = @fopen($dir . '/staffcalendar_reminders.lock', 'c');
if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) { echo "Another run is still going.\n"; exit(0); }

$db = \Models\Connection::getInstance()->getConnection();
if (!srm_installed($db)) { fwrite(STDERR, "Run the migrations first.\n"); exit(1); }
try {
    $now = isset($o['now']) ? new DateTime((string) $o['now'], scal_tz()) : scal_now();
} catch (Throwable $e) { fwrite(STDERR, "Bad --now value.\n"); exit(1); }

try {
    $r = srm_run($db, $now, ['dry' => $dry, 'no_send' => isset($o['no-send'])]);
} catch (Throwable $e) {
    fwrite(STDERR, '[' . $now->format('Y-m-d H:i') . '] staffcalendar_reminders failed: ' . $e->getMessage() . "\n"); exit(1);
}
foreach ($r['lines'] as $l) echo $l . "\n";
echo '[' . $now->format('Y-m-d H:i') . ' EAT] ' . ($dry ? 'DRY RUN: ' : '') . $r['jobs'] . ' due; sent app ' . $r['sent_app'] . ', push ' . $r['sent_push'] . ', email ' . $r['sent_email']
   . '; already sent ' . $r['dupe'] . '; switched off ' . $r['gated'] . '; failed ' . $r['failed'] . ($dry ? '; would send ' . $r['would'] : '') . "\n";
