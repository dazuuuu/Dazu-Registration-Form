<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

Middleware::requireSuperPortal();
$pdo = \Models\Connection::getInstance()->getConnection();
$permArea = 'superadmin';
$page_title = 'Tutor permissions';
$breadcrumbs = [['label' => 'Tutors', 'url' => url('superadmin/tutors/index.php')], ['label' => 'Permissions', 'active' => true]];
ob_start();
require __DIR__ . '/../../components/tutors/permissions_page.php';
$content = ob_get_clean();
require __DIR__ . '/../../templates/superadmin/layout.php';
