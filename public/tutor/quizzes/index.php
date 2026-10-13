<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/ClassworkModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/PortalNotifications.php';

use Models\Connection;
use Models\TutorModel;
use Models\ClassworkModel;

Middleware::requireTutor();
$pdo = Connection::getInstance()->getConnection();
$tutor = (new TutorModel($pdo))->getByUserId((int) $_SESSION['user_id']);
$tutorId = (int) $tutor['id'];
$cw = new ClassworkModel($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $cw->deleteQuiz($tutorId, (int) $_POST['id']);
        SessionModel::setFlash('success', 'Quiz deleted.');
    } else {
        $title = trim($_POST['title'] ?? '');
        $audience = $cw->audienceFromPost($tutorId, $_POST);
        if ($title === '' || !$audience) {
            SessionModel::setFlash('error', $title === '' ? 'Give the quiz a title.' : 'Pick at least one student, or choose "All my students".');
        } else {
            $due = trim($_POST['due_at'] ?? '');
            $id = $cw->createQuiz($tutorId, ['title' => mb_substr($title, 0, 200), 'description' => trim($_POST['description'] ?? ''),
                'due_at' => $due !== '' ? date('Y-m-d H:i:s', strtotime($due)) : null], $audience);
            SessionModel::setFlash('success', 'Quiz created. Add your questions, then publish it.');
            header('Location: edit.php?id=' . $id); exit();
        }
    }
    header('Location: index.php'); exit();
}

$cwStudents = $cw->studentsOf($tutorId);
$list = $cw->quizzesForTutor($tutorId);
$page_title = 'Quizzes';
ob_start();
?>
<div class="row g-3">
<div class="col-lg-5">
    <div class="card-tutor">
        <div class="head"><i class="fas fa-plus me-1"></i>New quiz</div>
        <form method="POST" class="p-3"><?php echo csrf_field(); ?>
            <div class="mb-3"><label class="form-label fw-semibold">Title</label><input name="title" class="form-control" required maxlength="200"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Instructions <span class="text-muted fw-normal">(optional)</span></label><textarea name="description" class="form-control" rows="3"></textarea></div>
            <div class="mb-3"><label class="form-label fw-semibold">Due <span class="text-muted fw-normal">(optional)</span></label><input type="datetime-local" name="due_at" class="form-control"></div>
            <?php require __DIR__ . '/../../components/classwork/audience.php'; ?>
            <button class="btn btn-brand w-100"><i class="fas fa-arrow-right me-1"></i>Create &amp; add questions</button>
        </form>
    </div>
</div>
<div class="col-lg-7">
    <div class="card-tutor">
        <div class="head">My quizzes (<?php echo count($list); ?>)</div>
        <?php if (!$list): ?><div class="as-empty"><i class="fas fa-circle-question"></i>No quizzes yet.</div><?php endif; ?>
        <div class="list-group list-group-flush">
        <?php foreach ($list as $q): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <div style="min-width:0">
                    <span class="fw-semibold"><?php echo htmlspecialchars($q['title']); ?></span>
                    <?php echo $q['is_published'] ? '<span class="badge bg-success ms-1">Published</span>' : '<span class="badge bg-secondary ms-1">Draft</span>'; ?>
                    <div class="small text-muted"><?php echo (int) $q['questions']; ?> questions · <?php echo ClassworkModel::dueLabel($q['due_at']); ?> · <?php echo $q['audience'] === 'all' ? 'All students' : 'Selected students'; ?></div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark border"><?php echo (int) $q['submitted']; ?> submitted</span>
                    <?php if ($q['ungraded']): ?><span class="badge bg-warning text-dark"><?php echo (int) $q['ungraded']; ?> to grade</span><?php endif; ?>
                    <a href="edit.php?id=<?php echo (int) $q['id']; ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <a href="view.php?id=<?php echo (int) $q['id']; ?>" class="btn btn-sm btn-brand">Results</a>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
