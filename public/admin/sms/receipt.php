<?php
require_once __DIR__ . '/_init.php';
sms_require_tables($pdo);
$id = (int) ($_GET['id'] ?? 0);
$receipt = $sms->getReceipt($id);
if (!$receipt) {
    http_response_code(404);
    echo 'Receipt not found.';
    exit;
}

if (isset($_GET['regen'])) {
    $receipt = $sms->regenerateReceipt($id) ?? $receipt;
}

$path = $sms->absoluteReceiptPath($receipt['file_path'] ?? '');
if (!$path || !is_file($path)) {
    $receipt = $sms->regenerateReceipt($id) ?? $receipt;
    $path = $sms->absoluteReceiptPath($receipt['file_path'] ?? '');
}

if (!$path || !is_file($path)) {
    http_response_code(404);
    echo 'Receipt file not found.';
    exit;
}

$filename = ($receipt['receipt_no'] ?? 'receipt');
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if ($ext === 'pdf') {
    header('Content-Type: application/pdf');
    $filename .= '.pdf';
} else {
    header('Content-Type: text/html; charset=utf-8');
    $filename .= '.html';
}
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
