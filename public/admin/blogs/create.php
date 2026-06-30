<?php
// public/admin/blogs/create.php
$page_title = 'Create New Blog Post';
$breadcrumbs = [
    ['label' => 'Blog', 'url' => 'index.php'],
    ['label' => 'Create', 'active' => true]
];

ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
    header('Location: /Realestate/public/auth/login.php');
    exit();
}

require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/controllers/BlogController.php';

$blogController = new BlogController($pdo);
$categories = $blogController->getCategories();
$allTags = $blogController->getAllTags();
?>

<style>
    .form-label {
        font-weight: 500;
        margin-bottom: 8px;
    }
    .form-control, .form-select {
        border-radius: 6px;
        border: 1px solid #ccc;
        padding: 8px 12px;
    }
    .form-control:focus, .form-select:focus {
        border-color: #1a1a1a;
        box-shadow: none;
    }
    .btn-primary-custom {
        background: #1a1a1a;
        border: 1px solid #1a1a1a;
        color: #fff;
        padding: 10px 24px;
        border-radius: 6px;
    }
    .btn-primary-custom:hover {
        background: #333;
    }
    .btn-secondary-custom {
        background: #fff;
        border: 1px solid #ccc;
        color: #1a1a1a;
        padding: 10px 24px;
        border-radius: 6px;
    }
    .card {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .card-header {
        background: #fafafa;
        border-bottom: 1px solid #e0e0e0;
        padding: 15px 20px;
        font-weight: 600;
    }
    .preview-image {
        max-width: 200px;
        margin-top: 10px;
        border-radius: 6px;
    }
</style>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-edit me-2"></i> Blog Post Content
            </div>
            <div class="card-body">
                <form id="createBlogForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Excerpt (Short Description)</label>
                        <textarea name="excerpt" class="form-control" rows="3"></textarea>
                        <small class="text-muted">Brief summary of the post (150-160 characters recommended)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content *</label>
                        <textarea name="content" id="content" class="form-control" rows="15" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">SEO Meta Description</label>
                        <textarea name="meta_description" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn-primary-custom">
                        <i class="fas fa-save me-2"></i>Publish Post
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-cog me-2"></i> Settings
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" form="createBlogForm">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Featured Image</label>
                    <input type="file" name="featured_image" id="featured_image" class="form-control" accept="image/*">
                    <img id="imagePreview" class="preview-image" style="display:none;">
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-folder me-2"></i> Category
            </div>
            <div class="card-body">
                <select name="category_id" class="form-select" form="createBlogForm">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-tags me-2"></i> Tags
            </div>
            <div class="card-body">
                <input type="text" name="tags" class="form-control" placeholder="Enter tags separated by commas" form="createBlogForm">
                <small class="text-muted">Example: PHP, Laravel, MySQL</small>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-star me-2"></i> Featured
            </div>
            <div class="card-body">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_featured" value="1" form="createBlogForm">
                    <label class="form-check-label">Feature this post (shows on homepage)</label>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">

<script>
$(document).ready(function() {
    $('#content').summernote({
        height: 400,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'clear']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture', 'video']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ]
    });
});

document.getElementById('featured_image').addEventListener('change', function(e) {
    const reader = new FileReader();
    reader.onload = function() {
        const preview = document.getElementById('imagePreview');
        preview.src = reader.result;
        preview.style.display = 'block';
    }
    if (e.target.files[0]) reader.readAsDataURL(e.target.files[0]);
});

document.getElementById('createBlogForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.set('content', $('#content').summernote('code'));
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('/Realestate/public/api/blog/create.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Blog post created successfully!');
            window.location.href = `edit.php?id=${result.blog_id}`;
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';
?>