<?php
// Compatibility shim -> consolidated cart API (add to cart).
$_GET['action'] = 'add';
require __DIR__ . '/store/cart/cart.php';