<?php
// Compatibility shim -> consolidated cart API (cart count).
$_GET['action'] = 'count';
require __DIR__ . '/store/cart/cart.php';