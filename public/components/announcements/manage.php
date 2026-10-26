<?php
// Announcements page for senders: admin, superadmin and tutor (already authorised).
// Expects $annArea = the portal folder ('admin' | 'superadmin' | 'tutor'). Handles its own POSTs, then
// fills $content and $page_title. They also see what they received.
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/AnnouncementService.php';

$db = \Models\Connection::getInstance()->getConnection();
$sender = ann_sender($db);
$annSelf = url($annArea . '/announcements/index.php');
$flash = function (string $type, string $msg) {
    $_SESSION[$type === 'success' ? 'success_msg' : 'error_msg'] = $msg;   // printed by this page, whatever the layout
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $flash('error', 'Security check failed. Please refresh and try again.');
    } elseif (($_POST['ann_action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if (ann_can_delete($db, $sender, $id)) {
            ann_delete($db, $id);
            $flash('success', 'Announcement deleted for everyone.');
        }
    } else {
        try {
            $res = ann_create($db, $sender, $_POST, $_FILES['media_file'] ?? []);
        } catch (PDOException $ex) {
            $res = ['ok' => false, 'error' => 'The announcements tables are not installed yet. Run System Updates (migration 073).'];
        }
        $res['ok']
            ? $flash('success', 'Announcement sent to ' . $res['count'] . ' ' . ($res['count'] === 1 ? 'person' : 'people') . ' — it pops up on their portal, in their notifications and by email.')
            : $flash('error', 'Not sent: ' . $res['error']);
    }
    header('Location: ' . $annSelf);
    exit;
}

$audiences = ann_audiences_for($sender['role']);
$courses = ann_courses_for($db, $sender);
$sent = ann_sent($db, $sender);
$receives = true;
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$page_title = 'Announcements';
$own = $sender['role'] === 'tutor';   // tutors reach only their own students
$people = [];
foreach (['student', 'tutor', 'intern', 'admin'] as $kind) {
    if ($list = ann_people($db, $sender, $kind)) $people[$kind] = $list;
}

ob_start();
require __DIR__ . '/styles.php';
?>
<div class="<?php echo in_array($annArea, ['admin', 'superadmin'], true) ? 'container-fluid py-4' : ''; ?>">
    <?php foreach (['success_msg' => 'success', 'error_msg' => 'danger'] as $key => $cls): if (isset($_SESSION[$key])): ?>
        <div class="alert alert-<?php echo $cls; ?> alert-dismissible fade show"><?php echo $e($_SESSION[$key]); unset($_SESSION[$key]); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; endforeach; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="h4 mb-0"><i class="fas fa-bullhorn text-success me-2"></i>Announcements</h2>
        <button class="btn btn-success" data-bs-toggle="collapse" data-bs-target="#annCompose" aria-expanded="<?php echo $sent ? 'false' : 'true'; ?>"><i class="fas fa-plus me-1"></i>New announcement</button>
    </div>

    <div class="collapse <?php echo $sent && empty($_GET['new']) ? '' : 'show'; ?> mb-4" id="annCompose">
        <form method="POST" enctype="multipart/form-data" class="ann-card">
            <?php echo csrf_field(); ?>
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                    <input name="title" class="form-control" maxlength="150" required placeholder="e.g. Exams start on Monday"></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Send to <span class="text-danger">*</span></label>
                    <select name="audience" id="annAudience" class="form-select" required>
                        <?php foreach ($audiences as $k => $label): ?>
                            <option value="<?php echo $k; ?>" data-kind="<?php echo ANN_AUDIENCES[$k][1]; ?>" data-single="<?php echo ANN_AUDIENCES[$k][2] ? 1 : 0; ?>">
                                <?php echo $e($own && ANN_AUDIENCES[$k][1] === 'student' ? ($k === 'students' ? 'All my students' : 'One of my students') : $label); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="col-12"><label class="form-label fw-semibold">Short description <span class="text-danger">*</span></label>
                    <input name="summary" class="form-control" maxlength="300" required placeholder="One line shown in the notification and at the top of the pop-up"></div>
                <div class="col-12" id="annPersonWrap" style="display:none">
                    <label class="form-label fw-semibold">Who <span class="text-danger">*</span></label>
                    <input type="search" id="annPersonSearch" class="form-control mb-1" placeholder="Search by name, email or registration number…" autocomplete="off">
                    <select name="person_id" id="annPerson" class="form-select" size="6"></select>
                    <div class="form-text" id="annPersonHint"></div>
                </div>
                <div class="col-md-6" id="annCourseWrap"><label class="form-label fw-semibold">Only students of <span class="text-muted fw-normal">(optional)</span></label>
                    <select name="course_id" class="form-select">
                        <option value="">All courses</option>
                        <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo (int) $cid; ?>"><?php echo $e($ct); ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Disappears on <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="date" name="expires_on" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                    <div class="form-text">At the end of this day it is deleted for everyone, with no trace left. Leave empty to keep it.</div></div>
                <?php if (ann_has_extras($db)): ?>
                <div class="col-md-4"><label class="form-label fw-semibold">Priority</label>
                    <select name="priority" class="form-select"><option value="normal">Normal</option><option value="important">Important</option><option value="urgent">Urgent</option></select></div>
                <div class="col-md-4"><label class="form-label fw-semibold">Deadline <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="date" name="deadline" class="form-control" min="<?php echo date('Y-m-d'); ?>"></div>
                <div class="col-md-4 d-flex align-items-end"><label class="form-check mb-2"><input type="checkbox" name="needs_confirm" value="1" class="form-check-input">
                    <span class="form-check-label">Ask people to <strong>confirm attendance</strong></span></label></div>
                <?php endif; ?>
                <div class="col-12"><label class="form-label fw-semibold">Message</label>
                    <textarea name="message" rows="5" class="form-control" placeholder="Write the full announcement…"></textarea></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Video link <span class="text-muted fw-normal">(YouTube, TikTok or Facebook)</span></label>
                    <input type="url" name="media_link" class="form-control" placeholder="https://www.youtube.com/watch?v=…"></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Picture, music or video file</label>
                    <input type="file" name="media_file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif,audio/*,video/mp4,video/webm,video/quicktime">
                    <div class="form-text">Up to 40 MB. Plays inside the announcement.</div></div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <span class="ann-meta"><i class="fas fa-user me-1"></i>From <strong><?php echo $e($sender['name']); ?></strong> · date and time are added when you send</span>
                <button class="btn btn-success"><i class="fas fa-paper-plane me-1"></i>Send announcement</button>
            </div>
        </form>
    </div>

    <h3 class="h6 text-uppercase text-muted mb-2"><?php echo in_array($sender['role'], ['superadmin', 'admin'], true) ? 'All announcements' : 'Sent by you'; ?></h3>
    <?php if (!$sent): ?>
        <div class="ann-card text-muted small">Nothing sent yet.</div>
    <?php else: foreach ($sent as $a): ?>
        <div class="ann-card d-flex flex-wrap gap-3 justify-content-between align-items-start">
            <div style="min-width:0;flex:1">
                <div class="fw-semibold"><?php echo $e($a['title']); ?></div>
                <div class="small text-muted mb-1"><?php echo $e($a['summary']); ?></div>
                <div class="ann-meta"><?php echo $e($a['author_name']); ?> · <?php echo $e(date('j M Y, g:i a', strtotime($a['created_at']))); ?>
                    · <?php echo $e(ann_audience_label($a)); ?> · <?php echo (int) $a['recipient_count']; ?> recipients
                    <?php if (!empty($a['needs_confirm'])): ?> · <strong><?php echo (int) ($a['confirmed'] ?? 0); ?></strong> confirmed<?php endif; ?>
                    <?php if (($a['priority'] ?? 'normal') !== 'normal'): ?> · <span class="badge <?php echo $a['priority'] === 'urgent' ? 'bg-danger' : 'bg-warning text-dark'; ?>"><?php echo ucfirst($a['priority']); ?></span><?php endif; ?>
                    <?php if ($a['media_link'] || $a['media_path']): ?> · <i class="fas fa-photo-film"></i><?php endif; ?>
                    · <?php echo $a['expires_at'] ? 'disappears ' . $e(date('j M Y', strtotime($a['expires_at']))) : 'kept until deleted'; ?></div>
            </div>
            <?php if (ann_can_delete($db, $sender, (int) $a['id'])): ?>
            <form method="POST" class="m-0" onsubmit="return confirm('Delete this announcement for everyone? It cannot be undone.')"><?php echo csrf_field(); ?>
                <input type="hidden" name="ann_action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>

    <?php if ($receives): ?>
        <h3 class="h6 text-uppercase text-muted mb-2 mt-4" id="received">Received</h3>
        <?php require __DIR__ . '/feed.php'; ?>
    <?php endif; ?>
</div>
<script>
(function () {
    var people = <?php echo json_encode($people, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var aud = document.getElementById('annAudience'), course = document.getElementById('annCourseWrap'),
        wrap = document.getElementById('annPersonWrap'), search = document.getElementById('annPersonSearch'),
        sel = document.getElementById('annPerson'), hint = document.getElementById('annPersonHint');
    function opt() { return aud.options[aud.selectedIndex]; }
    function fill() {
        var list = people[opt().dataset.kind] || [], q = search.value.trim().toLowerCase(), keep = sel.value, shown = 0;
        sel.innerHTML = '';
        list.forEach(function (p) {
            var text = p[1] + (p[2] ? ' — ' + p[2] : '');
            if (q && text.toLowerCase().indexOf(q) === -1) return;
            if (shown++ >= 200) return;
            var o = new Option(text, p[0]); if (String(p[0]) === keep) o.selected = true; sel.add(o);
        });
        hint.textContent = list.length ? (shown > 200 ? 'Showing the first 200 — type to narrow it down.' : shown + ' of ' + list.length) : 'Nobody to pick yet.';
    }
    function sync() {
        var single = opt().dataset.single === '1';
        wrap.style.display = single ? '' : 'none';
        sel.required = single;
        course.style.display = aud.value === 'students' ? '' : 'none';
        if (single) { search.value = ''; fill(); }
    }
    aud.addEventListener('change', sync);
    search.addEventListener('input', fill);
    sync();
})();
</script>
<?php
$content = ob_get_clean();
