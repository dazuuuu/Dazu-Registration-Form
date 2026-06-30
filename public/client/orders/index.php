<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/helpers/currency.php';
require_once __DIR__ . '/../../../app/models/OrderModel.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /Realestate/public/auth/login.php?redirect=' . urlencode('/Realestate/public/client/orders/'));
    exit();
}

$userId = (int) $_SESSION['user_id'];
$orders = new OrderModel($pdo);
$active = $orders->getActiveForUser($userId);
$history = $orders->getHistoryForUser($userId);

$page_title = 'My Orders';

// Human labels + colour for parcel statuses.
function status_label($s) {
    $map = [
        'processing'       => ['Processing', 'secondary'],
        'ready_for_pickup' => ['Ready for pickup', 'info'],
        'out_for_delivery' => ['Out for delivery', 'info'],
        'picked_up'        => ['Picked up', 'success'],
        'delivered'        => ['Delivered', 'success'],
        'arrived'          => ['Arrived', 'success'],
        'cancelled'        => ['Cancelled', 'danger'],
    ];
    return $map[$s] ?? [ucfirst(str_replace('_', ' ', $s)), 'secondary'];
}

function render_order($o, $isHistory = false) {
    $methodLabel = $o['fulfillment_method'] === 'delivery' ? 'Delivery' : 'Walk-in pickup';
    ?>
    <div class="card-modern mb-3">
        <div class="card-header-modern d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <strong><?php echo htmlspecialchars($o['order_number']); ?></strong>
                <span class="badge bg-light text-dark ms-2"><?php echo $methodLabel; ?></span>
                <?php if (($o['payment_status'] ?? '') !== 'paid'): ?>
                    <span class="badge bg-danger ms-1"><?php echo ucfirst($o['payment_status']); ?></span>
                <?php endif; ?>
            </div>
            <div class="text-muted small">
                <?php echo htmlspecialchars(date('d M Y', strtotime($o['created_at'] ?? 'now'))); ?>
                &middot; <?php echo format_price($o['total']); ?>
                <?php if (!empty($o['mpesa_receipt'])): ?>&middot; <?php echo htmlspecialchars($o['mpesa_receipt']); ?><?php endif; ?>
            </div>
        </div>
        <div class="card-body p-3">
            <?php foreach (($o['items'] ?? []) as $it): ?>
                <?php [$label, $color] = status_label($it['fulfillment_status']); ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($it['product_name']); ?> &times; <?php echo (int) $it['quantity']; ?></div>
                        <small class="text-muted">Parcel <?php echo htmlspecialchars($it['parcel_id']); ?> &middot; <?php echo format_price($it['line_total']); ?></small>
                    </div>
                    <span class="badge bg-<?php echo $color; ?>"><?php echo $label; ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$isHistory && ($o['payment_status'] ?? '') === 'paid'): ?>
                <div class="text-end mt-3">
                    <a href="/Realestate/public/store/receipt.php?order=<?php echo urlencode($o['order_number']); ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-file-invoice me-1"></i> Receipt
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">My Orders</h3>
    <a href="/Realestate/public/store/" class="btn btn-sm btn-primary"><i class="fas fa-store me-1"></i> Shop more</a>
</div>

<?php if (!empty($_GET['new'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i> Payment received for <strong><?php echo htmlspecialchars($_GET['new']); ?></strong>. Track its progress below.
    </div>
<?php endif; ?>

<h5 class="text-muted mb-3"><i class="fas fa-truck-loading me-2"></i>Active</h5>
<?php if (empty($active)): ?>
    <div class="card-modern mb-4"><div class="card-body text-center text-muted py-4">No active orders right now.</div></div>
<?php else: ?>
    <?php foreach ($active as $o) render_order($o, false); ?>
<?php endif; ?>

<h5 class="text-muted mb-3 mt-4"><i class="fas fa-clock-rotate-left me-2"></i>History</h5>
<?php if (empty($history)): ?>
    <div class="card-modern"><div class="card-body text-center text-muted py-4">Completed and past orders will appear here.</div></div>
<?php else: ?>
    <?php foreach ($history as $o) render_order($o, true); ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/client/layout.php';