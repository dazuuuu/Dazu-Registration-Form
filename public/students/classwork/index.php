<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/lms_files.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/ClassworkModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\StudentModel;
use Models\ClassworkModel;

Middleware::requireStudent();
$pdo = Connection::getInstance()->getConnection();
$userId = (int) SessionModel::get('user_id');
$reg = (new StudentModel($pdo))->getByUserId($userId);
if (!$reg || !empty($reg['portal_locked'])) {
    header('Location: ' . url('students/dashboard/index.php'));
    exit();
}
$cw = new ClassworkModel($pdo);
$quizzes = $cw->quizzesForStudent($userId);
$status = function (?string $submitted, ?string $graded, $score, $max): string {
    if ($graded) return '<span class="badge bg-success">' . (float) $score . ' / ' . (float) $max . '</span>';
    if ($submitted) return '<span class="badge bg-info text-dark">Submitted — awaiting grade</span>';
    return '<span class="badge bg-warning text-dark">To do</span>';
};
$page_title = 'Classwork';
ob_start();
?>
<div class="row g-3">
<div class="col-lg-6">
    <div class="card-student">
        <div class="card-head"><i class="fas fa-file-pen"></i>Assignments</div>
        <div class="card-body p-3">
            <p class="mb-2 small text-muted">Assignments from your tutor, in order, with your results and feedback.</p>
            <a href="<?php echo url('students/assignments/index.php'); ?>" class="btn btn-brand btn-sm">Open my assignments</a>
        </div>
    </div>
</div>
<div class="col-lg-6">
    <div class="card-student">
        <div class="card-head"><i class="fas fa-circle-question"></i>Quizzes</div>
        <?php if (!$quizzes): ?><div class="as-empty"><i class="fas fa-circle-question"></i>No quizzes yet.</div><?php endif; ?>
        <div class="list-group list-group-flush">
        <?php foreach ($quizzes as $q): ?>
            <a href="quiz.php?id=<?php echo (int) $q['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2">
                <div style="min-width:0"><div class="fw-semibold text-truncate"><?php echo htmlspecialchars($q['title']); ?></div>
                    <div class="small text-muted"><?php echo ClassworkModel::dueLabel($q['due_at']); ?></div></div>
                <?php echo $status($q['submitted_at'], $q['graded_at'], $q['score'], $q['max_score']); ?>
            </a>
        <?php endforeach; ?>
        </div>
    </div>
</div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/students/layout.php';
