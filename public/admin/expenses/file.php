<?php
// Streams a receipt from private storage: to the admin who sent the request (or the superadmin).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/ExpenseModel.php';

use Models\ExpenseModel;

Middleware::requireAuth();
$ex = new ExpenseModel($pdo);
$isSuper = (int) ($_SESSION['role_id'] ?? 0) === 1;
if (isset($_GET['expense'])) {
    $row = $isSuper ? $ex->expense((int) $_GET['expense']) : null;
} else {
    $row = $ex->request((int) ($_GET['id'] ?? 0));
    if ($row && !$isSuper && ((int) $row['admin_user_id'] !== (int) ($_SESSION['user_id'] ?? 0) || !empty($_SESSION['tutor_admin']))) $row = null;
}
$abs = $row ? ExpenseModel::receiptAbs($row['receipt_path'] ?? null) : null;
if (!$abs) {
    http_response_code(404);
    exit('File not found.');
}
$mime = in_array($row['receipt_mime'], ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true) ? $row['receipt_mime'] : 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($abs));
header('Content-Disposition: inline; filename="' . (preg_replace('/[^A-Za-z0-9 _.()-]/', '_', (string) $row['receipt_name']) ?: 'receipt') . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
readfile($abs);
