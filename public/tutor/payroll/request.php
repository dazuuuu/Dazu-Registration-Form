<?php
// public/tutor/payroll/request.php — a tutor asks the superadmin to correct their shares: unassign a student,
// mark a student completed, or correct the balance. Nothing changes until the superadmin approves.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/PayrollRequestModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\TutorModel;
use Models\PayrollRequestModel;

Middleware::requireTutor();

$back = url('tutor/dashboard/index.php') . '#payroll-fix';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
    SessionModel::setFlash('error', 'Security check failed. Please try again.');
    header('Location: ' . $back);
    exit();
}
$pdo = Connection::getInstance()->getConnection();
$tutor = (new TutorModel($pdo))->getByUserId((int) $_SESSION['user_id']);
$prm = new PayrollRequestModel($pdo);
if (!$tutor || !$prm->isInstalled()) {
    SessionModel::setFlash('error', 'This is not available yet.');
    header('Location: ' . $back);
    exit();
}
if (($_POST['action'] ?? '') === 'cancel') {
    $ok = $prm->cancel((int) ($_POST['id'] ?? 0), (int) $tutor['id']);
    SessionModel::setFlash($ok ? 'success' : 'error', $ok ? 'Request cancelled.' : 'That request can no longer be cancelled.');
} else {
    $amount = trim((string) ($_POST['amount'] ?? ''));
    $r = $prm->create((int) $tutor['id'], (string) ($_POST['type'] ?? ''), (int) ($_POST['student'] ?? 0) ?: null,
        $amount !== '' && is_numeric(str_replace(',', '', $amount)) ? (float) str_replace(',', '', $amount) : null,
        (string) ($_POST['completed_on'] ?? ''), (string) ($_POST['reason'] ?? ''));
    SessionModel::setFlash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Sent to the superadmin. Your balance changes only after they approve.' : htmlspecialchars($r['error']));
}
header('Location: ' . $back);
exit();
