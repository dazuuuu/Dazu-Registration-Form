<?php
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/SubmissionModel.php';
require_once __DIR__ . '/../../../app/v1/models/DashboardModel.php';

use Models\TutorModel;
use Models\SubmissionModel;
use Models\DashboardModel;

$user = api_require_role('tutor');
$pdo = api_pdo();
$tutorModel = new TutorModel($pdo);
$tutor = $tutorModel->getByUserId($user['id']);
if (!$tutor) {
    json_response(['success' => false, 'message' => 'Tutor profile not found'], 404);
}

$stats = (new DashboardModel($pdo))->tutorStats((int) $tutor['id']);
$pending = (new SubmissionModel($pdo))->getPendingForTutor((int) $tutor['id']);

json_response([
    'success' => true,
    'data' => [
        'tutor' => [
            'id' => (int) $tutor['id'],
            'full_name' => $tutorModel->fullName($tutor),
            'courses' => $tutor['courses'] ?? [],
        ],
        'stats' => $stats,
        'pending_submissions' => $pending,
    ],
]);
