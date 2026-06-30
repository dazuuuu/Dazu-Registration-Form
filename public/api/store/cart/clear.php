<?php
// Compatibility shim -> consolidated cart API. Safe to keep or delete once the
// front-end calls cart.php?action=clear directly.
$_GET['action'] = 'clear';
require __DIR__ . '/cart.php';