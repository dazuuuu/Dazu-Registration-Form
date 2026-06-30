<?php
// Compatibility shim -> consolidated cart API. Safe to keep or delete once the
// front-end calls cart.php?action=save_for_later directly.
$_GET['action'] = 'save_for_later';
require __DIR__ . '/cart.php';