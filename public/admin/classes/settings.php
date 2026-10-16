<?php
// Admin: Class Calendar settings — holidays and term breaks, reminders, "Join class" opening time, campuses and rooms,
// student self check-in, and whether Late counts as attended.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/ClassCalendarService.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['error_msg'] = 'Security check failed. Please refresh and try again.';
    } else {
        try {
            switch ($_POST['act'] ?? '') {
                case 'settings':
                    $list = fn($k) => implode(',', array_slice(array_values(array_filter(array_map(fn($x) => mb_substr(trim($x), 0, 60), explode(',', (string) ($_POST[$k] ?? ''))))), 0, 40));
                    $st = $db->prepare('INSERT INTO class_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
                    foreach (['join_open_minutes' => (string) max(0, min(120, (int) ($_POST['join_open_minutes'] ?? 10))), 'reminder_24h' => !empty($_POST['reminder_24h']) ? '1' : '0',
                              'reminder_1h' => !empty($_POST['reminder_1h']) ? '1' : '0', 'self_checkin' => !empty($_POST['self_checkin']) ? '1' : '0',
                              'late_counts' => !empty($_POST['late_counts']) ? '1' : '0', 'campuses' => $list('campuses') ?: 'Nairobi,Thika', 'rooms' => $list('rooms')] as $k => $v) $st->execute([$k, $v]);
                    cal_audit($db, null, null, 'class_settings_saved');
                    $_SESSION['success_msg'] = 'Settings saved.';
                    break;
                case 'holiday':
                    $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
                    $s = (string) ($_POST['start_date'] ?? '');
                    $e = (string) ($_POST['end_date'] ?? '') ?: $s;
                    if ($name === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $e) || $e < $s) {
                        $_SESSION['error_msg'] = 'Enter a name and valid dates.';
                        break;
                    }
                    $db->prepare('INSERT INTO holidays (name, start_date, end_date) VALUES (?, ?, ?)')->execute([$name, $s, $e]);
                    cal_audit($db, null, null, 'holiday_added', $name . ' ' . $s . '–' . $e);
                    $_SESSION['success_msg'] = 'Added. New recurring classes skip these days.';
                    break;
                case 'holiday_delete':
                    $db->prepare('DELETE FROM holidays WHERE id = ?')->execute([(int) $_POST['id']]);
                    cal_audit($db, null, null, 'holiday_deleted', '#' . (int) $_POST['id']);
                    break;
            }
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = 'Could not save. Run System Updates (migration 076) first.';
        }
    }
    header('Location: settings.php');
    exit;
}
$s = cal_settings($db);
$holidays = cal_holidays($db);
$page_title = 'Class settings';
$breadcrumbs = [['label' => 'Classes', 'url' => url('admin/classes/index.php')], ['label' => 'Settings', 'active' => true]];
ob_start();
?>
<div class="container-fluid py-3">
<?php foreach (['success_msg' => 'success', 'error_msg' => 'danger'] as $k => $c): if (isset($_SESSION[$k])): ?><div class="alert alert-<?php echo $c; ?>"><?php echo cal_e($_SESSION[$k]); unset($_SESSION[$k]); ?></div><?php endif; endforeach; ?>
<a href="index.php" class="btn btn-link btn-sm ps-0">← Classes</a>
<div class="row g-3">
    <div class="col-lg-6">
        <form method="POST" class="card card-body border-0 shadow-sm rounded-4"><?php echo csrf_field(); ?><input type="hidden" name="act" value="settings">
            <h5>Classes</h5>
            <label class="form-label">"Join class" opens this many minutes before the start</label><input type="number" name="join_open_minutes" min="0" max="120" value="<?php echo (int) $s['join_open_minutes']; ?>" class="form-control mb-3" style="max-width:140px">
            <label class="form-check"><input type="checkbox" name="reminder_24h" value="1" class="form-check-input" <?php echo $s['reminder_24h'] === '1' ? 'checked' : ''; ?>> Reminder 24 hours before</label>
            <label class="form-check mb-3"><input type="checkbox" name="reminder_1h" value="1" class="form-check-input" <?php echo $s['reminder_1h'] === '1' ? 'checked' : ''; ?>> Reminder 1 hour before</label>
            <label class="form-label">Campuses <span class="text-muted small">(comma-separated)</span></label><input name="campuses" value="<?php echo cal_e($s['campuses']); ?>" class="form-control mb-2">
            <label class="form-label">Rooms <span class="text-muted small">(comma-separated, suggested to tutors)</span></label><input name="rooms" value="<?php echo cal_e($s['rooms']); ?>" class="form-control mb-3">
            <label class="form-check"><input type="checkbox" name="self_checkin" value="1" class="form-check-input" <?php echo $s['self_checkin'] === '1' ? 'checked' : ''; ?>> Students can self check-in ("I'm here") for online classes — the tutor confirms</label>
            <label class="form-check mb-3"><input type="checkbox" name="late_counts" value="1" class="form-check-input" <?php echo $s['late_counts'] === '1' ? 'checked' : ''; ?>> Late counts as attended</label>
            <button class="btn btn-success">Save settings</button>
        </form>
    </div>
    <div class="col-lg-6">
        <div class="card card-body border-0 shadow-sm rounded-4">
            <h5>Public holidays &amp; term breaks</h5>
            <p class="small text-muted">Recurring classes skip these days. Tutors see how many were skipped and can add them back.</p>
            <form method="POST" class="row g-2 mb-3"><?php echo csrf_field(); ?><input type="hidden" name="act" value="holiday">
                <div class="col-md-5"><input name="name" class="form-control form-control-sm" placeholder="e.g. Mashujaa Day" required></div>
                <div class="col-md-3"><input type="date" name="start_date" class="form-control form-control-sm" required></div>
                <div class="col-md-3"><input type="date" name="end_date" class="form-control form-control-sm" title="Last day (leave empty for one day)"></div>
                <div class="col-md-1"><button class="btn btn-sm btn-success w-100">+</button></div>
            </form>
            <?php if (!$holidays): ?><div class="text-muted small">None yet.</div><?php endif; ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($holidays as $h): ?><li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span><strong><?php echo cal_e($h['name']); ?></strong> <span class="small text-muted"><?php echo date('j M Y', strtotime($h['start_date'])); ?><?php echo $h['end_date'] !== $h['start_date'] ? ' – ' . date('j M Y', strtotime($h['end_date'])) : ''; ?></span></span>
                    <form method="POST" class="m-0"><?php echo csrf_field(); ?><input type="hidden" name="act" value="holiday_delete"><input type="hidden" name="id" value="<?php echo (int) $h['id']; ?>"><button class="btn btn-sm btn-link text-danger"><i class="fas fa-trash"></i></button></form>
                </li><?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
