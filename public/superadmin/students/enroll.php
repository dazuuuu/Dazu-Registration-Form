<?php
// Superadmin: enrol a student. Same form and fields as the SmS "Add student" screen: it creates the portal account,
// the SmS fee record, the receipt and the sale, and emails the sign-in details. Optionally assigns a tutor.
require_once __DIR__ . '/../sms/_init.php';
require_once __DIR__ . '/../../../app/v1/models/PayrollModel.php';
sms_require_tables($pdo);

use Models\PayrollModel;

$id = 0;
$student = null;
$courses = $sms->coursePricingCatalog();
$payroll = new PayrollModel($pdo);
$tutors = $payroll->isInstalled() ? array_values(array_filter($payroll->listTutorNames(), fn($t) => $t['is_active'])) : [];
$error = '';
$done = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please try again.';
        $student = $data;
    } else {
        $result = $sms->createStudent($data);
        if (!empty($result['ok'])) {
            $tutorNote = '';
            $tutorId = (int)($data['tutor_id'] ?? 0);
            if ($tutorId > 0 && $payroll->isInstalled()) {
                $st = $pdo->prepare('SELECT user_id FROM registrations WHERE id = ?');
                $st->execute([(int)$result['registration_id']]);
                $uid = (int)$st->fetchColumn();
                if ($uid > 0 && $payroll->assignStudents($tutorId, [$uid])) $tutorNote = ' Tutor assigned.';
            }
            $creds = $sms->lastStudentCredentials();
            $mail = $sms->lastEmailResult();
            $done = ['name' => trim($data['name']), 'email' => trim($data['email']), 'reg_no' => $result['reg_no'] ?? '', 'password' => $creds['temp_password'] ?? '', 'id' => $result['id'], 'tutor_note' => $tutorNote, 'mail_ok' => !empty($mail['ok']), 'mail_msg' => $mail['message'] ?? '', 'reg_id' => (int)$result['registration_id']];
        } else {
            $error = $result['error'] ?? 'Save failed.';
            $student = $data;
        }
    }
}

$selectedCourse = null;
if (!empty($student['course_id'])) {
    foreach ($courses as $c) {
        if ((int) $c['id'] === (int) $student['course_id']) { $selectedCourse = $c; break; }
    }
}

$page_title = 'Enrol a student';
$sa_subtitle = 'Creates the portal account, fee record and receipt, and emails the sign-in details.';

ob_start();
?>
<?php if ($done): ?>
<div class="card border-success mb-3"><div class="card-body">
    <h5 class="text-success mb-1"><i class="fas fa-circle-check me-2"></i><?= htmlspecialchars($done['name']) ?> is enrolled</h5>
    <?php if ($done['mail_ok']): ?>
    <p class="text-muted small mb-3">Sign-in details were emailed to <?= htmlspecialchars($done['email']) ?>.<?= $done['tutor_note'] ?></p>
    <?php else: ?>
    <div class="alert alert-warning py-2 small"><strong>The sign-in email did not send</strong><?= $done['mail_msg'] !== '' ? ' (' . htmlspecialchars($done['mail_msg']) . ')' : '' ?>. The student is enrolled: give them the details below, or use "Send credentials" on their record.<?= $done['tutor_note'] ?></div>
    <?php endif; ?>
    <div class="row g-2 small">
        <div class="col-sm-4"><div class="text-muted">Registration no.</div><strong><?= htmlspecialchars($done['reg_no']) ?></strong></div>
        <div class="col-sm-4"><div class="text-muted">Sign in with (email)</div><strong class="text-break"><?= htmlspecialchars($done['email']) ?></strong></div>
        <div class="col-sm-4"><div class="text-muted">Temporary password</div><strong class="user-select-all"><?= htmlspecialchars($done['password']) ?></strong></div>
    </div>
    <div class="mt-3 d-flex gap-2 flex-wrap">
        <a href="enroll.php" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i>Enrol another</a>
        <a href="view.php?id=<?= (int)$done['reg_id'] ?>" class="btn btn-outline-secondary">Open student record</a>
        <a href="index.php" class="btn btn-outline-secondary">All students</a>
    </div>
</div></div>
<?php else: ?>
<div class="card shadow-sm"><div class="card-body">
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <p class="text-muted small">On enrolment: a portal account is created in the main student system, login credentials are emailed (temp password — must change on first login), and a receipt is sent. Parent details are optional.</p>
  <form method="post" class="row g-3" id="smsStudentForm">
    <?php echo csrf_field(); ?>
    <div class="col-md-6"><label class="form-label">Name *</label><input name="name" required class="form-control" value="<?= htmlspecialchars($student['name'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Email *</label><input type="email" name="email" required class="form-control" value="<?= htmlspecialchars($student['email'] ?? '') ?>" <?= $id ? '' : 'required' ?>></div>
    <div class="col-md-4"><label class="form-label">Phone *</label><input name="phone" required class="form-control" value="<?= htmlspecialchars($student['phone'] ?? '') ?>"></div>
    <div class="col-md-8"><label class="form-label">Course *</label>
      <select name="course_id" id="courseSelect" class="form-select" required>
        <option value="">— Select course —</option>
        <?php foreach ($courses as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= (int)($student['course_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?> — <?= sms_money($c['fee']) ?><?php if (!empty($c['duration_summary'])): ?> · <?= htmlspecialchars($c['duration_summary']) ?><?php endif; ?></option>
        <?php endforeach; ?>
      </select>
      <div id="courseStudySummary" class="form-text text-muted mt-1" style="display:none;"></div>
    </div>
    <div class="col-md-3">
      <label class="form-label">Enrollment <span id="durationUnitLabel" class="text-muted small">(months)</span></label>
      <input type="number" min="1" step="1" name="duration_value" id="durationValue" class="form-control" value="<?= htmlspecialchars($student['duration_value'] ?? ($selectedCourse['duration_value'] ?? '')) ?>">
      <input type="hidden" name="duration_unit" id="durationUnit" value="<?= htmlspecialchars($student['duration_unit'] ?? ($selectedCourse['duration_unit'] ?? 'months')) ?>">
      <input type="hidden" name="base_duration_value" id="baseDurationValue" value="<?= htmlspecialchars($student['base_duration_value'] ?? ($selectedCourse['duration_value'] ?? '')) ?>">
      <input type="hidden" name="base_course_fee" id="baseCourseFee" value="<?= htmlspecialchars($student['base_course_fee'] ?? ($selectedCourse['fee'] ?? '')) ?>">
      <input type="hidden" name="duration" id="durationLabel" value="<?= htmlspecialchars($student['duration'] ?? '') ?>">
    </div>
    <div class="col-md-3"><label class="form-label">Start date</label><input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($student['start_date'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">Cost (KES)</label><input type="number" step="0.01" min="0" name="cost" id="costField" class="form-control" value="<?= htmlspecialchars($student['cost'] ?? ($selectedCourse['fee'] ?? '0')) ?>" readonly></div>
    <div class="col-md-3"><label class="form-label">Paid (KES)</label><input type="number" step="0.01" min="0" name="paid" class="form-control" value="<?= htmlspecialchars($student['paid'] ?? '0') ?>"></div>
    <div class="col-md-4"><label class="form-label">Parent phone <span class="text-muted small">(optional)</span></label><input name="parent_phone" class="form-control" value="<?= htmlspecialchars($student['parent_phone'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Parent email <span class="text-muted small">(optional)</span></label><input type="email" name="parent_email" class="form-control" value="<?= htmlspecialchars($student['parent_email'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Status</label>
      <select name="status" class="form-select">
        <?php foreach (['active','completed','withdrawn'] as $st): ?>
        <option value="<?= $st ?>" <?= ($student['status'] ?? 'active') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($student['notes'] ?? '') ?></textarea></div>
    <div class="col-md-4"><label class="form-label">Tutor <span class="text-muted small">(optional)</span></label>
      <select name="tutor_id" class="form-select"><option value="">No tutor yet</option>
        <?php foreach ($tutors as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)($student['tutor_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['full_name']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-12"><button class="btn btn-success"><i class="fas fa-user-plus me-1"></i>Enrol student</button>
      <a href="<?= url('superadmin/students/index.php') ?>" class="btn btn-link">Cancel</a></div>
  </form>
</div></div>
<script>
(function () {
  const catalog = <?= json_encode($courses, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const byId = Object.fromEntries(catalog.map(c => [String(c.id), c]));
  const courseSelect = document.getElementById('courseSelect');
  const durationValue = document.getElementById('durationValue');
  const durationUnit = document.getElementById('durationUnit');
  const durationUnitLabel = document.getElementById('durationUnitLabel');
  const baseDurationValue = document.getElementById('baseDurationValue');
  const baseCourseFee = document.getElementById('baseCourseFee');
  const costField = document.getElementById('costField');
  const durationLabel = document.getElementById('durationLabel');

  const courseStudySummary = document.getElementById('courseStudySummary');

  function unitLabel(unit) {
    if (unit === 'hours') return 'hours';
    if (unit === 'weeks') return 'weeks';
    if (unit === 'days') return 'days';
    return 'months';
  }

  function studySummaryText(c) {
    if (!c) return '';
    if (c.duration_summary) return c.duration_summary;
    const bits = [];
    if (c.hours_of_study > 0) bits.push(c.hours_of_study + ' hour' + (c.hours_of_study === 1 ? '' : 's'));
    if (c.days_of_study > 0) bits.push(c.days_of_study + ' day' + (c.days_of_study === 1 ? '' : 's'));
    if (c.weeks_of_study > 0) bits.push(c.weeks_of_study + ' week' + (c.weeks_of_study === 1 ? '' : 's'));
    if (c.duration_months > 0) bits.push(c.duration_months + ' month' + (c.duration_months === 1 ? '' : 's'));
    if (c.study_schedule) bits.push(c.study_schedule);
    return bits.join(' · ');
  }

  function formatDuration(unit, value) {
    const v = parseInt(value, 10);
    if (!v) return '';
    if (unit === 'hours') return v + ' hour' + (v === 1 ? '' : 's');
    if (unit === 'days') return v + ' day' + (v === 1 ? '' : 's');
    if (unit === 'weeks') return v + ' week' + (v === 1 ? '' : 's');
    return v + ' month' + (v === 1 ? '' : 's');
  }

  function recalcCost() {
    const baseVal = parseFloat(baseDurationValue.value) || 0;
    const baseFee = parseFloat(baseCourseFee.value) || 0;
    const val = parseFloat(durationValue.value) || 0;
    if (baseVal > 0 && val > 0) {
      costField.value = (Math.round(baseFee * (val / baseVal) * 100) / 100).toFixed(2);
    }
    const c = byId[String(courseSelect.value)];
    const summary = studySummaryText(c);
    const primary = formatDuration(durationUnit.value, val);
    durationLabel.value = (val === parseFloat(c?.duration_value) && summary) ? summary : primary;
  }

  function applyCourse(id) {
    const c = byId[String(id)];
    if (!c) {
      if (courseStudySummary) {
        courseStudySummary.style.display = 'none';
        courseStudySummary.textContent = '';
      }
      return;
    }
    durationUnit.value = c.duration_unit;
    durationUnitLabel.textContent = '(' + unitLabel(c.duration_unit) + ')';
    durationValue.value = c.duration_value;
    baseDurationValue.value = c.duration_value;
    baseCourseFee.value = c.fee;
    costField.value = parseFloat(c.fee).toFixed(2);
    durationLabel.value = c.duration_label;
    const summary = studySummaryText(c);
    if (courseStudySummary) {
      if (summary) {
        courseStudySummary.style.display = 'block';
        courseStudySummary.innerHTML = '<strong>Course study period:</strong> ' + summary;
      } else {
        courseStudySummary.style.display = 'none';
        courseStudySummary.textContent = '';
      }
    }
  }

  courseSelect.addEventListener('change', function () { applyCourse(this.value); });
  durationValue.addEventListener('input', recalcCost);

  if (courseSelect.value) {
    applyCourse(courseSelect.value);
    const c = byId[String(courseSelect.value)];
    if (c) {
      if (!baseDurationValue.value) baseDurationValue.value = c.duration_value;
      if (!baseCourseFee.value) baseCourseFee.value = c.fee;
      recalcCost();
    }
  }
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/superadmin/layout.php';
