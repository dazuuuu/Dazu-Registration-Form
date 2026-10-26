<?php
// Tutor: track the students of one capstone: milestones, presentation slot, returns used, status. Nudge, Mark, Open.
require __DIR__ . '/../../components/capstone/tutor_boot.php';

use Models\AssignmentModel;

$id = (int) ($_GET['id'] ?? 0);
$cap = cap_get_for_tutor($pdo, $id, $tutorId);
if (!$cap) asg_back('index.php', null, 'Capstone not found.');
$self = 'track.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    asg_csrf_or_back($self);
    $action = $_POST['action'] ?? '';
    $students = cap_students($pdo, $id);
    if ($action === 'close') {
        cap_close($pdo, $id, $tutorId, $me);
        asg_back('index.php?tab=closed', 'Closed. Students can no longer change their work.');
    }
    if ($action === 'open_now') {
        $r = cap_open_now($pdo, $id, $tutorId, $me);
        AssignmentNotifier::flush();
        asg_back($self, $r['ok'] ? 'It is open now. ' . $r['told'] . ' told.' : null, $r['ok'] ? null : $r['error']);
    }
    if ($action === 'nudge' || $action === 'nudge_all') {
        $who = $action === 'nudge_all' ? array_keys($students) : [(int) ($_POST['student'] ?? 0)];
        $n = new CapstoneNotifier($pdo);
        $sent = $skipped = 0;
        foreach ($who as $uid) {
            if (!isset($students[$uid])) continue;
            $a = cap_latest_attempt($pdo, $id, $uid);
            if ($a && in_array($a['status'], ['submitted', 'marked', 'awaiting_payment'], true)) continue;
            $recent = $pdo->prepare("SELECT 1 FROM capstone_events WHERE capstone_id = ? AND student_user_id = ? AND event = 'nudge' AND created_at >= ?");
            $recent->execute([$id, $uid, gmdate('Y-m-d H:i:s', time() - 6 * 3600)]);
            if ($recent->fetchColumn()) { $skipped++; continue; }
            cap_log($pdo, $id, $a['id'] ?? null, $me, $uid, 'nudge');
            $n->capNudge($cap, $uid);
            $sent++;
        }
        AssignmentNotifier::flush();
        asg_back($self, $sent ? 'Nudge sent to ' . $sent . ' student' . ($sent === 1 ? '' : 's') . '.' . ($skipped ? ' ' . $skipped . ' were nudged in the last 6 hours.' : '') : null,
            $sent ? null : 'Nobody to nudge now. A student can be nudged once every 6 hours.');
    }
    if ($action === 'approve_ms') {
        $ok = cap_milestone_decide($pdo, $id, $tutorId, (int) ($_POST['sub'] ?? 0), true, '', $me);
        asg_back($self, $ok ? 'Milestone approved.' : null, $ok ? null : 'Not found.');
    }
    if ($action === 'open_student') {
        $r = cap_open_for_student($pdo, $id, (int) ($_POST['student'] ?? 0), $tutorId, $me);
        asg_back($self, $r['ok'] ? 'Opened for that student.' : null, $r['ok'] ? null : $r['error']);
    }
    asg_back($self);
}

$rows = cap_tracker($pdo, $cap);
$ms = cap_milestones($pdo, $id);
$count = ['sent' => 0, 'not_started' => 0, 'to_mark' => 0, 'returned' => 0, 'marked' => 0];
foreach ($rows as $r) {
    $st = $r['state'];
    if (in_array($st, ['to_mark', 'returned', 'final_try', 'marked'], true)) $count['sent']++;
    if ($st === 'not_started') $count['not_started']++;
    if ($st === 'to_mark') $count['to_mark']++;
    if (in_array($st, ['returned', 'final_try'], true)) $count['returned']++;
    if ($st === 'marked') $count['marked']++;
}
$firstToMark = null;
foreach ($rows as $r) if ($r['state'] === 'to_mark' && $r['attempt']) { $firstToMark = (int) $r['attempt']['id']; break; }
$page_title = 'Track capstone';
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">← Capstone</a>
<div class="card-tutor p-3 p-md-4 mb-3">
    <div class="d-flex flex-wrap justify-content-between gap-2">
        <div>
            <h4 class="mb-1"><?php echo asg_e($cap['title']); ?> <?php echo cap_chip($cap['status']); ?></h4>
            <div class="small text-muted"><?php echo asg_e($cap['course_title']); ?> · <?php echo $cap['mode'] === 'team' ? 'Team' : 'Individual'; ?> ·
                <?php echo $cap['status'] === 'scheduled' && $cap['opens_at'] ? 'Opens ' . asg_e(cap_local($cap['opens_at'], 'D j M, H:i')) . ' EAT' : 'Final due ' . asg_e($cap['due_at'] ? cap_local($cap['due_at'], 'D j M, H:i') : 'not set') . ' EAT'; ?></div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-start">
            <a class="btn btn-outline-dark btn-sm" href="edit.php?id=<?php echo $id; ?>">Edit</a>
            <a class="btn btn-outline-dark btn-sm" href="slots.php?id=<?php echo $id; ?>"><i class="fas fa-calendar-day me-1"></i>Presentation slots</a>
            <a class="btn btn-outline-dark btn-sm" href="results.php?id=<?php echo $id; ?>">Results</a>
            <?php if ($firstToMark): ?><a class="btn btn-brand btn-sm" href="mark.php?id=<?php echo $firstToMark; ?>&queue=<?php echo $id; ?>"><i class="fas fa-clipboard-check me-1"></i>Marking queue (<?php echo $count['to_mark']; ?>)</a><?php endif; ?>
            <?php if ($cap['status'] !== 'open'): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Open it now for all students?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="open_now"><button class="btn btn-brand btn-sm">Open now</button></form>
            <?php else: ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Close the capstone? Students can no longer change their work.')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="close"><button class="btn btn-outline-danger btn-sm">Close capstone</button></form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-2 mb-3">
    <?php foreach ([['Submitted', $count['sent'], 'sent to you'], ['Not started', $count['not_started'], 'nothing yet'], ['To mark', $count['to_mark'], 'waiting for you'], ['Returned', $count['returned'], 'with the student'], ['Marked', $count['marked'], 'done']] as [$k, $n, $sub]): ?>
        <div class="col-6 col-md"><div class="card-tutor p-3 h-100"><div class="small text-muted"><?php echo $k; ?></div><div class="fs-4 fw-bold"><?php echo $n; ?></div><div class="small text-muted"><?php echo $sub; ?></div></div></div>
    <?php endforeach; ?>
</div>

<div class="card-tutor p-0 mb-3">
    <div class="d-flex justify-content-between align-items-center p-3 pb-2">
        <h6 class="fw-bold mb-0">Students</h6>
        <?php if ($cap['status'] === 'open'): ?><form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="nudge_all"><button class="btn btn-outline-dark btn-sm"><i class="fas fa-bell me-1"></i>Nudge everyone who has not sent</button></form><?php endif; ?>
    </div>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0 cap-track">
        <thead class="table-light"><tr><th>Student</th>
            <?php foreach ($ms as $m): ?><th><?php echo asg_e($m['title']); ?><div class="fw-normal text-muted"><?php echo asg_e(cap_local($m['due_at'], 'j M')); ?></div></th><?php endforeach; ?>
            <th>Presentation</th><th>Returns</th><th>Status and actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $uid => $r): ?>
            <tr>
                <td><strong><?php echo asg_e($r['full_name']); ?></strong><div class="small text-muted"><?php echo asg_e($r['registration_no']); ?><?php echo $r['team_no'] ? ' · Team ' . (int) $r['team_no'] : ''; ?><?php echo $r['attempt'] && (int) $r['attempt']['attempt_no'] > 1 ? ' · Retake ' . ((int) $r['attempt']['attempt_no'] - 1) : ''; ?></div></td>
                <?php foreach ($ms as $m): $cell = $r['ms'][(int) $m['id']] ?? ['word' => 'Not yet']; ?>
                    <td><?php echo cap_ms_chip($cell['word']); ?>
                        <?php if (!empty($cell['sub']) && $m['kind'] === 'work'): ?>
                            <div class="small mt-1" style="white-space:normal;max-width:210px"><?php echo asg_e(mb_strimwidth((string) $cell['sub']['note'], 0, 70, '…')); ?>
                                <?php if ($cell['sub']['url']): ?><a href="<?php echo asg_e($cell['sub']['url']); ?>" target="_blank" rel="noopener noreferrer">link</a><?php endif; ?>
                                <?php if ($cell['sub']['file_path']): ?><a href="<?php echo asg_e(cap_file_url('milestone', (int) $cell['sub']['id'])); ?>" target="_blank">file</a><?php endif; ?></div>
                            <form method="POST" class="mt-1"><?php echo csrf_field(); ?><input type="hidden" name="action" value="approve_ms"><input type="hidden" name="sub" value="<?php echo (int) $cell['sub']['id']; ?>">
                                <?php if (!$cell['sub']['approved_at']): ?><button class="btn btn-link btn-sm p-0">Approve</button><?php endif; ?></form>
                        <?php endif; ?></td>
                <?php endforeach; ?>
                <td class="small"><?php echo $r['booking'] ? asg_e(cap_local($r['booking']['starts_at'], 'D j M, H:i')) . ($r['booking']['done_at'] ? ' · Done' : '') : '<span class="text-muted">No slot</span>'; ?></td>
                <td><?php echo (int) $r['returns_used']; ?> of <?php echo CAP_MAX_RETURNS; ?></td>
                <td><?php echo cap_chip($r['state']); ?>
                    <?php if ($r['mark'] && cap_mark_released($r['mark'])): ?><div class="small fw-semibold mt-1"><?php echo asg_e(cap_pretty_marks($r['mark']['total'])); ?> / 100 · <?php echo asg_e($r['mark']['result']); ?></div>
                    <?php elseif ($r['mark']): ?><div class="small mt-1"><span class="asg-chip st-locked"><i class="fas fa-lock"></i>Held</span> <?php echo asg_e(cap_pretty_marks($r['mark']['total'])); ?> · <?php echo asg_e($r['mark']['result']); ?></div><?php endif; ?>
                    <div class="d-flex flex-wrap gap-1 mt-2">
                    <?php if ($cap['status'] === 'open' && !in_array($r['state'], ['to_mark', 'marked', 'awaiting_payment'], true)): ?>
                        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="nudge"><input type="hidden" name="student" value="<?php echo $uid; ?>"><button class="btn btn-outline-dark btn-sm">Nudge</button></form>
                    <?php endif; ?>
                    <?php if ($cap['status'] === 'scheduled' && empty($r['opened_early_at'])): ?>
                        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="open_student"><input type="hidden" name="student" value="<?php echo $uid; ?>"><button class="btn btn-outline-dark btn-sm" title="Open it for this student now">Open early</button></form>
                    <?php endif; ?>
                    <?php if ($r['attempt'] && in_array($r['state'], ['to_mark'], true)): ?><a class="btn btn-brand btn-sm" href="mark.php?id=<?php echo (int) $r['attempt']['id']; ?>">Mark</a>
                    <?php elseif ($r['attempt']): ?><a class="btn btn-outline-dark btn-sm" href="mark.php?id=<?php echo (int) $r['attempt']['id']; ?>">Open</a><?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="<?php echo 4 + count($ms); ?>" class="text-center text-muted py-4">No students on this capstone yet. <a href="edit.php?id=<?php echo $id; ?>">Edit</a> to choose them.</td></tr><?php endif; ?>
        </tbody></table></div>
</div>
<div class="small text-muted">Statuses: Not started · In progress · Behind (past the due date) · To mark · Returned · <strong>Final try</strong> (returned twice, so the next send is final) · Marked.</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
