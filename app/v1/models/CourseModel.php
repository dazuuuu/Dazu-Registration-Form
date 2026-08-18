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
        $dur = $this->durationFields($data);
        $isFree = !empty($data['is_free']);
        $fee = $isFree ? 0.0 : (float) $data['course_fee'];
        $dates = $this->dateFields($data);

        try {
            $sql = "INSERT INTO courses (title, abbreviation, category_id, cover_image, description, requirements, overview, duration_months, weeks_of_study, days_of_study, hours_of_study, study_schedule, course_fee, is_free, start_date, end_date, is_active, created_by)
                    VALUES (:title, :abbreviation, :category_id, :cover_image, :description, :requirements, :overview, :duration_months, :weeks_of_study, :days_of_study, :hours_of_study, :study_schedule, :course_fee, :is_free, :start_date, :end_date, 1, :created_by)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':title'            => trim($data['title']),
                ':abbreviation'     => $abbr,
                ':category_id'      => $categoryId,
                ':cover_image'      => $data['cover_image'] ?? null,
                ':description'      => $data['description'] ?? '',
                ':requirements'     => trim($data['requirements'] ?? ''),
                ':overview'         => $data['overview'] ?? '',
                ':duration_months'  => $dur['duration_months'],
                ':weeks_of_study'   => $dur['weeks_of_study'],
                ':days_of_study'    => $dur['days_of_study'],
                ':hours_of_study'   => $dur['hours_of_study'],
                ':study_schedule'   => $dur['study_schedule'],
                ':course_fee'       => $fee,
                ':is_free'          => $isFree ? 1 : 0,
                ':start_date'       => $dates['start_date'],
                ':end_date'         => $dates['end_date'],
                ':created_by'       => $this->resolveCreatedBy($createdBy),
            ]);
        } catch (\PDOException $e) {
            $missingDateCols = stripos($e->getMessage(), 'start_date') !== false
                || stripos($e->getMessage(), 'end_date') !== false;
            if ($missingDateCols) {
                return ['ok' => false, 'error' => 'Database not migrated yet — run databases/migrations/035_add_course_dates_and_free_toggle.sql, then retry.'];
            }
            $missingNewCols = stripos($e->getMessage(), 'requirements') !== false
                || stripos($e->getMessage(), 'is_free') !== false;
            if ($missingNewCols) {
                return ['ok' => false, 'error' => 'Database not migrated yet — run databases/migrations/033_course_requirements_and_free_flag.sql, then retry.'];
            }
            $missingStudyCols = stripos($e->getMessage(), 'weeks_of_study') !== false
                || stripos($e->getMessage(), 'days_of_study') !== false
                || stripos($e->getMessage(), 'hours_of_study') !== false
                || stripos($e->getMessage(), 'study_schedule') !== false;
            if ($categoryId !== null && stripos($e->getMessage(), 'category_id') !== false) {
                $sql = "INSERT INTO courses (title, abbreviation, cover_image, description, overview, duration_months, weeks_of_study, days_of_study, hours_of_study, study_schedule, course_fee, is_active, created_by)
                        VALUES (:title, :abbreviation, :cover_image, :description, :overview, :duration_months, :weeks_of_study, :days_of_study, :hours_of_study, :study_schedule, :course_fee, 1, :created_by)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':title'            => trim($data['title']),
                    ':abbreviation'     => $abbr,
                    ':cover_image'      => $data['cover_image'] ?? null,
                    ':description'      => $data['description'] ?? '',
                    ':overview'         => $data['overview'] ?? '',
                    ':duration_months'  => $dur['duration_months'],
                    ':weeks_of_study'   => $dur['weeks_of_study'],
                    ':days_of_study'    => $dur['days_of_study'],
                    ':hours_of_study'   => $dur['hours_of_study'],
                    ':study_schedule'   => $dur['study_schedule'],
                    ':course_fee'       => $fee,
                    ':created_by'       => $this->resolveCreatedBy($createdBy),
                ]);
            } elseif ($missingStudyCols) {
                // DB not yet migrated (missing weeks_of_study/hours_of_study/study_schedule) — save without them
                // rather than fatal, run databases/migrations/022_add_course_study_duration.sql and
                // databases/migrations/024_add_course_study_schedule.sql to fix properly.
                $sql = "INSERT INTO courses (title, abbreviation, category_id, cover_image, description, overview, duration_months, course_fee, is_active, created_by)
                        VALUES (:title, :abbreviation, :category_id, :cover_image, :description, :overview, :duration_months, :course_fee, 1, :created_by)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':title'            => trim($data['title']),
                    ':abbreviation'     => $abbr,
                    ':category_id'      => $categoryId,
                    ':cover_image'      => $data['cover_image'] ?? null,
                    ':description'      => $data['description'] ?? '',
                    ':overview'         => $data['overview'] ?? '',
                    ':duration_months'  => max(1, $dur['duration_months'] ?: course_effective_months($dur)),
                    ':course_fee'       => $fee,
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

        $isFree = !empty($data['is_free']);
        $fee = $isFree ? 0.0 : (float) $data['course_fee'];
        $dates = $this->dateFields($data);

        $fields = "title = :title, abbreviation = :abbreviation, category_id = :category_id, description = :description,
                   requirements = :requirements, overview = :overview, duration_months = :duration_months, weeks_of_study = :weeks_of_study,
                   days_of_study = :days_of_study, hours_of_study = :hours_of_study, study_schedule = :study_schedule, course_fee = :course_fee,
                   is_free = :is_free, start_date = :start_date, end_date = :end_date";
        $dur = $this->durationFields($data);
        $params = [
            ':title'           => trim($data['title']),
            ':abbreviation'    => $abbr,
            ':category_id'     => $categoryId,
            ':description'     => $data['description'] ?? '',
            ':requirements'    => trim($data['requirements'] ?? ''),
            ':overview'        => $data['overview'] ?? '',
            ':duration_months' => $dur['duration_months'],
            ':weeks_of_study'  => $dur['weeks_of_study'],
            ':days_of_study'   => $dur['days_of_study'],
            ':hours_of_study'  => $dur['hours_of_study'],
            ':study_schedule'  => $dur['study_schedule'],
            ':course_fee'      => $fee,
            ':is_free'         => $isFree ? 1 : 0,
            ':start_date'      => $dates['start_date'],
            ':end_date'        => $dates['end_date'],
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
            $missingDateCols = stripos($e->getMessage(), 'start_date') !== false
                || stripos($e->getMessage(), 'end_date') !== false;
            if ($missingDateCols) {
                return ['ok' => false, 'error' => 'Database not migrated yet — run databases/migrations/035_add_course_dates_and_free_toggle.sql, then retry.'];
            }
            $missingNewCols = stripos($e->getMessage(), 'requirements') !== false
                || stripos($e->getMessage(), 'is_free') !== false;
            if ($missingNewCols) {
                return ['ok' => false, 'error' => 'Database not migrated yet — run databases/migrations/033_course_requirements_and_free_flag.sql, then retry.'];
            }
            $missingStudyCols = stripos($e->getMessage(), 'weeks_of_study') !== false
                || stripos($e->getMessage(), 'days_of_study') !== false
                || stripos($e->getMessage(), 'hours_of_study') !== false
                || stripos($e->getMessage(), 'study_schedule') !== false;
            if ($categoryId !== null && stripos($e->getMessage(), 'category_id') !== false) {
                $fields = str_replace('category_id = :category_id, ', '', $fields);
                unset($params[':category_id']);
                $sql = "UPDATE courses SET $fields WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            } elseif ($missingStudyCols) {
                // DB not yet migrated (missing weeks_of_study/hours_of_study/study_schedule) — save without them
                // rather than fatal, run databases/migrations/022_add_course_study_duration.sql and
                // databases/migrations/024_add_course_study_schedule.sql to fix properly.
                $fields = "title = :title, abbreviation = :abbreviation, category_id = :category_id, description = :description,
                           requirements = :requirements, overview = :overview, duration_months = :duration_months, course_fee = :course_fee,
                           is_free = :is_free";
                unset($params[':weeks_of_study'], $params[':days_of_study'], $params[':hours_of_study'], $params[':study_schedule']);
                $params[':duration_months'] = max(1, (int) $params[':duration_months'] ?: course_effective_months(array_merge($data, $dur)));
                if (!empty($data['cover_image'])) {
                    $fields .= ", cover_image = :cover_image";
                }
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

    /**
     * Free-course listing for public/courses/free.php. Degrades to an empty list
     * (not an error) if is_free hasn't been migrated yet — this is just a listing,
     * no data loss risk in silently showing nothing.
     *
     * A course whose end_date (migration 035_add_course_dates_and_free_toggle.sql)
     * has passed is excluded here — that's the "auto-removal", no cleanup job needed.
     */
    public function getFree(bool $activeOnly = true): array {
        try {
            $sql = "SELECT c.*, cc.name AS category_name, cc.slug AS category_slug, cc.icon_path AS category_icon
                    FROM courses c
                    LEFT JOIN course_categories cc ON cc.id = c.category_id
                    WHERE c.is_free = 1
                    AND (c.end_date IS NULL OR c.end_date >= CURDATE())";
            if ($activeOnly) {
                $sql .= " AND c.is_active = 1";
            }
            $sql .= " ORDER BY c.title ASC";
            return $this->db->query($sql)->fetchAll();
        } catch (\PDOException $e) {
            // Missing end_date column (not yet migrated) or is_free itself missing —
            // fall back to the unfiltered free listing rather than erroring the page.
            try {
                $sql = "SELECT c.*, cc.name AS category_name, cc.slug AS category_slug, cc.icon_path AS category_icon
                        FROM courses c
                        LEFT JOIN course_categories cc ON cc.id = c.category_id
                        WHERE c.is_free = 1";
                if ($activeOnly) {
                    $sql .= " AND c.is_active = 1";
                }
                $sql .= " ORDER BY c.title ASC";
                return $this->db->query($sql)->fetchAll();
            } catch (\PDOException $e2) {
                return [];
            }
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
     * Admin course browser — search text, category, active status, and free/paid.
     *
     * @param bool|null $isFree null = don't filter (paid+free), true = free only, false = paid only.
     *        admin/courses/index.php and admin/free-courses/index.php are kept as fully separate
     *        listings/forms (not one page with a toggle), so this is how each scopes itself.
     */
    public function listFiltered(?string $search = null, ?int $categoryId = null, ?string $status = 'all', ?bool $isFree = null): array
    {
        $sql = "SELECT c.*, cc.name AS category_name, cc.slug AS category_slug, cc.icon_path AS category_icon
                FROM courses c
                LEFT JOIN course_categories cc ON cc.id = c.category_id
                WHERE 1=1";
        $params = [];

        if ($status === 'active') {
            $sql .= ' AND c.is_active = 1';
        } elseif ($status === 'inactive') {
            $sql .= ' AND c.is_active = 0';
        }

        if ($isFree !== null) {
            $sql .= ' AND c.is_free = :is_free';
            $params[':is_free'] = $isFree ? 1 : 0;
        }

        if ($categoryId === 0) {
            $sql .= ' AND c.category_id IS NULL';
        } elseif ($categoryId !== null && $categoryId > 0) {
            $sql .= ' AND c.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        if ($search !== null && $search !== '') {
            $sql .= ' AND (c.title LIKE :q OR c.abbreviation LIKE :q OR c.description LIKE :q
                      OR c.overview LIKE :q OR cc.name LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY c.title ASC';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $sql = 'SELECT * FROM courses WHERE 1=1';
            $fallbackParams = [];
            if ($status === 'active') {
                $sql .= ' AND is_active = 1';
            } elseif ($status === 'inactive') {
                $sql .= ' AND is_active = 0';
            }
            if ($isFree !== null) {
                $sql .= ' AND is_free = :is_free';
                $fallbackParams[':is_free'] = $isFree ? 1 : 0;
            }
            if ($search !== null && $search !== '') {
                $sql .= ' AND (title LIKE :q OR abbreviation LIKE :q OR description LIKE :q OR overview LIKE :q)';
                $fallbackParams[':q'] = '%' . $search . '%';
            }
            $sql .= ' ORDER BY title ASC';
            try {
                $stmt = $this->db->prepare($sql);
                $stmt->execute($fallbackParams);
                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\PDOException $e2) {
                return $this->getAll($status === 'active');
            }
        }
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
        require_once __DIR__ . '/../helpers/course_duration.php';
        if (empty(trim($data['title'] ?? ''))) return 'Course title is required.';
        if (empty(trim($data['abbreviation'] ?? ''))) return 'Course abbreviation is required.';
        if (!preg_match('/^[A-Za-z0-9]{2,10}$/', trim($data['abbreviation']))) {
            return 'Abbreviation must be 2-10 letters or numbers, no spaces.';
        }
        if ($err = course_duration_validate_message($data)) {
            return $err;
        }

        if (empty($data['is_free'])) {
            $fee = (float) ($data['course_fee'] ?? 0);
            if ($fee <= 0) {
                return 'Course fee must be greater than zero.';
            }
        }

        $dates = $this->dateFields($data);
        if ($dates['start_date'] && $dates['end_date'] && $dates['end_date'] < $dates['start_date']) {
            return 'End date must be on or after the start date.';
        }
        return null;
    }

    private function durationFields(array $data): array
    {
        require_once __DIR__ . '/../helpers/course_duration.php';
        return course_normalize_duration_input($data);
    }

    /** Blank strings from an untouched date input become NULL, not '' (invalid DATE). */
    private function dateFields(array $data): array
    {
        $start = trim((string) ($data['start_date'] ?? ''));
        $end = trim((string) ($data['end_date'] ?? ''));
        return [
            'start_date' => $start !== '' ? $start : null,
            'end_date'   => $end !== '' ? $end : null,
        ];
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