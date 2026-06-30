<?php
// Compatibility shim -> consolidated cart API. Safe to keep or delete once the
// front-end calls cart.php?action=count directly.
$_GET['action'] = 'count';
require __DIR__ . '/cart.php';