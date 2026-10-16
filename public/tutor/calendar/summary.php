<?php
// Attendance summary per student (tutor's own students and classes only).
require __DIR__ . '/_boot.php';

$rows = [];
foreach (cal_tutor_students($db, $me) as $uid => $s) $rows[] = $s + ['sum' => cal_attendance_summary($db, (int) $uid, $me)];
$late = cal_settings($db)['late_counts'] === '1';
$page_title = 'Attendance summary';
ob_start();
require __DIR__ . '/../../components/classes/styles.php';
?>
<a href="index.php" class="btn btn-link btn-sm ps-0">← Calendar</a>
<h1 class="h4 mb-1">Attendance summary</h1>
<div class="cl-muted mb-3">Attendance % = (Present<?php echo $late ? ' + Late' : ''; ?>) ÷ classes held. Excused and cancelled classes never count. Counted from each student's joining date.</div>
<div class="cl-card p-0"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th class="ps-3">Student</th><th>Course</th><th class="text-center">Held</th><th class="text-center">Present</th><th class="text-center">Late</th><th class="text-center">Absent</th><th class="text-center">Excused</th><th class="text-end pe-3">Attendance</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="8" class="text-center cl-muted py-4">You have no students yet.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): $s = $r['sum']; ?>
        <tr><td class="ps-3 fw-semibold"><a class="text-reset" href="index.php?student=<?php echo (int) $r['user_id']; ?>"><?php echo cal_e($r['full_name']); ?></a><div class="cl-muted">joined <?php echo date('j M Y', strtotime($r['join_date'])); ?></div></td>
            <td><?php echo cal_e($r['course']); ?></td><td class="text-center"><?php echo $s['held']; ?></td><td class="text-center"><?php echo $s['present']; ?></td>
            <td class="text-center"><?php echo $s['late']; ?></td><td class="text-center"><?php echo $s['absent']; ?></td><td class="text-center"><?php echo $s['excused']; ?></td>
            <td class="text-end pe-3"><?php echo $s['percent'] === null ? '<span class="cl-muted">—</span>' : '<span class="cl-pill ' . ($s['percent'] >= 80 ? 'present' : ($s['percent'] >= 60 ? 'late' : 'absent')) . '">' . $s['percent'] . '%</span>'; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
