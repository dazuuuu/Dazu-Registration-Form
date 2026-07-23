<?php
require_once __DIR__ . '/../_init.php';
sms_require_tables($pdo);
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$student = $id ? $sms->getStudent($id) : null;
$courses = $sms->coursePricingCatalog();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    $result = $id ? $sms->updateStudent($id, $data) : $sms->createStudent($data);
    if (!empty($result['ok'])) {
        sms_flash('success', $id ? 'Student updated.' : 'Student created. Account and receipt emails sent.');
        sms_redirect('admin/sms/students/view.php?id=' . ($id ?: $result['id']));
    }
    $error = $result['error'] ?? 'Save failed.';
    $student = array_merge($student ?? [], $data);
}

$selectedCourse = null;
if (!empty($student['course_id'])) {
    foreach ($courses as $c) {
        if ((int) $c['id'] === (int) $student['course_id']) {
            $selectedCourse = $c;
            break;
        }
    }
}

$page_title = $id ? 'Edit student' : 'Add student';
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('admin/dashboard.php#sms-alerts')],
    ['label' => 'Students', 'url' => url('admin/sms/students/index.php')],
    ['label' => $page_title, 'active' => true],
];

ob_start();
?>
<div class="card shadow-sm"><div class="card-body">
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if (!$id): ?><p class="text-muted small">On create: a portal account is created in the main student system, login credentials are emailed (temp password — must change on first login), and a receipt is sent. Parent details are optional.</p><?php endif; ?>
  <form method="post" class="row g-3" id="smsStudentForm">
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
    <div class="col-12"><button class="btn btn-success"><?= $id ? 'Update' : 'Create' ?> student</button>
      <a href="<?= url('admin/sms/students/index.php') ?>" class="btn btn-link">Cancel</a></div>
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
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../templates/admin/layout.php';
