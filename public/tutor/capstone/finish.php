<?php
// Tutor: mark a student's course as finished (can be earlier than planned), see the certificate checklist, and set whether the
// admin may preview the transcript. Only the tutor of the student can do this.
require __DIR__ . '/../../components/capstone/tutor_boot.php';

require_once __DIR__ . '/../../../app/v1/services/CertificateApplication.php';
$uid = (int) ($_GET['student'] ?? 0);
$courseId = (int) ($_GET['course'] ?? 0);
$info = cap_finish_info($pdo, $tutorId, $uid, $courseId);
if (!$info) asg_back('../students/index.php', null, 'That is not one of your students in this course.');
$student = $am->student($uid);
$self = 'finish.php?student=' . $uid . '&course=' . $courseId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    asg_csrf_or_back($self);
    $action = $_POST['action'] ?? '';
    if ($action === 'finish') {
        $r = cap_finish_course($pdo, $tutorId, $uid, $courseId, (string) ($_POST['finished_on'] ?? ''), (string) ($_POST['note'] ?? ''), $me);
        AssignmentNotifier::flush();
        asg_back($self, $r['ok'] ? 'Marked as finished. The student was told.' : null, $r['ok'] ? null : $r['error']);
    }
    if ($action === 'extend_end') {
        $r = certapp_extend_end($pdo, $tutorId, $me, $uid, $courseId, (string) ($_POST['new_end'] ?? ''), (string) ($_POST['reason'] ?? ''));
        asg_back($self, $r['ok'] ? 'The end date was moved. The student sees the new date.' : null, $r['ok'] ? null : $r['error']);
    }
    if ($action === 'undo') {
        $r = cap_unfinish_course($pdo, $tutorId, $uid, $courseId, $me);
        asg_back($self, $r['ok'] ? 'Undone. The course is not marked as finished any more.' : null, $r['ok'] ? null : $r['error']);
    }
    if ($action === 'transcript') {
        cap_set_transcript_pref($pdo, $uid, $courseId, !empty($_POST['allow']), $me);
        asg_back($self, 'Saved.');
    }
    if ($action === 'letter_note') {
        $r = cap_letter_note_save($pdo, $tutorId, $uid, $courseId, (string) ($_POST['letter_note'] ?? ''), $me);
        asg_back($self, $r['ok'] ? 'Your sentence was saved. It shows only on the letter.' : null, $r['ok'] ? null : $r['error']);
    }
    if ($action === 'open_early') {
        $r = cap_open_for_student($pdo, (int) ($_POST['capstone'] ?? 0), $uid, $tutorId, $me);
        asg_back($self, $r['ok'] ? 'The capstone is open for this student.' : null, $r['ok'] ? null : $r['error']);
    }
    asg_back($self);
}

cap_release_sync($pdo, $uid, $courseId, $me);   // keep the transcript and the letter in step with the four conditions
$check = cap_certificate_readiness($pdo, $uid, $courseId, (int) $info['reg_id']);
$release = cap_release_row($pdo, $uid, $courseId);
$certIssued = cap_certificate_issued($pdo, $uid, $courseId);
$caps = $pdo->prepare("SELECT c.* FROM capstone_students cs JOIN capstones c ON c.id = cs.capstone_id WHERE cs.student_user_id = ? AND c.course_id = ? AND c.tutor_id = ? AND cs.removed_at IS NULL AND c.status <> 'draft' ORDER BY c.id DESC");
$caps->execute([$uid, $courseId, $tutorId]);
$caps = $caps->fetchAll(PDO::FETCH_ASSOC);
$fin = $info['finish'];
$courseTime = cert_course_time($pdo, $uid, $courseId);
$tick = fn(bool $ok) => $ok ? '<i class="fas fa-circle-check text-success"></i>' : '<i class="fas fa-circle-xmark text-danger"></i>';
$page_title = 'Course finished';
ob_start();
?>
<a href="../students/view.php?id=<?php echo (int) $info['reg_id']; ?>" class="btn btn-link btn-sm ps-0 mb-2">← <?php echo asg_e($student['full_name']); ?></a>
<h4 class="mb-1">Mark course finished</h4>
<div class="text-muted small mb-3"><?php echo asg_e($student['full_name']); ?> · <?php echo asg_e($info['title']); ?></div>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card-tutor p-3 p-md-4">
            <dl class="row small mb-3">
                <dt class="col-5">Planned time</dt><dd class="col-7"><?php echo (int) $info['duration_months']; ?> months</dd>
                <dt class="col-5">Started</dt><dd class="col-7"><?php echo asg_e(date('j M Y', strtotime($info['start']))); ?></dd>
                <dt class="col-5">Time so far</dt><dd class="col-7"><?php echo asg_e(cap_pretty_marks($info['months_so_far'])); ?> months</dd>
            </dl>
            <?php if ($fin): ?>
                <div class="alert alert-success small">Marked as finished on <strong><?php echo asg_e(date('j M Y', strtotime($fin['finished_on']))); ?></strong> after <strong><?php echo asg_e(cap_pretty_marks($fin['finished_after_months'])); ?> months</strong> (planned <?php echo (int) $fin['planned_months']; ?>).<?php echo $fin['note'] ? '<br>' . asg_e($fin['note']) : ''; ?></div>
                <?php if ($info['has_certificate']): ?><div class="small text-muted">A certificate was already issued, so this cannot be undone.</div>
                <?php else: ?><form method="POST" onsubmit="return confirm('Undo? The course will not be marked as finished.')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="undo"><button class="btn btn-outline-danger btn-sm">Undo</button></form><?php endif; ?>
            <?php else: ?>
            <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="finish">
                <label class="form-label small fw-semibold">Finish date</label><input type="date" name="finished_on" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" min="<?php echo asg_e($info['start']); ?>" required>
                <div class="form-text">The real time taken is saved and shown on the transcript later (for example 12 months instead of 18).</div>
                <label class="form-label small fw-semibold mt-2">Note <span class="text-muted fw-normal">(optional)</span></label><input name="note" maxlength="500" class="form-control form-control-sm" placeholder="Finished early: very fast learner">
                <button class="btn btn-brand btn-sm mt-3" onclick="return confirm('Mark this course as finished? The student will be told.')">Mark course finished</button>
            </form>
            <?php endif; ?>
        </div>
        <?php if ($courseTime): ?>
        <div class="card-tutor p-3 p-md-4 mt-3" id="endDate">
            <h6 class="fw-bold">Course end date</h6>
            <p class="small mb-2">The course is <?php echo (int) $courseTime['months']; ?> months, counted from the day the student was added (<?php echo asg_e(date('j M Y', strtotime($courseTime['start']))); ?>).
                It ends on <strong><?php echo asg_e(date('j M Y', strtotime($courseTime['end']))); ?></strong><?php echo $courseTime['extended'] ? ' (moved from ' . asg_e(date('j M Y', strtotime($courseTime['natural_end']))) . ': ' . asg_e((string) $courseTime['extended_reason']) . ')' : ''; ?>.
                <?php echo $courseTime['finished'] ? '<span class="text-success fw-semibold">The course time is finished.</span>' : 'The student can apply for the certificate after this date, or sooner if you mark the course finished.'; ?></p>
            <?php if (!$info['has_certificate']): ?>
            <form method="POST" class="row g-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="extend_end">
                <div class="col-sm-5"><label class="form-label small fw-semibold mb-0">New end date</label><input type="date" name="new_end" class="form-control form-control-sm" min="<?php echo asg_e(date('Y-m-d', strtotime($courseTime['end'] . ' +1 day'))); ?>" required></div>
                <div class="col-sm-7"><label class="form-label small fw-semibold mb-0">Reason</label><input name="reason" maxlength="300" class="form-control form-control-sm" placeholder="For example: a two-month break for illness" required></div>
                <div class="col-12"><button class="btn btn-outline-dark btn-sm">Extend the end date</button></div>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php foreach ($caps as $c): ?>
            <div class="card-tutor p-3 mt-3"><div class="fw-semibold small"><?php echo asg_e($c['title']); ?> <?php echo cap_chip($c['status']); ?></div>
                <?php if ($c['status'] === 'scheduled'): ?>
                    <form method="POST" class="mt-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="open_early"><input type="hidden" name="capstone" value="<?php echo (int) $c['id']; ?>">
                        <button class="btn btn-outline-dark btn-sm">Open this capstone for <?php echo asg_e(explode(' ', $student['full_name'])[0]); ?> now</button></form>
                <?php else: ?><a class="btn btn-outline-dark btn-sm mt-2" href="track.php?id=<?php echo (int) $c['id']; ?>">Track</a><?php endif; ?></div>
        <?php endforeach; ?>
    </div>
    <div class="col-lg-6">
        <div class="card-tutor p-3 p-md-4 mb-3">
            <h6 class="fw-bold">Certificate checklist</h6>
            <p class="small text-muted">All four must be true. Then the transcript and the letter of recommendation are released to the student, and the office can issue the certificate.</p>
            <?php if (!$check['applies']): ?>
                <div class="small text-muted">This student has no capstone in this course, so the office uses the normal certificate rules (fees paid).</div>
            <?php else:
                $d = $check['detail']; $cn = $check['conditions'];
                $rows = [
                    ['finished', 'Course time finished (or marked finished)', $cn['finished']['reason']],
                    ['fees', 'Course fees paid 100%', $cn['fees']['reason']],
                    ['capstone', 'Capstone passed (50 or more, released)', $cn['capstone']['reason'] . ($d['capstone_total'] !== null ? ' Final mark: ' . cap_pretty_marks($d['capstone_total']) . ' (' . $d['capstone_result'] . ').' : '')],
                    ['average', 'Course average 50 or more', $cn['average']['reason'] . ($d['average'] !== null ? ' Average now: ' . (int) round($d['average']) . '%.' : '')],
                ];
                foreach ($rows as [$k, $label, $why]): ?>
                <div class="d-flex justify-content-between gap-2 border-bottom py-2"><div><?php echo $tick($cn[$k]['ok']); ?> <?php echo asg_e($label); ?><div class="small text-muted ms-4"><?php echo asg_e($why); ?></div></div>
                    <span class="small fw-semibold text-nowrap"><?php echo asg_e($cn[$k]['word']); ?></span></div>
                <?php endforeach; ?>
                <div class="mt-2"><?php echo $check['ready'] ? '<span class="asg-chip st-graded"><i class="fas fa-award"></i>Ready</span>'
                    : '<span class="asg-chip ' . ($check['word'] === 'Retake needed' ? 'st-closed' : 'st-locked') . '"><i class="fas fa-hourglass-half"></i>' . asg_e($check['word']) . '</span>'; ?></div>
                <?php if ($check['word'] === 'Retake needed' && $d['failed_part']): ?>
                    <div class="alert alert-warning small mt-2 mb-0"><strong>A retake is needed.</strong> The failed part is <?php echo asg_e($d['failed_part']); ?>.
                        The student must retake it before the certificate, the transcript and the letter can be released.</div>
                <?php endif; ?>
            <?php endif; ?>
            <div class="small text-muted mt-2">Normal assignments do not decide certificates by themselves: only the course average and the capstone do.</div>
        </div>
        <?php if ($check['applies']): ?>
        <div class="card-tutor p-3 p-md-4 mb-3">
            <h6 class="fw-bold">Transcript and letter of recommendation</h6>
            <?php if ($release && $release['transcript_released_at']): ?>
                <div class="alert alert-success small">Released to the student on <strong><?php echo asg_e(cap_local($release['transcript_released_at'], 'D j M Y, H:i')); ?> EAT</strong>. The student was told.</div>
            <?php else: ?>
                <div class="small text-muted mb-2"><?php echo ($release && $release['hidden_at']) ? 'Hidden again: the student is not Ready now. ' : ''; ?>Both are released together, automatically, when the checklist is Ready. The student cannot open them before that.</div>
            <?php endif; ?>
            <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="letter_note">
                <label class="form-label small fw-semibold">A personal sentence for the letter <span class="text-muted fw-normal">(optional, up to 300 characters)</span></label>
                <textarea name="letter_note" maxlength="300" rows="2" class="form-control form-control-sm" <?php echo ($release && $release['letter_released_at']) ? 'readonly' : ''; ?> placeholder="For example: Lucy led her team calmly and helped others fix their code."><?php echo asg_e($release['letter_note'] ?? ''); ?></textarea>
                <div class="form-text">It shows only on the letter, never to the office. You can change it until the letter is released.</div>
                <div class="d-flex flex-wrap gap-2 mt-2"><?php if (!($release && $release['letter_released_at'])): ?><button class="btn btn-outline-dark btn-sm">Save sentence</button><?php endif; ?>
                    <a class="btn btn-outline-dark btn-sm" target="_blank" href="<?php echo asg_e(url('capstone/letter.php?course=' . $courseId . '&student=' . $uid)); ?>"><i class="fas fa-eye me-1"></i>Preview the letter</a>
                    <a class="btn btn-outline-dark btn-sm" target="_blank" href="<?php echo asg_e(url('capstone/transcript.php?course=' . $courseId . '&student=' . $uid)); ?>">Open the transcript</a></div>
            </form>
        </div>
        <?php endif; ?>
        <div class="card-tutor p-3 p-md-4">
            <h6 class="fw-bold">Transcript</h6>
            <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="transcript">
                <div class="form-check"><input class="form-check-input" type="checkbox" name="allow" value="1" id="allow" <?php echo cap_transcript_pref($pdo, $uid, $courseId) ? 'checked' : ''; ?>>
                    <label class="form-check-label small" for="allow">I allow the admin to preview this student's transcript</label></div>
                <div class="form-text">Off by default. The transcript page is built later and will use this setting.</div>
                <button class="btn btn-outline-dark btn-sm mt-2">Save</button></form>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
