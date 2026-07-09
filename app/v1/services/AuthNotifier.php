<?php
// app/v1/services/AuthNotifier.php

require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/AuthEmailTemplate.php';

class AuthNotifier {
    private Mailer $mailer;
    private string $companyName;

    public function __construct(?Mailer $mailer = null) {
        $cfg = require __DIR__ . '/../config/mail.php';
        $this->mailer = $mailer ?? new Mailer($cfg);
        $this->companyName = $cfg['from_name'] ?? 'Dazu Hub Institute';
    }

    public function sendPasswordResetOtp(
        string $email,
        string $recipientName,
        string $otp,
        string $roleLabel,
        int $expiresMinutes = 15
    ): array {
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
                'message' => 'Email delivery is disabled. Enable SMTP in app/v1/config/mail.php to send password reset codes.',
            ];
        }

        return $result;
    }
}
