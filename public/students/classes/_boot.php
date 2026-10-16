<?php
// Shared start of the student "My Classes" pages: signed-in student, $db, $me, $tz.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/ClassCalendarService.php';

Middleware::requireStudent();
$db = \Models\Connection::getInstance()->getConnection();
$me = (int) $_SESSION['user_id'];
if (!cal_installed($db)) {
    $page_title = 'My Classes';
    $content = '<div class="alert alert-secondary">Your class timetable is not ready yet. Please check again soon.</div>';
    require __DIR__ . '/../../templates/students/layout.php';
    exit;
}
$tz = cal_user_tz($db, $me);
cal_sync_groups($db);
