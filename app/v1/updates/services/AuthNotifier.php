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
                'message' => 'Password reset email is disabled. Set MAIL_PASSWORD_RESET_ENABLED=true in your .env file.',
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
                'message' => 'Email delivery is disabled. Configure SMTP in .env (MAIL_HOST, MAIL_USERNAME, MAIL_PASSWORD).',
            ];
        }

        if (!$result['ok']) {
            return [
                'ok' => false,
                'message' => 'Could not send email: ' . ($result['message'] ?? 'SMTP error.'),
            ];
        }

        return $result;
    }
}
