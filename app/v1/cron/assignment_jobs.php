<?php
// Assignment jobs. Run from cron every 5 minutes, e.g. in cPanel:
//   */5 * * * * php /home/USER/public_html/app/v1/cron/assignment_jobs.php --host=dazu.ac.ke --https
// 1. Publishes scheduled assignments whose show-on time has come, and sends the normal "new assignment" notice then.
// 2. Releases held grades whose release time has come, and tells each student once.
// 3. Reminders 24 hours and 1 hour before the due time, only to students who have not submitted (once each:
//    tutor_assignment_reminders_log). WhatsApp / SMS copies are queued when the tutor turned it on, and sent only
//    when a gateway is configured (none is today, so they stay queued).
// 4. Checks bad links again a few hours before the due time; never changes the sent time, Late or the mark.
// Safe to run twice: publish() only moves drafts, students are notified once (notified_at), releases are claimed first.
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
require_once __DIR__ . '/../services/LinkChecker.php';
require_once __DIR__ . '/../helpers/assignment_files.php';

$pdo = \Models\Connection::getInstance()->getConnection();
$am = new \Models\AssignmentModel($pdo);
$notifier = new AssignmentNotifier($pdo, $am);
$dry = isset($opts['dry-run']);

$scheduled = $am->dueScheduled();
foreach ($scheduled as $id) {
    if ($dry) continue;
    $uids = $am->publish($id, 0);
    if ($uids) $notifier->published($am->get($id), $uids);
}

$releases = $am->dueGradeReleases();
$told = 0;
foreach ($releases as $a) {
    if (!$dry) $told += $notifier->gradesReleased($a);
}
$reminded = 0;
foreach (['1h', '24h'] as $kind) {
    foreach ($am->remindersDue($kind) as $row) {
        if ($dry) { $reminded++; continue; }
        $aid = (int) $row['assignment_id'];
        $uid = (int) $row['student_user_id'];
        // A 1-hour reminder also covers the 24-hour one, so a late-added student never gets both at once.
        if (!$am->claimReminder($aid, $uid, $kind)) continue;
        if ($kind === '1h') $am->claimReminder($aid, $uid, '24h');
        $item = $am->itemForStudent($uid, $aid);
        if (!$item || !in_array($item['state'], ['open', 'in_progress', 'extension'], true)) continue;
        $notifier->reminder($item, $uid, $kind);
        $reminded++;
        if ((int) $row['remind_whatsapp']) {
            $pdo->prepare("INSERT INTO tutor_assignment_message_queue (channel, student_user_id, body, created_at) VALUES ('whatsapp', ?, ?, ?)")
                ->execute([$uid, mb_substr('Dazu: ' . $item['title'] . ' is due ' . ($kind === '1h' ? 'in 1 hour' : 'tomorrow') . ', ' . \Models\AssignmentModel::local($item['due'], 'D j M, H:i') . ' EAT.', 0, 500), \Models\AssignmentModel::now()]);
        }
    }
}

$rechecked = 0;
$lc = new LinkChecker($pdo);
foreach ($am->badLinksDueSoon(6) as $row) {
    if ($dry) { $rechecked++; continue; }
    $sub = $am->submissionById((int) $row['id']);
    $res = [];
    foreach (\Models\AssignmentModel::workLinks($sub['links'], $sub['answers']) as $u) {
        $c = $lc->check($u, $row['link_sites'], null, (int) $row['id']);
        if (!in_array($c['status'], ['not_checked', 'not_allowed'], true)) $res[] = $c;
    }
    $am->saveLinkResult((int) $row['id'], $res);
    $rechecked++;
}

if (!$dry) AssignmentNotifier::flush();
echo gmdate('c') . ' published: ' . count($scheduled) . ', grade releases: ' . count($releases) . ' (' . $told . ' students told), reminders: ' . $reminded
    . ', links rechecked: ' . $rechecked . ($dry ? ' (dry run, nothing sent)' : '') . PHP_EOL;
