<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/db_connect.php';
require_once __DIR__ . '/../../app/controllers/ProductController.php';

$productController = new ProductController($pdo);
$page_title = 'Products - Ismano';
$page_description = 'Browse our collection of premium products';

$category_id = $_GET['category'] ?? null;
$search = $_GET['search'] ?? null;

if ($search) {
    $products = $productController->search($search);
} else {
    $products = $productController->getAll('active', 12, 0, $category_id);
}

$categories = $productController->getAllCategories();
$featured = $productController->getFeatured(4);

ob_start();
?>

<section class="section">
    <div class="container">
        <!-- Page Header -->
        <div class="text-center mb-5 reveal">
            <span class="eyebrow">Shop</span>
            <h1 class="display-4 fw-bold mb-3">Our Products</h1>
            <p class="lead text-muted">Quality products at competitive prices</p>
        </div>
        
        <!-- Search -->
        <div class="row mb-5 reveal reveal-delay-1">
            <div class="col-md-6 mx-auto">
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-lg" 
                           placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn--primary">Search</button>
                </form>
            </div>
        </div>
        
        <!-- Categories & Products -->
        <div class="row">
            <!-- Sidebar Categories -->
            <div class="col-lg-3 mb-4 reveal reveal-delay-2">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-3">
                        <h5 class="mb-0">Categories</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <a href="?" class="text-decoration-none <?php echo !$category_id ? 'text-primary fw-bold' : 'text-muted'; ?>">
                                    All Products
                                </a>
                            </li>
                            <?php foreach ($categories as $cat): ?>
                                <li class="mb-2">
                                    <a href="?category=<?php echo $cat['id']; ?>" 
                                       class="text-decoration-none <?php echo $category_id == $cat['id'] ? 'text-primary fw-bold' : 'text-muted'; ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Products Grid -->
            <div class="col-lg-9">
                <?php if (empty($products)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <p>No products found.</p>
                        <a href="?" class="btn btn--primary">View All Products</a>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($products as $index => $product): ?>
                            <div class="col-md-6 col-lg-4 reveal reveal-delay-<?php echo min(4, ($index % 4) + 1); ?>">
                                <div class="product-card h-100">
                                    <div class="product-image">
                                        <?php if ($product['featured_image']): ?>
                                            <img src="<?php echo $product['featured_image']; ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center h-100">
                                                <i class="fas fa-image fa-3x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                                            <span class="sale-badge">Sale</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="product-info p-3">
                                        <h3 class="h6 mb-1">
                                            <a href="details.php?slug=<?php echo $product['slug']; ?>" class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </a>
                                        </h3>
                                        
                                        <?php if ($product['category_name']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($product['category_name']); ?></small>
                                        <?php endif; ?>
                                        
                                        <div class="mt-2">
                                            <?php if ($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                                                <span class="text-muted text-decoration-line-through me-2">$<?php echo number_format($product['compare_price'], 2); ?></span>
                                                <span class="text-primary fw-bold">$<?php echo number_format($product['price'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-primary fw-bold">$<?php echo number_format($product['price'], 2); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mt-3">
                                            <button class="btn btn--primary btn-sm w-100 add-to-cart" 
                                                    data-product="<?php echo $product['id']; ?>">
                                                <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Toast Notification -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
    <div id="cartToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="3000">
        <div class="toast-header">
            <strong class="me-auto">Shopping Cart</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body" id="toastMessage"></div>
    </div>
</div>

<style>
.product-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border: 1px solid #eef2f6;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.1);
}

.product-image {
    position: relative;
    height: 220px;
    overflow: hidden;
    background: #f8f9fa;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.product-card:hover .product-image img {
    transform: scale(1.05);
}

.sale-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: #e74c3c;
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    z-index: 1;
}

.product-info h3 a:hover {
    color: var(--brand-primary) !important;
}
</style>

<script>
document.querySelectorAll('.add-to-cart').forEach(button => {
    button.addEventListener('click', async function() {
        const productId = this.dataset.product;
        
        try {
            const response = await fetch('/Realestate/public/api/cart/add.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId, quantity: 1 })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('Item added to cart!', 'success');
                updateCartCount();
            } else if (data.require_login) {
                if (confirm('Please login to add items to cart. Go to login page?')) {
                    window.location.href = '/Realestate/public/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
                }
            } else {
                showToast(data.message || 'Failed to add item', 'error');
            }
        } catch (error) {
            showToast('An error occurred', 'error');
        }
    });
});

function showToast(message, type = 'success') {
    const toast = document.getElementById('cartToast');
    const toastBody = document.getElementById('toastMessage');
    toastBody.innerHTML = message;
    toastBody.className = `toast-body text-${type === 'success' ? 'success' : 'danger'}`;
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
}

function updateCartCount() {
    fetch('/Realestate/public/api/cart/count.php')
        .then(res => res.json())
        .then(data => {
            const cartCount = document.querySelector('.cart-count');
            if (cartCount && data.count > 0) {
                cartCount.textContent = data.count;
                cartCount.style.display = 'inline';
            }
        });
}
</script>

<?php
$content = ob_get_clean();
$use_home_navbar = false;
require_once __DIR__ . '/../templates/public/layout.php';
?>