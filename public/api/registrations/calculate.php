<?php
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/models/RegistrationModel.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/registration_submit.php';

use Models\CourseModel;

require_method('POST');

$courseId = (int) ($_POST['course_id'] ?? 0);
$plan = $_POST['payment_plan'] ?? 'monthly';
$mode = $_POST['study_mode'] ?? 'online';

$pdo = api_pdo();
$courseModel = new CourseModel($pdo);
$course = $courseModel->getById($courseId);
if (!$course) {
    json_response(['success' => false, 'message' => 'Invalid course'], 422);
}

$fee = (float) $course['course_fee'];
$months = max(1, (int) $course['duration_months']);
$regFee = $mode === 'physical' ? 1500 : 1000;
$installments = RegistrationModel::calculateInstallments($fee, $plan, $months);
$first = $installments[0] ?? $fee;

json_response([
    'success' => true,
    'data' => [
        'registration_fee' => $regFee,
        'installment_amount' => $first,
        'total_due_now' => $regFee + $first,
        'installments' => $installments,
        'course_fee' => $fee,
    ],
]);
