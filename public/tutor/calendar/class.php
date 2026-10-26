<?php
// One class (tutor): details, students, attendance (with history), make-up classes, materials, cancel.
require __DIR__ . '/_boot.php';

$id = (int) ($_GET['id'] ?? 0);
$c = cal_tutor_class($db, $me, $id);   // only the tutor's own classes
if (!$c) {
    SessionModel::setFlash('error', 'Class not found.');
    header('Location: index.php');
    exit;
}
$self = 'class.php?id=' . $id;
if (cal_post_ok()) {
    switch ($_POST['act'] ?? '') {
        case 'attendance':
            $marks = [];
            foreach ((array) ($_POST['att'] ?? []) as $sid => $status) $marks[(int) $sid] = ['status' => $status, 'note' => $_POST['note'][$sid] ?? ''];
            if (!empty($_POST['all_present'])) foreach (cal_class_students($db, $id) as $s) $marks[(int) $s['student_id']] = ['status' => 'present', 'note' => $marks[(int) $s['student_id']]['note'] ?? ''];
            $r = cal_mark($db, $me, $id, $marks);
            SessionModel::setFlash($r['ok'] ? 'success' : 'error', cal_e($r['ok'] ? 'Attendance saved (' . $r['count'] . ' change' . ($r['count'] === 1 ? '' : 's') . ').' : $r['error']));
            break;
        case 'cancel':
            $r = cal_cancel($db, $me, $id, ($_POST['scope'] ?? 'one') === 'future' ? 'future' : 'one', (string) ($_POST['reason'] ?? ''));
            SessionModel::setFlash($r['ok'] ? 'success' : 'error', cal_e($r['ok'] ? $r['count'] . ' class' . ($r['count'] === 1 ? '' : 'es') . ' cancelled. Students were told.' : $r['error']));
            break;
        case 'material':
            $r = cal_material_add($db, $me, $id, (string) ($_POST['title'] ?? ''), (string) ($_POST['link'] ?? ''), $_FILES['file'] ?? []);
            SessionModel::setFlash($r['ok'] ? 'success' : 'error', cal_e($r['ok'] ? 'Material added. Students see it after the class.' : $r['error']));
            break;
        case 'material_delete':
            $st = $db->prepare('SELECT file_path FROM class_materials WHERE id = ? AND class_id = ?');
            $st->execute([(int) $_POST['material_id'], $id]);
            if (($p = $st->fetchColumn()) !== false) {
                if ($abs = cal_material_abs($p ?: null)) @unlink($abs);
                $db->prepare('DELETE FROM class_materials WHERE id = ?')->execute([(int) $_POST['material_id']]);
                cal_audit($db, $id, null, 'material_deleted');
            }
            break;
        case 'add_skipped':
            $r = cal_add_skipped($db, $me, (int) $c['series_id']);
            SessionModel::setFlash($r['ok'] ? 'success' : 'error', cal_e($r['ok'] ? $r['count'] . ' class' . ($r['count'] === 1 ? '' : 'es') . ' added back.' : $r['error']));
            break;
    }
    header('Location: ' . $self);
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: ' . $self);
    exit;
}
$c = cal_tutor_class($db, $me, $id);
$students = cal_class_students($db, $id);
$materials = cal_materials($db, $id);
$past = cal_is_past($c);
$started = strtotime($c['start_utc'] . ' UTC') <= time();
$series = null;
if ($c['series_id']) {
    $st = $db->prepare('SELECT * FROM class_series WHERE id = ?');
    $st->execute([(int) $c['series_id']]);
    $series = $st->fetch(PDO::FETCH_ASSOC);
}
$history = $db->prepare("SELECT h.*, (SELECT r.full_name FROM registrations r WHERE r.user_id = h.student_id ORDER BY r.id DESC LIMIT 1) AS name,
                                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))), ''), u.username) AS by_name
                         FROM class_attendance_history h LEFT JOIN users u ON u.id = h.changed_by LEFT JOIN user_profiles p ON p.user_id = u.id
                         WHERE h.class_id = ? ORDER BY h.id DESC LIMIT 50");
$history->execute([$id]);
$history = $history->fetchAll(PDO::FETCH_ASSOC);
$page_title = $c['title'];
ob_start();
require __DIR__ . '/../../components/classes/styles.php';
?>
<a href="index.php?d=<?php echo cal_fmt($c['start_utc'], $tz, 'Y-m-d'); ?>" class="btn btn-link btn-sm ps-0">← Calendar</a>
<div class="row g-3">
<div class="col-lg-7">
    <div class="cl-card">
        <div class="d-flex flex-wrap gap-2 align-items-start mb-2">
            <div class="flex-grow-1"><h1 class="h4 mb-1"><?php echo cal_e($c['title']); ?></h1>
                <div class="cl-muted"><?php echo cal_e(cal_fmt_range($c['start_utc'], $c['end_utc'], $tz)); ?></div></div>
            <span class="cl-pill <?php echo cal_e($c['mode']); ?>"><?php echo CAL_MODES[$c['mode']]; ?></span>
            <?php if ($c['status'] !== 'scheduled'): ?><span class="cl-pill <?php echo cal_e($c['status']); ?>"><?php echo ucfirst($c['status']); ?></span><?php endif; ?>
            <?php if ($past): ?><span class="cl-pill excused"><i class="fas fa-lock me-1"></i>Past · time locked</span><?php endif; ?>
        </div>
        <?php if ($c['status'] === 'cancelled'): ?><div class="cl-cancel mb-2">Cancelled<?php echo $c['cancel_reason'] ? ': ' . cal_e($c['cancel_reason']) : ''; ?></div><?php endif; ?>
        <?php if ($c['status'] === 'moved' && $c['moved_from_utc']): ?><div class="cl-moved mb-2">Moved from <?php echo cal_e(cal_fmt($c['moved_from_utc'], $tz, 'D j M H:i')); ?> to <?php echo cal_e(cal_fmt($c['start_utc'], $tz, 'D j M H:i')); ?><?php echo $c['moved_reason'] ? ' — ' . cal_e($c['moved_reason']) : ''; ?></div><?php endif; ?>
        <?php if ($c['makeup_for_class_id']): ?><div class="cl-muted mb-2"><i class="fas fa-rotate me-1"></i>Make-up for <a href="class.php?id=<?php echo (int) $c['makeup_for_class_id']; ?>">a missed class</a></div><?php endif; ?>
        <dl class="row small mb-0">
            <?php if ($c['course_title']): ?><dt class="col-4">Course</dt><dd class="col-8"><?php echo cal_e($c['course_title']); ?></dd><?php endif; ?>
            <?php if ($c['topic']): ?><dt class="col-4">Topic</dt><dd class="col-8"><?php echo cal_e($c['topic']); ?><?php echo $c['subtopics'] ? ' · ' . cal_e($c['subtopics']) : ''; ?></dd><?php endif; ?>
            <?php if ($c['campus']): ?><dt class="col-4">Campus / room</dt><dd class="col-8"><?php echo cal_e($c['campus'] . ($c['room'] ? ' · ' . $c['room'] : '')); ?></dd><?php endif; ?>
            <?php if ($c['meeting_link']): ?><dt class="col-4">Meeting link</dt><dd class="col-8 text-break"><a href="<?php echo cal_e($c['meeting_link']); ?>" target="_blank" rel="noopener"><?php echo cal_e($c['meeting_link']); ?></a></dd><?php endif; ?>
            <?php if ($c['notes']): ?><dt class="col-4">Notes</dt><dd class="col-8" style="white-space:pre-line"><?php echo cal_e($c['notes']); ?></dd><?php endif; ?>
        </dl>
        <?php if ($c['status'] !== 'cancelled'): ?>
        <div class="d-flex gap-2 flex-wrap mt-3">
            <a class="btn btn-outline-success btn-sm" href="edit.php?id=<?php echo $id; ?>"><i class="fas fa-pen me-1"></i><?php echo $past ? 'Edit notes' : 'Edit'; ?></a>
            <?php if (!$past): ?><button class="btn btn-outline-danger btn-sm" data-bs-toggle="collapse" data-bs-target="#cancelBox"><i class="fas fa-ban me-1"></i>Cancel class</button><?php endif; ?>
        </div>
        <?php if (!$past): ?>
        <form method="POST" class="collapse border rounded-3 p-3 mt-2" id="cancelBox"><?php echo csrf_field(); ?><input type="hidden" name="act" value="cancel">
            <label class="form-label small fw-semibold">Short reason (students see it)</label><input name="reason" class="form-control form-control-sm mb-2" maxlength="255" placeholder="e.g. Public holiday">
            <?php if ($c['series_id']): ?><label class="form-check small"><input type="radio" name="scope" value="one" class="form-check-input" checked> This class only</label>
                <label class="form-check small mb-2"><input type="radio" name="scope" value="future" class="form-check-input"> This and all future classes</label><?php endif; ?>
            <button class="btn btn-sm btn-danger" onclick="return confirm('Cancel? Your students will be told.')">Cancel and tell students</button>
        </form>
        <?php endif; endif; ?>
        <?php if ($series && $series['skipped_dates']): $sk = explode(',', $series['skipped_dates']); ?>
            <form method="POST" class="mt-3 p-2 rounded-3 bg-light small d-flex flex-wrap gap-2 align-items-center"><?php echo csrf_field(); ?><input type="hidden" name="act" value="add_skipped">
                <span class="flex-grow-1"><i class="fas fa-umbrella-beach me-1 text-success"></i><?php echo count($sk); ?> class<?php echo count($sk) === 1 ? ' was' : 'es were'; ?> skipped for holidays (<?php echo cal_e(implode(', ', array_map(fn($d) => date('j M', strtotime($d)), array_slice($sk, 0, 5)))); ?><?php echo count($sk) > 5 ? '…' : ''; ?>).</span>
                <button class="btn btn-sm btn-outline-success">Add them back</button></form>
        <?php endif; ?>
    </div>

    <form method="POST" class="cl-card"><?php echo csrf_field(); ?><input type="hidden" name="act" value="attendance">
        <div class="cl-head"><h3>Students &amp; attendance</h3>
            <?php if ($started && $c['status'] !== 'cancelled' && $students): ?><button name="all_present" value="1" class="btn btn-sm btn-outline-success">Mark all present</button><?php endif; ?></div>
        <?php if (!$students): ?><div class="cl-muted">No students in this class.</div><?php endif; ?>
        <?php foreach ($students as $s): $sid = (int) $s['student_id']; ?>
            <div class="border-bottom py-2">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="fw-semibold flex-grow-1"><?php echo cal_e($s['full_name']); ?> <span class="cl-pill <?php echo cal_e($s['eff_mode']); ?> ms-1"><?php echo CAL_MODES[$s['eff_mode']]; ?></span>
                        <?php if ($s['self_checkin_at']): ?><span class="cl-pill online ms-1">Checked in <?php echo cal_e(cal_fmt($s['self_checkin_at'], $tz)); ?></span><?php endif; ?></span>
                    <?php if ($started && $c['status'] !== 'cancelled'): ?>
                        <div class="btn-group btn-group-sm" role="group">
                            <?php foreach (CAL_ATT as $k => $l): ?>
                                <input type="radio" class="btn-check" name="att[<?php echo $sid; ?>]" id="a<?php echo $sid . $k; ?>" value="<?php echo $k; ?>" <?php echo $s['att_status'] === $k ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-<?php echo ['present' => 'success', 'late' => 'warning', 'absent' => 'danger', 'excused' => 'secondary'][$k]; ?>" for="a<?php echo $sid . $k; ?>"><?php echo $l; ?></label>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($s['att_status']): ?><span class="cl-pill <?php echo cal_e($s['att_status']); ?>"><?php echo CAL_ATT[$s['att_status']]; ?></span><?php endif; ?>
                </div>
                <?php if ($started && $c['status'] !== 'cancelled'): ?>
                <div class="d-flex gap-2 mt-1">
                    <input name="note[<?php echo $sid; ?>]" value="<?php echo cal_e($s['att_note']); ?>" class="form-control form-control-sm" placeholder="Note (optional)" maxlength="255">
                    <?php if ($s['att_status'] === 'absent'): ?><a class="btn btn-sm btn-outline-primary text-nowrap" href="edit.php?makeup_for=<?php echo $id; ?>&student=<?php echo $sid; ?>"><i class="fas fa-rotate me-1"></i>Make-up class</a><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if ($started && $c['status'] !== 'cancelled' && $students): ?><button class="btn btn-success mt-3">Save attendance</button>
        <?php elseif (!$started): ?><div class="cl-muted mt-2">You can mark attendance once the class starts.</div><?php endif; ?>
    </form>
</div>
<div class="col-lg-5">
    <div class="cl-card">
        <h3 class="mb-2">Notes &amp; materials</h3>
        <div class="cl-muted mb-2">Students see these after the class.</div>
        <?php foreach ($materials as $m): ?>
            <div class="d-flex gap-2 align-items-center py-1">
                <i class="fas <?php echo $m['file_path'] ? 'fa-paperclip' : 'fa-link'; ?> text-success"></i>
                <a class="flex-grow-1 text-truncate" target="_blank" rel="noopener" href="<?php echo cal_e($m['file_path'] ? url('classes/file.php?id=' . (int) $m['id']) : $m['link']); ?>"><?php echo cal_e($m['title']); ?></a>
                <form method="POST" class="m-0"><?php echo csrf_field(); ?><input type="hidden" name="act" value="material_delete"><input type="hidden" name="material_id" value="<?php echo (int) $m['id']; ?>">
                    <button class="btn btn-sm btn-link text-danger p-0" onclick="return confirm('Remove?')"><i class="fas fa-trash"></i></button></form>
            </div>
        <?php endforeach; ?>
        <form method="POST" enctype="multipart/form-data" class="mt-2"><?php echo csrf_field(); ?><input type="hidden" name="act" value="material">
            <input name="title" class="form-control form-control-sm mb-2" placeholder="Title (e.g. Slides)" maxlength="200">
            <input type="url" name="link" class="form-control form-control-sm mb-2" placeholder="Link (optional)">
            <input type="file" name="file" class="form-control form-control-sm mb-2">
            <button class="btn btn-sm btn-outline-success">Add material</button></form>
    </div>
    <?php if ($history): ?>
    <div class="cl-card">
        <h3 class="mb-2">Attendance changes</h3>
        <ul class="list-unstyled small mb-0" style="max-height:260px;overflow:auto">
            <?php foreach ($history as $h): ?><li class="mb-1"><span class="cl-muted"><?php echo cal_e(cal_fmt($h['changed_at'], $tz, 'j M H:i')); ?></span> · <?php echo cal_e($h['by_name']); ?>:
                <?php echo cal_e($h['name']); ?> <?php echo cal_e($h['old_status'] ? CAL_ATT[$h['old_status']] . ' → ' : ''); ?><strong><?php echo cal_e(CAL_ATT[$h['new_status']] ?? $h['new_status']); ?></strong></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
