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
            return ['success' => false, 'message' => 'Please enter a valid email address.'];
        }

        if ($this->resetModel->isRateLimited($email)) {
            return [
                'success' => false,
                'message' => 'Too many reset requests. Please wait an hour and try again.',
            ];
        }

        $user = $this->userModel->getUserByEmail($email);
        if (!$user || !$this->canResetAccount($user)) {
            return ['success' => true, 'message' => $generic, 'sent' => false];
        }

        $profile = $this->userModel->getProfile((int) $user['id']);
        $recipientName = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''));
        if ($recipientName === '') {
            $recipientName = $user['username'] ?? $email;
        }

        try {
            $otpData = $this->resetModel->createOtp((int) $user['id'], $email, $ip);
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'password_reset_otps') !== false) {
                return ['success' => false, 'message' => 'Password reset is not configured. Run migration 022_password_reset_otps.sql.'];
            }
            throw $e;
        }

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
                'message' => $mailResult['message'] ?? 'Could not send verification email.',
            ];
        }

        return [
            'success' => true,
            'message' => $generic,
            'sent' => true,
            'email' => $email,
        ];
    }

    public function resetPassword(string $email, string $otp, string $password, string $confirm): array {
        $email = strtolower(trim($email));
        $otp = trim($otp);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address.'];
        }

        if (!preg_match('/^\d{6}$/', $otp)) {
            return ['success' => false, 'message' => 'Verification code must be a 6-digit number.'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
        }

        if ($password !== $confirm) {
            return ['success' => false, 'message' => 'Passwords do not match.'];
        }

        $verify = $this->resetModel->verifyOtp($email, $otp);
        if (!$verify['ok']) {
            return ['success' => false, 'message' => $verify['message'] ?? 'Invalid verification code.'];
        }

        $user = $this->userModel->getUserById((int) $verify['user_id']);
        if (!$user || !$this->canResetAccount($user)) {
            return ['success' => false, 'message' => 'This account cannot be reset. Contact the administrator.'];
        }

        if (!$this->userModel->updatePassword((int) $user['id'], $password)) {
            return ['success' => false, 'message' => 'Failed to update password. Please try again.'];
        }

        $updatedUser = $this->userModel->getUserById((int) $user['id']);
        if (!$updatedUser || !password_verify($password, $updatedUser['password_hash'] ?? '')) {
            return ['success' => false, 'message' => 'Password update could not be verified. Please request a new code and try again.'];
        }

        return [
            'success' => true,
            'message' => 'Password updated successfully. You can now sign in.',
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
