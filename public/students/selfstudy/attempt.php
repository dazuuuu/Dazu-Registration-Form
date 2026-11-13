<?php
// public/students/selfstudy/attempt.php — taking a quiz or exam: one question at a time, answers saved as the student goes, a countdown kept by the server.
// A finished attempt cannot be opened here again (no going back through the answers): the student is sent to the result.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/SelfStudyQuiz.php';
require_once __DIR__ . '/_quiz_shell.php';

Middleware::requireStudent();
$pdo = \Models\Connection::getInstance()->getConnection();
$uid = (int) SessionModel::get('user_id');
require __DIR__ . '/_gate.php';

$now = time();
$view = ss3_installed($pdo) ? ss3_attempt_view($pdo, $uid, (int) ($_GET['a'] ?? 0), $now) : null;
if (!$view) { header('Location: ' . url('students/selfstudy/index.php')); exit; }
$a = $view['attempt']; $courseId = (int) $a['course_id'];
$course = ss_course_for_student($pdo, $courseId, $uid);
if (!$course) { header('Location: ' . url('students/selfstudy/index.php')); exit; }
$kind = $a['kind']; $moduleId = $a['module_id'] !== null ? (int) $a['module_id'] : null;
$itemUrl = url('students/selfstudy/quiz.php') . '?id=' . $courseId . ($kind === 'module_quiz' ? '&module=' . $moduleId : '&exam=' . ($kind === 'checkpoint_exam' ? 'checkpoint' : 'final'));
if ($a['status'] !== 'in_progress') { header('Location: ' . $itemUrl); exit; }

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$qs = $view['questions']; $n = count($qs);
$label = SS3_KINDS[$kind]['label'];
if ($kind === 'module_quiz') { $m = ss3_item(ss2_model($pdo, $courseId, $uid), $kind, $moduleId); $label = $m['label'] ?? 'Quiz'; }
$timer = $view['seconds_left'] !== null ? '<div class="ssq-timer" id="ssqTimer" role="timer" aria-live="off"><i class="fas fa-clock"></i> <b id="ssqClock">--:--</b></div>' : '';
$cfg = ['csrf' => csrf_token(), 'endpoint' => url('students/selfstudy/quiz_api.php'), 'attempt' => (int) $a['id'], 'resultUrl' => $itemUrl, 'secondsLeft' => $view['seconds_left'], 'n' => $n];
ssq_open($label, $course['title'], url('students/selfstudy/course.php?id=' . $courseId), $timer);
?>
<main class="ssq-wrap ssq-take">
    <section class="ssq-card ssq-q">
        <div class="hd"><h3><?php echo $e($label); ?></h3><span class="ss-pill" id="ssqPill">Question 1 of <?php echo $n; ?></span></div>
        <div class="ss-seg"><?php for ($i = 0; $i < $n; $i++) echo '<i></i>'; ?></div>
        <?php foreach ($qs as $q): ?>
            <div class="qb" data-q="<?php echo (int) $q['id']; ?>" hidden>
                <p class="ss-qt"><?php echo $e($q['text']); ?></p>
                <?php if ($q['type'] === 'multiple_choice'): foreach ($q['options'] as [$oi, $lab]): ?>
                    <label class="ss-opt"><input type="radio" name="a<?php echo (int) $q['id']; ?>" value="<?php echo (int) $oi; ?>"<?php echo $q['saved'] !== null && (string) $oi === $q['saved'] ? ' checked' : ''; ?>><span><?php echo $e($lab); ?></span></label>
                <?php endforeach; else: ?>
                    <input type="text" class="ss-txt" maxlength="300" placeholder="Type your answer" autocomplete="off" value="<?php echo $e($q['saved'] ?? ''); ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="ss-err" id="ssqErr" role="alert"></div>
        <div class="ss-qf"><span class="note" id="ssqSaved">Your answers are saved as you go.<?php echo $view['seconds_left'] !== null ? ' If time runs out, your answers so far are submitted for you.' : ''; ?></span>
            <div class="btns"><button type="button" class="ss-btn ghost" id="ssqBack" hidden>Back</button><button type="button" class="ss-btn" id="ssqNext">Next question</button></div></div>
    </section>
</main>
<?php ssq_close(json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES));
