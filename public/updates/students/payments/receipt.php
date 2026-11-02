<?php
// public/students/payments/receipt.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/PaymentModel.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\PaymentModel;
use Models\StudentModel;

Middleware::requireStudent();

$pdo = Connection::getInstance()->getConnection();
$paymentModel = new PaymentModel($pdo);
$studentModel = new StudentModel($pdo);
$userId = (int) SessionModel::get('user_id');

$id = (int) ($_GET['id'] ?? 0);
$download = !empty($_GET['download']);
$payment = $paymentModel->getById($id);

if (!$payment || (int) $payment['user_id'] !== $userId || $payment['status'] !== 'approved') {
    http_response_code(404);
    echo 'Receipt not found.';
    exit();
}

if (!empty($payment['receipt_html'])) {
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
        . '<style>body{background:#f5f6f8;padding:24px;font-family:Arial,sans-serif;}@media print{body{background:#fff;padding:0;}}.receipt-actions{text-align:center;margin-top:20px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}.receipt-actions a,.receipt-actions button{padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:bold;cursor:pointer;border:none;font-size:14px;}.btn-download{background:#15A24A;color:#fff;}.btn-print{background:#fff;border:1px solid #ccc;color:#333;}</style></head><body>'
        . $payment['receipt_html']
        . '<div class="receipt-actions">'
        . '<a href="' . htmlspecialchars(url('students/payments/receipt.php?id=' . $id . '&download=1')) . '" class="btn-download">Download Receipt</a>'
        . '<button type="button" class="btn-print" onclick="window.print()">Print Receipt</button>'
        . '<a href="' . htmlspecialchars(url('students/payments/index.php')) . '" class="btn-print">Back to Payments</a>'
        . '</div>'
        . '</body></html>';
    exit();
}

http_response_code(404);
echo 'Receipt not available.';
