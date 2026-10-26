<?php
require_once __DIR__ . '/../../../app/v1/config/paths.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
Middleware::requireAdmin();   // admins only (the superadmin has a separate portal)
require_once __DIR__ . '/../../../app/v1/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
require_once __DIR__ . '/../../../app/v1/controllers/TestimonialController.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/testimonials.php';

$testimonialController = new TestimonialController($pdo);
$page_title = 'Add Student Story';
$breadcrumbs = [
    ['label' => 'Testimonials', 'url' => 'index.php'],
    ['label' => 'Add Student Story', 'active' => true],
];

$old = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = null;
    $name = trim($_POST['customer_name'] ?? '');
    $source = ($_POST['video_source'] ?? 'youtube') === 'upload' ? 'upload' : 'youtube';
    $videoUrl = '';

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $error = 'Security check failed. Please refresh and try again.';
    } elseif ($name === '') {
        $error = 'Enter the student\'s name.';
    } elseif ($source === 'youtube') {
        $videoUrl = trim($_POST['video_url'] ?? '');
        if (!testimonial_youtube_id($videoUrl)) {
            $error = 'Paste a valid YouTube link (youtube.com/watch?v=…, youtu.be/…, or a Shorts link).';
        }
    } else {
        $up = upload_video($_FILES['video_file'] ?? [], public_path('uploads/testimonials/videos'), 'uploads/testimonials/videos');
        if ($up['ok']) {
            $videoUrl = url($up['path']);
        } else {
            $error = $up['error'] ?? 'The video could not be uploaded.';
        }
    }

    $thumbnail = null;
    if (!$error && !empty($_FILES['thumbnail_file']['name'])) {
        $up = upload_image($_FILES['thumbnail_file'], public_path('uploads/testimonials'), 'uploads/testimonials');
        if ($up['ok']) {
            $thumbnail = url($up['path']);
        } else {
            $error = $up['error'] ?? 'The cover photo could not be uploaded.';
        }
    }

    if ($error) {
        $_SESSION['flash']['error'] = $error;
    } else {
        $pdo->prepare(
            'INSERT INTO testimonials (customer_name, customer_initial, rating, testimonial_text, testimonial_type, video_url, video_provider, thumbnail_url, service_tag, role, status, is_featured, sort_order, approved_at)
             VALUES (?, ?, 5, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())'
        )->execute([
            $name,
            strtoupper(substr($name, 0, 1)),
            trim($_POST['testimonial_text'] ?? ''),
            'video',
            $videoUrl,
            $source,
            $thumbnail,
            trim($_POST['service_tag'] ?? '') ?: null,
            trim($_POST['role'] ?? '') ?: null,
            !empty($_POST['publish']) ? 'approved' : 'pending',
            (int) ($_POST['sort_order'] ?? 0),
        ]);
        $_SESSION['flash']['success'] = 'Student story saved.';
        header('Location: index.php');
        exit;
    }
}

$e = static fn(string $k, string $d = '') => htmlspecialchars((string) ($old[$k] ?? $d), ENT_QUOTES);
$src = ($old['video_source'] ?? 'youtube') === 'upload' ? 'upload' : 'youtube';
$maxMb = round(MAX_VIDEO_UPLOAD_BYTES / 1048576);

ob_start();
?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0"><i class="fas fa-video me-2"></i>Add student story</h5>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">Back</a>
  </div>
  <div class="card-body">
    <p class="text-muted small">Stories appear in <strong>Hear From Our Students</strong> on the homepage, lowest sort order first.</p>
    <form method="POST" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Student name *</label>
          <input class="form-control" name="customer_name" value="<?php echo $e('customer_name'); ?>" required placeholder="e.g. Yuri Demidov">
        </div>
        <div class="col-md-6">
          <label class="form-label">Location or role</label>
          <input class="form-control" name="role" value="<?php echo $e('role'); ?>" placeholder="e.g. Nairobi, Kenya">
          <small class="text-muted">Shown under the name.</small>
        </div>
        <div class="col-12">
          <label class="form-label d-block">Video *</label>
          <div class="btn-group" role="group">
            <input type="radio" class="btn-check" name="video_source" id="srcYt" value="youtube" <?php echo $src === 'youtube' ? 'checked' : ''; ?>>
            <label class="btn btn-outline-success" for="srcYt"><i class="fab fa-youtube me-1"></i>YouTube link</label>
            <input type="radio" class="btn-check" name="video_source" id="srcUp" value="upload" <?php echo $src === 'upload' ? 'checked' : ''; ?>>
            <label class="btn btn-outline-success" for="srcUp"><i class="fas fa-upload me-1"></i>Upload video</label>
          </div>
        </div>
        <div class="col-12" id="ytField">
          <input class="form-control" name="video_url" value="<?php echo $e('video_url'); ?>" placeholder="https://www.youtube.com/watch?v=...">
        </div>
        <div class="col-12" id="upField">
          <input type="file" class="form-control" name="video_file" accept="video/mp4,video/webm,video/quicktime">
          <small class="text-muted">MP4, WebM or MOV, up to <?php echo $maxMb; ?> MB.</small>
        </div>
        <div class="col-md-6">
          <label class="form-label">Cover photo</label>
          <input type="file" class="form-control" name="thumbnail_file" accept="image/*">
          <small class="text-muted">Optional. Without one, YouTube's thumbnail or the video's first frame is used.</small>
        </div>
        <div class="col-md-6">
          <label class="form-label">Course</label>
          <input class="form-control" name="service_tag" value="<?php echo $e('service_tag'); ?>" placeholder="e.g. Web Development">
        </div>
        <div class="col-12">
          <label class="form-label">Short description</label>
          <textarea class="form-control" name="testimonial_text" rows="3" maxlength="400" placeholder="Shown over the video on the middle card"><?php echo $e('testimonial_text'); ?></textarea>
        </div>
        <div class="col-md-3">
          <label class="form-label">Sort order</label>
          <input class="form-control" type="number" name="sort_order" value="<?php echo $e('sort_order', '0'); ?>">
        </div>
        <div class="col-md-9 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="publish" value="1" id="pub" <?php echo $old && empty($old['publish']) ? '' : 'checked'; ?>>
            <label class="form-check-label" for="pub">Publish on the homepage now</label>
          </div>
        </div>
      </div>
      <button class="btn btn-success mt-3" type="submit"><i class="fas fa-save me-1"></i> Save story</button>
    </form>
  </div>
</div>
<script>
(function () {
  function sync() {
    var up = document.getElementById('srcUp').checked;
    document.getElementById('ytField').style.display = up ? 'none' : '';
    document.getElementById('upField').style.display = up ? '' : 'none';
  }
  document.querySelectorAll('[name="video_source"]').forEach(function (r) { r.addEventListener('change', sync); });
  sync();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
