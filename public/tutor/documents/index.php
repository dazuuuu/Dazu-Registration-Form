<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/lms_files.php';

Middleware::requireTutor();
$db = \Models\Connection::getInstance()->getConnection();
$userId = (int) $_SESSION['user_id'];
$docsSelf = url('tutor/documents/index.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    docs_handle_post($db, $userId, $docsSelf);
}

$page_title = 'My Documents';
ob_start();
require __DIR__ . '/../../components/documents/my_documents.php';
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
