<?php
// Student: apply for a certificate (POST only). The server checks the four conditions again, whatever the page showed.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateApplication.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateNotifier.php';

Middleware::requireStudent();
$back = url('students/certificates/index.php');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $back);
    exit;
}
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
    header('Location: ' . $back);
    exit;
}
$db = \Models\Connection::getInstance()->getConnection();
$r = certapp_apply($db, (int) $_SESSION['user_id'], (int) ($_POST['course_id'] ?? 0), (string) ($_POST['print_name'] ?? ''), !empty($_POST['confirm']));
CertificateNotifier::flush();
$_SESSION[$r['ok'] ? 'success_msg' : 'error_msg'] = $r['ok'] ? 'Your request was sent to your tutor.' : $r['error'];
header('Location: ' . $back);
exit;
