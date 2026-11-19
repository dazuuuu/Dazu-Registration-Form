<?php
// public/students/timetable/index.php — timetables the student's tutors shared with them (PDF and/or weekly table).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/TimetableService.php';

Middleware::requireStudent();
$db = \Models\Connection::getInstance()->getConnection();
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$page_title = 'Timetable';
$list = TimetableService::installed($db) ? TimetableService::forStudent($db, (int) $_SESSION['user_id']) : [];
ob_start(); ?>
<style>.tt-table{width:100%;border-collapse:collapse;font-size:.92rem}.tt-table th,.tt-table td{border:1px solid #dfe7e3;padding:.5rem .6rem}
.tt-table th{background:#eef5f2;font-weight:600}.tt-day{font-weight:600;color:#1f6f50;white-space:nowrap}</style>
<?php if (!$list): ?>
  <div class="alert alert-secondary">Your tutor has not shared a timetable yet.</div>
<?php else: ?>
  <p class="text-muted">You get an alert before every class. Allow notifications on this phone to get them as pop-ups.</p>
  <?php foreach ($list as $t) require __DIR__ . '/../../components/timetable/view.php'; ?>
<?php endif;
$content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
