<?php
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../../app/v1/helpers/registration_submit.php';

use Models\CourseModel;

require_method('POST');

$pdo = api_pdo();
$courseModel = new CourseModel($pdo);
$result = dh_process_registration_post($pdo, $courseModel);

if (!empty($result['ok'])) {
    json_response([
        'success' => true,
        'message' => $result['message'] ?? 'Application received.',
        'registration_id' => $result['registration_id'] ?? null,
    ]);
}

json_response([
    'success' => false,
    'message' => $result['error'] ?? 'Submission failed.',
], 422);
