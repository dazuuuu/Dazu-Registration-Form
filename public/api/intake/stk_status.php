<?php
// public/api/intake/stk_status.php — the Book now page polls this after an STK push to learn when payment lands.
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/BookingPaymentService.php';

$cr = (string) ($_GET['cr'] ?? '');
if ($cr === '') { echo json_encode(['success' => false, 'message' => 'cr is required']); exit; }

try {
    $pdo = \Models\Connection::getInstance()->getConnection();
    echo json_encode(bkp_status($pdo, $cr));
} catch (Throwable $e) {
    error_log('[booking stk status] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
