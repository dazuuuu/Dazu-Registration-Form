<?php
// public/templates/students/layout.php — app-style shell (components/app_shell): sidebar on desktop, bottom tabs on phones.
if (session_status() === PHP_SESSION_NONE) session_start();

if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}

require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';

Middleware::requireStudent();

require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/user_photo.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateService.php';

$slDb = \Models\Connection::getInstance()->getConnection();
$slUid = (int) ($_SESSION['user_id'] ?? 0);
if (!isset($portal_locked)) {
    // Pages that don't check the fee lock themselves (assignments, documents…) still get the short menu.
    try {
        $st = $slDb->prepare("SELECT portal_locked FROM registrations WHERE user_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 1");
        $st->execute([$slUid]);
        $portal_locked = (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        $portal_locked = false;
    }
}
$slMe = [];
try {
    $st = $slDb->prepare(
        "SELECT r.full_name, r.registration_no,
                CASE WHEN up.profile_photo_status <> 'rejected' THEN up.profile_photo_path END AS photo
         FROM users u
         LEFT JOIN registrations r ON r.user_id = u.id AND r.status = 'approved'
         LEFT JOIN user_profiles up ON up.user_id = u.id
         WHERE u.id = ? ORDER BY r.created_at DESC LIMIT 1"
    );
    $st->execute([$slUid]);
    $slMe = $st->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
}
$slName = trim((string) ($slMe['full_name'] ?? '')) ?: ($_SESSION['username'] ?? 'Student');
if ($slName === mb_strtolower($slName)) {
    $slName = mb_convert_case($slName, MB_CASE_TITLE, 'UTF-8');
}
$slDir = basename(dirname($_SERVER['PHP_SELF']));

// [key, label, icon, href, active, on tab bar, section heading, short tab label]
$slItems = [
    ['dashboard', 'Dashboard', 'fa-house', url('students/dashboard/index.php'), $slDir === 'dashboard', true, null, 'Home'],
    ['course', 'My Course', 'fa-book-open', url('students/course/index.php'), $slDir === 'course', true, null, 'Course'],
    ['payments', 'Finance', 'fa-wallet', url('students/payments/index.php'), $slDir === 'payments', true, null, 'Finance'],
    ['classes', 'My Classes', 'fa-calendar-days', url('students/classes/index.php'), $slDir === 'classes', true, null, 'Classes'],
    ['documents', 'My Documents', 'fa-id-card', url('students/documents/index.php'), $slDir === 'documents', false, 'More', 'Docs'],
    ['assignments', 'Assignments', 'fa-file-pen', url('students/assignments/index.php'), $slDir === 'assignments', false],
    ['classwork', 'Quizzes', 'fa-circle-question', url('students/classwork/index.php'), $slDir === 'classwork', false],
    ['resources', 'Resources', 'fa-folder-open', url('students/resources/index.php'), $slDir === 'resources', false],
];
if (cert_student_has_any($slUid)) {
    $slItems[] = ['certificates', 'Certificates', 'fa-award', url('students/certificates/index.php'), $slDir === 'certificates', false];
}
$slItems[] = ['announcements', 'Announcements', 'fa-bullhorn', url('students/announcements/index.php'), $slDir === 'announcements', false];
$slItems[] = ['notifications', 'Notifications', 'fa-bell', url('students/notifications/index.php'), $slDir === 'notifications', false];

if ($portal_locked) {
    // Fees overdue: the course lessons are locked, but assignments and quizzes (receive, view, submit, grades),
    // documents and payments stay open.
    $slItems = array_values(array_filter($slItems, fn($i) => in_array($i[0], ['dashboard', 'payments', 'documents', 'assignments', 'classwork', 'notifications'], true)));
    foreach ($slItems as &$slIt) { if (in_array($slIt[0], ['documents', 'assignments'], true)) { $slIt[5] = true; $slIt[6] = null; } }
    unset($slIt);
}

$shell = [
    'portal'   => 'students',
    'role'     => 'Student portal',
    'title'    => $page_title ?? 'Student Portal',
    'user'     => $slName,
    'user_sub' => $slMe['registration_no'] ?? 'Student',
    'photo'    => user_photo_url($slDb, $slUid),
    'items'    => $slItems,
    'menu'     => '<li><a class="dropdown-item" href="' . url('students/documents/index.php') . '"><i class="fas fa-id-card me-2"></i>My documents &amp; ID card</a></li>'
                . '<li><a class="dropdown-item" href="' . url('students/payments/index.php') . '"><i class="fas fa-receipt me-2"></i>Finance</a></li>',
    'logout'   => url(!empty($_SESSION['preview']) ? 'students/preview-exit.php' : 'auth/logout.php'),
    'hide_nav' => false,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title ?? 'Student Portal'); ?> — Dazu Training Institute</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php $pwaPortal = 'students'; require __DIR__ . '/../../components/pwa/head.php'; ?>
    <?php require __DIR__ . '/../../components/app_shell/styles.php'; ?>
    <?php if (isset($extra_css)): echo $extra_css; endif; ?>
</head>
<body>
<?php if (!empty($_SESSION['preview'])): ?>
<div style="position:relative;z-index:1050;background:#1d1b1c;color:#fff;padding:8px 16px;font:600 13px Poppins,system-ui,sans-serif;display:flex;gap:12px;justify-content:space-between;align-items:center;flex-wrap:wrap">
    <span><i class="fas fa-eye me-1"></i> Preview mode: you are looking at the portal as a temporary student. Nothing here is real.</span>
    <a href="<?php echo url('students/preview-exit.php'); ?>" style="color:#fff;background:#dc2626;padding:4px 12px;border-radius:6px;text-decoration:none">Exit preview</a>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../components/app_shell/shell.php'; ?>

<main class="as-main">
    <?php if ($flash = SessionModel::getFlash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-circle-check me-2"></i><?php echo $flash; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($flash = SessionModel::getFlash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-circle-exclamation me-2"></i><?php echo $flash; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php echo $content; ?>
</main>

<?php require __DIR__ . '/../../components/app_shell/tabs.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<?php require __DIR__ . '/../../components/pwa/script.php'; ?>
<?php require __DIR__ . '/../../components/announcements/overlay.php'; ?>
<?php require __DIR__ . '/../../components/classes/tz_prompt.php'; ?>
<?php if (isset($extra_js)): echo $extra_js; endif; ?>
</body>
</html>
