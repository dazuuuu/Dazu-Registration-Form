<?php
// Student: download their own fee structure (?type=structure) or fee statement (?type=statement) as a PDF.
// Open even while the portal is locked for fees. Only the signed-in student's own enrolment is ever read.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/PaymentModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/helpers/fee_documents.php';

use Models\Connection;
use Models\StudentModel;
use Models\PaymentModel;

Middleware::requireStudent();
$pdo = Connection::getInstance()->getConnection();
$sm = new StudentModel($pdo);
$reg = $sm->getByUserId((int) SessionModel::get('user_id'));
$type = $_GET['type'] ?? '';
if (!$reg || !in_array($type, ['structure', 'statement'], true)) {
    http_response_code(404);
    exit('Not found.');
}
$owed = $sm->totalOwed($reg);
$paid = (new PaymentModel($pdo))->getTotalApproved((int) $reg['id']);
$html = $type === 'structure' ? fee_structure_html($reg, $owed, $paid) : fee_statement_html($pdo, $reg, $owed, $paid);
$name = ($type === 'structure' ? 'Fee-Structure-' : 'Fee-Statement-') . preg_replace('/[^A-Za-z0-9]+/', '-', (string) $reg['registration_no']) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: ' . (isset($_GET['view']) ? 'inline' : 'attachment') . '; filename="' . $name . '"');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');
echo fee_pdf($html);
