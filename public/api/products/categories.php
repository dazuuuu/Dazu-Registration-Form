<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/controllers/ProductController.php';

$productController = new ProductController($pdo);
$page_title = 'Product Categories';
$breadcrumbs = [
    ['label' => 'Products', 'url' => 'index.php'],
    ['label' => 'Categories', 'active' => true]
];

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $result = $productController->createCategory($_POST['name'], $_POST['description'] ?? null, $_POST['parent_id'] ?? null);
                $_SESSION['flash'][$result['success'] ? 'success' : 'error'] = $result['message'];
                break;
            case 'update':
                $result = $productController->updateCategory($_POST['id'], $_POST);
                $_SESSION['flash']['success'] = $result ? 'Category updated' : 'Update failed';
                break;
            case 'delete':
                $result = $productController->deleteCategory($_POST['id']);
                $_SESSION['flash'][$result['success'] ? 'success' : 'error'] = $result['message'];
                break;
        }
        header('Location: categories.php');
        exit();
    }
}

$categories = $productController->getAllCategories();

ob_start();
?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Add New Category</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="create">
            <div class="col-md-4">
                <input type="text" name="name" class="form-control" placeholder="Category Name" required>
            </div>
            <div class="col-md-5">
                <input type="text" name="description" class="form-control" placeholder="Description (optional)">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">Add Category</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">All Categories</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Description</th>
                        <th>Products Count</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><?php echo $cat['id']; ?></td>
                        <td><?php echo htmlspecialchars($cat['name']); ?></td>
                        <td><code><?php echo $cat['slug']; ?></code></td>
                        <td><?php echo htmlspecialchars($cat['description'] ?? '-'); ?></td>
                        <td>
                            <?php
                            $sql = "SELECT COUNT(*) FROM products WHERE category_id = :id";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([':id' => $cat['id']]);
                            echo $stmt->fetchColumn();
                            ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name']); ?>', '<?php echo htmlspecialchars($cat['description'] ?? ''); ?>')">
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
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
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
function editCategory(id, name, description) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deleteCategory(id, name) {
    if (confirm(`Are you sure you want to delete category "${name}"? This will not delete products but will remove the category assignment.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';
?>