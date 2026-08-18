<?php
// app/services/StudentEmailTemplate.php
// HTML email templates for the student registration and payment workflow.

require_once __DIR__ . '/../helpers/currency.php';

class StudentEmailTemplate {

    /**
     * @return array [subject, html]
     */
    public static function render(string $event, array $data): array {
        switch ($event) {
            case 'registration_received':
                return [
                    'Registration received — awaiting approval',
                    self::layout(
                        'Application Received',
                        '<p>Hi ' . self::e($data['full_name']) . ',</p>'
                        . '<p>Thank you for registering at <strong>' . self::e($data['company_name']) . '</strong>. '
                        . 'We have received your application for <strong>' . self::e($data['course_title']) . '</strong>.</p>'
                        . '<div style="background:#f1f6f5;border-radius:8px;padding:16px;margin:16px 0;">'
                        . '<strong>What happens next?</strong><br>'
                        . (!empty($data['is_free'])
                            ? 'This course is free — our team will review your application and documents.'
                            : 'Our team will verify your payment reference and review your documents.')
                        . ' You will receive another email once your registration is approved or if we need more information.'
                        . '</div>'
                        . self::courseBlock($data)
                    ),
                ];

            case 'registration_approved':
                return [
                    'Welcome to ' . ($data['company_name'] ?? 'Dazu Hub Institute') . ' — your account is ready',
                    self::layout(
                        'Registration Approved!',
                        '<p>Hi ' . self::e($data['full_name']) . ',</p>'
                        . '<p>Great news — your registration has been <strong>approved</strong>! '
                        . 'Your student account has been created.</p>'
                        . self::credentialsBlock($data)
                        . '<p>Log in to your student portal to view your course details and payment history:</p>'
                        . '<p style="text-align:center;margin:24px 0;">'
                        . '<a href="' . self::e($data['login_url']) . '" style="background:#0D9488;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;">Go to Student Portal</a>'
                        . '</p>'
                        . self::courseBlock($data)
                    ),
                ];

            case 'registration_rejected':
                return [
                    'Registration update — action required',
                    self::layout(
                        'Registration Not Approved',
                        '<p>Hi ' . self::e($data['full_name']) . ',</p>'
                        . '<p>Unfortunately, we were unable to approve your registration at this time.</p>'
                        . '<div style="background:#fef2f2;border-left:4px solid #dc2626;padding:12px 16px;margin:16px 0;">'
                        . '<strong>Reason:</strong><br>' . nl2br(self::e($data['rejection_reason'] ?? ''))
                        . '</div>'
                        . '<p>If you believe this is an error, please contact us with your payment reference.</p>'
                    ),
                ];

            case 'credentials_sent':
                return [
                    'Your student portal login credentials',
                    self::layout(
                        'Login Credentials',
                        '<p>Hi ' . self::e($data['full_name']) . ',</p>'
                        . '<p>Here are your student portal login credentials:</p>'
                        . self::credentialsBlock($data)
                        . '<p style="text-align:center;margin:24px 0;">'
                        . '<a href="' . self::e($data['login_url']) . '" style="background:#0D9488;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;">Log In Now</a>'
                        . '</p>'
                        . '<p style="font-size:13px;color:#666;">Please change your password after your first login.</p>'
                    ),
                ];

            case 'payment_submitted':
                return [
                    'Payment received — pending verification',
                    self::layout(
                        'Payment Submitted',
                        '<p>Hi ' . self::e($data['full_name']) . ',</p>'
                        . '<p>We have received your school fees payment submission and it is now <strong>awaiting admin verification</strong>.</p>'
                        . self::paymentBlock($data)
                        . '<p>You will receive a receipt by email once your payment is confirmed.</p>'
                    ),
                ];

            case 'payment_approved':
                $receiptUrl = '';
                if (!empty($data['payment_id']) && function_exists('url')) {
                    $receiptUrl = url('students/payments/receipt.php?id=' . (int) $data['payment_id']);
                }
                return [
                    'Payment confirmed — receipt enclosed',
                    self::layout(
                        'Payment Confirmed',
                        '<p>Hi ' . self::e($data['full_name']) . ',</p>'
                        . '<p>Your payment has been <strong>verified and approved</strong>. Your student portal has been unlocked.</p>'
                        . ($data['receipt_html'] ?? '')
                        . ($receiptUrl !== ''
                            ? '<p style="text-align:center;margin:16px 0;">'
                                . '<a href="' . self::e($data['login_url'] ?? $receiptUrl) . '" style="background:#15A24A;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;display:inline-block;margin:4px;">Go to Student Portal</a>'
                                . '</p>'
                            : '<p style="text-align:center;margin:24px 0;">'
                                . '<a href="' . self::e($data['login_url']) . '" style="background:#15A24A;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;">Go to Student Portal</a>'
                                . '</p>')
                    ),
                ];

            case 'payment_rejected':
                return [
                    'Payment could not be verified',
                    self::layout(
                        'Payment Not Verified',
                        '<p>Hi ' . self::e($data['full_name']) . ',</p>'
                        . '<p>We were unable to verify your submitted payment.</p>'
                        . '<div style="background:#fef2f2;border-left:4px solid #dc2626;padding:12px 16px;margin:16px 0;">'
                        . '<strong>Reason:</strong><br>' . nl2br(self::e($data['rejection_reason'] ?? ''))
                        . '</div>'
                        . self::paymentBlock($data)
                        . '<p>Please submit your payment again with the correct M-Pesa code.</p>'
                    ),
                ];

            case 'portal_locked':
                return [
                    'Student portal locked — payment required',
                    self::layout(
                        'Portal Access Suspended',
                        '<p>Hi ' . self::e($data['full_name']) . ',</p>'
                        . '<p>Your student portal access has been <strong>suspended</strong> because your next installment is due.</p>'
                        . '<div style="background:#fff7ed;border-left:4px solid #f59e0b;padding:12px 16px;margin:16px 0;">'
                        . 'Amount due: <strong>' . format_price($data['amount_due'] ?? 0) . '</strong><br>'
                        . 'Due date: <strong>' . self::e($data['due_date'] ?? '') . '</strong>'
                        . '</div>'
                        . '<p>Log in to your portal to submit your M-Pesa payment code. Your access will be restored once admin verifies your payment.</p>'
                        . '<p style="text-align:center;margin:24px 0;">'
                        . '<a href="' . self::e($data['login_url']) . '" style="background:#0D9488;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;">Submit Payment</a>'
                        . '</p>'
                    ),
                ];

            case 'course_updated':
                $courseUrl = $data['course_url'] ?? '';
                $cover = $data['cover_image_url'] ?? '';
                return [
                    'Course update: ' . ($data['course_title'] ?? ''),
                    self::layout(
                        'Course Update',
                        '<p>Hi ' . self::e($data['full_name'] ?? '') . ',</p>'
                        . '<p>A course has just been updated on <strong>' . self::e($data['company_name'] ?? '') . '</strong>:</p>'
                        . ($cover !== ''
                            ? '<img src="' . self::e($cover) . '" alt="" style="width:100%;max-height:220px;object-fit:cover;border-radius:8px;margin:12px 0;">'
                            : '')
                        . '<h3 style="margin:8px 0 4px;color:#0D9488;">' . self::e($data['course_title'] ?? '') . '</h3>'
                        . '<p style="color:#444;">' . nl2br(self::e($data['course_overview'] ?? '')) . '</p>'
                        . ($courseUrl !== ''
                            ? '<p style="text-align:center;margin:24px 0;">'
                                . '<a href="' . self::e($courseUrl) . '" style="background:#15A24A;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;">View Course</a>'
                                . '</p>'
                            : '')
                    ),
                ];

            default:
                return ['Notification', self::layout('Notification', '<p>You have a new notification.</p>')];
        }
    }

    private static function credentialsBlock(array $data): string {
        return '<div style="background:#f1f6f5;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;">'
            . '<strong>Student ID:</strong> ' . self::e($data['registration_no'] ?? '') . '<br>'
            . '<strong>Username:</strong> ' . self::e($data['username'] ?? $data['registration_no'] ?? '') . '<br>'
            . '<strong>Email:</strong> ' . self::e($data['email'] ?? '') . '<br>'
            . '<strong>Temporary Password:</strong> <code style="background:#e8f5f3;padding:2px 8px;border-radius:4px;">'
            . self::e($data['temp_password'] ?? '') . '</code>'
            . '</div>';
    }

    private static function courseBlock(array $data): string {
        return '<table style="width:100%;font-size:14px;margin:16px 0;border-collapse:collapse;">'
            . '<tr><td style="padding:6px 0;color:#666;">Course</td><td style="text-align:right;"><strong>' . self::e($data['course_title'] ?? '') . '</strong></td></tr>'
            . '<tr><td style="padding:6px 0;color:#666;">Study Mode</td><td style="text-align:right;">' . self::e(ucfirst($data['study_mode'] ?? '')) . '</td></tr>'
            . '<tr><td style="padding:6px 0;color:#666;">Payment Plan</td><td style="text-align:right;">' . self::e($data['payment_plan'] ?? '') . '</td></tr>'
            . '</table>';
    }

    private static function paymentBlock(array $data): string {
        return '<div style="background:#f1f6f5;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;">'
            . '<strong>Amount:</strong> ' . format_price($data['amount'] ?? 0) . '<br>'
            . '<strong>M-Pesa Code:</strong> ' . self::e($data['mpesa_code'] ?? '') . '<br>'
            . '<strong>Payer Details:</strong> ' . self::e($data['payer_reference'] ?? '')
            . '</div>';
    }

    private static function layout(string $heading, string $bodyHtml): string {
        $company = 'Dazu Hub Institute';
        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#1a1a1a;">'
            . '<div style="text-align:center;padding:24px 0;border-bottom:1px solid #eee;">'
            . '<img src="cid:ismanlogo" alt="' . self::e($company) . '" style="max-height:64px;max-width:180px;object-fit:contain;margin-bottom:8px;">'
            . '<div style="font-size:18px;font-weight:bold;color:#15A24A;">' . self::e($company) . '</div>'
            . '</div>'
            . '<div style="padding:24px;">'
            . '<h2 style="color:#0D9488;margin:0 0 8px;">' . self::e($heading) . '</h2>'
            . $bodyHtml
            . '</div>'
            . '<div style="padding:16px 24px;border-top:1px solid #eee;color:#888;font-size:12px;text-align:center;">'
            . self::e($company) . ' &middot; Need help? Reply to this email.'
            . '</div>'
            . '</div>';
    }

    private static function e($v): string {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}
