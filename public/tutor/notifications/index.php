<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
Middleware::requireTutor();
$notifBase = 'tutor';
require __DIR__ . '/../../components/notifications/page.php';
require __DIR__ . '/../../templates/tutor/layout.php';
