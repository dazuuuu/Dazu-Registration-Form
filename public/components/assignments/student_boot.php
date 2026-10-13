<?php
// Common start for student Assignments pages: login and role are checked before any input is read.
// Gives $pdo, $am (AssignmentModel), $uid (users.id), $reg (latest approved registration).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/assignment_files.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/AssignmentModel.php';
require_once __DIR__ . '/../../../app/v1/services/AssignmentNotifier.php';

use Models\Connection;
use Models\StudentModel;
use Models\AssignmentModel;

Middleware::requireStudent();
$pdo = Connection::getInstance()->getConnection();
$uid = (int) SessionModel::get('user_id');
$reg = (new StudentModel($pdo))->getByUserId($uid);
if (!$reg || !empty($reg['portal_locked'])) {
    header('Location: ' . url('students/dashboard/index.php'));
    exit();
}
$am = new AssignmentModel($pdo);

function asg_back(string $to, ?string $ok = null, ?string $err = null): void
{
    if ($ok) SessionModel::setFlash('success', htmlspecialchars($ok));
    if ($err) SessionModel::setFlash('error', htmlspecialchars($err));
    header('Location: ' . $to);
    exit();
}

require_once __DIR__ . '/ui.php';
