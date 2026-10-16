<?php
/**
 * Tutor-assisted teaching (private).
 *
 * A tutor asks an active intern to help teach some of the tutor's own students, with topics and dates. Only the tutor
 * and that intern can see it:
 *  - Only tutor/helpers/* and the intern's own pages load this file. Admin and student code never queries these tables,
 *    and nothing here notifies or emails a student or an admin.
 *  - The intern sees the topics and only the FIRST NAMES of the students — never contact details, fees, grades,
 *    certificates or any other student data. The intern never grades or changes student records.
 *  - Every action is written to intern_teaching_audit, which no screen shows.
 *  - When an internship ends or the account is turned off, the intern's assignments close (it_close_for_intern / it_sync).
 */
require_once __DIR__ . '/PortalNotifications.php';

if (!function_exists('it_log')) {
    function it_log(PDO $db, ?int $assignmentId, ?int $topicId, string $action, string $detail = ''): void
    {
        try {
            $db->prepare('INSERT INTO intern_teaching_audit (actor_id, assignment_id, topic_id, action, detail, ip) VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([(int) ($_SESSION['user_id'] ?? 0), $assignmentId, $topicId, $action, mb_substr($detail, 0, 500) ?: null, $_SERVER['REMOTE_ADDR'] ?? null]);
        } catch (Throwable $e) {
            error_log('[teaching audit] ' . $e->getMessage());
        }
    }

    /** Close the assignments of interns whose internship ended or whose account is off. */
    function it_sync(PDO $db): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            $ids = $db->query("SELECT DISTINCT a.intern_id FROM intern_teaching_assignments a JOIN interns i ON i.id = a.intern_id JOIN users u ON u.id = i.user_id
                               WHERE a.status = 'active' AND (i.status <> 'active' OR u.is_active = 0 OR (i.end_date IS NOT NULL AND i.end_date < CURDATE()))")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $id) it_close_for_intern($db, (int) $id);
        } catch (Throwable $e) {
        }
    }

    function it_close_for_intern(PDO $db, int $internId): void
    {
        try {
            $st = $db->prepare("UPDATE intern_teaching_assignments SET status = 'closed', closed_at = NOW() WHERE intern_id = ? AND status = 'active'");
            $st->execute([$internId]);
            if ($st->rowCount()) it_log($db, null, null, 'auto_closed', 'intern #' . $internId . ' no longer active');
        } catch (Throwable $e) {
        }
    }

    /** Data deletion of an intern (InternService::in_delete): remove their teaching rows and files. */
    function it_delete_for_intern(PDO $db, int $internId): void
    {
        try {
            $st = $db->prepare('SELECT t.id, t.file_path FROM intern_teaching_topics t JOIN intern_teaching_assignments a ON a.id = t.assignment_id WHERE a.intern_id = ?');
            $st->execute([$internId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
                if (function_exists('in_file_delete')) in_file_delete($t['file_path']);
                $db->prepare('DELETE FROM intern_teaching_reports WHERE topic_id = ?')->execute([(int) $t['id']]);
                $db->prepare('DELETE FROM intern_teaching_ratings WHERE topic_id = ?')->execute([(int) $t['id']]);
            }
            $db->prepare('DELETE t FROM intern_teaching_topics t JOIN intern_teaching_assignments a ON a.id = t.assignment_id WHERE a.intern_id = ?')->execute([$internId]);
            $db->prepare('DELETE FROM intern_teaching_assignments WHERE intern_id = ?')->execute([$internId]);
            it_log($db, null, null, 'deleted_with_intern', 'intern #' . $internId);
        } catch (Throwable $e) {
        }
    }

    // ── Tutor side ───────────────────────────────────────────────────────────────────────────────────────
    /** The tutor's own approved students (registration id, name, course). */
    function it_tutor_students(PDO $db, int $tutorUid): array
    {
        $st = $db->prepare("SELECT r.id, r.full_name, c.title AS course, r.course_id FROM registrations r
                            JOIN tutor_students ts ON ts.user_id = r.user_id JOIN tutors t ON t.id = ts.tutor_id AND t.user_id = ?
                            JOIN courses c ON c.id = r.course_id
                            WHERE r.status = 'approved' AND r.registration_no NOT LIKE 'PREVIEW-%' ORDER BY c.title, r.full_name");
        $st->execute([$tutorUid]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Interns a tutor may pick: active internship, account on, not past the end date. */
    function it_active_interns(PDO $db): array
    {
        try {
            return $db->query("SELECT i.id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS name, d.name AS department
                               FROM interns i JOIN users u ON u.id = i.user_id LEFT JOIN user_profiles up ON up.user_id = u.id LEFT JOIN intern_departments d ON d.id = i.department_id
                               WHERE i.status = 'active' AND u.is_active = 1 AND (i.end_date IS NULL OR i.end_date >= CURDATE()) ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    function it_assignment_for_tutor(PDO $db, int $tutorUid, int $assignmentId): ?array
    {
        $st = $db->prepare('SELECT * FROM intern_teaching_assignments WHERE id = ? AND tutor_id = ?');
        $st->execute([$assignmentId, $tutorUid]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** All of a tutor's assignments with intern name, students and topics (+ report and rating). */
    function it_assignments_for_tutor(PDO $db, int $tutorUid): array
    {
        it_sync($db);
        try {
            $st = $db->prepare("SELECT a.*, i.status AS intern_status, i.end_date,
                                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS intern_name
                                FROM intern_teaching_assignments a JOIN interns i ON i.id = a.intern_id JOIN users u ON u.id = i.user_id
                                LEFT JOIN user_profiles up ON up.user_id = u.id
                                WHERE a.tutor_id = ? ORDER BY a.status = 'active' DESC, a.created_at DESC");
            $st->execute([$tutorUid]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
        $mine = [];
        foreach (it_tutor_students($db, $tutorUid) as $s) $mine[(int) $s['id']] = $s;
        foreach ($rows as &$a) {
            $a['students'] = array_values(array_filter(array_map(fn($id) => $mine[(int) $id] ?? null, explode(',', $a['student_reg_ids']))));
            $a['topics'] = it_topics($db, (int) $a['id']);
        }
        return $rows;
    }

    function it_topics(PDO $db, int $assignmentId): array
    {
        $st = $db->prepare('SELECT t.*, r.covered, r.went_well, r.challenges, r.questions, r.created_at AS reported_at,
                                   g.preparation, g.clarity, g.confidence, g.comment AS rating_comment
                            FROM intern_teaching_topics t LEFT JOIN intern_teaching_reports r ON r.topic_id = t.id LEFT JOIN intern_teaching_ratings g ON g.topic_id = t.id
                            WHERE t.assignment_id = ? ORDER BY t.position, t.starts_at, t.id');
        $st->execute([$assignmentId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Only the tutor's own students; only an active intern. */
    function it_clean_students(PDO $db, int $tutorUid, array $regIds): array
    {
        $mine = array_map('intval', array_column(it_tutor_students($db, $tutorUid), 'id'));
        return array_values(array_intersect(array_unique(array_map('intval', $regIds)), $mine));
    }

    function it_assign(PDO $db, int $tutorUid, int $internId, array $regIds, string $label): array
    {
        if (!in_array($internId, array_map('intval', array_column(it_active_interns($db), 'id')), true)) return ['ok' => false, 'error' => 'Choose an active intern.'];
        $regs = it_clean_students($db, $tutorUid, $regIds);
        if (!$regs) return ['ok' => false, 'error' => 'Choose at least one of your students.'];
        $db->prepare('INSERT INTO intern_teaching_assignments (tutor_id, intern_id, group_label, student_reg_ids) VALUES (?, ?, ?, ?)')
           ->execute([$tutorUid, $internId, mb_substr(trim($label), 0, 150) ?: null, implode(',', $regs)]);
        $id = (int) $db->lastInsertId();
        it_log($db, $id, null, 'assigned', 'intern #' . $internId . ' students ' . implode(',', $regs));
        it_notify_intern($db, $internId, 'New teaching assignment', 'A tutor asked you to help teach a group. Open My Tasks to see the topics.', 'intern/tasks/index.php?view=list#teaching');
        return ['ok' => true, 'id' => $id];
    }

    function it_update_group(PDO $db, int $tutorUid, int $assignmentId, array $regIds, string $label): array
    {
        $a = it_assignment_for_tutor($db, $tutorUid, $assignmentId);
        if (!$a || $a['status'] !== 'active') return ['ok' => false, 'error' => 'Assignment not found.'];
        $regs = it_clean_students($db, $tutorUid, $regIds);
        if (!$regs) return ['ok' => false, 'error' => 'Choose at least one of your students.'];
        $db->prepare('UPDATE intern_teaching_assignments SET student_reg_ids = ?, group_label = ? WHERE id = ?')->execute([implode(',', $regs), mb_substr(trim($label), 0, 150) ?: null, $assignmentId]);
        it_log($db, $assignmentId, null, 'group_changed', implode(',', $regs));
        return ['ok' => true];
    }

    /** Remove the intern from this group (the assignment closes; topics and reports stay for the tutor's records). */
    function it_remove(PDO $db, int $tutorUid, int $assignmentId): void
    {
        $a = it_assignment_for_tutor($db, $tutorUid, $assignmentId);
        if (!$a || $a['status'] !== 'active') return;
        $db->prepare("UPDATE intern_teaching_assignments SET status = 'closed', closed_at = NOW() WHERE id = ?")->execute([$assignmentId]);
        it_log($db, $assignmentId, null, 'removed');
        it_notify_intern($db, (int) $a['intern_id'], 'Teaching assignment ended', 'The tutor ended one of your teaching assignments.', 'intern/tasks/index.php');
    }

    /** Add or edit a topic. $file is an optional resource upload. */
    function it_topic_save(PDO $db, int $tutorUid, int $assignmentId, ?int $topicId, array $in, array $file): array
    {
        $a = it_assignment_for_tutor($db, $tutorUid, $assignmentId);
        if (!$a || $a['status'] !== 'active') return ['ok' => false, 'error' => 'Assignment not found.'];
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') return ['ok' => false, 'error' => 'Enter the topic title.'];
        $starts = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string) ($in['starts_at'] ?? '')) ? str_replace('T', ' ', $in['starts_at']) . ':00' : null;
        $duration = max(0, min(600, (int) ($in['duration_min'] ?? 0))) ?: null;
        $links = implode("\n", array_filter(array_map('trim', preg_split('/\r?\n/', (string) ($in['resources'] ?? ''))), fn($l) => preg_match('#^https?://#i', $l)));
        $vals = [mb_substr($title, 0, 200), trim((string) ($in['subtopics'] ?? '')) ?: null, $starts, $duration, trim((string) ($in['notes'] ?? '')) ?: null, $links ?: null];
        if ($topicId) {
            $st = $db->prepare("SELECT * FROM intern_teaching_topics WHERE id = ? AND assignment_id = ? AND status <> 'taught'");
            $st->execute([$topicId, $assignmentId]);
            if (!$st->fetch()) return ['ok' => false, 'error' => 'Topic not found, or it was already taught.'];
            $db->prepare('UPDATE intern_teaching_topics SET title = ?, subtopics = ?, starts_at = ?, duration_min = ?, notes = ?, resources = ? WHERE id = ?')->execute([...$vals, $topicId]);
            it_log($db, $assignmentId, $topicId, 'topic_edited', $title);
        } else {
            $pos = (int) $db->query('SELECT COALESCE(MAX(position), 0) + 1 FROM intern_teaching_topics WHERE assignment_id = ' . $assignmentId)->fetchColumn();
            $db->prepare('INSERT INTO intern_teaching_topics (assignment_id, title, subtopics, starts_at, duration_min, notes, resources, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$assignmentId, ...$vals, $pos]);
            $topicId = (int) $db->lastInsertId();
            it_log($db, $assignmentId, $topicId, 'topic_added', $title);
            it_notify_intern($db, (int) $a['intern_id'], 'New topic to teach: ' . $title, $starts ? 'On ' . date('D j M, g:i A', strtotime($starts)) . '.' : 'Open it in My Tasks.', 'intern/tasks/teaching.php?id=' . $topicId);
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            require_once __DIR__ . '/InternService.php';
            $r = in_store_file($file, 'teaching/' . $assignmentId);
            if (!$r['ok']) return ['ok' => false, 'error' => 'Topic saved, but the file was not: ' . $r['error']];
            $old = $db->query('SELECT file_path FROM intern_teaching_topics WHERE id = ' . $topicId)->fetchColumn();
            if ($old) in_file_delete($old);
            $db->prepare('UPDATE intern_teaching_topics SET file_path = ?, file_name = ? WHERE id = ?')->execute([$r['path'], $r['name'], $topicId]);
        }
        return ['ok' => true, 'id' => $topicId];
    }

    function it_topic_of_tutor(PDO $db, int $tutorUid, int $topicId): ?array
    {
        $st = $db->prepare('SELECT t.*, a.intern_id, a.status AS assignment_status FROM intern_teaching_topics t JOIN intern_teaching_assignments a ON a.id = t.assignment_id
                            WHERE t.id = ? AND a.tutor_id = ?');
        $st->execute([$topicId, $tutorUid]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    function it_topic_move(PDO $db, int $tutorUid, int $topicId, int $dir): void
    {
        $t = it_topic_of_tutor($db, $tutorUid, $topicId);
        if (!$t) return;
        $all = array_column(it_topics($db, (int) $t['assignment_id']), 'id');
        $i = array_search($topicId, array_map('intval', $all), true);
        $j = $i + ($dir < 0 ? -1 : 1);
        if ($i === false || !isset($all[$j])) return;
        [$all[$i], $all[$j]] = [$all[$j], $all[$i]];
        $st = $db->prepare('UPDATE intern_teaching_topics SET position = ? WHERE id = ?');
        foreach ($all as $pos => $id) $st->execute([$pos + 1, (int) $id]);
        it_log($db, (int) $t['assignment_id'], $topicId, 'topic_moved', $dir < 0 ? 'up' : 'down');
    }

    function it_topic_cancel(PDO $db, int $tutorUid, int $topicId): void
    {
        $t = it_topic_of_tutor($db, $tutorUid, $topicId);
        if (!$t || $t['status'] !== 'planned') return;
        $db->prepare("UPDATE intern_teaching_topics SET status = 'cancelled' WHERE id = ?")->execute([$topicId]);
        it_log($db, (int) $t['assignment_id'], $topicId, 'topic_cancelled', $t['title']);
        it_notify_intern($db, (int) $t['intern_id'], 'Topic cancelled: ' . $t['title'], 'You do not need to teach it.', 'intern/tasks/index.php');
    }

    /** Move a planned topic to another of the tutor's active assignments (another intern or group). */
    function it_topic_reassign(PDO $db, int $tutorUid, int $topicId, int $toAssignmentId): array
    {
        $t = it_topic_of_tutor($db, $tutorUid, $topicId);
        $to = it_assignment_for_tutor($db, $tutorUid, $toAssignmentId);
        if (!$t || $t['status'] !== 'planned') return ['ok' => false, 'error' => 'Only planned topics can be moved.'];
        if (!$to || $to['status'] !== 'active' || (int) $to['id'] === (int) $t['assignment_id']) return ['ok' => false, 'error' => 'Choose another active intern group.'];
        $pos = (int) $db->query('SELECT COALESCE(MAX(position), 0) + 1 FROM intern_teaching_topics WHERE assignment_id = ' . (int) $to['id'])->fetchColumn();
        $db->prepare('UPDATE intern_teaching_topics SET assignment_id = ?, position = ? WHERE id = ?')->execute([(int) $to['id'], $pos, $topicId]);
        it_log($db, (int) $to['id'], $topicId, 'topic_reassigned', 'from assignment #' . $t['assignment_id']);
        it_notify_intern($db, (int) $to['intern_id'], 'New topic to teach: ' . $t['title'], 'Open it in My Tasks.', 'intern/tasks/teaching.php?id=' . $topicId);
        return ['ok' => true];
    }

    function it_rate(PDO $db, int $tutorUid, int $topicId, array $in): array
    {
        $t = it_topic_of_tutor($db, $tutorUid, $topicId);
        if (!$t || $t['status'] !== 'taught') return ['ok' => false, 'error' => 'You can rate a session once the intern marks it as taught.'];
        $r = [];
        foreach (['preparation', 'clarity', 'confidence'] as $k) {
            $v = (int) ($in[$k] ?? 0);
            if ($v < 1 || $v > 5) return ['ok' => false, 'error' => 'Give 1 to 5 stars for preparation, clarity and confidence.'];
            $r[] = $v;
        }
        $db->prepare('INSERT INTO intern_teaching_ratings (topic_id, tutor_id, preparation, clarity, confidence, comment) VALUES (?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE preparation = VALUES(preparation), clarity = VALUES(clarity), confidence = VALUES(confidence), comment = VALUES(comment)')
           ->execute([$topicId, $tutorUid, ...$r, mb_substr(trim((string) ($in['comment'] ?? '')), 0, 3000) ?: null]);
        it_log($db, (int) $t['assignment_id'], $topicId, 'rated', implode('/', $r));
        it_notify_intern($db, (int) $t['intern_id'], 'Your teaching was rated: ' . $t['title'], 'See the tutor\'s comment in Assessments.', 'intern/assessments/index.php#teaching');
        return ['ok' => true];
    }

    // ── Intern side ──────────────────────────────────────────────────────────────────────────────────────
    /** First name only — the intern never sees anything else about a student. */
    function it_first_name(string $full): string
    {
        $first = explode(' ', trim($full))[0] ?? '';
        return mb_convert_case(mb_strtolower($first), MB_CASE_TITLE, 'UTF-8');
    }

    /** Topics of the intern's ACTIVE assignments (+ taught/rated ones for history), with student first names. */
    function it_topics_for_intern(PDO $db, int $internId, bool $activeOnly = true): array
    {
        it_sync($db);
        try {
            $st = $db->prepare("SELECT t.*, a.status AS assignment_status, a.group_label, a.student_reg_ids, a.tutor_id,
                                       COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS tutor_name,
                                       r.covered, r.went_well, r.challenges, r.questions, g.preparation, g.clarity, g.confidence, g.comment AS rating_comment, g.created_at AS rated_at
                                FROM intern_teaching_topics t JOIN intern_teaching_assignments a ON a.id = t.assignment_id
                                JOIN users u ON u.id = a.tutor_id LEFT JOIN user_profiles up ON up.user_id = u.id
                                LEFT JOIN intern_teaching_reports r ON r.topic_id = t.id LEFT JOIN intern_teaching_ratings g ON g.topic_id = t.id
                                WHERE a.intern_id = ? AND t.status <> 'cancelled'" . ($activeOnly ? " AND a.status = 'active'" : '') . '
                                ORDER BY t.starts_at IS NULL, t.starts_at, t.position');
            $st->execute([$internId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
        $names = [];
        foreach ($rows as $r) foreach (explode(',', $r['student_reg_ids']) as $id) $names[(int) $id] = null;
        if ($names) {
            $ids = implode(',', array_map('intval', array_keys($names)));
            foreach ($db->query("SELECT id, full_name FROM registrations WHERE id IN ($ids)")->fetchAll(PDO::FETCH_KEY_PAIR) as $id => $n) $names[(int) $id] = it_first_name((string) $n);
        }
        foreach ($rows as &$r) {
            $r['first_names'] = array_values(array_filter(array_map(fn($id) => $names[(int) $id] ?? null, explode(',', $r['student_reg_ids']))));
            unset($r['student_reg_ids']);   // never handed to intern pages
        }
        return $rows;
    }

    function it_topic_for_intern(PDO $db, int $internId, int $topicId): ?array
    {
        foreach (it_topics_for_intern($db, $internId, false) as $t) if ((int) $t['id'] === $topicId) return $t;
        return null;
    }

    function it_mark_taught(PDO $db, array $intern, int $topicId, array $in): array
    {
        $t = it_topic_for_intern($db, (int) $intern['id'], $topicId);
        if (!$t || $t['assignment_status'] !== 'active' || $t['status'] !== 'planned') return ['ok' => false, 'error' => 'This topic cannot be marked as taught.'];
        $covered = trim((string) ($in['covered'] ?? ''));
        if ($covered === '') return ['ok' => false, 'error' => 'Write what you covered.'];
        $f = fn($k) => mb_substr(trim((string) ($in[$k] ?? '')), 0, 3000) ?: null;
        $db->prepare('INSERT INTO intern_teaching_reports (topic_id, intern_id, covered, went_well, challenges, questions) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$topicId, (int) $intern['id'], mb_substr($covered, 0, 3000), $f('went_well'), $f('challenges'), $f('questions')]);
        $db->prepare("UPDATE intern_teaching_topics SET status = 'taught', taught_at = NOW() WHERE id = ?")->execute([$topicId]);
        it_log($db, (int) $t['assignment_id'], $topicId, 'marked_taught', $t['title']);
        PortalNotifications::notify($db, (int) $t['tutor_id'], 'teaching', $intern['full_name'] . ' taught: ' . $t['title'], 'Read the report and rate the session.', 'tutor/helpers/index.php#topic-' . $topicId);
        return ['ok' => true];
    }

    /** For the intern's progress: sessions planned, taught and the average tutor rating (1–5). */
    function it_summary_for_intern(PDO $db, int $internId): array
    {
        $rows = it_topics_for_intern($db, $internId, false);
        $taught = array_filter($rows, fn($t) => $t['status'] === 'taught');
        $ratings = array_filter(array_map(fn($t) => $t['preparation'] !== null ? ($t['preparation'] + $t['clarity'] + $t['confidence']) / 3 : null, $taught), fn($v) => $v !== null);
        return ['total' => count($rows), 'taught' => count($taught), 'rating' => $ratings ? round(array_sum($ratings) / count($ratings), 1) : null, 'topics' => $rows];
    }

    /** Can this user open the resource file of a topic? Only the tutor who owns it, or the assigned (active) intern. */
    function it_file_allowed(PDO $db, int $topicId, int $userId): ?array
    {
        $st = $db->prepare("SELECT t.file_path, t.file_name FROM intern_teaching_topics t JOIN intern_teaching_assignments a ON a.id = t.assignment_id
                            JOIN interns i ON i.id = a.intern_id
                            WHERE t.id = ? AND (a.tutor_id = ? OR (i.user_id = ? AND a.status = 'active' AND i.status = 'active'))");
        $st->execute([$topicId, $userId, $userId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    function it_notify_intern(PDO $db, int $internId, string $title, string $body, string $path): void
    {
        $uid = (int) $db->query('SELECT user_id FROM interns WHERE id = ' . $internId)->fetchColumn();
        if ($uid) PortalNotifications::notify($db, $uid, 'teaching', $title, $body, $path);
    }
}
