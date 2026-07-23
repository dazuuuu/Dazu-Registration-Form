<?php
// app/v1/helpers/registration_submit.php — shared course registration POST handler

require_once __DIR__ . '/uploads.php';

use Models\CourseModel;
use Models\RegistrationModel;

if (!function_exists('dh_process_registration_post')) {
    /**
     * @return array{ok:bool,error?:string,id?:int}
     */
    function dh_process_registration_post(PDO $pdo, CourseModel $courseModel): array
    {
        if (!csrf_verify($_POST['csrf_token'] ?? null)) {
            return ['ok' => false, 'error' => 'Security check failed. Refresh and try again.'];
        }

        $file = $_FILES['id_document'] ?? null;
        if (!$file || empty($file['name'])) {
            return ['ok' => false, 'error' => 'Please upload your ID or result slip as a PDF.'];
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Document upload failed. Try again.'];
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'Document must be under 5 MB.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if ($mime !== 'application/pdf') {
            return ['ok' => false, 'error' => 'Only PDF files are accepted for the ID/result slip.'];
        }

        if (!function_exists('public_path')) {
            require_once __DIR__ . '/../config/paths.php';
        }

        $dest = public_path('uploads/registrations/documents');
        if (!is_dir($dest) && !@mkdir($dest, 0775, true) && !is_dir($dest)) {
            return ['ok' => false, 'error' => 'Could not create upload folder.'];
        }

        $fname = time() . '_' . bin2hex(random_bytes(6)) . '.pdf';
        if (!move_uploaded_file($file['tmp_name'], $dest . DIRECTORY_SEPARATOR . $fname)) {
            return ['ok' => false, 'error' => 'Could not save the file. Try again.'];
        }

        $idDocPath = url('uploads/registrations/documents/') . $fname;

        $regModel = new RegistrationModel($pdo);
        return $regModel->submit([
            'full_name'         => $_POST['full_name'] ?? '',
            'email'             => $_POST['email'] ?? '',
            'phone'             => $_POST['phone'] ?? '',
            'course_id'         => $_POST['course_id'] ?? 0,
            'study_mode'        => $_POST['study_mode'] ?? 'online',
            'payment_plan'      => $_POST['payment_plan'] ?? 'monthly',
            'id_document_path'  => $idDocPath,
            'payment_reference' => $_POST['payment_reference'] ?? '',
        ], $courseModel);
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
                ? 'Application received. Our team will verify your payment and contact you within 24 hours.'
                : ($result['error'] ?? 'Submission failed.'),
            'id' => $result['id'] ?? null,
        ]);
        exit;
    }
}
