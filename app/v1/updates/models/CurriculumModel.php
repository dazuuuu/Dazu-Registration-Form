<?php
// app/models/CurriculumModel.php
namespace Models;

class CurriculumModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    // ── Units ──────────────────────────────────────────────

    public function getUnitsForCourse(int $courseId, bool $activeOnly = true): array {
        $sql = "SELECT * FROM curriculum_units WHERE course_id = :cid";
        if ($activeOnly) $sql .= " AND is_active = 1";
        $sql .= " ORDER BY sort_order, id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':cid' => $courseId]);
        return $stmt->fetchAll();
    }

    public function getUnitById(int $id): ?array {
        $stmt = $this->db->prepare(
            "SELECT u.*, c.title AS course_title, c.id AS course_id
             FROM curriculum_units u JOIN courses c ON u.course_id = c.id WHERE u.id = :id"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function createUnit(int $courseId, string $title, string $description = ''): int {
        $order = $this->nextSortOrder('curriculum_units', 'course_id', $courseId);
        $stmt = $this->db->prepare(
            "INSERT INTO curriculum_units (course_id, title, description, sort_order) VALUES (:cid, :title, :desc, :ord)"
        );
        $stmt->execute([':cid' => $courseId, ':title' => $title, ':desc' => $description, ':ord' => $order]);
        return (int) $this->db->lastInsertId();
    }

    public function updateUnit(int $id, string $title, string $description): bool {
        $stmt = $this->db->prepare("UPDATE curriculum_units SET title = :t, description = :d WHERE id = :id");
        return $stmt->execute([':t' => $title, ':d' => $description, ':id' => $id]);
    }

    public function deleteUnit(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM curriculum_units WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ── Sub-units ──────────────────────────────────────────

    public function getSubUnits(int $unitId): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM curriculum_sub_units WHERE unit_id = :uid ORDER BY sort_order, id"
        );
        $stmt->execute([':uid' => $unitId]);
        return $stmt->fetchAll();
    }

    public function createSubUnit(int $unitId, string $title, string $description = ''): int {
        $order = $this->nextSortOrder('curriculum_sub_units', 'unit_id', $unitId);
        $stmt = $this->db->prepare(
            "INSERT INTO curriculum_sub_units (unit_id, title, description, sort_order) VALUES (:uid, :t, :d, :ord)"
        );
        $stmt->execute([':uid' => $unitId, ':t' => $title, ':d' => $description, ':ord' => $order]);
        return (int) $this->db->lastInsertId();
    }

    public function updateSubUnit(int $id, string $title, string $description): bool {
        $stmt = $this->db->prepare("UPDATE curriculum_sub_units SET title = :t, description = :d WHERE id = :id");
        return $stmt->execute([':t' => $title, ':d' => $description, ':id' => $id]);
    }

    public function deleteSubUnit(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM curriculum_sub_units WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ── Topics ─────────────────────────────────────────────

    public function getTopics(int $subUnitId): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM curriculum_topics WHERE sub_unit_id = :sid ORDER BY sort_order, id"
        );
        $stmt->execute([':sid' => $subUnitId]);
        return $stmt->fetchAll();
    }

    public function createTopic(int $subUnitId, string $title, string $content = ''): int {
        $order = $this->nextSortOrder('curriculum_topics', 'sub_unit_id', $subUnitId);
        $stmt = $this->db->prepare(
            "INSERT INTO curriculum_topics (sub_unit_id, title, content, sort_order) VALUES (:sid, :t, :c, :ord)"
        );
        $stmt->execute([':sid' => $subUnitId, ':t' => $title, ':c' => $content, ':ord' => $order]);
        return (int) $this->db->lastInsertId();
    }

    public function updateTopic(int $id, string $title, string $content): bool {
        $stmt = $this->db->prepare("UPDATE curriculum_topics SET title = :t, content = :c WHERE id = :id");
        return $stmt->execute([':t' => $title, ':c' => $content, ':id' => $id]);
    }

    public function deleteTopic(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM curriculum_topics WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ── Tests ──────────────────────────────────────────────

    public function getTests(int $unitId): array {
        $stmt = $this->db->prepare("SELECT * FROM unit_tests WHERE unit_id = :uid ORDER BY sort_order, id");
        $stmt->execute([':uid' => $unitId]);
        return $stmt->fetchAll();
    }

    public function getTestById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM unit_tests WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function createTest(int $unitId, array $data): int {
        $order = $this->nextSortOrder('unit_tests', 'unit_id', $unitId);
        $stmt = $this->db->prepare(
            "INSERT INTO unit_tests (unit_id, title, description, instructions, max_marks, is_required, sort_order)
             VALUES (:uid, :title, :desc, :instr, :marks, :req, :ord)"
        );
        $stmt->execute([
            ':uid'   => $unitId,
            ':title' => $data['title'],
            ':desc'  => $data['description'] ?? '',
            ':instr' => $data['instructions'] ?? '',
            ':marks' => $data['max_marks'] ?? 100,
            ':req'   => !empty($data['is_required']) ? 1 : 0,
            ':ord'   => $order,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function deleteTest(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM unit_tests WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // ── Projects ───────────────────────────────────────────

    public function getProjects(int $unitId): array {
        $stmt = $this->db->prepare("SELECT * FROM unit_projects WHERE unit_id = :uid ORDER BY sort_order, id");
        $stmt->execute([':uid' => $unitId]);
        return $stmt->fetchAll();
    }

    public function getProjectById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM unit_projects WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function createProject(int $unitId, array $data): int {
        $order = $this->nextSortOrder('unit_projects', 'unit_id', $unitId);
        $stmt = $this->db->prepare(
            "INSERT INTO unit_projects (unit_id, title, description, instructions, max_marks, is_required, sort_order)
             VALUES (:uid, :title, :desc, :instr, :marks, :req, :ord)"
        );
        $stmt->execute([
            ':uid'   => $unitId,
            ':title' => $data['title'],
            ':desc'  => $data['description'] ?? '',
            ':instr' => $data['instructions'] ?? '',
            ':marks' => $data['max_marks'] ?? 100,
            ':req'   => !empty($data['is_required']) ? 1 : 0,
            ':ord'   => $order,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function deleteProject(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM unit_projects WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /** Full curriculum tree for a course */
    public function getCourseTree(int $courseId): array {
        $units = $this->getUnitsForCourse($courseId);
        foreach ($units as &$unit) {
            $unit['sub_units'] = $this->getSubUnits((int) $unit['id']);
            foreach ($unit['sub_units'] as &$su) {
                $su['topics'] = $this->getTopics((int) $su['id']);
            }
            $unit['tests'] = $this->getTests((int) $unit['id']);
            $unit['projects'] = $this->getProjects((int) $unit['id']);
        }
        return $units;
    }

    private function nextSortOrder(string $table, string $parentCol, int $parentId): int {
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM `$table` WHERE `$parentCol` = :pid");
        $stmt->execute([':pid' => $parentId]);
        return (int) $stmt->fetchColumn();
    }
}
