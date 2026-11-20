<?php
// Admin: students who are no longer with their tutor: completed (marked completed, studies completed or certificate issued) and paused.
// Tutors no longer see these students anywhere and are not paid for them. A paused student can be resumed here (pay starts again).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/models/PayrollModel.php';

use Models\PayrollModel;
use Models\SessionModel;

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$page_title = 'Completed & paused students';
$breadcrumbs = [['label' => 'Students', 'url' => url('admin/students/index.php')], ['label' => 'Completed & paused', 'active' => true]];
$payroll = new PayrollModel($db);
$today = (new DateTimeImmutable('now', new DateTimeZone('Africa/Nairobi')))->format('Y-m-d');

if (!$payroll->isInstalled()) {
    $content = '<div class="alert alert-secondary">Tutor payroll is not installed yet, so there are no completed or paused students to show.</div>';
    require __DIR__ . '/../../templates/admin/layout.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please refresh and try again.');
    } elseif (($_POST['action'] ?? '') === 'resume') {
        $tid = (int) ($_POST['tutor_id'] ?? 0);
        $p = PayrollModel::currentPause($payroll->getPauses($tid), (int) ($_POST['user_id'] ?? 0));
        $ok = $p && $payroll->endPause((int) $p['id'], $tid, $today);
        SessionModel::setFlash($ok ? 'success' : 'error', $ok ? 'Student resumed. They are back on the tutor\'s list and pay starts again.' : 'This student is not paused.');
    }
    header('Location: ' . url('admin/students/finished.php'));
    exit;
}

$tutors = $db->query("SELECT t.id, COALESCE(NULLIF(t.display_name, ''), NULLIF(TRIM(CONCAT_WS(' ', up.first_name, up.last_name)), ''), u.username) AS name
                      FROM tutors t JOIN users u ON u.id = t.user_id LEFT JOIN user_profiles up ON up.user_id = u.id ORDER BY name")->fetchAll(PDO::FETCH_KEY_PAIR);
$completed = $paused = [];
foreach ($tutors as $tid => $tname) {
    $pauses = $payroll->getPauses((int) $tid);
    foreach ($payroll->getCurrentStudents((int) $tid) as $s) {
        $row = ['tutor_id' => (int) $tid, 'tutor' => $tname, 'user_id' => (int) $s['user_id'], 'name' => $s['student_name'],
                'course' => $s['course_title'] ?? '', 'reg' => $s['registration_no'] ?? ''];
        if (!empty($s['finished_on'])) {
            $completed[] = $row + ['since' => $s['finished_on']];
        } elseif (($p = PayrollModel::currentPause($pauses, (int) $s['user_id'])) && $p['state'] === 'active') {
            $paused[] = $row + ['since' => $p['start_date'], 'note' => $p['note'] ?? ''];
        }
    }
}
usort($completed, fn($a, $b) => strcmp($b['since'], $a['since']));
usort($paused, fn($a, $b) => strcmp($b['since'], $a['since']));
$tab = ($_GET['tab'] ?? '') === 'paused' ? 'paused' : 'completed';
$list = $tab === 'paused' ? $paused : $completed;

ob_start();
?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="?tab=completed" class="btn btn-sm <?= $tab === 'completed' ? 'btn-success' : 'btn-outline-secondary' ?>"><i class="fas fa-award me-1"></i>Completed <span class="badge bg-light text-dark ms-1"><?= count($completed) ?></span></a>
    <a href="?tab=paused" class="btn btn-sm <?= $tab === 'paused' ? 'btn-success' : 'btn-outline-secondary' ?>"><i class="fas fa-pause me-1"></i>Paused <span class="badge bg-light text-dark ms-1"><?= count($paused) ?></span></a>
</div>
<p class="small text-muted">These students no longer show anywhere in their tutor's portal (students, progress, assignments, grading, calendar), and the tutor is not paid the monthly amount for them.
    <?= $tab === 'paused' ? 'Resume a student to put them back on the tutor\'s list.' : '' ?></p>
<div class="mb-3" style="max-width:380px"><input type="search" class="form-control" id="fsFind" placeholder="Search a student or tutor…"></div>
<div class="card"><div class="table-responsive">
<table class="table align-middle mb-0">
    <thead><tr><th>Student</th><th>Course</th><th>Tutor</th><th><?= $tab === 'paused' ? 'Paused since' : 'Completed on' ?></th><?php if ($tab === 'paused'): ?><th></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($list as $r): ?>
        <tr class="fs-row" data-q="<?= $e(mb_strtolower($r['name'] . ' ' . $r['tutor'] . ' ' . $r['reg'])) ?>">
            <td><span class="fw-semibold"><?= $e($r['name']) ?></span><div class="small text-muted"><?= $e($r['reg']) ?></div></td>
            <td class="small"><?= $e($r['course']) ?></td>
            <td class="small"><?= $e($r['tutor']) ?></td>
            <td class="small"><?= $e(date('j M Y', strtotime($r['since']))) ?><?= !empty($r['note']) ? '<div class="text-muted">' . $e($r['note']) . '</div>' : '' ?></td>
            <?php if ($tab === 'paused'): ?>
            <td class="text-end"><form method="POST" onsubmit="return confirm('Resume this student? The tutor sees them again and is paid for them.')"><?= csrf_field() ?>
                <input type="hidden" name="action" value="resume"><input type="hidden" name="tutor_id" value="<?= $r['tutor_id'] ?>"><input type="hidden" name="user_id" value="<?= $r['user_id'] ?>">
                <button class="btn btn-sm btn-outline-success"><i class="fas fa-play me-1"></i>Resume</button></form></td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    <?php if (!$list): ?><tr><td colspan="5" class="text-center text-muted py-4">No <?= $tab ?> students.</td></tr><?php endif; ?>
    </tbody>
</table>
</div></div>
<script>
document.getElementById('fsFind').addEventListener('input', function () { var q = this.value.trim().toLowerCase();
  document.querySelectorAll('.fs-row').forEach(function (r) { r.style.display = !q || r.dataset.q.indexOf(q) > -1 ? '' : 'none'; }); });
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
