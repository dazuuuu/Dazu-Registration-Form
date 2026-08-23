<?php
// Redirect to the public web root (AMPPS / subfolder installs).
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$target = rtrim($scriptDir, '/') . '/public/index.php';
header('Location: ' . $target);
exit;
