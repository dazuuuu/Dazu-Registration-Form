<?php
// Transcript access (the full transcript page is built separately later; this page enforces the release rule it will use).
//   student: opens it ONLY after it was released (the student is Ready). Before that: "not allowed".
//   tutor:   opens it for their own student at any time (shown as a draft until it is released).
//   admin:   only when the student's tutor allowed the admin to preview it (default off); words only, never a mark.
//   anyone else, or another student changing the URL: not allowed.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/Session.php';
require_once __DIR__ . '/../../app/v1/services/CapstoneService.php';

use Models\AssignmentModel;

Middleware::requireAuth();
$db = \Models\Connection::getInstance()->getConnection();
$me = (int) ($_SESSION['user_id'] ?? 0);
$role = (string) SessionModel::get('role');
$courseId = (int) ($_GET['course'] ?? 0);
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

function cap_tr_deny(string $why = 'Not allowed.'): void
{
    http_response_code(403);
    exit($why);
}

$draft = false;
$wordsOnly = false;
if ($role === 'student') {
    $uid = $me;
    if (!cap_release_state($db, $uid, $courseId)['transcript']) cap_tr_deny('Not allowed. Your transcript is released when your course is complete: finished, fees paid, capstone passed and course average 50 or more.');
} elseif ($role === 'tutor') {
    $uid = (int) ($_GET['student'] ?? 0);
    $am = new AssignmentModel($db);
    $tutor = $am->tutorByUser($me);
    if (!$uid || !$tutor || !$am->isTutorStudent((int) $tutor['id'], $uid)) cap_tr_deny();
    $draft = !cap_release_state($db, $uid, $courseId)['transcript'];
} elseif (in_array($role, ['admin', 'superadmin'], true)) {
    require_once __DIR__ . '/../../app/v1/services/CapstoneStatus.php';
    $uid = (int) ($_GET['student'] ?? 0);
    if (!$uid || !cap_admin_may_preview_transcript($db, $uid, $courseId)) cap_tr_deny('Not allowed. The tutor has not allowed a transcript preview.');
    $wordsOnly = true;
} else {
    cap_tr_deny();
}

$d = cap_letter_data($db, $uid, $courseId);
if (!$d) cap_tr_deny('Not allowed.');
$rows = $wordsOnly ? [] : cap_transcript_rows($db, $uid, $courseId);
$ready = cap_certificate_readiness($db, $uid, $courseId);
header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Transcript · Dazu Training Institute</title>
<style>body{margin:0;background:#f4f5f1;font-family:Poppins,system-ui,sans-serif;color:#373435}main{max-width:760px;margin:0 auto;padding:24px 16px}
.card{background:#fff;border-radius:16px;padding:22px;margin-bottom:14px;box-shadow:0 2px 10px rgba(0,0,0,.05)}h1{font-size:1.3rem;margin:0 0 4px}.m{color:#6b7067;font-size:.85rem}
.pill{display:inline-block;background:#e7f4e1;color:#245c12;border-radius:999px;padding:3px 12px;font-size:.75rem;font-weight:700}.pill.d{background:#fff4e5;color:#9a4b00}
table{width:100%;border-collapse:collapse;font-size:.88rem}td,th{padding:7px 4px;border-bottom:1px solid #eee;text-align:left}th{font-size:.72rem;text-transform:uppercase;color:#6b7067}.g{color:#44A024;font-weight:700}</style></head><body><main>
<div class="card">
  <span class="pill<?php echo $draft ? ' d' : ''; ?>"><?php echo $draft ? 'DRAFT: not released to the student yet' : 'Released'; ?></span>
  <h1 style="margin-top:10px"><?php echo $e($d['name']); ?></h1>
  <div class="m"><?php echo $e($d['course']); ?> · <?php echo $e($d['registration_no']); ?></div>
  <p class="m" style="margin:10px 0 0">Started <?php echo $e($d['start'] ? date('j M Y', strtotime($d['start'])) : '–'); ?> · Finished <?php echo $e($d['finish'] ? date('j M Y', strtotime($d['finish'])) : '–'); ?>
    <?php echo $d['months'] !== null ? '· ' . $e(rtrim(rtrim(number_format((float) $d['months'], 1), '0'), '.')) . ' months' . ($d['shortened'] ? ' (planned ' . (int) $d['planned'] . ')' : '') : ''; ?></p>
</div>
<div class="card"><div class="g">Capstone</div>
  <?php if ($wordsOnly): ?>
    <div style="margin-top:6px"><?php echo $e($d['capstone_title'] ?: 'Capstone'); ?> · Result: <strong><?php echo $e($d['result'] ?: 'Not marked yet'); ?></strong></div>
  <?php elseif (!$rows): ?><div class="m" style="margin-top:6px">No capstone result yet.</div>
  <?php else: ?>
    <table><tr><th>Try</th><th>Project</th><th>Docs</th><th>Presentation</th><th>Time</th><th>Total</th><th>Result</th></tr>
    <?php foreach ($rows as $r): if (!$r['released']) continue; ?>
      <tr><td><?php echo (int) $r['attempt_no'] > 1 ? 'Retake ' . ((int) $r['attempt_no'] - 1) : 'First'; ?></td><td><?php echo $e(cap_pretty_marks_plain($r['p_project'])); ?></td><td><?php echo $e(cap_pretty_marks_plain($r['p_docs'])); ?></td>
        <td><?php echo $e(cap_pretty_marks_plain($r['p_present'])); ?></td><td><?php echo $e(cap_pretty_marks_plain($r['p_time'])); ?></td><td><strong><?php echo $e(cap_pretty_marks_plain($r['total'])); ?></strong></td><td><?php echo $e($r['result']); ?></td></tr>
    <?php endforeach; ?></table>
  <?php endif; ?>
</div>
<div class="card m">The full transcript layout is built separately. This page shows that the transcript is released and what it will be built from.
  <?php if ($role === 'student'): ?><br><a href="<?php echo $e(url('capstone/letter.php?course=' . $courseId . '&dl=1')); ?>">Download my letter of recommendation (PDF)</a><?php endif; ?></div>
</main></body></html>
<?php
function cap_pretty_marks_plain($v): string
{
    return rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.') ?: '0';
}
