<?php
// POST: email a certificate PDF (attached) to the address typed in the Email dialog.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateService.php';

Middleware::requireAdmin();

$id = (int) ($_POST['id'] ?? 0);
$back = (string) ($_POST['return_to'] ?? '');
if (!str_starts_with($back, url('admin/'))) {
    $back = url($id ? 'admin/certificates/edit.php?id=' . $id : 'admin/certificates/index.php');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) {
    header('Location: ' . $back);
    exit;
}
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
    header('Location: ' . $back);
    exit;
}

$db = \Models\Connection::getInstance()->getConnection();
$email = trim((string) ($_POST['email'] ?? ''));
$res = cert_send_email($db, $id, $email !== '' ? $email : null);
$_SESSION[$res['ok'] ? 'success_msg' : 'error_msg'] = $res['message'];
header('Location: ' . $back);
exit;
