<?php
// Student: read an assignment and send work (spec 8.7), ask for an extension, or ask to unlock a closed one (spec 8.9).
// A locked assignment returns only its number and the unlock message: no title or details, not even in the page source.
require __DIR__ . '/../../components/assignments/student_boot.php';
require_once __DIR__ . '/../../../app/v1/services/LinkChecker.php';

use Models\AssignmentModel;

$id = (int) ($_GET['id'] ?? 0);
$item = $am->itemForStudent($uid, $id);
if (!$item) asg_back('index.php', null, 'That assignment is not available to you.');
$self = 'view.php?id=' . $id;
// Group work: every member edits and sends the same work, held by the group's first member ($workUid).
$groupMembers = $am->groupMembers($id, $uid);
$workUid = $groupMembers ? $groupMembers[0] : $uid;
$isAjax = ($_POST['action'] ?? '') === 'autosave';

/** Read links / answers / note from the form, validated against what the tutor allowed. Returns [links, answers, note, errors]. */
function asg_read_work(array $item, array $boxes, array $post): array
{
    $errors = [];
    $links = [];
    if ((int) $item['submit_link']) {
        foreach ((array) ($post['links'] ?? []) as $l) {
            $l = trim((string) $l);
            if ($l === '') continue;
            $clean = asg_clean_link($l, true);
            if (!$clean) { $errors[] = 'Links must start with https:// (' . mb_substr($l, 0, 60) . ').'; continue; }
            if (!asg_link_site_ok($clean, $item['link_sites'])) { $errors[] = 'Your tutor only accepts links from: ' . implode(', ', array_map(fn($h) => ASG_LINK_SITES[$h] ?? $h, explode(',', $item['link_sites']))) . '.'; continue; }
            $links[] = $clean;
        }
        $links = array_slice(array_values(array_unique($links)), 0, 10);
    }
    $answers = [];
    foreach ($boxes as $q) {
        $t = str_replace("\r\n", "\n", (string) ($post['answers'][(int) $q['id']] ?? ''));
        if (mb_strlen($t) > 5000) { $errors[] = 'Each answer can be up to 5,000 characters.'; $t = mb_substr($t, 0, 5000); }
        $answers[(int) $q['id']] = $t;
    }
    $note = mb_substr(trim((string) ($post['note'] ?? '')), 0, 2000);
    return [$links, $answers, $note !== '' ? $note : null, $errors];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => 'Session expired. Refresh the page.']); exit; }
        asg_back($self, null, 'Security check failed. Please refresh and try again.');
    }
    $action = $_POST['action'] ?? '';
    $notifier = new AssignmentNotifier($pdo, $am);
    $boxes = $am->answerBoxes($item);

    if ($action === 'extension' || $action === 'unlock_request') {
        if ($am->recentCount($uid, ['extension_request', 'unlock_request'], 3600) >= 5) asg_back($self, null, 'Too many requests. Please try again later or speak to your tutor.');
        $r = $action === 'extension'
            ? $am->requestExtension($item, $uid, (string) ($_POST['reason'] ?? ''), (string) ($_POST['reason_text'] ?? ''), AssignmentModel::toUtc($_POST['want'] ?? ''))
            : $am->requestUnlock($item, $uid, (string) ($_POST['message'] ?? ''));
        if (!$r['ok']) asg_back($self . ($action === 'extension' ? '#extension' : ''), null, $r['error']);
        $action === 'extension' ? $notifier->extensionRequested($r['id']) : $notifier->unlockRequested($r['id']);
        asg_back($self, $action === 'extension' ? 'Request sent. Your assignment stays open while your tutor decides.' : 'Your tutor has been asked to unlock it. You can also speak to them directly.');
    }

    if (!AssignmentModel::canEdit($item)) {
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok' => false]); exit; }
        asg_back($self, null, 'This assignment can no longer be changed.');
    }
    [$links, $answers, $note, $errors] = asg_read_work($item, $boxes, $_POST);

    if ($isAjax) {
        header('Content-Type: application/json');
        // Keep links as typed while drafting (they are checked again on Save draft / Submit, which is all the tutor ever sees).
        $raw = array_slice(array_values(array_filter(array_map(fn($l) => mb_substr(trim((string) $l), 0, 500), (array) ($_POST['links'] ?? [])), 'strlen')), 0, 10);
        $am->saveDraft($id, $workUid, (int) $item['submit_link'] ? $raw : [], $answers, $note);
        if ((int) ($item['submit_code'] ?? 0)) $am->saveExtras($id, $workUid, mb_substr(str_replace("\r\n", "\n", (string) ($_POST['code'] ?? '')), 0, 20000));
        echo json_encode(['ok' => true, 'saved' => AssignmentModel::local(AssignmentModel::now(), 'H:i')]);
        exit;
    }
    if ($action === 'remove_file') {
        $am->removeFile($workUid, (int) ($_POST['file_id'] ?? 0));
        asg_back($self, 'File removed from your work.');
    }
    if ($action === 'remove_audio') {
        $am->saveAudio($id, $workUid, null);
        asg_back($self, 'Recording removed.');
    }
    if ((int) ($item['no_submission'] ?? 0)) asg_back($self, null, 'Nothing to send: your tutor records the mark for this one.');

    // Save draft / submit: text fields, then uploads (private storage, re-encoded images, checked files).
    $am->saveDraft($id, $workUid, $links, $answers, $note);
    $sub = $am->submission($id, $workUid);
    $subDir = 'submissions/' . (int) $sub['id'];
    $imgs = (int) $item['submit_images'] ? asg_files_list($_FILES['images'] ?? null) : [];
    $docs = (int) $item['submit_file'] ? asg_files_list($_FILES['files'] ?? null) : [];
    $haveImg = count(array_filter($sub['files'], fn($f) => $f['kind'] === 'image'));
    $haveDoc = count(array_filter($sub['files'], fn($f) => $f['kind'] === 'file'));
    if ($haveImg + count($imgs) > ASG_MAX_IMAGES) { $errors[] = 'Up to ' . ASG_MAX_IMAGES . ' images.'; $imgs = array_slice($imgs, 0, max(0, ASG_MAX_IMAGES - $haveImg)); }
    if ($haveDoc + count($docs) > ASG_MAX_FILES) { $errors[] = 'Up to ' . ASG_MAX_FILES . ' files.'; $docs = array_slice($docs, 0, max(0, ASG_MAX_FILES - $haveDoc)); }
    foreach ($imgs as $f) {
        $r = asg_store_image($f, $subDir);
        $r['ok'] ? $am->addFile($id, $workUid, 'image', $r, (string) $f['name']) : $errors[] = $r['error'];
    }
    foreach ($docs as $f) {
        $r = asg_store_document($f, $subDir, ['pdf', 'docx', 'xlsx', 'pptx', 'zip']);
        $r['ok'] ? $am->addFile($id, $workUid, 'file', $r, (string) $f['name']) : $errors[] = $r['error'];
    }
    if ((int) ($item['submit_code'] ?? 0)) $am->saveExtras($id, $workUid, mb_substr(str_replace("\r\n", "\n", (string) ($_POST['code'] ?? '')), 0, 20000));
    if ((int) ($item['submit_quiz'] ?? 0)) $am->saveQuizAnswers($id, $workUid, (array) ($_POST['quiz'] ?? []));
    if ((int) ($item['submit_audio'] ?? 0) && !empty($_FILES['audio']['name'])) {
        $r = asg_store_audio($_FILES['audio'], $subDir);
        $r['ok'] ? $am->saveAudio($id, $workUid, $r['path']) : $errors[] = $r['error'];
    }
    $am->log($id, $uid, $uid, 'draft');

    if ($action === 'submit') {
        if ($errors) asg_back($self, null, 'Not submitted yet. ' . implode(' ', $errors) . ' Your draft was saved.');
        if ($am->recentCount($uid, ['submit', 'resubmit'], 600) >= 6) asg_back($self, null, 'You have submitted many times in a short while. Please wait a few minutes.');
        if ((int) ($item['require_own_work'] ?? 0) && empty($_POST['own_work'])) asg_back($self, null, 'Tick "This is my own work" before you submit. Your draft was saved.');
        // Link check on Submit: warn, and block only when the tutor chose "Do not accept links that cannot be opened".
        // "Could not check" never blocks. A bad link never changes the sent time, Late or the mark.
        $checks = [];
        if ((int) ($item['check_links'] ?? 1)) {
            $tutorRow = $am->tutorById((int) $item['tutor_id']);
            $lc = new LinkChecker($pdo);
            foreach (AssignmentModel::workLinks($links, $answers) as $u) {
                $c = $lc->check($u, $item['link_sites'], $uid, (int) $sub['id'], $tutorRow['github_username'] ?? null);
                if ($c['status'] !== 'not_checked' && $c['status'] !== 'not_allowed') $checks[] = $c;
            }
        }
        $bad = array_values(array_filter($checks, fn($c) => $c['bad']));
        if ($bad && (int) ($item['block_bad_links'] ?? 0)) asg_back($self, null, 'Not submitted yet. ' . $bad[0]['title'] . ': ' . $bad[0]['message'] . ' Your tutor only accepts links that open.');
        if ($bad && empty($_POST['link_ack'])) asg_back($self, null, $bad[0]['title'] . ' (' . $bad[0]['host'] . '). ' . $bad[0]['message'] . ' To send it anyway, tick "I understand my tutor may not be able to open this link."');
        $r = $am->submit($workUid === $uid ? $item : ($am->itemForStudent($workUid, $id) ?: $item), $workUid);
        if (!$r['ok']) asg_back($self, null, $r['error'] . ' Your draft was saved.');
        $am->saveLinkResult((int) $sub['id'], $checks);
        if ((int) ($item['require_own_work'] ?? 0) || (int) ($item['ask_ai_note'] ?? 0)) {
            $am->saveDeclaration((int) $sub['id'], (int) ($item['require_own_work'] ?? 0) ? true : null, (int) ($item['ask_ai_note'] ?? 0) ? trim((string) ($_POST['ai_note'] ?? '')) : null);
        }
        foreach ($groupMembers ? $am->mirrorGroupSubmission($id, $workUid) : [] as $mate) $notifier->receipt($item, $mate, (int) $r['version'], (bool) $r['late']);
        $notifier->receipt($item, $uid, (int) $r['version'], (bool) $r['late']);
        // Quiz only, marks, no rubric: the system marks it. Held grades stay hidden until the release time.
        $onlyQuiz = (int) ($item['submit_quiz'] ?? 0) && !$item['types'] && !(int) ($item['submit_code'] ?? 0) && !(int) ($item['submit_audio'] ?? 0);
        if ($onlyQuiz && $item['grading_mode'] === 'marks' && !$am->rubric($id) && ($ctx = $am->submissionForGrader((int) $sub['id'], null))) {
            $qs = $am->quizScore($id, (int) $sub['id']);
            $mark = $qs['of'] > 0 ? round($qs['got'] / $qs['of'] * (float) $item['max_score'], 2) : 0;
            $g = $am->grade($ctx, ['score' => $mark, 'why' => 'Marked by the system: ' . $qs['got'] . ' of ' . $qs['of'] . ' points.', 'improve' => 'Look again at the questions you got wrong.'], 'publish', $uid);
            if ($g['ok'] && !AssignmentModel::gradesHeld($item)) $notifier->result($am->get($id), $am->submissionById((int) $sub['id']), 'published');
            if ($g['ok']) $autoMsg = AssignmentModel::gradesHeld($item) ? 'Submitted and marked. Your marks show when your tutor releases them.' : 'Submitted and marked. Open your result to see your answers.';
        }
        $notifier->submitted($item, $uid, $r['resubmitted'], (int) $sub['id']);
        $notifier->unlocked($uid, array_diff($r['unlocked'], [$id]));
        $next = array_values(array_filter($am->chain($uid, (int) $item['tutor_id'], (int) $item['course_id']), fn($x) => $x['index'] === $item['index'] + 1));
        $msg = ($autoMsg ?? ($r['resubmitted'] ? 'New version sent (version ' . $r['version'] . ').' : 'Submitted. Your tutor will grade it.'))
            . ($next && $next[0]['state'] !== 'locked' && !$r['resubmitted'] ? ' Assignment ' . $next[0]['index'] . ' is now open.' : '');
        asg_back($self, $msg);
    }
    $errors ? asg_back($self, null, 'Draft saved, but: ' . implode(' ', $errors)) : asg_back($self, 'Draft saved. You can come back and finish later.');
}

$state = $item['state'];
$noun = ($item['kind'] ?? 'daily') === 'weekly' ? 'Week' : 'Assignment';
$page_title = $noun . ' ' . $item['index'];
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">‹ My assignments</a>

<?php if ($state === 'locked'): ?>
<div class="asg-card is-locked">
    <div class="num"><i class="fas fa-lock" aria-hidden="true"></i></div>
    <div class="flex-grow-1"><div class="k">Assignment <?php echo (int) $item['index']; ?></div><div class="s">Submit Assignment <?php echo (int) $item['prev_index']; ?> to unlock this one.</div></div>
    <span class="small fw-bold text-muted">LOCKED</span>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/students/layout.php'; exit; endif; ?>

<?php
$a = $am->get($id);
$sub = $am->submission($id, $workUid);
$boxes = $am->answerBoxes($a);
$questions = $am->questions($id);
$attachments = $am->attachments($id);
$editable = AssignmentModel::canEdit($item);
$nextNo = $item['index'] + 1;
$hasNext = (bool) array_filter($am->chain($uid, (int) $item['tutor_id'], (int) $item['course_id']), fn($x) => $x['index'] === $nextNo);
$canExtend = in_array($state, ['open', 'in_progress'], true) && $item['due'] && AssignmentModel::ts($item['due']) > time();
$lastExt = $am->latestRequest($id, $uid, 'extension');
$lastUnlock = $am->latestRequest($id, $uid, 'unlock');
$maxWant = $item['due'] ? gmdate('Y-m-d H:i:s', AssignmentModel::ts($item['due']) + (int) $a['max_extension_days'] * 86400) : null;
?>
<div class="row g-3">
<div class="col-lg-7">
    <div class="card-student p-3 p-md-4">
        <h4 class="mb-2"><?php echo $noun; ?> <?php echo (int) $item['index']; ?>: <?php echo asg_e($a['title']); ?></h4>
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <span class="asg-chip st-progress"><i class="fas fa-calendar"></i><?php echo asg_e(AssignmentModel::dueText($item['due'])); ?></span>
            <?php echo asg_chip($state); ?>
            <?php if ($item['late'] && in_array($state, ['open', 'in_progress'], true)): ?><span class="asg-chip st-returned"><i class="fas fa-clock"></i>Late work accepted</span><?php endif; ?>
            <?php if ($canExtend): ?><a href="#extension" class="btn btn-sm btn-outline-success" data-bs-toggle="collapse" data-bs-target="#extension">Request extension</a><?php endif; ?>
        </div>

        <?php if ($state === 'closed'): ?>
        <div class="d-flex flex-wrap gap-3 align-items-center p-3 mb-3" style="background:#fbe3e1;border:1px solid #d9534f;border-radius:14px">
            <i class="fas fa-lock fa-lg" style="color:#b42318" aria-hidden="true"></i>
            <div class="flex-grow-1">This assignment is locked because the due date has passed. Please ask your tutor to unlock it.
                <?php if ($lastUnlock && $lastUnlock['status'] === 'declined'): ?><div class="small mt-1">Your tutor said no<?php echo $lastUnlock['decision_note'] ? ': ' . asg_e($lastUnlock['decision_note']) : '.'; ?></div><?php endif; ?></div>
            <?php if ($item['unlock_request_id']): ?><span class="asg-chip st-extension"><i class="fas fa-hourglass-half"></i>Asked, waiting for your tutor</span>
            <?php else: ?>
            <form method="POST" class="d-flex flex-wrap gap-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="unlock_request">
                <input name="message" class="form-control form-control-sm" style="min-width:200px" placeholder="Message to your tutor (optional)" maxlength="2000">
                <button class="btn btn-sm btn-outline-danger fw-bold">Ask my tutor to unlock</button></form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($lastExt && $lastExt['status'] !== 'pending' && $lastExt['status'] !== 'cancelled' && in_array($state, ['open', 'in_progress', 'closed'], true)): ?>
            <div class="alert <?php echo $lastExt['status'] === 'approved' ? 'alert-success' : 'alert-warning'; ?> small">
                Extension <?php echo $lastExt['status']; ?><?php echo $lastExt['status'] === 'approved' ? ': new due date ' . asg_e(AssignmentModel::local($lastExt['new_due_at'])) : ''; ?>.
                <?php echo $lastExt['decision_note'] ? 'Your tutor says: ' . asg_e($lastExt['decision_note']) : ''; ?></div>
        <?php endif; ?>

        <?php if ($canExtend): ?>
        <div class="collapse mb-3" id="extension"><div class="asg-panel">
            <h6 class="fw-bold mb-0">Request an extension</h6>
            <div class="small text-muted mb-2">Assignment <?php echo (int) $item['index']; ?>: <?php echo asg_e($a['title']); ?> · <?php echo asg_e(AssignmentModel::dueText($item['due'])); ?></div>
            <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="extension">
                <label class="form-label small">Reason</label>
                <select name="reason" class="form-select mb-2" required><option value="">Choose…</option>
                    <?php foreach (AssignmentModel::REASONS as $k => $lbl): ?><option value="<?php echo $k; ?>"><?php echo asg_e($lbl); ?></option><?php endforeach; ?></select>
                <label class="form-label small">Tell your tutor what happened</label>
                <textarea name="reason_text" class="form-control mb-2" rows="2" required maxlength="2000"></textarea>
                <label class="form-label small">New date and time you need <span class="text-muted">(up to <?php echo (int) $a['max_extension_days']; ?> days more, East Africa Time)</span></label>
                <input type="datetime-local" name="want" class="form-control mb-2" required min="<?php echo asg_e(AssignmentModel::localInput($item['due'])); ?>" max="<?php echo asg_e(AssignmentModel::localInput($maxWant)); ?>">
                <p class="small text-muted fst-italic">Your assignment stays open while your tutor decides.</p>
                <div class="d-flex gap-2"><button type="button" class="btn btn-outline-dark" data-bs-toggle="collapse" data-bs-target="#extension">Cancel</button><button class="btn btn-brand flex-grow-1">Send request</button></div>
            </form>
        </div></div>
        <?php endif; ?>

        <?php if (!empty($a['class_session_id']) && ($cls = $am->classSession((int) $a['class_session_id']))): ?>
            <div class="asg-box small mb-3"><i class="fas fa-calendar-check me-1"></i>After this class: <strong><?php echo asg_e($cls['title']); ?></strong>, <?php echo asg_e(AssignmentModel::local($cls['start_utc'], 'D j M, H:i')); ?></div>
        <?php endif; ?>
        <?php if (in_array($state, ['open', 'in_progress', 'extension'], true) && !(int) $a['close_after_due'] && !(int) ($a['no_submission'] ?? 0)): ?>
            <div class="small mb-3"><i class="fas fa-circle-info me-1"></i>If you send it after the due time, it is marked <strong>Late</strong>. You can still send it.</div>
        <?php endif; ?>
        <div class="asg-section">Description</div>
        <p><?php echo nl2br(asg_e((string) $a['description'])); ?></p>
        <div class="asg-section">Details and questions</div>
        <div class="asg-details mb-2"><?php echo $a['details'] ?: nl2br(asg_e((string) $a['instructions'])); ?></div>
        <?php foreach ($questions as $n => $q): ?><div class="asg-box mb-2 small"><strong>Q<?php echo $n + 1; ?></strong> <?php echo nl2br(asg_e($q['text'])); ?></div><?php endforeach; ?>
        <?php if ($attachments || $a['attachment_path']): ?>
            <div class="asg-section">Attachments</div>
            <?php foreach ($attachments as $att): ?><a class="asg-pill" target="_blank" rel="noopener noreferrer" href="<?php echo asg_e($att['kind'] === 'link' ? $att['url'] : asg_files_url('att', (int) $att['id'])); ?>"><i class="fas <?php echo $att['kind'] === 'link' ? 'fa-link' : ($att['kind'] === 'pdf' ? 'fa-file-pdf' : 'fa-image'); ?>"></i><?php echo asg_e($att['label']); ?></a><?php endforeach; ?>
            <?php if ($a['attachment_path']): ?><a class="asg-pill" target="_blank" rel="noopener" href="<?php echo asg_e(settings_asset_url($a['attachment_path'])); ?>"><i class="fas fa-paperclip"></i>Attachment</a><?php endif; ?>
        <?php endif; ?>
        <?php if ($a['details'] && $a['instructions']): ?><div class="asg-section">Instructions</div><div class="asg-box"><?php echo nl2br(asg_e($a['instructions'])); ?><?php echo $editable ? ' You can save a draft and come back later.' : ''; ?></div><?php endif; ?>
    </div>
</div>

<div class="col-lg-5">
    <?php if (!empty($item['marks_held'])): ?>
    <div class="asg-fb"><div class="h"><i class="fas fa-lock me-1"></i>Marks are not out yet.</div>Your tutor will release them on <?php echo asg_e(AssignmentModel::local($item['release_grades_at'], 'D j M, H:i')); ?> EAT.</div>
    <?php endif; ?>
    <?php if ($state === 'returned' && $sub): ?>
    <div class="asg-fb dark"><div class="h">Your tutor asked for changes</div><div style="white-space:pre-wrap"><?php echo asg_e((string) $sub['feedback_why']); ?></div>
        <?php $fix = json_decode((string) ($sub['return_items'] ?? ''), true) ?: []; ?>
        <?php if ($fix): ?><div class="h mt-2">What to fix</div><ul class="mb-1 ps-3"><?php foreach ($fix as $t): ?><li><?php echo asg_e($t); ?></li><?php endforeach; ?></ul>
        <?php elseif ($sub['feedback_improve']): ?><div class="h mt-2">What to change</div><div style="white-space:pre-wrap"><?php echo asg_e($sub['feedback_improve']); ?></div><?php endif; ?>
        <div class="small text-muted mt-1"><i class="fas fa-clock me-1"></i>You can resubmit until <?php echo asg_e(AssignmentModel::local(!empty($sub['resubmit_by']) ? $sub['resubmit_by'] : $item['due'], 'D j M, H:i')); ?> EAT. It will not be marked Late.</div></div>
    <?php endif; ?>
    <?php if ($state === 'graded'): ?>
        <div class="asg-panel mb-3 text-center"><div class="fw-bold mb-2">Your tutor has graded this.</div><a href="result.php?id=<?php echo $id; ?>" class="btn btn-brand">View result</a></div>
    <?php endif; ?>

    <?php if ((int) ($a['no_submission'] ?? 0)): ?>
    <div class="asg-panel"><h6 class="fw-bold">Practical work</h6><p class="small mb-0">You do not send anything for this one. Your tutor watches the work and records your mark.</p></div>
    <?php elseif ($editable): ?>
    <form method="POST" enctype="multipart/form-data" class="asg-panel" id="workForm"><?php echo csrf_field(); ?>
        <?php if ($groupMembers): ?><div class="asg-box small mb-2"><i class="fas fa-users me-1"></i>Group work with <?php echo asg_e(implode(', ', array_map(fn($m) => $am->student($m)['full_name'], array_diff($groupMembers, [$uid])))); ?>. You all edit and send one piece of work, and you all get the same mark.</div><?php endif; ?>
        <h6 class="fw-bold">Your submission <?php if ($sub && $sub['version']): ?><span class="small text-muted fw-normal">· version <?php echo (int) $sub['version']; ?> sent <?php echo asg_e(AssignmentModel::local($sub['submitted_at'], 'D j M, H:i')); ?></span><?php endif; ?></h6>
        <?php if ((int) $a['submit_link']): $links = $sub['links'] ?? []; ?>
            <label class="form-label small mb-1"><?php echo $a['link_sites'] ? asg_e(implode(', ', array_map(fn($h) => ASG_LINK_SITES[$h] ?? $h, explode(',', $a['link_sites'])))) . ' link' : 'GitHub or other link'; ?></label>
            <div id="linkBox"><?php foreach ($links ?: [''] as $l): ?><input type="url" name="links[]" class="form-control mb-2" placeholder="https://" value="<?php echo asg_e($l); ?>" pattern="https://.*"><?php endforeach; ?></div>
            <button type="button" class="btn btn-link btn-sm p-0 mb-2" onclick="var i=document.createElement('input');i.type='url';i.name='links[]';i.className='form-control mb-2';i.placeholder='https://';document.getElementById('linkBox').appendChild(i)">+ Add another link</button>
        <?php endif; ?>
        <?php if ((int) $a['submit_link'] && (int) ($a['check_links'] ?? 1)): ?><div id="linkResults" class="mb-2" aria-live="polite"></div><?php endif; ?>
        <?php foreach ($boxes as $n => $q): ?>
            <label class="form-label small mb-1"><?php echo (int) $q['id'] ? 'Answer Q' . ($n + 1) : 'Your answer'; ?></label>
            <textarea name="answers[<?php echo (int) $q['id']; ?>]" class="form-control mb-2" rows="4" maxlength="5000"><?php echo asg_e($sub['answers'][(int) $q['id']] ?? ''); ?></textarea>
        <?php endforeach; ?>
        <?php if ((int) ($a['submit_code'] ?? 0)): ?>
            <label class="form-label small mb-1">Code box <span class="text-muted">(saved as text, never run)</span></label>
            <textarea name="code" class="form-control mb-2 asg-code" rows="8" spellcheck="false" maxlength="20000"><?php echo asg_e((string) ($sub['code_text'] ?? '')); ?></textarea>
        <?php endif; ?>
        <?php if ((int) ($a['submit_quiz'] ?? 0) && ($quizQs = $am->quizQuestions($id))): $qa = $sub ? $am->quizAnswers((int) $sub['id']) : []; ?>
            <div class="asg-section">Quiz <span class="text-muted fw-normal">(marked by the system; answers show when grades are released)</span></div>
            <?php foreach ($quizQs as $n => $q): ?>
                <div class="asg-box mb-2"><div class="small fw-semibold mb-1"><?php echo $n + 1; ?>. <?php echo asg_e($q['prompt']); ?></div>
                <?php foreach ($q['options'] as $oi => $opt): ?>
                    <div class="form-check small"><input class="form-check-input" type="radio" name="quiz[<?php echo (int) $q['id']; ?>]" value="<?php echo $oi; ?>" id="q<?php echo (int) $q['id'] . '_' . $oi; ?>" <?php echo ($qa[(int) $q['id']] ?? null) === $oi ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="q<?php echo (int) $q['id'] . '_' . $oi; ?>"><?php echo asg_e($opt); ?></label></div>
                <?php endforeach; ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if ((int) ($a['submit_audio'] ?? 0)): ?>
            <label class="form-label small mb-1">Record your answer <span class="text-muted">(up to 2 minutes)</span></label>
            <div class="asg-box mb-2" id="recBox">
                <?php if (!empty($sub['audio_path'])): ?><audio controls preload="none" src="<?php echo asg_e(asg_files_url('aud', (int) $sub['id'])); ?>" class="w-100 mb-1"></audio>
                    <button form="rmAudio" class="btn btn-link btn-sm text-danger p-0">Remove recording</button><?php endif; ?>
                <div class="d-flex align-items-center gap-2 mt-1"><button type="button" class="btn btn-sm btn-danger" id="recBtn"><i class="fas fa-microphone me-1"></i>Tap to record</button><span class="small text-muted" id="recTime">0:00</span></div>
                <div class="small text-muted mt-1 d-none" id="recNo">Your browser cannot record sound. Use another answer type, or open this page in Chrome or Firefox.</div>
                <input type="file" name="audio" id="recFile" class="d-none" accept="audio/*">
            </div>
        <?php endif; ?>
        <?php if ((int) $a['submit_images']): $imgs = array_filter($sub['files'] ?? [], fn($f) => $f['kind'] === 'image'); ?>
            <label class="form-label small mb-1">Photos or screenshots <span class="text-muted">(PNG or JPG, up to 5 MB each, up to <?php echo ASG_MAX_IMAGES; ?>)</span></label>
            <?php if ($imgs): ?><div class="asg-shots mb-2"><?php foreach ($imgs as $f): ?>
                <div class="text-center"><a href="<?php echo asg_e(asg_files_url('sub', (int) $f['id'])); ?>" target="_blank" style="width:96px;height:72px"><img src="<?php echo asg_e(asg_files_url('sub', (int) $f['id'])); ?>" alt="<?php echo asg_e($f['original_name']); ?>"></a>
                    <button form="rm<?php echo (int) $f['id']; ?>" class="btn btn-link btn-sm text-danger p-0">Remove</button></div>
            <?php endforeach; ?></div><?php endif; ?>
            <input type="file" name="images[]" accept=".png,.jpg,.jpeg,image/png,image/jpeg" multiple class="form-control mb-2">
        <?php endif; ?>
        <?php if ((int) $a['submit_file']): $docs = array_filter($sub['files'] ?? [], fn($f) => $f['kind'] === 'file'); ?>
            <label class="form-label small mb-1">Files <span class="text-muted">(PDF, Word, Excel, PowerPoint or ZIP, up to 10 MB each, up to <?php echo ASG_MAX_FILES; ?>)</span></label>
            <?php foreach ($docs as $f): ?><div class="small mb-1"><i class="fas fa-file me-1"></i><a href="<?php echo asg_e(asg_files_url('sub', (int) $f['id'], true)); ?>"><?php echo asg_e($f['original_name']); ?></a> (<?php echo asg_human_bytes((int) $f['bytes']); ?>)
                <button form="rm<?php echo (int) $f['id']; ?>" class="btn btn-link btn-sm text-danger p-0 ms-1">Remove</button></div><?php endforeach; ?>
            <input type="file" name="files[]" accept=".pdf,.docx,.xlsx,.pptx,.zip" multiple class="form-control mb-2">
        <?php endif; ?>
        <?php if ((int) $a['submit_images'] || (int) $a['submit_file']): ?>
            <label class="form-label small mb-1">Note <span class="text-muted">(optional)</span></label>
            <textarea name="note" class="form-control mb-2" rows="2" maxlength="2000"><?php echo asg_e((string) ($sub['note'] ?? '')); ?></textarea>
        <?php endif; ?>
        <div class="asg-box small text-success mb-3" style="background:var(--g-50,#eef8e9)">Your tutor allowed: <?php echo asg_e(mb_strtolower($a['types_text'])); ?>.<?php echo (int) $a['submit_link'] ? ' Check that your link opens in a private window.' : ''; ?></div>
        <?php if ((int) $a['submit_link'] && (int) ($a['check_links'] ?? 1)): ?>
            <div class="form-check small mb-2 d-none" id="linkAckBox"><input class="form-check-input" type="checkbox" name="link_ack" value="1" id="linkAck"><label class="form-check-label" for="linkAck">I understand my tutor may not be able to open this link.</label></div>
        <?php endif; ?>
        <?php if ((int) ($a['ask_ai_note'] ?? 0)): ?>
            <label class="form-label small mb-1">Did you use AI? Tell us how. <span class="text-muted">(optional)</span></label>
            <textarea name="ai_note" class="form-control mb-2" rows="2" maxlength="1000"><?php echo asg_e((string) ($sub['ai_note'] ?? '')); ?></textarea>
        <?php endif; ?>
        <?php if ((int) ($a['require_own_work'] ?? 0)): ?>
            <div class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="own_work" value="1" id="ownWork"><label class="form-check-label" for="ownWork">This is my own work.</label></div>
        <?php endif; ?>
        <div class="d-flex gap-2">
            <button name="action" value="draft" class="btn btn-outline-dark flex-grow-1" formnovalidate>Save draft</button>
            <button name="action" value="submit" class="btn btn-brand flex-grow-1" onclick="return confirm('Send this work to your tutor now?')"><?php echo $sub && $sub['version'] ? 'Send new version' : 'Submit'; ?></button>
        </div>
        <p class="small text-muted fst-italic mt-2 mb-0"><span id="autosaveMsg"><?php echo $sub && $sub['draft_saved_at'] ? 'Draft saved ' . asg_e(AssignmentModel::local($sub['draft_saved_at'], 'D j M, H:i')) . '. ' : ''; ?></span>
            <?php echo $hasNext && !($sub['version'] ?? 0) ? 'After you submit, Assignment ' . $nextNo . ' unlocks. ' : ''; ?>You can send a new version until your tutor grades it.</p>
    </form>
    <?php if (!empty($sub['audio_path'])): ?><form method="POST" id="rmAudio" class="d-none" onsubmit="return confirm('Remove your recording?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="remove_audio"></form><?php endif; ?>
    <?php foreach (($sub['files'] ?? []) as $f): ?>
        <form method="POST" id="rm<?php echo (int) $f['id']; ?>" class="d-none" onsubmit="return confirm('Remove this file from your work?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="remove_file"><input type="hidden" name="file_id" value="<?php echo (int) $f['id']; ?>"></form>
    <?php endforeach; ?>
    <?php elseif ($sub && $sub['submitted_at']): ?>
    <div class="asg-panel">
        <h6 class="fw-bold">Your submission</h6>
        <div class="small text-muted mb-2">Version <?php echo (int) $sub['version']; ?>, sent <?php echo asg_e(AssignmentModel::local($sub['submitted_at'])); ?></div>
        <?php foreach ($sub['links'] as $l): ?><div class="mb-1"><?php echo asg_link($l, $l, 'text-break'); ?></div><?php endforeach; ?>
        <?php foreach ($boxes as $n => $q): if (trim($sub['answers'][(int) $q['id']] ?? '') === '') continue; ?>
            <div class="asg-section"><?php echo (int) $q['id'] ? 'Answer Q' . ($n + 1) : 'Your answer'; ?></div><div class="asg-box small" style="white-space:pre-wrap"><?php echo asg_e($sub['answers'][(int) $q['id']]); ?></div>
        <?php endforeach; ?>
        <?php foreach ($sub['files'] as $f): ?><a class="asg-pill" href="<?php echo asg_e(asg_files_url('sub', (int) $f['id'], $f['kind'] === 'file')); ?>" target="_blank"><i class="fas <?php echo $f['kind'] === 'image' ? 'fa-image' : 'fa-file'; ?>"></i><?php echo asg_e($f['original_name']); ?></a><?php endforeach; ?>
        <?php if (!empty($sub['code_text'])): ?><div class="asg-section">Code box</div><pre class="asg-code p-2 small mb-2"><?php echo asg_e($sub['code_text']); ?></pre><?php endif; ?>
        <?php if (!empty($sub['audio_path'])): ?><audio controls preload="none" class="w-100 mt-2" src="<?php echo asg_e(asg_files_url('aud', (int) $sub['id'])); ?>"></audio><?php endif; ?>
        <?php if (!empty($sub['answer_text'])): ?><div class="asg-box small mt-2" style="white-space:pre-wrap"><?php echo asg_e($sub['answer_text']); ?></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($sub && $sub['submitted_at']): $hist = $am->history((int) $sub['id']); if ($hist): ?>
    <div class="asg-panel mt-3"><h6 class="fw-bold">History</h6>
        <?php foreach ($hist as $h): ?><div class="d-flex gap-2 small py-1 border-bottom"><i class="fas <?php echo asg_e($h['icon']); ?> mt-1"></i>
            <div><div class="fw-semibold"><?php echo asg_e($h['text']); ?></div><div class="text-muted"><?php echo asg_e(AssignmentModel::local($h['at'], 'D j M, H:i')); ?></div></div></div><?php endforeach; ?>
        <?php if (!empty($sub['receipt_sent_at'])): ?><div class="small mt-2" style="color:#36851b"><i class="fas fa-envelope me-1"></i>A receipt was emailed to you after each submission.</div><?php endif; ?>
    </div>
    <?php endif; endif; ?>
</div>
</div>
<?php
$content = ob_get_clean();
if ($editable) {
    $extra_js = <<<'JS'
<script>
// Drafts save automatically while the student types (text only; files are saved with Save draft or Submit).
(function () {
    var form = document.getElementById('workForm'), msg = document.getElementById('autosaveMsg'), dirty = false, busy = false;
    if (!form) return;
    form.addEventListener('input', function (e) { if (e.target.type !== 'file') dirty = true; });
    function save() {
        if (!dirty || busy) return;
        busy = true; dirty = false;
        var fd = new FormData();
        form.querySelectorAll('input[name="links[]"], textarea, input[name=csrf_token]').forEach(function (el) { fd.append(el.name, el.value); });
        fd.append('action', 'autosave');
        fetch(location.href, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); })
            .then(function (j) { if (j.ok) msg.textContent = 'Draft saved at ' + j.saved + '. '; else if (j.error) msg.textContent = j.error + ' '; })
            .catch(function () { dirty = true; }).finally(function () { busy = false; });
    }
    setInterval(save, 20000);
    window.addEventListener('beforeunload', function () { if (dirty && navigator.sendBeacon) { var fd = new FormData(); form.querySelectorAll('input[name="links[]"], textarea, input[name=csrf_token]').forEach(function (el) { fd.append(el.name, el.value); }); fd.append('action', 'autosave'); navigator.sendBeacon(location.href, fd); } });
    form.addEventListener('submit', function () { dirty = false; });
    // Links must start with https: warn straight away, the server checks again.
    form.addEventListener('change', function (e) {
        if (e.target.name === 'links[]' && e.target.value && !/^https:\/\//i.test(e.target.value)) e.target.setCustomValidity('Links must start with https://');
        else if (e.target.name === 'links[]') e.target.setCustomValidity('');
    });
    // Link check: as the student leaves the box, and with Check again. The server does the check.
    var results = document.getElementById('linkResults'), ackBox = document.getElementById('linkAckBox'), state = {};
    function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    function render() {
        if (!results) return;
        results.innerHTML = '';
        var anyBad = false;
        Object.keys(state).forEach(function (u) {
            var r = state[u]; if (!r || !r.title) return;
            var cls = r.status === 'works' ? 'ok' : (r.status === 'not_found' ? 'bad' : (r.bad ? 'warn' : 'grey'));
            anyBad = anyBad || r.bad;
            var div = document.createElement('div');
            div.className = 'asg-lc asg-lc-' + cls;
            div.innerHTML = '<div><strong>' + esc(r.title) + '</strong><div>' + esc(r.message) + '</div><div class="text-muted text-break">' + esc(u) + '</div></div>'
                + (r.status === 'works' ? '' : '<button type="button" class="btn btn-sm btn-light">Check again</button>');
            var b = div.querySelector('button'); if (b) b.addEventListener('click', function () { check(u, true); });
            results.appendChild(div);
        });
        if (ackBox) ackBox.classList.toggle('d-none', !anyBad);
    }
    function check(u, again) {
        if (!/^https:\/\//i.test(u) || (state[u] && !again)) return;
        state[u] = { title: 'Checking…', message: '', status: 'checking' }; render();
        var fd = new FormData(); fd.append('csrf_token', form.querySelector('input[name=csrf_token]').value);
        fd.append('id', new URLSearchParams(location.search).get('id')); fd.append('url', u);
        fetch(location.pathname.replace(/view\.php$/, 'link_check.php'), { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); })
            .then(function (j) { state[u] = j.ok ? j : null; render(); }).catch(function () { state[u] = null; render(); });
    }
    if (results) {
        form.addEventListener('focusout', function (e) { if (e.target.name === 'links[]' && e.target.value) check(e.target.value.trim()); });
        form.addEventListener('paste', function (e) { if (e.target.name === 'links[]') setTimeout(function () { check(e.target.value.trim()); }, 50); });
        form.querySelectorAll('input[name="links[]"]').forEach(function (i) { if (i.value) check(i.value.trim()); });
    }
    // Audio: record in the browser, up to 2 minutes; the file is sent with Save draft or Submit.
    var recBtn = document.getElementById('recBtn');
    if (recBtn) {
        if (!window.MediaRecorder || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            recBtn.disabled = true; document.getElementById('recNo').classList.remove('d-none');
        } else {
            var rec = null, chunks = [], t0 = 0, timer = null, fileIn = document.getElementById('recFile'), tEl = document.getElementById('recTime');
            recBtn.addEventListener('click', function () {
                if (rec && rec.state === 'recording') { rec.stop(); return; }
                navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
                    chunks = []; rec = new MediaRecorder(stream);
                    rec.ondataavailable = function (e) { if (e.data.size) chunks.push(e.data); };
                    rec.onstop = function () {
                        clearInterval(timer); stream.getTracks().forEach(function (t) { t.stop(); });
                        var type = rec.mimeType || 'audio/webm', blob = new Blob(chunks, { type: type });
                        var ext = /mp4/.test(type) ? 'm4a' : (/ogg/.test(type) ? 'ogg' : 'webm');
                        var dt = new DataTransfer(); dt.items.add(new File([blob], 'answer.' + ext, { type: type })); fileIn.files = dt.files;
                        recBtn.innerHTML = '<i class="fas fa-microphone me-1"></i>Record again';
                        tEl.textContent += ' recorded. Save draft or submit to keep it.';
                    };
                    rec.start(); t0 = Date.now(); recBtn.innerHTML = '<i class="fas fa-stop me-1"></i>Stop';
                    timer = setInterval(function () { var s = Math.floor((Date.now() - t0) / 1000); tEl.textContent = Math.floor(s / 60) + ':' + ('0' + s % 60).slice(-2); if (s >= 120) rec.stop(); }, 250);
                }).catch(function () { document.getElementById('recNo').textContent = 'We could not use your microphone. Allow it in your browser and try again.'; document.getElementById('recNo').classList.remove('d-none'); });
            });
        }
    }
})();
</script>
JS;
}
require __DIR__ . '/../../templates/students/layout.php';
