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

        $abbr = strtoupper(trim($data['abbreviation']));
        if ($taken = $this->abbreviationConflictMessage($abbr)) {
            return ['ok' => false, 'error' => $taken];
        }

        $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;

        $weeksOfStudy = isset($data['weeks_of_study']) && $data['weeks_of_study'] !== '' ? (int) $data['weeks_of_study'] : null;
        $hoursOfStudy = isset($data['hours_of_study']) && $data['hours_of_study'] !== '' ? (int) $data['hours_of_study'] : null;

        try {
            $sql = "INSERT INTO courses (title, abbreviation, category_id, cover_image, description, overview, duration_months, weeks_of_study, hours_of_study, course_fee, is_active, created_by)
                    VALUES (:title, :abbreviation, :category_id, :cover_image, :description, :overview, :duration_months, :weeks_of_study, :hours_of_study, :course_fee, 1, :created_by)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':title'            => trim($data['title']),
                ':abbreviation'     => $abbr,
                ':category_id'      => $categoryId,
                ':cover_image'      => $data['cover_image'] ?? null,
                ':description'      => $data['description'] ?? '',
                ':overview'         => $data['overview'] ?? '',
                ':duration_months'  => (int) $data['duration_months'],
                ':weeks_of_study'   => $weeksOfStudy,
                ':hours_of_study'   => $hoursOfStudy,
                ':course_fee'       => (float) $data['course_fee'],
                ':created_by'       => $this->resolveCreatedBy($createdBy),
            ]);
        } catch (\PDOException $e) {
            if ($categoryId !== null && stripos($e->getMessage(), 'category_id') !== false) {
                $sql = "INSERT INTO courses (title, abbreviation, cover_image, description, overview, duration_months, weeks_of_study, hours_of_study, course_fee, is_active, created_by)
                        VALUES (:title, :abbreviation, :cover_image, :description, :overview, :duration_months, :weeks_of_study, :hours_of_study, :course_fee, 1, :created_by)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':title'            => trim($data['title']),
                    ':abbreviation'     => $abbr,
                    ':cover_image'      => $data['cover_image'] ?? null,
                    ':description'      => $data['description'] ?? '',
                    ':overview'         => $data['overview'] ?? '',
                    ':duration_months'  => (int) $data['duration_months'],
                    ':weeks_of_study'   => $weeksOfStudy,
                    ':hours_of_study'   => $hoursOfStudy,
                    ':course_fee'       => (float) $data['course_fee'],
                    ':created_by'       => $this->resolveCreatedBy($createdBy),
                ]);
            } else {
                $mapped = $this->mapDbError($e, $abbr);
                if ($mapped !== null) {
                    return ['ok' => false, 'error' => $mapped];
                }
                throw $e;
            }
        }
        return ['ok' => true, 'id' => (int) $this->db->lastInsertId()];
    }

    public function update(int $id, array $data): array {
        $err = $this->validate($data);
        if ($err) return ['ok' => false, 'error' => $err];

        $abbr = strtoupper(trim($data['abbreviation']));
        if ($taken = $this->abbreviationConflictMessage($abbr, $id)) {
            return ['ok' => false, 'error' => $taken];
        }

        $categoryId = array_key_exists('category_id', $data)
            ? (!empty($data['category_id']) ? (int) $data['category_id'] : null)
            : null;

        $fields = "title = :title, abbreviation = :abbreviation, category_id = :category_id, description = :description,
                   overview = :overview, duration_months = :duration_months, weeks_of_study = :weeks_of_study,
                   hours_of_study = :hours_of_study, course_fee = :course_fee";
        $params = [
            ':title'           => trim($data['title']),
            ':abbreviation'    => $abbr,
            ':category_id'     => $categoryId,
            ':description'     => $data['description'] ?? '',
            ':overview'        => $data['overview'] ?? '',
            ':duration_months' => (int) $data['duration_months'],
            ':weeks_of_study'  => isset($data['weeks_of_study']) && $data['weeks_of_study'] !== '' ? (int) $data['weeks_of_study'] : null,
            ':hours_of_study'  => isset($data['hours_of_study']) && $data['hours_of_study'] !== '' ? (int) $data['hours_of_study'] : null,
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
            if ($categoryId !== null && stripos($e->getMessage(), 'category_id') !== false) {
                $fields = str_replace('category_id = :category_id, ', '', $fields);
                unset($params[':category_id']);
                $sql = "UPDATE courses SET $fields WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            } else {
                $mapped = $this->mapDbError($e, $abbr);
                if ($mapped !== null) {
                    return ['ok' => false, 'error' => $mapped];
                }
                throw $e;
            }
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

    public function getByAbbreviation(string $abbr): ?array {
        $abbr = strtoupper(trim($abbr));
        if ($abbr === '') {
            return null;
        }
        $stmt = $this->db->prepare("SELECT * FROM courses WHERE UPPER(abbreviation) = :abbr LIMIT 1");
        $stmt->execute([':abbr' => $abbr]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Whether the course can be shown on public pages / registration. */
    public static function isShareable(?array $course): bool {
        if (!$course || empty($course['id'])) {
            return false;
        }
        if (!array_key_exists('is_active', $course)) {
            return true;
        }
        return (int) $course['is_active'] === 1;
    }

    public function getAll(bool $activeOnly = false): array {
        try {
            $sql = "SELECT c.*, cc.name AS category_name, cc.slug AS category_slug, cc.icon_path AS category_icon
                    FROM courses c
                    LEFT JOIN course_categories cc ON cc.id = c.category_id";
            if ($activeOnly) {
                $sql .= " WHERE c.is_active = 1";
            }
            $sql .= " ORDER BY c.title ASC";
            return $this->db->query($sql)->fetchAll();
        } catch (\PDOException $e) {
            $sql = "SELECT * FROM courses" . ($activeOnly ? " WHERE is_active = 1" : "") . " ORDER BY title ASC";
            return $this->db->query($sql)->fetchAll();
        }
    }

    public function getByCategoryId(int $categoryId, bool $activeOnly = true): array {
        $sql = "SELECT c.*, cc.name AS category_name, cc.slug AS category_slug, cc.icon_path AS category_icon
                FROM courses c
                LEFT JOIN course_categories cc ON cc.id = c.category_id
                WHERE c.category_id = :category_id";
        if ($activeOnly) {
            $sql .= " AND c.is_active = 1";
        }
        $sql .= " ORDER BY c.title ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':category_id' => $categoryId]);
        return $stmt->fetchAll();
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

    /** Use NULL when session user id is missing or not in users (common after DB reset). */
    private function resolveCreatedBy(?int $createdBy): ?int {
        return resolve_valid_user_id($this->db, $createdBy, false);
    }

    private function findByAbbreviation(string $abbr, ?int $excludeId = null): ?array {
        $abbr = strtoupper(trim($abbr));
        $sql = 'SELECT id, title, abbreviation FROM courses WHERE UPPER(abbreviation) = :abbr';
        $params = [':abbr' => $abbr];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function abbreviationConflictMessage(string $abbr, ?int $excludeId = null): ?string {
        $existing = $this->findByAbbreviation($abbr, $excludeId);
        if (!$existing) {
            return null;
        }
        return sprintf(
            'Abbreviation "%s" is already used by "%s" (course #%d). Pick another or edit that course.',
            strtoupper(trim($abbr)),
            $existing['title'],
            (int) $existing['id']
        );
    }

    private function mapDbError(\PDOException $e, string $abbr): ?string {
        $sqlState = (string) $e->getCode();
        if ($sqlState !== '23000') {
            return null;
        }

        $msg = $e->getMessage();
        if (stripos($msg, 'foreign key constraint') !== false || stripos($msg, '1452') !== false) {
            return 'Could not save the course because your login session does not match the database. Log out, log in again, then retry.';
        }
        if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'abbreviation') !== false) {
            return $this->abbreviationConflictMessage($abbr)
                ?? 'That abbreviation is already used by another course.';
        }
        return 'Could not save the course due to a database constraint. Check Admin → Courses for existing entries.';
    }
}