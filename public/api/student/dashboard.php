<?php
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/PaymentModel.php';
require_once __DIR__ . '/../../../app/v1/models/RegistrationModel.php';
require_once __DIR__ . '/../../../app/v1/models/DashboardModel.php';
require_once __DIR__ . '/../../../app/v1/controllers/StudentController.php';

use Models\StudentModel;
use Models\PaymentModel;
use Models\RegistrationModel;
use Models\DashboardModel;
use Controllers\StudentController;

$user = api_require_role('student');
$pdo = api_pdo();
$studentModel = new StudentModel($pdo);
$paymentModel = new PaymentModel($pdo);

$reg = $studentModel->getByUserId($user['id']);
if (!$reg) {
    json_response(['success' => false, 'message' => 'No approved registration found'], 404);
}

$studentModel->ensurePortalStatus($reg);
$reg = $studentModel->getByUserId($user['id']);
$totalPaid = $paymentModel->getTotalApproved((int) $reg['id']);
$totalOwed = $studentModel->totalOwed($reg);
$learnStats = (new DashboardModel($pdo))->studentStats((int) $reg['id'], (int) $reg['course_id']);

json_response([
    'success' => true,
    'data' => [
        'registration' => [
            'id' => (int) $reg['id'],
            'registration_no' => $reg['registration_no'] ?? '',
            'course_title' => $reg['course_title'] ?? '',
            'study_mode' => $reg['study_mode'] ?? '',
            'payment_plan' => $reg['payment_plan'] ?? '',
            'portal_locked' => !empty($reg['portal_locked']),
            'balance' => max(0, $totalOwed - $totalPaid),
            'total_paid' => $totalPaid,
            'next_amount' => $studentModel->nextInstallmentAmount($reg),
            'installments' => RegistrationModel::calculateInstallments(
                (float) $reg['course_fee'],
                $reg['payment_plan'],
                (int) $reg['duration_months']
            ),
        ],
        'learning' => $learnStats,
        'has_pending_payment' => $paymentModel->hasPending((int) $reg['id']),
    ],
]);
