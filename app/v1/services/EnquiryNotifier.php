<?php
// app/v1/services/EnquiryNotifier.php

class EnquiryNotifier
{
    /**
     * @return array<int, array{email:string, name:string}>
     */
    public static function adminRecipients(PDO $pdo): array
    {
        $stmt = $pdo->query(
            "SELECT u.email, u.username AS name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE r.role_name IN ('admin', 'super_admin')
               AND u.is_active = 1
               AND u.email IS NOT NULL
               AND u.email != ''"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            return array_map(static function ($r) {
                return ['email' => $r['email'], 'name' => $r['name'] ?: 'Admin'];
            }, $rows);
        }

        $cfg = @include __DIR__ . '/../config/mail.php';
        $fallback = $cfg['from_email'] ?? '';
        if ($fallback && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
            return [['email' => $fallback, 'name' => $cfg['from_name'] ?? 'Admin']];
        }

        return [];
    }
}
