<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/helpers/currency.php';
require_once __DIR__ . '/../../../app/models/OrderModel.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id']) || !isset($_SESSION['role_id']) || (int) $_SESSION['role_id'] > 2) {
    header('Location: /Realestate/public/auth/login.php?error=access_denied');
    exit();
}

$orders = new OrderModel($pdo);
$order = $orders->getOrderWithItems($_GET['order'] ?? '');
if (!$order) {
    header('Location: index.php');
    exit();
}

$allowed = $orders->allowedStatusesFor($order['fulfillment_method']);
$statusLabels = [
    'processing' => 'Processing', 'ready_for_pickup' => 'Ready for pickup',
    'out_for_delivery' => 'Out for delivery', 'picked_up' => 'Picked up',
    'delivered' => 'Delivered', 'arrived' => 'Arrived', 'cancelled' => 'Cancelled',
];

$page_title = 'Order ' . $order['order_number'];
$breadcrumbs = [['label' => 'Orders', 'url' => 'index.php'], ['label' => $order['order_number'], 'active' => true]];

ob_start();
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Parcels</h5>
                <span class="badge bg-light text-dark"><?php echo $order['fulfillment_method'] === 'delivery' ? 'Delivery' : 'Walk-in pickup'; ?></span>
            </div>
            <div class="card-body">
                <?php if (($order['payment_status'] ?? '') !== 'paid'): ?>
                    <div class="alert alert-warning">Payment status is <strong><?php echo htmlspecialchars($order['payment_status']); ?></strong>. Parcels can be managed once paid.</div>
                <?php endif; ?>

                <?php foreach ($order['items'] as $it): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-3 parcel-row" data-parcel="<?php echo htmlspecialchars($it['parcel_id']); ?>">
                        <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($it['product_name']); ?> &times; <?php echo (int) $it['quantity']; ?></div>
                            <small class="text-muted">Parcel <strong><?php echo htmlspecialchars($it['parcel_id']); ?></strong> &middot; <?php echo format_price($it['line_total']); ?></small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select form-select-sm parcel-status" style="width:auto;">
                                <?php foreach ($allowed as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $it['fulfillment_status'] === $s ? 'selected' : ''; ?>><?php echo $statusLabels[$s] ?? $s; ?></option>
                                <?php endforeach; ?>
                                <option value="cancelled" <?php echo $it['fulfillment_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                            <button class="btn btn-sm btn-primary save-parcel"><i class="fas fa-save"></i></button>
                        </div>
                    </div>
                <?php endforeach; ?>
                <p class="text-muted small mt-3 mb-0">Setting <em>Picked up</em>, <em>Delivered</em>, or <em>Arrived</em> emails the customer automatically.</p>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Customer</h6></div>
            <div class="card-body">
                <p class="mb-1"><strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                <p class="mb-1"><i class="fas fa-phone me-1 text-muted"></i> <?php echo htmlspecialchars($order['contact_phone']); ?></p>
                <p class="mb-0"><i class="fas fa-map-marker-alt me-1 text-muted"></i> <?php echo htmlspecialchars($order['pickup_location'] ?? '-'); ?></p>
                <?php if (!empty($order['delivery_notes'])): ?><p class="mb-0 mt-2 text-muted small"><?php echo htmlspecialchars($order['delivery_notes']); ?></p><?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Payment</h6></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-1"><span>Status</span><strong><?php echo ucfirst($order['payment_status']); ?></strong></div>
                <div class="d-flex justify-content-between mb-1"><span>Total</span><strong><?php echo format_price($order['total']); ?></strong></div>
                <?php if (!empty($order['mpesa_receipt'])): ?>
                    <div class="d-flex justify-content-between mb-1"><span>M-Pesa</span><strong><?php echo htmlspecialchars($order['mpesa_receipt']); ?></strong></div>
                <?php endif; ?>
                <?php if (!empty($order['mpesa_phone'])): ?>
                    <div class="d-flex justify-content-between"><span>Paid from</span><strong><?php echo htmlspecialchars($order['mpesa_phone']); ?></strong></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.save-parcel').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var row = this.closest('.parcel-row');
        var parcelId = row.dataset.parcel;
        var status = row.querySelector('.parcel-status').value;
        var icon = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        fetch('/Realestate/public/api/store/admin/update_parcel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ parcel_id: parcelId, status: status })
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            this.disabled = false;
            this.innerHTML = icon;
            if (d.success) {
                this.classList.remove('btn-primary'); this.classList.add('btn-success');
                this.innerHTML = '<i class="fas fa-check"></i>';
                if (d.emailed) { /* customer notified */ }
                setTimeout(function(){ this.classList.add('btn-primary'); this.classList.remove('btn-success'); this.innerHTML = icon; }.bind(this), 1500);
            } else {
                alert(d.message || 'Could not update status');
            }
        }.bind(this))
        .catch(function () { this.disabled = false; this.innerHTML = icon; alert('Network error'); }.bind(this));
    });
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';