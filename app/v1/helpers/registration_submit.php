<?php
// app/v1/helpers/registration_submit.php — shared course registration POST handler

use Models\CourseModel;
use Services\CourseRegistrationHandler;

if (!function_exists('dh_process_registration_post')) {
    /**
     * @return array{ok:bool,error?:string,id?:int,registration_no?:string,message?:string}
     */
    function dh_process_registration_post(PDO $pdo, CourseModel $courseModel): array
    {
        require_once __DIR__ . '/../services/CourseRegistrationHandler.php';

        $courseId = (int) ($_POST['course_id'] ?? 0);
        $course = $courseId > 0 ? $courseModel->getById($courseId) : null;
        if (!$course) {
            return ['ok' => false, 'error' => 'Selected course is not available.'];
        }

        $handler = new CourseRegistrationHandler($pdo);
        $result = $handler->process($_POST, $_FILES, $course, null);
        if (!empty($result['ok'])) {
            $result['message'] = 'You are enrolled. Check your email for your registration number.';
        }
        return $result;
    }
}

if (!function_exists('dh_registration_wants_json')) {
    function dh_registration_wants_json(): bool
    {
        return !empty($_POST['dh_ajax'])
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    }
}

if (!function_exists('dh_registration_json_response')) {
    function dh_registration_json_response(array $result): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => !empty($result['ok']),
            'message' => $result['ok']
                ? ($result['message'] ?? 'You are enrolled. Check your email for your registration number.')
                : ($result['error'] ?? 'Submission failed.'),
            'id' => $result['id'] ?? null,
            'registration_no' => $result['registration_no'] ?? null,
        ]);
        exit;
    }
}
