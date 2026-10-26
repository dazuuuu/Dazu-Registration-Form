<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/lms_files.php';

Middleware::requireStudent();
$db = \Models\Connection::getInstance()->getConnection();
$userId = (int) $_SESSION['user_id'];
$docsSelf = url('students/documents/index.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['idc_action'] ?? '') === 'request') {
    require_once __DIR__ . '/../../../app/v1/services/IdCardService.php';
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
    } else {
        $res = idc_request($db, $userId, (int) ($_POST['registration_id'] ?? 0), trim((string) ($_POST['note'] ?? '')));
        $_SESSION[$res['ok'] ? 'success_msg' : 'error_msg'] = $res['ok'] ? 'ID card request sent. We will notify you when your card is ready.' : $res['error'];
    }
    header('Location: ' . $docsSelf);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    docs_handle_post($db, $userId, $docsSelf);
}

$page_title = 'My Documents';
ob_start();
require __DIR__ . '/../../components/documents/my_documents.php';
$content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
