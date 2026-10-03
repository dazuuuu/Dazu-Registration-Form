<?php
// Gallery items are edited from the gallery list; this old entry point just sends admins there.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
Middleware::requireAdmin();
header('Location: ' . url('admin/gallery/index.php'));
exit;
