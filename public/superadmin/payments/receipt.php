<?php
// public/superadmin/payments/receipt.php — view/download receipt (admin)
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/PaymentModel.php';

use Models\Connection;
use Models\PaymentModel;

Middleware::requireSuperPortal();

$pdo = Connection::getInstance()->getConnection();
$paymentModel = new PaymentModel($pdo);

$id = (int) ($_GET['id'] ?? 0);
$download = !empty($_GET['download']);
$payment = $paymentModel->getById($id);

if (!$payment || $payment['status'] !== 'approved' || empty($payment['receipt_html'])) {
    http_response_code(404);
    echo 'Receipt not found.';
    exit();
}

$receiptNo = $payment['receipt_no'] ?? ('receipt-' . $id);
$filename = preg_replace('/[^a-zA-Z0-9_-]/', '-', $receiptNo) . '.html';

if ($download) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Receipt ' . htmlspecialchars($receiptNo) . '</title></head><body>'
        . $payment['receipt_html']
        . '</body></html>';
    exit();
}

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Receipt ' . htmlspecialchars($receiptNo) . '</title>'
    . '<style>body{background:#f5f6f8;padding:24px;}@media print{body{background:#fff;padding:0;}}</style></head><body>'
    . $payment['receipt_html']
    . '<p style="text-align:center;margin-top:20px;">'
    . '<a href="' . htmlspecialchars(url('superadmin/payments/receipt.php?id=' . $id . '&download=1')) . '" style="margin-right:12px;">Download</a>'
    . '<button onclick="window.print()">Print</button></p>'
    . '</body></html>';
