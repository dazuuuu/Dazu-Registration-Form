<?php
// Shared start of the tutor Class Calendar pages: signed-in tutor (not a portal preview), $db, $me, $tz.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/ClassCalendarService.php';

Middleware::requireTutor();
$db = \Models\Connection::getInstance()->getConnection();
$me = (int) $_SESSION['user_id'];
if (($_SESSION['role'] ?? '') !== 'tutor') {
    header('Location: ' . url('tutor/dashboard/index.php'));
    exit;
}
if (!cal_installed($db)) {
    $page_title = 'Class Calendar';
    $content = '<div class="alert alert-warning">The Class Calendar is not installed yet. Ask the office to run System Updates (migration 076).</div>';
    require __DIR__ . '/../../templates/tutor/layout.php';
    exit;
}
$tz = cal_user_tz($db, $me);
cal_sync_groups($db, $me);

function cal_post_ok(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        return false;
    }
    return true;
}
