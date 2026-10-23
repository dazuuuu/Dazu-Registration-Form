<?php
// Intake Booking scheduled jobs. Run every 5 minutes from cron:
//   */5 * * * * /usr/bin/php /path/to/app/v1/cron/intake_jobs.php >> /path/to/app/v1/storage/intake_cron.log 2>&1
// 1. Early-access invites  2. 24-hour hold reminders  3. Expire ended holds, release seats, offer to the waiting list
// 4. Tell the interest list when an intake opens bookings  5. Daily admissions summary.  Every step is safe to run twice.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run from the command line.');
}
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/IntakeService.php';
require_once __DIR__ . '/../services/ClassCalendarService.php';

$db = \Models\Connection::getInstance()->getConnection();
if (!ik_installed($db)) {
    echo date('c') . " Intake Booking not installed (migration 079).\n";
    exit(0);
}
$lock = fopen(storage_path('intake_cron.lock'), 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo date('c') . " Another run is still busy.\n";
    exit(0);
}
$r = ik_cron($db);
$mail = cal_process_queue($db, 300);   // send the queued emails now (and retry failed ones)
echo date('c') . ' ' . json_encode($r) . " emails sent: {$mail['sent']}, failed: {$mail['failed']}\n";
flock($lock, LOCK_UN);
