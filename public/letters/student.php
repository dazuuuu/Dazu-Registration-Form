<?php
// Letter of recommendation (every student). ?reg=<registration id>[&pdf=1]
//   student: only their own, and only once the final letter is made (course finished + fees paid in full).
//   tutor:   their own student's letter at any time: a DRAFT until it is final. For a course shorter than 12 months the tutor types the
//            course topics here once (used on every letter of the course). Nobody can edit a final letter.
//   admin:   status words only.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/Session.php';
require_once __DIR__ . '/../../app/v1/services/StudentLetterService.php';

use Models\AssignmentModel;

Middleware::requireAuth();
$db = \Models\Connection::getInstance()->getConnection();
$me = (int) ($_SESSION['user_id'] ?? 0);
$role = (string) SessionModel::get('role');
$regId = (int) ($_GET['reg'] ?? 0);
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

function sl_deny(string $why = 'Not allowed.'): void
{
    http_response_code(403);
    exit($why);
}

// Role check first.
if (!sl_installed($db)) sl_deny('The letter is not installed yet. An administrator must run System Updates (migration 095).');
$reg = sl_registration($db, $regId);
if (!$reg) sl_deny();
if ($role === 'student') {
    if ((int) $reg['user_id'] !== $me) sl_deny();
    $view = 'student';
} elseif ($role === 'tutor') {
    $am = new AssignmentModel($db);
    $tutor = $am->tutorByUser($me);
    if (!$tutor || !$reg['user_id'] || !$am->isTutorStudent((int) $tutor['id'], (int) $reg['user_id'])) sl_deny();
    $view = 'tutor';
} elseif (in_array($role, ['admin', 'superadmin'], true)) {
    $view = 'admin';
} else {
    sl_deny();
}

sl_sync($db, $regId, $me);
$status = sl_status($db, $regId);
if ($view === 'student' && $status['state'] !== 'final') {
    sl_deny('Not available yet. Your letter of recommendation is made when your course is finished and your fees are paid in full.');
}

$course = sl_course($db, (int) $reg['course_id']);
$shortCourse = !sl_long_course($course);
if ($view === 'tutor' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $flash = ['danger', 'Security check failed. Please refresh and try again.'];
    } elseif (($_POST['action'] ?? '') === 'topics' && $shortCourse) {
        $r = sl_course_topics_save($db, (int) $reg['course_id'], (string) ($_POST['topics'] ?? ''), $me);
        $flash = $r['ok'] ? ['success', 'Topics saved. They are used on the letters of this course that are made from now on.'] : ['danger', $r['error']];
    } else {
        $flash = ['danger', 'Nothing to save.'];
    }
    $_SESSION['sl_flash'] = $flash;
    header('Location: ' . url('letters/student.php?reg=' . $regId));
    exit;
}
$flash = $_SESSION['sl_flash'] ?? null;
unset($_SESSION['sl_flash']);

header('X-Robots-Tag: noindex');
header('Cache-Control: private, no-store');

if ($view === 'admin') {
    $l = $status['letter'];
    ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>Letter status</title>
<style>body{margin:0;background:#f4f5f1;font-family:Poppins,system-ui,sans-serif;color:#373435}main{max-width:560px;margin:0 auto;padding:32px 16px}.card{background:#fff;border-radius:14px;padding:22px;box-shadow:0 2px 10px rgba(0,0,0,.05)}.k{font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:#6b7067;margin-top:12px}.v{font-weight:600}</style></head>
<body><main><div class="card"><h1 style="font-size:1.2rem;margin:0">Letter of recommendation</h1>
<div class="k">Student No</div><div class="v"><?php echo $e($reg['registration_no']); ?></div>
<div class="k">Status</div><div class="v"><?php echo $e($status['word']); ?></div>
<?php if ($l): ?><div class="k">Issued on</div><div class="v"><?php echo $e(date('j F Y', strtotime($l['generated_at']))); ?></div><?php endif; ?>
</div></main></body></html><?php
    exit;
}

require_once __DIR__ . '/../../app/v1/helpers/student_letter_pdf.php';
$d = sl_print_data($db, $status['letter'], $status['letter'] ? null : sl_build($db, $reg));

if (isset($_GET['pdf'])) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (isset($_GET['dl']) ? 'attachment' : 'inline') . '; filename="Letter-of-Recommendation-' . preg_replace('/[^A-Za-z0-9-]/', '-', (string) $reg['registration_no']) . ($d['draft'] ? '-DRAFT' : '') . '.pdf"');
    echo sl_pdf($d);
    exit;
}
$pdfUrl = url('letters/student.php?reg=' . $regId . '&pdf=1&dl=1');
$cond = $status['conditions'];
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Letter of recommendation · Dazu Training Institute</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{margin:0;background:#eef0ec;font-family:Poppins,system-ui,sans-serif;color:#373435}
.bar{max-width:210mm;margin:0 auto;padding:16px}
.box{background:#fff;border-radius:12px;padding:16px;margin-bottom:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);font-size:.88rem}
.pill{display:inline-block;border-radius:999px;padding:3px 12px;font-size:.75rem;font-weight:700;background:#e7f4e1;color:#245c12}.pill.d{background:#fff4e5;color:#9a4b00}
.btn{display:inline-block;background:#44A024;color:#fff;border:0;border-radius:8px;padding:8px 14px;font:inherit;font-weight:600;text-decoration:none;cursor:pointer}
.btn.o{background:#fff;color:#373435;border:1px solid #cfd4cb}
textarea{font:inherit;border:1px solid #cfd4cb;border-radius:8px;padding:8px 10px;width:100%;box-sizing:border-box}
.ok{color:#245c12}.no{color:#9a4b00}
.alert{border-radius:8px;padding:10px 12px;margin-bottom:12px}.alert.success{background:#e7f4e1}.alert.danger{background:#fde8e8}
.paper{overflow-x:auto;padding:0 16px 32px}.paper .sl-sheet{margin:0 auto;box-shadow:0 6px 24px rgba(0,0,0,.12)}
@media print{body{background:#fff}.bar{display:none}.paper{padding:0}.paper .sl-sheet{box-shadow:none}@page{size:A4;margin:0}}
</style></head><body>
<div class="bar">
  <?php if ($flash): ?><div class="alert <?php echo $e($flash[0]); ?>"><?php echo $e($flash[1]); ?></div><?php endif; ?>
  <div class="box">
    <?php if ($status['state'] === 'final'): ?>
      <span class="pill">Issued <?php echo $e(date('j M Y', strtotime($status['letter']['generated_at']))); ?> · final</span>
    <?php else: ?>
      <span class="pill d">DRAFT: the student cannot see this yet</span>
      <p style="margin:10px 0 4px">The final letter is made by itself when both are true:</p>
      <?php foreach (['finished', 'fees'] as $k): $c = $cond[$k]; ?>
        <div class="<?php echo $c['ok'] ? 'ok' : 'no'; ?>"><?php echo $c['ok'] ? '✓' : '•'; ?> <?php echo $e($c['label']); ?><?php echo $c['ok'] ? '' : ': ' . $e($c['reason']); ?></div>
      <?php endforeach; ?>
      <?php if ($status['missing']): ?><div class="no">• The office must add <?php echo $e(implode(' and ', $status['missing'])); ?> in Settings.</div><?php endif; ?>
    <?php endif; ?>
    <div style="margin-top:12px"><a class="btn" href="<?php echo $e($pdfUrl); ?>">Download PDF</a> <button class="btn o" type="button" onclick="window.print()">Print</button></div>
  </div>
  <?php if ($view === 'tutor' && $shortCourse): ?>
  <div class="box">
    <b>Topics of <?php echo $e($course['title'] ?? 'this course'); ?></b>
    <p style="margin:4px 0 8px;color:#6b7067">Type them once, one per line. Every letter of this course uses the first four in the sentence "hands-on training in …". With no topics, the sentence uses the course name.</p>
    <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="action" value="topics">
      <textarea name="topics" rows="4" maxlength="1200"><?php echo $e(implode("\n", sl_course_topics($db, (int) $reg['course_id']))); ?></textarea>
      <div style="margin-top:8px"><button class="btn">Save topics</button></div>
    </form>
  </div>
  <?php endif; ?>
</div>
<div class="paper"><?php echo sl_html($d, false); ?></div>
</body></html>
