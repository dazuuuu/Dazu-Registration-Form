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
$id = (int) ($_GET['id'] ?? 0);
$quiz = $cw->getQuiz($id, $tutorId);
if (!$quiz) { header('Location: index.php'); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
    } elseif ($att = $cw->gradeAttempt($id, (int) $_POST['attempt_id'], (array) ($_POST['score'] ?? []), trim($_POST['feedback'] ?? ''))) {
        $fresh = $cw->attempt($id, (int) $att['student_user_id']);
        PortalNotifications::notify($pdo, (int) $att['student_user_id'], 'grade', 'Quiz graded: ' . $quiz['title'],
            'You scored ' . (float) $fresh['score'] . ' / ' . (float) $fresh['max_score'] . '.',
            'students/classwork/quiz.php?id=' . $id, PortalNotifications::photoOf($pdo, (int) $_SESSION['user_id']));
        SessionModel::setFlash('success', 'Grade saved. The student can see it now.');
    }
    header('Location: view.php?id=' . $id . '#a' . (int) ($_POST['attempt_id'] ?? 0)); exit();
}

$questions = $cw->questions($id);
$attempts = $cw->quizAttempts($id);
$students = $cw->studentsOf($tutorId);
$missing = $quiz['is_published'] ? array_diff($cw->recipients('quiz', $quiz), array_map(fn($t) => (int) $t['student_user_id'], $attempts)) : [];
$page_title = $quiz['title'];
ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
    <a href="index.php" class="btn btn-link btn-sm ps-0">← All quizzes</a>
    <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen me-1"></i>Edit quiz</a>
</div>
<div class="card-tutor mb-3"><div class="p-3">
    <div class="fw-semibold fs-5"><?php echo htmlspecialchars($quiz['title']); ?> <?php echo $quiz['is_published'] ? '<span class="badge bg-success">Published</span>' : '<span class="badge bg-secondary">Draft</span>'; ?></div>
    <div class="small text-muted"><?php echo count($questions); ?> questions · <?php echo ClassworkModel::dueLabel($quiz['due_at']); ?> · <?php echo count($attempts); ?> submitted</div>
    <?php if ($missing): ?><div class="small mt-2"><strong>Not submitted yet (<?php echo count($missing); ?>):</strong>
        <?php echo htmlspecialchars(implode(', ', array_map(fn($u) => $students[$u]['full_name'] ?? ('#' . $u), $missing))); ?></div><?php endif; ?>
</div></div>

<?php if (!$attempts): ?><div class="card-tutor"><div class="as-empty"><i class="fas fa-inbox"></i>No answers yet.</div></div><?php endif; ?>
<?php foreach ($attempts as $t): $ans = $cw->answers((int) $t['id']); ?>
<div class="card-tutor mb-3" id="a<?php echo (int) $t['id']; ?>">
    <div class="head d-flex justify-content-between flex-wrap gap-2">
        <span><?php echo htmlspecialchars($t['full_name'] ?? 'Student'); ?> <span class="small fw-normal">(<?php echo htmlspecialchars($t['registration_no'] ?? ''); ?>) · <?php echo date('d M Y H:i', strtotime($t['submitted_at'])); ?></span></span>
        <span class="small"><?php echo $t['graded_at'] ? 'Score: ' . (float) $t['score'] . ' / ' . (float) $t['max_score'] : 'Needs marking'; ?></span>
    </div>
    <form method="POST" class="p-3"><?php echo csrf_field(); ?><input type="hidden" name="attempt_id" value="<?php echo (int) $t['id']; ?>">
        <?php foreach ($questions as $n => $q): $a = $ans[(int) $q['id']] ?? null; ?>
        <div class="border rounded p-2 mb-2">
            <div class="small fw-semibold">Q<?php echo $n + 1; ?>. <span style="white-space:pre-wrap"><?php echo htmlspecialchars($q['prompt']); ?></span></div>
            <?php if ($q['qtype'] === 'mcq'):
                $sel = $a && $a['selected_option'] !== null ? (int) $a['selected_option'] : null; $right = $sel === (int) $q['correct_option']; ?>
                <div class="small mt-1 <?php echo $right ? 'text-success' : 'text-danger'; ?>">
                    Answered: <?php echo $sel !== null ? htmlspecialchars(chr(65 + $sel) . '. ' . ($q['options'][$sel] ?? '')) : '<em>no answer</em>'; ?>
                    <?php if (!$right): ?><span class="text-muted">· correct: <?php echo htmlspecialchars(chr(65 + (int) $q['correct_option']) . '. ' . ($q['options'][(int) $q['correct_option']] ?? '')); ?></span><?php endif; ?>
                </div>
            <?php elseif ($q['qtype'] === 'code'): ?>
                <pre class="bg-dark text-light p-2 rounded small mt-1 mb-0" style="white-space:pre-wrap"><?php echo htmlspecialchars((string) ($a['answer_text'] ?? '')) ?: '<em>no answer</em>'; ?></pre>
            <?php else: ?>
                <div class="bg-light p-2 rounded small mt-1" style="white-space:pre-wrap"><?php echo htmlspecialchars((string) ($a['answer_text'] ?? '')) ?: '<em>no answer</em>'; ?></div>
            <?php endif; ?>
            <div class="d-flex align-items-center gap-2 mt-2">
                <label class="small text-muted mb-0">Points</label>
                <input type="number" step="0.5" min="0" max="<?php echo (int) $q['points']; ?>" name="score[<?php echo (int) $q['id']; ?>]" class="form-control form-control-sm" style="width:90px"
                       value="<?php echo $a && $a['score'] !== null ? (float) $a['score'] : ''; ?>" <?php echo $q['qtype'] !== 'mcq' ? 'required' : ''; ?>>
                <span class="small text-muted">/ <?php echo (int) $q['points']; ?></span>
            </div>
        </div>
        <?php endforeach; ?>
        <label class="form-label small mt-2">Feedback to the student</label>
        <textarea name="feedback" class="form-control" rows="2"><?php echo htmlspecialchars((string) $t['feedback']); ?></textarea>
        <button class="btn btn-brand mt-2"><i class="fas fa-check me-1"></i><?php echo $t['graded_at'] ? 'Update grade' : 'Save grade'; ?></button>
    </form>
</div>
<?php endforeach; ?>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
