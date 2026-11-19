<?php
// public/tutor/timetable/index.php — the tutor's timetables: upload a PDF and/or build a weekly table (day, course, time),
// then share with all their students or chosen ones. Students and the tutor get an alert before each class.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/ClassCalendarService.php';
require_once __DIR__ . '/../../../app/v1/services/TimetableService.php';

Middleware::requireTutor();
$db = \Models\Connection::getInstance()->getConnection();
$me = (int) $_SESSION['user_id'];
$self = url('tutor/timetable/index.php');
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$page_title = 'Timetables';

if (!TimetableService::installed($db)) {
    $content = '<div class="alert alert-secondary">Timetables need the latest system update (migration 134). Ask the admin to run System Updates.</div>';
    require __DIR__ . '/../../templates/tutor/layout.php';
    exit;
}

$myStudents = cal_tutor_students($db, $me);
$myTz = function_exists('cal_user_tz') ? cal_user_tz($db, $me) : 'Africa/Nairobi';
$flash = $_SESSION['tt_flash'] ?? null; unset($_SESSION['tt_flash']);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'delete') {
        TimetableService::delete($db, (int) $_POST['id'], $me);
        $_SESSION['tt_flash'] = 'Timetable deleted.';
        header('Location: ' . $self); exit;
    } else {
        $id = (int) ($_POST['id'] ?? 0) ?: null;
        $title = trim((string) ($_POST['title'] ?? ''));
        $audience = ($_POST['audience'] ?? 'all') === 'selected' ? 'selected' : 'all';
        $students = array_values(array_filter(array_map('intval', (array) ($_POST['students'] ?? [])), fn($u) => isset($myStudents[$u])));
        $slots = [];
        foreach ((array) ($_POST['day'] ?? []) as $i => $d) {
            $d = (int) $d; $c = trim((string) ($_POST['course'][$i] ?? '')); $s = (string) ($_POST['start'][$i] ?? '');
            if (!$c && !$s) continue;
            if (!isset(TimetableService::DAYS[$d]) || $c === '' || !preg_match('/^\d{2}:\d{2}$/', $s)) { $error = 'Every row needs a day, a course and a start time.'; break; }
            $e = (string) ($_POST['end'][$i] ?? '');
            $slots[] = [$d, mb_substr($c, 0, 160), $s, preg_match('/^\d{2}:\d{2}$/', $e) ? $e : null, mb_substr(trim((string) ($_POST['venue'][$i] ?? '')), 0, 160)];
        }
        $pdf = null;
        if (!$error && $title === '') $error = 'Give the timetable a title.';
        if (!$error && $audience === 'selected' && !$students) $error = 'Pick at least one student, or share with all students.';
        if (!$error && !empty($_FILES['pdf']['name'])) {
            $up = upload_pdf($_FILES['pdf'], __DIR__ . '/../../uploads/timetables', 'uploads/timetables');
            if ($up['ok']) $pdf = $up['path']; else $error = $up['error'];
        }
        $existing = $id ? TimetableService::find($db, $id, $me) : null;
        if (!$error && $id && !$existing) $error = 'Timetable not found.';
        if (!$error && !$slots && !$pdf && !($existing['pdf_path'] ?? null)) $error = 'Upload a PDF or add at least one class to the table.';
        if (!$error) {
            $newId = TimetableService::save($db, $me, $id, [
                'title' => mb_substr($title, 0, 160), 'audience' => $audience, 'timezone' => $myTz,
                'alert_minutes' => max(0, min(240, (int) ($_POST['alert_minutes'] ?? 15))), 'pdf_path' => $pdf,
                'remove_pdf' => !$pdf && !empty($_POST['remove_pdf']),
            ], $slots, $students);
            TimetableService::announce($db, $newId, $me, !$id);
            PortalNotifications::flush();
            $_SESSION['tt_flash'] = $id ? 'Timetable updated. Your students were told.' : 'Timetable shared with your students.';
            header('Location: ' . $self); exit;
        }
    }
}

$list = TimetableService::forTutor($db, $me);
$edit = isset($_GET['edit']) ? TimetableService::find($db, (int) $_GET['edit'], $me) : null;
$showForm = $edit || isset($_GET['new']) || $error || !$list;
$f = $edit ?? ['id' => 0, 'title' => $_POST['title'] ?? '', 'audience' => $_POST['audience'] ?? 'all', 'alert_minutes' => 15, 'pdf_path' => null, 'slots' => [], 'students' => []];
$rows = $f['slots'] ?: [['day_of_week' => 1, 'course' => '', 'start_time' => '', 'end_time' => '', 'venue' => '']];
$courses = array_values(array_unique(array_column($myStudents, 'course')));

ob_start(); ?>
<style>
.tt-grid{width:100%;border-collapse:separate;border-spacing:0 6px}.tt-grid th{font-size:.75rem;text-transform:uppercase;color:#6b7a75;padding:0 6px}
.tt-grid td{padding:0 4px}.tt-grid .form-control,.tt-grid .form-select{font-size:.9rem}
.tt-table{width:100%;border-collapse:collapse;font-size:.92rem}.tt-table th,.tt-table td{border:1px solid #dfe7e3;padding:.5rem .6rem}
.tt-table th{background:#eef5f2;font-weight:600}.tt-day{font-weight:600;color:#1f6f50;white-space:nowrap}
.tt-stu{max-height:220px;overflow:auto;border:1px solid #dfe7e3;border-radius:10px;padding:.5rem .75rem}
@media(max-width:700px){.tt-grid thead{display:none}.tt-grid tr{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:12px;padding:8px;border:1px solid #dfe7e3;border-radius:10px}.tt-grid td{padding:0}}
</style>
<?php if ($flash): ?><div class="alert alert-success"><?= $h($flash) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>

<?php if ($showForm): ?>
<div class="card-tutor p-3 p-md-4 mb-4">
  <h5 class="mb-3"><?= $edit ? 'Edit timetable' : 'New timetable' ?></h5>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Title</label>
        <input class="form-control" name="title" required maxlength="160" value="<?= $h($f['title']) ?>" placeholder="e.g. November class timetable"></div>
      <div class="col-md-3"><label class="form-label">Alert before class</label>
        <select class="form-select" name="alert_minutes"><?php foreach ([0 => 'At start time', 10 => '10 minutes', 15 => '15 minutes', 30 => '30 minutes', 60 => '1 hour'] as $m => $l): ?>
          <option value="<?= $m ?>" <?= (int) $f['alert_minutes'] === $m ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="col-md-3"><label class="form-label">PDF (optional)</label>
        <input class="form-control" type="file" name="pdf" accept="application/pdf"></div>
      <?php if (!empty($f['pdf_path'])): ?><div class="col-12 small">Current PDF: <a href="<?= $h(url($f['pdf_path'])) ?>" target="_blank">open</a>
        <label class="ms-3"><input type="checkbox" name="remove_pdf" value="1"> remove it</label></div><?php endif; ?>
    </div>

    <h6 class="mt-4 mb-1">Weekly classes</h6>
    <p class="small text-muted mb-2">Add one row per class. Times are in your time zone (<?= $h($myTz) ?>). Leave the table empty if you only upload a PDF.</p>
    <datalist id="ttCourses"><?php foreach ($courses as $c): ?><option value="<?= $h($c) ?>"><?php endforeach; ?></datalist>
    <table class="tt-grid"><thead><tr><th>Day</th><th>Course</th><th>Start</th><th>End</th><th>Venue / link</th><th></th></tr></thead>
      <tbody id="ttRows"><?php foreach ($rows as $r): ?>
        <tr>
          <td><select class="form-select" name="day[]"><?php foreach (TimetableService::DAYS as $n => $d): ?><option value="<?= $n ?>" <?= (int) $r['day_of_week'] === $n ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select></td>
          <td><input class="form-control" name="course[]" list="ttCourses" value="<?= $h($r['course']) ?>" placeholder="Course"></td>
          <td><input class="form-control" type="time" name="start[]" value="<?= $h(substr((string) $r['start_time'], 0, 5)) ?>"></td>
          <td><input class="form-control" type="time" name="end[]" value="<?= $h(substr((string) $r['end_time'], 0, 5)) ?>"></td>
          <td><input class="form-control" name="venue[]" value="<?= $h($r['venue']) ?>" placeholder="Room or meeting link"></td>
          <td><button type="button" class="btn btn-sm btn-outline-danger tt-del" title="Remove row"><i class="fas fa-times"></i></button></td>
        </tr><?php endforeach; ?>
      </tbody></table>
    <button type="button" class="btn btn-sm btn-outline-success" id="ttAdd"><i class="fas fa-plus me-1"></i>Add class</button>

    <h6 class="mt-4 mb-2">Who sees it</h6>
    <div class="mb-2">
      <label class="me-3"><input type="radio" name="audience" value="all" <?= $f['audience'] !== 'selected' ? 'checked' : '' ?>> All my students (<?= count($myStudents) ?>)</label>
      <label><input type="radio" name="audience" value="selected" <?= $f['audience'] === 'selected' ? 'checked' : '' ?>> Chosen students</label>
    </div>
    <div id="ttPick" class="<?= $f['audience'] === 'selected' ? '' : 'd-none' ?>">
      <input class="form-control form-control-sm mb-2" id="ttFind" placeholder="Search a student">
      <div class="tt-stu"><?php foreach ($myStudents as $u => $s): ?>
        <label class="d-block tt-s"><input type="checkbox" name="students[]" value="<?= (int) $u ?>" <?= in_array((int) $u, $f['students'], true) ? 'checked' : '' ?>>
          <?= $h($s['full_name']) ?> <span class="text-muted small">· <?= $h($s['course']) ?></span></label>
      <?php endforeach; if (!$myStudents): ?><span class="text-muted small">You have no students yet.</span><?php endif; ?></div>
    </div>

    <div class="mt-4 d-flex gap-2">
      <button class="btn btn-success"><i class="fas fa-paper-plane me-1"></i><?= $edit ? 'Save changes' : 'Share timetable' ?></button>
      <?php if ($list): ?><a class="btn btn-outline-secondary" href="<?= $h($self) ?>">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>
<script>
(function(){
  var body=document.getElementById('ttRows');
  document.getElementById('ttAdd').onclick=function(){var tr=body.rows[body.rows.length-1].cloneNode(true);
    tr.querySelectorAll('input').forEach(function(i){i.value=''});body.appendChild(tr);};
  body.addEventListener('click',function(e){var b=e.target.closest('.tt-del');if(!b)return;
    if(body.rows.length>1)b.closest('tr').remove();else b.closest('tr').querySelectorAll('input').forEach(function(i){i.value=''});});
  document.querySelectorAll('input[name=audience]').forEach(function(r){r.onchange=function(){
    document.getElementById('ttPick').classList.toggle('d-none',this.value!=='selected'||!this.checked);};});
  document.getElementById('ttFind').oninput=function(){var q=this.value.toLowerCase();
    document.querySelectorAll('.tt-s').forEach(function(l){l.style.display=l.textContent.toLowerCase().indexOf(q)>-1?'':'none'});};
})();
</script>
<?php else: ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <p class="text-muted mb-0">Share a PDF or a weekly table. Your students and you get an alert before every class.</p>
  <a class="btn btn-success" href="<?= $h($self . '?new=1') ?>"><i class="fas fa-plus me-1"></i>New timetable</a>
</div>
<?php endif; ?>

<?php foreach ($list as $t):
  require __DIR__ . '/../../components/timetable/view.php'; ?>
  <div class="d-flex gap-2 mb-4 mt-n2">
    <a class="btn btn-sm btn-outline-success" href="<?= $h($self . '?edit=' . (int) $t['id']) ?>"><i class="fas fa-pen me-1"></i>Edit</a>
    <form method="post" onsubmit="return confirm('Delete this timetable? Students will no longer see it.')">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
      <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>Delete</button></form>
    <span class="small text-muted align-self-center">Shared with <?= $t['audience'] === 'all' ? 'all your students' : count($t['students']) . ' student' . (count($t['students']) === 1 ? '' : 's') ?></span>
  </div>
<?php endforeach; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
