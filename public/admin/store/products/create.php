<?php
require_once __DIR__ . '/../../../../app/bootstrap.php';
require_once __DIR__ . '/../../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../../app/controllers/StoreController.php';
require_once __DIR__ . '/../../../../app/helpers/uploads.php';

$storeController = new StoreController($pdo);
$page_title = 'Add Product';
$breadcrumbs = [
    ['label' => 'Store', 'url' => 'index.php'],
    ['label' => 'Products', 'url' => 'index.php'],
    ['label' => 'Add New', 'active' => true]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash']['error'] = 'Invalid CSRF token';
        header('Location: index.php');
        exit();
    }
    
    $result = $storeController->createProduct($_POST, $_FILES['featured_image'] ?? null);
    
    if ($result['success']) {
        $_SESSION['flash']['success'] = $result['message'];
        header('Location: index.php');
    } else {
        $_SESSION['flash']['error'] = $result['message'];
    }
    exit();
}

$categories = $storeController->getAllCategories(true);

ob_start();
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Add New Product</h5>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="6"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Price *</label>
                                <input type="number" name="price" class="form-control" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Compare Price (Original)</label>
                                <input type="number" name="compare_price" class="form-control" step="0.01">
                                <small class="text-muted">Leave empty if no sale</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">SKU</label>
                                <input type="text" name="sku" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Stock Quantity</label>
                                <input type="number" name="stock_quantity" class="form-control" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Meta Title (SEO)</label>
                        <input type="text" name="meta_title" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Meta Description</label>
                        <textarea name="meta_description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Featured Image</label>
                        <input type="file" name="featured_image" class="form-control" accept="image/*">
                        <small class="text-muted">Recommended size: 600x600px</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_featured" class="form-check-input" value="1" id="isFeatured">
                            <label class="form-check-label" for="isFeatured">Feature this product</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="draft">Draft</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <hr>
            <button type="submit" class="btn btn-primary">Save Product</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../../templates/admin/layout.php';
?>