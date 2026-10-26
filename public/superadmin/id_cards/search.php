<?php
// JSON student search for the "Generate ID card" modal.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/IdCardService.php';

Middleware::requireSuperPortal();

$db = \Models\Connection::getInstance()->getConnection();
header('Content-Type: application/json');
echo json_encode(['results' => idc_search_enrolments($db, (string) ($_GET['q'] ?? ''), 25)]);
