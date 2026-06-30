<?php
// public/admin/blogs/edit.php
$page_title = 'Edit Blog Post';
$breadcrumbs = [
    ['label' => 'Blog', 'url' => 'index.php'],
    ['label' => 'Edit', 'active' => true]
];

ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
    header('Location: /Realestate/public/auth/login.php');
    exit();
}

$blog_id = $_GET['id'] ?? null;
if (!$blog_id) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/controllers/BlogController.php';

$blogController = new BlogController($pdo);
$blog = $blogController->getBlogDetails($blog_id);

if (!$blog) {
    header('Location: index.php');
    exit();
}

$categories = $blogController->getCategories();
$allTags = $blogController->getAllTags();
$blogTags = array_map(function($tag) { return $tag['name']; }, $blog['tags']);
?>

<style>
    .form-label {
        font-weight: 500;
        margin-bottom: 8px;
        color: #1a1a1a;
    }
    .form-control, .form-select {
        border-radius: 6px;
        border: 1px solid #ccc;
        padding: 8px 12px;
        transition: all 0.2s;
    }
    .form-control:focus, .form-select:focus {
        border-color: #1a1a1a;
        box-shadow: none;
        outline: none;
    }
    .card {
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        margin-bottom: 20px;
        background: #fff;
    }
    .card-header {
        background: #fafafa;
        border-bottom: 1px solid #e0e0e0;
        padding: 15px 20px;
        font-weight: 600;
        color: #1a1a1a;
    }
    .card-header h5 {
        margin: 0;
        font-size: 1rem;
    }
    .card-body {
        padding: 20px;
    }
    .btn-primary-custom {
        background: #1a1a1a;
        border: 1px solid #1a1a1a;
        color: #fff;
        padding: 10px 20px;
        border-radius: 6px;
        width: 100%;
        transition: all 0.2s;
    }
    .btn-primary-custom:hover {
        background: #333;
        border-color: #333;
    }
    .btn-secondary-custom {
        background: #fff;
        border: 1px solid #ccc;
        color: #1a1a1a;
        padding: 8px 16px;
        border-radius: 6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-secondary-custom:hover {
        background: #f5f5f5;
        color: #1a1a1a;
    }
    .preview-image {
        max-width: 200px;
        margin-top: 10px;
        border-radius: 6px;
        border: 1px solid #e0e0e0;
    }
    .current-image {
        margin-bottom: 15px;
    }
    .current-image img {
        border-radius: 6px;
        border: 1px solid #e0e0e0;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    .page-header h2 {
        font-size: 1.5rem;
        font-weight: 600;
        margin: 0;
    }
    textarea.form-control {
        resize: vertical;
    }
    .note-editor.note-frame {
        border-radius: 6px;
        border-color: #ccc;
    }
    .note-editor.note-frame .note-toolbar {
        background: #fafafa;
        border-bottom-color: #e0e0e0;
    }
    small.text-muted {
        color: #666;
        font-size: 0.75rem;
        margin-top: 5px;
        display: block;
    }
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .btn-primary-custom {
            width: auto;
        }
    }
</style>

<div class="page-header">
    <h2>Edit Blog Post</h2>
    <a href="index.php" class="btn-secondary-custom">
        <i class="fas fa-arrow-left"></i> Back to List
    </a>
</div>

<form id="updateBlogForm" enctype="multipart/form-data">
    <input type="hidden" name="blog_id" value="<?php echo $blog['id']; ?>">
    
    <div class="row">
        <!-- Main Content Column -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-edit me-2"></i> Blog Post Content
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($blog['title']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Excerpt (Short Description)</label>
                        <textarea name="excerpt" class="form-control" rows="3"><?php echo htmlspecialchars($blog['excerpt']); ?></textarea>
                        <small class="text-muted">Brief summary of the post (150-160 characters recommended)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content *</label>
                        <textarea name="content" id="content" class="form-control" rows="15" required><?php echo htmlspecialchars($blog['content']); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">SEO Meta Description</label>
                        <textarea name="meta_description" class="form-control" rows="2"><?php echo htmlspecialchars($blog['meta_description'] ?? ''); ?></textarea>
                        <small class="text-muted">Meta description for search engines (150-160 characters)</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar Column -->
        <div class="col-md-4">
            <!-- Publish Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-cog me-2"></i> Publish
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="draft" <?php echo $blog['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?php echo $blog['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="archived" <?php echo $blog['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Feature this post</label>
                        <select name="is_featured" class="form-select">
                            <option value="0" <?php echo $blog['is_featured'] ? '' : 'selected'; ?>>No</option>
                            <option value="1" <?php echo $blog['is_featured'] ? 'selected' : ''; ?>>Yes</option>
                        </select>
                        <small class="text-muted">Featured posts appear on the homepage</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Featured Image</label>
                        <?php if ($blog['featured_image']): ?>
                        <div class="current-image">
                            <img src="<?php echo $blog['featured_image']; ?>" class="preview-image">
                            <small class="text-muted d-block mt-1">Current image</small>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="featured_image" id="featured_image" class="form-control" accept="image/*">
                        <img id="imagePreview" class="preview-image" style="display:none;">
                        <small class="text-muted">Recommended size: 1200x800 pixels</small>
                    </div>
                    <button type="submit" class="btn-primary-custom">
                        <i class="fas fa-save me-2"></i> Update Post
                    </button>
                </div>
            </div>
            
            <!-- Categories Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-folder me-2"></i> Category
                </div>
                <div class="card-body">
                    <select name="category_id" class="form-select">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo ($blog['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Tags Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-tags me-2"></i> Tags
                </div>
                <div class="card-body">
                    <input type="text" name="tags" class="form-control" value="<?php echo htmlspecialchars(implode(', ', $blogTags)); ?>" placeholder="Enter tags separated by commas">
                    <small class="text-muted">Example: PHP, Laravel, MySQL, JavaScript</small>
                </div>
            </div>
            
            <!-- SEO Settings Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line me-2"></i> SEO Settings
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Meta Title</label>
                        <input type="text" name="meta_title" class="form-control" value="<?php echo htmlspecialchars($blog['meta_title'] ?? ''); ?>" placeholder="SEO title (60 characters max)">
                        <small class="text-muted">Leave empty to use post title</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Meta Keywords</label>
                        <input type="text" name="meta_keywords" class="form-control" value="<?php echo htmlspecialchars($blog['meta_keywords'] ?? ''); ?>" placeholder="keyword1, keyword2, keyword3">
                        <small class="text-muted">Comma-separated keywords</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Include Summernote CSS -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
<link href="https://code.jquery.com/jquery-3.6.0.min.js" rel="preload" as="script">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>

<script>
$(document).ready(function() {
    $('#content').summernote({
        height: 400,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'clear']],
            ['fontname', ['fontname']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture', 'video']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ],
        callbacks: {
            onImageUpload: function(files) {
                // Handle image upload if needed
                console.log('Image upload detected');
            }
        }
    });
});

// Image preview for featured image
document.getElementById('featured_image').addEventListener('change', function(e) {
    const reader = new FileReader();
    reader.onload = function() {
        const preview = document.getElementById('imagePreview');
        preview.src = reader.result;
        preview.style.display = 'block';
    }
    if (e.target.files[0]) {
        reader.readAsDataURL(e.target.files[0]);
    }
});

// Form submission
document.getElementById('updateBlogForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    formData.set('content', $('#content').summernote('code'));
    
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Updating...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch('/Realestate/public/api/blog/update.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Blog post updated successfully!');
            location.reload();
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