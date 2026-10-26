<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';

Middleware::requireAdmin();
$idcArea = 'admin';
require __DIR__ . '/../../components/id_cards/manage.php';
require __DIR__ . '/../../templates/admin/layout.php';
