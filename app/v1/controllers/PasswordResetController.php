<?php
// app/v1/controllers/PasswordResetController.php

require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/PasswordResetModel.php';
require_once __DIR__ . '/../services/AuthNotifier.php';

class PasswordResetController {
    private UserModel $userModel;
    private PasswordResetModel $resetModel;
    private AuthNotifier $notifier;

    public function __construct(PDO $pdo, ?AuthNotifier $notifier = null) {
        $this->userModel = new UserModel($pdo);
        $this->resetModel = new PasswordResetModel($pdo);
        $this->notifier = $notifier ?? new AuthNotifier();
    }

    public function requestOtp(string $email, string $ip): array {
        $email = strtolower(trim($email));
        $generic = 'If that email is registered, a verification code has been sent. Check your inbox.';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => '[err] invalid EMAIL format'];
        }

        if ($this->resetModel->isRateLimited($email)) {
            return [
                'success' => false,
                'message' => '[err] too many reset requests — wait an hour and try again',
            ];
        }

        $user = $this->userModel->getUserByEmail($email);
        if (!$user || !$this->canResetAccount($user)) {
            return ['success' => true, 'message' => '[ ok ] ' . $generic, 'sent' => false];
        }

        $profile = $this->userModel->getProfile((int) $user['id']);
        $recipientName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
        if ($recipientName === '') {
            $recipientName = $user['username'] ?? $email;
        }

        $otpData = $this->resetModel->createOtp((int) $user['id'], $email, $ip);
        $mailResult = $this->notifier->sendPasswordResetOtp(
            $email,
            $recipientName,
            $otpData['otp'],
            $this->roleLabel($user['role_name'] ?? 'user'),
            $this->resetModel->getOtpTtlMinutes()
        );

        if (empty($mailResult['ok'])) {
            return [
                'success' => false,
                'message' => '[err] could not send email — ' . ($mailResult['message'] ?? 'mail error'),
            ];
        }

        return [
            'success' => true,
            'message' => '[ ok ] ' . $generic,
            'sent' => true,
            'email' => $email,
        ];
    }

    public function resetPassword(string $email, string $otp, string $password, string $confirm): array {
        $email = strtolower(trim($email));
        $otp = trim($otp);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => '[err] invalid EMAIL format'];
        }

        if (!preg_match('/^\d{6}$/', $otp)) {
            return ['success' => false, 'message' => '[err] OTP must be a 6-digit code'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'message' => '[err] PASS must be at least 6 characters'];
        }

        if ($password !== $confirm) {
            return ['success' => false, 'message' => '[err] PASS and PASS_CONFIRM do not match'];
        }

        $verify = $this->resetModel->verifyOtp($email, $otp);
        if (!$verify['ok']) {
            return ['success' => false, 'message' => '[err] ' . ($verify['message'] ?? 'invalid OTP')];
        }

        $user = $this->userModel->getUserById((int) $verify['user_id']);
        if (!$user || !$this->canResetAccount($user)) {
            return ['success' => false, 'message' => '[err] account cannot be reset — contact administrator'];
        }

        if (!$this->userModel->updatePassword((int) $user['id'], $password)) {
            return ['success' => false, 'message' => '[err] failed to update password — try again'];
        }

        return [
            'success' => true,
            'message' => '[ ok ] password updated — redirecting to login...',
        ];
    }

    private function canResetAccount(array $user): bool {
        if (empty($user['is_active'])) {
            return false;
        }

        if (isset($user['account_status']) && $user['account_status'] === 'suspended') {
            return false;
        }

        return true;
    }

    private function roleLabel(string $roleName): string {
        $labels = [
            'superadmin' => 'Super Admin',
            'admin' => 'Admin',
            'tutor' => 'Tutor',
            'student' => 'Student',
            'user' => 'User',
        ];

        return $labels[$roleName] ?? ucfirst($roleName);
    }
}
