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
// Open even when the portal is suspended for fees: assignments and grades never depend on payment.
if (!$reg) {
    header('Location: ' . url('students/dashboard/index.php'));
    exit();
}
$cw = new ClassworkModel($pdo);
$id = (int) ($_GET['id'] ?? 0);
$quiz = $cw->quizForStudent($userId, $id);
if (!$quiz) { header('Location: index.php'); exit(); }
$questions = $cw->questions($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
    } elseif ($cw->submitQuiz($id, $userId, $_POST)) {
        require_once __DIR__ . '/../../../app/v1/services/PortalNotifications.php';
        $tu = $pdo->prepare("SELECT user_id FROM tutors WHERE id = ?");
        $tu->execute([(int) $quiz['tutor_id']]);
        PortalNotifications::notify($pdo, (int) $tu->fetchColumn(), 'submission', $reg['full_name'] . ' answered: ' . $quiz['title'], '',
            'tutor/quizzes/view.php?id=' . $id);
        SessionModel::setFlash('success', 'Answers submitted.');
    } else {
        SessionModel::setFlash('error', 'You have already submitted this quiz.');
    }
    header('Location: quiz.php?id=' . $id); exit();
}

$attempt = $cw->attempt($id, $userId);
$answers = $attempt ? $cw->answers((int) $attempt['id']) : [];
$graded = $attempt && $attempt['graded_at'];
$page_title = $quiz['title'];
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">← Classwork</a>
<div class="card-student mb-3"><div class="card-body p-3">
    <div class="fw-semibold fs-5"><?php echo htmlspecialchars($quiz['title']); ?></div>
    <div class="small text-muted"><?php echo count($questions); ?> questions · <?php echo array_sum(array_column($questions, 'points')); ?> points · <?php echo ClassworkModel::dueLabel($quiz['due_at']); ?></div>
    <?php if ($quiz['description']): ?><div class="mt-2" style="white-space:pre-wrap"><?php echo htmlspecialchars($quiz['description']); ?></div><?php endif; ?>
    <?php if ($graded): ?>
        <div class="fs-4 fw-bold text-success mt-2"><?php echo (float) $attempt['score']; ?> / <?php echo (float) $attempt['max_score']; ?></div>
        <?php if ($attempt['feedback']): ?><div><strong>Tutor feedback:</strong> <span style="white-space:pre-wrap"><?php echo htmlspecialchars($attempt['feedback']); ?></span></div><?php endif; ?>
    <?php elseif ($attempt): ?>
        <div class="alert alert-info mt-2 mb-0">Submitted <?php echo date('d M Y H:i', strtotime($attempt['submitted_at'])); ?>. Your tutor is marking it — your score appears here once graded.</div>
    <?php else: ?>
        <div class="alert alert-warning mt-2 mb-0 small">You can submit only once. Check your answers before you submit.</div>
    <?php endif; ?>
</div></div>

<?php if (!$attempt): ?><form method="POST" onsubmit="return confirm('Submit your answers? You cannot change them afterwards.')"><?php echo csrf_field(); ?><?php endif; ?>
<?php foreach ($questions as $n => $q): $qid = (int) $q['id']; $a = $answers[$qid] ?? null; ?>
<div class="card-student mb-3"><div class="card-body p-3">
    <div class="d-flex justify-content-between gap-2"><span class="fw-semibold">Question <?php echo $n + 1; ?></span>
        <span class="small text-muted"><?php echo $graded && $a ? (float) $a['score'] . ' / ' : ''; ?><?php echo (int) $q['points']; ?> pt</span></div>
    <div class="mt-1 mb-2" style="white-space:pre-wrap"><?php echo htmlspecialchars($q['prompt']); ?></div>
    <?php if ($q['qtype'] === 'mcq'): ?>
        <?php foreach ($q['options'] as $i => $o):
            $picked = $a && $a['selected_option'] !== null && (int) $a['selected_option'] === $i;
            $cls = $graded ? ($i === (int) $q['correct_option'] ? 'text-success fw-semibold' : ($picked ? 'text-danger' : '')) : ''; ?>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="q[<?php echo $qid; ?>]" value="<?php echo $i; ?>" id="q<?php echo $qid . '_' . $i; ?>" <?php echo $attempt ? 'disabled' : ''; ?> <?php echo $picked ? 'checked' : ''; ?>>
                <label class="form-check-label <?php echo $cls; ?>" for="q<?php echo $qid . '_' . $i; ?>"><?php echo htmlspecialchars(chr(65 + $i) . '. ' . $o); ?></label>
            </div>
        <?php endforeach; ?>
    <?php elseif ($attempt): ?>
        <div class="<?php echo $q['qtype'] === 'code' ? 'bg-dark text-light font-monospace' : 'bg-light'; ?> p-2 rounded small" style="white-space:pre-wrap"><?php echo htmlspecialchars((string) ($a['answer_text'] ?? '')) ?: '<em>no answer</em>'; ?></div>
    <?php else: ?>
        <textarea name="q[<?php echo $qid; ?>]" class="form-control <?php echo $q['qtype'] === 'code' ? 'font-monospace' : ''; ?>" rows="<?php echo $q['qtype'] === 'code' ? 10 : 4; ?>"
            <?php echo $q['qtype'] === 'code' ? 'spellcheck="false" placeholder="Write or paste your code here"' : 'placeholder="Your answer"'; ?>></textarea>
    <?php endif; ?>
</div></div>
<?php endforeach; ?>
<?php if (!$attempt): ?><button class="btn btn-brand mb-4"><i class="fas fa-paper-plane me-1"></i>Submit answers</button></form><?php endif; ?>
<script>
// Tab inserts spaces in code boxes instead of leaving the field.
document.querySelectorAll('textarea.font-monospace').forEach(function (t) {
    t.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab') return;
        e.preventDefault();
        var s = t.selectionStart; t.setRangeText('    ', s, t.selectionEnd, 'end');
    });
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/students/layout.php';
