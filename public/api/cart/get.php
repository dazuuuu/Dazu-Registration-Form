<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../app/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/config/db_connect.php';
require_once __DIR__ . '/../../../app/v1/controllers/CartController.php';

$cartController = new CartController($pdo);
echo json_encode($cartController->getCart());
?>

