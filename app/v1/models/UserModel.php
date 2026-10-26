<?php
// app/models/UserModel.php
require_once __DIR__ . '/../config/db_connect.php';

class UserModel {
    private $db;
    
    public function __construct($pdo) {
        $this->db = $pdo;
    }
    
    public function createUser($username, $email, $password, $role_id = 3) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified) 
                VALUES (:username, :email, :password_hash, :role_id, 1, 1)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $password_hash,
            ':role_id' => $role_id
        ]);
    }
    
    public function getUserByEmail($email) {
        $sql = "SELECT u.*, COALESCE(r.role_name,
                    CASE
                        WHEN u.role_id = 1 THEN 'superadmin'
                        WHEN u.role_id = 2 THEN 'admin'
                        WHEN u.role_id = 4 THEN 'tutor'
                        ELSE 'student'
                    END
                ) AS role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE LOWER(u.email) = LOWER(:email)
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => trim((string) $email)]);
        return $stmt->fetch();
    }
    
    public function getUserByUsername($username) {
        $sql = "SELECT u.*, COALESCE(r.role_name,
                    CASE
                        WHEN u.role_id = 1 THEN 'superadmin'
                        WHEN u.role_id = 2 THEN 'admin'
                        WHEN u.role_id = 4 THEN 'tutor'
                        ELSE 'student'
                    END
                ) AS role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE LOWER(u.username) = LOWER(:username)
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':username' => trim((string)$username)]);
        return $stmt->fetch();
    }

    public function getUserByIdentifier($identifier) {
        $ident = trim((string) $identifier);
        if ($ident === '') {
            return null;
        }

        $user = $this->getUserByEmail($ident);
        if ($user) {
            return $user;
        }

        $user = $this->getUserByUsername($ident);
        if ($user) {
            return $user;
        }

        try {
            $sql = "SELECT u.*, COALESCE(r.role_name,
                        CASE
                            WHEN u.role_id = 1 THEN 'superadmin'
                            WHEN u.role_id = 2 THEN 'admin'
                            WHEN u.role_id = 4 THEN 'tutor'
                            ELSE 'student'
                        END
                    ) AS role_name
                    FROM registrations reg
                    JOIN users u ON reg.user_id = u.id
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE LOWER(reg.registration_no) = LOWER(:id)
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $ident]);
            $res = $stmt->fetch();
            if ($res) {
                return $res;
            }
        } catch (\Throwable $e) {}

        try {
            $sql = "SELECT u.*, COALESCE(r.role_name, 'student') AS role_name
                    FROM sms_students s
                    JOIN users u ON s.user_id = u.id
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE LOWER(s.email) = LOWER(:e)
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':e' => $ident]);
            $res = $stmt->fetch();
            if ($res) {
                return $res;
            }
        } catch (\Throwable $e) {}

        return null;
    }
    
    public function getUserById($id) {
        $sql = "SELECT u.*, COALESCE(r.role_name,
                    CASE
                        WHEN u.role_id = 1 THEN 'superadmin'
                        WHEN u.role_id = 2 THEN 'admin'
                        WHEN u.role_id = 4 THEN 'tutor'
                        ELSE 'student'
                    END
                ) AS role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
    
    public function updateProfile($user_id, $first_name, $last_name, $phone) {
        $sql = "INSERT INTO user_profiles (user_id, first_name, last_name, phone) 
                VALUES (:user_id, :first_name, :last_name, :phone)
                ON DUPLICATE KEY UPDATE 
                first_name = VALUES(first_name), 
                last_name = VALUES(last_name), 
                phone = VALUES(phone)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':user_id' => $user_id,
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':phone' => $phone
        ]);
    }
    
    public function getProfile($user_id) {
        $sql = "SELECT * FROM user_profiles WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetch();
    }
    
    public function updatePassword($user_id, $newPassword) {
        $password_hash = password_hash($newPassword, PASSWORD_DEFAULT);
        try {
            $sql = "UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':hash' => $password_hash, ':id' => $user_id]) && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'must_change_password') === false) {
                throw $e;
            }
            $sql = "UPDATE users SET password_hash = :hash WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':hash' => $password_hash, ':id' => $user_id]) && $stmt->rowCount() > 0;
        }
    }
    
    public function logLoginAttempt($email, $ip) {
        $sql = "INSERT INTO login_attempts (email, ip_address) VALUES (:email, :ip)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':email' => $email, ':ip' => $ip]);
    }
}
?>
