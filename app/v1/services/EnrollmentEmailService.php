<?php
// Bundles admission letter, fee structure, and brochure for enrollment emails.

class EnrollmentEmailService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array{ok:bool, message?:string, attachments?:int}
     */
    public function send(array $registration, array $course, string $admissionLetterPath): array
    {
        require_once __DIR__ . '/../helpers/course_fee_structure.php';
        require_once __DIR__ . '/StudentNotifier.php';

        $regNo = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) ($registration['registration_no'] ?? 'enrollment')) ?: 'enrollment';
        $attachments = [];

        if ($admissionLetterPath !== '' && is_file($admissionLetterPath)) {
            $attachments[] = [
                'path' => $admissionLetterPath,
                'name' => $regNo . '-admission-letter.html',
            ];
        }

        $feeFile = $this->saveFeeStructureDocument($course, $regNo);
        if ($feeFile !== null) {
            $attachments[] = [
                'path' => $feeFile,
                'name' => $regNo . '-fee-structure.html',
            ];
        }

        $brochureFile = course_brochure_absolute_path($course);
        if ($brochureFile !== null && is_file($brochureFile)) {
            $attachments[] = [
                'path' => $brochureFile,
                'name' => $regNo . '-course-brochure.pdf',
            ];
        }

        if ($attachments === []) {
            return ['ok' => false, 'message' => 'No enrollment documents to attach.'];
        }

        $notifier = new \StudentNotifier($this->pdo, null, true);
        return $notifier->sendEnrollmentPackage($registration, $course, $attachments);
    }

    private function saveFeeStructureDocument(array $course, string $regNo): ?string
    {
        if (!function_exists('public_path')) {
            require_once __DIR__ . '/../config/paths.php';
        }

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Fee Structure</title></head><body style="margin:0;padding:24px;background:#fff;">'
            . course_fee_structure_document_html($course)
            . '</body></html>';

        $dir = public_path('uploads/enrollment_packages');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        $filename = $regNo . '-fee-structure.html';
        $abs = $dir . DIRECTORY_SEPARATOR . $filename;
        if (@file_put_contents($abs, $html) === false) {
            return null;
        }

        return $abs;
    }
}
