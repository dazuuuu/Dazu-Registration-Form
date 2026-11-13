<?php
// Admin Settings: Self-study. The numbers behind the system comment on a course's Students page and the reset limits.
// The working days (7) and not-working days (14) are the same numbers as Student Progress (Settings > Student Progress); the numbers below are saved next to them.
// Only the office administrators (role_id 2) may change them.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/SelfStudyProgress.php';

Middleware::requireAdmin();
if ((int) ($_SESSION['role_id'] ?? 99) > 2 || !empty($_SESSION['tutor_admin'])) {
    http_response_code(403);
    exit('Only the office administrators can change the Self-study settings.');
}
$db = \Models\Connection::getInstance()->getConnection();
$self = url('admin/settings/selfstudy.php');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$installed = sp_installed($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $f = ['error', 'Security check failed. Please refresh and try again.'];
    } elseif (!$installed) {
        $f = ['error', 'Run System Updates (migration 103) first.'];
    } elseif (isset($_POST['reset'])) {
        $db->exec("DELETE FROM progress_settings WHERE setting_key LIKE 'ss\\_%' AND setting_key <> 'ss_cron_last_run'");      // the cron heartbeat is not a setting
        $f = ['success', 'Back to the standard numbers.'];
    } else {
        $r = ssd_settings_save($db, $_POST, (int) ($_SESSION['user_id'] ?? 0));
        $f = $r['ok'] ? ['success', 'Saved. The Students page of every self-study course now uses these numbers.'] : ['error', $r['error']];
    }
    $_SESSION['ssd_flash'] = $f;
    header('Location: ' . $self);
    exit;
}
$flash = $_SESSION['ssd_flash'] ?? null;
unset($_SESSION['ssd_flash']);
$s = ssd_settings($db);
$cron = ssd_cron_status($db);
$cronCmd = '* * * * * php ' . dirname(__DIR__, 3) . '/app/v1/tools/selfstudy_expire.php >/dev/null 2>&1';
$fmtTime = fn(int $t) => (new DateTime('@' . $t))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('j M Y, H:i');

$page_title = 'Settings · Self-study';
$breadcrumbs = [['label' => 'Settings', 'url' => url('admin/settings/index.php')], ['label' => 'Self-study', 'active' => true]];
ob_start();
?>
<?php if ($flash): ?><div class="alert alert-<?php echo $flash[0] === 'success' ? 'success' : 'danger'; ?>"><?php echo $e($flash[1]); ?></div><?php endif; ?>
<?php if (!$installed): ?><div class="alert alert-warning">Open System Updates and install the latest version to save changes. Until then the standard numbers are used.</div><?php endif; ?>
<div class="card border-0 shadow-sm mb-3" style="max-width:720px">
  <div class="card-body">
    <h6 style="color:#048C44">Exams left open: the cron job</h6>
    <p class="text-muted small mb-2">When a student closes the page during an exam, the cron job submits it when the time ends. Add this line once in your hosting Cron Jobs (every minute). If your host asks for the PHP path, use the one it shows (often <code>/usr/local/bin/php</code>).</p>
    <input type="text" class="form-control form-control-sm" readonly value="<?php echo $e($cronCmd); ?>" onclick="this.select()" aria-label="Cron command" style="font-family:monospace">
    <p class="mt-2 mb-0 small">
      <b>Last ran:</b> <?php echo $cron['last'] ? $e($fmtTime($cron['last'])) . ' (Nairobi time)' : 'never'; ?>
      <?php if ($cron['late']): ?><span class="d-block fw-bold" style="color:#B91C1C" role="alert"><i class="fas fa-triangle-exclamation"></i> The cron job has not run in the last 10 minutes.</span>
      <?php else: ?><span class="d-block" style="color:#037A3B"><i class="fas fa-circle-check"></i> Running on time.</span><?php endif; ?>
    </p>
  </div>
</div>
<div class="card border-0 shadow-sm" style="max-width:720px">
  <div class="card-body">
    <p class="text-muted">On the Students page of a self-study course, every student gets one of three words (Working well, Slowing down, Not working), worked out again each time the page opens.
       <b>Working well</b>: studied in the last <?php echo (int) $s['working_days']; ?> days and moving forward. <b>Slowing down</b>: nothing studied for <?php echo (int) $s['working_days']; ?> to <?php echo (int) $s['slowing_days']; ?> days, or failed tries in a row on the same quiz or exam.
       <b>Not working</b>: nothing studied for more than <?php echo (int) $s['slowing_days']; ?> days, or every try used up and failed.
       The two day numbers are the same as for Student Progress: <a href="<?php echo $e(url('admin/settings/progress.php')); ?>">change them there</a>.</p>
    <form method="post">
      <?php echo csrf_field(); ?>
      <div class="mb-3"><label class="form-label">Failed tries in a row on the same quiz or exam that make a student "Slowing down" (<?php echo SSD_DEFAULTS['failed_in_row']; ?>)</label>
        <input type="number" name="failed_in_row" min="1" max="30" class="form-control" style="max-width:140px" value="<?php echo (int) $s['failed_in_row']; ?>" required></div>
      <h6 class="mt-4" style="color:#048C44">Resets by a tutor</h6>
      <p class="text-muted small">A tutor can reset one student this many times a month. After that only admin can reset. Admin has no limit. Every reset is saved with who did it and why.</p>
      <div class="mb-3"><label class="form-label">Module quiz resets per student per month (<?php echo SSD_DEFAULTS['reset_quiz_month']; ?>)</label>
        <input type="number" name="reset_quiz_month" min="1" max="30" class="form-control" style="max-width:140px" value="<?php echo (int) $s['reset_quiz_month']; ?>" required></div>
      <div class="mb-3"><label class="form-label">Exam resets per student per month (<?php echo SSD_DEFAULTS['reset_exam_month']; ?>)</label>
        <input type="number" name="reset_exam_month" min="1" max="30" class="form-control" style="max-width:140px" value="<?php echo (int) $s['reset_exam_month']; ?>" required></div>
      <button class="btn btn-success" <?php echo $installed ? '' : 'disabled'; ?>>Save</button>
      <button class="btn btn-outline-secondary" name="reset" value="1" <?php echo $installed ? '' : 'disabled'; ?> onclick="return confirm('Go back to the standard numbers?');">Use the standard numbers</button>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
