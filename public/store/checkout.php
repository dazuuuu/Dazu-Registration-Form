<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/config/db_connect.php';
require_once __DIR__ . '/../../app/helpers/currency.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: /Ismano/public/auth/login.php?redirect=' . urlencode('/Ismano/public/store/checkout.php'));
    exit();
}

$userId = (int) $_SESSION['user_id'];

// Load the active cart for the summary.
$cond = 'c.user_id = :u';
try { $pdo->query('SELECT saved_for_later FROM store_cart LIMIT 0'); $cond .= ' AND c.saved_for_later = 0'; } catch (Throwable $e) {}
$stmt = $pdo->prepare(
    "SELECT c.product_id, c.quantity, p.name, p.price, p.stock_quantity
     FROM store_cart c JOIN store_products p ON p.id = c.product_id
     WHERE $cond ORDER BY c.id DESC"
);
$stmt->execute([':u' => $userId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$subtotal = 0.0;
foreach ($items as $it) { $subtotal += (float) $it['price'] * (int) $it['quantity']; }

$prefillName = $_SESSION['username'] ?? '';
$page_title = 'Checkout - Ismano';

ob_start();
?>

<section class="section">
    <div class="container">
        <div class="text-center mb-5">
            <span class="eyebrow">Secure Checkout</span>
            <h1 class="fw-bold mb-2">Complete Your Order</h1>
            <p class="text-muted">Pay securely with M-Pesa</p>
        </div>

        <?php if (empty($items)): ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                <p class="mb-3">Your cart is empty.</p>
                <a href="/Ismano/public/store/" class="btn btn--primary"><i class="fas fa-store me-2"></i> Browse Products</a>
            </div>
        <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card-modern p-4" style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;">
                    <h5 class="mb-3">Delivery / Pickup Details</h5>

                    <div class="mb-3">
                        <label class="form-label d-block mb-2">How would you like to get your order?</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="fulfillment_method" id="mWalkin" value="walkin" checked>
                            <label class="btn btn-outline-primary" for="mWalkin"><i class="fas fa-store me-1"></i> Walk-in pickup <small class="d-block">Free</small></label>
                            <input type="radio" class="btn-check" name="fulfillment_method" id="mDelivery" value="delivery">
                            <label class="btn btn-outline-primary" for="mDelivery"><i class="fas fa-truck me-1"></i> Delivery <small class="d-block">Paid to courier</small></label>
                        </div>
                        <small class="text-muted d-block mt-2" id="methodHint">Pick your items up at the shop, no extra charge.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Name of person collecting *</label>
                        <input type="text" id="customer_name" class="form-control" value="<?php echo htmlspecialchars($prefillName); ?>" placeholder="Full name">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">M-Pesa phone number *</label>
                        <input type="tel" id="contact_phone" class="form-control" placeholder="07XX XXX XXX">
                        <small class="text-muted">You'll get an STK prompt on this number.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" id="locationLabel">Preferred pickup point *</label>
                        <input type="text" id="pickup_location" class="form-control" placeholder="e.g. Nairobi CBD branch">
                    </div>

                    <div class="mb-3" id="notesWrap" style="display:none;">
                        <label class="form-label">Delivery notes</label>
                        <textarea id="delivery_notes" class="form-control" rows="2" placeholder="Landmark, gate, instructions for the courier"></textarea>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="cart-summary" style="background:#f8f9fa;border-radius:14px;padding:22px;">
                    <h5 class="mb-3">Order Summary</h5>
                    <?php foreach ($items as $it): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span><?php echo htmlspecialchars($it['name']); ?> &times; <?php echo (int) $it['quantity']; ?></span>
                            <strong><?php echo format_price($it['price'] * $it['quantity']); ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal</span><strong><?php echo format_price($subtotal); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Delivery</span><span class="text-muted">Arranged with courier</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="fw-bold">Total to pay</span>
                        <span class="fw-bold fs-5"><?php echo format_price($subtotal); ?></span>
                    </div>

                    <button id="payBtn" class="btn btn--primary w-100">
                        <i class="fas fa-mobile-alt me-2"></i> Pay <?php echo format_price($subtotal); ?> with M-Pesa
                    </button>
                    <div id="payMsg" class="mt-3 small"></div>
                    <a href="/Ismano/public/client/cart/" class="btn btn-outline-secondary w-100 mt-2">Back to cart</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
(function () {
    var walkin = document.getElementById('mWalkin');
    var delivery = document.getElementById('mDelivery');
    var locLabel = document.getElementById('locationLabel');
    var locInput = document.getElementById('pickup_location');
    var notesWrap = document.getElementById('notesWrap');
    var hint = document.getElementById('methodHint');

    function syncMethod() {
        var isDelivery = delivery && delivery.checked;
        if (locLabel) locLabel.textContent = isDelivery ? 'Delivery address *' : 'Preferred pickup point *';
        if (locInput) locInput.placeholder = isDelivery ? 'e.g. Westlands, ABC Apartments' : 'e.g. Nairobi CBD branch';
        if (notesWrap) notesWrap.style.display = isDelivery ? 'block' : 'none';
        if (hint) hint.textContent = isDelivery
            ? 'Delivery fee is arranged directly with the courier, not charged here.'
            : 'Pick your items up at the shop, no extra charge.';
    }
    [walkin, delivery].forEach(function (el) { if (el) el.addEventListener('change', syncMethod); });
    syncMethod();

    var payBtn = document.getElementById('payBtn');
    var payMsg = document.getElementById('payMsg');
    function msg(text, kind) {
        payMsg.innerHTML = '<span class="text-' + (kind || 'muted') + '">' + text + '</span>';
    }

    var polling = null;
    function pollStatus(orderNumber, tries) {
        tries = tries || 0;
        if (tries > 24) { // ~2 minutes
            msg('Still waiting for confirmation. If you paid, check "My Orders" shortly.', 'warning');
            payBtn.disabled = false;
            return;
        }
        fetch('/Ismano/public/api/store/checkout/status.php?order=' + encodeURIComponent(orderNumber))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success && d.payment_status === 'paid') {
                    msg('Payment received! Redirecting to your order…', 'success');
                    window.dispatchEvent(new Event('cart:updated'));
                    setTimeout(function () { window.location.href = '/Ismano/public/client/orders/?new=' + encodeURIComponent(orderNumber); }, 1200);
                } else if (d.success && (d.payment_status === 'failed' || d.payment_status === 'cancelled')) {
                    msg('Payment was not completed. You can try again.', 'danger');
                    payBtn.disabled = false;
                } else {
                    polling = setTimeout(function () { pollStatus(orderNumber, tries + 1); }, 5000);
                }
            })
            .catch(function () { polling = setTimeout(function () { pollStatus(orderNumber, tries + 1); }, 5000); });
    }

    if (payBtn) payBtn.addEventListener('click', function () {
        var methodEl = document.querySelector('input[name="fulfillment_method"]:checked');
        var payload = {
            customer_name: (document.getElementById('customer_name') || {}).value || '',
            contact_phone: (document.getElementById('contact_phone') || {}).value || '',
            fulfillment_method: methodEl ? methodEl.value : 'walkin',
            pickup_location: (document.getElementById('pickup_location') || {}).value || '',
            delivery_notes: (document.getElementById('delivery_notes') || {}).value || ''
        };
        payBtn.disabled = true;
        msg('Sending payment request to your phone…');

        fetch('/Ismano/public/api/store/checkout/initiate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) {
                msg('Check your phone and enter your M-Pesa PIN…', 'success');
                pollStatus(d.order_number, 0);
            } else {
                console.error('[checkout] initiate rejected:', d.message, d.hint || '');
                msg((d.message || 'Could not start payment.') + (d.hint ? ' — ' + d.hint : ''), 'danger');
                payBtn.disabled = false;
            }
        })
        .catch(function (e) { console.error('[checkout] network/parse error:', e); msg('Network error. Please try again.', 'danger'); payBtn.disabled = false; });
    });
})();
</script>

<?php
$content = ob_get_clean();
$use_home_navbar = false;
require_once __DIR__ . '/../templates/public/layout.php';