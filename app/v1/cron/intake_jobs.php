<?php
// Enrolment scheduled jobs. Run every 5 minutes from cron:
//   */5 * * * * /usr/bin/php /path/to/app/v1/cron/intake_jobs.php >> /path/to/app/v1/storage/intake_cron.log 2>&1
// 1. Close enrolment openings whose closing date has passed (pages also do this when they load).
// 1a. Early access: send the invites when early access starts, the 24-hour reminders, and expire the seat holds that ended (en_cron).
// 1b. Tell the people who asked "Tell me when the next intake opens" once an opening is taking bookings.
// 2. Send the class calendar's queued emails (and retry failed ones). Safe to run twice.
// The older intake system stays retired: early access, holds and reminders now run from en_cron() on the enrolment tables.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run from the command line.');
}
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/EnrolmentService.php';
require_once __DIR__ . '/../services/ClassCalendarService.php';

$db = \Models\Connection::getInstance()->getConnection();
$lock = fopen(storage_path('intake_cron.lock'), 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo date('c') . " Another run is still busy.\n";
    exit(0);
}
if (en_installed($db)) { en_autoclose($db); $j = en_cron($db); if ($j['invites'] || $j['reminders'] || $j['expired']) echo date('c') . " early access: {$j['invites']} invites, {$j['reminders']} reminders, {$j['expired']} holds expired
"; $told = en_notify_interest($db); if ($told) echo date('c') . " told $told people the next intake is open for bookings
"; }
$mail = cal_process_queue($db, 300);
echo date('c') . " enrolment checked; emails sent: {$mail['sent']}, failed: {$mail['failed']}\n";
flock($lock, LOCK_UN);
