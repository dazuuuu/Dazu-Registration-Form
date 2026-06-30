<?php
// Compatibility shim -> consolidated cart API. Safe to keep or delete once the
// front-end calls cart.php?action=update directly.
$_GET['action'] = 'update';
require __DIR__ . '/cart.php';