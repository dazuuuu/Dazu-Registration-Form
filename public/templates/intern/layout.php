<?php
// public/templates/intern/layout.php — Intern Portal: the tutor app shell (sidebar with the Dazu logo corner on desktop,
// bottom tabs on phones: Home · Tasks · Clock · Messages · More). Expects $db, $intern (intern/_boot.php), $content.
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
Middleware::requireIntern();
require_once __DIR__ . '/../../../app/v1/models/Session.php';

$ilDir = basename(dirname($_SERVER['PHP_SELF']));
$ilBadge = in_badges($db, $intern);
$ilNotif = PortalNotifications::unreadCount($db, (int) $intern['user_id']);
$ilOn = fn(string ...$dirs) => in_array($ilDir, $dirs, true);
$u = fn(string $p) => url('intern/' . $p);

// [key, label, icon, href, active, on tab bar, section heading, short tab label, badge count]
$ilItems = [
    ['dashboard', 'Dashboard', 'fa-house', $u('dashboard/index.php'), $ilOn('dashboard'), false],
    ['tasks', 'My Tasks', 'fa-clipboard-check', $u('tasks/index.php'), $ilOn('tasks'), false, null, null, $ilBadge['tasks']],
    ['attendance', 'Attendance', 'fa-clock', $u('attendance/index.php'), $ilOn('attendance'), false],
    ['learning', 'Learning', 'fa-book', $u('learning/index.php'), $ilOn('learning'), false],
    ['progress', 'My Progress', 'fa-chart-simple', $u('progress/index.php'), $ilOn('progress'), false],
    ['reports', 'Reports & Logbook', 'fa-file-lines', $u('reports/index.php'), $ilOn('reports'), false],
    ['announcements', 'Announcements', 'fa-bullhorn', $u('announcements/index.php'), $ilOn('announcements'), false, null, null, $ilBadge['announcements']],
    ['messages', 'Messages', 'fa-message', $u('messages/index.php'), $ilOn('messages'), false, null, null, $ilBadge['messages']],
    ['assessments', 'Assessments', 'fa-star', $u('assessments/index.php'), $ilOn('assessments'), false],
    ['documents', 'Documents', 'fa-folder', $u('documents/index.php'), $ilOn('documents'), false],
    ['profile', 'My Profile', 'fa-user', $u('profile/index.php'), $ilOn('profile'), false, 'Account'],
    ['notifications', 'Notifications', 'fa-bell', $u('notifications/index.php'), $ilOn('notifications'), false, null, null, $ilNotif],
    ['settings', 'Settings', 'fa-gear', $u('settings/index.php'), $ilOn('settings'), false],
];
$ilFirst = $intern['first_name'] ?: strtok($intern['full_name'], ' ');
$shell = [
    'portal'   => 'intern',
    'role'     => 'Intern portal',
    'title'    => $page_title ?? 'Intern Portal',
    'subtitle' => $page_subtitle ?? date('l, j F'),
    'user'     => $intern['full_name'],
    'user_sub' => 'Intern' . ($intern['department'] ? ' · ' . $intern['department'] : ''),
    'photo'    => $intern['photo_path'] ? in_file_url('photo', (int) $intern['id']) : null,
    'items'    => $ilItems,
    'menu'     => '<li><a class="dropdown-item" href="' . $u('profile/index.php') . '"><i class="fas fa-user me-2"></i>My Profile</a></li>'
                . '<li><a class="dropdown-item" href="' . $u('settings/index.php') . '"><i class="fas fa-gear me-2"></i>Settings</a></li>',
    'logout'   => url(!empty($_SESSION['preview']) ? 'intern/preview-exit.php' : 'auth/logout.php'),
    'tabs'     => [
        ['link', 'Home', 'fa-house', $u('dashboard/index.php'), $ilOn('dashboard')],
        ['link', 'Tasks', 'fa-clipboard-check', $u('tasks/index.php'), $ilOn('tasks'), $ilBadge['tasks']],
        ['link', 'Clock', 'fa-clock', $u('attendance/index.php'), $ilOn('attendance')],
        ['link', 'Messages', 'fa-message', $u('messages/index.php'), $ilOn('messages'), $ilBadge['messages']],
        ['sheet', 'more', 'More', 'fa-ellipsis', [
            ['Learning', 'fa-book', $u('learning/index.php'), $ilOn('learning')],
            ['My Progress', 'fa-chart-simple', $u('progress/index.php'), $ilOn('progress')],
            ['Reports & Logbook', 'fa-file-lines', $u('reports/index.php'), $ilOn('reports')],
            ['Announcements' . ($ilBadge['announcements'] ? ' (' . $ilBadge['announcements'] . ')' : ''), 'fa-bullhorn', $u('announcements/index.php'), $ilOn('announcements')],
            ['Assessments', 'fa-star', $u('assessments/index.php'), $ilOn('assessments')],
            ['Documents', 'fa-folder', $u('documents/index.php'), $ilOn('documents')],
            ['My Profile', 'fa-user', $u('profile/index.php'), $ilOn('profile')],
            ['Notifications' . ($ilNotif ? ' (' . $ilNotif . ')' : ''), 'fa-bell', $u('notifications/index.php'), $ilOn('notifications')],
            ['Settings', 'fa-gear', $u('settings/index.php'), $ilOn('settings')],
            ['Install app', 'fa-download', '#install', false],
            ['Sign out', 'fa-arrow-right-from-bracket', '#logout', false],
        ], $ilOn('learning', 'progress', 'reports', 'announcements', 'assessments', 'documents', 'profile', 'notifications', 'settings')],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title ?? 'Intern Portal'); ?> — Dazu Intern Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <?php $pwaPortal = 'intern'; require __DIR__ . '/../../components/pwa/head.php'; ?>
    <meta name="theme-color" content="#44A024">
    <?php require __DIR__ . '/../../components/app_shell/styles.php'; ?>
    <?php require __DIR__ . '/../../components/interns/styles.php'; ?>
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body class="ip">
<?php if (!empty($_SESSION['preview'])): ?>
<div style="position:relative;z-index:1050;background:#fef3c7;color:#92400e;text-align:center;padding:6px 12px;font-size:13px">
    <strong>Intern portal preview</strong> — a temporary intern with sample tasks. Nothing you do here reaches real interns.
    <a href="<?php echo url('intern/preview-exit.php'); ?>" style="color:#fff;background:#dc2626;padding:3px 10px;border-radius:6px;text-decoration:none;margin-left:8px">Exit preview</a>
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
<?php require __DIR__ . '/../../components/interns/install_prompt.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<?php require __DIR__ . '/../../components/pwa/script.php'; ?>
<script src="<?php echo url('assets/js/intern-app.js'); ?>?v=1" defer data-clock="<?php echo in_e(url('intern/attendance/clock.php')); ?>" data-csrf="<?php echo in_e(csrf_token()); ?>"></script>
<?php require __DIR__ . '/../../components/announcements/overlay.php'; ?>
<?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
