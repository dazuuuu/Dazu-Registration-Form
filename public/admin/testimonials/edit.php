<?php
require_once __DIR__ . '/../../../app/v1/config/paths.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
Middleware::requireAdmin();   // admins only (the superadmin has a separate portal)
require_once __DIR__ . '/../../../app/v1/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
require_once __DIR__ . '/../../../app/v1/controllers/TestimonialController.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';

$testimonialController = new TestimonialController($pdo);
$page_title = 'Edit Testimonial';
$breadcrumbs = [
    ['label' => 'Testimonials', 'url' => 'index.php'],
    ['label' => 'Edit', 'active' => true]
];

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit();
}

$testimonial = $testimonialController->getById($id);
if (!$testimonial) {
    header('Location: index.php');
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash']['error'] = 'Security check failed. Please refresh and try again.';
    } else {
        $thumbnail_url = trim($_POST['thumbnail_url'] ?? '');
        if (!empty($_FILES['thumbnail_file']['name'])) {
            $absDir = public_path('uploads/testimonials');
            if (!is_dir($absDir)) {
                @mkdir($absDir, 0775, true);
            }
            $uploadRes = upload_image(
                $_FILES['thumbnail_file'],
                $absDir,
                'uploads/testimonials'
            );
            if ($uploadRes['ok']) {
                $thumbnail_url = url($uploadRes['path']);
            } else {
                $_SESSION['flash']['error'] = $uploadRes['error'] ?? 'Failed to upload thumbnail.';
            }
        }

        $type = $_POST['testimonial_type'] ?? 'text';
        $videoUrl = trim($_POST['video_url'] ?? '');
        $videoProvider = $_POST['video_provider'] ?? 'youtube';
        if (!empty($_FILES['video_file']['name'])) {
            $vUp = upload_video($_FILES['video_file'], public_path('uploads/testimonials/videos'), 'uploads/testimonials/videos');
            if ($vUp['ok']) {
                $videoUrl = url($vUp['path']);
                $videoProvider = 'upload';
            } else {
                $_SESSION['flash']['error'] = $vUp['error'] ?? 'Failed to upload video.';
            }
        }
        if ($type === 'text' && empty($videoUrl)) {
            $videoProvider = 'none';
        }

        $data = [
            'customer_name' => trim($_POST['customer_name'] ?? ''),
            'customer_email' => trim($_POST['customer_email'] ?? '') ?: null,
            'customer_phone' => trim($_POST['customer_phone'] ?? '') ?: null,
            'customer_initial' => strtoupper(substr(trim($_POST['customer_name'] ?? 'DH'), 0, 2)),
            'rating' => (int)($_POST['rating'] ?? 5),
            'testimonial_text' => trim($_POST['testimonial_text'] ?? ''),
            'testimonial_type' => $type,
            'video_url' => $videoUrl,
            'video_provider' => $videoProvider,
            'thumbnail_url' => $thumbnail_url ?: null,
            'avatar_url' => trim($_POST['avatar_url'] ?? '') ?: null,
            'service_tag' => trim($_POST['service_tag'] ?? '') ?: null,
            'role' => trim($_POST['role'] ?? '') ?: null,
            'is_featured' => !empty($_POST['is_featured']) ? 1 : 0,
            'sort_order' => (int)($_POST['sort_order'] ?? 0)
        ];
        
        $result = $testimonialController->update($id, $data);
        $_SESSION['flash'][$result['success'] ? 'success' : 'error'] = $result['message'];
        
        if ($result['success']) {
            header('Location: index.php');
            exit();
        }
    }
}

$coursesList = [];
try {
    $cStmt = $pdo->query("SELECT title FROM courses WHERE status = 'published' ORDER BY title ASC");
    $coursesList = $cStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$standardTracks = [
    'Software Development', 'Web Development', 'Data Analytics', 
    'Cybersecurity & Networks', 'AI & Machine Learning', 'Cloud Computing', 
    'Digital Marketing', 'Graphic Design'
];
$allTags = array_unique(array_filter(array_merge($standardTracks, $coursesList)));

ob_start();
?>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-edit me-2"></i>Edit Testimonial #<?php echo (int) $testimonial['id']; ?>
        </h5>
        <a href="index.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Testimonials
        </a>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label font-weight-bold">Student / Customer Name *</label>
                    <input type="text" name="customer_name" class="form-control" 
                           value="<?php echo htmlspecialchars($testimonial['customer_name']); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label font-weight-bold">Testimonial Type *</label>
                    <select name="testimonial_type" id="testimonialTypeSelect" class="form-select" onchange="toggleVideoFields()">
                        <option value="video" <?php echo ($testimonial['testimonial_type'] ?? '') === 'video' || !empty($testimonial['video_url']) ? 'selected' : ''; ?>>
                            Video Testimonial
                        </option>
                        <option value="text" <?php echo ($testimonial['testimonial_type'] ?? '') === 'text' && empty($testimonial['video_url']) ? 'selected' : ''; ?>>
                            Text Testimonial
                        </option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label font-weight-bold">Rating *</label>
                    <select name="rating" class="form-select">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo (int)$testimonial['rating'] === $i ? 'selected' : ''; ?>>
                                <?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Role / Student Title</label>
                    <input type="text" name="role" class="form-control" 
                           value="<?php echo htmlspecialchars($testimonial['role'] ?? ''); ?>"
                           placeholder="e.g., Full-Stack Graduate, Data Analyst at Safaricom">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Course / Track Tag</label>
                    <input type="text" name="service_tag" list="courseTagsList" class="form-control" 
                           value="<?php echo htmlspecialchars($testimonial['service_tag'] ?? ''); ?>"
                           placeholder="e.g. Software Development">
                    <datalist id="courseTagsList">
                        <?php foreach ($allTags as $tag): ?>
                            <option value="<?php echo htmlspecialchars($tag); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <!-- Video fields block -->
                <div id="videoFieldsBlock" class="col-12 p-3 my-2 border rounded bg-light">
                    <h6 class="text-primary mb-3">
                        <i class="fas fa-video me-1"></i> Video Testimonial Configuration
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Video Provider</label>
                            <select name="video_provider" class="form-select">
                                <option value="youtube" <?php echo ($testimonial['video_provider'] ?? '') === 'youtube' ? 'selected' : ''; ?>>YouTube</option>
                                <option value="vimeo" <?php echo ($testimonial['video_provider'] ?? '') === 'vimeo' ? 'selected' : ''; ?>>Vimeo</option>
                                <option value="upload" <?php echo ($testimonial['video_provider'] ?? '') === 'upload' ? 'selected' : ''; ?>>Uploaded video / direct URL</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Video URL (YouTube or Vimeo)</label>
                            <input type="text" name="video_url" class="form-control" 
                                   value="<?php echo htmlspecialchars($testimonial['video_url'] ?? ''); ?>"
                                   placeholder="https://www.youtube.com/watch?v=...">
                            <small class="text-muted">YouTube video URL or embed link</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Or upload a video file</label>
                            <input type="file" name="video_file" class="form-control" accept="video/mp4,video/webm,video/quicktime">
                            <small class="text-muted">MP4, WebM or MOV. Replaces the video URL above.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Thumbnail URL</label>
                            <input type="text" name="thumbnail_url" class="form-control" 
                                   value="<?php echo htmlspecialchars($testimonial['thumbnail_url'] ?? ''); ?>"
                                   placeholder="https://img.youtube.com/vi/.../hqdefault.jpg">
                            <small class="text-muted">Leave blank for auto YouTube thumbnail</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Or Upload Custom Thumbnail</label>
                            <input type="file" name="thumbnail_file" class="form-control" accept="image/*">
                            <?php if (!empty($testimonial['thumbnail_url'])): ?>
                                <div class="mt-2 d-flex align-items-center gap-2">
                                    <img src="<?php echo htmlspecialchars($testimonial['thumbnail_url']); ?>" alt="Current thumbnail" style="width: 70px; height: 45px; object-fit: cover; border-radius: 4px;">
                                    <small class="text-success">Current Thumbnail Active</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label font-weight-bold">Testimonial Caption / Quote *</label>
                    <textarea name="testimonial_text" class="form-control" rows="4" required placeholder="What the student said..."><?php echo htmlspecialchars($testimonial['testimonial_text']); ?></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Customer Email (Optional)</label>
                    <input type="email" name="customer_email" class="form-control" 
                           value="<?php echo htmlspecialchars($testimonial['customer_email'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Customer Phone (Optional)</label>
                    <input type="text" name="customer_phone" class="form-control" 
                           value="<?php echo htmlspecialchars($testimonial['customer_phone'] ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" 
                           value="<?php echo (int)($testimonial['sort_order'] ?? 0); ?>">
                    <small class="text-muted">Lowest first</small>
                </div>
                <div class="col-md-2 d-flex align-items-center pt-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_featured" value="1" id="isFeaturedCheck"
                               <?php echo !empty($testimonial['is_featured']) ? 'checked' : ''; ?>>
                        <label class="form-check-label font-weight-bold" for="isFeaturedCheck">
                            Featured
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Update Testimonial
                </button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleVideoFields() {
    var type = document.getElementById('testimonialTypeSelect').value;
    var block = document.getElementById('videoFieldsBlock');
    if (type === 'video') {
        block.style.display = 'block';
    } else {
        block.style.display = 'none';
    }
}
document.addEventListener('DOMContentLoaded', toggleVideoFields);
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';
?>