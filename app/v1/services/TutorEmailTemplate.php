<?php
// app/services/TutorEmailTemplate.php
class TutorEmailTemplate {

    public static function render(string $event, array $data): array {
        switch ($event) {
            case 'credentials_sent':
                return [
                    'Your tutor portal login credentials',
                    self::layout(
                        'Welcome, Tutor!',
                        '<p>Hi ' . self::e($data['full_name']) . ',</p>'
                        . '<p>Your tutor account at <strong>' . self::e($data['company_name']) . '</strong> has been created.</p>'
                        . self::credentialsBlock($data)
                        . '<p style="text-align:center;margin:24px 0;">'
                        . '<a href="' . self::e($data['login_url']) . '" style="background:#0D9488;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;">Go to Tutor Portal</a>'
                        . '</p>'
                        . (!empty($data['courses']) ? '<p><strong>Assigned courses:</strong> ' . self::e(implode(', ', $data['courses'])) . '</p>' : '')
                    ),
                ];
            case 'submission_received':
                return [
                    'New student submission to grade',
                    self::layout(
                        'New Submission',
                        '<p>Hi ' . self::e($data['tutor_name']) . ',</p>'
                        . '<p><strong>' . self::e($data['student_name']) . '</strong> submitted a '
                        . self::e($data['submission_type']) . ': <strong>' . self::e($data['item_title']) . '</strong></p>'
                        . '<p style="text-align:center;margin:24px 0;">'
                        . '<a href="' . self::e($data['portal_url']) . '" style="background:#0D9488;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;">Review Submission</a>'
                        . '</p>'
                    ),
                ];
            case 'graded':
                return [
                    'Your ' . ($data['submission_type'] ?? 'work') . ' has been graded',
                    self::layout(
                        'Submission Graded',
                        '<p>Hi ' . self::e($data['student_name']) . ',</p>'
                        . '<p>Your tutor has graded your <strong>' . self::e($data['item_title']) . '</strong>.</p>'
                        . '<div style="background:#f1f6f5;border-radius:8px;padding:16px;margin:16px 0;">'
                        . '<strong>Marks:</strong> ' . self::e($data['marks']) . ' / ' . self::e($data['max_marks']) . '<br>'
                        . '<strong>Feedback:</strong> ' . nl2br(self::e($data['feedback'] ?? ''))
                        . '</div>'
                        . '<p style="text-align:center;margin:24px 0;">'
                        . '<a href="' . self::e($data['portal_url']) . '" style="background:#0D9488;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;">View Course</a>'
                        . '</p>'
                    ),
                ];
            default:
                return ['Notification', self::layout('Notification', '<p>You have a notification.</p>')];
        }
    }

    private static function credentialsBlock(array $data): string {
        return '<div style="background:#f1f6f5;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;">'
            . '<strong>Username:</strong> ' . self::e($data['username'] ?? '') . '<br>'
            . '<strong>Email:</strong> ' . self::e($data['email'] ?? '') . '<br>'
            . '<strong>Temporary Password:</strong> <code style="background:#e8f5f3;padding:2px 8px;border-radius:4px;">'
            . self::e($data['temp_password'] ?? '') . '</code></div>';
    }

    private static function layout(string $heading, string $body): string {
        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">'
            . '<div style="background:#0f766e;padding:22px;text-align:center;">'
            . '<h1 style="color:#ffffff;margin:0;font-size:20px;letter-spacing:.02em;font-weight:bold;">DAZU TRAINING INSTITUTE</h1></div>'
            . '<div style="padding:26px 24px;"><h2 style="color:#0f766e;margin:0 0 16px;">' . self::e($heading) . '</h2>' . $body . '</div>'
            . '<div style="padding:16px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px;text-align:center;">'
            . 'Dazu Training Institute &middot; Need help? Reply to this email.</div></div>';
    }

    private static function e($v): string {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}
