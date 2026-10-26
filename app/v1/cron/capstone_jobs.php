<?php
// Capstone jobs. Run from cron every 5 minutes, e.g. in cPanel:
//   */5 * * * * php /home/USER/public_html/app/v1/cron/capstone_jobs.php --host=dazu.ac.ke --https
// 1. Opens scheduled capstones whose date and time has come, and tells the students.
// 2. "Your capstone opens on ..." three days before it opens.
// 3. Milestone reminders 2 days and 1 day before, only to students who have not sent that milestone.
// 4. Tells students when a held mark reaches its release time.
// 5. Re-checks every student who finished: when all four certificate conditions are true the transcript and the letter of
//    recommendation are released (and hidden again if the student stops being Ready before a certificate is issued).
// Safe to run twice: every step is claimed in the database first, so nothing is sent twice.
// If cron is not set up, the capstone pages run the same check (at most once a minute) when someone opens them.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$opts = getopt('', ['host:', 'https']);
$_SERVER['HTTP_HOST'] = $opts['host'] ?? (parse_url((require __DIR__ . '/../config/app.php')['app_url'] ?? '', PHP_URL_HOST) ?: 'localhost');
if (isset($opts['https'])) $_SERVER['HTTPS'] = 'on';

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/CapstoneService.php';

$pdo = \Models\Connection::getInstance()->getConnection();
$r = cap_tick($pdo);
AssignmentNotifier::flush();   // send the queued emails now (the CLI has no "after the page" moment)
echo date('c') . ' capstone: opened ' . $r['opened'] . ', opens-soon ' . $r['soon'] . ', reminders ' . $r['reminders'] . ', released ' . $r['released'] . ', transcripts+letters ' . $r['transcripts'] . ', certificate buttons opened ' . $r['certificates'] . ', tutor reminders ' . $r['cert_reminders'] . "\n";
