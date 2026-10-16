<?php
// Create a class (or a repeating series), or edit one. Times are entered in the tutor's own time zone.
// ?date=Y-m-d prefills the date · ?id= edits · ?makeup_for=<class id>&student=<user id> offers a make-up class.
require __DIR__ . '/_boot.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$class = $id ? cal_tutor_class($db, $me, $id) : null;
if ($id && !$class) {
    SessionModel::setFlash('error', 'Class not found.');
    header('Location: index.php');
    exit;
}
$past = $class && cal_is_past($class);
$students = cal_tutor_students($db, $me);
$courses = [];
foreach ($students as $s) $courses[(int) $s['course_id']] = $s['course'];
$makeup = null;
if (!$class && ($mf = (int) ($_GET['makeup_for'] ?? $_POST['makeup_for'] ?? 0))) $makeup = cal_tutor_class($db, $me, $mf);
$warnings = [];
$error = null;
$in = $_POST;

if (cal_post_ok()) {
    $force = !empty($_POST['force']);
    if ($class) {
        $r = cal_update($db, $me, $id, $_POST, ($_POST['scope'] ?? 'one') === 'future' ? 'future' : 'one', $force);
        if ($r['ok']) {
            SessionModel::setFlash('success', !empty($r['locked']) ? 'Notes saved. This class is in the past, so its date and time are locked.' : 'Saved — ' . $r['count'] . ' class' . ($r['count'] === 1 ? '' : 'es') . ' updated. Affected students were told.');
            header('Location: class.php?id=' . $id);
            exit;
        }
    } else {
        $c = cal_clean($db, $me, $_POST);
        $r = $c['ok'] ? cal_create($db, $me, $c['d'], $force) : $c;
        if ($r['ok']) {
            $msg = count($r['ids']) === 1 ? 'Class added. Your students were told.' : count($r['ids']) . ' classes added in one series. Each student got one message.';
            if ($r['skipped']) $msg .= ' ' . count($r['skipped']) . ' class' . (count($r['skipped']) === 1 ? ' was' : 'es were') . ' skipped for holidays — you can add them back on the class page.';
            SessionModel::setFlash('success', cal_e($msg));
            header('Location: class.php?id=' . $r['ids'][0]);
            exit;
        }
    }
    $warnings = $r['clash'] ?? [];
    $error = $r['error'] ?? null;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: index.php');
    exit;
}

// Values to show: what was posted, else the class, else defaults.
$cur = $class ? cal_class_students($db, $id) : [];
$v = fn($k, $d = '') => cal_e($in[$k] ?? $d);
$cs = $class ? cal_from_utc($class['start_utc'], $tz) : null;
$defaults = $class ? ['title' => $class['title'], 'course_id' => $class['course_id'], 'topic' => $class['topic'], 'subtopics' => $class['subtopics'], 'notes' => $class['notes'],
    'mode' => $class['mode'], 'campus' => $class['campus'], 'room' => $class['room'], 'meeting_link' => $class['meeting_link'], 'date' => $cs->format('Y-m-d'),
    'start_time' => $cs->format('H:i'), 'end_time' => cal_fmt($class['end_utc'], $tz, 'H:i'), 'reminders_on' => $class['reminders_on']]
  : ($makeup ? ['title' => 'Make-up: ' . $makeup['title'], 'course_id' => $makeup['course_id'], 'topic' => $makeup['topic'], 'mode' => $makeup['mode'], 'campus' => $makeup['campus'],
    'room' => $makeup['room'], 'meeting_link' => $makeup['meeting_link'], 'date' => (new DateTime('tomorrow', new DateTimeZone($tz)))->format('Y-m-d'),
    'start_time' => cal_fmt($makeup['start_utc'], $tz), 'end_time' => cal_fmt($makeup['end_utc'], $tz)]
  : ['date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date'] ?? '')) ? $_GET['date'] : (new DateTime('tomorrow', new DateTimeZone($tz)))->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '12:00', 'mode' => 'physical']);
$in += $defaults;
$picked = array_map('intval', (array) ($in['students'] ?? ($class ? array_column($cur, 'student_id') : ($makeup && ($s = (int) ($_GET['student'] ?? 0)) ? [$s] : []))));
$overrides = (array) ($in['student_mode'] ?? array_column(array_filter($cur, fn($r) => $r['mode_override']), 'mode_override', 'student_id'));
$campuses = cal_list_setting($db, 'campuses');
$rooms = cal_list_setting($db, 'rooms');
$byCourse = [];
foreach ($students as $uid => $s) $byCourse[$s['course']][$uid] = $s;
$page_title = $class ? 'Edit class' : ($makeup ? 'Make-up class' : 'New class');
ob_start();
require __DIR__ . '/../../components/classes/styles.php';
?>
<a href="<?php echo $class ? 'class.php?id=' . $id : 'index.php'; ?>" class="btn btn-link btn-sm ps-0">← Back</a>
<h1 class="h4 mb-3"><?php echo cal_e($page_title); ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?php echo cal_e($error); ?></div><?php endif; ?>

<?php if ($past): ?>
    <form method="POST" class="cl-card" style="max-width:760px"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>">
        <div class="alert alert-secondary"><i class="fas fa-lock me-1"></i>This class has ended, so it cannot be deleted and its date and time are locked. You can still update the notes, mark attendance and add materials.</div>
        <label class="form-label fw-semibold">Notes for students</label>
        <textarea name="notes" class="form-control mb-3" rows="5"><?php echo $v('notes'); ?></textarea>
        <button class="btn btn-success">Save notes</button>
    </form>
<?php else: ?>
<form method="POST" class="row g-3"><?php echo csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo $id; ?>"><?php if ($makeup): ?><input type="hidden" name="makeup_for" value="<?php echo (int) $makeup['id']; ?>"><?php endif; ?>
    <?php if ($warnings): ?>
        <div class="col-12"><div class="alert alert-warning mb-0"><strong>Time clash:</strong><ul class="mb-2"><?php foreach ($warnings as $w): ?><li><?php echo cal_e($w); ?></li><?php endforeach; ?></ul>
            <label class="form-check"><input type="checkbox" name="force" value="1" class="form-check-input" required> I understand — save anyway</label></div></div>
    <?php endif; ?>
    <div class="col-lg-7">
        <div class="cl-card">
            <h3 class="mb-3">Class</h3>
            <div class="row g-2">
                <div class="col-md-8"><label class="form-label small fw-semibold">Title *</label><input name="title" class="form-control" value="<?php echo $v('title'); ?>" placeholder="e.g. Python: Loops" required maxlength="200"></div>
                <div class="col-md-4"><label class="form-label small fw-semibold">Course</label><select name="course_id" class="form-select"><option value="">—</option>
                    <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo $cid; ?>" <?php echo (int) ($in['course_id'] ?? 0) === $cid ? 'selected' : ''; ?>><?php echo cal_e($ct); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label small fw-semibold">Topic</label><input name="topic" class="form-control" value="<?php echo $v('topic'); ?>" maxlength="200"></div>
                <div class="col-md-6"><label class="form-label small fw-semibold">Subtopics</label><input name="subtopics" class="form-control" value="<?php echo $v('subtopics'); ?>" placeholder="for, while, break"></div>
                <div class="col-12"><label class="form-label small fw-semibold">Notes <span class="text-muted fw-normal">(students see them after the class)</span></label><textarea name="notes" class="form-control" rows="2"><?php echo $v('notes'); ?></textarea></div>
            </div>
        </div>
        <div class="cl-card">
            <h3 class="mb-1">When</h3>
            <div class="cl-muted mb-3">In your time zone: <strong><?php echo cal_e($tz); ?></strong>. Each student sees it in their own time zone.</div>
            <div class="row g-2">
                <div class="col-md-4"><label class="form-label small fw-semibold">Date *</label><input type="date" name="date" class="form-control" value="<?php echo $v('date'); ?>" required></div>
                <div class="col-md-4"><label class="form-label small fw-semibold">Start *</label><input type="time" name="start_time" class="form-control" value="<?php echo $v('start_time'); ?>" required></div>
                <div class="col-md-4"><label class="form-label small fw-semibold">End *</label><input type="time" name="end_time" class="form-control" value="<?php echo $v('end_time'); ?>" required></div>
            </div>
            <?php if (!$class && !$makeup): ?>
            <div class="row g-2 mt-1">
                <div class="col-md-4"><label class="form-label small fw-semibold">Repeat</label><select name="repeat" id="clRepeat" class="form-select">
                    <?php foreach (['none' => 'Does not repeat', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo ($in['repeat'] ?? 'none') === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select></div>
                <div class="col-md-8" data-rep="weekly"><label class="form-label small fw-semibold">On</label><div class="d-flex flex-wrap gap-2">
                    <?php foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $n => $l): ?>
                        <label class="form-check"><input type="checkbox" name="repeat_days[]" value="<?php echo $n; ?>" class="form-check-input" <?php echo in_array((string) $n, (array) ($in['repeat_days'] ?? []), true) ? 'checked' : ''; ?>> <?php echo $l; ?></label><?php endforeach; ?></div></div>
                <div class="col-md-4" data-rep="daily weekly monthly"><label class="form-label small fw-semibold">Ends</label><select name="ends" id="clEnds" class="form-select">
                    <?php foreach (['date' => 'On a date', 'month' => 'After 1 month', 'year' => 'After 1 year'] as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo ($in['ends'] ?? 'date') === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4" data-rep="daily weekly monthly" data-ends="date"><label class="form-label small fw-semibold">Ends on</label><input type="date" name="ends_on" class="form-control" value="<?php echo $v('ends_on'); ?>"></div>
                <div class="col-12 cl-muted" data-rep="daily weekly monthly">Holidays and term breaks set by the office are skipped automatically.</div>
            </div>
            <?php endif; ?>
            <label class="form-check mt-3"><input type="checkbox" name="reminders_on" value="1" class="form-check-input" <?php echo !isset($in['title']) || !empty($in['reminders_on']) ? 'checked' : ''; ?>> Send reminders (24 hours and 1 hour before)</label>
        </div>
        <?php if ($class && $class['series_id']): ?>
        <div class="cl-card">
            <h3 class="mb-2">This class is part of a series</h3>
            <label class="form-check"><input type="radio" name="scope" value="one" class="form-check-input" <?php echo ($in['scope'] ?? 'one') === 'one' ? 'checked' : ''; ?>> This class only</label>
            <label class="form-check"><input type="radio" name="scope" value="future" class="form-check-input" <?php echo ($in['scope'] ?? '') === 'future' ? 'checked' : ''; ?>> This and all future classes</label>
            <div class="cl-muted mt-1">A time change moves each future class by the same amount.</div>
        </div>
        <?php endif; ?>
        <?php if ($class): ?>
        <div class="cl-card"><label class="form-label fw-semibold">Reason for the change <span class="text-muted fw-normal">(optional — students see it)</span></label>
            <input name="reason" class="form-control" maxlength="255" value="<?php echo $v('reason'); ?>" placeholder="e.g. The lab is booked"></div>
        <?php endif; ?>
    </div>
    <div class="col-lg-5">
        <div class="cl-card">
            <h3 class="mb-3">Mode &amp; place</h3>
            <div class="d-flex gap-3 mb-3"><?php foreach (CAL_MODES as $k => $l): ?><label class="form-check"><input type="radio" name="mode" value="<?php echo $k; ?>" class="form-check-input" data-mode <?php echo ($in['mode'] ?? 'physical') === $k ? 'checked' : ''; ?>> <?php echo $l; ?></label><?php endforeach; ?></div>
            <div class="row g-2">
                <div class="col-6" data-need="place"><label class="form-label small fw-semibold">Campus</label><select name="campus" class="form-select"><option value="">—</option>
                    <?php foreach ($campuses as $cp): ?><option <?php echo ($in['campus'] ?? '') === $cp ? 'selected' : ''; ?>><?php echo cal_e($cp); ?></option><?php endforeach; ?></select></div>
                <div class="col-6" data-need="place"><label class="form-label small fw-semibold">Room</label><input name="room" class="form-control" list="clRooms" value="<?php echo $v('room'); ?>" maxlength="80">
                    <datalist id="clRooms"><?php foreach ($rooms as $rm): ?><option value="<?php echo cal_e($rm); ?>"><?php endforeach; ?></datalist></div>
                <div class="col-12" data-need="link"><label class="form-label small fw-semibold">Meeting link</label><input type="url" name="meeting_link" class="form-control" value="<?php echo $v('meeting_link'); ?>" placeholder="https://meet.google.com/…">
                    <div class="form-text">Only online and hybrid students ever get this link.</div></div>
            </div>
        </div>
        <div class="cl-card">
            <h3 class="mb-2">Who</h3>
            <?php if (!$class && !$makeup): ?>
            <label class="form-label small fw-semibold">A whole course class <span class="text-muted fw-normal">(students who join it later get the future classes too)</span></label>
            <select name="group_course_id" class="form-select form-select-sm mb-2"><option value="">—</option>
                <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo $cid; ?>" <?php echo (int) ($in['group_course_id'] ?? 0) === $cid ? 'selected' : ''; ?>><?php echo cal_e($ct); ?></option><?php endforeach; ?></select>
            <div class="cl-muted mb-2">…or choose students:</div>
            <?php endif; ?>
            <?php if (!$students): ?><div class="cl-muted">You have no students yet.</div><?php endif; ?>
            <div style="max-height:360px;overflow:auto">
            <?php foreach ($byCourse as $course => $list): ?>
                <div class="small fw-semibold text-muted mt-2"><?php echo cal_e($course); ?></div>
                <?php foreach ($list as $uid => $s): ?>
                    <div class="d-flex align-items-center gap-2 py-1">
                        <label class="form-check flex-grow-1 mb-0"><input type="checkbox" name="students[]" value="<?php echo $uid; ?>" class="form-check-input" <?php echo in_array($uid, $picked, true) ? 'checked' : ''; ?>>
                            <?php echo cal_e($s['full_name']); ?> <span class="cl-muted">· <?php echo $s['study_mode'] === 'physical' ? 'physical' : 'online'; ?> · joined <?php echo date('j M Y', strtotime($s['join_date'])); ?></span></label>
                        <select name="student_mode[<?php echo $uid; ?>]" class="form-select form-select-sm" style="width:auto" title="This student's mode">
                            <option value="">Class mode</option><?php foreach (CAL_MODES as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo ($overrides[$uid] ?? '') === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </div>
            <div class="cl-muted mt-2">Mixed group? Give each student their own mode — a physical student never gets the link.</div>
        </div>
        <button class="btn btn-success btn-lg w-100"><?php echo $class ? 'Save changes' : ($makeup ? 'Offer make-up class' : 'Create class'); ?></button>
    </div>
</form>
<script>
(function () {
    function sync() {
        var m = (document.querySelector('[data-mode]:checked') || {}).value || 'physical';
        var per = Array.prototype.map.call(document.querySelectorAll('select[name^="student_mode"]'), function (s) { return s.value; });
        var place = m !== 'online' || per.indexOf('physical') > -1 || per.indexOf('hybrid') > -1;
        var link = m !== 'physical' || per.indexOf('online') > -1 || per.indexOf('hybrid') > -1;
        document.querySelectorAll('[data-need=place]').forEach(function (e) { e.style.display = place ? '' : 'none'; });
        document.querySelectorAll('[data-need=link]').forEach(function (e) { e.style.display = link ? '' : 'none'; });
        var rep = document.getElementById('clRepeat'), ends = document.getElementById('clEnds');
        if (rep) document.querySelectorAll('[data-rep]').forEach(function (e) {
            var on = e.dataset.rep.split(' ').indexOf(rep.value) > -1 && (!e.dataset.ends || (ends && ends.value === e.dataset.ends));
            e.style.display = on ? '' : 'none';
        });
    }
    document.addEventListener('change', sync); sync();
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
