<?php
// public/tutor/resources/download.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/config/paths.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/ResourceModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\ResourceModel;

Middleware::requireTutor();

$userId = (int) SessionModel::get('user_id');
$id = (int) ($_GET['id'] ?? 0);

$pdo = Connection::getInstance()->getConnection();
$resourceModel = new ResourceModel($pdo);
$doc = $resourceModel->getDocumentById($id);

if (!$doc || (int) $doc['user_id'] !== $userId) {
    http_response_code(404);
    echo 'Document not found.';
    exit();
}

$absPath = public_path($doc['file_path']);
if (!is_file($absPath)) {
    http_response_code(404);
    echo 'File not found.';
    exit();
}

$filename = ($doc['doc_type'] === 'id_card' ? 'tutor-id' : 'certificate') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($absPath));
readfile($absPath);
exit();
