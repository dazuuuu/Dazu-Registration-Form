<?php
require_once __DIR__ . '/../../../../app/bootstrap.php';
require_once __DIR__ . '/../../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../../app/controllers/StoreController.php';
require_once __DIR__ . '/../../../../app/helpers/uploads.php';

$storeController = new StoreController($pdo);
$page_title = 'Store Categories';
$breadcrumbs = [
    ['label' => 'Store', 'url' => '../products/'],
    ['label' => 'Categories', 'active' => true]
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash']['error'] = 'Invalid CSRF token';
        header('Location: index.php');
        exit();
    }
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $result = $storeController->createCategory(
                    $_POST['name'], 
                    $_POST['description'] ?? null,
                    $_FILES['category_image'] ?? null
                );
                $_SESSION['flash'][$result['success'] ? 'success' : 'error'] = $result['message'];
                break;
            case 'update':
                $result = $storeController->updateCategory(
                    $_POST['id'],
                    $_POST,
                    $_FILES['category_image'] ?? null
                );
                $_SESSION['flash'][$result['success'] ? 'success' : 'error'] = $result['message'];
                break;
            case 'delete':
                $result = $storeController->deleteCategory($_POST['id']);
                $_SESSION['flash'][$result['success'] ? 'success' : 'error'] = $result['message'];
                break;
        }
        header('Location: index.php');
        exit();
    }
}

$categories = $storeController->getAllCategories(false);

ob_start();
?>

<style>
.category-image {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 8px;
}
</style>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Add New Category</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label class="form-label">Category Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Category Image</label>
                        <input type="file" name="category_image" class="form-control" accept="image/*">
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Add Category</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">All Categories</h5>
            </div>
            <div class="card-body">
                <?php if (empty($categories)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                        <p>No categories yet. Create your first category!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Slug</th>
                                    <th>Products</th>
                                    <th>Status</th>
                                    <th>Sort</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td>
                                            <?php if ($cat['image_path']): ?>
                                                <img src="<?php echo $cat['image_path']; ?>" class="category-image">
                                            <?php else: ?>
                                                <div class="category-image bg-light d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-folder text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($cat['name']); ?></strong>
                                            <?php if ($cat['description']): ?>
                                                <br><small class="text-muted"><?php echo substr(htmlspecialchars($cat['description']), 0, 50); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?php echo $cat['slug']; ?></code></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $storeController->getCategoryProductCount($cat['id']); ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-<?php echo $cat['is_active'] ? 'success' : 'danger'; ?>" 
                                                    onclick="toggleStatus(<?php echo $cat['id']; ?>, <?php echo $cat['is_active'] ? 0 : 1; ?>)">
                                                <?php echo $cat['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </button>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm" style="width: 70px;" 
                                                   value="<?php echo $cat['sort_order']; ?>" 
                                                   data-id="<?php echo $cat['id']; ?>" 
                                                   onchange="updateSortOrder(this)">
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" id="edit_sort_order" class="form-control" value="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Image (optional)</label>
                        <input type="file" name="category_image" class="form-control" accept="image/*">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="edit_is_active" class="form-check-input" value="1">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCategory(category) {
    document.getElementById('edit_id').value = category.id;
    document.getElementById('edit_name').value = category.name;
    document.getElementById('edit_description').value = category.description || '';
    document.getElementById('edit_sort_order').value = category.sort_order || 0;
    document.getElementById('edit_is_active').checked = category.is_active == 1;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deleteCategory(id, name) {
    if (confirm(`Are you sure you want to delete category "${name}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleStatus(id, newStatus) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="${id}">
        <input type="hidden" name="is_active" value="${newStatus}">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
    `;
    document.body.appendChild(form);
    form.submit();
}

function updateSortOrder(input) {
    const id = input.dataset.id;
    const sortOrder = input.value;
    
    fetch('/Realestate/public/api/store/update_category_sort.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, sort_order: sortOrder })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Failed to update sort order');
        }
    });
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../../templates/admin/layout.php';
?>