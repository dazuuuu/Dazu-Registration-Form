<?php
// Intern Helpers (tutor only): ask an active intern to help teach some of your own students.
// Private to the tutor and the intern — students and admins never see it (services/InternTeachingService.php).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/InternTeachingService.php';

Middleware::requireTutor();
if (($_SESSION['role'] ?? '') !== 'tutor' || !empty($_SESSION['preview'])) {
    // A tutor using admin rights, or a portal preview: this page is only for a signed-in tutor.
    header('Location: ' . url('tutor/dashboard/index.php'));
    exit;
}
$db = \Models\Connection::getInstance()->getConnection();
$me = (int) $_SESSION['user_id'];
$self = url('tutor/helpers/index.php');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = $self;
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
    } else {
        $aid = (int) ($_POST['assignment_id'] ?? 0);
        $tid = (int) ($_POST['topic_id'] ?? 0);
        $r = ['ok' => true];
        $ok = 'Saved.';
        switch ($_POST['act'] ?? '') {
            case 'assign':
                $r = it_assign($db, $me, (int) ($_POST['intern_id'] ?? 0), (array) ($_POST['students'] ?? []), (string) ($_POST['group_label'] ?? ''));
                if ($r['ok'] && trim((string) ($_POST['t_title'] ?? '')) !== '') {
                    it_topic_save($db, $me, (int) $r['id'], null, ['title' => $_POST['t_title'], 'subtopics' => $_POST['t_subtopics'] ?? '', 'starts_at' => $_POST['t_starts_at'] ?? '',
                                                                     'duration_min' => $_POST['t_duration_min'] ?? '', 'notes' => $_POST['t_notes'] ?? ''], []);
                }
                $ok = 'Intern assigned. Now add the topics they will teach.';
                $back = $self . (!empty($r['id']) ? '#a-' . $r['id'] : '');
                break;
            case 'group':
                $r = it_update_group($db, $me, $aid, (array) ($_POST['students'] ?? []), (string) ($_POST['group_label'] ?? ''));
                $back = $self . '#a-' . $aid;
                break;
            case 'remove':
                it_remove($db, $me, $aid);
                $ok = 'The intern was removed from this group.';
                break;
            case 'topic':
                $r = it_topic_save($db, $me, $aid, $tid ?: null, $_POST, $_FILES['file'] ?? []);
                $ok = $tid ? 'Topic updated.' : 'Topic added. The intern was told.';
                $back = $self . '#a-' . $aid;
                break;
            case 'up':
            case 'down':
                it_topic_move($db, $me, $tid, $_POST['act'] === 'up' ? -1 : 1);
                $back = $self . '#topic-' . $tid;
                break;
            case 'cancel':
                it_topic_cancel($db, $me, $tid);
                $ok = 'Topic cancelled.';
                $back = $self . '#topic-' . $tid;
                break;
            case 'reassign':
                $r = it_topic_reassign($db, $me, $tid, (int) ($_POST['to_assignment'] ?? 0));
                $ok = 'Topic moved.';
                break;
            case 'rate':
                $r = it_rate($db, $me, $tid, $_POST);
                $ok = 'Thank you — your rating was sent to the intern.';
                $back = $self . '#topic-' . $tid;
                break;
        }
        SessionModel::setFlash($r['ok'] ? 'success' : 'error', $e($r['ok'] ? $ok : $r['error']));
    }
    header('Location: ' . $back);
    exit;
}

$assignments = it_assignments_for_tutor($db, $me);
$interns = it_active_interns($db);
$students = it_tutor_students($db, $me);
$byCourse = [];
foreach ($students as $s) $byCourse[$s['course']][] = $s;
$active = array_filter($assignments, fn($a) => $a['status'] === 'active');
$stars = fn(?int $n) => '<span style="color:#f59e0b;letter-spacing:2px">' . str_repeat('★', (int) $n) . '<span style="color:#d6d8d3">' . str_repeat('★', 5 - (int) $n) . '</span></span>';
$studentPicker = function (string $name, array $checked = []) use ($byCourse, $e) {
    ob_start();
    foreach ($byCourse as $course => $list): ?>
        <div class="border rounded-3 p-2 mb-2">
            <label class="form-check fw-semibold small"><input type="checkbox" class="form-check-input" data-class-all> Whole class: <?php echo $e($course); ?></label>
            <div class="row g-1 ms-1">
            <?php foreach ($list as $s): ?>
                <div class="col-md-6"><label class="form-check small"><input type="checkbox" class="form-check-input" name="<?php echo $name; ?>[]" value="<?php echo (int) $s['id']; ?>" <?php echo in_array((int) $s['id'], $checked, true) ? 'checked' : ''; ?>> <?php echo $e($s['full_name']); ?></label></div>
            <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach;
    if (!$byCourse) echo '<div class="small text-muted">You have no students yet.</div>';
    return ob_get_clean();
};
$topicForm = function (int $aid, ?array $t = null) use ($e) {
    ob_start(); ?>
    <form method="POST" enctype="multipart/form-data" class="row g-2"><?php echo csrf_field(); ?>
        <input type="hidden" name="act" value="topic"><input type="hidden" name="assignment_id" value="<?php echo $aid; ?>"><input type="hidden" name="topic_id" value="<?php echo (int) ($t['id'] ?? 0); ?>">
        <div class="col-md-6"><input name="title" class="form-control form-control-sm" placeholder="Topic title" required maxlength="200" value="<?php echo $e($t['title'] ?? ''); ?>"></div>
        <div class="col-md-4"><input type="datetime-local" name="starts_at" class="form-control form-control-sm" value="<?php echo !empty($t['starts_at']) ? date('Y-m-d\TH:i', strtotime($t['starts_at'])) : ''; ?>"></div>
        <div class="col-md-2"><input type="number" name="duration_min" class="form-control form-control-sm" placeholder="Minutes" min="0" max="600" value="<?php echo $e($t['duration_min'] ?? ''); ?>"></div>
        <div class="col-md-6"><textarea name="subtopics" class="form-control form-control-sm" rows="3" placeholder="Subtopics — one per line"><?php echo $e($t['subtopics'] ?? ''); ?></textarea></div>
        <div class="col-md-6"><textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Notes for the intern"><?php echo $e($t['notes'] ?? ''); ?></textarea></div>
        <div class="col-md-6"><textarea name="resources" class="form-control form-control-sm" rows="2" placeholder="Resource links — one per line"><?php echo $e($t['resources'] ?? ''); ?></textarea></div>
        <div class="col-md-6"><input type="file" name="file" class="form-control form-control-sm"><?php if (!empty($t['file_name'])): ?><div class="form-text">Now: <?php echo $e($t['file_name']); ?></div><?php endif; ?></div>
        <div class="col-12"><button class="btn btn-sm btn-success"><?php echo $t ? 'Save topic' : 'Add topic'; ?></button></div>
    </form>
    <?php return ob_get_clean();
};
$page_title = 'Intern Helpers';
ob_start();
?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div><h1 class="h4 mb-1"><i class="fas fa-hands-helping text-success me-2"></i>Intern Helpers</h1>
        <div class="small text-muted">An intern helps you teach some of your students. Only you and that intern see this — not your students, not the office. You stay in charge: the intern never marks work or signs certificates, and only ever sees the students' first names.</div></div>
    <button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#assignNew"><i class="fas fa-plus me-1"></i>Assign intern</button>
</div>

<form method="POST" class="card-tutor mb-3 collapse <?php echo $assignments ? '' : 'show'; ?>" id="assignNew"><?php echo csrf_field(); ?><input type="hidden" name="act" value="assign">
    <div class="head">Assign an intern</div>
    <div class="p-3">
        <?php if (!$interns): ?><div class="alert alert-secondary mb-0"><strong>There are no active interns yet.</strong> The office adds interns under <em>Admin → Interns</em>. Once an intern is active, you can pick them here.</div>
        <?php else: ?>
        <div class="row g-2 mb-2">
            <div class="col-md-6"><label class="form-label small">Intern</label><select name="intern_id" class="form-select" required><option value="">Choose…</option>
                <?php foreach ($interns as $i): ?><option value="<?php echo (int) $i['id']; ?>"><?php echo $e($i['name']); ?><?php echo $i['department'] ? ' · ' . $e($i['department']) : ''; ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label small">Group name <span class="text-muted">(optional)</span></label><input name="group_label" class="form-control" maxlength="150" placeholder="e.g. Web Dev — evening class"></div>
        </div>
        <label class="form-label small">Which of your students or classes will the intern help with?</label>
        <?php echo $studentPicker('students'); ?>
        <div class="border rounded-3 p-3 mb-3 bg-light">
            <div class="fw-semibold small mb-2">First topic to teach <span class="text-muted fw-normal">(optional — you can add more after)</span></div>
            <div class="row g-2">
                <div class="col-md-6"><input name="t_title" class="form-control form-control-sm" placeholder="Topic title" maxlength="200"></div>
                <div class="col-md-4"><input type="datetime-local" name="t_starts_at" class="form-control form-control-sm"></div>
                <div class="col-md-2"><input type="number" name="t_duration_min" class="form-control form-control-sm" placeholder="Minutes" min="0" max="600"></div>
                <div class="col-md-6"><textarea name="t_subtopics" class="form-control form-control-sm" rows="2" placeholder="Subtopics — one per line"></textarea></div>
                <div class="col-md-6"><textarea name="t_notes" class="form-control form-control-sm" rows="2" placeholder="Notes for the intern"></textarea></div>
            </div>
        </div>
        <button class="btn btn-success">Assign intern</button>
        <?php endif; ?>
    </div>
</form>

<?php if (!$assignments): ?><div class="card-tutor p-4 text-center text-muted">No intern helpers yet.</div><?php endif; ?>
<?php foreach ($assignments as $a): $live = $a['status'] === 'active'; ?>
<div class="card-tutor mb-3 <?php echo $live ? '' : 'opacity-75'; ?>" id="a-<?php echo (int) $a['id']; ?>">
    <div class="head justify-content-between">
        <span><i class="fas fa-user-graduate me-1"></i><?php echo $e($a['intern_name']); ?><?php echo $a['group_label'] ? ' · ' . $e($a['group_label']) : ''; ?>
            <span class="badge <?php echo $live ? 'bg-success' : 'bg-secondary'; ?> ms-1"><?php echo $live ? 'Active' : 'Ended'; ?></span></span>
        <?php if ($live): ?>
        <form method="POST" class="m-0" onsubmit="return confirm('Remove this intern from the group? They lose access to these topics.')"><?php echo csrf_field(); ?>
            <input type="hidden" name="act" value="remove"><input type="hidden" name="assignment_id" value="<?php echo (int) $a['id']; ?>"><button class="btn btn-sm btn-outline-danger">Remove intern</button></form>
        <?php endif; ?>
    </div>
    <div class="p-3">
        <div class="small mb-2"><strong>Students:</strong> <?php echo $e(implode(', ', array_column($a['students'], 'full_name')) ?: '—'); ?>
            <?php if ($live): ?><a class="ms-2" data-bs-toggle="collapse" href="#g-<?php echo (int) $a['id']; ?>">Change</a><?php endif; ?></div>
        <?php if ($live): ?>
        <form method="POST" class="collapse border rounded-3 p-2 mb-3" id="g-<?php echo (int) $a['id']; ?>"><?php echo csrf_field(); ?><input type="hidden" name="act" value="group"><input type="hidden" name="assignment_id" value="<?php echo (int) $a['id']; ?>">
            <input name="group_label" class="form-control form-control-sm mb-2" value="<?php echo $e($a['group_label']); ?>" placeholder="Group name">
            <?php echo $studentPicker('students', array_map('intval', array_column($a['students'], 'id'))); ?>
            <button class="btn btn-sm btn-success">Save group</button></form>
        <?php endif; ?>

        <?php foreach ($a['topics'] as $i => $t): ?>
        <div class="border rounded-3 p-3 mb-2 <?php echo $t['status'] === 'cancelled' ? 'opacity-50' : ''; ?>" id="topic-<?php echo (int) $t['id']; ?>">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <strong class="flex-grow-1"><?php echo ($i + 1) . '. ' . $e($t['title']); ?></strong>
                <span class="badge <?php echo ['planned' => 'bg-light text-dark border', 'taught' => 'bg-success', 'cancelled' => 'bg-secondary'][$t['status']]; ?>"><?php echo ucfirst($t['status']); ?></span>
                <?php if ($live && $t['status'] === 'planned'): ?>
                    <form method="POST" class="m-0 d-flex gap-1"><?php echo csrf_field(); ?><input type="hidden" name="topic_id" value="<?php echo (int) $t['id']; ?>">
                        <button name="act" value="up" class="btn btn-sm btn-outline-secondary" title="Move up" <?php echo $i === 0 ? 'disabled' : ''; ?>><i class="fas fa-arrow-up"></i></button>
                        <button name="act" value="down" class="btn btn-sm btn-outline-secondary" title="Move down" <?php echo $i === count($a['topics']) - 1 ? 'disabled' : ''; ?>><i class="fas fa-arrow-down"></i></button>
                        <button name="act" value="cancel" class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancel this topic?')">Cancel</button></form>
                    <a class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" href="#e-<?php echo (int) $t['id']; ?>">Edit</a>
                <?php endif; ?>
            </div>
            <div class="small text-muted"><?php echo $t['starts_at'] ? date('D j M Y, g:i A', strtotime($t['starts_at'])) : 'No date yet'; ?><?php echo $t['duration_min'] ? ' · ' . (int) $t['duration_min'] . ' min' : ''; ?></div>
            <?php if ($t['subtopics']): ?><div class="small mt-1"><?php echo $e(implode(' · ', array_filter(array_map('trim', preg_split('/\r?\n/', $t['subtopics']))))); ?></div><?php endif; ?>
            <?php if ($live && $t['status'] === 'planned'): ?>
                <div class="collapse mt-2" id="e-<?php echo (int) $t['id']; ?>"><?php echo $topicForm((int) $a['id'], $t); ?>
                    <?php $others = array_filter($active, fn($x) => (int) $x['id'] !== (int) $a['id']); if ($others): ?>
                    <form method="POST" class="d-flex gap-2 mt-2"><?php echo csrf_field(); ?><input type="hidden" name="act" value="reassign"><input type="hidden" name="topic_id" value="<?php echo (int) $t['id']; ?>">
                        <select name="to_assignment" class="form-select form-select-sm" style="max-width:320px"><?php foreach ($others as $o): ?><option value="<?php echo (int) $o['id']; ?>"><?php echo $e($o['intern_name'] . ($o['group_label'] ? ' · ' . $o['group_label'] : '')); ?></option><?php endforeach; ?></select>
                        <button class="btn btn-sm btn-outline-secondary">Reassign to this intern</button></form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($t['status'] === 'taught'): ?>
                <div class="bg-light rounded-3 p-2 mt-2 small">
                    <div class="fw-semibold mb-1">Teaching report · <?php echo date('j M', strtotime($t['reported_at'] ?: $t['taught_at'])); ?></div>
                    <?php foreach (['covered' => 'Covered', 'went_well' => 'Went well', 'challenges' => 'Challenges', 'questions' => 'Questions from students'] as $k => $l): if ($t[$k]): ?>
                        <div><strong><?php echo $l; ?>:</strong> <span style="white-space:pre-line"><?php echo $e($t[$k]); ?></span></div><?php endif; endforeach; ?>
                </div>
                <form method="POST" class="mt-2 d-flex flex-wrap gap-2 align-items-end"><?php echo csrf_field(); ?><input type="hidden" name="act" value="rate"><input type="hidden" name="topic_id" value="<?php echo (int) $t['id']; ?>">
                    <?php foreach (['preparation' => 'Preparation', 'clarity' => 'Clarity', 'confidence' => 'Confidence'] as $k => $l): ?>
                        <label class="small"><?php echo $l; ?><select name="<?php echo $k; ?>" class="form-select form-select-sm" required><option value="">–</option>
                            <?php for ($n = 1; $n <= 5; $n++): ?><option value="<?php echo $n; ?>" <?php echo (int) $t[$k] === $n ? 'selected' : ''; ?>><?php echo str_repeat('★', $n); ?></option><?php endfor; ?></select></label>
                    <?php endforeach; ?>
                    <input name="comment" class="form-control form-control-sm" style="max-width:320px" placeholder="Comment for the intern" value="<?php echo $e($t['rating_comment']); ?>">
                    <button class="btn btn-sm btn-success"><?php echo $t['preparation'] !== null ? 'Update rating' : 'Rate session'; ?></button>
                </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if ($live): ?>
            <details class="mt-2"><summary class="small fw-semibold text-success">+ Add a topic</summary><div class="mt-2"><?php echo $topicForm((int) $a['id']); ?></div></details>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<script>
document.querySelectorAll('[data-class-all]').forEach(function (all) {
    var boxes = all.closest('.border').querySelectorAll('input[type=checkbox]:not([data-class-all])');
    all.checked = boxes.length && Array.prototype.every.call(boxes, function (b) { return b.checked; });
    all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); });
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
