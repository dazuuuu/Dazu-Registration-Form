<?php
// Assignment reminders. Run from cron every 30 minutes, e.g. in cPanel:
//   */30 * * * * php /home/USER/public_html/app/v1/cron/assignment_reminders.php --host=dazu.ac.ke --https
// Sends: a "due in 24 hours" email to students who have not submitted, and a reminder to tutors for
// extension/unlock requests still pending after 24 hours. Each is sent once (reminded_at).
// --host / --https build the links inside emails (there is no web request to take them from).
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$opts = getopt('', ['host:', 'https', 'dry-run']);
$_SERVER['HTTP_HOST'] = $opts['host'] ?? (parse_url((require __DIR__ . '/../config/app.php')['app_url'] ?? '', PHP_URL_HOST) ?: 'localhost');
if (isset($opts['https'])) $_SERVER['HTTPS'] = 'on';

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../models/AssignmentModel.php';
require_once __DIR__ . '/../services/AssignmentNotifier.php';

$pdo = \Models\Connection::getInstance()->getConnection();
$am = new \Models\AssignmentModel($pdo);
$notifier = new AssignmentNotifier($pdo, $am);
$dry = isset($opts['dry-run']);

$due = $am->dueSoon();
foreach ($due as $row) {
    if (!$dry) {
        // Locked ones are checked again next run, in case they open before the due time.
        if ($notifier->dueReminder($row)) $am->markReminded((int) $row['id']);
    }
}
$stale = $am->staleRequests();
foreach ($stale as $reqId) {
    if (!$dry) {
        $notifier->requestReminder($reqId);
        $am->markRequestReminded($reqId);
    }
}
if (!$dry) AssignmentNotifier::flush();
echo gmdate('c') . ' due-soon: ' . count($due) . ', stale requests: ' . count($stale) . ($dry ? ' (dry run, nothing sent)' : '') . PHP_EOL;
