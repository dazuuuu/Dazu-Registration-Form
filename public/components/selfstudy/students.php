<?php
// public/components/selfstudy/students.php — how the students are doing on a self-study course, and the reset requests.
// A tutor sees only their own students; the course owner sees every student (with who added them); admin sees everyone; staff cannot open this page.
// All of it is decided by ssd_overview() on the server; the screen only shows it.
require __DIR__ . '/boot.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/../../../app/v1/services/SelfStudyProgress.php';

$id = (int) ($_GET['id'] ?? 0);
if (!$sst_ready || !ssc_installed($db) || !ssa_installed($db)) sst_deny(503, 'Self-study needs the latest updates first.', $sst_base);
try { $ov = ssd_overview($db, $viewer, $id); } catch (SscException $e) { sst_deny($e->getCode() ?: 404, $e->getMessage(), $sst_base); }
$c = $ov['course']; $isAdmin = $viewer['role'] === 'admin'; $S = $ov['summary'];
$page_title = 'Students: ' . $c['title'];
$extra_css = '<link rel="stylesheet" href="' . sst_e(url('assets/css/selfstudy-tutor.css')) . '?v=4">';
$cfg = ['csrf' => csrf_token(), 'endpoint' => $sst_base . 'students_api.php', 'course' => $id, 'isAdmin' => $isAdmin, 'items' => $ov['items'], 'questionsUrl' => $sst_base . 'questions.php?id=' . $id, 'settings' => $ov['settings'], 'open' => (int) ($_GET['open'] ?? 0)];
$resets = $isAdmin ? ssd_all_resets($db, $viewer, $id) : [];
$ago = function (?int $ts) { return $ts ? (new DateTime('@' . $ts))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('j M Y, H:i') : ''; };
ob_start();
?>
<div class="sst-page sst-ed sst-stu">
<div class="sts-top">
    <div class="sts-title"><span class="sts-cv"><?php echo sst_cover_img($c); ?></span><h2><?php echo sst_e($c['title']); ?></h2><?php echo sst_pill($c['status']); ?></div>
    <div class="sts-btns">
        <?php if ($c['can_edit'] || $isAdmin || $c['access'] === 'view'): ?><a class="sst-btn" href="<?php echo sst_e($sst_base . 'edit.php?id=' . $id); ?>"><i class="fas fa-pen"></i> <?php echo $c['can_edit'] ? 'Edit course' : 'Open course'; ?></a><?php endif; ?>
        <a class="sst-btn" href="<?php echo sst_e($sst_base . 'sharing.php?id=' . $id); ?>"><i class="fas fa-share-nodes"></i> Sharing</a>
    </div>
</div>
<div class="sts-msg" id="stsMsg" role="status" hidden></div>
<?php echo sst_bank_warnings($db, $id, $sst_base); ?>
<div class="sts-stats">
    <div class="sts-stat"><span>Students on this course</span><b><?php echo (int) $S['students']; ?></b><small>from <?php echo (int) $S['courses']; ?> <?php echo $S['courses'] === 1 ? 'course' : 'courses'; ?></small></div>
    <div class="sts-stat"><span>Started</span><b><?php echo (int) $S['started']; ?> of <?php echo (int) $S['students']; ?></b><small><?php echo (int) $S['not_opened']; ?> have not opened it</small></div>
    <div class="sts-stat"><span>Average progress</span><b><?php echo (int) $S['average']; ?>%</b><small>across all students</small></div>
    <div class="sts-stat"><span>Finished (badge)</span><b><?php echo (int) $S['finished']; ?></b><small>passed the Final Exam</small></div>
</div>
<div class="sts-grid">
    <section class="sst-card sts-list" aria-label="Students on this course">
        <div class="sts-lh">
            <h3>Students on this course</h3>
            <div class="sts-chips" role="group" aria-label="Filter students">
                <button type="button" class="on" data-f="all">All <?php echo (int) $ov['counts']['all']; ?></button>
                <button type="button" data-f="slowing">Slowing down <?php echo (int) $ov['counts']['slowing']; ?></button>
                <button type="button" data-f="notworking">Not working <?php echo (int) $ov['counts']['notworking']; ?></button>
            </div>
        </div>
        <?php if (!$ov['rows']): ?><div class="sts-empty"><i class="fas fa-users"></i><p><?php echo $c['status'] === 'published' ? 'No students to show yet. ' . ($ov['all'] ? 'Choose students in the "Who sees it" tab.' : 'None of your own students is on this course.') : 'Students appear here once the course is published and shared with them.'; ?></p></div><?php else: ?>
        <div class="sts-head" aria-hidden="true"><span>Student</span><span>Progress</span><span>Last studied</span><span>System comment</span></div>
        <?php foreach ($ov['rows'] as $r): ?>
            <div class="sts-row" tabindex="0" role="button" data-sid="<?php echo (int) $r['id']; ?>" data-st="<?php echo sst_e($r['status']['key']); ?>" aria-label="Open the details of <?php echo sst_e($r['name']); ?>">
                <div class="sts-who"><?php echo $r['photo'] ? '<img class="ssa-av" src="' . sst_e($r['photo']) . '" alt="" loading="lazy">' : '<span class="ssa-av ini">' . sst_e($r['initials']) . '</span>'; ?>
                    <span><b><?php echo sst_e($r['name']); ?></b><small><?php echo sst_e(trim($r['number'] . ($r['course'] !== '' ? ' · ' . $r['course'] : ''), ' ·')); ?></small><?php if ($r['added_by'] !== ''): ?><small class="by">added by <?php echo sst_e($r['added_by']); ?></small><?php endif; ?></span></div>
                <div class="sts-prog"><div class="pl"><span><?php echo sst_e($r['module']); ?></span><b><?php echo (int) $r['percent']; ?>%</b></div><div class="pb"><i class="<?php echo sst_e($r['status']['key']); ?>" style="width:<?php echo (int) $r['percent']; ?>%"></i></div></div>
                <div class="sts-last"><span class="lab">Last studied</span><?php echo sst_e($r['last']); ?></div>
                <div class="sts-cmt"><span class="sts-badge <?php echo sst_e($r['status']['key']); ?>"><i></i><?php echo sst_e($r['status']['word']); ?></span><p><?php echo sst_e($r['status']['reason']); ?></p><?php if ($r['long']): ?><p class="lg"><i class="fas fa-pen"></i> <?php echo (int) $r['long']; ?> long <?php echo $r['long'] === 1 ? 'answer' : 'answers'; ?> waiting for you</p><?php endif; ?></div>
            </div>
        <?php endforeach; ?>
        <p class="sst-hint sts-none" id="stsNone" hidden>No student matches this filter.</p>
        <?php endif; ?>
    </section>
    <div class="sts-side">
        <section class="sst-card sts-req" aria-label="Reset requests">
            <div class="sts-rh"><h3>Reset requests</h3><?php if ($ov['requests']): ?><span class="sts-wait"><?php echo count($ov['requests']); ?> waiting</span><?php endif; ?></div>
            <?php if (!$ov['requests']): ?><p class="mut">No reset requests waiting.</p><?php endif; ?>
            <?php foreach ($ov['requests'] as $q): ?>
                <div class="sts-rq" data-rid="<?php echo (int) $q['id']; ?>">
                    <div class="who"><?php echo $q['photo'] ? '<img class="ssa-av" src="' . sst_e($q['photo']) . '" alt="" loading="lazy">' : '<span class="ssa-av ini">' . sst_e($q['initials']) . '</span>'; ?><span><b><?php echo sst_e($q['student']); ?></b><small><?php echo sst_e($q['item']); ?> · <?php echo sst_e($q['tries']); ?></small></span></div>
                    <p class="rs">“<?php echo sst_e($q['reason']); ?>”</p>
                    <p class="lim<?php echo $q['can_reset'] ? '' : ' off'; ?>"><?php echo sst_e($q['limit_text']); ?><?php echo $q['can_reset'] ? '' : ' · Only admin can reset now'; ?></p>
                    <div class="bt"><button type="button" class="sst-btn sst-primary" data-do="reset" data-id="<?php echo (int) $q['id']; ?>"<?php echo $q['can_reset'] ? '' : ' disabled'; ?>><?php echo $q['category'] === 'quiz' ? 'Reset quiz' : 'Reset exam'; ?></button><button type="button" class="sst-btn" data-do="notnow" data-id="<?php echo (int) $q['id']; ?>">Not now</button></div>
                    <div class="nn" hidden><label>Optional note for <?php echo sst_e(strtok($q['student'], ' ')); ?></label><textarea class="sst-in" rows="2" maxlength="300" placeholder="For example: read topic 2.2 again first"></textarea><div class="bt"><button type="button" class="sst-btn" data-do="notnow-send" data-id="<?php echo (int) $q['id']; ?>">Send</button></div></div>
                </div>
            <?php endforeach; ?>
        </section>
        <section class="sst-card sts-mine"><h4>Your resets this month</h4>
            <?php if ($isAdmin): ?><p class="sst-hint">As admin you can reset beyond the tutor limit. Every reset is saved with who did it and why.</p>
            <?php else: ?>
            <dl><dt>Module quizzes</dt><dd><?php echo (int) $ov['mine']['quiz']; ?> done</dd><dt>Exams</dt><dd><?php echo (int) $ov['mine']['exam']; ?> done</dd></dl>
            <p class="sst-hint">You can reset one student <?php echo (int) $ov['settings']['reset_quiz_month']; ?> times a month for module quizzes and <?php echo (int) $ov['settings']['reset_exam_month']; ?> times a month for exams. After that, only admin can reset. Every reset is saved with who did it and why.</p><?php endif; ?>
        </section>
    </div>
</div>
<?php if ($isAdmin): ?>
<section class="sst-card sts-all"><h4>Every reset on this course</h4>
    <?php if (!$resets): ?><p class="mut">No reset yet.</p><?php else: ?><div class="sts-tw"><table class="sts-rt"><thead><tr><th>When (Nairobi)</th><th>Student</th><th>Quiz or exam</th><th>Done by</th><th>Why</th><th>Cleared</th></tr></thead><tbody>
    <?php foreach ($resets as $r): ?><tr><td data-l="When"><?php echo sst_e($ago($r['when'])); ?></td><td data-l="Student"><?php echo sst_e($r['student']); ?></td><td data-l="Quiz or exam"><?php echo sst_e($r['item']); ?></td><td data-l="Done by"><?php echo sst_e($r['by']); ?></td><td data-l="Why"><?php echo sst_e($r['reason']); ?><?php if ($r['note']): ?><small class="d-block">Note: <?php echo sst_e($r['note']); ?></small><?php endif; ?></td><td data-l="Cleared"><?php echo (int) $r['cleared']; ?> <?php echo $r['cleared'] === 1 ? 'try' : 'tries'; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php endif; ?>
</div>
<div class="sts-sheet" id="stsSheet" hidden><div class="sts-sheetbg" data-close="1"></div><aside class="sts-panel" role="dialog" aria-modal="true" aria-labelledby="stsPT"><button type="button" class="sts-x" data-close="1" aria-label="Close"><i class="fas fa-xmark"></i></button><div id="stsPanel"></div></aside></div>
<script type="application/json" id="stsCfg"><?php echo json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<script src="<?php echo sst_e(url('assets/js/selfstudy-students.js')); ?>?v=1" defer></script>
<?php
$content = ob_get_clean();
require $sst_layout;
