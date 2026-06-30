<?php
// public/api/store/checkout/mpesa_callback.php
// Public endpoint Safaricom calls after an STK push. NOT browser-facing.
// Always answers Daraja with a JSON acknowledgement.

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../../app/models/OrderModel.php';
require_once __DIR__ . '/../../../../app/services/MpesaService.php';

$raw = file_get_contents('php://input');
error_log('[mpesa callback] ' . $raw);          // keep a trail while integrating

$body = json_decode($raw, true);
$ack = ['ResultCode' => 0, 'ResultDesc' => 'Accepted'];

try {
    if (!is_array($body)) {
        header('Content-Type: application/json');
        echo json_encode($ack);
        exit;
    }

    $cb = MpesaService::parseCallback($body);
    if (!empty($cb['valid']) && !empty($cb['checkout_request_id'])) {
        $orders = new OrderModel($pdo);

        if ($cb['result_code'] === 0) {
            $res = $orders->markPaidByCheckoutId($cb['checkout_request_id'], [
                'receipt'    => $cb['receipt'] ?? null,
                'phone'      => $cb['phone'] ?? null,
                'payer_name' => null, // Daraja STK callback doesn't return the payer name
            ]);

            // Send the purchase-confirmation email once (not on duplicate callbacks).
            if (!empty($res['ok']) && empty($res['already'])) {
                try {
                    require_once __DIR__ . '/../../../../app/services/OrderNotifier.php';
                    $mailCfg = require __DIR__ . '/../../../../app/config/mail.php';
                    $notifier = new OrderNotifier($pdo, new Mailer($mailCfg));
                    $full = $orders->getOrderWithItems((int) $res['order']['id']);
                    if ($full) $notifier->sendPurchaseConfirmation($full);
                } catch (Throwable $e) {
                    error_log('[mpesa callback] email error: ' . $e->getMessage());
                }
            }
        } else {
            // Customer cancelled, insufficient funds, timeout, etc.
            $orders->markFailedByCheckoutId($cb['checkout_request_id']);
        }
    }
} catch (Throwable $e) {
    error_log('[mpesa callback] error: ' . $e->getMessage());
    // Still ack so Safaricom doesn't hammer retries; we logged the failure.
}

header('Content-Type: application/json');
echo json_encode($ack);