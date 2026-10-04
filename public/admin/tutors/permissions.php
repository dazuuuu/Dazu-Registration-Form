<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

Middleware::requireAdmin();
$pdo = \Models\Connection::getInstance()->getConnection();
$permArea = 'admin';
$page_title = 'Tutor permissions';
$breadcrumbs = [['label' => 'Tutors', 'url' => url('admin/tutors/index.php')], ['label' => 'Permissions', 'active' => true]];
ob_start();
require __DIR__ . '/../../components/tutors/permissions_page.php';
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
