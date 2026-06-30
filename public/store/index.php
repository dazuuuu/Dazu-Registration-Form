<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/db_connect.php';
require_once __DIR__ . '/../../app/controllers/StoreController.php';

$storeController = new StoreController($pdo);
$page_title = 'Store - ISMAN Company';
$page_description = 'Browse our quality engineering products and equipment';

$category_slug = $_GET['category'] ?? null;
$search = $_GET['search'] ?? null;
$sort = $_GET['sort'] ?? 'newest';

$category = null;
if ($category_slug) {
    $categories = $storeController->getAllCategories(true);
    foreach ($categories as $cat) {
        if ($cat['slug'] === $category_slug) {
            $category = $cat;
            break;
        }
    }
}

// Get products
try {
    $products = $storeController->getAllProducts('active', 12, 0, $category['id'] ?? null, $sort);
    $categories = $storeController->getAllCategories(true);
    $featured = $storeController->getFeaturedProducts(4);
} catch (Exception $e) {
    $products = [];
    $categories = [];
    $featured = [];
    error_log('Store error: ' . $e->getMessage());
}

ob_start();
?>

<style>
/* Store specific styles */
.store-section {
    padding: 60px 0;
    background: var(--color-surface);
}

.store-header {
    text-align: center;
    margin-bottom: 48px;
}

.store-header .eyebrow {
    color: var(--brand-primary, #0D9488);
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 2px;
    display: inline-block;
    margin-bottom: 12px;
}

.store-header h1 {
    font-size: 48px;
    font-weight: 700;
    margin-bottom: 16px;
    color: var(--color-text-heading);
}

.store-header p {
    font-size: 18px;
    color: var(--color-text-muted);
    max-width: 600px;
    margin: 0 auto;
}

/* Product Card */
.product-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: transparent;
}

.product-image {
    position: relative;
    height: 220px;
    overflow: hidden;
    background: var(--color-surface-alt);
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

.product-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    z-index: 1;
}

.product-badge.sale {
    background: #e74c3c;
    color: white;
}

.product-badge.featured {
    background: var(--brand-primary, #0D9488);
    color: white;
}

.product-badge.soldout {
    background: #333;
    color: white;
    left: 12px;
    right: auto;
}

.product-info {
    padding: 20px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.product-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--color-text-heading);
}

.product-title a {
    color: inherit;
    text-decoration: none;
    transition: color 0.2s ease;
}

.product-title a:hover {
    color: var(--brand-primary, #0D9488);
}

.product-category {
    font-size: 13px;
    color: var(--color-text-muted);
    margin-bottom: 12px;
}

.product-price {
    margin-bottom: 16px;
}

.current-price {
    font-size: 22px;
    font-weight: 700;
    color: var(--brand-primary, #0D9488);
}

.old-price {
    font-size: 14px;
    color: var(--color-text-muted);
    text-decoration: line-through;
    margin-left: 8px;
}

.product-stock {
    font-size: 12px;
    margin-bottom: 16px;
}

.stock-in {
    color: #27ae60;
}

.stock-out {
    color: #e74c3c;
}

.btn-add-cart {
    width: 100%;
    padding: 12px;
    background: var(--brand-primary, #0D9488);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-add-cart:hover:not(:disabled) {
    background: var(--brand-primary-deep, #0A766B);
    transform: translateY(-2px);
}

.btn-add-cart:disabled {
    background: #ccc;
    cursor: not-allowed;
}

/* Sidebar */
.store-sidebar .card {
    border: 1px solid var(--color-border);
    border-radius: 12px;
    margin-bottom: 24px;
}

.store-sidebar .card-header {
    background: var(--color-surface-alt);
    border-bottom: 1px solid var(--color-border);
    padding: 15px 20px;
    font-weight: 600;
}

.store-sidebar .card-body {
    padding: 20px;
}

.category-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.category-list li {
    margin-bottom: 10px;
}

.category-list a {
    color: var(--color-text-body);
    text-decoration: none;
    transition: color 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.category-list a:hover,
.category-list a.active {
    color: var(--brand-primary, #0D9488);
}

.category-count {
    font-size: 12px;
    color: var(--color-text-muted);
    background: var(--color-surface-alt);
    padding: 2px 8px;
    border-radius: 20px;
}

.sort-select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    background: var(--color-surface);
    color: var(--color-text-body);
    cursor: pointer;
}

/* Responsive */
@media (max-width: 768px) {
    .store-header h1 {
        font-size: 32px;
    }
    
    .product-image {
        height: 180px;
    }
    
    .store-section {
        padding: 40px 0;
    }
}

/* Toast notification */
.store-toast {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 280px;
    background: white;
    border-radius: 12px;
    box-shadow: var(--shadow-lg);
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideIn 0.3s ease;
}

.store-toast.success {
    border-left: 4px solid #27ae60;
}

.store-toast.error {
    border-left: 4px solid #e74c3c;
}

.store-toast i {
    font-size: 20px;
}

.store-toast.success i {
    color: #27ae60;
}

.store-toast.error i {
    color: #e74c3c;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
</style>

<section class="store-section">
    <div class="container">
        <!-- Header -->
        <div class="store-header">
            <div class="eyebrow">Our Store</div>
            <h1><?php echo $category ? htmlspecialchars($category['name']) : 'Engineering Products'; ?></h1>
            <p>Quality equipment and materials for your engineering needs</p>
        </div>
        
        <!-- Search Bar -->
        <div class="row justify-content-center mb-5">
            <div class="col-md-6">
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-lg" 
                           placeholder="Search products..." value="<?php echo htmlspecialchars($search ?? ''); ?>"
                           style="border-radius: 50px;">
                    <button type="submit" class="btn btn--primary" style="border-radius: 50px; padding: 12px 24px;">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>
        </div>
        
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 store-sidebar">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-folder-open me-2"></i> Categories
                    </div>
                    <div class="card-body">
                        <ul class="category-list">
                            <li>
                                <a href="/Ismano/public/store/" class="<?php echo !$category_slug ? 'active' : ''; ?>">
                                    All Products
                                    <span class="category-count">All</span>
                                </a>
                            </li>
                            <?php foreach ($categories as $cat): ?>
                            <li>
                                <a href="?category=<?php echo $cat['slug']; ?>" 
                                   class="<?php echo $category_slug === $cat['slug'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                    <span class="category-count"><?php echo $storeController->getCategoryProductCount($cat['id']); ?></span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-sort me-2"></i> Sort By
                    </div>
                    <div class="card-body">
                        <select class="sort-select" id="sortSelect">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                            <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name: Z to A</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Products Grid -->
            <div class="col-lg-9">
                <?php if (empty($products)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                        <h3>No products found</h3>
                        <p class="text-muted">Check back later for new products</p>
                        <a href="/Ismano/public/store/" class="btn btn--primary mt-3">
                            <i class="fas fa-store me-2"></i> Browse All
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($products as $product): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="product-card">
                                    <div class="product-image">
                                        <a href="details.php?slug=<?php echo $product['slug']; ?>">
                                            <?php if ($product['featured_image']): ?>
                                                <img src="<?php echo $product['featured_image']; ?>" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                     onerror="this.src='https://placehold.co/400x400?text=No+Image'">
                                            <?php else: ?>
                                                <div style="height: 100%; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-image fa-3x text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </a>
                                        
                                        <?php if ($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                                            <span class="product-badge sale">SALE</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($product['is_featured']): ?>
                                            <span class="product-badge featured">FEATURED</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($product['stock_quantity'] <= 0): ?>
                                            <span class="product-badge soldout">SOLD OUT</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="product-info">
                                        <h3 class="product-title">
                                            <a href="details.php?slug=<?php echo $product['slug']; ?>">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </a>
                                        </h3>
                                        
                                        <?php if ($product['category_name']): ?>
                                            <div class="product-category">
                                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="product-price">
                                            <span class="current-price">$<?php echo number_format($product['price'], 2); ?></span>
                                            <?php if ($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
                                                <span class="old-price">$<?php echo number_format($product['compare_price'], 2); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="product-stock">
                                            <?php if ($product['stock_quantity'] > 0): ?>
                                                <span class="stock-in">
                                                    <i class="fas fa-check-circle"></i> In Stock (<?php echo $product['stock_quantity']; ?>)
                                                </span>
                                            <?php else: ?>
                                                <span class="stock-out">
                                                    <i class="fas fa-times-circle"></i> Out of Stock
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <button class="btn-add-cart add-to-cart" 
                                                data-product="<?php echo $product['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                                            <i class="fas fa-shopping-cart"></i>
                                            <?php echo $product['stock_quantity'] <= 0 ? 'Unavailable' : 'Add to Cart'; ?>
                                        </button>
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

<script>
// Sort select handler
document.getElementById('sortSelect')?.addEventListener('change', function() {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', this.value);
    window.location.href = url.toString();
});

// Add to cart functionality
document.querySelectorAll('.add-to-cart').forEach(button => {
    button.addEventListener('click', async function(e) {
        e.preventDefault();
        
        if (this.disabled) return;
        
        const productId = this.dataset.product;
        const productName = this.dataset.name;
        const originalHtml = this.innerHTML;
        
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        this.disabled = true;
        
        try {
            const response = await fetch('/Ismano/public/api/store/cart/add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: 1
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast('✓ ' + productName + ' added to cart!', 'success');
                updateCartCount();
                
                this.innerHTML = '<i class="fas fa-check"></i> Added!';
                setTimeout(() => {
                    this.innerHTML = originalHtml;
                    this.disabled = false;
                }, 2000);
            } else if (data.require_login) {
                if (confirm('Please login to add items to cart. Go to login page?')) {
                    window.location.href = '/Ismano/public/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
                } else {
                    this.innerHTML = originalHtml;
                    this.disabled = false;
                }
            } else {
                showToast(data.message || 'Failed to add item', 'error');
                this.innerHTML = originalHtml;
                this.disabled = false;
            }
        } catch (error) {
            showToast('An error occurred. Please try again.', 'error');
            this.innerHTML = originalHtml;
            this.disabled = false;
        }
    });
});

function showToast(message, type = 'success') {
    // Remove existing toast
    const existingToast = document.querySelector('.store-toast');
    if (existingToast) existingToast.remove();
    
    // Create new toast
    const toast = document.createElement('div');
    toast.className = `store-toast ${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Update cart count - using new simple API
function updateCartCount() {
    fetch('/Ismano/public/api/count.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const count = data.count;
                const cartElements = document.querySelectorAll('.cart-count, .cart-count-mobile, .cart-badge');
                cartElements.forEach(el => {
                    if (count > 0) {
                        el.textContent = count;
                        el.style.display = 'inline-block';
                    } else {
                        el.style.display = 'none';
                    }
                });
            }
        })
        .catch(err => {
            console.warn('Cart count error:', err);
        });
}

// Add to cart - using new simple API
async function addToCart(productId, productName, button) {
    const originalHtml = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    button.disabled = true;
    
    try {
        const response = await fetch('/Ismano/public/api/add-to-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: 1
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Show success
            const toast = document.createElement('div');
            toast.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#4CAF50;color:white;padding:12px 20px;border-radius:8px;z-index:9999;animation:slideIn 0.3s ease;';
            toast.innerHTML = '<i class="fas fa-check-circle"></i> ' + productName + ' added to cart!';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
            
            updateCartCount();
            button.innerHTML = '<i class="fas fa-check"></i> Added!';
            setTimeout(() => {
                button.innerHTML = originalHtml;
                button.disabled = false;
            }, 2000);
        } else if (data.require_login) {
            if (confirm('Please login to add items to cart. Go to login page?')) {
                window.location.href = '/Ismano/public/auth/login.php?redirect=' + encodeURIComponent(window.location.pathname);
            } else {
                button.innerHTML = originalHtml;
                button.disabled = false;
            }
        } else {
            alert(data.message || 'Failed to add item');
            button.innerHTML = originalHtml;
            button.disabled = false;
        }
    } catch (error) {
        console.error('Add to cart error:', error);
        alert('An error occurred. Please try again.');
        button.innerHTML = originalHtml;
        button.disabled = false;
    }
}



// Show toast notification
function showToast(message, type = 'success') {
    const existingToast = document.querySelector('.store-toast');
    if (existingToast) existingToast.remove();
    
    const toast = document.createElement('div');
    toast.className = `store-toast ${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 9999;
        background: white;
        border-radius: 8px;
        padding: 12px 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Add CSS animation if not present
if (!document.querySelector('#toast-animation-style')) {
    const style = document.createElement('style');
    style.id = 'toast-animation-style';
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    `;
    document.head.appendChild(style);
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    updateCartCount();
    
    // Attach add to cart handlers
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.dataset.product;
            const productName = this.dataset.name || 'Product';
            addToCart(productId, productName, this);
        });
    });
});
// Initial cart count load
updateCartCount();
</script>

<?php
$content = ob_get_clean();
$use_home_navbar = false;
require_once __DIR__ . '/../templates/public/layout.php';
?>