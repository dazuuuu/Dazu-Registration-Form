<?php
// Compatibility shim -> consolidated cart API. Safe to keep or delete once the
// front-end calls cart.php?action=move_to_cart directly.
$_GET['action'] = 'move_to_cart';
require __DIR__ . '/cart.php';