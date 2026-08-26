<?php
// app/services/StudentNotifier.php
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/StudentEmailTemplate.php';
require_once __DIR__ . '/RegistrationFormGenerator.php';
require_once __DIR__ . '/AdmissionLetterGenerator.php';
require_once __DIR__ . '/../helpers/course_fee_structure.php';

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
        $this->companyName = $this->mailCfg['from_name'] ?? 'Dazu Hub Institute';

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
        return array_merge($reg, [
            'company_name' => $this->companyName,
            'login_url'    => $this->fullUrl($this->loginUrl),
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
        [$subject, $html] = StudentEmailTemplate::render($event, $data);
        $sendCfg = $this->mailCfg;
        $sendCfg['enabled'] = true;
        $mailer = $this->mailer ?? new Mailer($sendCfg);
        if ($attachmentPath && is_file($attachmentPath)) {
            $attachments[] = ['path' => $attachmentPath, 'name' => $attachmentName ?: basename($attachmentPath)];
        }
        if ($attachments !== [] || !$embedLogo) {
            $result = $mailer->sendWithAttachments($email, $name, $subject, $html, $attachments, $embedLogo);
        } else {
            $result = $mailer->send($email, $name, $subject, $html);
        }
        if (!$result['ok']) {
            error_log('[StudentNotifier] ' . $event . ' to ' . $email . ': ' . $result['message']);
        }
        return $result;
    }

    public function sendRegistrationReceived(array $reg, array $course = []): array {
        @ignore_user_abort(true);
        if (function_exists('set_time_limit')) {
            @set_time_limit(90);
        }

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
                RegistrationFormGenerator::fileName($reg)
            );
        } finally {
            if ($tmp && is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function sendRegistrationApproved(array $reg, string $tempPassword): array {
        $data = $this->baseData($reg);
        $data['temp_password'] = $tempPassword;
        $data['username'] = $reg['registration_no'] ?? '';
        return $this->send($reg['email'], $reg['full_name'], 'registration_approved', $data);
    }

    public function sendRegistrationRejected(array $reg, string $reason): array {
        $data = $this->baseData($reg);
        $data['rejection_reason'] = $reason;
        return $this->send($reg['email'], $reg['full_name'], 'registration_rejected', $data);
    }

    public function sendCredentials(array $reg, string $tempPassword): array {
        $data = $this->baseData($reg);
        $data['temp_password'] = $tempPassword;
        $data['username'] = $reg['registration_no'] ?? $reg['username'] ?? '';
        return $this->send($reg['email'], $reg['full_name'], 'credentials_sent', $data);
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
}
