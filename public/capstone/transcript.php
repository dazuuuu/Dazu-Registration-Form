<?php
// The academic transcript (courses of 12 months and longer only). ?course=<id>[&student=<user id>][&pdf=1]
//   student: opens it ONLY after it was released (finished, fees paid, capstone passed, average 50+). Released = locked.
//   tutor:   opens it for their own student at any time (a DRAFT built live from the marks until it is released), and before release
//            may change only the names and the recommendation wording.
//   admin:   status words only, never a mark.
//   anyone else, or another student changing the URL: not allowed.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/Session.php';
require_once __DIR__ . '/../../app/v1/services/TranscriptService.php';

use Models\AssignmentModel;

Middleware::requireAuth();
$db = \Models\Connection::getInstance()->getConnection();
$me = (int) ($_SESSION['user_id'] ?? 0);
$role = (string) SessionModel::get('role');
$courseId = (int) ($_GET['course'] ?? 0);
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

function tr_deny(string $why = 'Not allowed.'): void
{
    http_response_code(403);
    exit($why);
}

// Role check first: who is asking, and for which student.
if ($role === 'student') {
    $uid = $me;
    $view = 'student';
} elseif ($role === 'tutor') {
    $uid = (int) ($_GET['student'] ?? 0);
    $am = new AssignmentModel($db);
    $tutor = $am->tutorByUser($me);
    if (!$uid || !$tutor || !$am->isTutorStudent((int) $tutor['id'], $uid)) tr_deny();
    $view = 'tutor';
} elseif (in_array($role, ['admin', 'superadmin'], true)) {
    $uid = (int) ($_GET['student'] ?? 0);
    if (!$uid) tr_deny();
    $view = 'admin';
} else {
    tr_deny();
}
if (!tr_installed($db)) tr_deny('The transcript is not installed yet. An administrator must run System Updates (migration 094).');

tr_sync($db, $uid, $courseId, $me);
$status = tr_status($db, $uid, $courseId);
if ($status['state'] === 'none') tr_deny('There is no transcript for this course. Transcripts are given for courses of 12 months and longer.');
if ($view === 'student' && $status['state'] !== 'released') {
    tr_deny('Not allowed yet. Your transcript is released when your course is complete: finished, fees paid, capstone passed and course average 50 or more.');
}

// The tutor's edits: names and recommendation wording, before release only.
if ($view === 'tutor' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $flash = ['danger', 'Security check failed. Please refresh and try again.'];
    } else {
        $in = [];
        if (($_POST['action'] ?? '') === 'names') $in = ['surname' => $_POST['surname'] ?? '', 'other_names' => $_POST['other_names'] ?? ''];
        if (($_POST['action'] ?? '') === 'recommendation') $in = ['recommendation' => $_POST['recommendation'] ?? ''];
        if (($_POST['action'] ?? '') === 'recommendation_auto') $in = ['recommendation' => ''];
        $r = $in ? tr_save_draft($db, $uid, $courseId, $me, $in) : ['ok' => false, 'error' => 'Nothing to save.'];
        $flash = $r['ok'] ? ['success', 'Saved.'] : ['danger', $r['error']];
    }
    $_SESSION['tr_flash'] = $flash;
    header('Location: ' . url('capstone/transcript.php?course=' . $courseId . '&student=' . $uid));
    exit;
}
$flash = $_SESSION['tr_flash'] ?? null;
unset($_SESSION['tr_flash']);

header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

// Admin: words only.
if ($view === 'admin') {
    $rel = $status['released'];
    ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Transcript status</title>
<style>body{margin:0;background:#f4f5f1;font-family:Poppins,system-ui,sans-serif;color:#373435}main{max-width:560px;margin:0 auto;padding:32px 16px}.card{background:#fff;border-radius:14px;padding:22px;box-shadow:0 2px 10px rgba(0,0,0,.05)}.k{font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:#6b7067;margin-top:12px}.v{font-weight:600}</style></head>
<body><main><div class="card"><h1 style="font-size:1.2rem;margin:0">Transcript status</h1>
<div class="k">Status</div><div class="v"><?php echo $e($status['word']); ?></div>
<?php if ($rel): ?><div class="k">Transcript number</div><div class="v"><?php echo $e($rel['transcript_no']); ?></div>
<div class="k">Released on</div><div class="v"><?php echo $e(date('j F Y', strtotime($rel['released_at']))); ?></div><?php endif; ?>
<p style="color:#6b7067;font-size:.8rem;margin:16px 0 0">Marks are shown to the student and their tutor only.</p></div></main></body></html><?php
    exit;
}

require_once __DIR__ . '/../../app/v1/helpers/transcript_pdf.php';
$live = $status['state'] === 'released' ? null : tr_build($db, $uid, $courseId);
$d = tr_print_data($db, $status['released'], $live);

if (isset($_GET['pdf'])) {
    $name = $d['no'] ? 'Transcript-' . $d['no'] : 'Transcript-DRAFT';
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (isset($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . preg_replace('/[^A-Za-z0-9-]/', '', $name) . '.pdf"');
    echo tr_pdf($d);
    exit;
}
$pdfUrl = url('capstone/transcript.php?course=' . $courseId . ($view === 'tutor' ? '&student=' . $uid : '') . '&pdf=1&dl=1');
$cond = $status['readiness']['conditions'] ?? [];
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Transcript · Dazu Training Institute</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{margin:0;background:#eef0ec;font-family:Poppins,system-ui,sans-serif;color:#373435}
.bar{max-width:210mm;margin:0 auto;padding:16px}
.box{background:#fff;border-radius:12px;padding:16px;margin-bottom:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);font-size:.88rem}
.pill{display:inline-block;border-radius:999px;padding:3px 12px;font-size:.75rem;font-weight:700;background:#e7f4e1;color:#245c12}.pill.d{background:#fff4e5;color:#9a4b00}
.btn{display:inline-block;background:#44A024;color:#fff;border:0;border-radius:8px;padding:8px 14px;font:inherit;font-weight:600;text-decoration:none;cursor:pointer}
.btn.o{background:#fff;color:#373435;border:1px solid #cfd4cb}
input,textarea{font:inherit;border:1px solid #cfd4cb;border-radius:8px;padding:7px 10px;width:100%;box-sizing:border-box}
.row{display:flex;gap:10px;flex-wrap:wrap}.row>*{flex:1 1 200px}
.ok{color:#245c12}.no{color:#9a4b00}
.alert{border-radius:8px;padding:10px 12px;margin-bottom:12px}.alert.success{background:#e7f4e1}.alert.danger{background:#fde8e8}
.paper{overflow-x:auto;padding:0 16px 32px}
.paper .tr-sheet{margin:0 auto;box-shadow:0 6px 24px rgba(0,0,0,.12)}
@media print{body{background:#fff}.bar{display:none}.paper{padding:0}.paper .tr-sheet{box-shadow:none}@page{size:A4;margin:0}}
</style></head><body>
<div class="bar">
  <?php if ($flash): ?><div class="alert <?php echo $e($flash[0]); ?>"><?php echo $e($flash[1]); ?></div><?php endif; ?>
  <div class="box">
    <?php if ($status['state'] === 'released'): ?>
      <span class="pill">Released <?php echo $e(date('j M Y', strtotime($status['released']['released_at']))); ?> · locked</span>
    <?php else: ?>
      <span class="pill d">DRAFT: not released to the student yet</span>
      <p style="margin:10px 0 4px">The marks below are worked out from the released assignment, monthly project and capstone marks. Nobody can type a mark here. It is released by itself when all of these are true:</p>
      <?php foreach (['finished' => 'Course finished', 'fees' => 'Fees paid', 'capstone' => 'Capstone passed', 'average' => 'Course average 50 or more'] as $k => $label): $c = $cond[$k] ?? null; ?>
        <div class="<?php echo $c && $c['ok'] ? 'ok' : 'no'; ?>"><?php echo $c && $c['ok'] ? '✓' : '•'; ?> <?php echo $e($label); ?><?php echo $c && !$c['ok'] ? ': ' . $e($c['reason']) : ''; ?></div>
      <?php endforeach; ?>
      <?php if ($status['missing']): ?><div class="no">• The office must add <?php echo $e(implode(' and ', $status['missing'])); ?> in Settings → Transcript.</div><?php endif; ?>
    <?php endif; ?>
    <div style="margin-top:12px" class="row" ><div style="flex:0 0 auto"><a class="btn" href="<?php echo $e($pdfUrl); ?>">Download PDF</a> <button class="btn o" type="button" onclick="window.print()">Print</button></div></div>
  </div>

  <?php if ($view === 'tutor' && $status['state'] !== 'released'): ?>
  <div class="box">
    <b>Names on the transcript</b>
    <form method="post" class="row" style="margin-top:8px"><?php echo csrf_field(); ?><input type="hidden" name="action" value="names">
      <label>Surname<input name="surname" maxlength="80" required value="<?php echo $e($d['surname']); ?>"></label>
      <label>Other names<input name="other_names" maxlength="120" value="<?php echo $e($d['other_names']); ?>"></label>
      <div style="flex:0 0 auto;align-self:flex-end"><button class="btn">Save names</button></div>
    </form>
  </div>
  <div class="box">
    <b>Recommendation</b> <span style="color:#6b7067">(<?php echo $d['recommendation_custom'] ? 'your wording' : 'written by the system'; ?>)</span>
    <form method="post" style="margin-top:8px"><?php echo csrf_field(); ?><input type="hidden" name="action" value="recommendation">
      <textarea name="recommendation" rows="2" maxlength="300"><?php echo $e($d['recommendation']); ?></textarea>
      <div style="margin-top:8px"><button class="btn">Save wording</button></div>
    </form>
    <?php if ($d['recommendation_custom']): ?>
      <form method="post" style="margin-top:6px"><?php echo csrf_field(); ?><input type="hidden" name="action" value="recommendation_auto"><button class="btn o">Use the system's wording again</button></form>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<div class="paper"><?php echo tr_html($d, false); ?></div>
</body></html>
