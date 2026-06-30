<?php
require_once __DIR__ . '/../../../../app/bootstrap.php';
require_once __DIR__ . '/../../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../../app/controllers/StoreController.php';
require_once __DIR__ . '/../../../../app/helpers/uploads.php';

$storeController = new StoreController($pdo);
$page_title = 'Edit Product';
$breadcrumbs = [
    ['label' => 'Store', 'url' => 'index.php'],
    ['label' => 'Products', 'url' => 'index.php'],
    ['label' => 'Edit', 'active' => true]
];

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit();
}

$product = $storeController->getProductDetails($id);
if (!$product) {
    $_SESSION['flash']['error'] = 'Product not found';
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash']['error'] = 'Invalid CSRF token';
        header('Location: index.php');
        exit();
    }
    
    $result = $storeController->updateProduct($id, $_POST, $_FILES['featured_image'] ?? null);
    
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
        <h5 class="mb-0">Edit Product: <?php echo htmlspecialchars($product['name']); ?></h5>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label">Product Name *</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="6"><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Price *</label>
                                <input type="number" name="price" class="form-control" step="0.01" value="<?php echo $product['price']; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Compare Price</label>
                                <input type="number" name="compare_price" class="form-control" step="0.01" value="<?php echo $product['compare_price']; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">SKU</label>
                                <input type="text" name="sku" class="form-control" value="<?php echo htmlspecialchars($product['sku']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Stock Quantity</label>
                                <input type="number" name="stock_quantity" class="form-control" value="<?php echo $product['stock_quantity']; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Meta Title</label>
                        <input type="text" name="meta_title" class="form-control" value="<?php echo htmlspecialchars($product['meta_title']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Meta Description</label>
                        <textarea name="meta_description" class="form-control" rows="2"><?php echo htmlspecialchars($product['meta_description']); ?></textarea>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Current Image</label>
                        <?php if ($product['featured_image']): ?>
                            <div class="mb-2">
                                <img src="<?php echo $product['featured_image']; ?>" style="max-width: 100%; border-radius: 8px;">
                            </div>
                        <?php endif; ?>
                        <label class="form-label">New Image</label>
                        <input type="file" name="featured_image" class="form-control" accept="image/*">
                        <small class="text-muted">Leave empty to keep current image</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="<?php echo $product['sort_order']; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_featured" class="form-check-input" value="1" id="isFeatured" <?php echo $product['is_featured'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="isFeatured">Feature this product</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="draft" <?php echo $product['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="inactive" <?php echo $product['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <hr>
            <button type="submit" class="btn btn-primary">Update Product</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../../templates/admin/layout.php';
?>