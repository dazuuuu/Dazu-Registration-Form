<?php
// Tutor: Student records. Marks per assignment for the tutor's own students in one course, the same course average the
// student sees (AssignmentAverage), and Export CSV. For the tutor and student only: not used for certificates, not shown to admin.
require __DIR__ . '/../../components/assignments/tutor_boot.php';
require_once __DIR__ . '/../../../app/v1/services/AssignmentAverage.php';

use Models\AssignmentModel;

$courses = $am->tutorCourses($tutorId);
$courseId = isset($_GET['course']) && $_GET['course'] !== '' ? (int) $_GET['course'] : (int) (array_key_first($courses) ?? 0);
$fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');

$grid = $am->progress($tutorId, $courseId);
$rows = [];
foreach ($grid['rows'] as $uid => $r) {
    $raw = array_values(array_filter(AssignmentAverage::rowsForStudent($pdo, (int) $uid, $tutorId), fn($x) => (int) $x['course_id'] === $courseId));
    $calc = AssignmentAverage::compute($raw);
    $byId = [];
    foreach ($raw as $x) $byId[(int) $x['id']] = $x;
    $rows[$uid] = ['student' => $r['student'], 'raw' => $byId, 'calc' => $calc];
}

/** Cell text for the tutor. The tutor sees their own held marks, labelled Held. */
$cell = function (?array $x) use ($fmt): array {
    if (!$x) return ['—', 'text-muted'];
    $now = time();
    $pastDue = $x['due'] && AssignmentModel::ts($x['due']) <= $now;
    if ($x['sub_status'] === 'graded' && $x['result_published_at']) {
        $held = !AssignmentAverage::isReleased($x, $now);
        $t = $x['grading_mode'] === 'marks' ? $fmt($x['score']) . ' / ' . $fmt($x['max_score'])
            : ($x['pass_result'] === 'pass' ? 'Pass' : ($x['pass_result'] === 'not_yet' ? 'Not yet' : 'Reviewed'));
        return [($x['is_late'] ? 'Late · ' : '') . $t . ($held ? ' (Held)' : ''), $held ? 'st-submitted' : 'st-graded'];
    }
    if ($x['sub_status'] === 'returned') return ['Returned', 'st-returned'];
    if ($x['submitted_at']) return [($x['is_late'] ? 'Late · ' : '') . 'To grade', 'st-progress'];
    return $pastDue ? ['Missing', 'st-closed'] : ['Not due', 'st-open'];
};

if (($_GET['export'] ?? '') === 'csv') {
    $am->log(null, $me, null, 'records_export', ['course_id' => $courseId]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="student-records-' . $courseId . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    $head = ['Student', 'Registration no'];
    foreach ($grid['assignments'] as $ga) $head[] = $ga['title'];
    $head[] = 'Average';
    fputcsv($out, $head, ",", "\"", "");
    foreach ($rows as $r) {
        $line = [$r['student']['full_name'], $r['student']['registration_no']];
        foreach ($grid['assignments'] as $ga) $line[] = $cell($r['raw'][(int) $ga['id']] ?? null)[0];
        $line[] = $r['calc']['display'];
        // Neutralise spreadsheet formulas in names and titles.
        fputcsv($out, array_map(fn($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : $v, $line), ",", "\"", "");
    }
    fclose($out);
    exit();
}

$page_title = 'Student records';
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">← Assignments</a>
<div class="card-tutor p-3 p-md-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h5 class="mb-0">Student records</h5>
        <form class="d-flex flex-wrap gap-2" method="GET">
            <select name="course" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo (int) $cid; ?>" <?php echo (int) $cid === $courseId ? 'selected' : ''; ?>><?php echo asg_e($ct); ?></option><?php endforeach; ?>
            </select>
            <a class="btn btn-sm btn-outline-secondary" href="?course=<?php echo $courseId; ?>&amp;export=csv"><i class="fas fa-download me-1"></i>Export CSV</a>
        </form>
    </div>
    <?php if (!$grid['assignments']): ?>
        <p class="text-muted mb-0">No published assignments in this course yet.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table asg-grid align-middle mb-0">
            <thead><tr><th>Student</th>
                <?php foreach ($grid['assignments'] as $n => $ga): ?><th class="small"><a href="view.php?id=<?php echo (int) $ga['id']; ?>" class="text-decoration-none text-muted" title="<?php echo asg_e($ga['title']); ?>"><?php echo asg_e(($ga['kind'] ?? 'daily') === 'weekly' ? 'Week' : 'A'); ?><?php echo $n + 1; ?></a></th><?php endforeach; ?>
                <th>Average</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $uid => $r): ?>
                <tr>
                    <td><a href="progress.php?student=<?php echo (int) $uid; ?>" class="fw-semibold text-dark text-decoration-none"><?php echo asg_e($r['student']['full_name']); ?></a></td>
                    <?php foreach ($grid['assignments'] as $ga): [$t, $cls] = $cell($r['raw'][(int) $ga['id']] ?? null); ?>
                        <td><span class="asg-chip <?php echo $cls; ?>"><?php echo asg_e($t); ?></span></td>
                    <?php endforeach; ?>
                    <td class="fw-bold"><?php echo asg_e($r['calc']['display']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <p class="small text-muted mt-2 mb-0"><i class="fas fa-circle-info me-1"></i>Records are for you and your students. They do not decide who gets a certificate. The average counts released marks only; held marks show as (Held). Missing work is not counted as zero unless you give 0.</p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
