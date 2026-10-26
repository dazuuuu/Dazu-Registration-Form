<?php
// app/services/StudentNotifier.php
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/StudentEmailTemplate.php';
require_once __DIR__ . '/RegistrationFormGenerator.php';
require_once __DIR__ . '/AdmissionLetterGenerator.php';
require_once __DIR__ . '/../helpers/course_fee_structure.php';

if (!class_exists('StudentNotifier')) {
class StudentNotifier {
    private $pdo;
    private $mailer;
    private $mailCfg;
    private $enabled;
    private $companyName;
    private $loginUrl;

    public function __construct($pdo, ?Mailer $mailer = null, bool $forceEnabled = false) {
        if (!function_exists('url')) {
            require_once __DIR__ . '/../config/paths.php';
        }
        $this->loginUrl = url('auth/login.php');
        $this->pdo = $pdo;
        $this->mailCfg = require __DIR__ . '/../config/mail.php';
        if ($forceEnabled) {
            $this->mailCfg['enabled'] = true;
        }
        $this->enabled = !empty($this->mailCfg['enabled'])
            || !empty($this->mailCfg['enquiry_mail_enabled'])
            || !empty($this->mailCfg['password_reset_enabled'])
            || $forceEnabled;
        $this->mailer = $this->enabled ? ($mailer ?? null) : null;
        $this->companyName = $this->mailCfg['from_name'] ?? 'Dazu Training Institute';

        try {
            require_once __DIR__ . '/../models/SettingModel.php';
            $settings = new SettingModel($pdo);
            $siteName = $settings->get('site_name');
            if ($siteName) {
                $this->companyName = $siteName;
            }
        } catch (Throwable $e) {
            // settings table may not exist in all environments
        }
    }

    private function baseData(array $reg): array {
        $email = trim((string) ($reg['email'] ?? $reg['login_email'] ?? $reg['student_email'] ?? ''));
        if ($email === '' && !empty($reg['id'])) {
            try {
                $stmt = $this->pdo->prepare('SELECT email FROM registrations WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => (int) $reg['id']]);
                $f = $stmt->fetchColumn();
                if ($f) $email = trim((string) $f);
            } catch (\Throwable $e) {}
        }
        if ($email === '' && !empty($reg['user_id'])) {
            try {
                $stmt = $this->pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => (int) $reg['user_id']]);
                $f = $stmt->fetchColumn();
                if ($f) $email = trim((string) $f);
            } catch (\Throwable $e) {}
        }
        $reg['email'] = $email;
        $reg['login_email'] = $email;

        return array_merge($reg, [
            'company_name' => $this->companyName,
            'login_url'    => $this->fullUrl($this->loginUrl),
            'email'        => $email,
            'login_email'  => $email,
        ]);
    }

    private function fullUrl(string $path): string {
        if (strpos($path, 'http') === 0) return $path;
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . $path;
    }

    private function mailer(): Mailer {
        if ($this->mailer === null) {
            $this->mailer = new Mailer($this->mailCfg);
        }
        return $this->mailer;
    }

    private function send(
        string $email,
        string $name,
        string $event,
        array $data,
        ?string $attachmentPath = null,
        ?string $attachmentName = null,
        array $attachments = [],
        bool $embedLogo = true
    ): array {
        if (!$this->enabled) {
            error_log('[StudentNotifier] mail skipped — SMTP not enabled for ' . $event);
            return ['ok' => false, 'message' => 'mail disabled', 'skipped' => true];
        }
        $targetEmail = trim((string) ($email ?: ($data['email'] ?? $data['login_email'] ?? '')));
        if ($targetEmail === '' || !filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
            error_log('[StudentNotifier] invalid or empty recipient email for ' . $event . ': "' . $targetEmail . '"');
            return ['ok' => false, 'message' => 'Invalid recipient email address'];
        }
        [$subject, $html] = StudentEmailTemplate::render($event, $data);
        $sendCfg = $this->mailCfg;
        $sendCfg['enabled'] = true;
        $mailer = $this->mailer ?? new Mailer($sendCfg);
        if ($attachmentPath && is_file($attachmentPath)) {
            $attachments[] = ['path' => $attachmentPath, 'name' => $attachmentName ?: basename($attachmentPath)];
        }
        if ($attachments !== [] || !$embedLogo) {
            $result = $mailer->sendWithAttachments($targetEmail, $name, $subject, $html, $attachments, $embedLogo);
        } else {
            $result = $mailer->send($targetEmail, $name, $subject, $html);
        }
        if (!$result['ok']) {
            error_log('[StudentNotifier] ' . $event . ' to ' . $targetEmail . ': ' . $result['message']);
        }
        return $result;
    }

    /**
     * @return array{attachments:list<array{path:string,name:string}>,temps:list<string>,course:?array}
     */
    public function buildEnrolmentPack(array $reg, array $course = []): array
    {
        $course = $this->courseForRegistration($reg, $course);
        $temps = [];
        $attachments = [];

        try {
            $brochure = $course ? course_brochure_absolute_path($course) : null;
            if ($brochure) {
                $attachments[] = ['path' => $brochure, 'name' => course_brochure_download_name($course)];
            }
        } catch (Throwable $e) {
            error_log('[StudentNotifier] brochure attach skipped: ' . $e->getMessage());
        }

        try {
            $letter = AdmissionLetterGenerator::writeTempPdf($reg, $course ?? []);
            if ($letter) {
                $temps[] = $letter;
                $attachments[] = ['path' => $letter, 'name' => AdmissionLetterGenerator::fileName($reg)];
            }
        } catch (Throwable $e) {
            error_log('[StudentNotifier] admission letter skipped: ' . $e->getMessage());
        }

        try {
            if ($course) {
                $fees = course_fee_structure_write_temp_pdf($course);
                if ($fees) {
                    $temps[] = $fees;
                    $attachments[] = ['path' => $fees, 'name' => course_fee_structure_file_name($course)];
                }
            }
        } catch (Throwable $e) {
            error_log('[StudentNotifier] fee structure skipped: ' . $e->getMessage());
        }

        return ['attachments' => $attachments, 'temps' => $temps, 'course' => $course];
    }

    public function sendRegistrationReceived(array $reg, array $course = []): array {
        @ignore_user_abort(true);
        if (function_exists('set_time_limit')) {
            @set_time_limit(90);
        }

        $pack = $this->buildEnrolmentPack($reg, $course);
        $attachments = $pack['attachments'];
        $temps = $pack['temps'];

        error_log('[StudentNotifier] enrolment pack for ' . ($reg['email'] ?? '') . ': ' . implode(', ', array_column($attachments, 'name')));

        try {
            return $this->send(
                (string) $reg['email'],
                (string) $reg['full_name'],
                'registration_received',
                $this->baseData($reg),
                null,
                null,
                $attachments,
                false
            );
        } finally {
            foreach ($temps as $tmp) {
                if ($tmp && is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        }
    }

    public function sendCourseFeeStructure(array $reg): array
    {
        $course = $this->courseForRegistration($reg);
        if (!$course) {
            return ['ok' => false, 'message' => 'Course not found for this application.'];
        }
        $tmp = course_fee_structure_write_temp_pdf($course);
        if (!$tmp) {
            return ['ok' => false, 'message' => 'Could not generate the fee structure PDF.'];
        }
        try {
            return $this->send(
                (string) $reg['email'],
                (string) $reg['full_name'],
                'fee_structure',
                $this->baseData($reg),
                $tmp,
                course_fee_structure_file_name($course),
                [],
                false
            );
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    private function courseForRegistration(array $reg, array $course = []): ?array
    {
        if ($course !== [] && !empty($course['id'])) {
            return $course;
        }
        $courseId = (int) ($reg['course_id'] ?? $course['id'] ?? 0);
        if ($courseId <= 0 && !empty($reg['id'])) {
            try {
                $stmt = $this->pdo->prepare('SELECT course_id FROM registrations WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => (int) $reg['id']]);
                $cid = $stmt->fetchColumn();
                if ($cid) {
                    $courseId = (int) $cid;
                }
            } catch (\Throwable $e) {}
        }
        if ($courseId <= 0 && !empty($reg['course_title'])) {
            try {
                $stmt = $this->pdo->prepare('SELECT * FROM courses WHERE title = :t LIMIT 1');
                $stmt->execute([':t' => trim((string) $reg['course_title'])]);
                $found = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (is_array($found)) return $found;
            } catch (\Throwable $e) {}
        }
        if ($courseId <= 0) {
            return null;
        }
        try {
            require_once __DIR__ . '/../models/CourseModel.php';
            $model = new \Models\CourseModel($this->pdo);
            $found = $model->getById($courseId);
            return is_array($found) ? $found : null;
        } catch (Throwable $e) {
            return $course !== [] ? $course : null;
        }
    }

    public function sendRegistrationForm(array $reg): array {
        $tmp = RegistrationFormGenerator::writeTempPdf($reg);
        if (!$tmp) {
            return ['ok' => false, 'message' => 'Could not generate registration form PDF.'];
        }
        try {
            return $this->send(
                $reg['email'],
                $reg['full_name'],
                'registration_form',
                $this->baseData($reg),
                $tmp,
                RegistrationFormGenerator::fileName($reg),
                [],
                false
            );
        } finally {
            if ($tmp && is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function sendAdmissionLetter(array $reg, array $course = []): array {
        $course = $this->courseForRegistration($reg, $course);
        $data = $this->baseData($reg);
        $data['course'] = $course;
        if (!empty($course['title'])) {
            $data['course_title'] = $course['title'];
        }
        $tmp = AdmissionLetterGenerator::writeTempPdf($data, $course ?? []);
        if (!$tmp) {
            return ['ok' => false, 'message' => 'Could not generate admission letter PDF.'];
        }
        try {
            return $this->send(
                (string) $reg['email'],
                (string) ($reg['full_name'] ?? $reg['name'] ?? 'Student'),
                'admission_letter',
                $data,
                $tmp,
                AdmissionLetterGenerator::fileName($data),
                [],
                false
            );
        } finally {
            if ($tmp && is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function sendStudentDocumentsPack(array $reg, array $course = []): array {
        @ignore_user_abort(true);
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $course = $this->courseForRegistration($reg, $course);
        $data = $this->baseData($reg);
        $data['course'] = $course;
        if (!empty($course['title'])) {
            $data['course_title'] = $course['title'];
        }

        $temps = [];
        $attachments = [];

        // 1. Admission Letter PDF
        try {
            $letter = AdmissionLetterGenerator::writeTempPdf($data, $course ?? []);
            if ($letter && is_file($letter)) {
                $temps[] = $letter;
                $attachments[] = [
                    'path' => $letter,
                    'name' => AdmissionLetterGenerator::fileName($data),
                ];
            }
        } catch (\Throwable $e) {
            error_log('[StudentNotifier] pack admission letter error: ' . $e->getMessage());
        }

        // 2. Fee Structure PDF
        try {
            if ($course) {
                $fees = course_fee_structure_write_temp_pdf($course);
                if ($fees && is_file($fees)) {
                    $temps[] = $fees;
                    $attachments[] = [
                        'path' => $fees,
                        'name' => course_fee_structure_file_name($course),
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log('[StudentNotifier] pack fee structure error: ' . $e->getMessage());
        }

        // 3. Course Brochure PDF
        try {
            if ($course) {
                require_once __DIR__ . '/CourseBrochureGenerator.php';
                $brochure = CourseBrochureGenerator::writeTempPdf($course);
                if ($brochure && is_file($brochure)) {
                    if (strpos($brochure, 'dti_brochure_') !== false || strpos($brochure, sys_get_temp_dir()) !== false) {
                        $temps[] = $brochure;
                    }
                    $attachments[] = [
                        'path' => $brochure,
                        'name' => CourseBrochureGenerator::fileName($course),
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log('[StudentNotifier] pack brochure error: ' . $e->getMessage());
        }

        try {
            return $this->send(
                (string) ($data['email'] ?? $reg['email']),
                (string) ($data['full_name'] ?? ($reg['name'] ?? 'Student')),
                'documents_pack',
                $data,
                null,
                null,
                $attachments,
                false
            );
        } finally {
            foreach ($temps as $tmp) {
                if ($tmp && is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        }
    }

    public function sendRegistrationApproved(array $reg, string $tempPassword, array $course = []): array {
        $course = $this->courseForRegistration($reg, $course);
        $data = $this->baseData($reg);
        $data['temp_password'] = $tempPassword;
        $data['username'] = $reg['registration_no'] ?? ($reg['username'] ?? '');
        $data['registration_no'] = $reg['registration_no'] ?? ($data['username'] ?? '');
        $data['course'] = $course;
        if (!empty($course['title'])) {
            $data['course_title'] = $course['title'];
        }

        $tmp = AdmissionLetterGenerator::writeTempPdf($data, $course ?? []);
        try {
            $attachments = [];
            if ($tmp && is_file($tmp)) {
                $attachments[] = [
                    'path' => $tmp,
                    'name' => AdmissionLetterGenerator::fileName($data),
                ];
            }
            return $this->send(
                (string) $reg['email'],
                (string) ($reg['full_name'] ?? $reg['name'] ?? 'Student'),
                'registration_approved',
                $data,
                null,
                null,
                $attachments,
                false
            );
        } finally {
            if ($tmp && is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function sendRegistrationRejected(array $reg, string $reason): array {
        $data = $this->baseData($reg);
        $data['rejection_reason'] = $reason;
        return $this->send($reg['email'], $reg['full_name'], 'registration_rejected', $data, null, null, [], false);
    }

    public function sendCredentials(array $reg, string $tempPassword, array $course = []): array {
        $course = $this->courseForRegistration($reg, $course);
        $data = $this->baseData($reg);
        $data['temp_password'] = $tempPassword;
        $data['username'] = $reg['registration_no'] ?? ($reg['username'] ?? '');
        $data['registration_no'] = $reg['registration_no'] ?? ($data['username'] ?? '');
        $data['course'] = $course;
        if (!empty($course['title'])) {
            $data['course_title'] = $course['title'];
        }

        $tmp = AdmissionLetterGenerator::writeTempPdf($data, $course ?? []);
        try {
            $attachments = [];
            if ($tmp && is_file($tmp)) {
                $attachments[] = [
                    'path' => $tmp,
                    'name' => AdmissionLetterGenerator::fileName($data),
                ];
            }
            return $this->send(
                (string) $reg['email'],
                (string) ($reg['full_name'] ?? $reg['name'] ?? 'Student'),
                'credentials_sent',
                $data,
                null,
                null,
                $attachments,
                false
            );
        } finally {
            if ($tmp && is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function sendPaymentSubmitted(array $reg, array $payment): array {
        $data = $this->baseData(array_merge($reg, $payment));
        return $this->send($reg['email'], $reg['full_name'], 'payment_submitted', $data);
    }

    public function sendPaymentApproved(array $reg, array $payment, string $receiptHtml): array {
        $data = $this->baseData($reg);
        $data['receipt_html'] = $receiptHtml;
        $data = array_merge($data, $payment);
        return $this->send($reg['email'], $reg['full_name'], 'payment_approved', $data);
    }

    public function sendPaymentRejected(array $reg, array $payment, string $reason): array {
        $data = $this->baseData(array_merge($reg, $payment));
        $data['rejection_reason'] = $reason;
        return $this->send($reg['email'], $reg['full_name'], 'payment_rejected', $data);
    }

    public function sendPortalLocked(array $reg, float $amountDue): array {
        $data = $this->baseData($reg);
        $data['amount_due'] = $amountDue;
        $data['due_date'] = $reg['next_payment_due'] ?? '';
        return $this->send($reg['email'], $reg['full_name'], 'portal_locked', $data);
    }

    public function sendCertificate(array $reg, array $cert): array {
        $data = $this->baseData($reg);
        $data['course_title'] = $cert['course_name'] ?? ($reg['course_title'] ?? '');
        $data['certificate_url'] = $this->fullUrl(url('students/certificates/index.php'));
        return $this->send($reg['email'], $reg['full_name'], 'certificate_issued', $data);
    }

    /** Certificate email with the PDF attached, to the student's address or to $toEmail when given. */
    public function sendCertificatePdf(array $reg, array $cert, string $pdfPath, ?string $toEmail = null): array {
        $data = $this->baseData($reg);
        $data['course_title'] = $cert['snapshot_course'] ?? ($cert['course_name'] ?? '');
        $data['certificate_url'] = $this->fullUrl(url('students/certificates/index.php'));
        $data['pdf_attached'] = true;
        $fileName = 'Certificate_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $cert['certificate_no']) . '.pdf';
        return $this->send($toEmail ?: $reg['email'], $reg['full_name'], 'certificate_issued', $data, $pdfPath, $fileName);
    }
}
}

