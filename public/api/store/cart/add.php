<?php
// Compatibility shim -> consolidated cart API. Safe to keep or delete once the
// front-end calls cart.php?action=add directly.
$_GET['action'] = 'add';
require __DIR__ . '/cart.php';