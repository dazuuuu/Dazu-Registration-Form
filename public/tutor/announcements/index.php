<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';

Middleware::requireTutor();
$annArea = 'tutor';
require __DIR__ . '/../../components/announcements/manage.php';
require __DIR__ . '/../../templates/tutor/layout.php';
