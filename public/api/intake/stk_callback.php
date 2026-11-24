<?php
// public/api/intake/stk_callback.php — Safaricom's webhook after a booking-fee STK push. NOT browser-facing.
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/BookingPaymentService.php';

$raw = file_get_contents('php://input');
error_log('[booking stk callback] ' . $raw);

$body = json_decode($raw, true);
$ack = ['ResultCode' => 0, 'ResultDesc' => 'Accepted'];

try {
    if (is_array($body)) {
        $pdo = \Models\Connection::getInstance()->getConnection();
        $cb = MpesaService::parseCallback($body);
        bkp_handle_callback($pdo, $cb);
    }
} catch (Throwable $e) {
    error_log('[booking stk callback] error: ' . $e->getMessage());
}

header('Content-Type: application/json');
echo json_encode($ack);
