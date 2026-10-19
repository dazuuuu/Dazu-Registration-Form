<?php
// app/v1/models/TutorCourseModel.php
// A tutor's own materials per course (overview, video, resources, modules, questions) and student progress on them.
// Materials belong to (tutor, course): every student of that course assigned to the tutor sees the same set,
// so a newly assigned student picks up everything the tutor already built.
// $tutorUserId everywhere is the tutor's users.id (course_modules.tutor_id references users.id), not tutors.id.
namespace Models;

class TutorCourseModel {
    public const ANSWER_MODES = [
        'text'   => 'Written answer',
        'choice' => 'Multiple choice (auto-marked)',
        'file'   => 'Upload a file / zipped folder',
        'github' => 'GitHub repository link',
    ];

    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    /* ---------- Overview & resources ---------- */

    public function getOverview(int $tutorUserId, int $courseId): array {
        $st = $this->db->prepare("SELECT * FROM tutor_course_overviews WHERE tutor_id = ? AND course_id = ?");
        $st->execute([$tutorUserId, $courseId]);
        return $st->fetch() ?: ['overview' => '', 'instructions' => '', 'video_path' => null, 'video_url' => null];
    }

    public function saveOverview(int $tutorUserId, int $courseId, string $overview, string $instructions, ?string $videoUrl, ?string $videoPath, bool $removeVideo): void {
        $current = $this->getOverview($tutorUserId, $courseId);
        $path = $removeVideo ? null : ($videoPath ?? ($current['video_path'] ?? null));
        $this->db->prepare(
            "INSERT INTO tutor_course_overviews (tutor_id, course_id, overview, instructions, video_path, video_url)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE overview = VALUES(overview), instructions = VALUES(instructions),
                                     video_path = VALUES(video_path), video_url = VALUES(video_url)"
        )->execute([$tutorUserId, $courseId, $overview, $instructions, $path, $videoUrl ?: null]);
    }

    public function getResources(int $tutorUserId, int $courseId): array {
        $st = $this->db->prepare("SELECT * FROM tutor_course_resources WHERE tutor_id = ? AND course_id = ? ORDER BY created_at");
        $st->execute([$tutorUserId, $courseId]);
        return $st->fetchAll();
    }

    public function addResource(int $tutorUserId, int $courseId, string $title, ?string $filePath, ?string $linkUrl): void {
        $this->db->prepare("INSERT INTO tutor_course_resources (tutor_id, course_id, title, file_path, link_url) VALUES (?, ?, ?, ?, ?)")
                 ->execute([$tutorUserId, $courseId, $title, $filePath, $linkUrl ?: null]);
    }

    public function deleteResource(int $tutorUserId, int $resourceId): void {
        $this->db->prepare("DELETE FROM tutor_course_resources WHERE id = ? AND tutor_id = ?")->execute([$resourceId, $tutorUserId]);
    }

    /* ---------- Modules ---------- */

    public function getModules(int $tutorUserId, int $courseId): array {
        $st = $this->db->prepare("SELECT * FROM course_modules WHERE course_id = ? AND tutor_id = ? ORDER BY order_index, id");
        $st->execute([$courseId, $tutorUserId]);
        return $st->fetchAll();
    }

    public function getModule(int $moduleId, ?int $tutorUserId = null): ?array {
        $sql = "SELECT * FROM course_modules WHERE id = ?" . ($tutorUserId !== null ? " AND tutor_id = ?" : "");
        $st = $this->db->prepare($sql);
        $st->execute($tutorUserId !== null ? [$moduleId, $tutorUserId] : [$moduleId]);
        return $st->fetch() ?: null;
    }

    public function saveModule(int $tutorUserId, int $courseId, ?int $moduleId, array $d): int {
        $vals = [trim($d['title']), trim($d['description'] ?? ''), trim($d['instructions'] ?? ''), trim($d['video_url'] ?? '') ?: null, (int) ($d['order_index'] ?? 0)];
        if ($moduleId) {
            $this->db->prepare("UPDATE course_modules SET title = ?, description = ?, instructions = ?, video_url = ?, order_index = ? WHERE id = ? AND tutor_id = ?")
                     ->execute(array_merge($vals, [$moduleId, $tutorUserId]));
            return $moduleId;
        }
        if (empty($d['order_index'])) {
            $st = $this->db->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 FROM course_modules WHERE course_id = ? AND tutor_id = ?");
            $st->execute([$courseId, $tutorUserId]);
            $vals[4] = (int) $st->fetchColumn();
        }
        $this->db->prepare("INSERT INTO course_modules (title, description, instructions, video_url, order_index, course_id, tutor_id) VALUES (?, ?, ?, ?, ?, ?, ?)")
                 ->execute(array_merge($vals, [$courseId, $tutorUserId]));
        return (int) $this->db->lastInsertId();
    }

    public function deleteModule(int $tutorUserId, int $moduleId): void {
        if (!$this->getModule($moduleId, $tutorUserId)) return;
        $this->db->prepare("DELETE FROM module_contents WHERE module_id = ?")->execute([$moduleId]);
        $this->db->prepare("DELETE FROM student_module_progress WHERE module_id = ?")->execute([$moduleId]);
        $this->db->prepare("DELETE FROM course_modules WHERE id = ?")->execute([$moduleId]);
    }

    /* ---------- Module items (materials and questions) ---------- */

    public function getItems(int $moduleId): array {
        $st = $this->db->prepare("SELECT * FROM module_contents WHERE module_id = ? ORDER BY order_index, id");
        $st->execute([$moduleId]);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['options'] = $r['options_json'] ? (json_decode($r['options_json'], true) ?: []) : [];
        }
        return $rows;
    }

    public function getItem(int $itemId): ?array {
        $st = $this->db->prepare("SELECT * FROM module_contents WHERE id = ?");
        $st->execute([$itemId]);
        $r = $st->fetch();
        if (!$r) return null;
        $r['options'] = $r['options_json'] ? (json_decode($r['options_json'], true) ?: []) : [];
        return $r;
    }

    /** @return array{ok:bool, error?:string} */
    public function saveItem(int $moduleId, ?int $itemId, array $d, ?string $filePath, bool $removeFile): array {
        $type = $d['type'] === 'material' ? 'material' : 'question';
        $mode = $type === 'question' && isset(self::ANSWER_MODES[$d['answer_mode'] ?? '']) ? $d['answer_mode'] : 'text';
        $title = trim($d['title'] ?? '');
        if ($title === '') return ['ok' => false, 'error' => 'Give the item a title.'];

        $options = null;
        $correct = null;
        if ($type === 'question' && $mode === 'choice') {
            $opts = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) ($d['options'] ?? ''))), 'strlen'));
            if (count($opts) < 2) return ['ok' => false, 'error' => 'A choice question needs at least two options (one per line).'];
            $correct = (int) ($d['correct_option'] ?? 0) - 1;
            if ($correct < 0 || $correct >= count($opts)) return ['ok' => false, 'error' => 'Pick which option number is correct (1 to ' . count($opts) . ').'];
            $options = json_encode($opts, JSON_UNESCAPED_UNICODE);
        }
        $maxScore = $type === 'question' ? max(1, (int) ($d['max_score'] ?? 0) ?: 10) : null;
        $due = $type === 'question' && !empty($d['due_date']) ? $d['due_date'] : null;
        $link = trim($d['link_url'] ?? '') ?: null;

        if ($itemId) {
            $cur = $this->getItem($itemId);
            if (!$cur || (int) $cur['module_id'] !== $moduleId) return ['ok' => false, 'error' => 'Item not found.'];
            $file = $removeFile ? null : ($filePath ?? $cur['file_path']);
            $this->db->prepare(
                "UPDATE module_contents SET type = ?, answer_mode = ?, title = ?, content = ?, options_json = ?, correct_option = ?,
                        file_path = ?, link_url = ?, max_score = ?, due_date = ?, order_index = ? WHERE id = ?"
            )->execute([$type, $mode, $title, trim($d['content'] ?? ''), $options, $correct, $file, $link, $maxScore, $due, (int) ($d['order_index'] ?? 0), $itemId]);
        } else {
            $st = $this->db->prepare("SELECT COALESCE(MAX(order_index), 0) + 1 FROM module_contents WHERE module_id = ?");
            $st->execute([$moduleId]);
            $this->db->prepare(
                "INSERT INTO module_contents (module_id, type, answer_mode, title, content, options_json, correct_option, file_path, link_url, max_score, due_date, order_index)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([$moduleId, $type, $mode, $title, trim($d['content'] ?? ''), $options, $correct, $filePath, $link, $maxScore, $due, (int) $st->fetchColumn()]);
        }
        return ['ok' => true];
    }

    public function deleteItem(int $moduleId, int $itemId): void {
        $this->db->prepare("DELETE FROM module_contents WHERE id = ? AND module_id = ?")->execute([$itemId, $moduleId]);
    }

    public static function isGradable(array $item): bool {
        return in_array($item['type'], ['question', 'assignment'], true);
    }

    /* ---------- Which tutor teaches a student ---------- */

    /** The tutor whose materials this enrolment follows: assigned to the student, preferring one who built this course. */
    public function tutorForRegistration(array $reg): ?array {
        if (empty($reg['user_id'])) return null;
        $st = $this->db->prepare(
            "SELECT t.*, up.first_name, up.last_name
             FROM tutor_students ts
             JOIN tutors t ON t.id = ts.tutor_id AND t.is_active = 1
             JOIN users u ON u.id = t.user_id
             LEFT JOIN user_profiles up ON up.user_id = u.id
             WHERE ts.user_id = ?
             ORDER BY EXISTS(SELECT 1 FROM course_modules m WHERE m.tutor_id = t.user_id AND m.course_id = ?) DESC, ts.assigned_at DESC
             LIMIT 1"
        );
        $st->execute([(int) $reg['user_id'], (int) $reg['course_id']]);
        return $st->fetch() ?: null;
    }

    /* ---------- Student submissions ---------- */

    /** Latest submission per module item for this enrolment, keyed by module_content_id. */
    public function submissionsFor(int $registrationId, array $itemIds): array {
        if (!$itemIds) return [];
        $in = implode(',', array_map('intval', $itemIds));
        $st = $this->db->prepare(
            "SELECT * FROM student_submissions WHERE registration_id = ? AND submission_type = 'module' AND module_content_id IN ($in)
             ORDER BY submitted_at, id"
        );
        $st->execute([$registrationId]);
        $map = [];
        foreach ($st->fetchAll() as $s) $map[(int) $s['module_content_id']] = $s;
        return $map;
    }

    /**
     * Save a student's answer. Choice questions are marked straight away.
     * @return array{ok:bool, error?:string, auto_graded?:bool, correct?:bool}
     */
    public function submit(int $registrationId, array $item, ?string $answer, ?string $filePath, ?string $githubUrl): array {
        if (!self::isGradable($item)) return ['ok' => false, 'error' => 'This item does not take answers.'];
        $mode = $item['answer_mode'] ?: 'text';
        $answer = trim((string) $answer);

        if ($mode === 'choice') {
            if ($answer === '' || !ctype_digit($answer) || !isset($item['options'][(int) $answer])) return ['ok' => false, 'error' => 'Choose one of the options.'];
        } elseif ($mode === 'file') {
            if (!$filePath) return ['ok' => false, 'error' => 'Attach your file.'];
        } elseif ($mode === 'github') {
            require_once __DIR__ . '/../helpers/open_submissions.php';
            $chk = os_check_link((string) $githubUrl);
            if (!$chk['ok']) return ['ok' => false, 'error' => $chk['error']];
            if ($chk['kind'] !== 'github' || ($chk['meta']['type'] ?? 'repo') === 'profile') {
                return ['ok' => false, 'error' => 'Enter a GitHub repository link like https://github.com/username/project.'];
            }
            $githubUrl = $chk['url'];
        } elseif ($answer === '') {
            return ['ok' => false, 'error' => 'Write your answer first.'];
        }

        $existing = $this->submissionsFor($registrationId, [(int) $item['id']])[(int) $item['id']] ?? null;
        if ($existing && $existing['status'] === 'graded') {
            return ['ok' => false, 'error' => 'This has already been marked. Ask your tutor if you need to resubmit.'];
        }

        $status = 'submitted';
        $marks = null;
        $correct = null;
        if ($mode === 'choice') {
            $correct = (int) $answer === (int) $item['correct_option'];
            $status = 'graded';
            $marks = $correct ? (float) ($item['max_score'] ?: 10) : 0.0;
        }

        $vals = [$answer !== '' ? $answer : null, $filePath, $githubUrl ?: null, $status, $marks, $status === 'graded' ? 'Marked automatically.' : null];
        if ($existing) {
            $this->db->prepare(
                "UPDATE student_submissions SET answer_text = ?, file_path = ?, github_url = ?, status = ?, marks = ?, feedback = ?,
                        graded_by = NULL, graded_at = " . ($status === 'graded' ? 'NOW()' : 'NULL') . ", submitted_at = NOW() WHERE id = ?"
            )->execute(array_merge($vals, [(int) $existing['id']]));
        } else {
            $this->db->prepare(
                "INSERT INTO student_submissions (answer_text, file_path, github_url, status, marks, feedback, graded_at, registration_id, submission_type, module_content_id)
                 VALUES (?, ?, ?, ?, ?, ?, " . ($status === 'graded' ? 'NOW()' : 'NULL') . ", ?, 'module', ?)"
            )->execute(array_merge($vals, [$registrationId, (int) $item['id']]));
        }

        $this->refreshModuleCompletion($registrationId, (int) $item['module_id']);
        return ['ok' => true, 'auto_graded' => $status === 'graded', 'correct' => $correct];
    }

    /* ---------- Progress & completion ---------- */

    /**
     * A module is complete when every question in it has been marked. A module with no questions is completed
     * by the student pressing "Mark as done" (markModuleDone).
     */
    public function refreshModuleCompletion(int $registrationId, int $moduleId): bool {
        $items = array_filter($this->getItems($moduleId), [self::class, 'isGradable']);
        if (!$items) return $this->isModuleDone($registrationId, $moduleId);
        $subs = $this->submissionsFor($registrationId, array_map(fn($i) => (int) $i['id'], $items));
        $done = true;
        foreach ($items as $i) {
            if (($subs[(int) $i['id']]['status'] ?? '') !== 'graded') { $done = false; break; }
        }
        if ($done) {
            $this->db->prepare("INSERT IGNORE INTO student_module_progress (registration_id, module_id) VALUES (?, ?)")->execute([$registrationId, $moduleId]);
        } else {
            $this->db->prepare("DELETE FROM student_module_progress WHERE registration_id = ? AND module_id = ?")->execute([$registrationId, $moduleId]);
        }
        $this->refreshStudiesCompletion($registrationId);
        return $done;
    }

    public function markModuleDone(int $registrationId, int $moduleId): void {
        $this->db->prepare("INSERT IGNORE INTO student_module_progress (registration_id, module_id) VALUES (?, ?)")->execute([$registrationId, $moduleId]);
        $this->refreshStudiesCompletion($registrationId);
    }

    public function isModuleDone(int $registrationId, int $moduleId): bool {
        $st = $this->db->prepare("SELECT 1 FROM student_module_progress WHERE registration_id = ? AND module_id = ?");
        $st->execute([$registrationId, $moduleId]);
        return (bool) $st->fetchColumn();
    }

    /**
     * Progress of one enrolment through its tutor's modules.
     * @return array{tutor:?array, modules:array, done:int, total:int, percent:int, score:float, max_score:float, grade_percent:?int}
     */
    public function progressFor(array $reg): array {
        $tutor = $this->tutorForRegistration($reg);
        $modules = $tutor ? $this->getModules((int) $tutor['user_id'], (int) $reg['course_id']) : [];
        $done = 0;
        $score = 0.0;
        $max = 0.0;
        if ($modules) {
            $ids = implode(',', array_map(fn($m) => (int) $m['id'], $modules));
            $st = $this->db->prepare("SELECT module_id FROM student_module_progress WHERE registration_id = ? AND module_id IN ($ids)");
            $st->execute([(int) $reg['id']]);
            $doneIds = array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
            $st = $this->db->prepare(
                "SELECT mc.max_score, s.marks FROM student_submissions s JOIN module_contents mc ON mc.id = s.module_content_id
                 WHERE s.registration_id = ? AND s.submission_type = 'module' AND s.status = 'graded' AND mc.module_id IN ($ids)"
            );
            $st->execute([(int) $reg['id']]);
            foreach ($st->fetchAll() as $g) {
                $score += (float) $g['marks'];
                $max += (float) ($g['max_score'] ?: 10);
            }
            foreach ($modules as &$m) {
                $m['is_done'] = in_array((int) $m['id'], $doneIds, true);
                if ($m['is_done']) $done++;
            }
            unset($m);
        }
        $total = count($modules);
        return [
            'tutor' => $tutor,
            'modules' => $modules,
            'done' => $done,
            'total' => $total,
            'percent' => $total ? (int) round($done / $total * 100) : 0,
            'score' => $score,
            'max_score' => $max,
            'grade_percent' => $max > 0 ? (int) round($score / $max * 100) : null,
        ];
    }

    /**
     * Ticks "studies completed" automatically once every module is done. An automatic tick is removed again if the
     * tutor later adds work (unless a certificate exists); a tutor's manual tick is never changed here.
     */
    public function refreshStudiesCompletion(int $registrationId): void {
        $st = $this->db->prepare("SELECT * FROM registrations WHERE id = ?");
        $st->execute([$registrationId]);
        $reg = $st->fetch();
        if (!$reg || !empty($reg['studies_completed_by'])) return;
        $p = $this->progressFor($reg);
        $allDone = $p['total'] > 0 && $p['done'] === $p['total'];
        if ($allDone && empty($reg['studies_completed_at'])) {
            $this->setStudiesCompleted($registrationId, true, null);
            require_once __DIR__ . '/../services/PortalNotifications.php';
            \PortalNotifications::notify($this->db, (int) $p['tutor']['user_id'], 'studies_completed', $reg['full_name'] . ' completed all modules',
                'Review and tick "Course finished" to issue the certificate.', 'tutor/students/view.php?id=' . $registrationId,
                \PortalNotifications::photoOf($this->db, (int) $reg['user_id']));
        } elseif (!$allDone && !empty($reg['studies_completed_at'])) {
            $st = $this->db->prepare("SELECT 1 FROM certificates WHERE registration_id = ? AND status <> 'withdrawn' LIMIT 1");
            $st->execute([$registrationId]);
            if (!$st->fetchColumn()) $this->setStudiesCompleted($registrationId, false, null);
        }
    }

    /** Recalculate module and studies completion for every student of this tutor in the course (after content changes). */
    public function refreshCourseForAll(int $tutorUserId, int $courseId): void {
        $st = $this->db->prepare(
            "SELECT DISTINCT r.id FROM registrations r
             JOIN tutor_students ts ON ts.user_id = r.user_id
             JOIN tutors t ON t.id = ts.tutor_id AND t.user_id = ?
             WHERE r.course_id = ? AND r.status = 'approved'"
        );
        $st->execute([$tutorUserId, $courseId]);
        $regIds = array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
        $modules = $this->getModules($tutorUserId, $courseId);
        foreach ($regIds as $rid) {
            foreach ($modules as $m) {
                if (array_filter($this->getItems((int) $m['id']), [self::class, 'isGradable'])) {
                    $this->refreshModuleCompletion($rid, (int) $m['id']);
                }
            }
            $this->refreshStudiesCompletion($rid);
        }
    }

    public function setStudiesCompleted(int $registrationId, bool $completed, ?int $byUserId): void {
        if ($completed) {
            $this->db->prepare("UPDATE registrations SET studies_completed_at = COALESCE(studies_completed_at, NOW()), studies_completed_by = COALESCE(studies_completed_by, ?) WHERE id = ?")
                     ->execute([$byUserId, $registrationId]);
        } else {
            $this->db->prepare("UPDATE registrations SET studies_completed_at = NULL, studies_completed_by = NULL WHERE id = ?")->execute([$registrationId]);
        }
    }

    /** Questions waiting for this tutor to mark (module items only), oldest first. */
    public function pendingForTutor(int $tutorUserId): array {
        $st = $this->db->prepare(
            "SELECT s.*, r.full_name, r.registration_no, c.title AS course_title, mc.title AS item_title, mc.answer_mode,
                    mc.max_score, mc.content AS question_text, m.title AS module_title
             FROM student_submissions s
             JOIN module_contents mc ON mc.id = s.module_content_id
             JOIN course_modules m ON m.id = mc.module_id AND m.tutor_id = ?
             JOIN registrations r ON r.id = s.registration_id
             JOIN courses c ON c.id = r.course_id
             WHERE s.submission_type = 'module' AND s.status = 'submitted'
             ORDER BY s.submitted_at"
        );
        $st->execute([$tutorUserId]);
        return $st->fetchAll();
    }

    /** Mark a module answer; returns the registration id or null when the tutor does not own the question. */
    public function gradeModuleSubmission(int $tutorUserId, int $submissionId, float $marks, string $feedback, int $gradedBy): ?array {
        $st = $this->db->prepare(
            "SELECT s.*, mc.module_id, mc.max_score, mc.title AS item_title FROM student_submissions s
             JOIN module_contents mc ON mc.id = s.module_content_id
             JOIN course_modules m ON m.id = mc.module_id AND m.tutor_id = ?
             WHERE s.id = ? AND s.submission_type = 'module'"
        );
        $st->execute([$tutorUserId, $submissionId]);
        $sub = $st->fetch();
        if (!$sub) return null;
        $marks = max(0, min($marks, (float) ($sub['max_score'] ?: 10)));
        $this->db->prepare("UPDATE student_submissions SET marks = ?, feedback = ?, status = 'graded', graded_by = ?, graded_at = NOW() WHERE id = ?")
                 ->execute([$marks, $feedback, $gradedBy, $submissionId]);
        $this->refreshModuleCompletion((int) $sub['registration_id'], (int) $sub['module_id']);
        $sub['marks'] = $marks;
        $sub['feedback'] = $feedback;
        return $sub;
    }

    /** Let the student answer again (keeps nothing of the old mark). */
    public function reopenSubmission(int $tutorUserId, int $submissionId): void {
        $st = $this->db->prepare(
            "SELECT s.registration_id, mc.module_id FROM student_submissions s
             JOIN module_contents mc ON mc.id = s.module_content_id
             JOIN course_modules m ON m.id = mc.module_id AND m.tutor_id = ?
             WHERE s.id = ? AND s.submission_type = 'module'"
        );
        $st->execute([$tutorUserId, $submissionId]);
        if ($row = $st->fetch()) {
            $this->db->prepare("DELETE FROM student_submissions WHERE id = ?")->execute([$submissionId]);
            $this->refreshModuleCompletion((int) $row['registration_id'], (int) $row['module_id']);
        }
    }
}
