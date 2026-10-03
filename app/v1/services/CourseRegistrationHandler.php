<?php
// app/v1/services/CourseRegistrationHandler.php
namespace Services;

use Models\CourseModel;
use Models\RegistrationModel;

class CourseRegistrationHandler
{
    private $pdo;
    private $regModel;
    private $courseModel;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->regModel = new RegistrationModel($pdo);
        $this->courseModel = new CourseModel($pdo);
    }

    /**
     * Process a course registration POST. Works standalone on share pages.
     *
     * @return array{ok:bool, error?:string, id?:int}
     */
    public function process(array $post, array $files, array $course, ?string $referralNo = null): array
    {
        if (empty($post['dh_register'])) {
            return ['ok' => false, 'error' => 'Invalid submission.'];
        }

        if (!csrf_verify($post['csrf_token'] ?? null)) {
            return ['ok' => false, 'error' => 'Security check failed. Refresh and try again.'];
        }

        $idDocPath = $this->uploadIdDocument($files['id_document'] ?? null);
        if (is_string($idDocPath) && str_starts_with($idDocPath, 'ERROR:')) {
            return ['ok' => false, 'error' => substr($idDocPath, 6)];
        }

        $data = [
            'full_name'         => $post['full_name'] ?? '',
            'email'             => $post['email'] ?? '',
            'phone'             => $post['phone'] ?? '',
            'secondary_phone'   => $post['secondary_phone'] ?? '',
            'course_id'         => $course['id'],
            'study_mode'        => $post['study_mode'] ?? 'online',
            'payment_plan'      => $post['payment_plan'] ?? 'monthly',
            'id_document_path'  => $idDocPath,
            'payment_reference' => $post['payment_reference'] ?? 'Public enrolment — payment to follow',
            'free_course_reason' => $post['free_course_reason'] ?? '',
            'referral_registration_no' => $referralNo,
        ];

        $result = $this->regModel->submit($data, $this->courseModel);
        if (!$result['ok']) {
            return $result;
        }

        $reg = $this->regModel->getById($result['id']) ?: array_merge($data, $result);
        require_once __DIR__ . '/../helpers/enrolment_offer.php';
        enrolment_offer_store($reg, $course, $result);

        return $result;
    }

    /**
     * @return string|null Path on success, null when no file, or 'ERROR:...' on failure
     */
    private function uploadIdDocument($file): ?string
    {
        if (!$file || empty($file['name'])) {
            return null;
        }
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            return 'ERROR:Document upload failed. Try again.';
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return 'ERROR:Document must be under 5 MB.';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if ($mime !== 'application/pdf') {
            return 'ERROR:Only PDF files are accepted.';
        }

        if (!function_exists('public_path')) {
            require_once __DIR__ . '/../config/paths.php';
        }

        $dest = public_path('uploads/registrations/documents');
        if (!is_dir($dest)) {
            mkdir($dest, 0775, true);
        }

        $fname = time() . '_' . bin2hex(random_bytes(6)) . '.pdf';
        if (!move_uploaded_file($file['tmp_name'], $dest . DIRECTORY_SEPARATOR . $fname)) {
            return 'ERROR:Could not save the document. Try again.';
        }

        return url('uploads/registrations/documents/' . $fname);
    }
}
