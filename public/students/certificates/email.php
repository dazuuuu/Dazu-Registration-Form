<?php
// POST: a student emails one of their own certificates (PDF attached) to any address.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateService.php';

Middleware::requireStudent();
$back = url('students/certificates/index.php');

$id = (int) ($_POST['id'] ?? 0);
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
$st = $db->prepare("SELECT c.id FROM certificates c JOIN registrations r ON r.id = c.registration_id
                    WHERE c.id = ? AND r.user_id = ? AND c.status <> 'withdrawn'");
$st->execute([$id, (int) $_SESSION['user_id']]);
if (!$st->fetchColumn()) {
    $_SESSION['error_msg'] = 'Certificate not found.';
    header('Location: ' . $back);
    exit;
}

$res = cert_send_email($db, $id, trim((string) ($_POST['email'] ?? '')) ?: null);
$_SESSION[$res['ok'] ? 'success_msg' : 'error_msg'] = $res['message'];
header('Location: ' . $back);
exit;
