<?php
// public/intake/receipt.php — download a booking-fee receipt as a PDF. ?p=<payment id>&t=<token>
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/EnrolmentFeeService.php';
require_once __DIR__ . '/../../app/v1/models/PaymentModel.php';
require_once __DIR__ . '/../../app/v1/helpers/receipt_pdf.php';

$pdo = \Models\Connection::getInstance()->getConnection();
$paymentId = (int) ($_GET['p'] ?? 0);
$token = (string) ($_GET['t'] ?? '');

$st = $pdo->prepare("SELECT p.*, r.full_name, r.email, r.phone, r.registration_no, r.course_fee, r.registration_fee, r.registration_fee_waived,
                             r.payment_plan, c.title AS course_title
                      FROM payments p JOIN registrations r ON r.id = p.registration_id JOIN courses c ON c.id = r.course_id WHERE p.id = ?");
$st->execute([$paymentId]);
$row = $st->fetch(PDO::FETCH_ASSOC);

if (!$row || !$paymentId || $token === '' || !hash_equals(en_receipt_token($paymentId, (string) $row['registration_no']), $token)) {
    http_response_code(404);
    exit('Receipt not found.');
}

$totalPaid = (new \Models\PaymentModel($pdo))->getTotalApproved((int) $row['registration_id']);
$pdf = receipt_pdf_render($row, $row, $totalPaid > 0 ? $totalPaid : (float) $row['amount']);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="receipt-' . preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $row['registration_no']) . '.pdf"');
echo $pdf;
