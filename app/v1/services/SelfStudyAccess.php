<?php
// app/v1/services/SelfStudyAccess.php — Self-study (step 6): who sees a course (students) and which other tutors can open it (sharing).
//
// THE RULES, checked here on the server (the screens only show them):
//  * A tutor can only pick their OWN students (the students linked to them in the existing system, the same list as "My Students"). Admin can pick any approved student.
//    A request that names any other student is refused as a whole, even if someone changed the page.
//  * Owner, "Can edit" and "Can view" tutors and admin may choose students. A tutor with any access can only remove the students THEY added; students added by
//    another tutor are left alone. The owner and admin see every student on the course and can remove any of them.
//  * Only the owner and admin can change the sharing. Changing a tutor to "No access" never removes the students that tutor added.
//  * Removing a student only sets removed_at: the course is hidden from them but their progress is kept, and adding them again opens exactly where they stopped.
//  * A draft tells nobody. Publishing (or adding a student to a published course) tells each new student through the portal notifications.
//  * Every change is written to self_study_activity_log with who did it.
require_once __DIR__ . '/SelfStudyContent.php';
require_once __DIR__ . '/StudentProgress.php';

if (!function_exists('ssa_picker')) {

    function ssa_installed(PDO $db): bool
    {
        try { $db->query('SELECT added_by_tutor_id, removed_by, notified_at FROM self_study_access WHERE 1 = 0'); $db->query('SELECT 1 FROM self_study_activity_log WHERE 1 = 0'); return true; }
        catch (Throwable $e) { return false; }
    }

    /** What this person may do on this course. @return array{pick:bool, share:bool, see_all:bool} */
    function ssa_caps(array $course, array $viewer): array
    {
        $admin = $viewer['role'] === 'admin';
        $access = $course['access'] ?? null;
        return ['pick' => $admin || in_array($access, ['owner', 'edit', 'view'], true), 'share' => $admin || $access === 'owner', 'see_all' => $admin || $access === 'owner'];
    }

    function ssa_guard(PDO $db, array $viewer, int $courseId, string $need = 'pick'): array
    {
        $c = sst_course($db, $viewer, $courseId);
        if (!$c) throw new SscException('That course was not found, or it was not shared with you.', 404);
        $caps = ssa_caps($c, $viewer);
        if (!$caps[$need]) throw new SscException($need === 'share' ? 'Only the owner and admin can change who the course is shared with.' : 'You cannot choose students for this course.', 403);
        return $c;
    }

    function ssa_log(PDO $db, array $viewer, int $courseId, string $action, int $count, array $detail, ?int $now = null): void
    {
        $db->prepare('INSERT INTO self_study_activity_log (course_id, actor_user_id, actor_name, actor_role, action, item_count, detail, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([$courseId, $viewer['uid'], mb_substr((string) $viewer['name'], 0, 150), $viewer['role'], $action, $count, json_encode($detail, JSON_UNESCAPED_UNICODE), ss2_ts($now ?? time())]);
    }

    function ssa_tutor_names(PDO $db, array $tutorIds): array
    {
        $tutorIds = array_values(array_unique(array_filter(array_map('intval', $tutorIds))));
        if (!$tutorIds) return [];
        $out = [];
        foreach (ss2_rows($db, "SELECT t.id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))), ''), u.username) AS name
                                FROM tutors t JOIN users u ON u.id = t.user_id LEFT JOIN user_profiles up ON up.user_id = u.id WHERE t.id IN (" . implode(',', $tutorIds) . ')') as $r) $out[(int) $r['id']] = (string) $r['name'];
        return $out;
    }

    /** All access rows of the course (active and removed), by student id. */
    function ssa_rows(PDO $db, int $courseId): array
    {
        $out = [];
        foreach (ss2_rows($db, 'SELECT student_id, added_by, added_by_tutor_id, added_at, removed_at FROM self_study_access WHERE course_id = ?', [$courseId]) as $r) $out[(int) $r['student_id']] = $r;
        return $out;
    }

    /**
     * The students this person can see in the picker, as id => info, worked out in a few queries (never one per student):
     * a tutor: their own students; admin: every approved student; the owner also sees every student already on the course (to remove them if needed).
     */
    function ssa_universe(PDO $db, array $viewer, array $course, string $q = ''): array
    {
        $caps = ssa_caps($course, $viewer);
        $rows = ssa_rows($db, (int) $course['id']);
        $students = sp_students($db, $viewer, $q !== '' ? ['q' => $q] : []);
        if ($caps['see_all'] && $viewer['role'] !== 'admin') {                         // the owner also needs the students other tutors added
            $extra = array_values(array_diff(array_keys(array_filter($rows, fn($r) => $r['removed_at'] === null)), array_keys($students)));
            if ($extra) foreach (sp_students($db, ['role' => 'admin', 'tutor_id' => null, 'uid' => $viewer['uid'], 'name' => ''], ['ids' => $extra] + ($q !== '' ? ['q' => $q] : [])) as $id => $s) $students[$id] = $s;
        }
        $adderIds = array_filter(array_map(fn($r) => (int) ($r['added_by_tutor_id'] ?? 0), $rows));
        $names = ssa_tutor_names($db, $adderIds);
        $me = (int) ($viewer['tutor_id'] ?? 0); $out = [];
        foreach ($students as $id => $s) {
            $row = $rows[$id] ?? null; $active = $row && $row['removed_at'] === null; $by = $active ? (int) ($row['added_by_tutor_id'] ?? 0) : 0;
            $removable = $active && ($caps['see_all'] || ($me && $by === $me));
            $out[$id] = ['id' => $id, 'name' => $s['full_name'], 'number' => $s['registration_no'], 'course_id' => (int) $s['course_id'], 'course_title' => $s['course_title'],
                         'photo' => $s['photo']['url'] ?? null, 'selected' => (bool) $active, 'locked' => $active && !$removable,
                         'by' => $active ? ($by ? ($names[$by] ?? 'another tutor') : 'Admin') : '', 'mine' => $active && $by === $me && $me > 0];
        }
        // students on the course whose registration is no longer approved: the owner and admin can still remove them
        if ($caps['see_all']) {
            $missing = array_values(array_diff(array_keys(array_filter($rows, fn($r) => $r['removed_at'] === null)), array_keys($out)));
            if ($missing) foreach (ss2_rows($db, "SELECT u.id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))), ''), u.username) AS name FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id IN (" . implode(',', array_map('intval', $missing)) . ')') as $u) {
                $by = (int) ($rows[(int) $u['id']]['added_by_tutor_id'] ?? 0);
                if ($q !== '' && mb_stripos($u['name'], $q) === false) continue;
                $out[(int) $u['id']] = ['id' => (int) $u['id'], 'name' => $u['name'], 'number' => '', 'course_id' => 0, 'course_title' => 'Other', 'photo' => null, 'selected' => true, 'locked' => false, 'by' => $by ? ($names[$by] ?? 'another tutor') : 'Admin', 'mine' => false];
            }
        }
        return $out;
    }

    /** The course audience for the right-hand box: how many students, from which courses, and (owner / admin) who added how many. */
    function ssa_audience(PDO $db, int $courseId, bool $withAdders): array
    {
        $rows = ss2_rows($db, "SELECT a.student_id, a.added_by_tutor_id, r.course_id, c.title
                               FROM self_study_access a
                               LEFT JOIN registrations r ON r.user_id = a.student_id AND r.status = 'approved' AND r.id = (SELECT MAX(r2.id) FROM registrations r2 WHERE r2.user_id = a.student_id AND r2.status = 'approved')
                               LEFT JOIN courses c ON c.id = r.course_id
                               WHERE a.course_id = ? AND a.removed_at IS NULL", [$courseId]);
        $byCourse = []; $byAdder = [];
        foreach ($rows as $r) {
            $k = (int) ($r['course_id'] ?? 0); $byCourse[$k] ??= ['id' => $k, 'title' => $r['title'] ?: 'Other', 'n' => 0]; $byCourse[$k]['n']++;
            $a = (int) ($r['added_by_tutor_id'] ?? 0); $byAdder[$a] = ($byAdder[$a] ?? 0) + 1;
        }
        $adders = [];
        if ($withAdders) { $names = ssa_tutor_names($db, array_keys($byAdder)); foreach ($byAdder as $tid => $n) $adders[] = ['tutor_id' => $tid, 'name' => $tid ? ($names[$tid] ?? 'A tutor') : 'Admin', 'n' => $n]; }
        usort($byCourse, fn($a, $b) => strcmp($a['title'], $b['title']));
        return ['total' => count($rows), 'courses' => array_values($byCourse), 'adders' => $adders];
    }

    /** Everything the "Who sees it" tab shows, grouped by the students' course. */
    function ssa_picker(PDO $db, array $viewer, int $courseId): array
    {
        $c = ssa_guard($db, $viewer, $courseId);
        $caps = ssa_caps($c, $viewer);
        $uni = ssa_universe($db, $viewer, $c);
        $groups = [];
        foreach ($uni as $s) {
            $k = $s['course_id']; $groups[$k] ??= ['id' => $k, 'title' => $s['course_title'], 'students' => []];
            $groups[$k]['students'][] = $s + ['initials' => sp_initials($s['name'])];
        }
        foreach ($groups as &$g) usort($g['students'], fn($a, $b) => strcasecmp($a['name'], $b['name']));
        unset($g);
        uasort($groups, fn($a, $b) => strcasecmp($a['title'], $b['title']));
        $audience = ssa_audience($db, $courseId, $caps['see_all']);
        return ['groups' => array_values($groups), 'audience' => $audience, 'caps' => $caps, 'status' => $c['status'], 'can_publish' => $c['can_edit'], 'total_students' => count($uni)];
    }

    /** Tell students about the course (only a published one). Records notified_at so nobody is told twice. */
    function ssa_notify_students(PDO $db, int $courseId, array $studentIds, ?int $now = null): int
    {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
        if (!$studentIds) return 0;
        $c = ss2_rows($db, 'SELECT title, status FROM self_study_courses WHERE id = ?', [$courseId])[0] ?? null;
        if (!$c || $c['status'] !== 'published') return 0;
        ss3_notify($studentIds, 'selfstudy_course', 'New self-study course: ' . $c['title'], 'Your tutor shared a self-study course with you. Open Self-study to start.', 'students/selfstudy/index.php', $db);
        $up = $db->prepare('UPDATE self_study_access SET notified_at = ? WHERE course_id = ? AND student_id = ? AND removed_at IS NULL');
        foreach ($studentIds as $sid) $up->execute([ss2_ts($now ?? time()), $courseId, $sid]);
        return count($studentIds);
    }

    /** Called when the course is published: every active student who has not been told yet. */
    function ssa_notify_pending(PDO $db, int $courseId, ?int $now = null): int
    {
        $ids = array_map('intval', array_column(ss2_rows($db, 'SELECT student_id FROM self_study_access WHERE course_id = ? AND removed_at IS NULL AND notified_at IS NULL', [$courseId]), 'student_id'));
        return ssa_notify_students($db, $courseId, $ids, $now);
    }

    /**
     * Save the choice of students. $ids = the students the person ticked. The server works out who they are allowed to touch (never trusting the page):
     * a student who is not theirs to pick refuses the whole request; locked students (added by another tutor) are left alone.
     * @return array{ok:true, added:int, removed:int, total:int, notified:int}
     */
    function ssa_save_students(PDO $db, array $viewer, int $courseId, array $ids, ?int $now = null): array
    {
        $now ??= time();
        $c = ssa_guard($db, $viewer, $courseId);
        $sel = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
        $uni = ssa_universe($db, $viewer, $c);
        $rows = ssa_rows($db, $courseId);
        $selSet = array_flip($sel);
        foreach ($sel as $id) {                                              // every ticked student must be one this person may pick
            if (!isset($uni[$id])) throw new SscException('You can only pick your own students. The request was refused and nothing was changed.', 403);
        }
        $allowed = array_flip(sp_allowed_ids($db, $viewer, array_keys(array_filter($uni, fn($s) => !$s['selected'] && isset($selSet[$s['id']])))));   // new students: approved and (for a tutor) linked to them
        $ts = ss2_ts($now); $tutorId = $viewer['tutor_id'] ?? null; $add = []; $remove = [];
        foreach ($uni as $id => $s) {
            $want = isset($selSet[$id]);
            if ($want && !$s['selected']) { if (!isset($allowed[$id])) throw new SscException('You can only pick your own students. The request was refused and nothing was changed.', 403); $add[] = $id; }
            elseif (!$want && $s['selected'] && !$s['locked']) $remove[] = $id;
        }
        $db->beginTransaction();
        try {
            foreach ($add as $id) {
                if (isset($rows[$id])) $db->prepare('UPDATE self_study_access SET removed_at = NULL, removed_by = NULL, added_by = ?, added_by_tutor_id = ?, added_at = ?, notified_at = NULL WHERE course_id = ? AND student_id = ?')->execute([$viewer['uid'], $tutorId, $ts, $courseId, $id]);
                else $db->prepare('INSERT INTO self_study_access (course_id, student_id, added_by, added_by_tutor_id, added_at) VALUES (?, ?, ?, ?, ?)')->execute([$courseId, $id, $viewer['uid'], $tutorId, $ts]);
            }
            foreach ($remove as $id) $db->prepare('UPDATE self_study_access SET removed_at = ?, removed_by = ? WHERE course_id = ? AND student_id = ? AND removed_at IS NULL')->execute([$ts, $viewer['uid'], $courseId, $id]);
            if ($add) ssa_log($db, $viewer, $courseId, 'students_added', count($add), ['ids' => array_slice($add, 0, 200)], $now);
            if ($remove) ssa_log($db, $viewer, $courseId, 'students_removed', count($remove), ['ids' => array_slice($remove, 0, 200)], $now);
            $db->prepare('UPDATE self_study_courses SET updated_at = ? WHERE id = ?')->execute([$ts, $courseId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('selfstudy save students: ' . $e->getMessage());
            throw new SscException('The students could not be saved. Please try again.', 500);
        }
        $notified = $add ? ssa_notify_students($db, $courseId, $add, $now) : 0;      // only does anything when the course is already published
        $total = (int) ss2_rows($db, 'SELECT COUNT(*) AS n FROM self_study_access WHERE course_id = ? AND removed_at IS NULL', [$courseId])[0]['n'];
        return ['ok' => true, 'added' => count($add), 'removed' => count($remove), 'total' => $total, 'notified' => $notified];
    }

    /** Save the students, then (for a draft) publish: the server checks every item of the checklist again. */
    function ssa_save_and_publish(PDO $db, array $viewer, int $courseId, array $ids, ?int $now = null): array
    {
        $r = ssa_save_students($db, $viewer, $courseId, $ids, $now);
        $p = sst_publish($db, $viewer, $courseId, $now);
        return $r + ['published' => $p['ok'], 'publish_error' => $p['ok'] ? null : $p['error']];
    }

    /* ------------------------------ sharing with other tutors ------------------------------ */

    /** The other active tutors with their access (none / view / edit) and how many students each added. */
    function ssa_tutors(PDO $db, array $viewer, int $courseId): array
    {
        $c = ssa_guard($db, $viewer, $courseId);
        $acc = array_column(ss2_rows($db, 'SELECT tutor_id, access FROM self_study_course_editors WHERE course_id = ?', [$courseId]), 'access', 'tutor_id');
        $added = array_column(ss2_rows($db, 'SELECT added_by_tutor_id AS t, COUNT(*) AS n FROM self_study_access WHERE course_id = ? AND removed_at IS NULL AND added_by_tutor_id IS NOT NULL GROUP BY added_by_tutor_id', [$courseId]), 'n', 't');
        $subs = [];
        foreach (ss2_rows($db, 'SELECT ct.tutor_id, co.title FROM course_tutors ct JOIN courses co ON co.id = ct.course_id ORDER BY co.title') as $r) $subs[(int) $r['tutor_id']][] = $r['title'];
        $out = [];
        foreach (ss2_rows($db, "SELECT t.id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))), ''), u.username) AS name
                                FROM tutors t JOIN users u ON u.id = t.user_id LEFT JOIN user_profiles up ON up.user_id = u.id WHERE t.is_active = 1 AND t.id <> ? ORDER BY name", [(int) $c['owner_tutor_id']]) as $t) {
            $id = (int) $t['id'];
            $out[] = ['id' => $id, 'name' => $t['name'], 'initials' => sp_initials($t['name']), 'sub' => 'Tutor' . (!empty($subs[$id]) ? ' · ' . $subs[$id][0] : '') . (!empty($added[$id]) ? ' · added ' . (int) $added[$id] . ' of his students' : ''),
                      'access' => $acc[$id] ?? 'none', 'added' => (int) ($added[$id] ?? 0)];
        }
        return $out;
    }

    /** Owner and admin only. 'none' removes the tutor's access but NEVER touches the students that tutor added. */
    function ssa_set_editor(PDO $db, array $viewer, int $courseId, int $tutorId, string $access, ?int $now = null): array
    {
        $now ??= time();
        $c = ssa_guard($db, $viewer, $courseId, 'share');
        if (!in_array($access, ['none', 'view', 'edit'], true)) throw new SscException('Choose No access, Can view or Can edit.', 422);
        $t = ss2_rows($db, 'SELECT t.id, t.user_id FROM tutors t WHERE t.id = ? AND t.is_active = 1', [$tutorId])[0] ?? null;
        if (!$t) throw new SscException('That tutor was not found.', 404);
        if ((int) $c['owner_tutor_id'] === $tutorId) throw new SscException('The owner already has full access.', 422);
        $cur = ss2_rows($db, 'SELECT id, access FROM self_study_course_editors WHERE course_id = ? AND tutor_id = ?', [$courseId, $tutorId])[0] ?? null;
        $old = $cur['access'] ?? 'none';
        if ($old === $access) return ['ok' => true, 'access' => $access, 'changed' => false];
        if ($access === 'none') $db->prepare('DELETE FROM self_study_course_editors WHERE course_id = ? AND tutor_id = ?')->execute([$courseId, $tutorId]);
        elseif ($cur) $db->prepare('UPDATE self_study_course_editors SET access = ?, given_by = ?, given_at = ? WHERE id = ?')->execute([$access, $viewer['uid'], ss2_ts($now), $cur['id']]);
        else $db->prepare('INSERT INTO self_study_course_editors (course_id, tutor_id, access, given_by, given_at) VALUES (?, ?, ?, ?, ?)')->execute([$courseId, $tutorId, $access, $viewer['uid'], ss2_ts($now)]);
        ssa_log($db, $viewer, $courseId, 'access_' . $access, 1, ['tutor_id' => $tutorId, 'from' => $old], $now);
        if ($access !== 'none') {
            ss3_notify([(int) $t['user_id']], 'selfstudy_share', $viewer['name'] . ' shared a self-study course with you', '"' . $c['title'] . '" · ' . ($access === 'edit' ? 'Can edit' : 'Can view') . '. It is in your Self-study list.', 'tutor/selfstudy/edit.php?id=' . $courseId, $db);
        }
        return ['ok' => true, 'access' => $access, 'changed' => true];
    }
}
