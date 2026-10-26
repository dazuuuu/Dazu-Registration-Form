<?php
// app/v1/services/AuthEmailTemplate.php

class AuthEmailTemplate {
    public static function render(string $event, array $data): array {
        switch ($event) {
            case 'password_reset_otp':
                $minutes = (int) ($data['expires_minutes'] ?? 15);
                return [
                    'Your password reset code — ' . ($data['company_name'] ?? 'Dazu Training Institute'),
                    self::layout(
                        'Password Reset Code',
                        '<p>Hi ' . self::e($data['recipient_name'] ?? 'there') . ',</p>'
                        . '<p>We received a request to reset the password for your '
                        . self::e($data['company_name'] ?? 'Dazu Training Institute') . ' account.</p>'
                        . '<p>Use this one-time verification code:</p>'
                        . '<div style="text-align:center;margin:28px 0;">'
                        . '<span style="display:inline-block;background:#f0fdf4;border:2px dashed #16a34a;'
                        . 'border-radius:10px;padding:18px 32px;font-size:32px;letter-spacing:8px;'
                        . 'font-weight:bold;color:#15803d;font-family:monospace;">'
                        . self::e($data['otp'] ?? '')
                        . '</span></div>'
                        . '<p>This code expires in <strong>' . $minutes . ' minutes</strong>. '
                        . 'If you did not request a password reset, you can safely ignore this email.</p>'
                        . '<p style="color:#64748b;font-size:13px;">Account role: '
                        . self::e($data['role_label'] ?? 'User') . '</p>'
                    ),
                ];
            default:
                return ['Notification — Dazu Training Institute', self::layout('Notification', '<p>You have a notification.</p>')];
        }
    }

    private static function layout(string $heading, string $body): string {
        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">'
            . '<div style="background:#0f766e;padding:22px;text-align:center;">'
            . '<h1 style="color:#ffffff;margin:0;font-size:20px;letter-spacing:.02em;font-weight:bold;">DAZU TRAINING INSTITUTE</h1></div>'
            . '<div style="padding:26px 24px;"><h2 style="color:#0f766e;margin:0 0 16px;">' . self::e($heading) . '</h2>' . $body . '</div>'
            . '<div style="padding:16px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;color:#64748b;font-size:12px;text-align:center;">'
            . 'Dazu Training Institute &middot; Do not share this code with anyone.</div></div>';
    }

    private static function e($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
