<?php
// public/api/store/checkout/status.php
// The checkout page polls this after an STK push to learn when payment lands.
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../../app/models/OrderModel.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$orderNumber = $_GET['order'] ?? '';
if ($orderNumber === '') {
    echo json_encode(['success' => false, 'message' => 'order is required']);
    exit;
}

try {
    $orders = new OrderModel($pdo);
    $order = $orders->getByOrderNumber($orderNumber);

    // Only let a user see their own order's status.
    if (!$order || (int) $order['user_id'] !== (int) $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    echo json_encode([
        'success'        => true,
        'payment_status' => $order['payment_status'],     // pending | paid | failed | cancelled
        'order_number'   => $order['order_number'],
        'mpesa_receipt'  => $order['mpesa_receipt'],
    ]);
} catch (Throwable $e) {
    error_log('[checkout/status] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}