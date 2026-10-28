<?php
// Superadmin: My profile — change or delete their photo.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/profile_photo.php';

Middleware::requireSuperPortal();
$db = \Models\Connection::getInstance()->getConnection();
$uid = (int) ($_SESSION['user_id'] ?? 0);
pp_handle($db, $uid, url('superadmin/profile/index.php'));

$page_title = 'My profile';
ob_start();
require __DIR__ . '/../../components/profile/photo_card.php';
$content = ob_get_clean();
require __DIR__ . '/../../templates/superadmin/layout.php';
