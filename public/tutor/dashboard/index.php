<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/SubmissionModel.php';
require_once __DIR__ . '/../../../app/v1/models/DashboardModel.php';

use Models\Connection;
use Models\TutorModel;
use Models\SubmissionModel;
use Models\DashboardModel;

Middleware::requireTutor();

$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$tutor = $tutorModel->getByUserId((int)$_SESSION['user_id']);
$stats = (new DashboardModel($pdo))->tutorStats((int)$tutor['id']);
$pending = (new SubmissionModel($pdo))->getPendingForTutor((int)$tutor['id']);

$page_title = 'Dashboard';
ob_start();
$pwaPortal = 'tutor'; require __DIR__ . '/../../components/pwa/banner.php';
?>
<?php
require_once __DIR__ . '/../../../app/v1/models/TutorCourseModel.php';
$tc = new \Models\TutorCourseModel($pdo);
$myStudents = $tutorModel->getStudentsForTutor((int) $tutor['id']);
$firstName = strtok($tutorModel->fullName($tutor), ' ') ?: 'there';
$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
?>
<style>
.td-row{display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid var(--line);text-decoration:none;color:inherit}
.td-row:last-child{border-bottom:0}
.td-row:hover{background:#fafbf8}
.td-row .as-ava{width:40px;height:40px;font-size:.8rem}
.td-row .grow{flex:1;min-width:0}
.td-row .nm{font-weight:600;font-size:.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.td-row .sb{font-size:.75rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.td-mini{height:6px;border-radius:99px;background:#eceee9;overflow:hidden;margin-top:6px}
.td-mini>div{height:100%;background:var(--g);border-radius:99px}
</style>

<?php require __DIR__ . '/_app_home.php'; ?>
<div class="th-desktop">
<?php require __DIR__ . '/_desktop_home.php'; ?>
</div><!-- /.th-desktop -->
<?php require __DIR__ . '/payroll_section.php'; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
