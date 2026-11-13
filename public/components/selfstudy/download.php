<?php
// public/components/selfstudy/download.php — a tutor (or admin) downloads a file attached to a topic. The files are private; access follows the course permissions.
require __DIR__ . '/boot.php';

$f = $sst_ready ? ssc_file_for_tutor($db, $viewer, (int) ($_GET['id'] ?? 0)) : null;
if (!$f) { http_response_code(404); echo 'File not found.'; exit; }
$name = preg_replace('/[^A-Za-z0-9._ -]+/', '_', basename((string) $f['file_name'])) ?: 'file';
header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($f['abs']));
header('Cache-Control: private, no-store');
readfile($f['abs']);
