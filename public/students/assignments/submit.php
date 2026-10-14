<?php
// Student: hand in work without an assignment being set (open submissions). Title + GitHub link, Google Drive link or a
// document, and an optional note. Links are checked live (action=check) and again on submit. Returned work can be resubmitted.
require __DIR__ . '/../../components/assignments/student_boot.php';
require_once __DIR__ . '/../../../app/v1/helpers/open_submissions.php';
require_once __DIR__ . '/../../../app/v1/services/PortalNotifications.php';

$self = 'submit.php';
$tutors = os_student_tutors($pdo, $uid);
$mine = os_for_student($pdo, $uid);
$editId = (int) ($_GET['edit'] ?? $_POST['edit'] ?? 0);
$editing = null;
foreach ($mine as $m) if ((int) $m['id'] === $editId && $m['status'] === 'returned') $editing = $m;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'check') {
        header('Content-Type: application/json');
        if (!csrf_verify($_POST['csrf_token'] ?? null)) { echo json_encode(['ok' => false, 'error' => 'Session expired. Refresh the page.']); exit; }
        echo json_encode(os_check_link((string) ($_POST['link'] ?? '')));
        exit;
    }
    if (!csrf_verify($_POST['csrf_token'] ?? null)) asg_back($self, null, 'Security check failed. Please refresh and try again.');

    $title = trim(preg_replace('/\s+/', ' ', (string) ($_POST['title'] ?? '')));
    $note = mb_substr(trim(str_replace("\r\n", "\n", (string) ($_POST['note'] ?? ''))), 0, 2000);
    $kind = in_array($_POST['kind'] ?? '', ['github', 'drive', 'file'], true) ? $_POST['kind'] : '';
    $tutorId = (int) ($_POST['tutor_id'] ?? 0) ?: (int) array_key_first($tutors);
    $error = null;
    $work = ['kind' => null, 'url' => null, 'meta' => null, 'file' => null];
    $upload = asg_files_list($_FILES['document'] ?? null)[0] ?? null;
    $hasNew = $kind === 'file' ? (bool) $upload : trim((string) ($_POST['link'] ?? '')) !== '';

    if ($editId && !$editing) $error = 'Only work your tutor returned can be resubmitted.';
    elseif (!$tutors) $error = 'You have no tutor assigned yet, so there is nobody to send work to. Please contact the office.';
    elseif (!isset($tutors[$tutorId])) $error = 'Choose your tutor.';
    elseif ($title === '' || mb_strlen($title) > 150) $error = 'Give the assignment a title (up to 150 characters).';
    elseif (!$kind) $error = 'Choose what you are submitting: a GitHub link, a Google Drive link or a document.';
    elseif (!$hasNew && !$editing) $error = $kind === 'file' ? 'Choose the document to upload.' : 'Paste your link.';
    elseif ($recent = (function () use ($pdo, $uid) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM open_submissions WHERE student_user_id = ? AND submitted_at > ?');
        $st->execute([$uid, date('Y-m-d H:i:s', time() - 3600)]);
        return (int) $st->fetchColumn() >= 20;
    })()) $error = 'Too many submissions in the last hour. Please try again later.';
    elseif ($hasNew && $kind === 'file') {
        $r = asg_store_document($upload, 'open/' . $uid, OS_DOC_TYPES);
        if (!$r['ok']) $error = $r['error'];
        else $work = ['kind' => 'file', 'url' => null, 'meta' => null,
                      'file' => ['path' => $r['path'], 'name' => mb_substr(basename((string) $upload['name']), 0, 255), 'mime' => $r['mime'], 'bytes' => $r['bytes']]];
    } elseif ($hasNew) {
        $r = os_check_link((string) $_POST['link']);
        if (!$r['ok']) $error = $r['error'];
        elseif ($r['kind'] !== $kind) $error = $kind === 'github' ? 'That is not a GitHub link.' : 'That is not a Google Drive link.';
        else $work = ['kind' => $r['kind'], 'url' => $r['url'], 'meta' => $r['meta'], 'file' => null];
    }

    if (!$error) {
        $id = os_save($pdo, $work + ['title' => $title, 'note' => $note !== '' ? $note : null, 'tutor_id' => $tutorId,
                                     'course_id' => (int) ($reg['course_id'] ?? 0) ?: null, 'student_user_id' => $uid], $editing);
        $tu = $pdo->prepare('SELECT user_id FROM tutors WHERE id = ?');
        $tu->execute([$tutorId]);
        $me = trim((string) ($reg['full_name'] ?? '')) ?: (string) SessionModel::get('username');
        PortalNotifications::notify($pdo, (int) $tu->fetchColumn(), 'submission', ($editing ? 'Resubmitted: ' : 'New submission: ') . $title,
            $me . ' sent work for you to grade.', 'tutor/submissions/open.php?id=' . $id);
        asg_back($self, $editing ? 'Resubmitted. Your tutor has been told.' : 'Submitted. Your tutor has been told and will grade it.');
    }
}

$v = fn(string $k, $fallback = '') => $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST[$k] ?? '') : (string) $fallback;
$curKind = $v('kind', $editing['kind'] ?? 'github');
$page_title = 'Submit work';
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">‹ My assignments</a>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo asg_e($error); ?></div><?php endif; ?>

<div class="card-student p-3 p-md-4 mb-3">
    <h5 class="mb-1"><?php echo $editing ? 'Resubmit: ' . asg_e($editing['title']) : 'Submit work'; ?></h5>
    <p class="small text-muted">Hand in any assignment your tutor gave you in class: a GitHub repository, a Google Drive file or a document.</p>
    <?php if ($editing && $editing['feedback']): ?>
        <div class="alert alert-warning small"><strong>Your tutor asked for changes:</strong><div style="white-space:pre-wrap"><?php echo asg_e($editing['feedback']); ?></div></div>
    <?php endif; ?>
    <?php if (!$tutors): ?>
        <div class="as-empty"><i class="fas fa-user-clock"></i>You have no tutor assigned yet. Once you do, you can submit work here.</div>
    <?php else: ?>
    <form method="POST" enctype="multipart/form-data" id="osForm" novalidate><?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="submit">
        <?php if ($editing): ?><input type="hidden" name="edit" value="<?php echo (int) $editing['id']; ?>"><?php endif; ?>

        <label class="form-label fw-semibold" for="osTitle">Assignment title</label>
        <input id="osTitle" name="title" class="form-control mb-3" maxlength="150" required placeholder="e.g. Week 3: Portfolio website"
               value="<?php echo asg_e($v('title', $editing['title'] ?? '')); ?>">

        <?php if (count($tutors) > 1): ?>
            <label class="form-label fw-semibold" for="osTutor">Tutor</label>
            <select id="osTutor" name="tutor_id" class="form-select mb-3">
                <?php foreach ($tutors as $tid => $tn): ?><option value="<?php echo (int) $tid; ?>" <?php echo (int) $v('tutor_id', $editing['tutor_id'] ?? 0) === (int) $tid ? 'selected' : ''; ?>><?php echo asg_e($tn); ?></option><?php endforeach; ?>
            </select>
        <?php else: ?>
            <input type="hidden" name="tutor_id" value="<?php echo (int) array_key_first($tutors); ?>">
            <p class="small text-muted mb-3">Goes to your tutor, <strong><?php echo asg_e(reset($tutors)); ?></strong>.</p>
        <?php endif; ?>

        <div class="form-label fw-semibold">What are you submitting?</div>
        <div class="d-flex flex-wrap gap-2 mb-2" role="radiogroup">
            <?php foreach (['github' => ['fab fa-github', 'GitHub link'], 'drive' => ['fab fa-google-drive', 'Google Drive link'], 'file' => ['fas fa-file-arrow-up', 'Document']] as $k => [$ic, $lbl]): ?>
                <input type="radio" class="btn-check" name="kind" id="k<?php echo $k; ?>" value="<?php echo $k; ?>" <?php echo $curKind === $k ? 'checked' : ''; ?>>
                <label class="btn btn-outline-dark btn-sm" for="k<?php echo $k; ?>"><i class="<?php echo $ic; ?> me-1"></i><?php echo $lbl; ?></label>
            <?php endforeach; ?>
        </div>

        <div id="linkBox" class="mb-3">
            <div class="input-group">
                <input type="url" name="link" id="osLink" class="form-control" maxlength="500" autocomplete="off" inputmode="url"
                       value="<?php echo asg_e($v('link', $editing && $editing['kind'] !== 'file' ? $editing['url'] : '')); ?>">
                <button type="button" class="btn btn-outline-secondary" id="osCheck">Check link</button>
            </div>
            <div class="form-text" id="linkHint"></div>
            <div id="linkResult" class="small mt-2" aria-live="polite"></div>
        </div>
        <div id="fileBox" class="mb-3">
            <input type="file" name="document" class="form-control" accept=".pdf,.docx,.xlsx,.pptx,.zip">
            <div class="form-text">PDF, Word, Excel, PowerPoint or ZIP, up to <?php echo (int) (ASG_FILE_MAX / 1048576); ?> MB.</div>
        </div>
        <?php if ($editing): ?><p class="small text-muted">Leave the link or document empty to keep what you sent before
            (<?php echo asg_e($editing['kind'] === 'file' ? $editing['original_name'] : $editing['url']); ?>).</p><?php endif; ?>

        <label class="form-label fw-semibold" for="osNote">Note to your tutor <span class="text-muted fw-normal">(optional)</span></label>
        <textarea id="osNote" name="note" class="form-control mb-3" rows="3" maxlength="2000" placeholder="Anything your tutor should know: how to run it, what is unfinished…"><?php echo asg_e($v('note', $editing['note'] ?? '')); ?></textarea>

        <button class="btn btn-brand" id="osSubmit"><i class="fas fa-paper-plane me-1"></i><?php echo $editing ? 'Resubmit' : 'Submit'; ?></button>
        <?php if ($editing): ?><a href="submit.php" class="btn btn-link">Cancel</a><?php endif; ?>
    </form>
    <?php endif; ?>
</div>

<div class="card-student p-3 p-md-4">
    <h5 class="mb-3">My submitted work</h5>
    <?php if (!$mine): ?><div class="as-empty"><i class="fas fa-inbox"></i>Nothing submitted yet.</div><?php endif; ?>
    <?php foreach ($mine as $m): $meta = json_decode((string) $m['link_meta'], true) ?: []; ?>
        <div class="border rounded p-3 mb-2">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div style="min-width:0">
                    <div class="fw-semibold"><?php echo asg_e($m['title']); ?></div>
                    <div class="small text-muted"><?php echo asg_e(os_kind_label($m['kind'])); ?> · sent <?php echo asg_e(date('D j M, H:i', strtotime($m['submitted_at']))); ?></div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <?php if ($m['status'] === 'graded'): ?><span class="asg-chip st-graded"><i class="fas fa-check"></i><?php echo asg_e(os_score_text($m)); ?></span>
                    <?php elseif ($m['status'] === 'returned'): ?><span class="asg-chip st-returned">Changes asked</span><a href="?edit=<?php echo (int) $m['id']; ?>" class="btn btn-sm btn-brand">Resubmit</a>
                    <?php else: ?><span class="asg-chip st-submitted">Waiting for grading</span><?php endif; ?>
                </div>
            </div>
            <div class="small mt-2 text-break">
                <?php if ($m['kind'] === 'file'): ?><a href="<?php echo asg_e(asg_files_url('open', (int) $m['id'], true)); ?>"><i class="fas fa-download me-1"></i><?php echo asg_e($m['original_name']); ?></a>
                <?php else: echo asg_link($m['url'], ($meta['name'] ?? '') ?: $m['url']); endif; ?>
            </div>
            <?php if ($m['status'] === 'graded' && $m['feedback']): ?><div class="small mt-2 bg-light rounded p-2" style="white-space:pre-wrap"><strong>Feedback:</strong> <?php echo asg_e($m['feedback']); ?></div><?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php
$content = ob_get_clean();
$extra_js = ($extra_js ?? '') . <<<'JS'
<script>
(function () {
    var form = document.getElementById('osForm'); if (!form) return;
    var link = document.getElementById('osLink'), out = document.getElementById('linkResult'), hint = document.getElementById('linkHint'),
        linkBox = document.getElementById('linkBox'), fileBox = document.getElementById('fileBox'), btn = document.getElementById('osCheck'), timer, last = '';
    var hints = { github: ['https://github.com/your-name/your-repo', 'The repository must be public.'],
                  drive: ['https://drive.google.com/file/d/…', 'In Drive: Share → General access → "Anyone with the link".'] };
    function kind() { var k = form.querySelector('input[name=kind]:checked'); return k ? k.value : 'github'; }
    function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
    function sync() {
        var k = kind(); linkBox.hidden = k === 'file'; fileBox.hidden = k !== 'file';
        if (hints[k]) { link.placeholder = hints[k][0]; hint.textContent = hints[k][1]; }
        last = ''; out.innerHTML = ''; if (k !== 'file' && link.value.trim()) check();
    }
    function check() {
        var v = link.value.trim(); if (!v || v === last) return; last = v;
        out.innerHTML = '<span class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Checking the link…</span>';
        var fd = new FormData(); fd.append('action', 'check'); fd.append('link', v); fd.append('csrf_token', form.querySelector('[name=csrf_token]').value);
        fetch('submit.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) {
            if (v !== link.value.trim()) return;
            if (!r.ok) { out.innerHTML = '<span class="text-danger"><i class="fas fa-circle-xmark me-1"></i>' + esc(r.error) + '</span>'; return; }
            if (r.kind !== kind()) { out.innerHTML = '<span class="text-danger"><i class="fas fa-circle-xmark me-1"></i>That is a ' + (r.kind === 'github' ? 'GitHub' : 'Google Drive') + ' link. Pick that option above.</span>'; return; }
            var m = r.meta || {}, html = '<div class="border rounded p-2 bg-light"><i class="' + (r.kind === 'github' ? 'fab fa-github' : 'fab fa-google-drive') + ' me-1"></i><strong>' + esc(m.name) + '</strong>';
            if (m.description) html += '<div class="text-muted">' + esc(m.description) + '</div>';
            var bits = []; if (m.language) bits.push(esc(m.language)); if (m.type === 'repo') bits.push('★ ' + (m.stars || 0)); if (m.type === 'profile') bits.push((m.repos || 0) + ' public repos');
            if (bits.length) html += '<div class="text-muted">' + bits.join(' · ') + '</div>';
            html += '<div class="' + (m.verified ? 'text-success' : 'text-warning') + ' mt-1"><i class="fas ' + (m.verified ? 'fa-circle-check' : 'fa-triangle-exclamation') + ' me-1"></i>' +
                    (m.verified ? 'Link works and is open to your tutor.' : 'Could not reach the site to confirm it right now. You can still submit.') + '</div></div>';
            out.innerHTML = html;
        }).catch(function () { last = ''; out.innerHTML = '<span class="text-warning">Could not check the link. You can still submit; it is checked again.</span>'; });
    }
    form.querySelectorAll('input[name=kind]').forEach(function (r) { r.addEventListener('change', sync); });
    link.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(check, 700); });
    link.addEventListener('paste', function () { setTimeout(check, 50); });
    btn.addEventListener('click', function () { last = ''; check(); });
    form.addEventListener('submit', function () { var b = document.getElementById('osSubmit'); b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Checking and sending…'; });
    sync();
})();
</script>
JS;
require __DIR__ . '/../../templates/students/layout.php';
