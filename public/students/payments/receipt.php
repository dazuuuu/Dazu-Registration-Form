<?php
// public/students/payments/receipt.php
require_once __DIR__ . '/../../../app/init.php';
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
$payment = $paymentModel->getById($id);

if (!$payment || (int) $payment['user_id'] !== $userId || $payment['status'] !== 'approved') {
    http_response_code(404);
    echo 'Receipt not found.';
    exit();
}

if (!empty($payment['receipt_html'])) {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Receipt ' . htmlspecialchars($payment['receipt_no']) . '</title>'
        . '<style>body{background:#f5f6f8;padding:24px;}@media print{body{background:#fff;padding:0;}}</style></head><body>'
        . $payment['receipt_html']
        . '<p style="text-align:center;margin-top:20px;"><button onclick="window.print()">Print Receipt</button></p>'
        . '</body></html>';
    exit();
}

http_response_code(404);
echo 'Receipt not available.';
