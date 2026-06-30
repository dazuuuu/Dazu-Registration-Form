<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/helpers/currency.php';
require_once __DIR__ . '/../../../app/models/OrderModel.php';

if (session_status() === PHP_SESSION_NONE) session_start();
// Admin guard (role_id 1=superadmin, 2=admin).
if (empty($_SESSION['user_id']) || !isset($_SESSION['role_id']) || (int) $_SESSION['role_id'] > 2) {
    header('Location: /Realestate/public/auth/login.php?error=access_denied');
    exit();
}

$orders = new OrderModel($pdo);
$filter = $_GET['status'] ?? null;
$valid = ['pending', 'paid', 'failed', 'cancelled'];
$filter = in_array($filter, $valid, true) ? $filter : null;

$list = $orders->adminList($filter, 200, 0);

$page_title = 'Sales & Orders';
$breadcrumbs = [['label' => 'Orders', 'active' => true]];

// Quick totals (paid only).
$paidRevenue = 0.0; $paidCount = 0;
foreach ($orders->adminList('paid', 1000, 0) as $o) { $paidRevenue += (float) $o['total']; $paidCount++; }

ob_start();
?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <div class="text-muted small">Paid orders</div>
            <div class="fs-3 fw-bold"><?php echo number_format($paidCount); ?></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <div class="text-muted small">Revenue (paid)</div>
            <div class="fs-3 fw-bold text-success"><?php echo format_price($paidRevenue); ?></div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <div class="text-muted small">Showing</div>
            <div class="fs-3 fw-bold"><?php echo count($list); ?> <?php echo $filter ? '(' . htmlspecialchars($filter) . ')' : '(all)'; ?></div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">All Orders</h5>
        <div class="btn-group btn-group-sm">
            <a href="?" class="btn btn-outline-secondary <?php echo !$filter ? 'active' : ''; ?>">All</a>
            <a href="?status=paid" class="btn btn-outline-success <?php echo $filter === 'paid' ? 'active' : ''; ?>">Paid</a>
            <a href="?status=pending" class="btn btn-outline-warning <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="?status=failed" class="btn btn-outline-danger <?php echo $filter === 'failed' ? 'active' : ''; ?>">Failed</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($list)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-receipt fa-3x mb-3"></i>
                <p>No orders found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Order</th><th>Customer</th><th>Method</th><th>Items</th>
                            <th>Total</th><th>Payment</th><th>Date</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $o): ?>
                            <?php
                            $payColor = ['paid' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'cancelled' => 'secondary'][$o['payment_status']] ?? 'secondary';
                            $itemCount = count($o['items'] ?? []);
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($o['order_number']); ?></strong>
                                    <?php if (!empty($o['mpesa_receipt'])): ?><br><small class="text-muted"><?php echo htmlspecialchars($o['mpesa_receipt']); ?></small><?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($o['customer_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($o['contact_phone']); ?></small></td>
                                <td><span class="badge bg-light text-dark"><?php echo $o['fulfillment_method'] === 'delivery' ? 'Delivery' : 'Walk-in'; ?></span></td>
                                <td><?php echo $itemCount; ?></td>
                                <td><?php echo format_price($o['total']); ?></td>
                                <td><span class="badge bg-<?php echo $payColor; ?>"><?php echo ucfirst($o['payment_status']); ?></span></td>
                                <td><small><?php echo htmlspecialchars(date('d M Y H:i', strtotime($o['created_at'] ?? 'now'))); ?></small></td>
                                <td><a href="view.php?order=<?php echo urlencode($o['order_number']); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> Manage</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../templates/admin/layout.php';