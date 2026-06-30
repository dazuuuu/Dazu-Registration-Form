<?php
// Compatibility shim -> consolidated cart API. Safe to keep or delete once the
// front-end calls cart.php?action=get directly.
$_GET['action'] = 'get';
require __DIR__ . '/cart.php';