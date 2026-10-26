<?php
// Tutor: one assignment and its students (spec 8.5). Grade, decide requests, unlock, extend for one student, skip, export CSV.
require __DIR__ . '/../../components/assignments/tutor_boot.php';

use Models\AssignmentModel;

require_once __DIR__ . '/../../../app/v1/services/SubjectService.php';

$id = (int) ($_GET['id'] ?? 0);
$a = $am->getForTutor($id, $tutorId);
if (!$a) asg_back('index.php', null, 'Assignment not found.');
$self = 'view.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    asg_csrf_or_back($self);
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $r = $am->deleteOrArchive($id, $me, false);
        $r ? asg_back('index.php', 'Assignment deleted.') : asg_back($self, null, 'Students have already submitted work, so it cannot be deleted. Use Archive instead.');
    }
    if ($action === 'archive') {
        $am->setArchived($id, true, $me);
        asg_back('index.php', 'Assignment archived. Its submissions and grades are kept.');
    }
    if ($action === 'release_grades') {
        if (!AssignmentModel::gradesHeld($a)) asg_back($self, null, 'Grades for this assignment are not held.');
        $am->releaseGradesNow($id, $me);
        $n = (new AssignmentNotifier($pdo, $am))->gradesReleased($am->get($id));
        AssignmentNotifier::flush();
        asg_back($self, 'Grades released. ' . $n . ' student' . ($n === 1 ? ' was' : 's were') . ' told.');
    }
    if ($action === 'nudge_all' || $action === 'nudge') {
        $who = $action === 'nudge_all' ? $am->recipientIds($id) : [(int) ($_POST['student'] ?? 0)];
        $notifier = new AssignmentNotifier($pdo, $am);
        $sent = $skipped = 0;
        foreach ($who as $nu) {
            if (!in_array($nu, $am->recipientIds($id), true) || !$am->isTutorStudent($tutorId, $nu)) continue;
            $sub = $am->submission($id, $nu);
            if ($sub && $sub['submitted_at']) continue;
            if ($am->recordNudge($id, $nu, $tutorId, $me)) { $notifier->nudge($a, $nu); $sent++; } else { $skipped++; }
        }
        asg_back($self, $sent ? 'Nudge sent to ' . $sent . ' student' . ($sent === 1 ? '' : 's') . '.' . ($skipped ? ' ' . $skipped . ' already nudged in the last 6 hours.' : '') : null,
            $sent ? null : 'Nobody to nudge. Students can be nudged once every 6 hours.');
    }
    $uid = (int) ($_POST['student'] ?? 0);
    if (!in_array($uid, $am->recipientIds($id), true) || !$am->isTutorStudent($tutorId, $uid)) asg_back($self, null, 'That student is not on this assignment.');
    $notifier = new AssignmentNotifier($pdo, $am);
    if ($action === 'unlock' || $action === 'extend') {
        $due = AssignmentModel::toUtc($_POST['due'] ?? '');
        if (!$due || AssignmentModel::ts($due) <= time()) asg_back($self, null, 'Pick a new due date in the future.');
        $action === 'unlock' ? $am->unlock($id, $uid, $due, $me) : $am->setStudentDue($id, $uid, $due, $me, 'extend');
        $notifier->decided($a, $uid, $action === 'unlock' ? 'unlock' : 'extension', true, $due, trim((string) ($_POST['note'] ?? '')) ?: null);
        asg_back($self, ($action === 'unlock' ? 'Unlocked' : 'Due date changed') . ' for ' . $am->student($uid)['full_name'] . '. They have been emailed.');
    }
    if ($action === 'skip') {
        $opened = $am->skip($a, $uid, $me);
        $notifier->unlocked($uid, $opened);
        asg_back($self, 'Skipped for ' . $am->student($uid)['full_name'] . '. Their next assignment is open.');
    }
    asg_back($self);
}

$rows = $am->assignmentStudents($a);
$counts = array_count_values(array_map(fn($r) => $r['item']['state'], $rows));

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="assignment-' . $id . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Student', 'Registration no', 'Status', 'Submitted (EAT)', 'Version', 'Late', 'Marks', 'Out of', 'Result', 'Content', 'Due for student (EAT)'], ",", "\"", "");
    foreach ($rows as $r) {
        $s = $r['sub'];
        $i = $r['item'];
        fputcsv($out, [
            $r['student']['full_name'], $r['student']['registration_no'], AssignmentModel::STATES[$i['state']][0],
            $s && $s['submitted_at'] ? AssignmentModel::local($s['submitted_at'], 'Y-m-d H:i') : '', $s['version'] ?? '',
            !empty($s['is_late']) ? 'Yes' : '', $i['state'] === 'graded' && $a['grading_mode'] === 'marks' ? (float) $s['score'] : '',
            $a['grading_mode'] === 'marks' ? (int) $a['max_score'] : '', $i['state'] === 'graded' ? (string) $s['grade_label'] : '',
            AssignmentModel::contentSummary($s), AssignmentModel::local($i['due'], 'Y-m-d H:i'),
        ], ",", "\"", "");
    }
    exit;
}

$kpi = ['submitted' => 0, 'late' => 0, 'graded' => 0, 'returned' => 0];
$notSent = $lateList = [];
$markSum = $markN = 0;
foreach ($rows as $ruid => $r) {
    $sb = $r['sub'];
    if ($sb && $sb['submitted_at']) {
        $kpi['submitted']++;
        if (!empty($sb['is_late'])) { $kpi['late']++; $lateList[$ruid] = $r; }
        if ($sb['status'] === 'graded') { $kpi['graded']++; if ($a['grading_mode'] === 'marks' && $sb['score'] !== null) { $markSum += (float) $sb['score']; $markN++; } }
        if ($sb['status'] === 'returned') $kpi['returned']++;
    } elseif (!(int) ($a['no_submission'] ?? 0) && !in_array($r['item']['state'], ['skipped', 'locked'], true)) {
        $notSent[$ruid] = $r;
    }
}
$insights = $am->insights($id);
$requests = [];
foreach ($am->requests($tutorId, 'pending', $id) as $q) $requests[(int) $q['student_user_id']] = $q;
$num = $am->number($a);
$page_title = AssignmentModel::noun($a['kind'] ?? 'daily') . ' ' . $num;
$subjCard = subj_card($pdo, $a, array_keys($rows));
$defaultUnlock = AssignmentModel::localInput(gmdate('Y-m-d H:i:s', time() + 48 * 3600));
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">← Assignments</a>
<div class="card-tutor p-3 p-md-4 mb-3">
    <div class="d-flex flex-wrap justify-content-between gap-2">
        <div>
            <h4 class="mb-1"><?php echo AssignmentModel::noun($a['kind'] ?? 'daily'); ?> <?php echo $num; ?>: <?php echo asg_e($a['title']); ?>
                <?php if ($a['status'] === 'draft'): ?><span class="badge bg-secondary align-middle"><?php echo !empty($a['publish_at']) ? 'SCHEDULED ' . asg_e(AssignmentModel::local($a['publish_at'], 'D j M, H:i')) : 'DRAFT'; ?></span><?php endif; ?>
                <?php if (AssignmentModel::gradesHeld($a)): ?><span class="badge bg-info text-dark align-middle"><i class="fas fa-lock"></i> Grades held until <?php echo asg_e(AssignmentModel::local($a['release_grades_at'], 'D j M, H:i')); ?></span><?php endif; ?></h4>
            <div class="small text-muted">Due <?php echo asg_e(AssignmentModel::local($a['due_at'])); ?> · <?php echo asg_e($a['course_title'] ?: 'No course'); ?> · <?php echo asg_e($a['types_text']); ?> ·
                <?php echo $a['grading_mode'] === 'marks' ? 'Marks out of ' . (int) $a['max_score'] : asg_e(AssignmentModel::GRADING_MODES[$a['grading_mode']]); ?>
                · <?php echo $a['sequential_lock'] ? 'Opens in order' : 'Always open'; ?> · <?php echo $a['close_after_due'] ? 'Closes at the due time' : 'Late work accepted'; ?></div>
        </div>
        <div class="d-flex gap-2 align-items-start flex-wrap">
            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-outline-dark btn-sm">Edit</a>
            <a href="<?php echo $self; ?>&export=csv" class="btn btn-outline-dark btn-sm"><i class="fas fa-download me-1"></i>Export CSV</a>
            <?php if ($a['status'] === 'draft'): ?><a href="edit.php?id=<?php echo $id; ?>" class="btn btn-brand btn-sm">Publish…</a><?php endif; ?>
            <?php if (AssignmentModel::gradesHeld($a)): ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Release the grades to students now?')"><?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="release_grades"><button class="btn btn-brand btn-sm"><i class="fas fa-lock-open me-1"></i>Release grades now</button></form>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <?php foreach (['submitted' => 'submitted', 'graded' => 'graded', 'returned' => 'returned', 'in_progress' => 'in progress', 'open' => 'open', 'locked' => 'locked', 'closed' => 'closed', 'extension' => 'extension asked', 'skipped' => 'skipped'] as $st => $lbl): ?>
            <?php if (!empty($counts[$st])) echo asg_chip($st, $counts[$st] . ' ' . $lbl); ?>
        <?php endforeach; ?>
        <?php if (!$rows): ?><span class="small text-muted">No students on this assignment yet.</span><?php endif; ?>
    </div>
</div>

<?php if ($subjCard): $sj = $subjCard['subject']; ?>
<div class="card-tutor p-3 mb-3" id="subjectCard">
    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
        <div><h6 class="fw-bold mb-0"><?php echo asg_e($sj['name']); ?> now</h6>
            <div class="small text-muted">Subject mark is worked out by itself from released marks. <?php echo (int) $subjCard['assignments']; ?> assignment<?php echo $subjCard['assignments'] === 1 ? '' : 's'; ?> and <?php echo (int) $subjCard['monthly']; ?> of <?php echo (int) $sj['monthly_expected']; ?> monthly project<?php echo (int) $sj['monthly_expected'] === 1 ? '' : 's'; ?> set.</div></div>
        <a href="subjects.php?course=<?php echo (int) $sj['course_id']; ?>" class="btn btn-outline-secondary btn-sm">Subjects</a>
    </div>
    <?php if ($subjCard['students']): ?>
    <div class="table-responsive mt-2"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Student</th><th>Marked</th><th>Subject mark</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($subjCard['students'] as $suid => $m): ?><tr>
            <td class="small"><?php echo asg_e($rows[$suid]['student']['full_name'] ?? ''); ?></td>
            <td class="small"><?php echo (int) $m['assignments_marked']; ?> assignment<?php echo $m['assignments_marked'] === 1 ? '' : 's'; ?>, <?php echo (int) $m['monthly_marked']; ?> of <?php echo (int) $m['monthly_expected']; ?> monthly</td>
            <td class="small fw-semibold"><?php echo asg_e($m['display']); ?><?php echo $m['word'] ? ' · ' . asg_e($m['word']) : ''; ?></td>
            <td><span class="badge <?php echo $m['status'] === 'Final' ? 'bg-success' : ($m['status'] === 'In progress' ? 'bg-warning text-dark' : 'bg-secondary'); ?>"><?php echo asg_e($m['status']); ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="row g-2 mb-3">
    <?php foreach ([['Submitted', $kpi['submitted'] . ' / ' . count($rows), count($rows) ? round($kpi['submitted'] / count($rows) * 100) . '%' : ''],
                    ['Late', $kpi['late'], 'labelled Late, no penalty'], ['Graded', $kpi['graded'], max(0, $kpi['submitted'] - $kpi['graded'] - $kpi['returned']) . ' waiting'],
                    ['Returned', $kpi['returned'], 'waiting for resubmission'],
                    ['Average', $markN ? round($markSum / $markN, 1) . ' / ' . (int) $a['max_score'] : '–', 'graded so far']] as [$k, $v, $sub]): ?>
        <div class="col-6 col-md"><div class="card-tutor p-3 h-100"><div class="small text-muted"><?php echo asg_e($k); ?></div><div class="fs-4 fw-bold"><?php echo asg_e((string) $v); ?></div><div class="small text-muted"><?php echo asg_e($sub); ?></div></div></div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
<div class="col-lg-6">
    <div class="card-tutor p-3 mb-3" id="notsent">
        <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="fw-bold mb-0">Not submitted yet (<?php echo count($notSent); ?>)</h6>
            <?php if ($notSent): ?><form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="nudge_all"><button class="btn btn-sm btn-brand"><i class="fas fa-bell me-1"></i>Nudge all <?php echo count($notSent); ?></button></form><?php endif; ?></div>
        <?php if (!$notSent): ?><div class="small text-muted">Everyone has sent it.</div><?php endif; ?>
        <?php foreach ($notSent as $nuid => $r): ?>
            <div class="d-flex align-items-center gap-2 py-1 border-bottom flex-wrap"><span class="flex-grow-1 small fw-semibold"><?php echo asg_e($r['student']['full_name']); ?></span>
                <span class="asg-chip st-open"><?php echo asg_e(AssignmentModel::dueText($r['item']['due'])); ?></span>
                <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="nudge"><input type="hidden" name="student" value="<?php echo (int) $nuid; ?>"><button class="btn btn-sm btn-outline-secondary">Nudge</button></form>
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#dueModal" data-action="extend" data-student="<?php echo (int) $nuid; ?>" data-name="<?php echo asg_e($r['student']['full_name']); ?>">More time</button></div>
        <?php endforeach; ?>
    </div>
    <?php if ($lateList): ?>
    <div class="card-tutor p-3"><div class="d-flex justify-content-between"><h6 class="fw-bold">Late (<?php echo count($lateList); ?>)</h6><span class="small text-muted">Marked Late. No marks taken off.</span></div>
        <?php foreach ($lateList as $r): ?><div class="d-flex align-items-center gap-2 py-1 border-bottom"><span class="flex-grow-1 small fw-semibold"><?php echo asg_e($r['student']['full_name']); ?></span>
            <a href="../submissions/grade.php?id=<?php echo (int) $r['sub']['id']; ?>" class="btn btn-sm btn-brand">Grade</a></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<div class="col-lg-6">
    <?php if ($insights): ?>
    <div class="card-tutor p-3">
        <h6 class="fw-bold mb-0">Where marks are lost</h6><div class="small text-muted mb-2">From the work graded so far. Use it to decide what to re-teach.</div>
        <?php foreach ($insights as $in): $pc = (int) round($in['percent']); ?>
            <div class="d-flex justify-content-between small fw-semibold"><span><?php echo asg_e($in['name']); ?></span><span class="text-muted"><?php echo $pc; ?>% of marks</span></div>
            <div class="asg-bar mb-2"><i style="width:<?php echo $pc; ?>%;<?php echo $pc < 60 ? 'background:#e08a00' : ''; ?>"></i></div>
        <?php endforeach; ?>
        <?php foreach (array_filter($insights, fn($x) => $x['percent'] < 60) as $low): ?>
            <div class="asg-lc asg-lc-warn"><i class="fas fa-triangle-exclamation"></i><div>Most of the class lost marks on "<?php echo asg_e($low['name']); ?>". Cover it in the next class.</div></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</div>

<div class="card-tutor mb-3">
    <div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead><tr><th>Student</th><th>Status</th><th>Date</th><th>Note</th><th class="text-end"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $uid => $r): $i = $r['item']; $s = $r['sub']; $q = $requests[$uid] ?? null; ?>
            <tr>
                <td><a href="progress.php?student=<?php echo (int) $uid; ?>&course=<?php echo (int) $a['course_id']; ?>" class="fw-semibold text-decoration-none text-dark"><?php echo asg_e($r['student']['full_name']); ?></a>
                    <div class="small text-muted"><?php echo asg_e($r['student']['registration_no']); ?></div></td>
                <td><?php echo asg_chip($i['state'], $i['state'] === 'graded' ? 'Graded ' . AssignmentModel::resultText($i + ['max_score' => $a['max_score']]) : null); ?>
                    <?php if (!empty($s['is_late'])): ?><span class="asg-chip st-returned ms-1"><i class="fas fa-clock"></i>Late</span><?php endif; ?></td>
                <td class="small">
                    <?php if ($s && $s['submitted_at']): echo asg_e(AssignmentModel::local($s['submitted_at'], 'D j M, H:i'));
                    elseif ($i['state'] === 'closed'): ?>Due date passed
                    <?php elseif ($i['state'] === 'in_progress'): ?>Draft saved
                    <?php elseif ($q): echo asg_e(AssignmentModel::local($q['created_at'], 'D j M, H:i'));
                    else: echo asg_e(AssignmentModel::dueText($i['due'])); endif; ?>
                    <?php if ($i['current_due_at']): ?><div class="text-muted">Own due: <?php echo asg_e(AssignmentModel::local($i['current_due_at'], 'D j M, H:i')); ?></div><?php endif; ?>
                </td>
                <td class="small"><?php
                    if ($q && $q['type'] === 'extension') echo 'Wants ' . asg_e(AssignmentModel::local($q['requested_due_at'], 'D j M, H:i'));
                    elseif ($q) echo 'Asked to unlock';
                    elseif ($s && $s['version'] > 1 && $s['submitted_at']) echo asg_e(AssignmentModel::contentSummary($s)) . ' · version ' . (int) $s['version'];
                    else echo asg_e(AssignmentModel::contentSummary($s)); ?></td>
                <td class="text-end text-nowrap">
                    <?php if ($s && $s['submitted_at']): ?>
                        <a href="../submissions/grade.php?id=<?php echo (int) $s['id']; ?>" class="btn btn-sm <?php echo $i['state'] === 'submitted' ? 'btn-brand' : 'btn-outline-secondary'; ?>"><?php echo $i['state'] === 'submitted' ? 'Grade' : 'Open'; ?></a>
                    <?php endif; ?>
                    <?php if ($q): ?><a href="../submissions/request.php?id=<?php echo (int) $q['id']; ?>" class="btn btn-sm btn-outline-success">Decide</a><?php endif; ?>
                    <?php if ($i['state'] === 'closed'): ?>
                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#dueModal" data-action="unlock" data-student="<?php echo (int) $uid; ?>" data-name="<?php echo asg_e($r['student']['full_name']); ?>">Unlock</button>
                    <?php elseif (in_array($i['state'], ['open', 'in_progress', 'extension'], true)): ?>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#dueModal" data-action="extend" data-student="<?php echo (int) $uid; ?>" data-name="<?php echo asg_e($r['student']['full_name']); ?>">Extend</button>
                    <?php endif; ?>
                    <?php if (in_array($i['state'], ['closed', 'open', 'in_progress', 'extension', 'locked'], true)): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Skip this assignment for this student? Their next assignment will open.')"><?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="skip"><input type="hidden" name="student" value="<?php echo (int) $uid; ?>">
                            <button class="btn btn-sm btn-link text-muted">Skip</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card-tutor p-3">
            <div class="asg-section mt-0">Description</div><p><?php echo nl2br(asg_e((string) $a['description'])); ?></p>
            <div class="asg-section">Details</div><div class="asg-details"><?php echo $a['details'] ?: nl2br(asg_e((string) $a['instructions'])); ?></div>
            <?php if ($qs = $am->questions($id)): ?><div class="asg-section">Questions</div>
                <?php foreach ($qs as $n => $qq): ?><div class="asg-box mb-2"><strong>Q<?php echo $n + 1; ?></strong> <?php echo nl2br(asg_e($qq['text'])); ?></div><?php endforeach; ?>
            <?php endif; ?>
            <?php if ($atts = $am->attachments($id)): ?><div class="asg-section">Attachments</div>
                <?php foreach ($atts as $att): ?><a class="asg-pill" target="_blank" rel="noopener noreferrer" href="<?php echo asg_e($att['kind'] === 'link' ? $att['url'] : asg_files_url('att', (int) $att['id'])); ?>"><i class="fas <?php echo $att['kind'] === 'link' ? 'fa-link' : ($att['kind'] === 'pdf' ? 'fa-file-pdf' : 'fa-image'); ?>"></i><?php echo asg_e($att['label']); ?></a><?php endforeach; ?>
            <?php endif; ?>
            <?php if ($a['details'] && $a['instructions']): ?><div class="asg-section">Instructions</div><div class="asg-box"><?php echo nl2br(asg_e($a['instructions'])); ?></div><?php endif; ?>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card-tutor p-3 mb-3">
            <div class="asg-section mt-0">History</div>
            <ul class="list-unstyled small mb-0" style="max-height:260px;overflow:auto">
                <?php foreach ($am->events($id, null, 40) as $ev): if ($ev['event'] === 'email') continue; ?>
                    <li class="mb-1"><span class="text-muted"><?php echo asg_e(AssignmentModel::local($ev['created_at'], 'j M H:i')); ?></span> · <?php echo asg_e(str_replace('_', ' ', $ev['event'])); ?>
                        <?php if ($ev['student_user_id']): ?>· <?php echo asg_e($am->student((int) $ev['student_user_id'])['full_name']); ?><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="card-tutor p-3">
            <form method="POST" onsubmit="return confirm('<?php echo $am->hasSubmissions($id) ? 'Archive this assignment? Students will no longer see it. Grades are kept.' : 'Delete this assignment for good?'; ?>')"><?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="<?php echo $am->hasSubmissions($id) ? 'archive' : 'delete'; ?>">
                <button class="btn btn-sm btn-outline-danger w-100"><i class="fas <?php echo $am->hasSubmissions($id) ? 'fa-box-archive' : 'fa-trash'; ?> me-1"></i><?php echo $am->hasSubmissions($id) ? 'Archive assignment' : 'Delete assignment'; ?></button>
            </form>
            <div class="small text-muted mt-2">An assignment can be deleted only while nobody has submitted. After that it is archived, never lost.</div>
        </div>
    </div>
</div>

<div class="modal fade" id="dueModal" tabindex="-1"><div class="modal-dialog"><form method="POST" class="modal-content"><?php echo csrf_field(); ?>
    <input type="hidden" name="action"><input type="hidden" name="student">
    <div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <label class="form-label fw-semibold">New due date and time <span class="text-muted fw-normal">(East Africa Time)</span></label>
        <input type="datetime-local" name="due" class="form-control" required value="<?php echo asg_e($defaultUnlock); ?>">
        <label class="form-label fw-semibold mt-3">Note to the student <span class="text-muted fw-normal">(optional)</span></label>
        <textarea name="note" class="form-control" rows="2"></textarea>
        <div class="small text-muted mt-2">This changes the due date for this student only. They are emailed.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-brand">Save</button></div>
</form></div></div>
<?php
$content = ob_get_clean();
$extra_js = <<<'JS'
<script>
document.getElementById('dueModal').addEventListener('show.bs.modal', function (e) {
    var b = e.relatedTarget, f = this.querySelector('form');
    f.querySelector('[name=action]').value = b.dataset.action; f.querySelector('[name=student]').value = b.dataset.student;
    this.querySelector('.modal-title').textContent = (b.dataset.action === 'unlock' ? 'Unlock for ' : 'Extend the due date for ') + b.dataset.name;
});
</script>
JS;
require __DIR__ . '/../../templates/tutor/layout.php';
