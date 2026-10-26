<?php
// Superadmin: every tutor's assignments, submissions and grades (read-only), with the fixes an admin needs:
// archive / restore, give one student a new due date, and the grade scale used for labels.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
Middleware::requireSuperPortal();
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/assignment_files.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/models/SettingModel.php';
require_once __DIR__ . '/../../../app/v1/models/AssignmentModel.php';
require_once __DIR__ . '/../../../app/v1/services/AssignmentNotifier.php';

use Models\AssignmentModel;

$pdo = \Models\Connection::getInstance()->getConnection();
$am = new AssignmentModel($pdo);
$me = (int) $_SESSION['user_id'];
$id = (int) ($_GET['id'] ?? 0);
$self = 'index.php' . ($id ? '?id=' . $id : '');
$flash = function (string $type, string $msg) use ($self) { $_SESSION['flash'][$type] = $msg; header('Location: ' . $self); exit; };

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) $flash('error', 'Security check failed. Please refresh and try again.');
    switch ($_POST['action'] ?? '') {
        case 'scale':
            $d = (int) $_POST['distinction']; $m = (int) $_POST['merit']; $p = (int) $_POST['pass'];
            if (!($d > $m && $m > $p && $p > 0 && $d <= 100)) $flash('error', 'Use Distinction > Merit > Pass, between 1 and 100.');
            $s = new SettingModel($pdo);
            $s->set('assignment_scale_distinction', (string) $d); $s->set('assignment_scale_merit', (string) $m); $s->set('assignment_scale_pass', (string) $p);
            $am->log(null, $me, null, 'grade_scale', compact('d', 'm', 'p'));
            $flash('success', 'Grade scale saved. It applies to results published from now on.');
        case 'archive':
        case 'restore':
            $am->setArchived((int) $_POST['id'], $_POST['action'] === 'archive', $me);
            $flash('success', $_POST['action'] === 'archive' ? 'Archived. Nothing was deleted.' : 'Restored.');
        case 'due':
            $a = $am->get($id);
            $uid = (int) $_POST['student'];
            $due = AssignmentModel::toUtc($_POST['due'] ?? '');
            if (!$a || !in_array($uid, $am->recipientIds($id), true) || !$due || AssignmentModel::ts($due) <= time()) $flash('error', 'Pick a student on this assignment and a future date.');
            $am->unlock($id, $uid, $due, $me);
            (new AssignmentNotifier($pdo, $am))->decided($a, $uid, 'unlock', true, $due, 'Changed by the school office.');
            $flash('success', 'Due date changed and the student emailed.');
    }
    $flash('error', 'Unknown action.');
}

require_once __DIR__ . '/../../components/assignments/ui.php';
$page_title = 'Assignments';
ob_start();

if ($id && ($a = $am->get($id))):
    $tutor = $am->tutorById((int) $a['tutor_id']);
    $rows = $am->assignmentStudents($a);
    $breadcrumbs = [['label' => 'Assignments', 'url' => url('superadmin/assignments/index.php')], ['label' => $a['title']]];
?>
<div class="card mb-3"><div class="card-body">
    <h5 class="mb-1"><?php echo asg_e($a['title']); ?> <span class="badge bg-secondary"><?php echo asg_e(strtoupper($a['status'])); ?></span></h5>
    <div class="small text-muted">Tutor: <?php echo asg_e($tutor['full_name'] ?? '?'); ?> · <?php echo asg_e($a['course_title'] ?: 'No course'); ?> · Due <?php echo asg_e(AssignmentModel::local($a['due_at'])); ?> · <?php echo asg_e($a['types_text']); ?></div>
</div></div>
<div class="card mb-3"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Student</th><th>Status</th><th>Submitted</th><th>Result</th><th>Feedback</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $uid => $r): $s = $r['sub']; $i = $r['item']; ?>
        <tr><td><?php echo asg_e($r['student']['full_name']); ?><div class="small text-muted"><?php echo asg_e($r['student']['registration_no']); ?></div></td>
            <td><?php echo asg_chip($i['state']); ?></td>
            <td class="small"><?php echo $s && $s['submitted_at'] ? asg_e(AssignmentModel::local($s['submitted_at'])) . ' · v' . (int) $s['version'] : '—'; ?><div class="text-muted"><?php echo asg_e(AssignmentModel::contentSummary($s)); ?></div></td>
            <td class="small"><?php echo $s && $s['graded_at'] ? asg_e(AssignmentModel::resultText($s + ['grading_mode' => $a['grading_mode'], 'max_score' => $a['max_score']])) . ($s['published_at'] ? '' : ' (draft)') : '—'; ?></td>
            <td class="small" style="max-width:320px"><?php echo $s ? nl2br(asg_e(mb_strimwidth((string) $s['feedback_why'], 0, 220, '…'))) : ''; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<div class="card"><div class="card-body">
    <h6>Give one student a new due date</h6>
    <form method="POST" class="row g-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="due">
        <div class="col-md-5"><select name="student" class="form-select" required><option value="">Student…</option>
            <?php foreach ($rows as $uid => $r): ?><option value="<?php echo (int) $uid; ?>"><?php echo asg_e($r['student']['full_name']); ?> (<?php echo asg_e(AssignmentModel::STATES[$r['item']['state']][0]); ?>)</option><?php endforeach; ?></select></div>
        <div class="col-md-4"><input type="datetime-local" name="due" class="form-control" required value="<?php echo asg_e(AssignmentModel::localInput(gmdate('Y-m-d H:i:s', time() + 172800))); ?>"></div>
        <div class="col-md-3"><button class="btn btn-success w-100">Save and email</button></div>
    </form>
    <h6 class="mt-4">History</h6>
    <ul class="small mb-0"><?php foreach ($am->events($id, null, 60) as $ev): ?><li><?php echo asg_e(AssignmentModel::local($ev['created_at'])); ?> · <?php echo asg_e($ev['actor_name'] ?: 'system'); ?> · <?php echo asg_e(str_replace('_', ' ', $ev['event'])); ?></li><?php endforeach; ?></ul>
</div></div>

<?php else:
    $list = $am->listAll(null, true);
    $tutorNames = [];
    $scale = $am->gradeScale();
    $q = mb_strtolower(trim((string) ($_GET['q'] ?? '')));
?>
<div class="row g-3">
<div class="col-lg-8">
    <form class="mb-2"><input type="search" name="q" class="form-control" placeholder="Search title, course or tutor" value="<?php echo asg_e($q); ?>"></form>
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Assignment</th><th>Tutor</th><th>Students</th><th>Work</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $a):
            $tn = $tutorNames[(int) $a['tutor_id']] ??= ($am->tutorById((int) $a['tutor_id'])['full_name'] ?? '?');
            if ($q !== '' && !str_contains(mb_strtolower($a['title'] . ' ' . $a['course_title'] . ' ' . $tn), $q)) continue; ?>
            <tr><td><a href="?id=<?php echo (int) $a['id']; ?>" class="fw-semibold"><?php echo asg_e($a['title']); ?></a>
                    <?php if ($a['status'] !== 'published'): ?><span class="badge bg-secondary"><?php echo asg_e(strtoupper($a['status'])); ?></span><?php endif; ?>
                    <div class="small text-muted"><?php echo asg_e($a['course_title'] ?: 'No course'); ?> · due <?php echo asg_e(AssignmentModel::local($a['due_at'], 'j M Y')); ?></div></td>
                <td class="small"><?php echo asg_e($tn); ?></td>
                <td><?php echo (int) $a['recipients']; ?></td>
                <td class="small"><?php echo (int) $a['submitted']; ?> sent · <?php echo (int) $a['awaiting']; ?> to grade · <?php echo (int) $a['graded']; ?> graded<?php echo $a['requests'] ? ' · ' . (int) $a['requests'] . ' requests' : ''; ?></td>
                <td class="text-end"><form method="POST" class="d-inline"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                    <button name="action" value="<?php echo $a['status'] === 'archived' ? 'restore' : 'archive'; ?>" class="btn btn-sm btn-outline-secondary"><?php echo $a['status'] === 'archived' ? 'Restore' : 'Archive'; ?></button></form></td></tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="5" class="text-muted">No assignments yet.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>
</div>
<div class="col-lg-4">
    <div class="card"><div class="card-body">
        <h6>Grade scale</h6>
        <p class="small text-muted">Labels shown with marks. Each tutor sets the pass mark per assignment.</p>
        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="scale">
            <?php foreach (['distinction' => 'Distinction from', 'merit' => 'Merit from', 'pass' => 'Pass from'] as $k => $lbl): ?>
                <div class="input-group input-group-sm mb-2"><span class="input-group-text" style="width:130px"><?php echo $lbl; ?></span><input type="number" name="<?php echo $k; ?>" min="1" max="100" class="form-control" value="<?php echo (int) $scale[$k]; ?>"><span class="input-group-text">%</span></div>
            <?php endforeach; ?>
            <div class="small text-muted mb-2">Below the Pass line the label is “Not yet”.</div>
            <button class="btn btn-success btn-sm">Save scale</button>
        </form>
    </div></div>
</div>
</div>
<?php endif;
$content = ob_get_clean();
require __DIR__ . '/../../templates/superadmin/layout.php';
