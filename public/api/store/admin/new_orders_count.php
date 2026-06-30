<?php
// public/api/store/admin/new_orders_count.php
// Admin-only. Returns how many paid orders still need fulfillment + the newest paid
// order (so the sidebar can toast a brand-new one). Hardened to always return JSON.
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();
header('Content-Type: application/json; charset=utf-8');

// Never emit a blank 500 — report the real fatal so we can see the cause.
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false, 'count' => 0, 'latest_id' => 0,
            'error' => $e['message'], 'where' => basename($e['file']) . ':' . $e['line'],
        ]);
    }
});

$need = [
    __DIR__ . '/../../../../app/config/db_connect.php',
    __DIR__ . '/../../../../app/models/OrderModel.php',
];
foreach ($need as $f) {
    if (!is_file($f)) {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'count' => 0, 'error' => 'Missing file: ' . $f]);
        exit;
    }
    require_once $f;
}

// Inline auth (don't depend on require_admin existing in an older respond.php).
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id']) || !isset($_SESSION['role_id']) || (int) $_SESSION['role_id'] > 2) {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'count' => 0, 'message' => 'Unauthorized']);
    exit;
}

try {
    $orders = new OrderModel($pdo);

    // Guard against an older OrderModel that lacks the new methods.
    if (!method_exists($orders, 'countNeedingAttention') || !method_exists($orders, 'latestPaidOrder')) {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'count' => 0, 'error' => 'OrderModel is outdated — deploy the version with countNeedingAttention()/latestPaidOrder().']);
        exit;
    }

    $latest = $orders->latestPaidOrder();
    while (ob_get_level() > 0) ob_end_clean();
    echo json_encode([
        'success'      => true,
        'count'        => $orders->countNeedingAttention(),
        'latest_id'    => $latest ? (int) $latest['id'] : 0,
        'latest_order' => $latest ? $latest['order_number'] : null,
        'latest_name'  => $latest ? $latest['customer_name'] : null,
    ]);
} catch (Throwable $e) {
    error_log('[new_orders_count] ' . $e->getMessage());
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'count' => 0, 'error' => $e->getMessage()]);
}