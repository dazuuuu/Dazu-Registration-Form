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
$locked = $cw->hasAttempts($id); // questions are frozen once a student has answered

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
    } elseif ($action === 'settings') {
        $audience = $cw->audienceFromPost($tutorId, $_POST);
        $title = trim($_POST['title'] ?? '');
        if ($title === '' || !$audience) {
            SessionModel::setFlash('error', $title === '' ? 'Give the quiz a title.' : 'Pick at least one student, or choose "All my students".');
        } else {
            $before = $quiz['is_published'] ? $cw->recipients('quiz', $quiz) : [];
            $due = trim($_POST['due_at'] ?? '');
            $cw->updateQuiz($id, ['title' => mb_substr($title, 0, 200), 'description' => trim($_POST['description'] ?? ''),
                'due_at' => $due !== '' ? date('Y-m-d H:i:s', strtotime($due)) : null], $audience);
            if ($quiz['is_published']) { // tell students who were just added
                $quiz = $cw->getQuiz($id);
                foreach (array_diff($cw->recipients('quiz', $quiz), $before) as $uid) {
                    PortalNotifications::notify($pdo, $uid, 'quiz', 'New quiz: ' . $quiz['title'], 'Open it to answer the questions.',
                        'students/classwork/quiz.php?id=' . $id, PortalNotifications::photoOf($pdo, (int) $_SESSION['user_id']));
                }
            }
            SessionModel::setFlash('success', 'Quiz settings saved.');
        }
    } elseif ($action === 'add_question' && !$locked) {
        $type = array_key_exists($_POST['qtype'] ?? '', ClassworkModel::QTYPES) ? $_POST['qtype'] : 'text';
        $prompt = trim($_POST['prompt'] ?? '');
        $options = array_map('strval', (array) ($_POST['options'] ?? []));
        $filled = array_values(array_filter(array_map('trim', $options), 'strlen'));
        // The correct radio indexes the raw option rows; map it onto the non-empty ones.
        $correct = null;
        if ($type === 'mcq' && isset($_POST['correct'])) {
            $raw = (int) $_POST['correct'];
            $pos = 0;
            foreach ($options as $i => $o) {
                if (trim($o) === '') continue;
                if ($i === $raw) { $correct = $pos; break; }
                $pos++;
            }
        }
        if ($prompt === '') {
            SessionModel::setFlash('error', 'Write the question.');
        } elseif ($type === 'mcq' && (count($filled) < 2 || $correct === null)) {
            SessionModel::setFlash('error', 'A multiple-choice question needs at least two options and a correct answer ticked.');
        } else {
            $cw->addQuestion($id, $type, $prompt, $options, $correct, (int) ($_POST['points'] ?? 1));
            SessionModel::setFlash('success', 'Question added.');
        }
    } elseif ($action === 'delete_question' && !$locked) {
        $cw->deleteQuestion($id, (int) $_POST['question_id']);
        SessionModel::setFlash('success', 'Question removed.');
    } elseif ($action === 'publish') {
        $on = !empty($_POST['on']);
        if ($on && !$cw->questions($id)) {
            SessionModel::setFlash('error', 'Add at least one question before publishing.');
        } else {
            $cw->setPublished($id, $on);
            if ($on) {
                $icon = PortalNotifications::photoOf($pdo, (int) $_SESSION['user_id']);
                foreach ($cw->recipients('quiz', $quiz) as $uid) {
                    PortalNotifications::notify($pdo, $uid, 'quiz', 'New quiz: ' . $quiz['title'], 'Open it to answer the questions.',
                        'students/classwork/quiz.php?id=' . $id, $icon);
                }
            }
            SessionModel::setFlash('success', $on ? 'Quiz published. Your students have been notified.' : 'Quiz hidden from students.');
        }
    }
    header('Location: edit.php?id=' . $id); exit();
}

$questions = $cw->questions($id);
$cwStudents = $cw->studentsOf($tutorId);
$cwAudience = $quiz['audience'];
$cwSelected = $cw->quizTargets($id);
$page_title = 'Edit quiz';
ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
    <a href="index.php" class="btn btn-link btn-sm ps-0">← All quizzes</a>
    <div class="d-flex gap-2">
        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-chart-simple me-1"></i>Results</a>
        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="publish"><input type="hidden" name="on" value="<?php echo $quiz['is_published'] ? '' : '1'; ?>">
            <button class="btn btn-sm <?php echo $quiz['is_published'] ? 'btn-outline-danger' : 'btn-success'; ?>"><i class="fas <?php echo $quiz['is_published'] ? 'fa-eye-slash' : 'fa-paper-plane'; ?> me-1"></i><?php echo $quiz['is_published'] ? 'Unpublish' : 'Publish to students'; ?></button></form>
    </div>
</div>
<div class="row g-3">
<div class="col-lg-7">
    <div class="card-tutor mb-3">
        <div class="head">Questions (<?php echo count($questions); ?>) · <?php echo array_sum(array_column($questions, 'points')); ?> points</div>
        <?php if ($locked): ?><div class="alert alert-info m-3 mb-0 small">Students have already answered, so questions can no longer be changed.</div><?php endif; ?>
        <?php if (!$questions): ?><div class="as-empty"><i class="fas fa-list-ol"></i>No questions yet — add the first one.</div><?php endif; ?>
        <div class="list-group list-group-flush">
        <?php foreach ($questions as $n => $q): ?>
            <div class="list-group-item">
                <div class="d-flex justify-content-between gap-2">
                    <div><span class="fw-semibold">Q<?php echo $n + 1; ?>.</span> <span class="badge bg-light text-dark border"><?php echo ClassworkModel::QTYPES[$q['qtype']]; ?></span> <span class="small text-muted"><?php echo (int) $q['points']; ?> pt</span></div>
                    <?php if (!$locked): ?>
                    <form method="POST" onsubmit="return confirm('Remove this question?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_question"><input type="hidden" name="question_id" value="<?php echo (int) $q['id']; ?>">
                        <button class="btn btn-sm btn-link text-danger p-0"><i class="fas fa-trash"></i></button></form>
                    <?php endif; ?>
                </div>
                <div class="mt-1" style="white-space:pre-wrap"><?php echo htmlspecialchars($q['prompt']); ?></div>
                <?php if ($q['qtype'] === 'mcq'): ?><ol type="A" class="small mb-0 mt-1">
                    <?php foreach ($q['options'] as $i => $o): ?><li class="<?php echo $i === (int) $q['correct_option'] ? 'text-success fw-semibold' : ''; ?>"><?php echo htmlspecialchars($o); ?><?php echo $i === (int) $q['correct_option'] ? ' ✓' : ''; ?></li><?php endforeach; ?>
                </ol><?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php if (!$locked): ?>
    <div class="card-tutor">
        <div class="head"><i class="fas fa-plus me-1"></i>Add a question</div>
        <form method="POST" class="p-3" id="qForm"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add_question">
            <div class="row g-2 mb-3">
                <div class="col-8"><label class="form-label fw-semibold">Type</label><select name="qtype" class="form-select" id="qType">
                    <?php foreach (ClassworkModel::QTYPES as $k => $v): ?><option value="<?php echo $k; ?>"><?php echo $v; ?></option><?php endforeach; ?></select></div>
                <div class="col-4"><label class="form-label fw-semibold">Points</label><input type="number" name="points" value="1" min="1" max="100" class="form-control"></div>
            </div>
            <div class="mb-3"><label class="form-label fw-semibold">Question</label><textarea name="prompt" class="form-control" rows="3" required></textarea></div>
            <div id="qOpts" class="mb-3">
                <label class="form-label fw-semibold">Options <span class="text-muted fw-normal small">— tick the correct one</span></label>
                <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="input-group mb-2"><span class="input-group-text"><input class="form-check-input mt-0" type="radio" name="correct" value="<?php echo $i; ?>"></span>
                    <input type="text" name="options[]" class="form-control" placeholder="Option <?php echo chr(65 + $i); ?>"></div>
                <?php endfor; ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="qMore"><i class="fas fa-plus me-1"></i>Another option</button>
            </div>
            <button class="btn btn-brand"><i class="fas fa-plus me-1"></i>Add question</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<div class="col-lg-5">
    <div class="card-tutor">
        <div class="head">Quiz settings</div>
        <form method="POST" class="p-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="settings">
            <div class="mb-3"><label class="form-label fw-semibold">Title</label><input name="title" class="form-control" required maxlength="200" value="<?php echo htmlspecialchars($quiz['title']); ?>"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Instructions</label><textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars((string) $quiz['description']); ?></textarea></div>
            <div class="mb-3"><label class="form-label fw-semibold">Due</label><input type="datetime-local" name="due_at" class="form-control" value="<?php echo $quiz['due_at'] ? date('Y-m-d\TH:i', strtotime($quiz['due_at'])) : ''; ?>"></div>
            <?php require __DIR__ . '/../../components/classwork/audience.php'; ?>
            <button class="btn btn-brand w-100">Save settings</button>
        </form>
        <form method="POST" action="index.php" class="px-3 pb-3" onsubmit="return confirm('Delete this quiz and all answers?')"><?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $id; ?>">
            <button class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-trash me-1"></i>Delete quiz</button></form>
    </div>
</div>
</div>
<script>
(function () {
    var type = document.getElementById('qType'), opts = document.getElementById('qOpts'), more = document.getElementById('qMore');
    if (!type) return;
    function sync() { opts.style.display = type.value === 'mcq' ? '' : 'none'; }
    type.addEventListener('change', sync); sync();
    more.addEventListener('click', function () {
        var n = opts.querySelectorAll('.input-group').length;
        if (n >= 8) return;
        var g = opts.querySelector('.input-group').cloneNode(true);
        g.querySelector('input[type=radio]').value = n; g.querySelector('input[type=radio]').checked = false;
        var t = g.querySelector('input[type=text]'); t.value = ''; t.placeholder = 'Option ' + String.fromCharCode(65 + n);
        opts.insertBefore(g, more);
    });
})();
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
