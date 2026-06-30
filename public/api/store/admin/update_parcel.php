<?php
// public/api/store/admin/update_parcel.php
// Admin-only. Advances one parcel's fulfillment status and emails the customer
// when the new status is picked_up / delivered / arrived.
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../../app/models/OrderModel.php';
require_once __DIR__ . '/../../_lib/respond.php';

require_method('POST');
require_admin();

$body = read_json_body();
if (empty($body)) $body = $_POST;

$parcelId = trim((string) ($body['parcel_id'] ?? ''));
$status   = trim((string) ($body['status'] ?? ''));
if ($parcelId === '' || $status === '') {
    json_response(['success' => false, 'message' => 'parcel_id and status are required'], 400);
}

try {
    $orders = new OrderModel($pdo);
    $res = $orders->updateParcelStatus($parcelId, $status);
    if (!$res['ok']) {
        json_response(['success' => false, 'message' => $res['message']], 422);
    }

    // Fire the status email for the customer-facing statuses.
    $emailed = false;
    if (in_array($status, ['picked_up', 'delivered', 'arrived'], true)) {
        try {
            require_once __DIR__ . '/../../../../app/services/OrderNotifier.php';
            $mailCfg = require __DIR__ . '/../../../../app/config/mail.php';

            // Find the parcel's order + the specific item.
            $stmt = $pdo->prepare(
                "SELECT o.id AS order_id FROM store_order_items i JOIN store_orders o ON o.id = i.order_id WHERE i.parcel_id = :p"
            );
            $stmt->execute([':p' => $parcelId]);
            $orderId = $stmt->fetchColumn();
            if ($orderId) {
                $order = $orders->getOrderWithItems((int) $orderId);
                $item = null;
                foreach ($order['items'] as $it) {
                    if ($it['parcel_id'] === $parcelId) { $item = $it; break; }
                }
                if ($item) {
                    $notifier = new OrderNotifier($pdo, new Mailer($mailCfg));
                    $mailRes = $notifier->sendParcelStatus($order, $item, $status);
                    $emailed = !empty($mailRes['ok']);
                }
            }
        } catch (Throwable $e) {
            error_log('[update_parcel] email error: ' . $e->getMessage());
        }
    }

    json_response(['success' => true, 'final' => $res['final'] ?? false, 'emailed' => $emailed]);
} catch (Throwable $e) {
    error_log('[update_parcel] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error'], 500);
}