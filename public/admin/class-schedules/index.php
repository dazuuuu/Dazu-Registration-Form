<?php
require_once __DIR__ . '/../../../app/v1/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/models/ClassScheduleModel.php';
require_once __DIR__ . '/../../../app/v1/helpers/class_schedule_defaults.php';

use Models\ClassScheduleModel;

if (!isLoggedIn() || (int) ($_SESSION['role_id'] ?? 99) > 2) {
    redirect(url('admin/portal.php'));
}

$model = new ClassScheduleModel($pdo);
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed.';
    } elseif (isset($_POST['restore_defaults'])) {
        $model->restoreDefaults();
        $success = 'Default Mon–Fri schedules restored.';
    } elseif (isset($_POST['delete_id'])) {
        $model->delete((int) $_POST['delete_id']);
        $success = 'Schedule entry removed.';
    } elseif (isset($_POST['save_entry'])) {
        $payload = [
            'study_mode' => $_POST['study_mode'] ?? 'online',
            'day_label' => $_POST['day_label'] ?? '',
            'time_label' => $_POST['time_label'] ?? '',
            'notes' => $_POST['notes'] ?? '',
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
            'is_active' => !empty($_POST['is_active']),
        ];
        $editId = (int) ($_POST['edit_id'] ?? 0);
        $result = $editId > 0 ? $model->update($editId, $payload) : $model->create($payload);
        if ($result['ok']) {
            $success = $editId > 0 ? 'Schedule updated.' : 'Schedule entry added.';
        } else {
            $error = $result['error'] ?? 'Could not save schedule.';
        }
    }
}

try {
    $model->seedDefaultsIfEmpty();
    $schedules = $model->getAllGrouped(false);
} catch (Throwable $e) {
    $schedules = ['online' => [], 'physical' => []];
    $error = 'Run Admin → System Updates (038 and 039) to enable class schedules.';
}

$dayPresets = class_schedule_day_presets();
$onlineTimePresets = class_schedule_online_time_presets();

$page_title = 'Class Schedules';
$breadcrumbs = [
    ['label' => 'System', 'url' => url('admin/registrations/index.php')],
    ['label' => 'Class Schedules', 'active' => true],
];

ob_start();
?>
<div class="alert alert-info small mb-4">
  <strong>Default schedule:</strong> Classes run <strong>Monday – Friday</strong>. Online sessions are typically
  <strong>6:00–8:00 AM</strong>, <strong>9:00–11:00 AM</strong>, and similar slots through the day.
  Use <em>Restore defaults</em> to reset, or edit individual entries below.
</div>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Add / edit schedule entry</h5>
        <form method="post" onsubmit="return confirm('Replace all schedules with the default Mon–Fri slots?');">
          <?php echo csrf_field(); ?>
          <button type="submit" name="restore_defaults" value="1" class="btn btn-sm btn-outline-secondary">Restore defaults</button>
        </form>
      </div>
      <div class="card-body">
        <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <form method="post" id="scheduleForm">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="save_entry" value="1">
          <input type="hidden" name="edit_id" id="edit_id" value="0">
          <div class="mb-3">
            <label class="form-label">Study mode</label>
            <select name="study_mode" id="study_mode" class="form-select" required>
              <option value="online">Online classes</option>
              <option value="physical">Physical classes</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Days</label>
            <select name="day_label" id="day_label" class="form-select" required>
              <?php foreach ($dayPresets as $day): ?>
                <option value="<?php echo htmlspecialchars($day); ?>"><?php echo htmlspecialchars($day); ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" class="form-control mt-2" id="day_label_custom" placeholder="Or type custom days…" aria-label="Custom days">
          </div>
          <div class="mb-3">
            <label class="form-label">Time</label>
            <select id="time_preset" class="form-select mb-2">
              <option value="">— Pick a preset or type below —</option>
              <?php foreach ($onlineTimePresets as $slot): ?>
                <option value="<?php echo htmlspecialchars($slot); ?>"><?php echo htmlspecialchars($slot); ?></option>
              <?php endforeach; ?>
              <option value="9:00 AM – 1:00 PM">9:00 AM – 1:00 PM (Saturday)</option>
            </select>
            <input type="text" name="time_label" id="time_label" class="form-control" required placeholder="e.g. 6:00 AM – 8:00 AM">
          </div>
          <div class="mb-3">
            <label class="form-label">Notes (optional)</label>
            <input type="text" name="notes" id="notes" class="form-control" placeholder="e.g. Live Zoom sessions">
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label">Sort order</label>
              <input type="number" name="sort_order" id="sort_order" class="form-control" value="0">
            </div>
            <div class="col-6 d-flex align-items-end">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
                <label class="form-check-label" for="is_active">Active</label>
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary mt-3">Save entry</button>
          <button type="button" class="btn btn-link mt-3" id="resetForm">Clear form</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <?php foreach (['online' => 'Online classes', 'physical' => 'Physical classes'] as $mode => $heading): ?>
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><?php echo htmlspecialchars($heading); ?></h5>
        <span class="badge bg-secondary"><?php echo count($schedules[$mode] ?? []); ?> entries</span>
      </div>
      <div class="list-group list-group-flush">
        <?php if (empty($schedules[$mode])): ?>
          <div class="list-group-item text-muted small">No schedule entries yet.</div>
        <?php else: foreach ($schedules[$mode] as $row): ?>
          <div class="list-group-item d-flex justify-content-between align-items-start gap-3">
            <div>
              <strong><?php echo htmlspecialchars($row['day_label']); ?></strong>
              <div class="small text-muted"><?php echo htmlspecialchars($row['time_label']); ?></div>
              <?php if (!empty($row['notes'])): ?><div class="small"><?php echo htmlspecialchars($row['notes']); ?></div><?php endif; ?>
              <?php if (empty($row['is_active'])): ?><span class="badge bg-secondary">Inactive</span><?php endif; ?>
            </div>
            <div class="d-flex gap-1">
              <button type="button" class="btn btn-sm btn-outline-primary edit-schedule"
                      data-id="<?php echo (int) $row['id']; ?>"
                      data-mode="<?php echo htmlspecialchars($row['study_mode']); ?>"
                      data-day="<?php echo htmlspecialchars($row['day_label']); ?>"
                      data-time="<?php echo htmlspecialchars($row['time_label']); ?>"
                      data-notes="<?php echo htmlspecialchars($row['notes'] ?? ''); ?>"
                      data-sort="<?php echo (int) $row['sort_order']; ?>"
                      data-active="<?php echo (int) $row['is_active']; ?>">Edit</button>
              <form method="post" onsubmit="return confirm('Remove this entry?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="delete_id" value="<?php echo (int) $row['id']; ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">&times;</button>
              </form>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<script>
(function () {
  var daySelect = document.getElementById('day_label');
  var dayCustom = document.getElementById('day_label_custom');
  var timePreset = document.getElementById('time_preset');
  var timeInput = document.getElementById('time_label');
  var form = document.getElementById('scheduleForm');

  dayCustom.addEventListener('input', function () {
    if (dayCustom.value.trim() !== '') {
      daySelect.name = '';
      dayCustom.name = 'day_label';
    } else {
      daySelect.name = 'day_label';
      dayCustom.name = '';
    }
  });

  timePreset.addEventListener('change', function () {
    if (timePreset.value) timeInput.value = timePreset.value;
  });

  document.getElementById('resetForm').addEventListener('click', function () {
    document.getElementById('edit_id').value = '0';
    form.reset();
    daySelect.name = 'day_label';
    dayCustom.name = '';
    dayCustom.value = '';
  });

  document.querySelectorAll('.edit-schedule').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('edit_id').value = btn.getAttribute('data-id');
      document.getElementById('study_mode').value = btn.getAttribute('data-mode');
      var day = btn.getAttribute('data-day') || '';
      var matched = false;
      for (var i = 0; i < daySelect.options.length; i++) {
        if (daySelect.options[i].value === day) { matched = true; break; }
      }
      if (matched) {
        daySelect.value = day;
        dayCustom.value = '';
        daySelect.name = 'day_label';
        dayCustom.name = '';
      } else {
        dayCustom.value = day;
        daySelect.name = '';
        dayCustom.name = 'day_label';
      }
      timeInput.value = btn.getAttribute('data-time') || '';
      document.getElementById('notes').value = btn.getAttribute('data-notes') || '';
      document.getElementById('sort_order').value = btn.getAttribute('data-sort') || '0';
      document.getElementById('is_active').checked = btn.getAttribute('data-active') === '1';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });
})();
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/admin/layout.php';
