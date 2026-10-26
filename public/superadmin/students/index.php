<?php
// Superadmin: every enrolled student in one clean, searchable sheet. (Enrolling lives on enroll.php.)
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/RegistrationModel.php';
require_once __DIR__ . '/../../../app/v1/models/PayrollModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/controllers/StudentController.php';
require_once __DIR__ . '/../../../app/v1/services/StudentNotifier.php';
require_once __DIR__ . '/../../../app/v1/helpers/study_location.php';

use Models\Connection;
use Models\RegistrationModel;
use Models\PayrollModel;
use Controllers\StudentController;

Middleware::requireSuperPortal();

$pdo = Connection::getInstance()->getConnection();
$payroll = new PayrollModel($pdo);
$tutorsReady = $payroll->isInstalled();
$tutors = $tutorsReady ? array_values(array_filter($payroll->listTutorNames(), fn($t) => $t['is_active'])) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = 'index.php';
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        header('Location: ' . $back); exit();
    }
    $action = $_POST['action'] ?? '';
    $regId = (int)($_POST['reg_id'] ?? 0);
    $reg = $regId ? (new RegistrationModel($pdo))->getById($regId) : null;
    if (!$reg || $reg['status'] !== 'approved' || !$reg['user_id']) {
        SessionModel::setFlash('error', 'Student not found.');
    } elseif ($action === 'assign_tutor') {
        $tid = (int)($_POST['tutor_id'] ?? 0);
        if (!$tutorsReady) {
            SessionModel::setFlash('error', 'Apply the pending system update (migration 048) first.');
        } elseif ($tid <= 0) {
            SessionModel::setFlash('error', 'Choose a tutor.');
        } else {
            $n = $payroll->assignStudents($tid, [(int)$reg['user_id']]);
            SessionModel::setFlash($n ? 'success' : 'error', $n ? htmlspecialchars($reg['full_name']) . ' assigned to the tutor.' : 'Already assigned to that tutor.');
        }
    } elseif ($action === 'send_credentials') {
        $r = (new StudentController())->sendCredentials($regId);
        if ($r['ok']) {
            $mail = (new StudentNotifier($pdo, null, true))->sendCredentials($reg, $r['temp_password']);
            SessionModel::setFlash(!empty($mail['ok']) ? 'success' : 'warning', !empty($mail['ok'])
                ? 'New sign-in details emailed to ' . htmlspecialchars($reg['email']) . '.'
                : 'Password reset, but the email failed. Temporary password: <strong>' . htmlspecialchars($r['temp_password']) . '</strong>');
        } else {
            SessionModel::setFlash('error', $r['error']);
        }
    }
    header('Location: ' . $back); exit();
}

$tutorSql = $tutorsReady
    ? "(SELECT GROUP_CONCAT(COALESCE(NULLIF(TRIM(CONCAT_WS(' ', tup.first_name, tup.last_name)), ''), tu.username) SEPARATOR ', ')
        FROM tutor_students ts JOIN tutors tt ON tt.id = ts.tutor_id JOIN users tu ON tu.id = tt.user_id
        LEFT JOIN user_profiles tup ON tup.user_id = tu.id WHERE ts.user_id = r.user_id)"
    : "NULL";
$rows = $pdo->query(
    "SELECT r.id, r.full_name, r.email, r.phone, r.registration_no, r.created_at, r.course_fee, r.registration_fee, r.registration_fee_waived,
            c.title AS course_title, r.study_mode, " . study_location_col($pdo) . " AS study_location, $tutorSql AS tutors,
            (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.registration_id = r.id AND p.status = 'approved') AS paid
     FROM registrations r LEFT JOIN courses c ON c.id = r.course_id
     WHERE r.status = 'approved' AND r.registration_no NOT LIKE 'PREVIEW-%' ORDER BY r.created_at DESC, r.id DESC LIMIT 3000"
)->fetchAll();

$students = [];
$tot = ['fee' => 0.0, 'paid' => 0.0];
foreach ($rows as $r) {
    $fee = (float)$r['course_fee'] + ($r['registration_fee_waived'] ? 0 : (float)$r['registration_fee']);
    $paid = (float)$r['paid'];
    $students[] = $r + ['fee' => $fee, 'bal' => max(0, $fee - $paid), 'ts' => $r['created_at'] ? strtotime($r['created_at']) : 0];
}
$num = fn($n): string => number_format((float)$n, 0);
$col = fn(int $i): string => chr(64 + $i);

$page_title = 'Students';
$sa_subtitle = count($students) . ' enrolled student' . (count($students) === 1 ? '' : 's') . '.';
$sa_actions = '<a class="btn sa-btn-primary" href="enroll.php"><i class="fas fa-user-plus me-1"></i>Enrol student</a>';
$extra_css = '<link rel="stylesheet" href="' . sa_asset('css/sheet.css') . '">';
ob_start();
?>
<div class="xl-toolbar">
    <label class="xl-search" style="max-width:560px"><i class="fas fa-magnifying-glass"></i><input type="search" id="stSearch" placeholder="Search name, course, tutor… or try: oldest, balance" autocomplete="off"></label>
    <span class="xl-count" id="stCount"></span>
</div>
<div class="xl-hint" id="stHint">Tip: type <b>oldest</b>, <b>newest</b>, <b>balance</b> (owing, highest first) or <b>cleared</b>, alone or with a name or course. Click a column heading to sort.</div>

<div class="xl-shell">
    <div class="xl-scroll">
    <table class="xl" id="stTable">
        <thead>
            <tr class="xl-letters"><th class="rn"></th><?php for ($i = 1; $i <= 8; $i++) echo '<th' . ($i === 1 ? ' class="xl-sticky"' : '') . '>' . $col($i) . '</th>'; ?></tr>
            <tr class="xl-head">
                <th class="rn"></th>
                <th class="xl-sticky xl-sort" data-sort="name">Student <i class="fas fa-sort"></i></th>
                <th class="xl-sort" data-sort="course">Course <i class="fas fa-sort"></i></th>
                <th class="xl-sort" data-sort="date">Registered <i class="fas fa-sort"></i></th>
                <th>Tutor</th>
                <th class="num xl-sort" data-sort="fee">Fee <i class="fas fa-sort"></i></th>
                <th class="num xl-sort" data-sort="paid">Paid <i class="fas fa-sort"></i></th>
                <th class="num xl-sort" data-sort="bal">Balance <i class="fas fa-sort"></i></th>
                <th></th>
            </tr>
        </thead>
        <tbody id="stBody">
        <?php foreach ($students as $n => $s): ?>
            <tr data-search="<?php echo htmlspecialchars(strtolower($s['full_name'] . ' ' . $s['registration_no'] . ' ' . $s['course_title'] . ' ' . $s['tutors'] . ' ' . $s['phone'] . ' ' . $s['email']), ENT_QUOTES); ?>"
                data-name="<?php echo htmlspecialchars(strtolower($s['full_name']), ENT_QUOTES); ?>" data-course="<?php echo htmlspecialchars(strtolower((string)$s['course_title']), ENT_QUOTES); ?>"
                data-date="<?php echo (int)$s['ts']; ?>" data-fee="<?php echo (float)$s['fee']; ?>" data-paid="<?php echo (float)$s['paid']; ?>" data-bal="<?php echo (float)$s['bal']; ?>">
                <th class="rn"><?php echo $n + 1; ?></th>
                <td class="xl-sticky"><a class="xl-link" href="<?php echo url('superadmin/students/view.php?id=' . (int)$s['id']); ?>"><?php echo htmlspecialchars($s['full_name']); ?></a>
                    <div class="text-muted" style="font-size:.72rem"><?php echo htmlspecialchars((string)$s['registration_no']); ?></div></td>
                <td class="xl-wrap"><?php echo htmlspecialchars((string)$s['course_title']); ?><?php if ($pl = study_placement_label($s['study_mode'], $s['study_location'])): ?><div class="text-muted" style="font-size:.72rem"><?php echo htmlspecialchars($pl); ?></div><?php endif; ?></td>
                <td><?php echo $s['ts'] ? date('d M Y', $s['ts']) : '—'; ?></td>
                <td class="xl-wrap"><?php echo $s['tutors'] ? htmlspecialchars($s['tutors']) : '<span class="text-muted">—</span>'; ?></td>
                <td class="num"><?php echo $num($s['fee']); ?></td>
                <td class="num"><?php echo $num($s['paid']); ?></td>
                <td class="num fw-bold <?php echo $s['bal'] > 0 ? 'xl-owed' : 'xl-clear'; ?>"><?php echo $num($s['bal']); ?></td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary js-manage" data-reg="<?php echo (int)$s['id']; ?>" data-name="<?php echo htmlspecialchars($s['full_name'], ENT_QUOTES); ?>" data-tutors="<?php echo htmlspecialchars((string)$s['tutors'], ENT_QUOTES); ?>">Manage</button></td>
            </tr>
        <?php endforeach; ?>
        <tr id="stEmpty" style="display:none"><td colspan="9" class="text-muted text-center py-4">No student matches your search.</td></tr>
        </tbody>
        <tfoot><tr><th class="rn"></th><td class="xl-sticky" id="stFootN">Total</td><td></td><td></td><td></td><td class="num" id="stFootFee"></td><td class="num" id="stFootPaid"></td><td class="num" id="stFootBal"></td><td></td></tr></tfoot>
    </table>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-2" id="stPager">
    <span class="small text-muted pg-label"></span>
    <div class="d-flex align-items-center gap-2">
        <select class="form-select form-select-sm pg-size" style="width:auto"><option value="14" selected>14 per page</option><option value="30">30 per page</option><option value="100">100 per page</option><option value="0">All</option></select>
        <button type="button" class="btn btn-sm btn-outline-secondary pg-prev"><i class="fas fa-chevron-left me-1"></i>Previous</button>
        <button type="button" class="btn btn-sm btn-outline-secondary pg-next">Next<i class="fas fa-chevron-right ms-1"></i></button>
    </div>
</div>

<div class="modal fade" id="manageModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="mName">Student</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="small text-muted mb-3">Tutor: <strong id="mTutors"></strong></p>
        <?php if ($tutors): ?>
        <form method="POST" class="input-group mb-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="assign_tutor"><input type="hidden" name="reg_id" class="mReg">
            <select name="tutor_id" class="form-select" required><option value="">Assign a tutor…</option><?php foreach ($tutors as $t): ?><option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['full_name']); ?></option><?php endforeach; ?></select>
            <button class="btn btn-primary">Assign</button></form>
        <?php endif; ?>
        <form method="POST" class="mb-3" onsubmit="return confirm('Reset the password and email new sign-in details?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="send_credentials"><input type="hidden" name="reg_id" class="mReg">
            <button class="btn btn-outline-secondary w-100"><i class="fas fa-key me-1"></i>Send credentials</button></form>
        <a class="btn btn-outline-secondary w-100" id="mDetails" href="#">Open full details</a>
    </div>
</div></div></div>
<?php $content = ob_get_clean();
$extra_js = '<script>window.SA_DETAIL = ' . json_encode(url('superadmin/students/view.php?id='), JSON_UNESCAPED_SLASHES) . ';</script><script src="' . sa_asset('superadmin/students.js') . '"></script>';
require __DIR__ . '/../../templates/superadmin/layout.php';
