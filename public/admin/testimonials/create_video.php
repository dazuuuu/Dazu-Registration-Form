<?php
require_once __DIR__ . '/../../../app/v1/config/paths.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
Middleware::requireAdmin();   // admins only (the superadmin has a separate portal)
require_once __DIR__ . '/../../../app/v1/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
require_once __DIR__ . '/../../../app/v1/controllers/TestimonialController.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';

$testimonialController = new TestimonialController($pdo);
$page_title = 'Add Video Testimonial';
$breadcrumbs = [
    ['label' => 'Testimonials', 'url' => 'index.php'],
    ['label' => 'Add Video', 'active' => true],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash']['error'] = 'Security check failed.';
    } else {
        $thumbnail_url = trim($_POST['thumbnail_url'] ?? '');
        if (!empty($_FILES['thumbnail_file']['name'])) {
            $absDir = public_path('uploads/testimonials');
            if (!is_dir($absDir)) {
                @mkdir($absDir, 0775, true);
            }
            $up = upload_image($_FILES['thumbnail_file'], $absDir, 'uploads/testimonials');
            if ($up['ok']) {
                $thumbnail_url = asset($up['path']);
            }
        }

        $pdo->prepare(
            'INSERT INTO testimonials (customer_name, customer_initial, rating, testimonial_text, testimonial_type, video_url, video_provider, avatar_url, thumbnail_url, service_tag, role, status, is_featured, sort_order, approved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            trim($_POST['customer_name']),
            strtoupper(substr(trim($_POST['customer_name']), 0, 1)),
            (int) $_POST['rating'],
            trim($_POST['testimonial_text'] ?? ''),
            $_POST['testimonial_type'] ?? 'video',
            trim($_POST['video_url'] ?? ''),
            $_POST['video_provider'] ?? 'youtube',
            trim($_POST['avatar_url'] ?? '') ?: null,
            $thumbnail_url ?: null,
            trim($_POST['service_tag'] ?? '') ?: null,
            trim($_POST['role'] ?? '') ?: null,
            'approved',
            !empty($_POST['is_featured']) ? 1 : 0,
            (int) ($_POST['sort_order'] ?? 0),
        ]);
        $_SESSION['flash']['success'] = 'Video testimonial added.';
        header('Location: index.php');
        exit;
    }
}

ob_start();
?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0">Add video testimonial</h5>
    <a href="index.php" class="btn btn-sm btn-outline-secondary">Back</a>
  </div>
  <div class="card-body">
    <form method="POST" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Name *</label>
          <input class="form-control" name="customer_name" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Role / title</label>
          <input class="form-control" name="role" placeholder="Student, Web Dev cohort">
        </div>
        <div class="col-md-4">
          <label class="form-label">Type</label>
          <select class="form-select" name="testimonial_type">
            <option value="video">Video</option>
            <option value="text">Text</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Video provider</label>
          <select class="form-select" name="video_provider">
            <option value="youtube">YouTube</option>
            <option value="vimeo">Vimeo</option>
            <option value="upload">Upload URL</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Rating</label>
          <select class="form-select" name="rating">
            <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?php echo $i; ?>"><?php echo $i; ?> stars</option><?php endfor; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label">Video URL *</label>
          <input class="form-control" name="video_url" placeholder="https://www.youtube.com/watch?v=..." required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Avatar image URL</label>
          <input class="form-control" name="avatar_url" placeholder="https://...">
        </div>
        <div class="col-md-6">
          <label class="form-label">Thumbnail URL</label>
          <input class="form-control" name="thumbnail_url" placeholder="https://...">
          <small class="text-muted">Leave blank for automatic YouTube thumbnail</small>
        </div>
        <div class="col-md-6">
          <label class="form-label">Or Upload Thumbnail Image</label>
          <input type="file" name="thumbnail_file" class="form-control" accept="image/*">
        </div>
        <div class="col-12">
          <label class="form-label">Caption / quote</label>
          <textarea class="form-control" name="testimonial_text" rows="3"></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Course tag</label>
          <input class="form-control" name="service_tag" placeholder="Web Development">
        </div>
        <div class="col-md-3">
          <label class="form-label">Sort order</label>
          <input class="form-control" type="number" name="sort_order" value="0">
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="feat">
            <label class="form-check-label" for="feat">Featured</label>
          </div>
        </div>
      </div>
      <button class="btn btn-success mt-3" type="submit"><i class="fas fa-save me-1"></i> Save testimonial</button>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
