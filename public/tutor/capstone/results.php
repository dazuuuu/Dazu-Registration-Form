<?php
// Tutor: results of one capstone: the marks of every student (newest try), retakes, and the certificate checklist at a glance.
require __DIR__ . '/../../components/capstone/tutor_boot.php';

$id = (int) ($_GET['id'] ?? 0);
$cap = cap_get_for_tutor($pdo, $id, $tutorId);
if (!$cap) asg_back('index.php', null, 'Capstone not found.');
$fee = cap_retake_fee($pdo, (int) $cap['course_id']);
$rows = [];
foreach (cap_students($pdo, $id) as $uid => $s) {
    $a = cap_latest_attempt($pdo, $id, $uid);
    $rows[] = ['s' => $s, 'a' => $a, 'mark' => $a ? cap_mark($pdo, (int) $a['id']) : null, 'hist' => cap_history($pdo, $id, $uid),
               'check' => cap_certificate_readiness($pdo, $uid, (int) $cap['course_id'])];
}
$page_title = 'Capstone results';
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">← Capstone</a>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <div><h4 class="mb-1">Results: <?php echo asg_e($cap['title']); ?> <?php echo cap_chip($cap['status']); ?></h4>
        <div class="small text-muted"><?php echo asg_e($cap['course_title']); ?> · only your marks are shown here; the admin sees status words only.</div></div>
    <a class="btn btn-outline-dark btn-sm align-self-start" href="track.php?id=<?php echo $id; ?>">Track</a>
</div>
<?php if ($fee === null): ?><div class="alert alert-warning small">The office has not set the <strong>retake fee</strong> for this course yet, so a retake cannot start. Ask the office to set it in Settings.</div>
<?php else: ?><div class="small text-muted mb-2">Retake fee for this course: <strong>KES <?php echo number_format($fee); ?></strong>. A retake is allowed up to <?php echo CAP_MAX_RETAKES; ?> times and the student pays each time.</div><?php endif; ?>
<div class="card-tutor p-0"><div class="table-responsive"><table class="table align-middle mb-0 small">
    <thead class="table-light"><tr><th>Student</th><?php foreach (CAP_PARTS as $l): ?><th class="text-end"><?php echo $l; ?></th><?php endforeach; ?><th class="text-end">Total</th><th>Result</th><th>Tries</th><th>Certificate</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $m = $r['mark']; $a = $r['a']; ?>
        <tr>
            <td><strong><?php echo asg_e($r['s']['full_name']); ?></strong><div class="text-muted"><?php echo asg_e($r['s']['registration_no']); ?></div></td>
            <?php foreach (['project', 'docs', 'present', 'time'] as $k): ?><td class="text-end"><?php echo $m ? cap_pretty_marks($m['p_' . $k]) . ' / ' . (int) $cap['w_' . $k] : '–'; ?></td><?php endforeach; ?>
            <td class="text-end fw-bold"><?php echo $m ? cap_pretty_marks($m['total']) : '–'; ?></td>
            <td><?php echo $m ? asg_e($m['result']) . (cap_mark_released($m) ? '' : ' <span class="asg-chip st-locked"><i class="fas fa-lock"></i>Held</span>') : ($a ? cap_chip(cap_state($cap + ['own_due_at' => null], $a)) : cap_chip('not_started')); ?></td>
            <td><?php echo count($r['hist']); ?><?php echo $a ? ' (returns ' . (int) $a['returns_used'] . '/2)' : ''; ?></td>
            <td><?php echo $r['check']['ready'] ? '<span class="asg-chip st-graded"><i class="fas fa-award"></i>Ready</span>' : '<span class="asg-chip st-locked">' . asg_e($r['check']['word']) . '</span>'; ?>
                <a href="finish.php?student=<?php echo (int) $r['s']['student_user_id']; ?>&course=<?php echo (int) $cap['course_id']; ?>">checklist</a></td>
            <td><?php if ($a): ?><a class="btn btn-outline-dark btn-sm" href="mark.php?id=<?php echo (int) $a['id']; ?>">Open</a><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="10" class="text-center text-muted py-4">No students.</td></tr><?php endif; ?>
    </tbody></table></div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
