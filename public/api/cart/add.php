<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/config/db_connect.php';
require_once __DIR__ . '/../../../app/controllers/CartController.php';

$cartController = new CartController($pdo);
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Product ID required']);
    exit();
}

$result = $cartController->addToCart($input['product_id'], $input['quantity'] ?? 1);
echo json_encode($result);
?>