<?php
// public/tutor/payroll/confirm.php — a tutor confirms they received a payment from the institute.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/PayrollModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\TutorModel;
use Models\PayrollModel;

Middleware::requireTutor();

$back = url('tutor/dashboard/index.php') . '#payroll';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $back);
    exit();
}
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    SessionModel::setFlash('error', 'Security check failed. Please try again.');
    header('Location: ' . $back);
    exit();
}

$pdo = Connection::getInstance()->getConnection();
$tutor = (new TutorModel($pdo))->getByUserId((int)$_SESSION['user_id']);
$payroll = new PayrollModel($pdo);

if (!$tutor || !$payroll->isInstalled()) {
    SessionModel::setFlash('error', 'Payroll is not available right now.');
    header('Location: ' . $back);
    exit();
}

$which = $_POST['payment_id'] ?? '';
if ($which === 'all') {
    $n = $payroll->confirmAllPayments((int)$tutor['id']);
    SessionModel::setFlash($n ? 'success' : 'error', $n ? $n . ' payment(s) confirmed. Thank you.' : 'There was nothing to confirm.');
} else {
    $ok = $payroll->confirmPayment((int)$which, (int)$tutor['id']);
    SessionModel::setFlash($ok ? 'success' : 'error', $ok ? 'Payment confirmed. Thank you.' : 'That payment could not be confirmed (it may already be confirmed).');
}
header('Location: ' . $back);
exit();
