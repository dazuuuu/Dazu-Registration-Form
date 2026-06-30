<?php
require_once __DIR__ . '/../../../../app/bootstrap.php';
require_once __DIR__ . '/../../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../../app/controllers/StoreController.php';

$storeController = new StoreController($pdo);
$page_title = 'Store Products';
$breadcrumbs = [
    ['label' => 'Store', 'active' => true],
    ['label' => 'Products', 'active' => true]
];

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $result = $storeController->deleteProduct($_GET['delete']);
    $_SESSION['flash'][$result['success'] ? 'success' : 'error'] = $result['message'];
    header('Location: index.php');
    exit();
}

$products = $storeController->getAllProducts(null, 100, 0);
$categories = $storeController->getAllCategories(false);

ob_start();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">All Products</h5>
        <a href="create.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Add New Product
        </a>
    </div>
    <div class="card-body">
        <?php if (empty($products)): ?>
            <div class="text-center py-5">
                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                <p>No products found.</p>
                <a href="create.php" class="btn btn-primary">Add Your First Product</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Featured</th>
                            <th>Views</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td width="80">
                                    <?php if ($product['featured_image']): ?>
                                        <img src="<?php echo $product['featured_image']; ?>" 
                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px;">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; border-radius: 6px;">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                    <?php if ($product['sku']): ?>
                                        <br><small class="text-muted">SKU: <?php echo $product['sku']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['category_name'] ?? '-'); ?></td>
                                <td>
                                    $<?php echo number_format($product['price'], 2); ?>
                                    <?php if ($product['compare_price']): ?>
                                        <br><small class="text-muted text-decoration-line-through">$<?php echo number_format($product['compare_price'], 2); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $product['stock_quantity'] > 0 ? 'success' : 'danger'; ?>">
                                        <?php echo $product['stock_quantity']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $product['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($product['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($product['is_featured']): ?>
                                        <i class="fas fa-star text-warning"></i>
                                    <?php else: ?>
                                        <i class="far fa-star text-muted"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($product['view_count']); ?></td>
                                <td>
                                    <a href="edit.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
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

<script>
function confirmDelete(id, name) {
    if (confirm(`Are you sure you want to delete product "${name}"?`)) {
        window.location.href = `?delete=${id}`;
    }
}
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../../templates/admin/layout.php';
?>