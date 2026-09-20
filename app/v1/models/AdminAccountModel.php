<?php
// app/v1/models/AdminAccountModel.php
// Admin (role 2) accounts, managed by the superadmin. Superadmin accounts are never touched here.
namespace Models;

class AdminAccountModel
{
    private $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    private const NAME_SQL = "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', up.first_name, up.last_name)), ''), u.username)";

    public function listAdmins(): array
    {
        return $this->db->query(
            "SELECT u.id, u.username, u.email, u.is_active, u.created_at, " . self::NAME_SQL . " AS full_name
             FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE u.role_id = 2 ORDER BY full_name"
        )->fetchAll();
    }

    public function get(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT u.id, u.username, u.email, u.is_active, " . self::NAME_SQL . " AS full_name
             FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = ? AND u.role_id = 2"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array{ok:bool, error?:string, user_id?:int, temp_password?:string} */
    public function create(string $fullName, string $email, string $phone = ''): array
    {
        $fullName = trim($fullName);
        $email = strtolower(trim($email));
        if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Enter the admin\'s name and a valid email address.'];
        }
        $stmt = $this->db->prepare("SELECT 1 FROM users WHERE LOWER(email) = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            return ['ok' => false, 'error' => 'A user with this email already exists.'];
        }
        $base = substr(preg_replace('/[^a-z0-9]/', '', strtolower(explode('@', $email)[0])), 0, 20) ?: 'admin';
        $username = 'ADM-' . $base;
        for ($n = 1; $this->usernameExists($username); $n++) {
            $username = 'ADM-' . $base . $n;
        }
        $temp = bin2hex(random_bytes(8));
        $parts = explode(' ', $fullName, 2);
        try {
            $this->db->beginTransaction();
            $this->db->prepare(
                "INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status)
                 VALUES (?, ?, ?, 2, 1, 1, 'active')"
            )->execute([$username, $email, password_hash($temp, PASSWORD_DEFAULT)]);
            $uid = (int) $this->db->lastInsertId();
            $this->db->prepare("UPDATE users SET must_change_password = 1 WHERE id = ?")->execute([$uid]);
            $this->db->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, phone) VALUES (?, ?, ?, ?)")
                ->execute([$uid, $parts[0], $parts[1] ?? '', mb_substr(trim($phone), 0, 30)]);
            $this->db->commit();
            return ['ok' => true, 'user_id' => $uid, 'temp_password' => $temp];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('AdminAccountModel::create failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not create the admin. ' . $e->getMessage()];
        }
    }

    public function setActive(int $id, bool $active): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role_id = 2");
        $stmt->execute([$active ? 1 : 0, $id]);
        return $stmt->rowCount() > 0;
    }

    /** New temporary password (must be changed at next sign-in), or null if this is not an admin. */
    public function resetPassword(int $id): ?string
    {
        if (!$this->get($id)) {
            return null;
        }
        $temp = bin2hex(random_bytes(8));
        $this->db->prepare("UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ? AND role_id = 2")
            ->execute([password_hash($temp, PASSWORD_DEFAULT), $id]);
        return $temp;
    }

    private function usernameExists(string $username): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return (bool) $stmt->fetchColumn();
    }
}
