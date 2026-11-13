<?php
// public/students/selfstudy/quiz.php — one module quiz or exam: the start screen, the result of the last try, "Try again" and "Ask my tutor for a reset".
//   quiz.php?id=<course>&module=<module id>      a module quiz
//   quiz.php?id=<course>&exam=checkpoint|final   an exam
// Everything is checked on the server: a locked item sends the student back to the course; an open attempt is continued, never duplicated.
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

$courseId = (int) ($_GET['id'] ?? 0);
$course = ss_course_for_student($pdo, $courseId, $uid);
$courseUrl = url('students/selfstudy/course.php?id=' . $courseId);
if (!$course || !ss2_installed($pdo) || !ss3_installed($pdo)) { header('Location: ' . ($course ? $courseUrl : url('students/selfstudy/index.php'))); exit; }

$now = time();
ss3_expire_due($pdo, $now, $uid);               // an attempt whose time ran out is submitted before anything is shown
$kind = isset($_GET['module']) ? 'module_quiz' : (($_GET['exam'] ?? '') === 'checkpoint' ? 'checkpoint_exam' : (($_GET['exam'] ?? '') === 'final' ? 'final_exam' : ''));
$moduleId = $kind === 'module_quiz' ? (int) $_GET['module'] : null;
$self = url('students/selfstudy/quiz.php') . '?id=' . $courseId . ($kind === 'module_quiz' ? '&module=' . $moduleId : '&exam=' . ($kind === 'checkpoint_exam' ? 'checkpoint' : 'final'));
$model = $kind ? ss2_model($pdo, $courseId, $uid) : null;
$it = $model ? ss3_item($model, $kind, $moduleId) : null;
if (!$it || !$it['item']['open']) { header('Location: ' . $courseUrl); exit; }      // locked, or not part of this course: back to the course

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$notice = null; $noticeOk = false; $same = false;
$open = ss3_open_attempt($pdo, $uid);
if ($open) {
    $same = (int) $open['course_id'] === $courseId && $open['kind'] === $kind && (int) ($open['module_id'] ?? 0) === (int) ($moduleId ?? 0);
    if ($same) { header('Location: ' . url('students/selfstudy/attempt.php?a=' . (int) $open['id'])); exit; }
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $notice = 'Your session expired. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'start') {
        $r = ss3_start($pdo, $uid, $courseId, $kind, $moduleId, $now);
        if ($r['ok']) { header('Location: ' . url('students/selfstudy/attempt.php?a=' . (int) $r['attempt']['id'])); exit; }
        $notice = $r['error'];
    } elseif (($_POST['action'] ?? '') === 'reset') {
        $r = ss3_request_reset($pdo, $uid, $courseId, $kind, $moduleId, (string) ($_POST['reason'] ?? ''), $now);
        if ($r['ok']) {
            SessionModel::set('ssq_notice', 'Your request was sent to your tutor and to the office. You will get a notification when it is handled.');
            header('Location: ' . $self);       // no re-send on refresh
            exit;
        }
        $notice = $r['error'];
    }
}
if (SessionModel::get('ssq_notice')) { $notice = SessionModel::get('ssq_notice'); $noticeOk = true; unset($_SESSION['ssq_notice']); }

$latest = ss3_latest_attempt($pdo, $uid, $courseId, $kind, $moduleId);
$res = $latest ? ss3_result($pdo, $uid, $latest, $model, $now) : null;
$tries = $res ? $res['tries'] : ss3_tries($pdo, $uid, $courseId, $kind, $moduleId, $now);
$info = SS3_KINDS[$kind]; $set = ss3_course_settings($pdo, $courseId);
$rules = $res ? $res['rules'] : ['pass' => $info['pass'], 'timed' => $info['timed'], 'minutes' => $info['min_col'] ? $set[$info['min_col']] : null, 'limits' => $info['limits']];
$label = $it['label'];
$nextSteps = $res ? $res['next'] : ss3_next_steps($model, $kind, $moduleId);
$blocked = $tries['blocked'];
$updating = !empty($it['item']['updating']);          // the tutor removed questions: it cannot be started until the bank is full again
$updatingMsg = 'This ' . ($kind === 'module_quiz' ? 'quiz' : 'exam') . ' is being updated. Please check again soon.';
$resumeTopic = $model['resume'] ? $courseUrl . '&topic=' . (int) $model['resume'] : $courseUrl;
$backAt = $blocked ? (new DateTime('@' . $tries['next_at']))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('l j F \a\t H:i') : '';

ssq_open($label . ($res ? ' result' : ''), $course['title'], $courseUrl);
?>
<main class="ssq-wrap">
<?php if ($notice): ?><div class="ssq-note <?php echo $noticeOk ? 'ok' : 'bad'; ?>" role="status"><?php echo $e($notice); ?></div><?php endif; ?>
<?php if ($open && !$same): ?>
    <div class="ssq-note bad" role="alert">You already have another quiz or exam open. <a href="<?php echo $e(url('students/selfstudy/attempt.php?a=' . (int) $open['id'])); ?>">Go back to it</a> and finish it first.</div>
<?php endif; ?>
<div class="ssq-grid">
<div class="ssq-main">
<?php if ($res): $pct = $res['percent']; $ok = $res['passed']; $circ = 2 * M_PI * 80; ?>
    <section class="ssq-card ssq-hero">
        <div class="ssq-ring <?php echo $ok ? 'pass' : 'fail'; ?>" role="img" aria-label="Score <?php echo $pct; ?> percent, <?php echo $ok ? 'passed' : 'not passed'; ?>">
            <svg viewBox="0 0 180 180" width="180" height="180"><circle cx="90" cy="90" r="80" class="bg"/><circle cx="90" cy="90" r="80" class="fg" stroke-dasharray="<?php echo round($circ * $pct / 100, 1) . ' ' . round($circ, 1); ?>" transform="rotate(-90 90 90)"/></svg>
            <b><?php echo $pct; ?>%</b>
        </div>
        <div class="ssq-hero-t">
            <div class="kick"><?php echo $e(mb_strtoupper($label)); ?> · RESULT</div>
            <h2 class="<?php echo $ok ? 'pass' : 'fail'; ?>"><?php echo $ok ? '<i class="fas fa-circle-check"></i> Passed' : 'Not passed yet'; ?></h2>
            <p><?php
                echo 'You got ' . (int) $res['correct'] . ' of ' . (int) $res['total'] . ' right. ';
                if ($res['timed_out']) echo 'Time ran out, so your answers were submitted for you. ';
                if ($ok) {
                    echo $kind === 'module_quiz' ? ($res['next_module'] ? 'Module ' . (int) $res['next_module']['no'] . ' is now open.' : 'Well done. The next part of the course is open.')
                        : ($kind === 'checkpoint_exam' ? 'The Final Exam is now open.' : 'You have finished the course. Well done!');
                } else {
                    echo 'You need ' . (int) $res['pass'] . '% to ' . ($kind === 'module_quiz' ? 'open ' . ($res['next_module'] ? 'Module ' . (int) $res['next_module']['no'] : 'the next part') : 'pass') . '. Read the topics below again, then try once more.';
                }
            ?></p>
            <?php if (!$ok): ?>
                <div class="ssq-tries"><span class="pips" aria-hidden="true"><?php
                    $primary = $tries['windows']['week'] ?? $tries['windows']['day'];
                    for ($i = 0; $i < $primary['limit']; $i++) echo '<i class="' . ($i < $primary['used'] ? 'used' : '') . '"></i>';
                ?></span><span><?php echo $e($res['tries_text']); ?></span></div>
            <?php endif; ?>
        </div>
        <div class="ssq-actions">
            <?php if ($ok): ?>
                <a class="ss-btn" href="<?php echo $e($res['next_module'] && $res['next_module']['topics'] ? $courseUrl . '&topic=' . (int) $res['next_module']['topics'][0]['id'] : ($kind === 'final_exam' ? url('students/selfstudy/index.php') : $courseUrl)); ?>"><?php echo $kind === 'final_exam' ? 'Back to Self-study' : 'Continue the course'; ?></a>
            <?php else: ?>
                <?php if ($updating): ?>
                    <span class="ssq-back" role="status"><i class="fas fa-screwdriver-wrench"></i> <?php echo $e($updatingMsg); ?></span>
                <?php elseif (!$blocked): ?>
                    <form method="post"><input type="hidden" name="csrf_token" value="<?php echo $e(csrf_token()); ?>"><input type="hidden" name="action" value="start"><button class="ss-btn" type="submit"<?php echo $open ? ' disabled' : ''; ?>>Try again</button></form>
                <?php else: ?>
                    <span class="ssq-back" role="status"><i class="fas fa-clock"></i> No tries left. Your tries come back <?php echo $e($backAt); ?> (Nairobi time).</span>
                <?php endif; ?>
                <?php if ($res['first_link']): ?><a class="ss-btn ghost" href="<?php echo $e($courseUrl . '&topic=' . (int) $res['first_link']['topic']); ?>">Go to <?php echo $e(preg_match('/^\d/', $res['first_link']['label']) ? 'topic ' . strtok($res['first_link']['label'], ' ') : $res['first_link']['label']); ?></a><?php endif; ?>
                <button type="button" class="ss-btn ghost" id="ssqAsk"<?php echo $res['waiting'] ? ' disabled' : ''; ?>><i class="fas fa-comment"></i> <?php echo $res['waiting'] ? 'Reset request sent' : 'Ask my tutor for a reset'; ?></button>
            <?php endif; ?>
        </div>
        <?php if (!$ok && !$res['waiting']): ?>
        <form method="post" class="ssq-ask" id="ssqAskForm" hidden>
            <input type="hidden" name="csrf_token" value="<?php echo $e(csrf_token()); ?>"><input type="hidden" name="action" value="reset">
            <label for="ssqReason">Why would you like a reset? (a short reason, up to 200 letters)</label>
            <textarea id="ssqReason" name="reason" maxlength="200" rows="3" required></textarea>
            <button class="ss-btn" type="submit">Send to my tutor</button>
        </form>
        <?php endif; ?>
    </section>
    <?php if (!$ok): ?>
    <section class="ssq-card ssq-look">
        <div class="hd"><h3>Questions to look at again</h3><span>The correct answers are not shown</span></div>
        <?php if (!$res['look']): ?><p class="mut">No questions to show.</p><?php endif; ?>
        <?php foreach ($res['look'] as $l): ?>
            <div class="qrow"><span class="qn">Q<?php echo (int) $l['n']; ?></span>
                <div class="qt"><p><?php echo $e($l['text']); ?></p><small class="bad">Not correct</small></div>
                <?php if ($l['link'] && $l['link']['open']): ?><a class="chip" href="<?php echo $e($courseUrl . '&topic=' . (int) $l['link']['topic']); ?>">Read again: <?php echo $e($l['link']['label']); ?></a><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
<?php else: /* nothing taken yet: the start screen */ ?>
    <section class="ssq-card ssq-start">
        <div class="kick"><?php echo $e(mb_strtoupper($label)); ?></div>
        <h2><?php echo $e($label); ?></h2>
        <p>You need <b><?php echo (int) $rules['pass']; ?>%</b> to pass. <?php
            echo $rules['timed'] ? 'You have <b>' . (int) $rules['minutes'] . ' minutes</b> once you start, and the timer keeps running if you close the page. ' : '';
            echo 'The questions and the answers are shuffled for you, shown one at a time, and marked as soon as you submit. A try counts the moment you start, so closing the page does not give you another one.'; ?></p>
        <div class="ssq-tries"><span><?php echo $e(ss3_tries_text($tries, $kind)); ?></span></div>
        <div class="ssq-actions">
            <?php if ($updating): ?>
                <span class="ssq-back" role="status"><i class="fas fa-screwdriver-wrench"></i> <?php echo $e($updatingMsg); ?></span>
            <?php elseif ($blocked): ?>
                <span class="ssq-back" role="status"><i class="fas fa-clock"></i> No tries left. Your tries come back <?php echo $e($backAt); ?> (Nairobi time).</span>
            <?php else: ?>
                <form method="post"><input type="hidden" name="csrf_token" value="<?php echo $e(csrf_token()); ?>"><input type="hidden" name="action" value="start"><button class="ss-btn" type="submit"<?php echo $open ? ' disabled' : ''; ?>>Start</button></form>
            <?php endif; ?>
            <a class="ss-btn ghost" href="<?php echo $e($resumeTopic); ?>">Back to the course</a>
        </div>
    </section>
<?php endif; ?>
</div>
<aside class="ssq-side">
    <section class="ssq-card">
        <h4>What happens next</h4>
        <?php foreach ($nextSteps as $s): ?>
            <div class="nx <?php echo $e($s['state']); ?>">
                <span class="ic"><?php echo $s['state'] === 'passed' ? '<i class="fas fa-check"></i>' : ($s['state'] === 'locked' ? '<i class="fas fa-lock"></i>' : ''); ?></span>
                <span><b><?php echo $e($s['title']); ?></b><?php if ($s['sub'] !== ''): ?><small><?php echo $e($s['sub']); ?></small><?php endif; ?></span>
            </div>
        <?php endforeach; ?>
    </section>
    <section class="ssq-card rules">
        <h4><?php echo $kind === 'module_quiz' ? 'Quiz rules' : 'Exam rules'; ?></h4>
        <dl>
            <dt>Pass mark</dt><dd><?php echo (int) $rules['pass']; ?>%</dd>
            <dt>Time to finish</dt><dd><?php echo $rules['timed'] ? (int) $rules['minutes'] . ' minutes' : 'No time limit'; ?></dd>
            <?php if (isset($rules['limits']['week'])): ?><dt>Tries per week</dt><dd><?php echo (int) $rules['limits']['week']; ?></dd>
            <?php else: ?><dt>Tries per day</dt><dd><?php echo (int) $rules['limits']['day']; ?></dd><dt>Tries per month</dt><dd><?php echo (int) $rules['limits']['month']; ?></dd><?php endif; ?>
        </dl>
        <p><?php echo $kind === 'checkpoint_exam' ? 'The Checkpoint Exam must be passed before the Final Exam opens. ' : ($kind === 'module_quiz' ? 'Pass this quiz to open the next module. ' : ''); ?>Questions and answers are shuffled each time. If you run out of tries, you can ask your tutor for a reset.</p>
    </section>
</aside>
</div>
</main>
<?php ssq_close();
