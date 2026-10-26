<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';

Middleware::requireStudent();
$db = \Models\Connection::getInstance()->getConnection();
$page_title = 'Announcements';
ob_start();
?>
<h1 class="h4 mb-3"><i class="fas fa-bullhorn text-success me-2"></i>Announcements</h1>
<?php require __DIR__ . '/../../components/announcements/feed.php'; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
