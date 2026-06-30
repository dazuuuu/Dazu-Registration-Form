<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/db_connect.php';
require_once __DIR__ . '/../../app/helpers/currency.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: /Ismano/public/auth/login.php?redirect=' . urlencode('/Ismano/public/store/cart.php'));
    exit();
}

$userId = (int) $_SESSION['user_id'];

// Does the cart table support save-for-later?
$hasSaved = false;
try { $pdo->query('SELECT saved_for_later FROM store_cart LIMIT 0'); $hasSaved = true; } catch (Throwable $e) {}

// Active cart items (price + stock come from the product, matching checkout).
$activeCond = 'c.user_id = :u' . ($hasSaved ? ' AND c.saved_for_later = 0' : '');
$stmt = $pdo->prepare(
    "SELECT c.id, c.product_id, c.quantity, p.name, p.slug, p.featured_image, p.price, p.stock_quantity, p.status
     FROM store_cart c JOIN store_products p ON p.id = c.product_id
     WHERE $activeCond ORDER BY c.id DESC"
);
$stmt->execute([':u' => $userId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Saved-for-later items (only if supported)
$saved = [];
if ($hasSaved) {
    $s = $pdo->prepare(
        "SELECT c.id, c.product_id, c.quantity, p.name, p.slug, p.featured_image, p.price
         FROM store_cart c JOIN store_products p ON p.id = c.product_id
         WHERE c.user_id = :u AND c.saved_for_later = 1 ORDER BY c.id DESC"
    );
    $s->execute([':u' => $userId]);
    $saved = $s->fetchAll(PDO::FETCH_ASSOC);
}

$subtotal = 0.0;
foreach ($items as $it) { $subtotal += (float) $it['price'] * (int) $it['quantity']; }

$page_title = 'My Cart - Ismano';

ob_start();
?>

<style>
.cart-item { border-bottom: 1px solid #e8eae9; padding: 18px 0; }
.cart-item:last-child { border-bottom: none; }
.cart-thumb { width: 84px; height: 84px; object-fit: cover; border-radius: 10px; background: #f2f5f4; }
.cart-thumb-placeholder { width: 84px; height: 84px; border-radius: 10px; background: #f2f5f4; display: flex; align-items: center; justify-content: center; }
.qty-box { width: 120px; }
.cart-summary { background: #f8f9fa; border-radius: 14px; padding: 22px; position: sticky; top: 96px; }
</style>

<section class="section">
    <div class="container">
        <div class="mb-4">
            <span class="eyebrow">Your selection</span>
            <h1 class="fw-bold mb-0">Shopping Cart</h1>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card-modern" style="background:#fff;border:1px solid #e2eae8;border-radius:14px;">
                    <div class="card-header-modern" style="padding:16px 20px;border-bottom:1px solid #e2eae8;font-weight:600;">
                        <i class="fas fa-shopping-cart me-2"></i> Cart Items (<?php echo count($items); ?>)
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <?php if (empty($items)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                <p class="mb-3">Your cart is empty.</p>
                                <a href="/Ismano/public/store/" class="btn btn--primary"><i class="fas fa-store me-2"></i> Continue Shopping</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($items as $it): ?>
                                <div class="cart-item" data-item-id="<?php echo (int) $it['id']; ?>">
                                    <div class="row align-items-center g-3">
                                        <div class="col-auto">
                                            <?php if (!empty($it['featured_image'])): ?>
                                                <img src="<?php echo htmlspecialchars($it['featured_image']); ?>" class="cart-thumb" alt="<?php echo htmlspecialchars($it['name']); ?>"
                                                     onerror="this.onerror=null;this.src='https://placehold.co/84x84?text=No+Image'">
                                            <?php else: ?>
                                                <div class="cart-thumb-placeholder"><i class="fas fa-image text-muted"></i></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col">
                                            <h6 class="mb-1">
                                                <a href="/Ismano/public/store/details.php?slug=<?php echo urlencode($it['slug'] ?? ''); ?>" class="text-decoration-none text-dark">
                                                    <?php echo htmlspecialchars($it['name']); ?>
                                                </a>
                                            </h6>
                                            <small class="text-muted"><?php echo format_price($it['price']); ?> each</small>
                                            <?php if ((int) $it['quantity'] > (int) $it['stock_quantity']): ?>
                                                <div class="text-danger small mt-1">Only <?php echo (int) $it['stock_quantity']; ?> left in stock</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-12 col-md-auto">
                                            <div class="input-group qty-box">
                                                <button class="btn btn-outline-secondary btn-sm update-qty" data-change="-1" aria-label="Decrease"><i class="fas fa-minus"></i></button>
                                                <input type="number" class="form-control form-control-sm text-center quantity-input"
                                                       value="<?php echo (int) $it['quantity']; ?>" min="1" max="<?php echo (int) $it['stock_quantity']; ?>">
                                                <button class="btn btn-outline-secondary btn-sm update-qty" data-change="1" aria-label="Increase"><i class="fas fa-plus"></i></button>
                                            </div>
                                        </div>
                                        <div class="col-auto text-end" style="min-width:110px;">
                                            <strong><?php echo format_price($it['price'] * $it['quantity']); ?></strong>
                                        </div>
                                        <div class="col-auto">
                                            <button class="btn btn-sm btn-outline-danger remove-item" data-item-id="<?php echo (int) $it['id']; ?>" aria-label="Remove"><i class="fas fa-trash"></i></button>
                                        </div>
                                    </div>
                                    <?php if ($hasSaved): ?>
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-link text-muted p-0 save-for-later" data-product-id="<?php echo (int) $it['product_id']; ?>">
                                                <i class="far fa-heart me-1"></i> Save for later
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($saved)): ?>
                    <div class="card-modern mt-4" style="background:#fff;border:1px solid #e2eae8;border-radius:14px;">
                        <div class="card-header-modern" style="padding:16px 20px;border-bottom:1px solid #e2eae8;font-weight:600;">
                            <i class="far fa-heart me-2"></i> Saved for Later (<?php echo count($saved); ?>)
                        </div>
                        <div class="card-body p-3 p-md-4">
                            <?php foreach ($saved as $it): ?>
                                <div class="cart-item">
                                    <div class="row align-items-center g-3">
                                        <div class="col-auto">
                                            <?php if (!empty($it['featured_image'])): ?>
                                                <img src="<?php echo htmlspecialchars($it['featured_image']); ?>" class="cart-thumb" alt="<?php echo htmlspecialchars($it['name']); ?>">
                                            <?php else: ?>
                                                <div class="cart-thumb-placeholder"><i class="fas fa-image text-muted"></i></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($it['name']); ?></h6>
                                            <small class="text-muted"><?php echo format_price($it['price']); ?></small>
                                        </div>
                                        <div class="col-auto">
                                            <button class="btn btn-sm btn--primary move-to-cart" data-product-id="<?php echo (int) $it['product_id']; ?>">
                                                <i class="fas fa-shopping-cart me-1"></i> Move to Cart
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-4">
                <div class="cart-summary">
                    <h5 class="mb-3">Order Summary</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span><strong><?php echo format_price($subtotal); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Delivery</span><span class="text-muted">Calculated at checkout</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="fw-bold">Total</span>
                        <span class="fw-bold fs-5"><?php echo format_price($subtotal); ?></span>
                    </div>
                    <a href="/Ismano/public/store/checkout.php" class="btn btn--primary w-100 <?php echo empty($items) ? 'disabled' : ''; ?>">
                        <i class="fas fa-lock me-2"></i> Proceed to Checkout
                    </a>
                    <a href="/Ismano/public/store/" class="btn btn-outline-secondary w-100 mt-2">
                        <i class="fas fa-store me-2"></i> Continue Shopping
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
const CART_API = '/Ismano/public/api/store/cart/cart.php';

async function cartPost(action, payload) {
    try {
        const res = await fetch(CART_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ action: action }, payload))
        });
        return await res.json();
    } catch (e) {
        return { success: false, message: 'Network error' };
    }
}

function afterChange(data) {
    if (data && data.success) {
        window.dispatchEvent(new Event('cart:updated'));
        location.reload();
    } else {
        alert((data && data.message) || 'Could not update cart');
    }
}

// Quantity +/-
document.querySelectorAll('.update-qty').forEach(btn => {
    btn.addEventListener('click', async function () {
        const row = this.closest('.cart-item');
        const input = row.querySelector('.quantity-input');
        let q = parseInt(input.value, 10) + parseInt(this.dataset.change, 10);
        if (q < 1) q = 1;
        input.value = q;
        afterChange(await cartPost('update', { item_id: row.dataset.itemId, quantity: q }));
    });
});

// Manual quantity edit
document.querySelectorAll('.quantity-input').forEach(input => {
    input.addEventListener('change', async function () {
        const row = this.closest('.cart-item');
        let q = parseInt(this.value, 10);
        if (isNaN(q) || q < 1) q = 1;
        afterChange(await cartPost('update', { item_id: row.dataset.itemId, quantity: q }));
    });
});

// Remove
document.querySelectorAll('.remove-item').forEach(btn => {
    btn.addEventListener('click', async function () {
        if (!confirm('Remove this item from your cart?')) return;
        afterChange(await cartPost('remove', { item_id: this.dataset.itemId }));
    });
});

// Save for later
document.querySelectorAll('.save-for-later').forEach(btn => {
    btn.addEventListener('click', async function () {
        afterChange(await cartPost('save_for_later', { product_id: this.dataset.productId }));
    });
});

// Move back to cart
document.querySelectorAll('.move-to-cart').forEach(btn => {
    btn.addEventListener('click', async function () {
        afterChange(await cartPost('move_to_cart', { product_id: this.dataset.productId }));
    });
});
</script>

<?php
$content = ob_get_clean();
$use_home_navbar = false;
require_once __DIR__ . '/../templates/public/layout.php';