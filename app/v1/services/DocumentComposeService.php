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
        'remembrance_note' => [
            'label' => 'Remembrance Note',
            'title' => 'Fee Balance Reminder',
            'subject' => 'Reminder — outstanding school fees — {institute_name}',
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

    /** Which fee fields appear on the compose sidebar (amount is always course school fees). */
    public function templateFieldUi(string $slug): array
    {
        return [
            'show_school_fees'  => in_array($slug, ['invoice', 'receipt'], true),
            'show_amount_paid'  => $slug === 'receipt',
            'admission_auto_fees' => $slug === 'admission_letter',
        ];
    }

    /**
     * Apply course school fees, amount paid, and balance to context.
     *
     * @return array<string, string>
     */
    public function applyCourseFees(array $ctx, ?int $courseId, ?int $smsStudentId = null): array
    {
        require_once __DIR__ . '/../helpers/course_fee_structure.php';
        require_once __DIR__ . '/../models/CourseModel.php';

        if ($courseId !== null && $courseId > 0) {
            $course = (new \Models\CourseModel($this->pdo))->getById($courseId);
            if ($course) {
                $fees = course_school_fees($course);
                if (($ctx['course_title'] ?? '') === '') {
                    $ctx['course_title'] = (string) ($course['title'] ?? '');
                }
                $ctx['fee_online'] = $fees['online'] > 0 ? format_price($fees['online']) : '';
                $ctx['fee_physical'] = $fees['physical'] > 0 ? format_price($fees['physical']) : '';

                $mode = strtolower(trim((string) ($ctx['study_mode'] ?? '')));
                if ($mode === 'n/a') {
                    $mode = '';
                }
                if ($mode === 'physical' && $fees['physical'] > 0) {
                    $ctx['school_fees'] = format_price($fees['physical']);
                } elseif ($mode === 'online' && $fees['online'] > 0) {
                    $ctx['school_fees'] = format_price($fees['online']);
                } else {
                    $catalog = course_catalog_fee($course);
                    $ctx['school_fees'] = $catalog > 0 ? format_price($catalog) : '';
                }
            }
        }

        $ctx['school_fees'] = trim((string) ($ctx['school_fees'] ?? ''));
        $ctx['amount'] = $ctx['school_fees'];

        if ($smsStudentId !== null && $smsStudentId > 0) {
            require_once __DIR__ . '/SmsModuleService.php';
            $student = (new SmsModuleService($this->pdo))->getStudent($smsStudentId);
            if ($student) {
                $cost = (float) ($student['cost'] ?? 0);
                $paid = (float) ($student['paid'] ?? 0);
                if ($ctx['school_fees'] === '' && $cost > 0) {
                    $ctx['school_fees'] = format_price($cost);
                    $ctx['amount'] = $ctx['school_fees'];
                }
                $ctx['amount_paid'] = format_price($paid);
                $ctx['balance'] = $cost > $paid ? format_price($cost - $paid) : format_price(0);
            }
        }

        if (!isset($ctx['amount_paid'])) {
            $ctx['amount_paid'] = '';
        }
        if (!isset($ctx['balance'])) {
            $ctx['balance'] = '';
        }

        return $ctx;
    }

    /**
     * First enrollment registration number for a returning student (by SmS record or email).
     */
    public function findExistingRegistrationNo(?int $smsStudentId, ?string $email): ?string
    {
        if ($smsStudentId > 0) {
            $stmt = $this->pdo->prepare(
                'SELECT r.registration_no FROM sms_students s
                 LEFT JOIN registrations r ON r.id = s.registration_id
                 WHERE s.id = :id LIMIT 1'
            );
            $stmt->execute([':id' => $smsStudentId]);
            $regNo = trim((string) ($stmt->fetchColumn() ?: ''));
            if ($regNo !== '') {
                return $regNo;
            }
        }

        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT registration_no FROM registrations
             WHERE LOWER(email) = :email AND status = 'approved'
               AND registration_no IS NOT NULL AND registration_no != ''
             ORDER BY COALESCE(reviewed_at, created_at) ASC, id ASC LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $regNo = trim((string) ($stmt->fetchColumn() ?: ''));

        return $regNo !== '' ? $regNo : null;
    }

    /** Preview next number without consuming sequence (requires course). */
    public function peekRegistrationNumber(int $courseId): ?string
    {
        require_once __DIR__ . '/../helpers/registration_number.php';
        require_once __DIR__ . '/../models/CourseModel.php';

        $course = (new \Models\CourseModel($this->pdo))->getById($courseId);
        if (!$course || empty($course['abbreviation'])) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT last_sequence FROM courses WHERE id = :id');
        $stmt->execute([':id' => $courseId]);
        $current = (int) $stmt->fetchColumn();
        $next = $current < 49 ? 50 : $current + 1;

        return registration_number_format((string) $course['abbreviation'], $next);
    }

    /** Claim and return a new registration number (issues once in DB). */
    public function claimRegistrationNumber(int $courseId): ?string
    {
        require_once __DIR__ . '/../helpers/registration_number.php';
        require_once __DIR__ . '/../models/CourseModel.php';

        $course = (new \Models\CourseModel($this->pdo))->getById($courseId);
        if (!$course || empty($course['abbreviation'])) {
            return null;
        }

        try {
            $this->pdo->beginTransaction();
            $seq = registration_number_claim($this->pdo, $courseId);
            $this->pdo->commit();
            return registration_number_format((string) $course['abbreviation'], $seq);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[DocumentComposeService] claimRegistrationNumber: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array{registration_no:string, is_new:bool, is_preview:bool, claimed:bool}
     */
    public function resolveRegistrationNumber(int $courseId, ?int $smsStudentId, ?string $email, bool $claimIfNew = false): array
    {
        $existing = $this->findExistingRegistrationNo($smsStudentId, $email);
        if ($existing !== null) {
            return [
                'registration_no' => $existing,
                'is_new'          => false,
                'is_preview'      => false,
                'claimed'         => false,
            ];
        }

        if ($courseId <= 0) {
            return ['registration_no' => '', 'is_new' => true, 'is_preview' => false, 'claimed' => false];
        }

        if ($claimIfNew) {
            $claimed = $this->claimRegistrationNumber($courseId);
            return [
                'registration_no' => $claimed ?? '',
                'is_new'          => true,
                'is_preview'      => false,
                'claimed'         => $claimed !== null,
            ];
        }

        return [
            'registration_no' => $this->peekRegistrationNumber($courseId) ?? '',
            'is_new'          => true,
            'is_preview'      => true,
            'claimed'         => false,
        ];
    }

    /**
     * @param array<string, string> $ctx
     * @return array<string, string>
     */
    public function normalizeContext(array $ctx): array
    {
        $mode = trim((string) ($ctx['study_mode'] ?? ''));
        $ctx['study_mode'] = $mode === '' ? 'N/A' : ucfirst(strtolower($mode));

        foreach (['recipient_name', 'registration_no', 'course_title', 'email', 'phone'] as $key) {
            $ctx[$key] = trim((string) ($ctx[$key] ?? ''));
        }

        return $ctx;
    }

    /**
     * Build compose context for the UI (JSON/API).
     *
     * @return array{ctx: array<string, string>, reg: array{registration_no:string, is_new:bool, is_preview:bool}}
     */
    public function buildComposeContext(
        ?int $courseId,
        ?int $smsStudentId = null,
        ?int $prospectId = null,
        ?string $email = null
    ): array {
        if ($smsStudentId > 0) {
            $ctx = $this->contextFromSmsStudent($smsStudentId);
        } elseif ($prospectId > 0) {
            $ctx = $this->contextFromProspect($prospectId);
        } else {
            $ctx = $this->defaultContext($courseId ?: null);
        }

        if ($courseId > 0) {
            $courseCtx = $this->defaultContext($courseId);
            foreach (['course_title', 'fee_online', 'fee_physical'] as $key) {
                if (($courseCtx[$key] ?? '') !== '') {
                    $ctx[$key] = $courseCtx[$key];
                }
            }
        }

        $reg = $this->resolveRegistrationNumber(
            (int) ($courseId ?: (int) ($ctx['course_id'] ?? 0)),
            $smsStudentId,
            $email ?? ($ctx['email'] ?? null),
            false
        );
        if ($reg['registration_no'] !== '') {
            $ctx['registration_no'] = $reg['registration_no'];
        }

        $resolvedCourseId = (int) ($courseId ?: (int) ($ctx['course_id'] ?? 0));
        $ctx = $this->applyCourseFees($ctx, $resolvedCourseId > 0 ? $resolvedCourseId : null, $smsStudentId);

        return ['ctx' => $this->normalizeContext($ctx), 'reg' => $reg];
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
            'school_fees'      => '',
            'invoice_no'       => 'INV-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'receipt_no'       => 'RCP-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'amount'           => '',
            'amount_paid'      => '',
            'balance'          => '',
            'payment_reference'=> '',
        ];

        if ($courseId) {
            $ctx = $this->applyCourseFees($ctx, $courseId);
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
        $ctx['email'] = (string) ($student['email'] ?? '');
        $ctx['phone'] = (string) ($student['phone'] ?? '');
        $ctx['course_title'] = (string) ($student['course_title'] ?? $ctx['course_title']);
        $ctx['course_id'] = (string) ((int) ($student['course_id'] ?? 0));

        $existingReg = $this->findExistingRegistrationNo($smsStudentId, $ctx['email']);
        $ctx['registration_no'] = $existingReg ?? (string) ($student['registration_no'] ?? '');

        if (!empty($student['registration_id'])) {
            $stmt = $this->pdo->prepare('SELECT study_mode FROM registrations WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => (int) $student['registration_id']]);
            $mode = trim((string) ($stmt->fetchColumn() ?: ''));
            if ($mode !== '') {
                $ctx['study_mode'] = ucfirst($mode);
            }
        }

        return $this->applyCourseFees($ctx, (int) ($student['course_id'] ?? 0) ?: null, $smsStudentId);
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
        $ctx['amount_paid'] = format_price($paid);
        $ctx['balance'] = format_price(max(0, $total - $paid));

        return $this->applyCourseFees($ctx, (int) ($reg['course_id'] ?? 0) ?: null);
    }

    public function bodyTemplate(string $slug): string
    {
        switch ($slug) {
            case 'admission_letter':
                return "Date: {date}\n\n"
                    . "Dear {recipient_name},\n\n"
                    . "RE: LETTER OF ADMISSION\n\n"
                    . "We are pleased to inform you that you have been provisionally admitted to {course_title} at {institute_name}.\n\n"
                    . "Registration Number: {registration_no}\n"
                    . "Study Mode: {study_mode}\n"
                    . "School Fees: {school_fees}\n\n"
                    . "Please present this letter at the admissions office for verification, date confirmation, and official signing.\n\n"
                    . "Yours faithfully,\n\n"
                    . "_________________________\n"
                    . "Authorized Signatory\n"
                    . "{institute_name}";

            case 'application_letter':
                return "Date: {date}\n\n"
                    . "Dear {recipient_name},\n\n"
                    . "RE: APPLICATION FOR ENROLMENT\n\n"
                    . "Thank you for submitting your application to study {course_title} at {institute_name}. "
                    . "We confirm receipt of your application and supporting documents.\n\n"
                    . "Reference: {registration_no}\n\n"
                    . "Our admissions team will review your application and contact you regarding the outcome. "
                    . "If any additional information is required, we will reach you using the contact details on file.\n\n"
                    . "Yours faithfully,\n\n"
                    . "_________________________\n"
                    . "Admissions Office\n"
                    . "{institute_name}";

            case 'fee_structure':
                return "Date: {date}\n\n"
                    . "Dear {recipient_name},\n\n"
                    . "RE: FEE STRUCTURE — {course_title}\n\n"
                    . "Please find below the approved school fees for {course_title} at {institute_name}:\n\n"
                    . "Online classes: {fee_online}\n"
                    . "Physical classes: {fee_physical}\n\n"
                    . "Fees are subject to the institute's payment policy. Installment plans may be available upon request.\n\n"
                    . "For payment enquiries, contact the finance office.\n\n"
                    . "Yours faithfully,\n\n"
                    . "_________________________\n"
                    . "Finance Office\n"
                    . "{institute_name}";

            case 'invoice':
                return "Date: {date}\n\n"
                    . "Invoice No: {invoice_no}\n\n"
                    . "Bill To:\n{recipient_name}\n{email}\n{phone}\n\n"
                    . "Description: Tuition — {course_title}\n"
                    . "Registration: {registration_no}\n\n"
                    . "School Fees Due: {school_fees}\n\n"
                    . "Payment is due within fourteen (14) days of this invoice date. "
                    . "Please quote the invoice number when making payment.\n\n"
                    . "Yours faithfully,\n\n"
                    . "_________________________\n"
                    . "Accounts Office\n"
                    . "{institute_name}";

            case 'receipt':
                return "Date: {date}\n\n"
                    . "Receipt No: {receipt_no}\n\n"
                    . "Received from: {recipient_name}\n"
                    . "Registration: {registration_no}\n"
                    . "Course: {course_title}\n\n"
                    . "School Fees: {school_fees}\n"
                    . "Amount Received: {amount_paid}\n"
                    . "Payment Reference: {payment_reference}\n\n"
                    . "This receipt confirms payment received. Please retain for your records.\n\n"
                    . "Yours faithfully,\n\n"
                    . "_________________________\n"
                    . "Finance Office\n"
                    . "{institute_name}";

            case 'remembrance_note':
                return "Date: {date}\n\n"
                    . "Dear {recipient_name},\n\n"
                    . "RE: REMINDER — OUTSTANDING SCHOOL FEES\n\n"
                    . "This is a courteous reminder regarding your enrollment in {course_title} at {institute_name}.\n\n"
                    . "Registration Number: {registration_no}\n"
                    . "Total School Fees: {school_fees}\n"
                    . "Amount Paid to Date: {amount_paid}\n"
                    . "Outstanding Balance: {balance}\n\n"
                    . "Please arrange to settle the outstanding balance at your earliest convenience. "
                    . "If you have already made payment, kindly disregard this notice and forward your payment confirmation to the finance office.\n\n"
                    . "For payment enquiries, contact the finance office.\n\n"
                    . "Yours faithfully,\n\n"
                    . "_________________________\n"
                    . "Finance Office\n"
                    . "{institute_name}";

            default:
                return "Date: {date}\n\nDear {recipient_name},\n\n[Your message here]\n\nYours faithfully,\n{institute_name}";
        }
    }

    public function defaultBody(string $slug, array $ctx): string
    {
        $ctx = $this->normalizeContext($ctx);
        $ctx['recipient_name'] = $ctx['recipient_name'] !== '' ? $ctx['recipient_name'] : '_________________________';
        $ctx['course_title'] = $ctx['course_title'] !== '' ? $ctx['course_title'] : '_________________________';
        $ctx['registration_no'] = $ctx['registration_no'] !== '' ? $ctx['registration_no'] : '_________________________';
        if ($ctx['fee_online'] === '') {
            $ctx['fee_online'] = 'As advised';
        }
        if ($ctx['fee_physical'] === '') {
            $ctx['fee_physical'] = 'As advised';
        }
        if ($ctx['school_fees'] === '') {
            $ctx['school_fees'] = 'As advised';
        }
        if ($ctx['amount_paid'] === '') {
            $ctx['amount_paid'] = '_________________________';
        }
        if ($ctx['balance'] === '') {
            $ctx['balance'] = '_________________________';
        }
        $ctx['amount'] = $ctx['school_fees'];
        if ($ctx['payment_reference'] === '') {
            $ctx['payment_reference'] = '_________________________';
        }
        if ($ctx['email'] === '') {
            $ctx['email'] = '';
        }
        if ($ctx['phone'] === '') {
            $ctx['phone'] = '';
        }

        return document_merge_placeholders($this->bodyTemplate($slug), $ctx);
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

        $ctx = $this->normalizeContext($ctx);
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

    public function composedDocumentsTableExists(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM sms_composed_documents LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function admissionDocumentsTableExists(): bool
    {
        try {
            $this->pdo->query('SELECT 1 FROM admission_documents LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array{
     *   template_slug:string,
     *   template_label:string,
     *   delivery:string,
     *   recipient_name?:string,
     *   recipient_email?:string,
     *   registration_no?:string,
     *   course_id?:int|null,
     *   course_title?:string,
     *   sms_student_id?:int|null,
     *   file_path:string,
     *   file_name:string,
     *   email_subject?:string,
     *   actor_id?:int|null
     * } $data
     */
    public function recordIssuedDocument(array $data): ?int
    {
        if (!$this->composedDocumentsTableExists()) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO sms_composed_documents (
                    template_slug, template_label, delivery, recipient_name, recipient_email,
                    registration_no, course_id, course_title, sms_student_id,
                    file_path, file_name, email_subject, actor_id
                ) VALUES (
                    :template_slug, :template_label, :delivery, :recipient_name, :recipient_email,
                    :registration_no, :course_id, :course_title, :sms_student_id,
                    :file_path, :file_name, :email_subject, :actor_id
                )'
            );
            $stmt->execute([
                ':template_slug'    => $data['template_slug'],
                ':template_label'   => $data['template_label'],
                ':delivery'         => $data['delivery'] === 'email' ? 'email' : 'download',
                ':recipient_name'   => ($data['recipient_name'] ?? '') !== '' ? $data['recipient_name'] : null,
                ':recipient_email'  => ($data['recipient_email'] ?? '') !== '' ? $data['recipient_email'] : null,
                ':registration_no'  => ($data['registration_no'] ?? '') !== '' ? $data['registration_no'] : null,
                ':course_id'        => !empty($data['course_id']) ? (int) $data['course_id'] : null,
                ':course_title'     => ($data['course_title'] ?? '') !== '' ? $data['course_title'] : null,
                ':sms_student_id'   => !empty($data['sms_student_id']) ? (int) $data['sms_student_id'] : null,
                ':file_path'        => $data['file_path'],
                ':file_name'        => $data['file_name'],
                ':email_subject'    => ($data['email_subject'] ?? '') !== '' ? $data['email_subject'] : null,
                ':actor_id'         => !empty($data['actor_id']) ? (int) $data['actor_id'] : null,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (\Throwable $e) {
            error_log('[DocumentComposeService] recordIssuedDocument: ' . $e->getMessage());
            return null;
        }
    }

    public function getIssuedDocument(int $id): ?array
    {
        if (!$this->composedDocumentsTableExists()) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT d.*, u.username AS actor_name
             FROM sms_composed_documents d
             LEFT JOIN users u ON u.id = d.actor_id
             WHERE d.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getAdmissionDocument(int $id): ?array
    {
        if (!$this->admissionDocumentsTableExists()) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT ad.*, r.email AS recipient_email
             FROM admission_documents ad
             LEFT JOIN registrations r ON r.id = ad.registration_id
             WHERE ad.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function listDocumentHistory(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $source = $filters['source'] ?? 'all';
        $items = [];

        if ($source !== 'enrollment' && $this->composedDocumentsTableExists()) {
            $items = array_merge($items, $this->fetchComposedHistoryRows($filters));
        }

        if ($source !== 'composed' && $this->admissionDocumentsTableExists()) {
            $items = array_merge($items, $this->fetchAdmissionHistoryRows($filters));
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        $total = count($items);
        $items = array_slice($items, $offset, $limit);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchComposedHistoryRows(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['template']) && $filters['template'] !== 'all') {
            $where[] = 'd.template_slug = :template';
            $params[':template'] = $filters['template'];
        }
        if (!empty($filters['delivery']) && in_array($filters['delivery'], ['download', 'email'], true)) {
            $where[] = 'd.delivery = :delivery';
            $params[':delivery'] = $filters['delivery'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(d.recipient_name LIKE :q OR d.recipient_email LIKE :q OR d.registration_no LIKE :q OR d.course_title LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $sql = 'SELECT d.*, u.username AS actor_name
                FROM sms_composed_documents d
                LEFT JOIN users u ON u.id = d.actor_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY d.created_at DESC
                LIMIT 500';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            $row['history_source'] = 'composed';
            $row['history_id'] = (int) $row['id'];
            $row['document_label'] = (string) ($row['template_label'] ?? 'Document');
            $row['delivery_label'] = ($row['delivery'] ?? '') === 'email' ? 'Emailed' : 'Downloaded';
            return $row;
        }, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAdmissionHistoryRows(array $filters): array
    {
        if (!empty($filters['delivery']) && $filters['delivery'] !== 'all' && $filters['delivery'] !== 'enrollment') {
            return [];
        }
        if (!empty($filters['template']) && $filters['template'] !== 'all' && $filters['template'] !== 'admission_letter') {
            return [];
        }

        $where = ['1=1'];
        $params = [];
        if (!empty($filters['q'])) {
            $where[] = '(ad.student_name LIKE :q OR ad.registration_no LIKE :q OR ad.course_title LIKE :q OR r.email LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }

        $sql = 'SELECT ad.*, r.email AS recipient_email
                FROM admission_documents ad
                LEFT JOIN registrations r ON r.id = ad.registration_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ad.created_at DESC
                LIMIT 500';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            return [
                'history_source'   => 'enrollment',
                'history_id'       => (int) $row['id'],
                'document_label'   => 'Admission Letter',
                'template_slug'    => 'admission_letter',
                'delivery'         => 'enrollment',
                'delivery_label'   => 'Enrollment',
                'recipient_name'   => (string) ($row['student_name'] ?? ''),
                'recipient_email'  => (string) ($row['recipient_email'] ?? ''),
                'registration_no'  => (string) ($row['registration_no'] ?? ''),
                'course_title'     => (string) ($row['course_title'] ?? ''),
                'file_path'        => (string) ($row['file_path'] ?? ''),
                'file_name'        => basename((string) ($row['file_path'] ?? 'admission.pdf')),
                'email_subject'    => null,
                'actor_name'       => null,
                'created_at'       => (string) ($row['created_at'] ?? ''),
            ];
        }, $rows);
    }

    public function absoluteDocumentPath(string $relPath): ?string
    {
        $relPath = ltrim(str_replace('\\', '/', $relPath), '/');
        if ($relPath === '' || strpos($relPath, '..') !== false) {
            return null;
        }

        if (!function_exists('public_path')) {
            require_once __DIR__ . '/../config/paths.php';
        }

        $abs = public_path($relPath);
        if (!is_file($abs)) {
            return null;
        }

        $uploadsRoot = realpath(public_path('uploads'));
        $fileReal = realpath($abs);
        if (!$uploadsRoot || !$fileReal || strpos($fileReal, $uploadsRoot) !== 0) {
            return null;
        }

        return $fileReal;
    }
}
