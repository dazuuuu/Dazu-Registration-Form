<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/controllers/GalleryController.php';
require_once __DIR__ . '/../../../app/helpers/uploads.php';

$galleryController = new GalleryController($pdo);
$page_title = 'Add Gallery Item';
$breadcrumbs = [
    ['label' => 'Gallery', 'url' => 'index.php'],
    ['label' => 'Add New', 'active' => true]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash']['error'] = 'Invalid CSRF token';
        header('Location: index.php');
        exit();
    }
    
    $media_file = $_FILES['media_file'] ?? null;
    $result = $galleryController->create($_POST, $media_file);
    
    if ($result['success']) {
        $_SESSION['flash']['success'] = $result['message'];
        header('Location: index.php');
    } else {
        $_SESSION['flash']['error'] = $result['message'];
    }
    exit();
}

$categories = $galleryController->getCategories();

ob_start();
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Add New Gallery Item</h5>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Media Type</label>
                        <select name="media_type" class="form-select" id="mediaType">
                            <option value="image">Image</option>
                            <option value="video">Video</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="fileUploadDiv">
                        <label class="form-label">Upload File</label>
                        <input type="file" name="media_file" class="form-control" accept="image/*,video/*">
                        <small class="text-muted">Supported: JPG, PNG, GIF, WebP, MP4, WebM (Max 10MB)</small>
                    </div>
                    
                    <div class="mb-3" id="videoUrlDiv" style="display: none;">
                        <label class="form-label">Video URL (YouTube/Vimeo)</label>
                        <input type="text" name="video_url" class="form-control" placeholder="https://www.youtube.com/watch?v=...">
                    </div>
                    
                    <div class="mb-3" id="embedCodeDiv" style="display: none;">
                        <label class="form-label">Embed Code</label>
                        <textarea name="video_embed_code" class="form-control" rows="3" placeholder="<iframe ...></iframe>"></textarea>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-control" list="categories">
                        <datalist id="categories">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tags</label>
                        <input type="text" name="tags" class="form-control" placeholder="tag1, tag2, tag3">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_featured" class="form-check-input" value="1" id="isFeatured">
                            <label class="form-check-label" for="isFeatured">Feature this item</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Gallery Item</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
const mediaType = document.getElementById('mediaType');
const fileUploadDiv = document.getElementById('fileUploadDiv');
const videoUrlDiv = document.getElementById('videoUrlDiv');
const embedCodeDiv = document.getElementById('embedCodeDiv');

mediaType.addEventListener('change', function() {
    if (this.value === 'image') {
        fileUploadDiv.style.display = 'block';
        videoUrlDiv.style.display = 'none';
        embedCodeDiv.style.display = 'none';
    } else {
        fileUploadDiv.style.display = 'block';
        videoUrlDiv.style.display = 'block';
        embedCodeDiv.style.display = 'block';
    }
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';
?>