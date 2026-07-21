<?php
require_once __DIR__ . '/../../../app/v1/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/SettingModel.php';
require_once __DIR__ . '/../../../app/v1/helpers/testimonials.php';

if (empty($_SESSION['logged_in']) || (int) ($_SESSION['role_id'] ?? 99) > 2) {
    header('Location: ' . url('admin/portal.php'));
    exit;
}

$settings = new SettingModel($pdo);
$page_title = 'Testimonial Settings';
$breadcrumbs = [
    ['label' => 'Testimonials', 'url' => 'index.php'],
    ['label' => 'Settings', 'active' => true],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash']['error'] = 'Security check failed.';
    } else {
        $settings->set('testimonials_students_open', !empty($_POST['students_open']) ? '1' : '0');
        $settings->set('testimonials_auto_publish', !empty($_POST['auto_publish']) ? '1' : '0');
        $_SESSION['flash']['success'] = 'Testimonial settings saved.';
        header('Location: settings.php');
        exit;
    }
}

$studentOpen = testimonials_students_open();
$autoPublish = testimonial_settings()['auto_publish'];
$submitUrl = url('testimonials/submit');

ob_start();
?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Student submission portal</h5>
        <a href="index.php" class="btn btn-sm btn-outline-secondary">Back</a>
      </div>
      <div class="card-body">
        <form method="POST">
          <?php echo csrf_field(); ?>
          <div class="form-check form-switch mb-4">
            <input class="form-check-input" type="checkbox" name="students_open" value="1" id="students_open" <?php echo $studentOpen ? 'checked' : ''; ?>>
            <label class="form-check-label" for="students_open">
              <strong>Open testimonial page for students</strong>
              <div class="text-muted small">When enabled, students can submit text or video testimonials using the link below.</div>
            </label>
          </div>
          <div class="form-check form-switch mb-4">
            <input class="form-check-input" type="checkbox" name="auto_publish" value="1" id="auto_publish" <?php echo $autoPublish ? 'checked' : ''; ?>>
            <label class="form-check-label" for="auto_publish">
              <strong>Auto-publish on homepage</strong>
              <div class="text-muted small">When checked, student submissions go live immediately. Otherwise they stay pending until you approve them.</div>
            </label>
          </div>
          <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Save settings</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Share with students</h5></div>
      <div class="card-body">
        <p class="small text-muted">Send this link when submissions are open:</p>
        <div class="input-group mb-3">
          <input type="text" class="form-control form-control-sm" id="submit-link" readonly value="<?php echo htmlspecialchars($submitUrl); ?>">
          <button type="button" class="btn btn-outline-primary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('submit-link').value)">Copy</button>
        </div>
        <a href="<?php echo htmlspecialchars($submitUrl); ?>" target="_blank" class="btn btn-sm btn-primary">Preview student page</a>
      </div>
    </div>

    <div class="card mt-4">
      <div class="card-header"><h5 class="mb-0">Manual add</h5></div>
      <div class="card-body d-grid gap-2">
        <a href="create.php" class="btn btn-outline-success"><i class="fas fa-quote-left me-1"></i> Add text testimonial</a>
        <a href="create_video.php" class="btn btn-outline-primary"><i class="fas fa-video me-1"></i> Add video testimonial</a>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
