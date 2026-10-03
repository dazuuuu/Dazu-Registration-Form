<?php
// public/admin/blogs/edit.php
require_once __DIR__ . '/../../../app/v1/config/paths.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
Middleware::requireAdmin();   // admins only (the superadmin has a separate portal)
require_once __DIR__ . '/../../../app/v1/config/paths.php';
$page_title = 'Edit Blog Post';
$breadcrumbs = [
    ['label' => 'Blog', 'url' => 'index.php'],
    ['label' => 'Edit', 'active' => true]
];

ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
    header('Location: ' . url('auth/login.php'));
    exit();
}

$blog_id = $_GET['id'] ?? null;
if (!$blog_id) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
require_once __DIR__ . '/../../../app/v1/controllers/BlogController.php';

$blogController = new BlogController($pdo);
$blog = $blogController->getBlogDetails($blog_id);

if (!$blog) {
    header('Location: index.php');
    exit();
}

$categories = $blogController->getCategories();
$allTags = $blogController->getAllTags();
$blogTags = array_map(function($tag) { return $tag['name']; }, $blog['tags']);

$blockTypes = [
    'heading'       => ['icon' => 'fa-heading', 'label' => 'Heading'],
    'paragraph'     => ['icon' => 'fa-paragraph', 'label' => 'Paragraph'],
    'image'         => ['icon' => 'fa-image', 'label' => 'Image'],
    'image_gallery' => ['icon' => 'fa-images', 'label' => 'Image Gallery'],
    'youtube'       => ['icon' => 'fa-brands fa-youtube', 'label' => 'YouTube Video'],
    'link'          => ['icon' => 'fa-link', 'label' => 'Link / URL'],
    'quote'         => ['icon' => 'fa-quote-right', 'label' => 'Quote'],
    'cta'           => ['icon' => 'fa-bullhorn', 'label' => 'CTA Button'],
    'faq'           => ['icon' => 'fa-circle-question', 'label' => 'FAQ'],
];

function dazu_block_preview(array $s): string {
    switch ($s['section_type']) {
        case 'heading': return $s['title'] !== '' && $s['title'] !== null ? $s['title'] : '(empty heading)';
        case 'paragraph': return mb_strimwidth(strip_tags($s['content'] ?? ''), 0, 90, '…') ?: '(empty)';
        case 'image': return $s['title'] ?: ($s['media_url'] ? basename($s['media_url']) : '(no image yet)');
        case 'image_gallery':
            $imgs = json_decode($s['gallery_images'] ?? '[]', true) ?: [];
            return count($imgs) . ' image' . (count($imgs) === 1 ? '' : 's');
        case 'youtube': return $s['title'] ?: ($s['media_url'] ?: '(no url yet)');
        case 'link': return $s['link_label'] ?: ($s['media_url'] ?: '(no url yet)');
        case 'quote': return mb_strimwidth(strip_tags($s['content'] ?? ''), 0, 90, '…') ?: '(empty)';
        case 'cta': return ($s['cta_label'] ?: 'CTA') . ' — ' . ucfirst($s['cta_type'] ?? '');
        case 'faq':
            $items = json_decode($s['faq_items'] ?? '[]', true) ?: [];
            return count($items) . ' question' . (count($items) === 1 ? '' : 's');
        default: return '';
    }
}
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
    .blocks-list { display: flex; flex-direction: column; gap: 8px; }
    .block-row {
        display: flex;
        align-items: center;
        gap: 12px;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        padding: 10px 12px;
        background: #fff;
    }
    .block-row.sortable-ghost { opacity: 0.4; }
    .block-drag { cursor: grab; color: #999; }
    .block-type-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        color: #1a1a1a;
        background: #f0f0f0;
        padding: 4px 10px;
        border-radius: 999px;
        white-space: nowrap;
    }
    .block-preview {
        flex: 1;
        font-size: 0.85rem;
        color: #555;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .block-actions { display: flex; gap: 6px; flex-shrink: 0; }
    .faq-row, .gallery-thumb-row { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 10px; }
    .faq-row .form-control { flex: 1; }
    .gallery-thumb {
        position: relative;
        display: inline-block;
        margin: 4px;
    }
    .gallery-thumb img {
        width: 80px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid #e0e0e0;
    }
    .gallery-thumb .remove-thumb {
        position: absolute; top: -6px; right: -6px;
        background: #dc3545; color: #fff; border: none; border-radius: 50%;
        width: 20px; height: 20px; font-size: 12px; line-height: 1; cursor: pointer;
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
                        <label class="form-label">Show in Popular slider</label>
                        <select name="is_featured" class="form-select">
                            <option value="0" <?php echo $blog['is_featured'] ? '' : 'selected'; ?>>No</option>
                            <option value="1" <?php echo $blog['is_featured'] ? 'selected' : ''; ?>>Yes</option>
                        </select>
                        <small class="text-muted">Ticked posts rotate in the slider at the top of the Blog page. If no post is ticked, the slider section is hidden.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cover Image <span class="text-danger">*</span></label>
                        <?php if ($blog['featured_image']): ?>
                        <div class="current-image">
                            <img src="<?php echo htmlspecialchars($blog['featured_image']); ?>" class="preview-image">
                            <small class="text-muted d-block mt-1">Current image</small>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="featured_image" id="featured_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" <?php echo empty($blog['featured_image']) ? 'required' : ''; ?>>
                        <img id="imagePreview" class="preview-image" style="display:none;">
                        <small class="text-muted"><?php echo empty($blog['featured_image']) ? 'Required — this post has no cover yet. ' : 'Upload only to replace the current cover. '; ?>Landscape, about 1200×800.</small>
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
                <a href="categories.php" class="small d-inline-block mt-2"><i class="fas fa-folder-plus me-1"></i>Manage categories</a>
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

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-cubes me-2"></i> Content Blocks</span>
        <button type="button" class="btn-primary-custom" style="width:auto;padding:8px 16px;" onclick="openBlockModal()">
            <i class="fas fa-plus me-1"></i> Add Block
        </button>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Build the post from images, galleries, YouTube videos, links, headings, paragraphs, quotes,
            CTA buttons (WhatsApp / email / link) and FAQs. When at least one block exists here, blocks are
            shown on the public page <strong>instead of</strong> the legacy Content field above. Drag <i class="fas fa-grip-vertical"></i> to reorder.
        </p>
        <div id="blocksList" class="blocks-list">
            <?php foreach ($blog['sections'] as $s): $meta = $blockTypes[$s['section_type']] ?? ['icon' => 'fa-cube', 'label' => $s['section_type']]; ?>
            <div class="block-row" data-id="<?php echo (int) $s['id']; ?>" data-block='<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES); ?>'>
                <span class="block-drag"><i class="fas fa-grip-vertical"></i></span>
                <span class="block-type-badge"><i class="fas <?php echo htmlspecialchars($meta['icon']); ?>"></i> <?php echo htmlspecialchars($meta['label']); ?></span>
                <span class="block-preview"><?php echo htmlspecialchars(dazu_block_preview($s)); ?></span>
                <span class="block-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="editBlock(this)"><i class="fas fa-edit"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteBlock(<?php echo (int) $s['id']; ?>)"><i class="fas fa-trash"></i></button>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="text-muted small fst-italic mt-2 mb-0" id="noBlocksMsg" style="<?php echo empty($blog['sections']) ? '' : 'display:none;'; ?>">
            No blocks yet. Click "Add Block" to start.
        </p>
    </div>
</div>

<!-- Add/Edit Block Modal -->
<div class="modal fade" id="blockModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="blockModalTitle">Add Block</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="blockSectionId" value="">
        <div class="mb-3">
            <label class="form-label">Block Type</label>
            <select id="blockType" class="form-select" onchange="renderBlockFields()">
                <option value="heading">Heading</option>
                <option value="paragraph">Paragraph</option>
                <option value="image">Image</option>
                <option value="image_gallery">Image Gallery</option>
                <option value="youtube">YouTube Video</option>
                <option value="link">Link / URL</option>
                <option value="quote">Quote</option>
                <option value="cta">CTA Button (WhatsApp / Email / Link)</option>
                <option value="faq">FAQ</option>
            </select>
        </div>
        <div id="blockFields"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn-primary-custom" style="width:auto;" onclick="saveBlock()" id="saveBlockBtn">Save Block</button>
      </div>
    </div>
  </div>
</div>

<!-- Include Summernote CSS -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
<link href="https://code.jquery.com/jquery-3.6.0.min.js" rel="preload" as="script">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

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
                for (let i = 0; i < files.length; i++) {
                    const reader = new FileReader();
                    reader.onload = function() {
                        $('#content').summernote('insertImage', reader.result);
                    };
                    reader.readAsDataURL(files[i]);
                }
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

    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Updating...';
    submitBtn.disabled = true;

    try {
        let content = document.getElementById('content').value;
        try {
            content = $('#content').summernote('code');
        } catch (summernoteError) {
            console.error('Summernote unavailable, falling back to raw textarea value:', summernoteError);
        }
        formData.set('content', content);

        const response = await fetch(url('api/blog/update.php'), {
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

<script>
const BLOG_ID = <?php echo (int) $blog['id']; ?>;
const blockModalEl = document.getElementById('blockModal');
const blockModal = new bootstrap.Modal(blockModalEl);
let currentGalleryImages = []; // paths kept when editing an image_gallery block

const BLOCK_LABELS = {
    heading: 'Heading', paragraph: 'Paragraph', image: 'Image', image_gallery: 'Image Gallery',
    youtube: 'YouTube Video', link: 'Link / URL', quote: 'Quote', cta: 'CTA Button', faq: 'FAQ'
};

function openBlockModal() {
    document.getElementById('blockSectionId').value = '';
    document.getElementById('blockType').value = 'heading';
    document.getElementById('blockType').disabled = false;
    document.getElementById('blockModalTitle').textContent = 'Add Block';
    currentGalleryImages = [];
    renderBlockFields();
    blockModal.show();
}

function editBlock(btn) {
    const row = btn.closest('.block-row');
    const data = JSON.parse(row.getAttribute('data-block'));
    document.getElementById('blockSectionId').value = data.id;
    document.getElementById('blockType').value = data.section_type;
    document.getElementById('blockType').disabled = true; // keep it simple: type is fixed once created
    document.getElementById('blockModalTitle').textContent = 'Edit ' + (BLOCK_LABELS[data.section_type] || 'Block');
    currentGalleryImages = data.gallery_images ? JSON.parse(data.gallery_images) : [];
    renderBlockFields(data);
    blockModal.show();
}

function deleteBlock(id) {
    if (!confirm('Delete this block? This cannot be undone.')) return;
    const formData = new FormData();
    formData.set('section_id', id);
    fetch(url('api/blog/delete_section.php'), { method: 'POST', body: formData })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        })
        .catch(() => alert('An error occurred. Please try again.'));
}

function renderBlockFields(data) {
    data = data || {};
    const type = document.getElementById('blockType').value;
    const c = document.getElementById('blockFields');
    const esc = (s) => (s || '').toString().replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

    let html = '';
    switch (type) {
        case 'heading':
            html = `<div class="mb-3"><label class="form-label">Heading text</label>
                    <input type="text" class="form-control" id="f_title" value="${esc(data.title)}" placeholder="e.g. Why this matters"></div>`;
            break;

        case 'paragraph':
            html = `<div class="mb-3"><label class="form-label">Paragraph text</label>
                    <textarea class="form-control" id="f_content" rows="5" placeholder="Write a paragraph...">${esc(data.content)}</textarea></div>`;
            break;

        case 'image':
            html = `<div class="mb-3"><label class="form-label">Image</label>
                    ${data.media_url ? `<div class="mb-2"><img src="${esc(data.media_url)}" style="max-width:200px;border-radius:6px;border:1px solid #e0e0e0;"></div>` : ''}
                    <input type="file" class="form-control" id="f_media_file" accept="image/*">
                    <small class="text-muted">${data.media_url ? 'Leave blank to keep the current image.' : ''}</small></div>
                    <div class="mb-3"><label class="form-label">Caption (optional)</label>
                    <input type="text" class="form-control" id="f_title" value="${esc(data.title)}"></div>`;
            break;

        case 'image_gallery':
            html = `<div class="mb-3"><label class="form-label">Gallery heading (optional)</label>
                    <input type="text" class="form-control" id="f_title" value="${esc(data.title)}"></div>
                    <div class="mb-3"><label class="form-label">Existing images</label>
                    <div id="galleryThumbs"></div></div>
                    <div class="mb-3"><label class="form-label">Add images</label>
                    <input type="file" class="form-control" id="f_gallery_files" accept="image/*" multiple></div>`;
            break;

        case 'youtube':
            html = `<div class="mb-3"><label class="form-label">YouTube URL</label>
                    <input type="url" class="form-control" id="f_media_url" value="${esc(data.media_url)}" placeholder="https://www.youtube.com/watch?v=..."></div>
                    <div class="mb-3"><label class="form-label">Caption (optional)</label>
                    <input type="text" class="form-control" id="f_title" value="${esc(data.title)}"></div>`;
            break;

        case 'link':
            html = `<div class="mb-3"><label class="form-label">URL</label>
                    <input type="url" class="form-control" id="f_media_url" value="${esc(data.media_url)}" placeholder="https://example.com"></div>
                    <div class="mb-3"><label class="form-label">Link text</label>
                    <input type="text" class="form-control" id="f_link_label" value="${esc(data.link_label)}" placeholder="e.g. Read the full report"></div>`;
            break;

        case 'quote':
            html = `<div class="mb-3"><label class="form-label">Quote text</label>
                    <textarea class="form-control" id="f_content" rows="3">${esc(data.content)}</textarea></div>
                    <div class="mb-3"><label class="form-label">Attribution (optional)</label>
                    <input type="text" class="form-control" id="f_title" value="${esc(data.title)}" placeholder="e.g. Jane Doe, CEO"></div>`;
            break;

        case 'cta':
            html = `<div class="mb-3"><label class="form-label">CTA type</label>
                    <select class="form-select" id="f_cta_type" onchange="updateCtaPlaceholder()">
                        <option value="whatsapp" ${data.cta_type === 'whatsapp' ? 'selected' : ''}>WhatsApp</option>
                        <option value="email" ${data.cta_type === 'email' ? 'selected' : ''}>Email</option>
                        <option value="link" ${data.cta_type === 'link' ? 'selected' : ''}>Link</option>
                    </select></div>
                    <div class="mb-3"><label class="form-label" id="ctaValueLabel">Value</label>
                    <input type="text" class="form-control" id="f_cta_value" value="${esc(data.cta_value)}"></div>
                    <div class="mb-3"><label class="form-label">Button text</label>
                    <input type="text" class="form-control" id="f_cta_label" value="${esc(data.cta_label)}" placeholder="e.g. Chat on WhatsApp"></div>`;
            break;

        case 'faq':
            html = `<div class="mb-2"><label class="form-label">Questions &amp; answers</label></div>
                    <div id="faqRows"></div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addFaqRow()"><i class="fas fa-plus me-1"></i>Add question</button>`;
            break;
    }
    c.innerHTML = html;

    if (type === 'cta') updateCtaPlaceholder();

    if (type === 'image_gallery') renderGalleryThumbs();

    if (type === 'faq') {
        const items = data.faq_items ? JSON.parse(data.faq_items) : [];
        if (items.length === 0) items.push({ question: '', answer: '' });
        items.forEach(item => addFaqRow(item.question, item.answer));
    }
}

function updateCtaPlaceholder() {
    const t = document.getElementById('f_cta_type').value;
    const label = document.getElementById('ctaValueLabel');
    const input = document.getElementById('f_cta_value');
    if (t === 'whatsapp') { label.textContent = 'WhatsApp number (with country code)'; input.placeholder = 'e.g. 254712345678'; }
    else if (t === 'email') { label.textContent = 'Email address'; input.placeholder = 'e.g. info@dazuhub.co.ke'; }
    else { label.textContent = 'URL'; input.placeholder = 'https://example.com'; }
}

function renderGalleryThumbs() {
    const wrap = document.getElementById('galleryThumbs');
    if (!wrap) return;
    if (currentGalleryImages.length === 0) {
        wrap.innerHTML = '<p class="text-muted small mb-0">No images yet — add some below.</p>';
        return;
    }
    wrap.innerHTML = currentGalleryImages.map((src, i) =>
        `<span class="gallery-thumb"><img src="${src}"><button type="button" class="remove-thumb" onclick="removeGalleryThumb(${i})">&times;</button></span>`
    ).join('');
}

function removeGalleryThumb(i) {
    currentGalleryImages.splice(i, 1);
    renderGalleryThumbs();
}

function addFaqRow(q, a) {
    const wrap = document.getElementById('faqRows');
    const div = document.createElement('div');
    div.className = 'faq-row';
    div.innerHTML = `<div style="flex:1">
            <input type="text" class="form-control mb-1 faq-q" placeholder="Question" value="${(q || '').replace(/"/g, '&quot;')}">
            <textarea class="form-control faq-a" rows="2" placeholder="Answer">${a || ''}</textarea>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.faq-row').remove()"><i class="fas fa-times"></i></button>`;
    wrap.appendChild(div);
}

async function saveBlock() {
    const type = document.getElementById('blockType').value;
    const sectionId = document.getElementById('blockSectionId').value;
    const formData = new FormData();
    formData.set('blog_id', BLOG_ID);
    formData.set('section_type', type);
    if (sectionId) formData.set('section_id', sectionId);

    const val = (id) => { const el = document.getElementById(id); return el ? el.value : ''; };

    switch (type) {
        case 'heading':
            formData.set('title', val('f_title'));
            break;
        case 'paragraph':
        case 'quote':
            formData.set('content', val('f_content'));
            formData.set('title', val('f_title'));
            break;
        case 'image': {
            formData.set('title', val('f_title'));
            const f = document.getElementById('f_media_file').files[0];
            if (f) formData.set('media_file', f);
            break;
        }
        case 'image_gallery': {
            formData.set('title', val('f_title'));
            formData.set('gallery_images_touched', '1');
            currentGalleryImages.forEach(src => formData.append('existing_gallery_images[]', src));
            const files = document.getElementById('f_gallery_files').files;
            for (let i = 0; i < files.length; i++) formData.append('gallery_files[]', files[i]);
            break;
        }
        case 'youtube':
            formData.set('media_url', val('f_media_url'));
            formData.set('title', val('f_title'));
            break;
        case 'link':
            formData.set('media_url', val('f_media_url'));
            formData.set('link_label', val('f_link_label'));
            break;
        case 'cta':
            formData.set('cta_type', val('f_cta_type'));
            formData.set('cta_value', val('f_cta_value'));
            formData.set('cta_label', val('f_cta_label'));
            break;
        case 'faq': {
            document.querySelectorAll('#faqRows .faq-row').forEach(row => {
                formData.append('faq_question[]', row.querySelector('.faq-q').value);
                formData.append('faq_answer[]', row.querySelector('.faq-a').value);
            });
            break;
        }
    }

    const btn = document.getElementById('saveBlockBtn');
    const originalText = btn.textContent;
    btn.textContent = 'Saving...';
    btn.disabled = true;

    try {
        const endpoint = sectionId ? 'api/blog/update_section.php' : 'api/blog/add_section.php';
        const response = await fetch(url(endpoint), { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
    }
}

// Drag-to-reorder
const blocksListEl = document.getElementById('blocksList');
if (blocksListEl) {
    Sortable.create(blocksListEl, {
        handle: '.block-drag',
        animation: 150,
        onEnd: function () {
            const order = Array.from(blocksListEl.querySelectorAll('.block-row')).map(r => r.getAttribute('data-id'));
            const formData = new FormData();
            order.forEach(id => formData.append('order[]', id));
            fetch(url('api/blog/reorder_sections.php'), { method: 'POST', body: formData }).catch(() => {});
        }
    });
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';
?>