<?php
// Compatibility shim -> consolidated cart API. Safe to keep or delete once the
// front-end calls cart.php?action=remove directly.
$_GET['action'] = 'remove';
require __DIR__ . '/cart.php';