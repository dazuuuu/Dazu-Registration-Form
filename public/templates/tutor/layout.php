<?php
// public/templates/tutor/layout.php — app-style shell (components/app_shell): sidebar on desktop, bottom tabs on phones.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
Middleware::requireTutor();
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/helpers/tutor_permissions.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';

$tlDb = \Models\Connection::getInstance()->getConnection();
$tlUid = (int) ($_SESSION['user_id'] ?? 0);
$tlMe = [];
try {
    $st = $tlDb->prepare("SELECT up.first_name, up.last_name, up.profile_photo_path FROM user_profiles up WHERE up.user_id = ?");
    $st->execute([$tlUid]);
    $tlMe = $st->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
}
$tlName = trim(($tlMe['first_name'] ?? '') . ' ' . ($tlMe['last_name'] ?? '')) ?: ($_SESSION['username'] ?? 'Tutor');
$tlDir = basename(dirname($_SERVER['PHP_SELF']));
$tlAdmin = empty($_SESSION['preview']) && tutor_admin_allowed($tlDb, $tlUid);
// Submissions badge: assignment work awaiting a grade.
$tlAwaiting = 0;
try {
    require_once __DIR__ . '/../../../app/v1/models/AssignmentModel.php';
    $tlAm = new \Models\AssignmentModel($tlDb);
    if ($tlTutor = $tlAm->tutorByUser($tlUid)) $tlAwaiting = $tlAm->awaitingCount((int) $tlTutor['id']);
} catch (Throwable $e) {
}

// [key, label, icon, href, active, on tab bar, section heading, short tab label, badge count]
$tlItems = [
    ['dashboard', 'Dashboard', 'fa-house', url('tutor/dashboard/index.php'), $tlDir === 'dashboard', true, null, 'Home'],
    ['students', 'My Students', 'fa-user-graduate', url('tutor/students/index.php'), $tlDir === 'students', true, null, 'Students'],
    ['submissions', 'Submissions', 'fa-clipboard-check', url('tutor/submissions/index.php'), $tlDir === 'submissions', true, null, 'Grade', $tlAwaiting],
    ['modules', 'Course Builder', 'fa-layer-group', url('tutor/modules/index.php'), $tlDir === 'modules', true, null, 'Builder'],
    ['calendar', 'Class Calendar', 'fa-calendar-days', url('tutor/calendar/index.php'), $tlDir === 'calendar', false, 'Teaching'],
    ['courses', 'My Courses', 'fa-book-open', url('tutor/courses/index.php'), $tlDir === 'courses', false],
    ['resources', 'Resources', 'fa-folder-open', url('tutor/resources/index.php'), $tlDir === 'resources', false],
    ['assignments', 'Assignments', 'fa-file-pen', url('tutor/assignments/index.php'), $tlDir === 'assignments', false],
    ['quizzes', 'Quizzes', 'fa-circle-question', url('tutor/quizzes/index.php'), $tlDir === 'quizzes', false],
    ['announcements', 'Announcements', 'fa-bullhorn', url('tutor/announcements/index.php'), $tlDir === 'announcements', false],
    ['helpers', 'Intern Helpers', 'fa-hands-helping', url('tutor/helpers/index.php'), $tlDir === 'helpers', false],
    ['notifications', 'Notifications', 'fa-bell', url('tutor/notifications/index.php'), $tlDir === 'notifications', false, 'Account'],
    ['profile', 'My ID & signature', 'fa-id-badge', url('tutor/profile/index.php'), $tlDir === 'profile', false],
];
if ($tlAdmin) {
    $tlItems[] = ['admin', 'Admin portal', 'fa-user-shield', url('admin/dashboard.php'), false, false];
}
$shell = [
    'portal'   => 'tutor',
    'role'     => 'Tutor portal',
    'title'    => $page_title ?? 'Tutor Portal',
    'user'     => $tlName,
    'user_sub' => 'Tutor',
    'photo'    => !empty($tlMe['profile_photo_path']) ? settings_asset_url($tlMe['profile_photo_path']) : null,
    'items'    => $tlItems,
    'menu'     => ($tlAdmin ? '<li><a class="dropdown-item" href="' . url('admin/dashboard.php') . '"><i class="fas fa-user-shield me-2"></i>Admin portal</a></li>' : ''),
    'logout'   => url(!empty($_SESSION['preview']) ? 'tutor/preview-exit.php' : 'auth/logout.php'),
    // Phone header and tab bar: Home · System · Students · Courses · Announce · More
    'app_title' => 'Dazu Tutor',
    'app_hello' => 'Hi, ' . (strtok($tlName, ' ') ?: 'Tutor'),
];
$tlOn = fn(array $dirs) => in_array($tlDir, $dirs, true);
$tlFile = basename($_SERVER['PHP_SELF']);
$shell['tabs'] = [
    ['link', 'Home', 'fa-house', url('tutor/dashboard/index.php'), $tlOn(['dashboard'])],
    ['sheet', 'system', 'System', 'fa-sliders', [
        ['Announcements', 'fa-bullhorn', url('tutor/announcements/index.php#received'), false],
        ['Notifications', 'fa-bell', url('tutor/notifications/index.php'), $tlOn(['notifications'])],
        ['Alerts on phone', 'fa-mobile-screen', '#alerts', false],
        ['Install app', 'fa-download', '#install', false],
    ], $tlOn(['notifications'])],
    ['link', 'Students', 'fa-user-graduate', url('tutor/students/index.php'), $tlOn(['students'])],
    ['sheet', 'courses', 'Courses', 'fa-book-open', [
        ['Class Calendar', 'fa-calendar-days', url('tutor/calendar/index.php'), $tlOn(['calendar'])],
        ['My courses', 'fa-book-open', url('tutor/courses/index.php'), $tlOn(['courses'])],
        ['Course builder', 'fa-layer-group', url('tutor/modules/index.php'), $tlOn(['modules'])],
        ['Resources', 'fa-folder-open', url('tutor/resources/index.php'), $tlOn(['resources'])],
        ['Questions & quizzes', 'fa-circle-question', url('tutor/quizzes/index.php'), $tlOn(['quizzes'])],
        ['Assignments', 'fa-file-pen', url('tutor/assignments/index.php'), $tlOn(['assignments'])],
        ['Grade work' . ($tlAwaiting ? ' (' . $tlAwaiting . ')' : ''), 'fa-clipboard-check', url('tutor/submissions/index.php'), $tlOn(['submissions'])],
    ], $tlOn(['calendar', 'courses', 'modules', 'resources', 'quizzes', 'assignments', 'submissions'])],
    ['link', 'Announce', 'fa-bullhorn', url('tutor/announcements/index.php?new=1'), $tlOn(['announcements'])],
    ['sheet', 'more', 'More', 'fa-grip', array_merge(
        [['My ID & signature', 'fa-id-badge', url('tutor/profile/index.php'), $tlOn(['profile'])],
         ['Intern Helpers', 'fa-hands-helping', url('tutor/helpers/index.php'), $tlOn(['helpers'])]],
        $tlAdmin ? [['Admin portal', 'fa-user-shield', url('admin/dashboard.php'), false]] : [],
        [['Sign out', 'fa-arrow-right-from-bracket', '#logout', false]]
    ), $tlOn(['profile', 'helpers'])],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title ?? 'Tutor Portal'); ?> — Dazu Training Institute</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php $pwaPortal = 'tutor'; require __DIR__ . '/../../components/pwa/head.php'; ?>
    <?php require __DIR__ . '/../../components/app_shell/styles.php'; ?>
    <?php require __DIR__ . '/../../components/app_shell/glass.php'; ?>
    <meta name="theme-color" content="#f7fbf4" media="(max-width: 991.98px)">
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body class="as-glass">
<?php if (!empty($_SESSION['preview'])): ?>
<div style="position:relative;z-index:1050;background:#fef3c7;color:#92400e;text-align:center;padding:6px 12px;font-size:13px">
    <strong>Tutor portal preview</strong> — a temporary tutor with one temporary student. Nothing you do here reaches real students.
    <a href="<?php echo url('tutor/preview-exit.php'); ?>" style="color:#fff;background:#dc2626;padding:3px 10px;border-radius:6px;text-decoration:none;margin-left:8px">Exit preview</a>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../../components/app_shell/shell.php'; ?>
<main class="as-main">
<?php
if ($f = SessionModel::getFlash('success')): ?><div class="alert alert-success alert-dismissible fade show"><i class="fas fa-circle-check me-2"></i><?php echo $f; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;
if ($f = SessionModel::getFlash('error')): ?><div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-circle-exclamation me-2"></i><?php echo $f; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;
echo $content;
?>
</main>
<?php require __DIR__ . '/../../components/app_shell/tabs.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php require __DIR__ . '/../../components/pwa/script.php'; ?>
<?php require __DIR__ . '/../../components/announcements/overlay.php'; ?>
<?php require __DIR__ . '/../../components/classes/tz_prompt.php'; ?>
<?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
