<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';

Middleware::requireAdmin();
$annArea = 'admin';
require __DIR__ . '/../../components/announcements/manage.php';
require __DIR__ . '/../../templates/admin/layout.php';
