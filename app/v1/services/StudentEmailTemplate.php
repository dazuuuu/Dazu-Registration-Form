<?php
// app/services/StudentEmailTemplate.php
// HTML email templates for the student registration and payment workflow.

require_once __DIR__ . '/../helpers/currency.php';

if (!class_exists('StudentEmailTemplate')) {
class StudentEmailTemplate {

    /**
     * @return array [subject, html]
     */
    public static function render(string $event, array $data): array {
        $data['full_name'] = $data['full_name'] ?? $data['name'] ?? 'Student';
        switch ($event) {
            case 'registration_received':
                $name = trim((string) ($data['full_name'] ?? ''));
                $first = $name !== '' ? explode(' ', $name)[0] : '';
                $greeting = $first !== '' ? 'Hi ' . self::e($first) . ',' : 'Hi,';
                return [
                    'Welcome to Dazu Training Institute',
                    '<p style="font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#111827;">'
                    . $greeting . '</p>'
                    . '<p style="font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#111827;">Welcome to Dazu Training Institute.</p>'
                    . '<p style="font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#111827;">Please find the attached files.</p>',
                ];

            case 'fee_structure':
                $name = trim((string) ($data['full_name'] ?? ''));
                $first = $name !== '' ? explode(' ', $name)[0] : '';
                $greeting = $first !== '' ? 'Hi ' . self::e($first) . ',' : 'Hi,';
                return [
                    'Fee Structure — Dazu Training Institute',
                    '<p style="font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#111827;">'
                    . $greeting . '</p>'
                    . '<p style="font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#111827;">Please find the attached fee structure statement from Dazu Training Institute.</p>',
                ];

            case 'admission_letter':
                $name = trim((string) ($data['full_name'] ?? $data['name'] ?? 'Student'));
                return [
                    'Official Admission Letter & Fee Structure — Dazu Training Institute',
                    self::layout(
                        'Admission Letter & Fee Structure',
                        '<p style="font-size:15px;margin:0 0 12px;font-weight:700;color:#0f172a;">Dear ' . self::e($name) . ',</p>'
                        . '<p style="font-size:13.5px;line-height:1.6;color:#334155;margin:0 0 16px;">We are pleased to offer you admission to <strong>Dazu Training Institute</strong>. Below are your enrolment details, credentials, and fee structure.</p>'
                        . self::credentialsBlock($data)
                        . self::feeStructureBlock($data)
                        . '<p style="font-size:13px;line-height:1.6;color:#334155;margin:16px 0 20px;">Your official <strong>Admission Letter and Fee Structure</strong> document is attached as a PDF. Please download and preserve a copy for your records.</p>'
                        . '<p style="font-size:13px;color:#0f172a;margin:0 0 4px;">Yours faithfully,</p>'
                        . '<p style="font-size:13px;font-weight:700;color:#0f172a;margin:0;">Admissions Office<br><span style="font-weight:400;color:#64748b;">Dazu Training Institute</span></p>'
                    ),
                ];

            case 'registration_form':
                return [
                    'Your Registration Form — Dazu Training Institute',
                    self::layout(
                        'Registration Form',
                        '<p>Hi ' . self::e($data['full_name']) . ',</p>'
                        . '<p>Your registration form for <strong>' . self::e($data['course_title'] ?? '') . '</strong> at <strong>Dazu Training Institute</strong> is attached.</p>'
                        . '<p>Keep a copy for your records. If any details need updating, please reply directly to this email.</p>'
                        . self::courseBlock($data)
                    ),
                ];

            case 'registration_approved':
                $name = trim((string) ($data['full_name'] ?? $data['name'] ?? 'Student'));
                return [
                    'Admission Confirmation & Student Credentials — Dazu Training Institute',
                    self::layout(
                        'Admission Confirmation & Credentials',
                        '<p style="font-size:15px;margin:0 0 12px;font-weight:700;color:#0f172a;">Dear ' . self::e($name) . ',</p>'
                        . '<p style="font-size:13.5px;line-height:1.6;color:#334155;margin:0 0 16px;">We are pleased to offer you admission to <strong>Dazu Training Institute</strong>. Your student account has been created. Below are your enrolment details, student credentials, and fee structure.</p>'
                        . self::credentialsBlock($data)
                        . self::feeStructureBlock($data)
                        . '<p style="font-size:13px;line-height:1.6;color:#334155;margin:16px 0 20px;">Your official <strong>Admission Letter and Fee Structure</strong> document has been attached to this email as a PDF. Please download and preserve a copy for your records.</p>'
                        . '<p style="font-size:13px;color:#0f172a;margin:0 0 4px;">Yours faithfully,</p>'
                        . '<p style="font-size:13px;font-weight:700;color:#0f172a;margin:0;">Admissions Office<br><span style="font-weight:400;color:#64748b;">Dazu Training Institute</span></p>'
                    ),
                ];

            case 'documents_pack':
                $name = trim((string) ($data['full_name'] ?? $data['name'] ?? 'Student'));
                $courseTitle = (string) ($data['course_title'] ?? ($data['course']['title'] ?? 'your enrolled programme'));
                return [
                    'Official Admission Documents — Admission Letter, Fee Structure & Course Brochure | Dazu Training Institute',
                    self::layout(
                        'Official Admission Documents Pack',
                        '<p style="font-size:15px;margin:0 0 12px;font-weight:700;color:#0f172a;">Dear ' . self::e($name) . ',</p>'
                        . '<p style="font-size:13.5px;line-height:1.6;color:#334155;margin:0 0 16px;">We are pleased to send you your complete official admission document package for <strong>' . self::e($courseTitle) . '</strong> at <strong>Dazu Training Institute</strong>.</p>'
                        . '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px 16px;margin:0 0 16px;">'
                        . '<div style="font-size:11.5px;font-weight:800;color:#166534;letter-spacing:1px;text-transform:uppercase;margin-bottom:8px;">Included Documents in This Email (Attached as PDFs)</div>'
                        . '<ul style="margin:0;padding-left:18px;font-size:13px;line-height:1.7;color:#15803d;">'
                        . '<li><strong>1. Official Admission Letter:</strong> Formal offer of admission with semester schedule.</li>'
                        . '<li><strong>2. Programme Fee Structure:</strong> Complete semester and installment fee breakdown.</li>'
                        . '<li><strong>3. Course Brochure &amp; Syllabus:</strong> Detailed curriculum outline and learning modules.</li>'
                        . '</ul>'
                        . '</div>'
                        . self::credentialsBlock($data)
                        . self::feeStructureBlock($data)
                        . '<p style="font-size:13px;line-height:1.6;color:#334155;margin:16px 0 20px;">All documents listed above are attached to this email as downloadable PDF files. Please download and preserve them safely for your records.</p>'
                        . '<p style="font-size:13px;color:#0f172a;margin:0 0 4px;">Yours faithfully,</p>'
                        . '<p style="font-size:13px;font-weight:700;color:#0f172a;margin:0;">Admissions Office<br><span style="font-weight:400;color:#64748b;">Dazu Training Institute</span></p>'
                    ),
                ];

            case 'registration_rejected':
                return [
                    'Registration Update — Dazu Training Institute',
                    self::layout(
                        'Registration Not Approved',
                        '<p>Hi ' . self::e($data['full_name']) . ',</p>'
                        . '<p>We regret to inform you that we were unable to approve your registration at this time.</p>'
                        . '<div style="background:#fef2f2;border-left:4px solid #dc2626;padding:12px 16px;margin:16px 0;">'
                        . '<strong>Reason:</strong><br>' . nl2br(self::e($data['rejection_reason'] ?? ''))
                        . '</div>'
                        . '<p>If you believe this is an error or need clarification, please contact our admissions office.</p>'
                    ),
                ];

            case 'credentials_sent':
                $name = trim((string) ($data['full_name'] ?? $data['name'] ?? 'Student'));
                return [
                    'Your Student Portal Login Credentials — Dazu Training Institute',
                    self::layout(
                        'Student Login Credentials',
                        '<p style="font-size:15px;margin:0 0 12px;font-weight:700;color:#0f172a;">Dear ' . self::e($name) . ',</p>'
                        . '<p style="font-size:13.5px;line-height:1.6;color:#334155;margin:0 0 16px;">Here are your student portal login credentials, admission details, and fee structure for <strong>Dazu Training Institute</strong>:</p>'
                        . self::credentialsBlock($data)
                        . self::feeStructureBlock($data)
                        . '<p style="font-size:13px;line-height:1.6;color:#334155;margin:16px 0 20px;">Your official <strong>Admission Letter and Fee Structure</strong> document has been attached to this email as a PDF. Please download and preserve a copy for your records.</p>'
                        . '<p style="font-size:13px;color:#0f172a;margin:0 0 4px;">Yours faithfully,</p>'
                        . '<p style="font-size:13px;font-weight:700;color:#0f172a;margin:0;">Admissions Office<br><span style="font-weight:400;color:#64748b;">Dazu Training Institute</span></p>'
                    ),
                ];

            case 'payment_submitted':
                return [
                    'Payment Received — Pending Verification',
                    self::layout(
                        'Payment Submitted',
                        '<p>Hi ' . self::e($data['full_name']) . ',</p>'
                        . '<p>We have received your school fees payment submission and it is now awaiting verification by our finance office.</p>'
                        . self::paymentBlock($data)
                        . '<p>You will receive an official receipt once your payment is confirmed.</p>'
                    ),
                ];

            case 'payment_approved':
                $receiptUrl = '';
                if (!empty($data['payment_id']) && function_exists('url')) {
                    $receiptUrl = url('students/payments/receipt.php?id=' . (int) $data['payment_id']);
                }
                return [
                    'Payment Confirmed — Receipt Enclosed',
                    self::layout(
                        'Payment Confirmed',
                        '<p>Hi ' . self::e($data['full_name']) . ',</p>'
                        . '<p>Your payment has been <strong>verified and approved</strong>. Your student portal is unlocked.</p>'
                        . ($data['receipt_html'] ?? '')
                        . ($receiptUrl !== ''
                            ? '<p style="text-align:center;margin:16px 0;">'
                                . '<a href="' . self::e($data['login_url'] ?? $receiptUrl) . '" style="background:#0f766e;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;margin:4px;">Go to Student Portal</a>'
                                . '</p>'
                            : '<p style="text-align:center;margin:24px 0;">'
                                . '<a href="' . self::e($data['login_url']) . '" style="background:#0f766e;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">Go to Student Portal</a>'
                                . '</p>')
                    ),
                ];

            case 'payment_rejected':
                return [
                    'Payment Could Not Be Verified — Dazu Training Institute',
                    self::layout(
                        'Payment Not Verified',
                        '<p>Hi ' . self::e($data['full_name']) . ',</p>'
                        . '<p>We were unable to verify your submitted payment.</p>'
                        . '<div style="background:#fef2f2;border-left:4px solid #dc2626;padding:12px 16px;margin:16px 0;">'
                        . '<strong>Reason:</strong><br>' . nl2br(self::e($data['rejection_reason'] ?? ''))
                        . '</div>'
                        . self::paymentBlock($data)
                        . '<p>Please re-submit your payment with the correct transaction reference code.</p>'
                    ),
                ];

            case 'portal_locked':
                return [
                    'Student Portal Notice — Payment Required',
                    self::layout(
                        'Portal Access Suspended',
                        '<p>Hi ' . self::e($data['full_name']) . ',</p>'
                        . '<p>Your student portal access has been temporarily suspended because your next fee installment is due.</p>'
                        . '<div style="background:#fff7ed;border-left:4px solid #f59e0b;padding:12px 16px;margin:16px 0;">'
                        . 'Amount due: <strong>' . format_price($data['amount_due'] ?? 0) . '</strong><br>'
                        . 'Due date: <strong>' . self::e($data['due_date'] ?? '') . '</strong>'
                        . '</div>'
                        . '<p>Please log in to your portal to submit your fee payment reference.</p>'
                        . '<p style="text-align:center;margin:24px 0;">'
                        . '<a href="' . self::e($data['login_url']) . '" style="background:#0f766e;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">Submit Payment</a>'
                        . '</p>'
                    ),
                ];

            case 'course_updated':
                $courseUrl = $data['course_url'] ?? '';
                $cover = $data['cover_image_url'] ?? '';
                return [
                    'Course Update: ' . ($data['course_title'] ?? ''),
                    self::layout(
                        'Course Update',
                        '<p>Hi ' . self::e($data['full_name'] ?? '') . ',</p>'
                        . '<p>A course has been updated at <strong>Dazu Training Institute</strong>:</p>'
                        . ($cover !== ''
                            ? '<img src="' . self::e($cover) . '" alt="" style="width:100%;max-height:220px;object-fit:cover;border-radius:8px;margin:12px 0;">'
                            : '')
                        . '<h3 style="margin:8px 0 4px;color:#0f766e;">' . self::e($data['course_title'] ?? '') . '</h3>'
                        . '<p style="color:#444;">' . nl2br(self::e($data['course_overview'] ?? '')) . '</p>'
                        . ($courseUrl !== ''
                            ? '<p style="text-align:center;margin:24px 0;">'
                                . '<a href="' . self::e($courseUrl) . '" style="background:#0f766e;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">View Course</a>'
                                . '</p>'
                            : '')
                    ),
                ];

            case 'certificate_issued':
                $certUrl = $data['certificate_url'] ?? '';
                return [
                    'Your Certificate is Ready! — Dazu Training Institute',
                    self::layout(
                        'Certificate Issued',
                        '<p>Hi ' . self::e($data['full_name']) . ',</p>'
                        . '<p>Congratulations! Your certificate for <strong>' . self::e($data['course_title'] ?? '') . '</strong> has been issued.</p>'
                        . (!empty($data['pdf_attached'])
                            ? '<p>Your certificate is attached to this email as a PDF. You can also download it any time from your student portal.</p>'
                            : '<p>You can now view and download your official certificate from your student portal.</p>')
                        . ($certUrl !== ''
                            ? '<p style="text-align:center;margin:24px 0;">'
                                . '<a href="' . self::e($certUrl) . '" style="background:#0f766e;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;">View Certificate</a>'
                                . '</p>'
                            : '')
                    ),
                ];

            default:
                return ['Notification — Dazu Training Institute', self::layout('Notification', '<p>You have a new notification from Dazu Training Institute.</p>')];
        }
    }

    private static function credentialsBlock(array $data): string {
        $name = self::e(trim((string) ($data['full_name'] ?? $data['name'] ?? '')));
        $email = self::e(trim((string) ($data['email'] ?? $data['login_email'] ?? $data['student_email'] ?? '')));
        if ($email === '' && !empty($data['id'])) {
            try {
                if (class_exists('Models\\Connection')) {
                    $pdo = \Models\Connection::getInstance()->getConnection();
                    $stmt = $pdo->prepare('SELECT email FROM registrations WHERE id = :id LIMIT 1');
                    $stmt->execute([':id' => (int) $data['id']]);
                    $f = $stmt->fetchColumn();
                    if ($f) $email = self::e(trim((string) $f));
                }
            } catch (\Throwable $e) {}
        }
        $admNo = self::e(trim((string) ($data['registration_no'] ?? $data['admission_no'] ?? $data['username'] ?? '')));
        $course = self::e(trim((string) ($data['course_title'] ?? ($data['course']['title'] ?? ''))));
        $username = self::e(trim((string) ($data['username'] ?? $data['registration_no'] ?? '')));
        $pass = self::e(trim((string) ($data['temp_password'] ?? '')));
        $loginUrl = self::e($data['login_url'] ?? '');

        return '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid #0f766e;border-radius:6px;padding:16px 18px;margin:16px 0;font-size:13px;line-height:1.8;">'
            . '<div style="font-size:11px;font-weight:700;color:#0f766e;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Student Admission &amp; Credentials</div>'
            . '<table style="width:100%;border-collapse:collapse;font-size:13px;">'
            . ($name !== '' ? '<tr><td style="padding:4px 0;color:#64748b;width:40%;">Student Name:</td><td style="padding:4px 0;font-weight:700;color:#0f172a;">' . $name . '</td></tr>' : '')
            . ($email !== '' ? '<tr><td style="padding:4px 0;color:#64748b;">Student Email / Login:</td><td style="padding:4px 0;font-weight:700;color:#0f766e;font-size:13.5px;">' . $email . '</td></tr>' : '')
            . ($admNo !== '' ? '<tr><td style="padding:4px 0;color:#64748b;">Registration Number:</td><td style="padding:4px 0;font-weight:700;color:#0f172a;font-family:monospace;font-size:14px;">' . $admNo . '</td></tr>' : '')
            . ($course !== '' ? '<tr><td style="padding:4px 0;color:#64748b;">Enrolled Course:</td><td style="padding:4px 0;font-weight:700;color:#0f172a;">' . $course . '</td></tr>' : '')
            . ($username !== '' ? '<tr><td style="padding:4px 0;color:#64748b;">Portal Username:</td><td style="padding:4px 0;font-family:monospace;font-weight:700;color:#0f172a;">' . $username . '</td></tr>' : '')
            . ($pass !== '' ? '<tr><td style="padding:4px 0;color:#64748b;">Temporary Password:</td><td style="padding:4px 0;"><code style="background:#e0f2fe;color:#0369a1;padding:3px 10px;border-radius:4px;font-weight:700;font-size:13.5px;font-family:monospace;">' . $pass . '</code></td></tr>' : '')
            . '</table>'
            . ($loginUrl !== '' ? '<div style="margin-top:14px;text-align:center;"><a href="' . $loginUrl . '" style="background:#0f766e;color:#ffffff;padding:10px 24px;border-radius:6px;text-decoration:none;font-weight:700;font-size:13px;display:inline-block;">Log In to Student Portal</a></div>' : '')
            . '</div>';
    }

    private static function feeStructureBlock(array $data): string {
        $course = $data['course'] ?? [];
        if (empty($course) && !empty($data['course_id'])) {
            try {
                if (class_exists('Models\\Connection')) {
                    $pdo = \Models\Connection::getInstance()->getConnection();
                    $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = :id LIMIT 1');
                    $stmt->execute([':id' => (int) $data['course_id']]);
                    $course = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
                }
            } catch (\Throwable $e) {}
        }

        $months = (int) ($course['duration_months'] ?? ($data['duration_months'] ?? 0));
        if ($months <= 0) $months = 18;
        $isOneMonth = ($months === 1) || (!empty($course['duration_type']) && $course['duration_type'] === 'short');

        $durationText = $isOneMonth ? '1 month' : ($months . ' months');
        $semesterLengthText = $isOneMonth ? 'Full course (1 month)' : '3 months per semester';

        $totalFee = 0.0;
        if (function_exists('course_fee_modes_catalog')) {
            $modes = course_fee_modes_catalog($course);
            $active = $modes[$data['study_mode'] ?? 'online'] ?? $modes['online'] ?? $modes['physical'] ?? [];
            $totalFee = course_fee_mode_total($active);
        }
        if ($totalFee <= 0) {
            $totalFee = (float) ($course['course_fee'] ?? ($data['course_fee'] ?? 360000.0));
        }

        $monthlyFee = $isOneMonth ? 0 : round($totalFee / max(1, $months), 2);

        $payment = function_exists('payment_credentials')
            ? payment_credentials()
            : ['business_short_code' => '880100', 'account_number' => '681158', 'phone' => '0722 922 454'];
        $paybill = htmlspecialchars((string) ($payment['business_short_code'] ?? '880100'));
        $account = htmlspecialchars((string) ($payment['account_number'] ?? '681158'));
        $phone = htmlspecialchars((string) ($payment['phone'] ?? '0722 922 454'));

        $totalFormatted = function_exists('format_price') ? format_price($totalFee) : ('KSh ' . number_format($totalFee, 2));
        $monthlyFormatted = function_exists('format_price') ? format_price($monthlyFee) : ('KSh ' . number_format($monthlyFee, 2));

        $monthlyText = $isOneMonth 
            ? 'Full course payment: ' . $totalFormatted
            : $monthlyFormatted . ' per month &times; ' . $months . ' months';

        return '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid #0284c7;border-radius:6px;padding:16px 18px;margin:16px 0;font-size:13px;line-height:1.8;">'
            . '<div style="font-size:11px;font-weight:700;color:#0284c7;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Programme Fee Structure</div>'
            . '<table style="width:100%;border-collapse:collapse;font-size:13px;">'
            . '<tr><td style="padding:4px 0;color:#64748b;width:40%;">Programme Duration:</td><td style="padding:4px 0;font-weight:700;color:#0f172a;">' . htmlspecialchars($durationText . ' (' . $semesterLengthText . ')') . '</td></tr>'
            . '<tr><td style="padding:4px 0;color:#64748b;">Total Programme Fees:</td><td style="padding:4px 0;font-weight:800;color:#0f172a;">' . htmlspecialchars($totalFormatted) . '</td></tr>'
            . '<tr><td style="padding:4px 0;color:#64748b;">Monthly Payment Option:</td><td style="padding:4px 0;font-weight:700;color:#0f766e;">' . $monthlyText . '</td></tr>'
            . '</table>'
            . '<div style="margin-top:10px;padding-top:10px;border-top:1px dashed #cbd5e1;font-size:12px;color:#475569;">'
            . '<strong>Fee Payment Details:</strong> M-PESA Paybill: <strong>' . $paybill . '</strong> &bull; Account: <strong>' . $account . '</strong> &bull; Phone: <strong>' . $phone . '</strong>'
            . '</div>'
            . '</div>';
    }

    private static function courseBlock(array $data): string {
        $admNo = self::e(trim((string) ($data['registration_no'] ?? $data['admission_no'] ?? '')));
        return '<table style="width:100%;font-size:14px;margin:16px 0;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:6px;">'
            . ($admNo !== '' ? '<tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:10px 14px;color:#64748b;background:#f8fafc;">Admission Number</td><td style="padding:10px 14px;text-align:right;"><strong>' . $admNo . '</strong></td></tr>' : '')
            . '<tr style="border-bottom:1px solid #e2e8f0;"><td style="padding:10px 14px;color:#64748b;background:#f8fafc;">Course</td><td style="padding:10px 14px;text-align:right;"><strong>' . self::e($data['course_title'] ?? '') . '</strong></td></tr>'
            . '<tr><td style="padding:10px 14px;color:#64748b;background:#f8fafc;">Study Mode</td><td style="padding:10px 14px;text-align:right;">' . self::e(($data['study_mode'] ?? '') === 'physical' ? 'Physical classes' : 'Online classes') . '</td></tr>'
            . '</table>';
    }

    private static function paymentBlock(array $data): string {
        return '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:16px 18px;margin:16px 0;font-size:14px;line-height:1.7;">'
            . '<div><strong style="color:#334155;">Amount:</strong> ' . format_price($data['amount'] ?? 0) . '</div>'
            . '<div><strong style="color:#334155;">Transaction Reference:</strong> <span style="font-family:monospace;font-weight:700;">' . self::e($data['mpesa_code'] ?? '') . '</span></div>'
            . '<div><strong style="color:#334155;">Payer Details:</strong> ' . self::e($data['payer_reference'] ?? '') . '</div>'
            . '</div>';
    }

    private static function layout(string $heading, string $bodyHtml): string {
        $company = 'Dazu Training Institute';
        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#1e293b;background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">'
            . '<div style="background:#0f766e;padding:22px 20px;text-align:center;">'
            . '<h1 style="color:#ffffff;margin:0;font-size:20px;letter-spacing:.02em;font-weight:800;">DAZU TRAINING INSTITUTE</h1>'
            . '<div style="color:#ccfbf1;font-size:10px;font-weight:700;letter-spacing:2px;margin-top:4px;">ADMISSIONS OFFICE</div>'
            . '</div>'
            . '<div style="padding:24px 22px;">'
            . '<h2 style="color:#0f766e;margin:0 0 16px;font-size:17px;font-weight:700;">' . self::e($heading) . '</h2>'
            . $bodyHtml
            . '</div>'
            . '<div style="padding:14px 20px;background:#f8fafc;border-top:1px solid #e2e8f0;color:#64748b;font-size:11.5px;text-align:center;">'
            . self::e($company) . ' &bull; dazuhub.ac.ke'
            . '</div>'
            . '</div>';
    }

    private static function e($v): string {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}
}

