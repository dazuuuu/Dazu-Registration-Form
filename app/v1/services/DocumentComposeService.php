<?php
// Admin document compose: templates, PDF, email.

require_once __DIR__ . '/../helpers/document_letterhead.php';
require_once __DIR__ . '/../helpers/pdf_render.php';
require_once __DIR__ . '/../helpers/currency.php';
require_once __DIR__ . '/Mailer.php';

class DocumentComposeService
{
    public const TEMPLATES = [
        'admission_letter' => [
            'label' => 'Admission Letter',
            'title' => 'Letter of Admission',
            'subject' => 'Your admission letter — {institute_name}',
        ],
        'application_letter' => [
            'label' => 'Application Letter',
            'title' => 'Application Acknowledgement',
            'subject' => 'Application received — {institute_name}',
        ],
        'fee_structure' => [
            'label' => 'Fee Structure',
            'title' => 'Fee Structure',
            'subject' => 'Fee structure — {course_title}',
        ],
        'invoice' => [
            'label' => 'Invoice',
            'title' => 'Tax Invoice',
            'subject' => 'Invoice {invoice_no} — {institute_name}',
        ],
        'receipt' => [
            'label' => 'Receipt',
            'title' => 'Official Receipt',
            'subject' => 'Payment receipt {receipt_no} — {institute_name}',
        ],
    ];

    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function templateSlugs(): array
    {
        return array_keys(self::TEMPLATES);
    }

    public function templateMeta(string $slug): ?array
    {
        return self::TEMPLATES[$slug] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function defaultContext(?int $courseId = null): array
    {
        require_once __DIR__ . '/../helpers/course_fee_structure.php';
        require_once __DIR__ . '/../models/CourseModel.php';

        $ctx = [
            'institute_name'   => document_letterhead_institute(),
            'date'             => date('d F Y'),
            'recipient_name'   => '',
            'registration_no'  => '',
            'course_title'     => '',
            'study_mode'       => '',
            'email'            => '',
            'phone'            => '',
            'fee_online'       => '',
            'fee_physical'     => '',
            'invoice_no'       => 'INV-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'receipt_no'       => 'RCP-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'amount'           => '',
            'balance'          => '',
            'payment_reference'=> '',
        ];

        if ($courseId) {
            $cm = new \Models\CourseModel($this->pdo);
            $course = $cm->getById($courseId);
            if ($course) {
                $fees = course_school_fees($course);
                $ctx['course_title'] = (string) ($course['title'] ?? '');
                $ctx['fee_online'] = $fees['online'] > 0 ? format_price($fees['online']) : '';
                $ctx['fee_physical'] = $fees['physical'] > 0 ? format_price($fees['physical']) : '';
            }
        }

        return $ctx;
    }

    /**
     * @return array<string, string>
     */
    public function contextFromSmsStudent(int $smsStudentId): array
    {
        require_once __DIR__ . '/SmsModuleService.php';
        $sms = new SmsModuleService($this->pdo);
        $student = $sms->getStudent($smsStudentId);
        if (!$student) {
            return $this->defaultContext();
        }

        $ctx = $this->defaultContext((int) ($student['course_id'] ?? 0));
        $ctx['recipient_name'] = (string) ($student['name'] ?? '');
        $ctx['registration_no'] = (string) ($student['registration_no'] ?? '');
        $ctx['email'] = (string) ($student['email'] ?? '');
        $ctx['phone'] = (string) ($student['phone'] ?? '');
        $ctx['course_title'] = (string) ($student['course_title'] ?? $ctx['course_title']);
        $cost = (float) ($student['cost'] ?? 0);
        $paid = (float) ($student['paid'] ?? 0);
        $ctx['amount'] = $paid > 0 ? format_price($paid) : ($cost > 0 ? format_price($cost) : '');
        $ctx['balance'] = $cost > $paid ? format_price($cost - $paid) : format_price(0);

        return $ctx;
    }

    /**
     * @return array<string, string>
     */
    public function contextFromProspect(int $prospectId): array
    {
        require_once __DIR__ . '/SmsModuleService.php';
        $sms = new SmsModuleService($this->pdo);
        $p = $sms->getProspect($prospectId);
        if (!$p) {
            return $this->defaultContext();
        }

        $ctx = $this->defaultContext();
        $ctx['recipient_name'] = (string) ($p['name'] ?? '');
        $ctx['email'] = (string) ($p['email'] ?? '');
        $ctx['phone'] = (string) ($p['phone'] ?? '');
        $ctx['course_title'] = (string) ($p['intake_or_course'] ?? '');

        return $ctx;
    }

    /**
     * @return array<string, string>
     */
    public function contextFromRegistration(int $registrationId): array
    {
        require_once __DIR__ . '/../models/RegistrationModel.php';
        $reg = (new \Models\RegistrationModel($this->pdo))->getById($registrationId);
        if (!$reg) {
            return $this->defaultContext();
        }

        $ctx = $this->defaultContext((int) ($reg['course_id'] ?? 0));
        $ctx['recipient_name'] = (string) ($reg['full_name'] ?? '');
        $ctx['registration_no'] = (string) ($reg['registration_no'] ?? '');
        $ctx['email'] = (string) ($reg['email'] ?? '');
        $ctx['phone'] = (string) ($reg['phone'] ?? '');
        $ctx['study_mode'] = ucfirst((string) ($reg['study_mode'] ?? ''));
        $ctx['course_title'] = (string) ($reg['course_title'] ?? $ctx['course_title']);

        $regFee = !empty($reg['registration_fee_waived']) ? 0 : (float) ($reg['registration_fee'] ?? 0);
        $total = $regFee + (float) ($reg['course_fee'] ?? 0);
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE registration_id = :rid AND status = 'approved'"
        );
        $stmt->execute([':rid' => $registrationId]);
        $paid = (float) $stmt->fetchColumn();
        $ctx['amount'] = $paid > 0 ? format_price($paid) : format_price($total);
        $ctx['balance'] = format_price(max(0, $total - $paid));

        return $ctx;
    }

    public function defaultBody(string $slug, array $ctx): string
    {
        $name = $ctx['recipient_name'] !== '' ? $ctx['recipient_name'] : '_________________________';
        $course = $ctx['course_title'] !== '' ? $ctx['course_title'] : '_________________________';
        $regNo = $ctx['registration_no'] !== '' ? $ctx['registration_no'] : '_________________________';

        switch ($slug) {
            case 'admission_letter':
                return "Date: {date}\n\n"
                    . "Dear {$name},\n\n"
                    . "RE: LETTER OF ADMISSION\n\n"
                    . "We are pleased to inform you that you have been provisionally admitted to {$course} at {institute_name}.\n\n"
                    . "Registration Number: {$regNo}\n"
                    . "Study Mode: " . ($ctx['study_mode'] !== '' ? $ctx['study_mode'] : '_________________________') . "\n\n"
                    . "Please present this letter at the admissions office for verification, date confirmation, and official signing.\n\n"
                    . "Yours faithfully,\n\n"
                    . "_________________________\n"
                    . "Authorized Signatory\n"
                    . "{institute_name}";

            case 'application_letter':
                return "Date: {date}\n\n"
                    . "Dear {$name},\n\n"
                    . "RE: APPLICATION FOR ENROLMENT\n\n"
                    . "Thank you for submitting your application to study {$course} at {institute_name}. "
                    . "We confirm receipt of your application and supporting documents.\n\n"
                    . "Reference: {$regNo}\n\n"
                    . "Our admissions team will review your application and contact you regarding the outcome. "
                    . "If any additional information is required, we will reach you using the contact details on file.\n\n"
                    . "Yours faithfully,\n\n"
                    . "_________________________\n"
                    . "Admissions Office\n"
                    . "{institute_name}";

            case 'fee_structure':
                return "Date: {date}\n\n"
                    . "Dear {$name},\n\n"
                    . "RE: FEE STRUCTURE — {$course}\n\n"
                    . "Please find below the approved school fees for {$course} at {institute_name}:\n\n"
                    . "Online classes: " . ($ctx['fee_online'] !== '' ? $ctx['fee_online'] : 'As advised') . "\n"
                    . "Physical classes: " . ($ctx['fee_physical'] !== '' ? $ctx['fee_physical'] : 'As advised') . "\n\n"
                    . "Fees are subject to the institute's payment policy. Installment plans may be available upon request.\n\n"
                    . "For payment enquiries, contact the finance office.\n\n"
                    . "Yours faithfully,\n\n"
                    . "_________________________\n"
                    . "Finance Office\n"
                    . "{institute_name}";

            case 'invoice':
                return "Date: {date}\n\n"
                    . "Invoice No: {invoice_no}\n\n"
                    . "Bill To:\n{$name}\n"
                    . ($ctx['email'] !== '' ? $ctx['email'] . "\n" : '')
                    . ($ctx['phone'] !== '' ? $ctx['phone'] . "\n" : '')
                    . "\n"
                    . "Description: Tuition — {$course}\n"
                    . "Registration: {$regNo}\n\n"
                    . "Amount Due: " . ($ctx['amount'] !== '' ? $ctx['amount'] : '_________________________') . "\n"
                    . "Balance: " . ($ctx['balance'] !== '' ? $ctx['balance'] : '_________________________') . "\n\n"
                    . "Payment is due within fourteen (14) days of this invoice date. "
                    . "Please quote the invoice number when making payment.\n\n"
                    . "Yours faithfully,\n\n"
                    . "_________________________\n"
                    . "Accounts Office\n"
                    . "{institute_name}";

            case 'receipt':
                return "Date: {date}\n\n"
                    . "Receipt No: {receipt_no}\n\n"
                    . "Received from: {$name}\n"
                    . "Registration: {$regNo}\n"
                    . "Course: {$course}\n\n"
                    . "Amount Received: " . ($ctx['amount'] !== '' ? $ctx['amount'] : '_________________________') . "\n"
                    . "Payment Reference: " . ($ctx['payment_reference'] !== '' ? $ctx['payment_reference'] : '_________________________') . "\n\n"
                    . "This receipt confirms payment received. Please retain for your records.\n\n"
                    . "Yours faithfully,\n\n"
                    . "_________________________\n"
                    . "Finance Office\n"
                    . "{institute_name}";

            default:
                return "Date: {date}\n\nDear {$name},\n\n[Your message here]\n\nYours faithfully,\n{institute_name}";
        }
    }

    /**
     * @param array<string, string> $ctx
     */
    public function buildDocument(string $slug, string $bodyText, array $ctx): array
    {
        $meta = $this->templateMeta($slug);
        if (!$meta) {
            return ['ok' => false, 'error' => 'Unknown template.'];
        }

        $mergedBody = document_merge_placeholders($bodyText, $ctx);
        $bodyHtml = document_body_to_html($mergedBody);
        $html = document_full_html_page($meta['title'], $bodyHtml);
        $subject = document_merge_placeholders($meta['subject'], $ctx);

        return [
            'ok' => true,
            'html' => $html,
            'subject' => $subject,
            'title' => $meta['title'],
            'label' => $meta['label'],
        ];
    }

    public function renderPdf(string $html, string $filenameBase): array
    {
        if (!function_exists('public_path')) {
            require_once __DIR__ . '/../config/paths.php';
        }

        $dir = public_path('uploads/composed_documents');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'Could not create documents folder.'];
        }

        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '-', $filenameBase) ?: 'document';
        $filename = $safe . '-' . date('Ymd-His') . '.pdf';
        $abs = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!html_to_pdf_file($html, $abs)) {
            return ['ok' => false, 'error' => 'PDF generation failed. Run composer install (Dompdf).'];
        }

        return [
            'ok' => true,
            'path' => $abs,
            'rel_path' => 'uploads/composed_documents/' . $filename,
            'filename' => $filename,
        ];
    }

    public function sendEmail(string $toEmail, string $toName, string $subject, string $html, ?string $pdfPath = null): array
    {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid recipient email.'];
        }

        $cfg = require __DIR__ . '/../config/mail.php';
        $sendCfg = $cfg;
        $sendCfg['enabled'] = true;

        require_once __DIR__ . '/../helpers/site_logo.php';
        $logoAbs = site_logo_absolute_path();
        if ($logoAbs) {
            $sendCfg['logo_path'] = $logoAbs;
        }

        $mailer = new Mailer($sendCfg);
        $body = '<p>Dear ' . htmlspecialchars($toName !== '' ? $toName : 'Sir/Madam', ENT_QUOTES, 'UTF-8') . ',</p>'
            . '<p>Please find your document attached.</p>';

        if ($pdfPath && is_file($pdfPath)) {
            return $mailer->sendWithAttachment($toEmail, $toName, $subject, $body, $pdfPath, basename($pdfPath));
        }

        return $mailer->send($toEmail, $toName, $subject, $html);
    }
}
