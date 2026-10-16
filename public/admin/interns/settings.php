<?php
// Admin / HR: attendance rules (work hours, late rule, work days, location) and the progress weights.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/InternService.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$self = url('admin/interns/settings.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
    } else {
        $time = fn($k, $d) => preg_match('/^\d{2}:\d{2}$/', (string) ($_POST[$k] ?? '')) ? $_POST[$k] : $d;
        $days = array_values(array_intersect(array_map('intval', (array) ($_POST['work_days'] ?? [])), [1, 2, 3, 4, 5, 6, 7]));
        $w = array_map(fn($k) => max(0, min(100, (int) ($_POST[$k] ?? 0))), ['weight_attendance' => 'weight_attendance', 'weight_tasks' => 'weight_tasks', 'weight_training' => 'weight_training', 'weight_assessments' => 'weight_assessments']);
        if (array_sum($w) !== 100) {
            $_SESSION['error_msg'] = 'The four progress weights must add up to 100 (now ' . array_sum($w) . ').';
        } else {
            try {
                in_setting_save($db, [
                    'attendance_enabled' => !empty($_POST['attendance_enabled']) ? '1' : '0',
                    'save_location' => !empty($_POST['save_location']) ? '1' : '0',
                    'work_start' => $time('work_start', '08:00'), 'work_end' => $time('work_end', '17:00'),
                    'late_after_minutes' => (string) max(0, min(240, (int) ($_POST['late_after_minutes'] ?? 10))),
                    'daily_hours' => (string) max(1, min(16, (float) ($_POST['daily_hours'] ?? 9))),
                    'work_days' => implode(',', $days ?: [1, 2, 3, 4, 5]),
                ] + $w);
                in_audit($db, null, 'settings_saved');
                $_SESSION['success_msg'] = 'Settings saved.';
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = 'Could not save. Run System Updates (migration 075) first.';
            }
        }
    }
    header('Location: ' . $self);
    exit;
}

$s = in_settings($db);
$days = array_map('intval', explode(',', $s['work_days']));
$page_title = 'Intern rules & settings';
$breadcrumbs = [['label' => 'Interns', 'url' => url('admin/interns/index.php')], ['label' => 'Settings', 'active' => true]];
$inTab = 'settings';
ob_start();
?>
<div class="container-fluid py-3">
<?php require __DIR__ . '/_nav.php'; ?>
<form method="POST" class="row g-3">
    <?php echo csrf_field(); ?>
    <div class="col-lg-6"><div class="in-card h-100">
        <h5>Attendance rules</h5>
        <label class="form-check form-switch mb-3"><input type="checkbox" name="attendance_enabled" value="1" class="form-check-input" <?php echo $s['attendance_enabled'] === '1' ? 'checked' : ''; ?>>
            <span class="form-check-label">Clock in / clock out is on</span></label>
        <div class="row g-3">
            <div class="col-6"><label class="form-label">Work starts</label><input type="time" name="work_start" value="<?php echo in_e($s['work_start']); ?>" class="form-control"></div>
            <div class="col-6"><label class="form-label">Work ends</label><input type="time" name="work_end" value="<?php echo in_e($s['work_end']); ?>" class="form-control"></div>
            <div class="col-6"><label class="form-label">Late after (minutes)</label><input type="number" name="late_after_minutes" value="<?php echo (int) $s['late_after_minutes']; ?>" min="0" max="240" class="form-control">
                <div class="form-text">Clocking in later than start + this is "Late".</div></div>
            <div class="col-6"><label class="form-label">Hours in a work day</label><input type="number" step="0.5" name="daily_hours" value="<?php echo in_e($s['daily_hours']); ?>" min="1" max="16" class="form-control"></div>
            <div class="col-12"><label class="form-label">Work days</label><div class="d-flex flex-wrap gap-3">
                <?php foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $n => $l): ?>
                    <label class="form-check"><input type="checkbox" name="work_days[]" value="<?php echo $n; ?>" class="form-check-input" <?php echo in_array($n, $days, true) ? 'checked' : ''; ?>> <?php echo $l; ?></label>
                <?php endforeach; ?></div></div>
            <div class="col-12"><label class="form-check form-switch"><input type="checkbox" name="save_location" value="1" class="form-check-input" <?php echo $s['save_location'] === '1' ? 'checked' : ''; ?>>
                <span class="form-check-label">Save the location of each clock-in (the phone asks the intern first)</span></label></div>
        </div>
    </div></div>
    <div class="col-lg-6"><div class="in-card h-100">
        <h5>Progress weights</h5>
        <p class="small text-muted">Overall progress is made from these four parts. They must add up to 100.</p>
        <?php foreach (['weight_attendance' => 'Attendance', 'weight_tasks' => 'Tasks', 'weight_training' => 'Training (learning)', 'weight_assessments' => 'Assessments'] as $k => $l): ?>
            <div class="d-flex align-items-center gap-2 mb-2"><label class="flex-grow-1"><?php echo $l; ?></label>
                <input type="number" name="<?php echo $k; ?>" value="<?php echo (int) $s[$k]; ?>" min="0" max="100" class="form-control" style="width:100px" data-w><span>%</span></div>
        <?php endforeach; ?>
        <div class="small">Total: <strong id="wTotal"></strong></div>
    </div></div>
    <div class="col-12 text-end"><button class="btn btn-success">Save settings</button></div>
</form>
</div>
<script>
(function () {
    var inputs = document.querySelectorAll('[data-w]'), out = document.getElementById('wTotal');
    function sum() { var t = 0; inputs.forEach(function (i) { t += parseInt(i.value || 0, 10); }); out.textContent = t + '%'; out.style.color = t === 100 ? '#2f7a14' : '#b42318'; }
    inputs.forEach(function (i) { i.addEventListener('input', sum); }); sum();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
