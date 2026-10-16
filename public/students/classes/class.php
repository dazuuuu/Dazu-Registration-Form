<?php
// One class for the student: their own details only (link only when online/hybrid — removed on the server),
// Join button 10 minutes before, Cancelled/Moved labels, notes and materials after the class, attendance, self check-in.
require __DIR__ . '/_boot.php';

$id = (int) ($_GET['id'] ?? 0);
$v = cal_student_class($db, $me, $id);   // null if this is not the student's class
if (!$v) {
    http_response_code(404);
    SessionModel::setFlash('error', 'Class not found.');
    header('Location: index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_verify($_POST['csrf_token'] ?? null) && ($_POST['act'] ?? '') === 'checkin') {
        $r = cal_checkin($db, $me, $id);
        SessionModel::setFlash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Checked in. Your tutor will confirm your attendance.' : $r['error']);
    }
    header('Location: class.php?id=' . $id);
    exit;
}
$c = cal_class($db, $id);
$ended = cal_is_past($c);
$open = cal_join_open($db, $v);
$att = $db->prepare('SELECT status, self_checkin_at FROM class_attendance WHERE class_id = ? AND student_id = ?');
$att->execute([$id, $me]);
$att = $att->fetch(PDO::FETCH_ASSOC) ?: ['status' => null, 'self_checkin_at' => null];
$materials = $ended ? cal_materials($db, $id) : [];
$mins = (int) cal_settings($db)['join_open_minutes'];
$page_title = $v['title'];
ob_start();
require __DIR__ . '/../../components/classes/styles.php';
?>
<a href="index.php?m=<?php echo cal_fmt($v['start_utc'], $tz, 'Y-m'); ?>" class="btn btn-link btn-sm ps-0">← My Classes</a>
<div class="cl-card" style="max-width:760px">
    <div class="d-flex flex-wrap gap-2 align-items-start mb-2">
        <div class="flex-grow-1"><h1 class="h4 mb-1"><?php echo cal_e($v['title']); ?></h1>
            <div class="fw-semibold"><?php echo cal_e(cal_fmt_range($v['start_utc'], $v['end_utc'], $tz)); ?></div></div>
        <span class="cl-pill <?php echo cal_e($v['mode']); ?>"><?php echo CAL_MODES[$v['mode']]; ?></span>
    </div>
    <?php if ($v['status'] === 'cancelled'): ?><div class="cl-cancel mb-3"><strong>Cancelled</strong><?php echo $v['cancel_reason'] ? ' — ' . cal_e($v['cancel_reason']) : ''; ?></div><?php endif; ?>
    <?php if ($v['status'] === 'moved' && $v['moved_from_utc']): ?><div class="cl-moved mb-3"><strong>Moved</strong> from <?php echo cal_e(cal_fmt($v['moved_from_utc'], $tz, 'D j M, H:i')); ?> to <?php echo cal_e(cal_fmt($v['start_utc'], $tz, 'D j M, H:i')); ?><?php echo $v['moved_reason'] ? ' — ' . cal_e($v['moved_reason']) : ''; ?></div><?php endif; ?>
    <dl class="row small mb-3">
        <dt class="col-4">Tutor</dt><dd class="col-8"><?php echo cal_e($v['tutor']); ?></dd>
        <?php if ($v['course']): ?><dt class="col-4">Course</dt><dd class="col-8"><?php echo cal_e($v['course']); ?></dd><?php endif; ?>
        <?php if ($v['topic']): ?><dt class="col-4">Topic</dt><dd class="col-8"><?php echo cal_e($v['topic']); ?><?php echo $v['subtopics'] ? ' · ' . cal_e($v['subtopics']) : ''; ?></dd><?php endif; ?>
        <?php if ($v['campus']): ?><dt class="col-4">Campus</dt><dd class="col-8"><?php echo cal_e($v['campus']); ?></dd><?php endif; ?>
        <?php if ($v['room']): ?><dt class="col-4">Room</dt><dd class="col-8"><?php echo cal_e($v['room']); ?></dd><?php endif; ?>
        <?php if ($v['meeting_link']): ?><dt class="col-4">Meeting link</dt><dd class="col-8 text-break"><?php echo cal_e($v['meeting_link']); ?></dd><?php endif; ?>
    </dl>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($v['meeting_link'] && $v['status'] !== 'cancelled' && !$ended): ?>
            <?php if ($open): ?><a class="btn btn-success" href="<?php echo cal_e($v['meeting_link']); ?>" target="_blank" rel="noopener"><i class="fas fa-video me-1"></i>Join class</a>
            <?php else: ?><button class="btn btn-success" disabled><i class="fas fa-video me-1"></i>Join class</button><span class="cl-muted align-self-center">Opens <?php echo $mins; ?> minutes before the class</span><?php endif; ?>
        <?php endif; ?>
        <?php if ($v['status'] !== 'cancelled'): ?><a class="btn btn-outline-secondary" href="ics.php?id=<?php echo $id; ?>"><i class="fas fa-calendar-plus me-1"></i>Add to my phone calendar</a><?php endif; ?>
        <?php if ($open && cal_settings($db)['self_checkin'] === '1' && !$att['self_checkin_at'] && !$att['status']): ?>
            <form method="POST" class="m-0"><?php echo csrf_field(); ?><input type="hidden" name="act" value="checkin"><button class="btn btn-outline-success"><i class="fas fa-hand me-1"></i>I'm here</button></form>
        <?php elseif ($att['self_checkin_at'] && !$att['status']): ?><span class="cl-pill online align-self-center">Checked in — waiting for your tutor</span><?php endif; ?>
    </div>
    <?php if ($ended && $v['status'] !== 'cancelled'): ?>
        <hr>
        <div class="mb-2"><strong>Your attendance:</strong> <?php echo $att['status'] ? '<span class="cl-pill ' . cal_e($att['status']) . '">' . CAL_ATT[$att['status']] . '</span>' : '<span class="cl-muted">not marked yet</span>'; ?></div>
        <?php if ($v['notes']): ?><div class="mb-2"><strong>Notes from your tutor</strong><div class="small" style="white-space:pre-line"><?php echo cal_e($v['notes']); ?></div></div><?php endif; ?>
        <?php if ($materials): ?><strong>Materials</strong>
            <?php foreach ($materials as $m): ?><div class="small"><i class="fas <?php echo $m['file_path'] ? 'fa-paperclip' : 'fa-link'; ?> text-success me-1"></i>
                <a target="_blank" rel="noopener" href="<?php echo cal_e($m['file_path'] ? url('classes/file.php?id=' . (int) $m['id']) : $m['link']); ?>"><?php echo cal_e($m['title']); ?></a></div><?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php if (!$open && $v['meeting_link'] && !$ended && $v['status'] !== 'cancelled'): ?>
<script>// Reload when the join window opens, so the button becomes active.
(function () { var ms = <?php echo (strtotime($v['start_utc'] . ' UTC') - 60 * $mins - time()) * 1000; ?>; if (ms > 0 && ms < 6 * 3600 * 1000) setTimeout(function () { location.reload(); }, ms + 1500); })();</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
