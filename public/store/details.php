<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/db_connect.php';
require_once __DIR__ . '/../../app/controllers/StoreController.php';

$storeController = new StoreController($pdo);
$slug = $_GET['slug'] ?? null;

if (!$slug) {
    header('Location: index.php');
    exit();
}

$product = $storeController->getProductDetails($slug);

if (!$product) {
    header('Location: index.php');
    exit();
}

$page_title = $product['name'] . ' - Ismano Store';
$page_description = $product['meta_description'] ?: substr(strip_tags($product['description']), 0, 160);

ob_start();
?>

<section class="section">
    <div class="container">
        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/Ismano/public/store/">Store</a></li>
                <?php if ($product['category_name']): ?>
                    <li class="breadcrumb-item"><a href="?category=<?php echo $product['category_slug']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a></li>
                <?php endif; ?>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['name']); ?></li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- Product Image -->
            <div class="col-md-6 mb-4 reveal">
                <div class="product-gallery">
                    <?php if ($product['featured_image']): ?>
                        <img src="<?php echo $product['featured_image']; ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             class="img-fluid rounded shadow-sm">
                    <?php else: ?>
                        <div class="bg-light d-flex align-items-center justify-content-center rounded shadow-sm" style="height: 400px;">
                            <i class="fas fa-image fa-4x text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Product Info -->
            <div class="col-md-6 mb-4 reveal reveal-delay-1">
                <div class="product-info">
                    <?php if ($product['category_name']): ?>
                        <span class="badge bg-light text-dark mb-2"><?php echo htmlspecialchars($product['category_name']); ?></span>
                    <?php endif; ?>
                    
                    <h1 class="display-5 fw-bold mb-3"><?php echo htmlspecialchars($product['name']); ?></h1>
                    
                    <?php if ($product['sku']): ?>
                        <p class="text-muted">SKU: <?php echo $product['sku']; ?></p>
                    <?php endif; ?>
                    
                    <div class="price-container mb-4">
                        <?php if ($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                            <span class="text-muted text-decoration-line-through fs-5 me-3">$<?php echo number_format($product['compare_price'], 2); ?></span>
                            <span class="text-primary display-6 fw-bold">$<?php echo number_format($product['price'], 2); ?></span>
                            <span class="badge bg-danger ms-2">Save <?php echo round((($product['compare_price'] - $product['price']) / $product['compare_price']) * 100); ?>%</span>
                        <?php else: ?>
                            <span class="text-primary display-6 fw-bold">$<?php echo number_format($product['price'], 2); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="stock-info mb-4">
                        <?php if ($product['stock_quantity'] > 0): ?>
                            <span class="text-success">
                                <i class="fas fa-check-circle"></i> In Stock (<?php echo $product['stock_quantity']; ?> available)
                            </span>
                        <?php else: ?>
                            <span class="text-danger">
                                <i class="fas fa-times-circle"></i> Out of Stock
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="description mb-4">
                        <h5>Description</h5>
                        <div class="product-description">
                            <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                        </div>
                    </div>
                    
                    <div class="quantity-selector mb-4 d-flex align-items-center gap-3">
                        <label class="fw-bold">Quantity:</label>
                        <div class="input-group" style="width: 130px;">
                            <button class="btn btn-outline-secondary" type="button" id="decrementQty">-</button>
                            <input type="number" id="productQty" class="form-control text-center" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>">
                            <button class="btn btn-outline-secondary" type="button" id="incrementQty">+</button>
                        </div>
                    </div>
                    
                    <div class="action-buttons d-flex gap-3">
                        <button class="btn btn--primary btn-lg flex-grow-1 add-to-cart-btn" 
                                data-product="<?php echo $product['id']; ?>"
                                <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-shopping-cart me-2"></i>
                            <?php echo $product['stock_quantity'] <= 0 ? 'Out of Stock' : 'Add to Cart'; ?>
                        </button>
                        <button class="btn btn-outline-secondary btn-lg save-for-later" data-product="<?php echo $product['id']; ?>">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if (!empty($product['related_products'])): ?>
            <div class="related-products mt-5">
                <h3 class="mb-4">Related Products</h3>
                <div class="row g-4">
                    <?php foreach ($product['related_products'] as $related): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="product-card h-100">
                                <div class="product-image">
                                    <a href="details.php?slug=<?php echo $related['slug']; ?>">
                                        <?php if ($related['featured_image']): ?>
                                            <img src="<?php echo $related['featured_image']; ?>" 
                                                 alt="<?php echo htmlspecialchars($related['name']); ?>">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center h-100">
                                                <i class="fas fa-image fa-2x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <div class="product-info p-3">
                                    <h3 class="h6 mb-1">
                                        <a href="details.php?slug=<?php echo $related['slug']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($related['name']); ?>
                                        </a>
                                    </h3>
                                    <div class="mt-2">
                                        <span class="text-primary fw-bold">$<?php echo number_format($related['price'], 2); ?></span>
                                    </div>
                                    <button class="btn btn--primary btn-sm w-100 mt-2 add-to-cart" data-product="<?php echo $related['id']; ?>">
                                        <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
.product-gallery img {
    width: 100%;
    object-fit: cover;
}

.product-description {
    line-height: 1.8;
    color: #555;
}

.product-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.3s ease;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border: 1px solid #eef2f6;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.product-image {
    height: 200px;
    overflow: hidden;
    background: #f8f9fa;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cart-toast {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
}
</style>

<script>
// Quantity selector
const qtyInput = document.getElementById('productQty');
const decrementBtn = document.getElementById('decrementQty');
const incrementBtn = document.getElementById('incrementQty');

if (decrementBtn) {
    decrementBtn.addEventListener('click', () => {
        let val = parseInt(qtyInput.value);
        if (val > 1) {
            qtyInput.value = val - 1;
        }
    });
}

if (incrementBtn) {
    incrementBtn.addEventListener('click', () => {
        let val = parseInt(qtyInput.value);
        let max = parseInt(qtyInput.getAttribute('max'));
        if (val < max) {
            qtyInput.value = val + 1;
        }
    });
}

// Add to cart
document.querySelector('.add-to-cart-btn')?.addEventListener('click', async function() {
    if (this.disabled) return;
    
    const productId = this.dataset.product;
    const quantity = parseInt(qtyInput.value);
    const originalText = this.innerHTML;
    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
    this.disabled = true;
    
    try {
        const response = await fetch('/Ismano/public/api/store/cart/add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId, quantity: quantity })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Item added to cart!', 'success');
            updateCartCount();
            this.innerHTML = '<i class="fas fa-check me-2"></i>Added!';
            setTimeout(() => {
                this.innerHTML = originalText;
                this.disabled = false;
            }, 2000);
        } else if (data.require_login) {
            if (confirm('Please login to add items to cart. Go to login page?')) {
                window.location.href = '/Ismano/public/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
            } else {
                this.innerHTML = originalText;
                this.disabled = false;
            }
        } else {
            showToast(data.message || 'Failed to add item', 'error');
            this.innerHTML = originalText;
            this.disabled = false;
        }
    } catch (error) {
        showToast('An error occurred', 'error');
        this.innerHTML = originalText;
        this.disabled = false;
    }
});

// Save for later
document.querySelector('.save-for-later')?.addEventListener('click', async function() {
    const productId = this.dataset.product;
    
    try {
        const response = await fetch('/Ismano/public/api/store/cart/save_for_later.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Saved for later!', 'success');
        } else if (data.require_login) {
            if (confirm('Please login to save items. Go to login page?')) {
                window.location.href = '/Ismano/public/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
            }
        } else {
            showToast(data.message || 'Failed to save', 'error');
        }
    } catch (error) {
        showToast('An error occurred', 'error');
    }
});

function showToast(message, type = 'success') {
    const toastHtml = `
        <div class="cart-toast toast align-items-center text-white bg-${type === 'success' ? 'success' : 'danger'} border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    const container = document.querySelector('.cart-toast-container') || (() => {
        const div = document.createElement('div');
        div.className = 'cart-toast-container';
        document.body.appendChild(div);
        return div;
    })();
    
    container.innerHTML = toastHtml;
    setTimeout(() => {
        const toast = container.querySelector('.toast');
        if (toast) toast.remove();
    }, 3000);
}

function updateCartCount() {
    fetch('/Ismano/public/api/store/cart/count.php')
        .then(res => res.json())
        .then(data => {
            const cartCount = document.querySelector('.cart-count');
            if (cartCount && data.count > 0) {
                cartCount.textContent = data.count;
                cartCount.style.display = 'inline';
            } else if (cartCount) {
                cartCount.style.display = 'none';
            }
        });
}
</script>

<?php
$content = ob_get_clean();
$use_home_navbar = false;
require_once __DIR__ . '/../templates/public/layout.php';
?>