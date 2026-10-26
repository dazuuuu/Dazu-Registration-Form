<?php
// Enrolment scheduled jobs. Run every 5 minutes from cron:
//   */5 * * * * /usr/bin/php /path/to/app/v1/cron/intake_jobs.php >> /path/to/app/v1/storage/intake_cron.log 2>&1
// 1. Close enrolment openings whose closing date has passed (pages also do this when they load).
// 2. Send the class calendar's queued emails (and retry failed ones). Safe to run twice.
// The older intake jobs (early access, holds, waiting lists) are retired with the old intake system.
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
if (en_installed($db)) en_autoclose($db);
$mail = cal_process_queue($db, 300);
echo date('c') . " enrolment checked; emails sent: {$mail['sent']}, failed: {$mail['failed']}\n";
flock($lock, LOCK_UN);
