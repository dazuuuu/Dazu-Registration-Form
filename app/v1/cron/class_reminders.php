<?php
// Class Calendar scheduled job. Run every 5 minutes from cron (see the setup notes in the hand-over):
//   */5 * * * * /usr/bin/php /path/to/app/v1/cron/class_reminders.php >> /path/to/app/v1/storage/class_cron.log 2>&1
// 1. Reminders 24 hours and 1 hour before each class (each sent once — class_reminders_sent).
// 2. Students who joined a whole-course class get its future classes.
// 3. Sends queued emails and retries failed ones (up to 5 tries).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run from the command line.');
}
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/ClassCalendarService.php';

$db = \Models\Connection::getInstance()->getConnection();
if (!cal_installed($db)) {
    echo date('c') . " Class Calendar not installed (migration 076).\n";
    exit(0);
}
$lock = fopen(storage_path('class_cron.lock'), 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo date('c') . " Another run is still busy.\n";
    exit(0);
}
cal_sync_groups($db);
$reminders = cal_send_reminders($db);
$mail = cal_process_queue($db, 300);
PortalNotifications::flush();
echo date('c') . " reminders: $reminders, emails sent: {$mail['sent']}, failed: {$mail['failed']}\n";
flock($lock, LOCK_UN);
