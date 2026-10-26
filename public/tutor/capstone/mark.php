<?php
// Tutor: one student's capstone. See the work, mark out of 100 in four parts, hold the mark, or return it (at most 2 times).
require __DIR__ . '/../../components/capstone/tutor_boot.php';

use Models\AssignmentModel;

$attemptId = (int) ($_GET['id'] ?? 0);
$att = cap_attempt($pdo, $attemptId);
$cap = $att ? cap_get_for_tutor($pdo, (int) $att['capstone_id'], $tutorId) : null;
if (!$cap) asg_back('index.php', null, 'Capstone not found.');
$queue = (int) ($_GET['queue'] ?? 0);
$self = 'mark.php?id=' . $attemptId . ($queue ? '&queue=' . $queue : '');
$studentUid = (int) $att['student_user_id'];
$owner = cap_owner($pdo, $att);
$group = cap_group_attempts($pdo, $owner);
$latest = cap_latest_attempt($pdo, (int) $cap['id'], $studentUid);
$isLatest = (int) $latest['id'] === $attemptId;

/** The next student waiting to be marked (for the marking queue). */
$nextToMark = function () use ($pdo, $cap, $attemptId): ?int {
    $st = $pdo->prepare("SELECT id FROM capstone_attempts WHERE capstone_id = ? AND status = 'submitted' AND shared_attempt_id IS NULL AND id <> ? ORDER BY submitted_at, id LIMIT 1");
    $st->execute([(int) $cap['id'], $attemptId]);
    return ($v = $st->fetchColumn()) ? (int) $v : null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    asg_csrf_or_back($self);
    $action = $_POST['action'] ?? '';
    if ($action === 'save_mark') {
        $held = null;
        if (!empty($_POST['hold']) && !empty($_POST['held_until'])) {
            $held = AssignmentModel::toUtc((string) $_POST['held_until']);
            if (!$held || strtotime($held . ' UTC') <= time()) asg_back($self, null, 'The release date for a held mark must be in the future.');
        }
        $ev = cap_time_evidence($pdo, $cap, $owner);
        $r = cap_save_mark($pdo, $attemptId, $tutorId, $_POST + ['held_until' => $held, 'time_evidence' => $ev['rows']], $me);
        AssignmentNotifier::flush();
        if (!$r['ok']) asg_back($self, null, $r['error']);
        $msg = 'Mark saved: ' . cap_pretty_marks($r['total']) . ' out of 100 (' . $r['result'] . ').' . ($held ? ' It is held until ' . AssignmentModel::local($held, 'D j M, H:i') . ' EAT.' : ' The student was told.');
        if ($queue && ($n = $nextToMark())) asg_back('mark.php?id=' . $n . '&queue=' . $queue, $msg . ' Next student in the queue.');
        asg_back($self, $msg);
    }
    if ($action === 'return') {
        $by = trim((string) ($_POST['send_by'] ?? '')) !== '' ? AssignmentModel::toUtc((string) $_POST['send_by']) : null;
        $r = cap_return($pdo, $attemptId, $tutorId, (string) ($_POST['instructions'] ?? ''), $by, $me);
        AssignmentNotifier::flush();
        $r['ok'] ? asg_back('track.php?id=' . (int) $cap['id'], 'Returned (' . $r['return_no'] . ' of 2). The student was told.' . ($r['return_no'] >= 2 ? ' This was the last return: the next send is final.' : '')) : asg_back($self, null, $r['error']);
    }
    if ($action === 'recheck') {
        $item = cap_item($pdo, (int) ($_POST['item'] ?? 0));
        $own = $item ? $pdo->prepare('SELECT s.attempt_id FROM capstone_submissions s WHERE s.id = ?') : null;
        if ($item && $own) { $own->execute([(int) $item['submission_id']]); if ((int) $own->fetchColumn() === (int) $owner['id'] && $item['url']) {
            $r = (new LinkChecker($pdo))->check($item['url'], null, $me, null, $tutor['github_username'] ?? null);
            $pdo->prepare('UPDATE capstone_submission_items SET link_status = ?, link_message = ? WHERE id = ?')->execute([$r['status'], mb_substr($r['title'] . ': ' . $r['message'], 0, 300), (int) $item['id']]);
            asg_back($self, 'Checked again: ' . $r['title']);
        } }
        asg_back($self);
    }
    if ($action === 'presentation_done') {
        $ok = cap_presentation_done($pdo, $cap, $owner, (string) ($_POST['notes'] ?? ''), (int) ($_POST['late_minutes'] ?? 0), $me);
        asg_back($self, $ok ? 'Presentation marked as done.' : null, $ok ? null : 'This student has not booked a slot.');
    }
    if ($action === 'start_retake') {
        $r = cap_start_retake($pdo, (int) $cap['id'], $studentUid, $tutorId, $me);
        AssignmentNotifier::flush();
        $r['ok'] ? asg_back('track.php?id=' . (int) $cap['id'], 'Retake started. The student was told to pay the retake fee first; it opens after the office confirms the payment.') : asg_back($self, null, $r['error']);
    }
    if ($action === 'open_student') {
        $r = cap_open_for_student($pdo, (int) $cap['id'], $studentUid, $tutorId, $me);
        asg_back($self, $r['ok'] ? 'Opened for this student.' : null, $r['ok'] ? null : $r['error']);
    }
    asg_back($self);
}

$mark = cap_mark($pdo, $attemptId);
$work = cap_work($pdo, $owner, false);
$workItems = $work ? cap_items($pdo, (int) $work['id']) : [];
$isDraft = $work && $work['status'] === 'draft';
$returns = cap_returns($pdo, (int) $owner['id']);
$versions = cap_versions($pdo, (int) $owner['id']);
$evidence = cap_time_evidence($pdo, $cap, $owner);
$booking = cap_booking($pdo, $owner);
$history = cap_history($pdo, (int) $cap['id'], $studentUid);
$names = [];
foreach ($group as $g) $names[(int) $g['student_user_id']] = $am->student((int) $g['student_user_id'])['full_name'];
$state = cap_state($cap + ['own_due_at' => null], $att);
$canMark = in_array($owner['status'], ['submitted', 'marked'], true) && $isLatest;
$canReturn = $isLatest && cap_can_return($owner);
$retake = ($isLatest && $att['status'] === 'marked') ? cap_can_retake($pdo, $cap, $att) : null;
$ownTicks = [];
if ($work) {
    $st = $pdo->prepare('SELECT * FROM capstone_own_work WHERE attempt_id = ? AND version = ?');
    $st->execute([(int) $owner['id'], (int) $work['version']]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) $ownTicks[(int) $t['student_user_id']] = $t;
}
$sameWarn = array_filter($workItems, fn($i) => !empty($i['same_as_example']));
$pv = fn(string $k, $d = '') => $mark[$k] ?? $d;
$page_title = 'Capstone: ' . ($names[$studentUid] ?? 'student');
ob_start();
?>
<a href="track.php?id=<?php echo (int) $cap['id']; ?>" class="btn btn-link btn-sm ps-0 mb-2">← Track: <?php echo asg_e($cap['title']); ?></a>
<div class="card-tutor p-3 p-md-4 mb-3">
    <div class="d-flex flex-wrap justify-content-between gap-2">
        <div>
            <h4 class="mb-1"><?php echo asg_e(implode(' & ', $names)); ?> <?php echo cap_chip($state); ?></h4>
            <div class="small text-muted"><?php echo asg_e($cap['course_title']); ?> · <?php echo asg_e($cap['title']); ?>
                · <?php echo (int) $att['attempt_no'] > 1 ? 'Retake ' . ((int) $att['attempt_no'] - 1) . ' of ' . CAP_MAX_RETAKES : 'First try'; ?>
                · Returns used <strong><?php echo (int) $owner['returns_used']; ?> of <?php echo CAP_MAX_RETURNS; ?></strong>
                <?php if ((int) $owner['returns_used'] >= CAP_MAX_RETURNS): ?><span class="asg-chip st-extension ms-1"><i class="fas fa-flag-checkered"></i>Final try: the mark is final</span><?php endif; ?></div>
            <?php if (count($group) > 1): ?><div class="small mt-1">Team: the same mark is saved for every member.</div><?php endif; ?>
        </div>
        <div class="text-end small text-muted"><?php echo $owner['submitted_at'] ? 'Sent ' . asg_e(cap_local($owner['submitted_at'])) . ' EAT' : 'Not sent yet'; ?></div>
    </div>
</div>

<?php if (!$isLatest): ?><div class="alert alert-info small">This is an older try. Only the newest try counts. <a href="mark.php?id=<?php echo (int) $latest['id']; ?>">Open the newest</a>.</div><?php endif; ?>
<?php if ($sameWarn): ?><div class="alert alert-warning"><i class="fas fa-triangle-exclamation me-1"></i><strong>Check this one.</strong> The student sent a link or file that is the <strong>same as your example project</strong>: <?php echo asg_e(implode(', ', array_map(fn($i) => $i['label'], $sameWarn))); ?>.</div><?php endif; ?>

<div class="row g-3">
<div class="col-lg-7">
    <!-- The work -->
    <div class="card-tutor p-3 p-md-4 mb-3">
        <h6 class="fw-bold">The work <?php if ($work): ?><span class="small text-muted fw-normal">version <?php echo (int) $work['version']; ?><?php echo $isDraft ? ' (draft, not sent)' : ''; ?></span><?php endif; ?></h6>
        <?php if (!$work || !$workItems): ?><div class="text-muted small">Nothing has been added yet.</div><?php endif; ?>
        <?php foreach ($workItems as $it): ?>
            <div class="d-flex flex-wrap justify-content-between gap-2 border-bottom py-2">
                <div><div class="fw-semibold small"><?php echo asg_e($it['label']); ?></div>
                    <?php if ($it['url']): ?><a href="<?php echo asg_e($it['url']); ?>" target="_blank" rel="noopener noreferrer" class="small text-break"><?php echo asg_e($it['url']); ?></a>
                    <?php elseif ($it['file_path']): ?><span class="small"><i class="fas fa-file-pdf me-1"></i><?php echo asg_e($it['file_name']); ?></span><?php endif; ?>
                    <?php if ($it['link_message'] && cap_link_chip($it['link_status']) && LinkRules::isBad($it['link_status'])): ?><div class="small text-muted"><?php echo asg_e($it['link_message']); ?></div><?php endif; ?></div>
                <div class="d-flex gap-2 align-items-start">
                    <?php if ($it['url']): ?><?php echo cap_link_chip($it['link_status']); ?>
                        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="recheck"><input type="hidden" name="item" value="<?php echo (int) $it['id']; ?>"><button class="btn btn-outline-dark btn-sm">Check again</button></form>
                    <?php elseif ($it['file_path']): ?><a class="btn btn-outline-dark btn-sm" target="_blank" href="<?php echo asg_e(cap_file_url('item', (int) $it['id'])); ?>">Open</a><?php endif; ?></div>
            </div>
        <?php endforeach; ?>
        <div class="small mt-3"><strong>Own work:</strong>
            <?php foreach ($group as $g): $uid = (int) $g['student_user_id']; ?>
                <div><?php echo isset($ownTicks[$uid]) ? '<i class="fas fa-circle-check text-success"></i>' : '<i class="fas fa-circle-xmark text-danger"></i>'; ?> <?php echo asg_e($names[$uid]); ?> ticked "This is my own work"<?php echo isset($ownTicks[$uid]) ? '' : ' (not yet)'; ?>
                    <?php if (!empty($ownTicks[$uid]['ai_note'])): ?><div class="text-muted ms-4">AI use: <?php echo asg_e($ownTicks[$uid]['ai_note']); ?></div><?php endif; ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($canMark): ?>
    <!-- Time keeping evidence -->
    <div class="card-tutor p-3 mb-3">
        <h6 class="fw-bold">Time keeping evidence</h6>
        <table class="table table-sm cap-evidence mb-1"><thead><tr><th>Milestone</th><th>Due</th><th>Sent</th><th>Result</th><th class="text-end">Marks</th></tr></thead><tbody>
        <?php foreach ($evidence['rows'] as $r): ?>
            <tr><td><?php echo asg_e($r['title']); ?></td><td><?php echo asg_e(cap_local($r['due_at'], 'j M, H:i')); ?></td><td><?php echo $r['sent_at'] ? asg_e(cap_local($r['sent_at'], 'j M, H:i')) : '–'; ?></td>
                <td><?php echo cap_ms_chip(['on_time' => 'On time', 'late' => 'Late', 'missing' => 'Missing', 'not_yet' => 'Not yet'][$r['status']]); ?><?php echo $r['status'] === 'late' ? '<div class="text-muted">' . asg_e(cap_late_text($r['minutes_late'])) . '</div>' : ''; ?></td>
                <td class="text-end"><?php echo cap_pretty_marks($r['earned']); ?> / <?php echo cap_pretty_marks($r['max']); ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <div class="small">Suggested Time keeping: <strong><?php echo cap_pretty_marks($evidence['suggested']); ?> / <?php echo cap_pretty_marks($evidence['max']); ?></strong>
            <button type="button" class="btn btn-outline-dark btn-sm ms-1" id="useSuggested">Use this</button></div>
        <div class="small text-muted mt-1">A late milestone loses its marks. This is only a suggestion: you can change the number. The evidence is saved next to the mark.</div>
    </div>

    <?php endif; ?>

    <!-- Presentation -->
    <div class="card-tutor p-3 p-md-4 mb-3">
        <h6 class="fw-bold">Presentation</h6>
        <?php if (!$booking): ?><div class="small text-muted">No slot booked yet. <a href="slots.php?id=<?php echo (int) $cap['id']; ?>">Manage slots</a>.</div>
        <?php else: ?>
            <div class="mb-2"><i class="fas fa-calendar-day me-1"></i><strong><?php echo asg_e(cap_local($booking['starts_at'], 'D j M Y, H:i')); ?> EAT</strong> · <?php echo (int) $booking['minutes']; ?> min · <?php echo $booking['mode'] === 'online' ? 'Online' : 'In person'; ?><?php echo $booking['place'] ? ' · ' . asg_e($booking['place']) : ''; ?>
                <?php echo $booking['done_at'] ? '<span class="asg-chip st-graded ms-2"><i class="fas fa-check"></i>Done</span>' : ''; ?></div>
            <form method="POST" class="row g-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="presentation_done">
                <div class="col-md-8"><label class="form-label small fw-semibold">Notes</label><textarea name="notes" class="form-control form-control-sm" rows="2"><?php echo asg_e($booking['notes'] ?? ''); ?></textarea></div>
                <div class="col-md-4"><label class="form-label small fw-semibold">Started late (minutes)</label><input type="number" name="late_minutes" min="0" max="600" class="form-control form-control-sm" value="<?php echo (int) ($booking['late_minutes'] ?? 0); ?>">
                    <div class="form-text">0 = on time. A late start counts as time lost.</div></div>
                <div class="col-12"><button class="btn btn-outline-dark btn-sm"><?php echo $booking['done_at'] ? 'Update' : 'Mark presentation done'; ?></button></div>
            </form>
        <?php endif; ?>
    </div>

    <!-- History -->
    <div class="card-tutor p-3 p-md-4 mb-3">
        <h6 class="fw-bold">History</h6>
        <ul class="cap-hist small mb-0 ps-3">
            <?php foreach ($history as $h): ?>
                <li><strong><?php echo (int) $h['attempt_no'] > 1 ? 'Retake ' . ((int) $h['attempt_no'] - 1) : 'First try'; ?></strong>: <?php echo cap_chip(cap_state($cap + ['own_due_at' => null], $h)); ?>
                    · returns <?php echo (int) $h['returns_used']; ?> of 2<?php echo $h['mark'] ? ' · ' . cap_pretty_marks($h['mark']['total']) . ' / 100 (' . asg_e($h['mark']['result']) . ')' : ''; ?>
                    <?php if ((int) $h['id'] !== $attemptId): ?><a href="mark.php?id=<?php echo (int) $h['id']; ?>">open</a><?php endif; ?></li>
            <?php endforeach; ?>
            <?php foreach ($returns as $r): ?>
                <li>Return <?php echo (int) $r['return_no']; ?> of 2 · <?php echo asg_e(cap_local($r['returned_at'])); ?>: <em><?php echo asg_e($r['instructions']); ?></em><?php echo $r['send_by'] ? ' (send by ' . asg_e(cap_local($r['send_by'], 'D j M, H:i')) . ')' : ''; ?></li>
            <?php endforeach; ?>
            <?php foreach ($versions as $v): ?>
                <li>Version <?php echo (int) $v['version']; ?> sent <?php echo asg_e(cap_local($v['submitted_at'])); ?> (<?php echo count($v['items']); ?> items)</li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if ($retake !== null): ?>
    <div class="card-tutor p-3 p-md-4 mb-3">
        <h6 class="fw-bold">Retake</h6>
        <?php if ($retake['ok']): ?>
            <p class="small">This capstone was not passed. You can start a retake: a fresh try with the same brief and 0 of 2 returns. The student must <strong>pay the retake fee again</strong> first (KES <?php echo number_format((float) cap_retake_fee($pdo, (int) $cap['course_id'])); ?>), and the retake opens when the office confirms the payment.
               Retakes used: <?php echo (int) $att['attempt_no'] - 1; ?> of <?php echo CAP_MAX_RETAKES; ?>.</p>
            <form method="POST" onsubmit="return confirm('Start a retake? The student will be asked to pay the retake fee.')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="start_retake"><button class="btn btn-brand btn-sm">Start a retake</button></form>
        <?php else: ?><div class="small text-muted"><?php echo asg_e($retake['why']); ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div class="col-lg-5">
    <?php if ($canMark): ?>
    <form method="POST" id="markForm"><?php echo csrf_field(); ?>
    <!-- Marks -->
    <div class="card-tutor p-3 mb-3">
        <h6 class="fw-bold">Marks out of 100</h6>
        <?php foreach (CAP_PARTS as $k => $label): ?>
            <div class="cap-part"><div><label class="form-label small fw-semibold mb-1" for="p_<?php echo $k; ?>"><?php echo $label; ?> <span class="text-muted fw-normal">(out of <?php echo (int) $cap['w_' . $k]; ?>)</span></label>
                <textarea name="c_<?php echo $k; ?>" class="form-control form-control-sm" rows="2" placeholder="Comment on <?php echo mb_strtolower($label); ?>"><?php echo asg_e($pv('c_' . $k)); ?></textarea></div>
                <input type="number" step="0.5" min="0" max="<?php echo (int) $cap['w_' . $k]; ?>" class="form-control part-in" id="p_<?php echo $k; ?>" name="p_<?php echo $k; ?>" value="<?php echo $mark ? asg_e(cap_pretty_marks($mark['p_' . $k])) : ''; ?>" required></div>
        <?php endforeach; ?>
        <div class="d-flex justify-content-between align-items-end border-top pt-2"><div><div class="small text-muted">Capstone mark</div><div class="cap-total"><span id="sum">0</span><span class="fs-6 text-muted"> / 100</span></div></div>
            <div class="text-end"><span class="asg-chip st-graded" id="band">–</span></div></div>
        <div class="cap-bands mt-2"><span>Distinction 70+</span><span>Credit 60–69</span><span>Pass 50–59</span><span class="f">Fail below 50</span></div>
        <hr>
        <label class="form-label small fw-semibold">Feedback to the student</label>
        <textarea name="feedback" id="feedback" class="form-control form-control-sm" rows="4"><?php echo asg_e($pv('feedback')); ?></textarea>
        <div class="d-flex flex-wrap gap-1 mt-1">
            <?php foreach (['Well done, this is clear and complete.', 'Test your project on a phone before you send it.', 'Add more detail to your report.', 'Explain your choices when you present.'] as $q): ?>
                <button type="button" class="btn btn-outline-secondary btn-sm quick" data-t="<?php echo asg_e($q); ?>"><?php echo asg_e(mb_strimwidth($q, 0, 28, '…')); ?></button>
            <?php endforeach; ?></div>
        <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="hold" value="1" id="hold" <?php echo ($mark && $mark['held_until'] && !cap_mark_released($mark)) ? 'checked' : ''; ?>>
            <label class="form-check-label small" for="hold"><strong>Hold this mark</strong> until a release date. The student sees "Held" until then.</label></div>
        <input type="datetime-local" name="held_until" class="form-control form-control-sm mt-1" value="<?php echo $mark && $mark['held_until'] && !cap_mark_released($mark) ? asg_e(AssignmentModel::localInput($mark['held_until'])) : ''; ?>">
        <?php if ((int) $owner['returns_used'] >= CAP_MAX_RETURNS): ?><div class="alert alert-warning small mt-3 mb-0">This capstone was already returned 2 times. <strong>This mark is final.</strong> Below 50 is Fail.</div><?php endif; ?>
    </div>
    <div class="cap-sticky d-grid gap-2">
        <button class="btn btn-brand" name="action" value="save_mark"><i class="fas fa-circle-check me-1"></i><?php echo $mark ? 'Update mark' : 'Save mark'; ?></button>
        <?php if ($canReturn): ?><button type="button" class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#returnModal">Return to student (<?php echo (int) $owner['returns_used'] + 1; ?> of <?php echo CAP_MAX_RETURNS; ?>)</button><?php endif; ?>
    </div>
    </form>
    <?php else: ?>
    <div class="card-tutor p-3 mb-3">
        <h6 class="fw-bold">Marks</h6>
        <?php if ($mark): ?>
            <div class="cap-total mb-1"><?php echo asg_e(cap_pretty_marks($mark['total'])); ?><span class="fs-6 text-muted"> / 100</span> <span class="asg-chip st-graded"><?php echo asg_e($mark['result']); ?></span></div>
            <?php foreach (CAP_PARTS as $k => $label): ?><div class="small"><?php echo $label; ?>: <strong><?php echo cap_pretty_marks($mark['p_' . $k]); ?></strong> / <?php echo (int) $cap['w_' . $k]; ?><?php echo $mark['c_' . $k] ? ' · ' . asg_e($mark['c_' . $k]) : ''; ?></div><?php endforeach; ?>
            <?php if (!cap_mark_released($mark)): ?><div class="mt-2"><span class="asg-chip st-locked"><i class="fas fa-lock"></i>Held until <?php echo asg_e(cap_local($mark['held_until'], 'D j M, H:i')); ?> EAT</span></div><?php endif; ?>
        <?php else: ?><div class="small text-muted">
            <?php echo $state === 'returned' || $state === 'final_try' ? 'Returned to the student. You can mark it after they send it again.' : ($state === 'awaiting_payment' ? 'Waiting for the retake fee to be paid.' : 'Nothing to mark yet.'); ?></div><?php endif; ?>
        <?php if ($cap['status'] === 'scheduled' && !cap_students($pdo, (int) $cap['id'])[$studentUid]['opened_early_at']): ?>
            <form method="POST" class="mt-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="open_student"><button class="btn btn-outline-dark btn-sm">Open the capstone for this student now</button></form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</div>

<?php if ($canReturn): ?>
<div class="modal fade" id="returnModal" tabindex="-1"><div class="modal-dialog"><form method="POST" class="modal-content"><?php echo csrf_field(); ?><input type="hidden" name="action" value="return">
    <div class="modal-header"><h5 class="modal-title">Return to the student (<?php echo (int) $owner['returns_used'] + 1; ?> of <?php echo CAP_MAX_RETURNS; ?>)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="alert alert-warning small">The student can only resubmit after a return, and you can return only <?php echo CAP_MAX_RETURNS; ?> times. <?php echo (int) $owner['returns_used'] + 1 >= CAP_MAX_RETURNS ? '<strong>After this return, the next send is final.</strong>' : ''; ?></div>
        <label class="form-label small fw-semibold">Instructions (required)</label>
        <textarea name="instructions" class="form-control" rows="5" required placeholder="What must the student improve? Be specific."></textarea>
        <label class="form-label small fw-semibold mt-3">Send it back by <span class="text-muted fw-normal">(optional, EAT)</span></label>
        <input type="datetime-local" name="send_by" class="form-control">
        <div class="form-text">A return does not change due dates. Time keeping still uses the original milestone dates.</div>
    </div>
    <div class="modal-footer"><button class="btn btn-brand">Return to student</button></div>
</form></div></div>
<?php endif; ?>
<script>
(function () {
    var ins = document.querySelectorAll('.part-in'), sum = document.getElementById('sum'), band = document.getElementById('band');
    if (!sum) return;
    function calc() {
        var t = 0; ins.forEach(function (i) { t += parseFloat(i.value || 0); }); t = Math.round(t * 10) / 10; sum.textContent = t;
        band.textContent = t >= 70 ? 'Distinction' : t >= 60 ? 'Credit' : t >= 50 ? 'Pass' : 'Fail';
        band.className = 'asg-chip ' + (t >= 50 ? 'st-graded' : 'st-closed');
    }
    ins.forEach(function (i) { i.addEventListener('input', calc); }); calc();
    var u = document.getElementById('useSuggested');
    if (u) u.onclick = function () { document.getElementById('p_time').value = <?php echo json_encode(min((float) $cap['w_time'], $evidence['suggested'])); ?>; calc(); };
    document.querySelectorAll('.quick').forEach(function (b) { b.onclick = function () { var f = document.getElementById('feedback'); f.value = (f.value ? f.value + ' ' : '') + b.dataset.t; }; });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
