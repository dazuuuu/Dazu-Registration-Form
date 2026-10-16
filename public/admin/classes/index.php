<?php
// Admin: read-only overview of tutor classes and attendance reports (by tutor, course or student), with CSV/PDF export.
// Admins cannot create or edit tutor classes here.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/ClassCalendarService.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$installed = cal_installed($db);
$tz = $installed ? cal_user_tz($db, (int) $_SESSION['user_id']) : CAL_DEFAULT_TZ;
$tab = ($_GET['tab'] ?? '') === 'report' ? 'report' : 'classes';
$by = in_array($_GET['by'] ?? '', ['tutor', 'course', 'student'], true) ? $_GET['by'] : 'student';
$f = ['tutor' => (int) ($_GET['tutor'] ?? 0), 'course' => (int) ($_GET['course'] ?? 0),
      'from' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? $_GET['from'] : date('Y-m-01'),
      'to' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? $_GET['to'] : date('Y-m-t')];
$tutors = $installed ? $db->query("SELECT DISTINCT c.tutor_id AS id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))), ''), u.username) AS name
                                   FROM classes c JOIN users u ON u.id = c.tutor_id LEFT JOIN user_profiles p ON p.user_id = u.id ORDER BY name")->fetchAll(PDO::FETCH_KEY_PAIR) : [];
$courses = $db->query('SELECT id, title FROM courses ORDER BY title')->fetchAll(PDO::FETCH_KEY_PAIR);
$fromU = cal_to_utc($f['from'] . ' 00:00', $tz);
$toU = gmdate('Y-m-d H:i:s', strtotime(cal_to_utc($f['to'] . ' 00:00', $tz) . ' UTC') + 86400);
$where = 'c.start_utc >= ? AND c.start_utc < ?' . ($f['tutor'] ? ' AND c.tutor_id = ' . $f['tutor'] : '') . ($f['course'] ? ' AND c.course_id = ' . $f['course'] : '');
$tutorName = "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(tp.first_name,''), ' ', COALESCE(tp.last_name,''))), ''), tu.username)";
$rows = [];
if ($installed && $tab === 'classes') {
    $st = $db->prepare("SELECT c.*, co.title AS course_title, $tutorName AS tutor_name,
                               (SELECT COUNT(*) FROM class_students x WHERE x.class_id = c.id) AS students,
                               (SELECT COUNT(*) FROM class_attendance a WHERE a.class_id = c.id AND a.status IN ('present','late')) AS attended,
                               (SELECT COUNT(*) FROM class_attendance a WHERE a.class_id = c.id AND a.status = 'absent') AS absent
                        FROM classes c LEFT JOIN courses co ON co.id = c.course_id JOIN users tu ON tu.id = c.tutor_id LEFT JOIN user_profiles tp ON tp.user_id = tu.id
                        WHERE $where ORDER BY c.start_utc LIMIT 1000");
    $st->execute([$fromU, $toU]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} elseif ($installed) {
    $late = cal_settings($db)['late_counts'] === '1' ? "'present','late'" : "'present'";
    $key = ['tutor' => "c.tutor_id", 'course' => "c.course_id", 'student' => "a.student_id"][$by];
    $label = ['tutor' => $tutorName, 'course' => "COALESCE(co.title, 'No course')",
              'student' => "(SELECT r.full_name FROM registrations r WHERE r.user_id = a.student_id ORDER BY r.id DESC LIMIT 1)"][$by];
    $st = $db->prepare("SELECT $label AS label, SUM(a.status IN ('present','late','absent')) AS held, SUM(a.status = 'present') AS present, SUM(a.status = 'late') AS late,
                               SUM(a.status = 'absent') AS absent, SUM(a.status = 'excused') AS excused, SUM(a.status IN ($late)) AS attended
                        FROM class_attendance a JOIN classes c ON c.id = a.class_id AND c.status <> 'cancelled'
                        LEFT JOIN courses co ON co.id = c.course_id JOIN users tu ON tu.id = c.tutor_id LEFT JOIN user_profiles tp ON tp.user_id = tu.id
                        WHERE a.status IS NOT NULL AND $where GROUP BY $key, label ORDER BY label");
    $st->execute([$fromU, $toU]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) $r['percent'] = $r['held'] ? (int) round($r['attended'] / $r['held'] * 100) : null;
    unset($r);
}

$export = (string) ($_GET['export'] ?? '');
if ($installed && $export !== '') {
    cal_audit($db, null, null, 'admin_export', $tab . ' ' . $export);
    if ($tab === 'classes') {
        $head = ['Date', 'Time (' . $tz . ')', 'Class', 'Course', 'Tutor', 'Mode', 'Status', 'Students', 'Attended', 'Absent'];
        $data = array_map(fn($r) => [cal_fmt($r['start_utc'], $tz, 'Y-m-d'), cal_fmt($r['start_utc'], $tz) . '-' . cal_fmt($r['end_utc'], $tz), $r['title'], $r['course_title'], $r['tutor_name'],
                                     CAL_MODES[$r['mode']], ucfirst($r['status']), $r['students'], $r['attended'], $r['absent']], $rows);
    } else {
        $head = [ucfirst($by), 'Held', 'Present', 'Late', 'Absent', 'Excused', 'Attendance %'];
        $data = array_map(fn($r) => [$r['label'], $r['held'], $r['present'], $r['late'], $r['absent'], $r['excused'], $r['percent'] === null ? '' : $r['percent']], $rows);
    }
    $name = 'attendance_' . $tab . '_' . $f['from'] . '_' . $f['to'];
    if ($export === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $head, ',', '"', '');
        foreach ($data as $d) fputcsv($out, $d, ',', '"', '');
        exit;
    }
    require_once __DIR__ . '/../../../app/v1/helpers/certificate_pdf.php';
    $html = '<html><head><meta charset="UTF-8"><style>body{font-family:Helvetica;font-size:9pt;color:#373435}table{width:100%;border-collapse:collapse}th{background:#44A024;color:#fff;padding:5px;text-align:left}td{padding:5px;border-bottom:1px solid #ddd}</style></head><body>'
          . '<h2>Dazu Training Institute — ' . ($tab === 'classes' ? 'Classes' : 'Attendance by ' . $by) . '</h2><p>' . cal_e($f['from'] . ' to ' . $f['to'] . ' · times in ' . $tz) . '</p><table><tr>'
          . implode('', array_map(fn($h) => '<th>' . cal_e($h) . '</th>', $head)) . '</tr>'
          . implode('', array_map(fn($d) => '<tr>' . implode('', array_map(fn($x) => '<td>' . cal_e($x) . '</td>', $d)) . '</tr>', $data)) . '</table></body></html>';
    $pdf = new \Dompdf\Dompdf();
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'landscape');
    $pdf->render();
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $name . '.pdf"');
    echo $pdf->output();
    exit;
}

$qs = fn(array $x) => '?' . http_build_query(array_filter(['tab' => $tab, 'by' => $by] + $f) + $x);
$page_title = 'Classes & attendance';
$breadcrumbs = [['label' => 'Classes', 'active' => true]];
ob_start();
require __DIR__ . '/../../components/classes/styles.php';
?>
<div class="container-fluid py-3">
<?php if (!$installed): ?><div class="alert alert-warning">The Class Calendar is not installed yet. Run <a href="<?php echo url('admin/system/updates.php'); ?>">System Updates</a> (migration 076).</div><?php endif; ?>
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <div class="cl-tabs"><a class="<?php echo $tab === 'classes' ? 'on' : ''; ?>" href="<?php echo $qs(['tab' => 'classes']); ?>">Classes</a><a class="<?php echo $tab === 'report' ? 'on' : ''; ?>" href="<?php echo $qs(['tab' => 'report']); ?>">Attendance report</a></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-secondary btn-sm" href="settings.php"><i class="fas fa-sliders me-1"></i>Class settings</a>
        <a class="btn btn-outline-success btn-sm" href="<?php echo $qs(['export' => 'csv']); ?>"><i class="fas fa-file-csv me-1"></i>CSV</a>
        <a class="btn btn-outline-success btn-sm" href="<?php echo $qs(['export' => 'pdf']); ?>"><i class="fas fa-file-pdf me-1"></i>PDF</a></div>
</div>
<form method="GET" class="cl-card row g-2 align-items-end mx-0"><input type="hidden" name="tab" value="<?php echo $tab; ?>">
    <div class="col-md-2"><label class="form-label small mb-1">From</label><input type="date" name="from" value="<?php echo cal_e($f['from']); ?>" class="form-control form-control-sm"></div>
    <div class="col-md-2"><label class="form-label small mb-1">To</label><input type="date" name="to" value="<?php echo cal_e($f['to']); ?>" class="form-control form-control-sm"></div>
    <div class="col-md-3"><label class="form-label small mb-1">Tutor</label><select name="tutor" class="form-select form-select-sm"><option value="">All tutors</option>
        <?php foreach ($tutors as $tid => $tn): ?><option value="<?php echo (int) $tid; ?>" <?php echo $f['tutor'] === (int) $tid ? 'selected' : ''; ?>><?php echo cal_e($tn); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label small mb-1">Course</label><select name="course" class="form-select form-select-sm"><option value="">All courses</option>
        <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo (int) $cid; ?>" <?php echo $f['course'] === (int) $cid ? 'selected' : ''; ?>><?php echo cal_e($ct); ?></option><?php endforeach; ?></select></div>
    <?php if ($tab === 'report'): ?><div class="col-md-2"><label class="form-label small mb-1">Group by</label><select name="by" class="form-select form-select-sm">
        <?php foreach (['student' => 'Student', 'tutor' => 'Tutor', 'course' => 'Course'] as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo $by === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select></div>
    <?php else: ?><div class="col-md-2"><button class="btn btn-sm btn-success w-100">Show</button></div><?php endif; ?>
    <?php if ($tab === 'report'): ?><div class="col-12 text-end"><button class="btn btn-sm btn-success">Show</button></div><?php endif; ?>
</form>
<div class="cl-card p-0"><div class="table-responsive"><table class="table align-middle mb-0">
<?php if ($tab === 'classes'): ?>
    <thead><tr><th class="ps-3">When (<?php echo cal_e($tz); ?>)</th><th>Class</th><th>Tutor</th><th>Mode</th><th>Status</th><th class="text-center">Students</th><th class="text-center">Attended</th><th class="text-center">Absent</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="8" class="text-center cl-muted py-4">No classes in this period.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr><td class="ps-3 small"><?php echo cal_e(cal_fmt($r['start_utc'], $tz, 'D j M') . ' · ' . cal_fmt($r['start_utc'], $tz) . '–' . cal_fmt($r['end_utc'], $tz)); ?></td>
            <td><div class="fw-semibold"><?php echo cal_e($r['title']); ?></div><div class="cl-muted"><?php echo cal_e($r['course_title'] ?? ''); ?></div></td>
            <td><?php echo cal_e($r['tutor_name']); ?></td><td><span class="cl-pill <?php echo cal_e($r['mode']); ?>"><?php echo CAL_MODES[$r['mode']]; ?></span></td>
            <td><?php echo $r['status'] === 'scheduled' ? '<span class="cl-muted">Scheduled</span>' : '<span class="cl-pill ' . cal_e($r['status']) . '">' . ucfirst($r['status']) . '</span>'; ?></td>
            <td class="text-center"><?php echo (int) $r['students']; ?></td><td class="text-center"><?php echo (int) $r['attended']; ?></td><td class="text-center"><?php echo (int) $r['absent']; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
<?php else: ?>
    <thead><tr><th class="ps-3"><?php echo ucfirst($by); ?></th><th class="text-center">Held</th><th class="text-center">Present</th><th class="text-center">Late</th><th class="text-center">Absent</th><th class="text-center">Excused</th><th class="text-end pe-3">Attendance</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-center cl-muted py-4">No attendance marked in this period.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
        <tr><td class="ps-3 fw-semibold"><?php echo cal_e($r['label']); ?></td><td class="text-center"><?php echo (int) $r['held']; ?></td><td class="text-center"><?php echo (int) $r['present']; ?></td>
            <td class="text-center"><?php echo (int) $r['late']; ?></td><td class="text-center"><?php echo (int) $r['absent']; ?></td><td class="text-center"><?php echo (int) $r['excused']; ?></td>
            <td class="text-end pe-3"><?php echo $r['percent'] === null ? '—' : $r['percent'] . '%'; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
<?php endif; ?>
</table></div></div>
<p class="cl-muted">Read-only. Tutors create and change their own classes in their Class Calendar.</p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
