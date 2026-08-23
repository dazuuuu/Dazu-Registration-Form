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
     * @return array{ok:bool, error?:string, id?:int, registration_no?:string}
     */
    public function process(array $post, array $files, array $course, ?string $referralNo = null): array
    {
        if (empty($post['dh_register'])) {
            return ['ok' => false, 'error' => 'Invalid submission.'];
        }

        if (!csrf_verify($post['csrf_token'] ?? null)) {
            return ['ok' => false, 'error' => 'Security check failed. Refresh and try again.'];
        }

        $courseId = (int) ($post['course_id'] ?? $course['id'] ?? 0);
        if ($courseId > 0 && $courseId !== (int) ($course['id'] ?? 0)) {
            $resolved = $this->courseModel->getById($courseId);
            if (CourseModel::isShareable($resolved)) {
                $course = $resolved;
            }
        }

        $data = [
            'full_name'                => $post['full_name'] ?? '',
            'email'                    => $post['email'] ?? '',
            'phone'                    => $post['phone'] ?? '',
            'course_id'                => $course['id'],
            'study_mode'               => $post['study_mode'] ?? 'online',
            'payment_plan'             => $post['payment_plan'] ?? 'full',
            'payment_reference'      => $post['payment_reference'] ?? '',
            'free_course_reason'       => $post['free_course_reason'] ?? '',
            'referral_registration_no' => $referralNo,
            'id_document_path'         => null,
        ];

        $result = $this->regModel->submit($data, $this->courseModel);
        if (!$result['ok']) {
            return $result;
        }

        $reg = $this->regModel->getById((int) $result['id']);
        if ($reg) {
            $this->issueAdmissionLetterAndEmail($reg, $course, (int) $result['id']);
        }

        return $result;
    }

    private function issueAdmissionLetterAndEmail(array $reg, array $course, int $registrationId): void
    {
        try {
            require_once __DIR__ . '/AdmissionLetterService.php';
            $letterService = new \AdmissionLetterService($this->pdo);
            $letter = $letterService->generateAndStore($reg, $course);
            if (empty($letter['ok'])) {
                error_log("[registration] Admission letter failed for #{$registrationId}: " . ($letter['error'] ?? 'unknown'));
                return;
            }

            $fullCourse = $this->courseModel->getById((int) ($course['id'] ?? $reg['course_id'] ?? 0)) ?? $course;

            require_once __DIR__ . '/EnrollmentEmailService.php';
            $emailService = new \EnrollmentEmailService($this->pdo);
            $emailResult = $emailService->send($reg, $fullCourse, $letter['path'] ?? '');
            if (empty($emailResult['ok'])) {
                error_log("[registration] Enrollment email failed for #{$registrationId}: " . ($emailResult['message'] ?? 'Unknown error'));
            }
        } catch (\Throwable $e) {
            error_log("[registration] Admission letter/email error for #{$registrationId}: " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
