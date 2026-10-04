<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
Middleware::requireStudent();
require __DIR__ . '/../../components/notifications/api.php';
