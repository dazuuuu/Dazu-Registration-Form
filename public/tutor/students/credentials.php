<?php
// Tutor resends sign-in details to one of THEIR assigned students (same steps as the admin "Send credentials" button):
// a new temporary password is set (the student must change it on first sign-in) and emailed with the login link.
// If the email cannot be sent, the tutor is shown the temporary password to pass on.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/StudentController.php';
require_once __DIR__ . '/../../../app/v1/services/StudentNotifier.php';

use Models\Connection;
use Models\TutorModel;

Middleware::requireTutor();
$pdo = Connection::getInstance()->getConnection();
$regId = (int) ($_POST['reg_id'] ?? 0);
$back = ($_POST['return'] ?? '') === 'view' ? 'view.php?id=' . $regId : 'index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
    SessionModel::setFlash('error', 'Security check failed. Please try again.');
    header('Location: ' . $back);
    exit();
}
if (!empty($_SESSION['preview'])) {
    SessionModel::setFlash('error', 'Sign-in details cannot be sent in preview mode.');
    header('Location: ' . $back);
    exit();
}

$tutor = (new TutorModel($pdo))->getByUserId((int) $_SESSION['user_id']);
$reg = (new \Models\StudentModel($pdo))->getById($regId);
$assigned = false;
if ($tutor && $reg && !empty($reg['user_id'])) {
    $st = $pdo->prepare("SELECT 1 FROM tutor_students WHERE tutor_id = ? AND user_id = ?");
    $st->execute([(int) $tutor['id'], (int) $reg['user_id']]);
    $assigned = (bool) $st->fetchColumn();
}
if (!$assigned) {
    SessionModel::setFlash('error', 'You can only send sign-in details to students assigned to you.');
    header('Location: index.php');
    exit();
}

$r = (new \Controllers\StudentController())->sendCredentials($regId);
if (!$r['ok']) {
    SessionModel::setFlash('error', htmlspecialchars($r['error']));
} else {
    $mail = (new StudentNotifier($pdo, null, true))->sendCredentials($reg, $r['temp_password']);
    if (!empty($mail['ok'])) {
        SessionModel::setFlash('success', 'New sign-in details emailed to ' . htmlspecialchars($reg['full_name']) . ' (' . htmlspecialchars($reg['email']) . ').');
    } else {
        SessionModel::setFlash('error', 'The email did not send (' . htmlspecialchars((string) ($mail['message'] ?? 'unknown error')) . '). Give '
            . htmlspecialchars($reg['full_name']) . ' these details yourself — Username: <strong>' . htmlspecialchars($r['username'] ?: $reg['email'])
            . '</strong> · Temporary password: <strong>' . htmlspecialchars($r['temp_password']) . '</strong>');
    }
}
header('Location: ' . $back);
exit();
