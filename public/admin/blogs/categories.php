<?php
$page_title = 'Blog Categories';
$breadcrumbs = [
    ['label' => 'Blog', 'url' => 'index.php'],
    ['label' => 'Categories', 'active' => true],
];

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
    header('Location: ' . url('auth/login.php'));
    exit();
}

require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
require_once __DIR__ . '/../../../app/v1/models/BlogModel.php';

$blogModel = new BlogModel($pdo);
$categories = $blogModel->getAllCategories();
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    .page-header h2 { margin: 0; font-size: 1.5rem; font-weight: 600; }
    .card-table {
        background: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 20px;
    }
    .card-header-table {
        padding: 15px 20px;
        background: #fafafa;
        border-bottom: 1px solid #e0e0e0;
        font-weight: 600;
    }
    .color-swatch {
        width: 22px;
        height: 22px;
        border-radius: 4px;
        border: 1px solid #ddd;
        display: inline-block;
        vertical-align: middle;
    }
    .btn-icon {
        border: 1px solid #ccc;
        background: #fff;
        width: 34px;
        height: 34px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    .btn-icon:hover { background: #f5f5f5; }
    .btn-icon.btn-delete:hover { background: #fee; border-color: #f88; color: #c00; }
    .create-btn, .btn-primary-custom {
        background: #1a1a1a;
        border: 1px solid #1a1a1a;
        color: #fff;
        padding: 10px 20px;
        border-radius: 6px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .create-btn:hover, .btn-primary-custom:hover { background: #333; color: #fff; }
</style>

<div class="page-header">
    <h2>Blog Categories</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="index.php" class="create-btn" style="background:#fff;color:#1a1a1a;border-color:#ccc;">
            <i class="fas fa-arrow-left"></i> Back to Posts
        </a>
        <button type="button" class="create-btn" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
            <i class="fas fa-plus"></i> New Category
        </button>
    </div>
</div>

<div class="card-table">
    <div class="card-header-table"><i class="fas fa-folder me-2"></i> All Categories</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th width="60">ID</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th width="80">Color</th>
                    <th width="90">Posts</th>
                    <th width="120">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No categories yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($categories as $category):
                        $postCount = $blogModel->countBlogsInCategory((int) $category['id']);
                    ?>
                    <tr>
                        <td><?php echo (int) $category['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                            <?php if (!empty($category['description'])): ?>
                                <div class="text-muted small"><?php echo htmlspecialchars($category['description']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo htmlspecialchars($category['slug']); ?></code></td>
                        <td><span class="color-swatch" style="background:<?php echo htmlspecialchars($category['color'] ?? '#667eea'); ?>"></span></td>
                        <td><?php echo $postCount; ?></td>
                        <td>
                            <button type="button" class="btn-icon btn-edit"
                                    onclick='editCategory(<?php echo json_encode($category, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                    title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn-icon btn-delete"
                                    onclick="deleteCategory(<?php echo (int) $category['id']; ?>, <?php echo $postCount; ?>)"
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="createCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="createCategoryForm">
                <div class="modal-header">
                    <h5 class="modal-title">New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <input type="color" name="color" class="form-control form-control-color" value="#667eea">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Create Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editCategoryForm">
                <input type="hidden" name="id" id="editCategoryId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" id="editCategoryName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editCategoryDescription" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <input type="color" name="color" id="editCategoryColor" class="form-control form-control-color">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
async function postCategoryJson(endpoint, payload) {
    const response = await fetch(url(endpoint), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    return response.json();
}

document.getElementById('createCategoryForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const payload = Object.fromEntries(new FormData(form).entries());
    try {
        const result = await postCategoryJson('api/blog/create_category.php', payload);
        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error(error);
        alert('An error occurred while creating the category.');
    }
});

function editCategory(category) {
    document.getElementById('editCategoryId').value = category.id;
    document.getElementById('editCategoryName').value = category.name;
    document.getElementById('editCategoryDescription').value = category.description || '';
    document.getElementById('editCategoryColor').value = category.color || '#667eea';
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}

document.getElementById('editCategoryForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const payload = Object.fromEntries(new FormData(form).entries());
    try {
        const result = await postCategoryJson('api/blog/update_category.php', payload);
        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error(error);
        alert('An error occurred while updating the category.');
    }
});

async function deleteCategory(id, postCount) {
    if (postCount > 0) {
        alert('This category has blog posts assigned. Reassign or delete those posts first.');
        return;
    }
    if (!confirm('Delete this category? This cannot be undone.')) {
        return;
    }
    try {
        const result = await postCategoryJson('api/blog/delete_category.php', { id });
        if (result.success) {
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error(error);
        alert('An error occurred while deleting the category.');
    }
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';
