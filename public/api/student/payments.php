<?php
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/PaymentModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/StudentController.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';

use Models\StudentModel;
use Models\PaymentModel;
use Controllers\StudentController;

$user = api_require_role('student');
$pdo = api_pdo();
$studentModel = new StudentModel($pdo);
$paymentModel = new PaymentModel($pdo);
$reg = $studentModel->getByUserId($user['id']);

if (!$reg) {
    json_response(['success' => false, 'message' => 'No registration found'], 404);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $payments = $paymentModel->listByRegistration((int) $reg['id']);
    json_response(['success' => true, 'data' => $payments]);
}

require_method('POST');
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    json_response(['success' => false, 'message' => 'Security check failed'], 403);
}

$controller = new StudentController();
$result = $controller->submitPayment(
    (int) $reg['id'],
    $user['id'],
    $_POST['mpesa_code'] ?? '',
    $_POST['payer_reference'] ?? ''
);

if (!empty($result['ok'])) {
    json_response(['success' => true, 'message' => 'Payment submitted for verification']);
}
json_response(['success' => false, 'message' => $result['error'] ?? 'Failed'], 422);
