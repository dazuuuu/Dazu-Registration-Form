<?php
// public/api/store/checkout/initiate.php
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();
header('Content-Type: application/json; charset=utf-8');

// Catch fatal errors (bad include, parse error, etc.) and still return JSON so the
// browser Network tab shows the real reason instead of an empty 500.
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Fatal: ' . $e['message'], 'where' => basename($e['file']) . ':' . $e['line']]);
    }
});

function out($d, $code = 200) { while (ob_get_level() > 0) ob_end_clean(); http_response_code($code); echo json_encode($d); exit; }

// Confirm the required files exist before requiring them.
$need = [
    'db'         => __DIR__ . '/../../../../app/config/db_connect.php',
    'controller' => __DIR__ . '/../../../../app/controllers/CheckoutController.php',
    'mpesa_cfg'  => __DIR__ . '/../../../../app/config/mpesa.php',
];
foreach ($need as $label => $path) {
    if (!is_file($path)) {
        out(['success' => false, 'message' => "Missing required file ($label): " . $path], 500);
    }
}

require_once $need['db'];
require_once $need['controller'];

if (session_status() === PHP_SESSION_NONE) session_start();

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    out(['success' => false, 'message' => 'Method not allowed'], 405);
}
if (empty($_SESSION['user_id'])) {
    out(['success' => false, 'message' => 'Please login to checkout', 'require_login' => true], 401);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) $body = $_POST;

try {
    $mpesaCfg = require $need['mpesa_cfg'];
    $checkout = new CheckoutController($pdo, new MpesaService($mpesaCfg));
    $result = $checkout->initiate((int) $_SESSION['user_id'], $body);
} catch (Throwable $e) {
    error_log('[checkout/initiate] ' . $e->getMessage());
    out([
        'success' => false,
        'message' => 'Checkout error: ' . $e->getMessage(),
        'hint'    => "If this mentions 'store_orders' or 'no such table', run migration 009_create_store_orders.sql.",
    ], 500);
}

out($result, $result['success'] ? 200 : 422);