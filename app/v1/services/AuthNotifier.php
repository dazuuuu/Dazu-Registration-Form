<?php
// app/v1/services/AuthNotifier.php

require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/AuthEmailTemplate.php';

class AuthNotifier {
    private Mailer $mailer;
    private string $companyName;
    private bool $passwordResetEnabled;

    public function __construct(?Mailer $mailer = null) {
        $cfg = require __DIR__ . '/../config/mail.php';
        $this->companyName = $cfg['from_name'] ?? 'Dazu Hub Institute';
        $this->passwordResetEnabled = !empty($cfg['password_reset_enabled']);

        if ($mailer !== null) {
            $this->mailer = $mailer;
            return;
        }

        $sendCfg = $cfg;
        if ($this->passwordResetEnabled) {
            $sendCfg['enabled'] = true;
        }

        $this->mailer = new Mailer($sendCfg);
    }

    public function sendPasswordResetOtp(
        string $email,
        string $recipientName,
        string $otp,
        string $roleLabel,
        int $expiresMinutes = 15
    ): array {
        if (!$this->passwordResetEnabled) {
            return [
                'ok' => false,
                'message' => 'Password reset email is disabled. Set password_reset_enabled or MAIL_PASSWORD_RESET_ENABLED=true in app/v1/config/mail.php.',
            ];
        }

        [$subject, $html] = AuthEmailTemplate::render('password_reset_otp', [
            'recipient_name' => $recipientName,
            'otp' => $otp,
            'company_name' => $this->companyName,
            'role_label' => $roleLabel,
            'expires_minutes' => $expiresMinutes,
        ]);

        $result = $this->mailer->send($email, $recipientName, $subject, $html);

        if (!empty($result['skipped'])) {
            return [
                'ok' => false,
                'message' => 'Email delivery is disabled. Enable password_reset_enabled in app/v1/config/mail.php or set MAIL_PASSWORD_RESET_ENABLED=true in .env.',
            ];
        }

        if (!$result['ok']) {
            return [
                'ok' => false,
                'message' => 'Could not send email: ' . ($result['message'] ?? 'SMTP error. Check MAIL_HOST, MAIL_USERNAME, and MAIL_PASSWORD in .env or mail.php.'),
            ];
        }

        return $result;
    }
}
