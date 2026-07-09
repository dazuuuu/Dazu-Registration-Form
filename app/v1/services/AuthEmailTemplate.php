<?php
// app/v1/services/AuthEmailTemplate.php

class AuthEmailTemplate {
    public static function render(string $event, array $data): array {
        switch ($event) {
            case 'password_reset_otp':
                $minutes = (int) ($data['expires_minutes'] ?? 15);
                return [
                    'Your password reset code — ' . ($data['company_name'] ?? 'Dazu Hub Institute'),
                    self::layout(
                        'Password Reset Code',
                        '<p>Hi ' . self::e($data['recipient_name'] ?? 'there') . ',</p>'
                        . '<p>We received a request to reset the password for your '
                        . self::e($data['company_name'] ?? 'Dazu Hub Institute') . ' account.</p>'
                        . '<p>Use this one-time verification code:</p>'
                        . '<div style="text-align:center;margin:28px 0;">'
                        . '<span style="display:inline-block;background:#f1f6f5;border:2px dashed #0D9488;'
                        . 'border-radius:10px;padding:18px 32px;font-size:32px;letter-spacing:8px;'
                        . 'font-weight:bold;color:#0D9488;font-family:monospace;">'
                        . self::e($data['otp'] ?? '')
                        . '</span></div>'
                        . '<p>This code expires in <strong>' . $minutes . ' minutes</strong>. '
                        . 'If you did not request a password reset, you can safely ignore this email.</p>'
                        . '<p style="color:#888;font-size:13px;">Account role: '
                        . self::e($data['role_label'] ?? 'User') . '</p>'
                    ),
                ];
            default:
                return ['Notification', self::layout('Notification', '<p>You have a notification.</p>')];
        }
    }

    private static function layout(string $heading, string $body): string {
        return '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">'
            . '<div style="text-align:center;padding:24px 0;border-bottom:1px solid #eee;">'
            . '<img src="cid:ismanlogo" alt="Logo" style="max-height:56px;"></div>'
            . '<div style="padding:24px;"><h2 style="color:#0D9488;">' . self::e($heading) . '</h2>' . $body . '</div>'
            . '<div style="padding:16px 24px;border-top:1px solid #eee;color:#888;font-size:12px;text-align:center;">'
            . 'Dazu Hub Institute — do not share this code with anyone.</div></div>';
    }

    private static function e($value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
