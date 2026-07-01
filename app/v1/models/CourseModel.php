<?php
// app/models/CourseModel.php
namespace Models;

class CourseModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    /**
     * @return array{ok:bool, error?:string, id?:int}
     */
    public function create(array $data, ?int $createdBy): array {
        $err = $this->validate($data);
        if ($err) return ['ok' => false, 'error' => $err];

        $sql = "INSERT INTO courses (title, abbreviation, cover_image, description, overview, duration_months, course_fee, created_by)
                VALUES (:title, :abbreviation, :cover_image, :description, :overview, :duration_months, :course_fee, :created_by)";
        $stmt = $this->db->prepare($sql);
        try {
            $stmt->execute([
                ':title'            => trim($data['title']),
                ':abbreviation'     => strtoupper(trim($data['abbreviation'])),
                ':cover_image'      => $data['cover_image'] ?? null,
                ':description'      => $data['description'] ?? '',
                ':overview'         => $data['overview'] ?? '',
                ':duration_months'  => (int) $data['duration_months'],
                ':course_fee'       => (float) $data['course_fee'],
                ':created_by'       => $createdBy,
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'error' => 'That abbreviation is already used by another course.'];
            }
            throw $e;
        }
        return ['ok' => true, 'id' => (int) $this->db->lastInsertId()];
    }

    public function update(int $id, array $data): array {
        $err = $this->validate($data);
        if ($err) return ['ok' => false, 'error' => $err];

        $fields = "title = :title, abbreviation = :abbreviation, description = :description,
                   overview = :overview, duration_months = :duration_months, course_fee = :course_fee";
        $params = [
            ':title'           => trim($data['title']),
            ':abbreviation'    => strtoupper(trim($data['abbreviation'])),
            ':description'     => $data['description'] ?? '',
            ':overview'        => $data['overview'] ?? '',
            ':duration_months' => (int) $data['duration_months'],
            ':course_fee'      => (float) $data['course_fee'],
            ':id'              => $id,
        ];

        if (!empty($data['cover_image'])) {
            $fields .= ", cover_image = :cover_image";
            $params[':cover_image'] = $data['cover_image'];
        }

        $sql = "UPDATE courses SET $fields WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        try {
            $stmt->execute($params);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'error' => 'That abbreviation is already used by another course.'];
            }
            throw $e;
        }
        return ['ok' => true];
    }

    public function setActive(int $id, bool $active): bool {
        $stmt = $this->db->prepare("UPDATE courses SET is_active = :a WHERE id = :id");
        return $stmt->execute([':a' => $active ? 1 : 0, ':id' => $id]);
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM courses WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getAll(bool $activeOnly = false): array {
        $sql = "SELECT * FROM courses" . ($activeOnly ? " WHERE is_active = 1" : "") . " ORDER BY title ASC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Atomically claims the next sequence number for a course (e.g. 1 -> "01").
     * Must run inside the same DB transaction as the registration approval that uses it,
     * so two admins approving simultaneously can never receive the same number.
     * Resets implicitly each calendar year via the caller appending the year, NOT by
     * resetting this counter — see RegistrationModel::approve().
     */
    public function claimNextSequence(int $courseId): int {
        $this->db->prepare("SELECT last_sequence FROM courses WHERE id = :id FOR UPDATE")
                  ->execute([':id' => $courseId]);

        $stmt = $this->db->prepare("UPDATE courses SET last_sequence = last_sequence + 1 WHERE id = :id");
        $stmt->execute([':id' => $courseId]);

        $stmt = $this->db->prepare("SELECT last_sequence FROM courses WHERE id = :id");
        $stmt->execute([':id' => $courseId]);
        return (int) $stmt->fetchColumn();
    }

    private function validate(array $data): ?string {
        if (empty(trim($data['title'] ?? ''))) return 'Course title is required.';
        if (empty(trim($data['abbreviation'] ?? ''))) return 'Course abbreviation is required.';
        if (!preg_match('/^[A-Za-z0-9]{2,10}$/', trim($data['abbreviation']))) {
            return 'Abbreviation must be 2-10 letters or numbers, no spaces.';
        }
        $duration = (int) ($data['duration_months'] ?? 0);
        if ($duration < 1 || $duration > 36) return 'Duration must be between 1 and 36 months.';

        $fee = (float) ($data['course_fee'] ?? 0);
        if ($fee <= 0) {
            return 'Course fee must be greater than zero.';
        }
        return null;
    }
}