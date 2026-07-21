<?php
// app/models/ProgressModel.php
namespace Models;

class ProgressModel {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    /** Initialize progress rows when a student is approved — unlock through learning month. */
    public function initializeForRegistration(int $registrationId, int $courseId, int $learningMonth = 1): void {
        $units = $this->db->prepare(
            "SELECT id FROM curriculum_units WHERE course_id = :cid AND is_active = 1 ORDER BY sort_order, id"
        );
        $units->execute([':cid' => $courseId]);
        $unitIds = $units->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($unitIds)) {
            return;
        }

        $unlockThrough = max(1, min(count($unitIds), $learningMonth));

        $ins = $this->db->prepare(
            "INSERT IGNORE INTO student_unit_progress (registration_id, unit_id, is_unlocked)
             VALUES (:rid, :uid, :unlocked)"
        );
        foreach ($unitIds as $i => $unitId) {
            $ins->execute([
                ':rid'      => $registrationId,
                ':uid'      => $unitId,
                ':unlocked' => ($i < $unlockThrough) ? 1 : 0,
            ]);
        }
    }

    /** Unlock all curriculum units up to the given learning month (1-based, maps to unit sort order). */
    public function syncUnlockedThroughMonth(int $registrationId, int $courseId, int $learningMonth): void {
        $units = $this->db->prepare(
            "SELECT id FROM curriculum_units WHERE course_id = :cid AND is_active = 1 ORDER BY sort_order, id"
        );
        $units->execute([':cid' => $courseId]);
        $unitIds = $units->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($unitIds)) {
            return;
        }

        $unlockThrough = max(1, min(count($unitIds), $learningMonth));
        $upsert = $this->db->prepare(
            "INSERT INTO student_unit_progress (registration_id, unit_id, is_unlocked)
             VALUES (:rid, :uid, :unlocked)
             ON DUPLICATE KEY UPDATE is_unlocked = VALUES(is_unlocked)"
        );
        foreach ($unitIds as $i => $unitId) {
            $upsert->execute([
                ':rid'      => $registrationId,
                ':uid'      => $unitId,
                ':unlocked' => ($i < $unlockThrough) ? 1 : 0,
            ]);
        }
    }

    public function getProgress(int $registrationId, int $courseId): array {
        $stmt = $this->db->prepare(
            "SELECT sup.*, cu.title, cu.sort_order
             FROM student_unit_progress sup
             JOIN curriculum_units cu ON cu.id = sup.unit_id
             WHERE sup.registration_id = :rid AND cu.course_id = :cid
             ORDER BY cu.sort_order, cu.id"
        );
        $stmt->execute([':rid' => $registrationId, ':cid' => $courseId]);
        return $stmt->fetchAll();
    }

    public function getUnitProgress(int $registrationId, int $unitId): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM student_unit_progress WHERE registration_id = :rid AND unit_id = :uid"
        );
        $stmt->execute([':rid' => $registrationId, ':uid' => $unitId]);
        return $stmt->fetch() ?: null;
    }

    public function isUnitUnlocked(int $registrationId, int $unitId): bool {
        $p = $this->getUnitProgress($registrationId, $unitId);
        return $p && (bool) $p['is_unlocked'];
    }

    /**
     * After tutor grades a submission, re-evaluate unit completion and unlock next.
     */
    public function evaluateUnitCompletion(int $registrationId, int $unitId, CurriculumModel $curriculum): void {
        $tests = $curriculum->getTests($unitId);
        $projects = $curriculum->getProjects($unitId);

        $requiredTests = array_filter($tests, fn($t) => $t['is_required']);
        $requiredProjects = array_filter($projects, fn($p) => $p['is_required']);

        $submissionModel = new SubmissionModel($this->db);

        $testDone = empty($requiredTests);
        foreach ($requiredTests as $t) {
            $sub = $submissionModel->getGradedSubmission($registrationId, 'test', (int) $t['id']);
            if (!$sub) { $testDone = false; break; }
        }

        $projectDone = empty($requiredProjects);
        foreach ($requiredProjects as $p) {
            $sub = $submissionModel->getGradedSubmission($registrationId, 'project', (int) $p['id']);
            if (!$sub) { $projectDone = false; break; }
        }

        $stmt = $this->db->prepare(
            "UPDATE student_unit_progress
             SET test_completed = :tc, project_completed = :pc,
                 completed_at = CASE WHEN :tc = 1 AND :pc = 1 THEN NOW() ELSE NULL END
             WHERE registration_id = :rid AND unit_id = :uid"
        );
        $stmt->execute([
            ':tc'  => $testDone ? 1 : 0,
            ':pc'  => $projectDone ? 1 : 0,
            ':rid' => $registrationId,
            ':uid' => $unitId,
        ]);

        if ($testDone && $projectDone) {
            $this->unlockNextUnit($registrationId, $unitId);
        }
    }

    private function unlockNextUnit(int $registrationId, int $completedUnitId): void {
        $stmt = $this->db->prepare(
            "SELECT cu.course_id, cu.sort_order FROM curriculum_units cu WHERE cu.id = :uid"
        );
        $stmt->execute([':uid' => $completedUnitId]);
        $current = $stmt->fetch();
        if (!$current) return;

        $next = $this->db->prepare(
            "SELECT cu.id FROM curriculum_units cu
             WHERE cu.course_id = :cid AND cu.is_active = 1
               AND (cu.sort_order > :ord OR (cu.sort_order = :ord AND cu.id > :uid))
             ORDER BY cu.sort_order, cu.id LIMIT 1"
        );
        $next->execute([':cid' => $current['course_id'], ':ord' => $current['sort_order'], ':uid' => $completedUnitId]);
        $nextId = $next->fetchColumn();
        if (!$nextId) return;

        $upd = $this->db->prepare(
            "INSERT INTO student_unit_progress (registration_id, unit_id, is_unlocked)
             VALUES (:rid, :uid, 1)
             ON DUPLICATE KEY UPDATE is_unlocked = 1"
        );
        $upd->execute([':rid' => $registrationId, ':uid' => $nextId]);
    }
}
