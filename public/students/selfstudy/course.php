<?php
// public/students/selfstudy/course.php — the study screen: header, outline on the left, the open topic on the right.
// Every rule is decided by app/v1/services/SelfStudyStudy.php: a locked topic typed into the address bar sends the student back to the topic they are on.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/SelfStudyStudy.php';

$ssPreview = $ss_preview ?? null;          // set by components/selfstudy/preview.php: a tutor or admin looking at the course, nothing saved
if (!$ssPreview) Middleware::requireStudent();
$pdo = \Models\Connection::getInstance()->getConnection();
$uid = $ssPreview ? 0 : (int) SessionModel::get('user_id');
if (!$ssPreview) require __DIR__ . '/_gate.php';

$courseId = (int) ($_GET['id'] ?? 0);
$course = $ssPreview ? $ssPreview['course'] : ss_course_for_student($pdo, $courseId, $uid);           // published + shared with this student, else null
if (!$course) {
    http_response_code(404);
    $page_title = 'Self-study';
    ob_start();
    echo '<div class="card-student"><div class="as-empty"><i class="fas fa-laptop-code"></i>That course is not available to you.<br><a href="index.php" class="btn btn-brand btn-sm mt-3">Back to Self-study</a></div></div>';
    $content = ob_get_clean();
    require __DIR__ . '/../../templates/students/layout.php';
    exit;
}
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$self = $ssPreview ? $ssPreview['self'] : url('students/selfstudy/course.php');
$topicUrl = fn(int $id) => $self . '?id=' . $courseId . '&topic=' . $id;

$model = ss2_installed($pdo) ? ss2_model($pdo, $courseId, $uid) : null;
if (!$model || !$model['has_content']) {
    $page_title = $course['title'];
    ob_start();
    echo '<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">&larr; Self-study</a><div class="card-student"><div class="as-empty"><i class="fas fa-hourglass-half"></i><h2 class="h5 fw-bold text-dark">' . $e($course['title']) . '</h2><p class="mb-0">This course opens soon.</p></div></div>';
    $content = ob_get_clean();
    require __DIR__ . '/../../templates/students/layout.php';
    exit;
}

if ($ssPreview && $model) {                // a preview opens every topic (nothing is checked or saved) so the tutor can read the whole course
    foreach ($model['intro'] as $i => $t) { $model['intro'][$i]['open'] = true; $model['intro'][$i]['reason'] = ''; }
    foreach ($model['modules'] as $k => $m) {
        $model['modules'][$k]['open'] = true; $model['modules'][$k]['reason'] = '';
        foreach ($m['topics'] as $j => $t) { $model['modules'][$k]['topics'][$j]['open'] = true; $model['modules'][$k]['topics'][$j]['reason'] = ''; }
    }
    foreach ($model['topics'] as $tkey => $t) { $model['topics'][$tkey]['open'] = true; $model['topics'][$tkey]['reason'] = ''; }
    $model['resume'] = $model['flat'][0] ?? null;
}
$view = ($_GET['view'] ?? '') === 'kb' ? 'kb' : 'topic';
$topic = null; $tid = (int) ($_GET['topic'] ?? 0);
$resumeId = $model['resume'];
if ($view === 'kb' && $model['kb']['questions'] === 0) $view = 'topic';
if ($view === 'topic') {
    $topic = $model['topics'][$tid] ?? null;
    if (!$topic || !$topic['open']) {            // not there, or locked: back to the topic the student is on
        if ($resumeId) { header('Location: ' . $topicUrl((int) $resumeId)); exit; }
        header('Location: ' . url('students/selfstudy/index.php'));
        exit;
    }
    if (!$ssPreview) ss2_touch($pdo, $uid, $courseId, $tid, time());     // remembers this spot and the last studied date (never in a preview)
}

$content_t = $topic ? ss2_topic_content($pdo, $courseId, $tid) : null;
$curModuleId = $topic ? $topic['module_id'] : null;
$curModule = null; foreach ($model['modules'] as $m) if ($m['id'] === $curModuleId) $curModule = $m;

/* ---------- the outline ---------- */
$icon = fn(string $k) => '<span class="ss-ck ' . $k . '">' . ($k === 'done' ? '<i class="fas fa-check"></i>' : ($k === 'lock' ? '<i class="fas fa-lock"></i>' : '')) . '</span>';
$label = fn(array $t) => ($t['number'] !== '' ? $t['number'] . ' ' : '') . $t['title'];
$topicRow = function (array $t) use ($tid, $topicUrl, $icon, $label, $e, $view): string {
    $cur = $view === 'topic' && $t['id'] === $tid;
    $kind = !$t['open'] ? 'lock' : ($t['done'] ? 'done' : ($cur ? 'cur' : ''));
    $h = '<' . ($t['open'] ? 'a href="' . $e($topicUrl($t['id'])) . '"' : 'div') . ' class="ss-row' . ($cur ? ' cur' : '') . (!$t['open'] ? ' locked' : '') . '" data-t="' . $e(mb_strtolower($label($t))) . '"' . ($cur ? ' aria-current="page"' : '') . '>'
        . $icon($kind) . '<span><span class="t">' . $e($label($t)) . '</span>' . (!empty($t['optional']) && !$t['done'] ? '<span class="s"> · Optional</span>' : '') . (!$t['open'] ? '<span class="s d-block">' . $e($t['reason']) . '</span>' : '') . '</span></' . ($t['open'] ? 'a' : 'div') . '>';
    return $h;
};
$introRow = function (array $t) use ($tid, $topicUrl, $icon, $e, $view): string {
    $cur = $view === 'topic' && $t['id'] === $tid;
    $kind = !$t['open'] ? 'lock' : ($t['done'] ? 'done' : ($cur ? 'cur' : ''));
    return '<' . ($t['open'] ? 'a href="' . $e($topicUrl($t['id'])) . '"' : 'div') . ' class="ss-row' . (!$t['open'] ? ' locked' : '') . '" data-t="' . $e(mb_strtolower($t['title'])) . '">' . $icon($kind)
        . '<span><span class="t">' . $e($t['title']) . '</span><span class="s d-block">' . ($t['done'] ? 'Done' : ($t['open'] ? ($cur ? 'You are here' : 'Open') : $e($t['reason']))) . '</span></span></' . ($t['open'] ? 'a' : 'div') . '>';
};
$examRow = function (string $name, array $x, string $href) use ($icon, $e): string {
    $lines = $x['passed'] ? 'Passed' . ($x['best'] !== null ? ' ' . $x['best'] . '%' : '') : (!empty($x['updating']) ? 'Being updated · check again soon' : ($x['open'] ? 'Open · pass mark ' . $x['pass'] . '%' : $x['reason']));
    $tag = $x['open'] ? 'a href="' . $e($href) . '"' : 'div';
    return '<' . $tag . ' class="ss-row' . (!$x['open'] ? ' locked' : '') . '" data-t="' . $e(mb_strtolower($name)) . '">' . $icon(!$x['open'] ? 'lock' : ($x['passed'] ? 'done' : ''))
        . '<span><span class="t">' . $e($name) . '</span><span class="s d-block">' . $e($lines) . '</span></span></' . ($x['open'] ? 'a' : 'div') . '>';
};
$files = $pdo ? ss2_files($pdo, $courseId) : [];
$closeUrl = $ssPreview ? $ssPreview['close'] : url('students/selfstudy/index.php');
$quizUrl = fn(string $q) => url('students/selfstudy/quiz.php') . '?id=' . $courseId . '&' . $q;
$kbLast = $model['kb']['last'];
$kbUrl = $self . '?id=' . $courseId . '&view=kb';

/* ---------- the open topic: questions, video, finished state ---------- */
$questions = []; $answersShown = []; $videoState = null; $embed = null;
if ($topic && $content_t) {
    if ($content_t['video_id']) {
        $videoState = ss2_video_state($pdo, $uid, $tid, $topic['video_duration']);
        $origin = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $embed = ss_youtube_embed_url($content_t['video_id'], $origin) . ($videoState['furthest'] > 3 && !$topic['done'] ? '&start=' . (int) $videoState['furthest'] : '');
    }
    if ($topic['q_count'] > 0) {
        $questions = $ssPreview ? ss2_preview_questions($pdo, $courseId, $tid) : ss2_topic_questions($pdo, $uid, $courseId, $tid, time());
        if ($topic['done']) { $answersShown = ss2_topic_answers($pdo, $uid, $courseId, $questions); $commentsShown = ss2_topic_comments($pdo, $uid, $courseId, $questions); }
    }
}
$next = $topic ? ss2_next_topic($model, $tid) : null;
$videoOk = $ssPreview || !$videoState || $videoState['completed'] || $topic['video_watched'] || ($topic['done'] ?? false);

$cfg = [
    'csrf' => csrf_token(), 'course' => $courseId, 'topic' => $tid, 'endpoint' => url('students/selfstudy/progress.php'),
    'topicUrl' => $self . '?id=' . $courseId . '&topic={id}', 'resumeUrl' => $resumeId ? $topicUrl((int) $resumeId) : url('students/selfstudy/index.php'), 'kbUrl' => $kbUrl,
    'done' => (bool) ($topic['done'] ?? false), 'preview' => (bool) $ssPreview, 'next' => $next,
    'video' => ($videoState && $embed) ? ['furthest' => $videoState['furthest'], 'duration' => $videoState['duration'], 'percent' => $videoState['percent'], 'completed' => (bool) $videoOk] : null,
];
$cfgJson = json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
$logo = url('assets/images/logo/logo.png');
$curModulePct = $curModule ? $curModule['percent'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo $e($course['title']); ?> — Dazu Training Institute</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo url('assets/css/selfstudy-study.css'); ?>?v=4">
</head>
<body class="ss-study">
<?php if ($ssPreview): ?><div class="ss-prev" role="status"><i class="fas fa-eye"></i> <b>Preview as student.</b> Nothing you do here is saved: no progress, answers or attempts. All topics are open so you can look around. <a href="<?php echo $e($closeUrl); ?>">Back to editing</a></div><?php endif; ?>
<header class="ss-top">
    <button type="button" class="ss-ic only-m" data-ss-drawer aria-label="Open the course outline"><i class="fas fa-list-ul"></i></button>
    <img class="logo" src="<?php echo $e($logo); ?>" alt="Dazu Training Institute">
    <span class="sep"></span>
    <h1><?php echo $e($course['title']); ?></h1>
    <a class="ss-ic on hide-m" href="<?php echo $e($view === 'topic' && $resumeId ? $topicUrl((int) $resumeId) : $self . '?id=' . $courseId); ?>" aria-label="Course"><i class="fas fa-book-open"></i></a>
    <span class="ss-ic hide-m" title="Your progress: <?php echo (int) $model['percent']; ?>%"><i class="fas fa-chart-simple"></i></span>
    <a class="ss-ic" href="<?php echo $e($closeUrl); ?>" aria-label="<?php echo $ssPreview ? 'Close the preview and go back to the editor' : 'Close and go back to Self-study'; ?>"><i class="fas fa-xmark"></i></a>
</header>
<div class="ss-strip" aria-label="Your progress">
    <div class="r"><span class="l">Whole course</span><div class="ss-bar"><i style="width:<?php echo (int) $model['percent']; ?>%"></i></div><b><?php echo (int) $model['percent']; ?>%</b></div>
    <?php if ($curModule): ?><div class="r"><span class="l">Module <?php echo (int) $curModule['no']; ?></span><div class="ss-bar"><i style="width:<?php echo (int) $curModulePct; ?>%"></i></div><b><?php echo (int) $curModulePct; ?>%</b></div><?php endif; ?>
</div>
<div class="ss-ovl"></div>
<div class="ss-wrap">
<aside class="ss-side" aria-label="Course outline">
    <div class="ss-tabs" role="tablist">
        <button type="button" class="ss-tab on" role="tab" aria-selected="true" data-pane="ssOutline">Course Outline</button>
        <button type="button" class="ss-tab" role="tab" aria-selected="false" data-pane="ssRes">Resources</button>
    </div>
    <div class="ss-pane" id="ssOutline">
        <div class="ss-whole"><div class="row"><span>Whole course</span><b><?php echo (int) $model['percent']; ?>%</b></div><div class="ss-bar"><i style="width:<?php echo (int) $model['percent']; ?>%"></i></div></div>
        <label class="ss-search"><input type="search" id="ssFind" placeholder="Search course outline" aria-label="Search course outline" autocomplete="off"><i class="fas fa-search"></i></label>
        <?php if ($model['kb']['questions'] > 0): ?>
            <a class="ss-row" href="<?php echo $e($kbUrl); ?>" data-t="knowledge check"><?php echo $icon($kbLast ? 'done' : ($view === 'kb' ? 'cur' : '')); ?>
                <span><span class="t">Knowledge Check</span><span class="s d-block"><?php echo $kbLast ? 'Done · ' . (int) $kbLast['correct'] . ' of ' . (int) $kbLast['total'] . ' · no pass mark' : 'Optional · no pass mark'; ?></span></span><i class="fas fa-paper-plane go"></i></a>
        <?php endif; ?>
        <?php foreach ($model['intro'] as $t) echo $introRow($t); ?>
        <?php foreach ($model['modules'] as $m): $isCur = $curModule && $curModule['id'] === $m['id'];
              $pctText = $m['quiz']['passed'] && $m['quiz']['best'] !== null ? 'Passed ' . $m['quiz']['best'] . '%' : (!$m['open'] ? 'Locked' : $m['percent'] . '%'); ?>
            <div class="ss-mod<?php echo $isCur ? ' open' : ''; ?><?php echo !$m['open'] ? ' locked' : ''; ?>" data-t="<?php echo $e(mb_strtolower($m['title'])); ?>">
                <button type="button" class="ss-modh" aria-expanded="<?php echo $isCur ? 'true' : 'false'; ?>">
                    <span class="row"><span class="n"><?php echo $e($m['title']); ?></span><i class="fas <?php echo $m['open'] ? 'fa-chevron-down chev' : 'fa-lock chev'; ?>"></i></span>
                    <span class="meta"><span class="ss-bar"><i style="width:<?php echo (int) $m['percent']; ?>%"></i></span><small><?php echo $e($pctText); ?></small></span>
                </button>
                <?php if (!$m['open']): ?><div class="ss-why"><?php echo $e($m['reason']); ?></div><?php endif; ?>
                <div class="ss-mbody">
                    <?php foreach ($m['topics'] as $t) echo $topicRow($t); ?>
                    <?php $qx = $m['quiz']; $qName = 'Module ' . $m['no'] . ' quiz';
                          $qTag = $qx['open'] ? 'a href="' . $e($quizUrl('module=' . $m['id'])) . '"' : 'div'; ?>
                    <<?php echo $qTag; ?> class="ss-row<?php echo !$qx['open'] ? ' locked' : ''; ?>" data-t="<?php echo $e(mb_strtolower($qName)); ?>">
                        <?php echo $icon(!$qx['open'] ? 'lock' : ($qx['passed'] ? 'done' : '')); ?>
                        <span><span class="t"><?php echo $e($qName); ?></span><span class="s"> · <?php echo $qx['passed'] ? 'Passed' : (!empty($qx['updating']) ? 'Being updated · check again soon' : (int) $qx['pass'] . '% to pass'); ?></span></span>
                    </<?php echo $qx['open'] ? 'a' : 'div'; ?>>
                </div>
            </div>
        <?php endforeach; ?>
        <?php echo $examRow('Checkpoint Exam', $model['checkpoint'], $quizUrl('exam=checkpoint')); ?>
        <?php echo $examRow('Final Exam', $model['final'], $quizUrl('exam=final')); ?>
    </div>
    <div class="ss-pane ss-files" id="ssRes" hidden>
        <?php if (!$files): ?><div class="ss-empty">Your tutor has not attached any files yet.</div><?php endif; ?>
        <?php foreach ($files as $f): ?>
            <<?php echo $ssPreview ? 'div' : 'a href="' . $e(url('students/selfstudy/file.php') . '?id=' . (int) $f['id']) . '"'; ?> class="ss-row"><i class="fas fa-file-arrow-down f"></i><span><span class="t"><?php echo $e($f['file_name']); ?></span><?php echo $ssPreview ? '<span class="s">Students can download this file</span>' : ''; ?></span></<?php echo $ssPreview ? 'div' : 'a'; ?>>
        <?php endforeach; ?>
    </div>
</aside>

<main class="ss-main">
    <div class="ss-mhead">
        <i class="fas fa-list-ul hide-m" style="font-size:15px"></i>
        <span class="ttl"><?php echo $view === 'kb' ? 'Knowledge Check' : $e($label($topic)); ?></span>
        <span class="saved">Saved automatically · you can return to this spot any time</span>
    </div>

<?php if ($view === 'kb'): $kq = ss2_kb_questions($pdo, $courseId); $retake = isset($_GET['retake']); ?>
    <div class="ss-crumb">Before you start</div>
    <h2 class="title">Knowledge Check</h2>
    <?php if ($kbLast && !$retake): ?>
        <div class="ss-done" style="margin-top:6px"><span class="ss-score"><?php echo (int) $kbLast['correct']; ?> of <?php echo (int) $kbLast['total']; ?></span><span>Your latest score. There is no pass mark, and nothing is locked by this check.</span>
            <a class="ss-btn ghost btn-next" href="<?php echo $e($kbUrl . '&retake=1'); ?>">Retake</a></div>
        <p class="mt-3"><a class="ss-btn" href="<?php echo $e($resumeId ? $topicUrl((int) $resumeId) : $self . '?id=' . $courseId); ?>">Go to the course</a></p>
    <?php else: ?>
        <p class="ss-doc">Answer these questions to see where you are. There is no pass mark and nothing is locked, and you can retake it any time.</p>
        <div class="ss-q" id="ssKb" style="margin-top:10px">
            <?php foreach ($kq as $n => $q): ?>
                <div data-kq="<?php echo (int) $q['id']; ?>" style="margin-bottom:22px">
                    <p class="ss-qt"><?php echo ($n + 1) . '. ' . $e($q['text']); ?></p>
                    <?php foreach ($q['options'] as [$oi, $lab]): ?><label class="ss-opt"><input type="radio" name="q<?php echo (int) $q['id']; ?>" value="<?php echo (int) $oi; ?>"><span><?php echo $e($lab); ?></span></label><?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <div class="ss-qf"><span class="note">Answer every question, then submit to see your score.</span><div class="btns"><button type="button" class="ss-btn" id="ssKbSubmit">Submit</button></div></div>
            <div class="ss-err" id="ssKbErr"></div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="ss-crumb"><?php
        if ($topic['is_intro']) echo 'Course Introduction';
        else echo $e($curModule ? $curModule['title'] : 'Course') . ' &rsaquo; Topic ' . $e($topic['number']) . ' of ' . (int) $topic['count'];
    ?></div>
    <h2 class="title"><?php echo $e($label($topic)); ?></h2>
    <div class="ss-doc"><?php echo $content_t['html']; /* cleaned with the allow-list in SelfStudyHtml.php */ ?></div>

    <?php if ($embed): ?>
        <div class="ss-video"><iframe id="ssFrame" src="<?php echo $e($embed); ?>" title="<?php echo $e($topic['title']); ?> video" allow="encrypted-media; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin" loading="lazy"></iframe></div>
        <div class="ss-vs<?php echo $videoOk ? ' ok' : ''; ?>" id="ssVStatus"><i class="fas fa-eye" style="margin-top:4px"></i>
            <span><?php echo $ssPreview ? '<b>Preview.</b> Video tracking and the "watch to the end" rule are switched off here.' : ($videoOk ? '<b>Video watched.</b> You can answer the topic questions now.'
                : '<b>Watched ' . (int) $videoState['percent'] . '%.</b> Watch to the end to finish this video. Skipping ahead is switched off, and the video pauses if you leave this page.'); ?></span></div>
    <?php endif; ?>

    <?php if ($topic['done']): ?>
        <div class="ss-done"><i class="fas fa-circle-check"></i> You finished this topic.
            <?php if ($next): ?><a class="ss-btn btn-next" href="<?php echo $e($topicUrl($next)); ?>">Next topic</a><?php endif; ?></div>
        <?php if ($questions): ?>
            <div class="ss-ro"><h3 style="font-size:18px;font-weight:600">Your answers</h3>
                <?php foreach ($questions as $n => $q): ?><p class="ss-qt"><?php echo ($n + 1) . '. ' . $e($q['text']); ?></p><div class="a"><?php echo $e($answersShown[$q['id']] ?? ''); ?></div><?php if (!empty($commentsShown[$q['id']])): ?><div class="cm"><b>Your tutor's comment:</b> <?php echo $e($commentsShown[$q['id']]); ?></div><?php endif; ?><?php endforeach; ?></div>
        <?php endif; ?>
    <?php elseif ($questions): ?>
        <section class="ss-q" aria-label="Topic questions">
            <div class="hd"><h3>Topic questions</h3><span class="ss-pill" id="ssPill">Question 1 of <?php echo count($questions); ?></span></div>
            <div class="ss-seg"><?php foreach ($questions as $i => $q) echo '<i></i>'; ?></div>
            <?php foreach ($questions as $q): ?>
                <div data-q="<?php echo (int) $q['id']; ?>" hidden>
                    <p class="ss-qt"><?php echo $e($q['text']); ?></p>
                    <?php if ($q['type'] === 'multiple_choice'): foreach ($q['options'] as [$oi, $lab]): ?>
                        <label class="ss-opt"><input type="radio" name="a<?php echo (int) $q['id']; ?>" value="<?php echo (int) $oi; ?>"><span><?php echo $e($lab); ?></span></label>
                    <?php endforeach; elseif ($q['type'] === 'short_answer'): ?>
                        <input type="text" class="ss-txt" maxlength="300" placeholder="Type your answer" autocomplete="off">
                    <?php else: ?>
                        <textarea class="ss-txt" maxlength="5000" placeholder="Write your answer. Your tutor will read it."></textarea>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="ss-err" id="ssErr"></div>
            <div class="ss-qf"><span class="note">There is no pass mark on topic questions. Submit all <?php echo count($questions); ?> to open the next topic.</span>
                <div class="btns"><button type="button" class="ss-btn ghost" id="ssBack" hidden>Back</button><button type="button" class="ss-btn" id="ssNext" disabled>Next question</button></div></div>
        </section>
    <?php else: ?>
        <div class="ss-q"><div class="ss-qf" style="margin-top:0"><span class="note"><?php echo $videoOk ? 'That is the end of this topic.' : 'Watch the video to the end to finish this topic.'; ?></span>
            <div class="btns"><button type="button" class="ss-btn" id="ssMarkDone"<?php echo $videoOk ? '' : ' disabled'; ?>>Mark as done and continue</button></div></div><div class="ss-err" id="ssErr2"></div></div>
    <?php endif; ?>
<?php endif; ?>
</main>
</div>
<script type="application/json" id="ssCfg"><?php echo $cfgJson; ?></script>
<script src="<?php echo url('assets/js/selfstudy-study.js'); ?>?v=1" defer></script>
</body>
</html>
