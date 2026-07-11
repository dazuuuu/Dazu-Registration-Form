<?php
// app/v1/models/PasswordResetModel.php

class PasswordResetModel {
    private PDO $db;
    private int $otpTtlMinutes = 15;
    private int $maxRequestsPerHour = 3;
    private int $maxVerifyAttempts = 5;

    public function __construct(PDO $pdo) {
        $this->db = $pdo;
    }

    public function countRecentRequests(string $email): int {
        $sql = "SELECT COUNT(*) FROM password_reset_otps
                WHERE email = :email AND created_at >= (NOW() - INTERVAL 1 HOUR)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        return (int) $stmt->fetchColumn();
    }

    public function invalidateActiveForUser(int $userId): void {
        $sql = "UPDATE password_reset_otps
                SET used_at = NOW()
                WHERE user_id = :user_id AND used_at IS NULL AND expires_at > NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
    }

    /** @return array{otp:string,expires_at:string} */
    public function createOtp(int $userId, string $email, string $ip): array {
        $this->invalidateActiveForUser($userId);

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + ($this->otpTtlMinutes * 60));

        $sql = "INSERT INTO password_reset_otps (user_id, email, otp_hash, expires_at, ip_address)
                VALUES (:user_id, :email, :otp_hash, :expires_at, :ip)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':email' => $email,
            ':otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
            ':expires_at' => $expiresAt,
            ':ip' => $ip,
        ]);

        return ['otp' => $otp, 'expires_at' => $expiresAt];
    }

    /** @return array{ok:bool,message?:string,user_id?:int} */
    public function verifyOtp(string $email, string $otp): array {
        $sql = "SELECT * FROM password_reset_otps
                WHERE email = :email AND used_at IS NULL AND expires_at > NOW()
                ORDER BY created_at DESC
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['ok' => false, 'message' => 'Invalid or expired verification code. Request a new one.'];
        }

        if ((int) $row['attempts'] >= $this->maxVerifyAttempts) {
            $this->markUsed((int) $row['id']);
            return ['ok' => false, 'message' => 'Too many failed attempts. Request a new verification code.'];
        }

        if (!password_verify($otp, $row['otp_hash'])) {
            $this->incrementAttempts((int) $row['id']);
            return ['ok' => false, 'message' => 'Invalid verification code. Please try again.'];
        }

        $this->markUsed((int) $row['id']);
        return ['ok' => true, 'user_id' => (int) $row['user_id']];
    }

    public function isRateLimited(string $email): bool {
        return $this->countRecentRequests($email) >= $this->maxRequestsPerHour;
    }

    public function getOtpTtlMinutes(): int {
        return $this->otpTtlMinutes;
    }

    private function incrementAttempts(int $id): void {
        $stmt = $this->db->prepare(
            "UPDATE password_reset_otps SET attempts = attempts + 1 WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }

    private function markUsed(int $id): void {
        $stmt = $this->db->prepare(
            "UPDATE password_reset_otps SET used_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }
}
