<?php
// app/models/TutorModel.php
namespace Models;

class TutorModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function getRoleId(): int {
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE role_name = 'tutor' LIMIT 1");
        $stmt->execute();
        $id = (int) $stmt->fetchColumn();
        if (!$id) throw new \RuntimeException("'tutor' role not found. Run migration 015.");
        return $id;
    }

    public function listAll(): array {
        $stmt = $this->db->query(
            "SELECT t.*, u.username, u.email, u.is_active AS user_active,
                    up.first_name, up.last_name
             FROM tutors t
             JOIN users u ON t.user_id = u.id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             ORDER BY t.created_at DESC"
        );
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['courses'] = $this->getAssignedCourses((int) $r['id']);
        }
        return $rows;
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT t.*, u.username, u.email, u.is_active AS user_active,
                    up.first_name, up.last_name
             FROM tutors t
             JOIN users u ON t.user_id = u.id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE t.id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row) {
            $row['courses'] = $this->getAssignedCourses($id);
        }
        return $row ?: null;
    }

    public function getByUserId(int $userId): ?array {
        $stmt = $this->db->prepare(
            "SELECT t.*, u.username, u.email, up.first_name, up.last_name
             FROM tutors t
             JOIN users u ON t.user_id = u.id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE t.user_id = :uid AND t.is_active = 1"
        );
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch();
        if ($row) {
            $row['courses'] = $this->getAssignedCourses((int) $row['id']);
        }
        return $row ?: null;
    }

    public function getForCourse(int $courseId): array {
        $stmt = $this->db->prepare(
            "SELECT t.*, u.email, up.first_name, up.last_name
             FROM tutors t
             JOIN course_tutors ct ON ct.tutor_id = t.id
             JOIN users u ON t.user_id = u.id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE ct.course_id = :cid AND t.is_active = 1"
        );
        $stmt->execute([':cid' => $courseId]);
        return $stmt->fetchAll();
    }

    public function getAssignedCourses(int $tutorId): array {
        $stmt = $this->db->prepare(
            "SELECT c.id, c.title, c.abbreviation
             FROM courses c
             JOIN course_tutors ct ON ct.course_id = c.id
             WHERE ct.tutor_id = :tid ORDER BY c.title"
        );
        $stmt->execute([':tid' => $tutorId]);
        return $stmt->fetchAll();
    }

    public function tutorTeachesCourse(int $tutorId, int $courseId): bool {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM course_tutors WHERE tutor_id = :tid AND course_id = :cid"
        );
        $stmt->execute([':tid' => $tutorId, ':cid' => $courseId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return array{ok:bool, error?:string, tutor_id?:int, temp_password?:string}
     */
    public function create(array $data, array $courseIds): array {
        $email = strtolower(trim($data['email'] ?? ''));
        $fullName = trim($data['full_name'] ?? '');
        if (!$email || !$fullName) {
            return ['ok' => false, 'error' => 'Name and email are required.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid email address.'];
        }

        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetchColumn()) {
            return ['ok' => false, 'error' => 'A user with this email already exists.'];
        }

        $parts = explode(' ', $fullName, 2);
        $username = $this->generateUsername($email);
        $tempPassword = bin2hex(random_bytes(8));
        $roleId = $this->getRoleId();

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status)
                 VALUES (:username, :email, :hash, :role_id, 1, 1, 'active')"
            );
            $stmt->execute([
                ':username' => $username,
                ':email'    => $email,
                ':hash'     => password_hash($tempPassword, PASSWORD_DEFAULT),
                ':role_id'  => $roleId,
            ]);
            $userId = (int) $this->db->lastInsertId();

            $stmt = $this->db->prepare(
                "INSERT INTO user_profiles (user_id, first_name, last_name, phone)
                 VALUES (:uid, :fn, :ln, :phone)"
            );
            $stmt->execute([
                ':uid'   => $userId,
                ':fn'    => $parts[0],
                ':ln'    => $parts[1] ?? '',
                ':phone' => trim($data['phone'] ?? ''),
            ]);

            $stmt = $this->db->prepare(
                "INSERT INTO tutors (user_id, bio, photo_path, phone) VALUES (:uid, :bio, :photo, :phone)"
            );
            $stmt->execute([
                ':uid'   => $userId,
                ':bio'   => trim($data['bio'] ?? ''),
                ':photo' => $data['photo_path'] ?? null,
                ':phone' => trim($data['phone'] ?? ''),
            ]);
            $tutorId = (int) $this->db->lastInsertId();

            $this->syncCourses($tutorId, $courseIds);

            $this->db->commit();
            return ['ok' => true, 'tutor_id' => $tutorId, 'user_id' => $userId, 'temp_password' => $tempPassword, 'username' => $username];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function update(int $tutorId, array $data, array $courseIds): array {
        $tutor = $this->getById($tutorId);
        if (!$tutor) return ['ok' => false, 'error' => 'Tutor not found.'];

        $parts = explode(' ', trim($data['full_name'] ?? ''), 2);
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "UPDATE tutors SET bio = :bio, phone = :phone, is_active = :active"
                . (!empty($data['photo_path']) ? ", photo_path = :photo" : "")
                . " WHERE id = :id"
            );
            $params = [
                ':bio'    => trim($data['bio'] ?? ''),
                ':phone'  => trim($data['phone'] ?? ''),
                ':active' => !empty($data['is_active']) ? 1 : 0,
                ':id'     => $tutorId,
            ];
            if (!empty($data['photo_path'])) $params[':photo'] = $data['photo_path'];
            $stmt->execute($params);

            $stmt = $this->db->prepare(
                "UPDATE user_profiles SET first_name = :fn, last_name = :ln, phone = :phone WHERE user_id = :uid"
            );
            $stmt->execute([
                ':fn'    => $parts[0],
                ':ln'    => $parts[1] ?? '',
                ':phone' => trim($data['phone'] ?? ''),
                ':uid'   => $tutor['user_id'],
            ]);

            $this->syncCourses($tutorId, $courseIds);
            $this->db->commit();
            return ['ok' => true];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function syncCourses(int $tutorId, array $courseIds): void {
        $stmt = $this->db->prepare("DELETE FROM course_tutors WHERE tutor_id = :tid");
        $stmt->execute([':tid' => $tutorId]);
        $ins = $this->db->prepare("INSERT INTO course_tutors (course_id, tutor_id) VALUES (:cid, :tid)");
        foreach (array_unique(array_map('intval', $courseIds)) as $cid) {
            if ($cid > 0) $ins->execute([':cid' => $cid, ':tid' => $tutorId]);
        }
    }

    public function markCredentialsSent(int $tutorId): void {
        $stmt = $this->db->prepare("UPDATE tutors SET credentials_sent_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $tutorId]);
    }

    public function getStudentsForTutor(int $tutorId): array {
        $stmt = $this->db->prepare(
            "SELECT r.*, c.title AS course_title,
                    (SELECT COUNT(*) FROM student_unit_progress sup
                     JOIN curriculum_units cu ON cu.id = sup.unit_id
                     WHERE sup.registration_id = r.id AND sup.completed_at IS NOT NULL) AS units_completed,
                    (SELECT COUNT(*) FROM curriculum_units cu2 WHERE cu2.course_id = r.course_id AND cu2.is_active = 1) AS total_units
             FROM registrations r
             JOIN courses c ON r.course_id = c.id
             JOIN course_tutors ct ON ct.course_id = r.course_id
             WHERE ct.tutor_id = :tid AND r.status = 'approved'
             ORDER BY r.full_name"
        );
        $stmt->execute([':tid' => $tutorId]);
        return $stmt->fetchAll();
    }

    private function generateUsername(string $email): string {
        $base = preg_replace('/[^a-z0-9]/', '', strtolower(explode('@', $email)[0]));
        $base = substr($base, 0, 20) ?: 'tutor';
        $username = 'TUT-' . $base;
        $n = 1;
        while ($this->usernameExists($username)) {
            $username = 'TUT-' . $base . $n++;
        }
        return $username;
    }

    private function usernameExists(string $username): bool {
        $stmt = $this->db->prepare("SELECT 1 FROM users WHERE username = :u");
        $stmt->execute([':u' => $username]);
        return (bool) $stmt->fetchColumn();
    }

    public function fullName(array $tutor): string {
        $name = trim(($tutor['first_name'] ?? '') . ' ' . ($tutor['last_name'] ?? ''));
        return $name ?: ($tutor['username'] ?? 'Tutor');
    }
}
