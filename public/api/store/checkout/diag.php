<?php
// public/api/store/checkout/diag.php
// Open this in the browser (while logged in) to see what's wired up:
//   /Ismano/public/api/store/checkout/diag.php
// Reports table + file + config presence. Delete once checkout works.
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../app/config/db_connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login first, then reload this page.']);
    exit;
}

function tableExists(PDO $pdo, string $t): bool {
    try { $pdo->query("SELECT 1 FROM `$t` LIMIT 1"); return true; }
    catch (Throwable $e) { return false; }
}

$base = __DIR__ . '/../../../../app';
$report = [
    'success' => true,
    'tables' => [
        'store_orders'      => tableExists($pdo, 'store_orders'),
        'store_order_items' => tableExists($pdo, 'store_order_items'),
        'store_cart'        => tableExists($pdo, 'store_cart'),
        'store_products'    => tableExists($pdo, 'store_products'),
        'users'             => tableExists($pdo, 'users'),
    ],
    'files' => [
        'OrderModel'         => is_file($base . '/models/OrderModel.php'),
        'CheckoutController' => is_file($base . '/controllers/CheckoutController.php'),
        'MpesaService'       => is_file($base . '/services/MpesaService.php'),
        'currency_helper'    => is_file($base . '/helpers/currency.php'),
        'mpesa_config'       => is_file($base . '/config/mpesa.php'),
    ],
];

// Is the M-Pesa config still on placeholders?
if ($report['files']['mpesa_config']) {
    $cfg = require $base . '/config/mpesa.php';
    $report['mpesa'] = [
        'env'                 => $cfg['env'] ?? '?',
        'consumer_key_set'    => !empty($cfg['consumer_key']) && $cfg['consumer_key'] !== 'YOUR_CONSUMER_KEY',
        'passkey_set'         => !empty($cfg['passkey']) && $cfg['passkey'] !== 'YOUR_LNM_PASSKEY',
        'callback_url_public' => !empty($cfg['callback_url']) && strpos($cfg['callback_url'], 'YOUR_PUBLIC_HOST') === false,
    ];
}

$report['next'] = [];
if (!$report['tables']['store_orders'] || !$report['tables']['store_order_items']) {
    $report['next'][] = 'Run migration 009_create_store_orders.sql — the orders tables are missing.';
}
if (!empty($report['mpesa']) && (!$report['mpesa']['consumer_key_set'] || !$report['mpesa']['callback_url_public'])) {
    $report['next'][] = 'Fill in real Daraja credentials + a public callback_url in app/config/mpesa.php before a live STK push will work.';
}

echo json_encode($report, JSON_PRETTY_PRINT);