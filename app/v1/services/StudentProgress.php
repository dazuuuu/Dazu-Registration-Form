<?php
// app/v1/services/StudentProgress.php
// Student Progress: a progress card for every student, for tutors and admin. READ-ONLY on marks, assignments and monthly projects.
//
// THE RULES, in one place:
//  1 Who: admin sees every student; a tutor sees only the students linked to them (tutor_students, the same list as "My Students").
//    Every page and every download checks this on the server with sp_viewer() and sp_allowed_ids(); hiding a menu link is not the check.
//  2 Marks: every average comes from AssignmentAverage::compute() (the course average service): released marks only, the newest
//    resubmission, the assignment weights, missing work never counted as zero. The subject average goes through subj_compute(), which
//    calls the same service. There is no second copy of the maths here.
//  3 Speed: the marks of all listed students come from ONE query (sp_rows()), not one per student.
//  4 The status word (Working well / Slowing down / Not working) and its comment are worked out live, every time, from the numbers in
//    progress_settings (defaults below). Nothing about the status is stored.
//  5 Private notes are seen only by the tutor who wrote them and by admin, and never go on a downloaded card.
//  6 A nudge is sent only when a tutor presses the button, through PortalNotifications.

require_once __DIR__ . '/AssignmentAverage.php';
require_once __DIR__ . '/SubjectService.php';
require_once __DIR__ . '/PortalNotifications.php';
require_once __DIR__ . '/../helpers/course_duration.php';

if (!function_exists('sp_viewer')) {
    /** The status numbers admin can change in Settings; these are the defaults. */
    define('SP_DEFAULTS', ['working_days' => 7, 'slowing_days' => 14, 'slowing_missing' => 2, 'notworking_missing' => 3]);
    define('SP_CHUNK', 400);        // students per query (keeps IN lists short)
    define('SP_PDF_MAX', 300);      // most cards in one PDF

    /* ---------------- who may use this ---------------- */

    /**
     * The signed-in person as a viewer, or null when they may not use Student Progress.
     * $portal is 'admin' or 'tutor'. Only office admins (role id 2, or a tutor using granted admin rights) and active tutors qualify;
     * students, staff, interns and everyone else get null.
     */
    function sp_viewer(PDO $db, string $portal): ?array
    {
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        if ($uid <= 0 || ($_SESSION['logged_in'] ?? false) !== true) return null;
        $role = (string) ($_SESSION['role'] ?? '');
        $roleId = (int) ($_SESSION['role_id'] ?? 0);
        if ($portal === 'admin') {
            if ($role !== 'admin' || $roleId !== 2) return null;
            return ['uid' => $uid, 'role' => 'admin', 'tutor_id' => null, 'name' => sp_user_name($db, $uid)];
        }
        if ($portal === 'tutor' && $role === 'tutor') {
            $st = $db->prepare('SELECT id FROM tutors WHERE user_id = ? AND is_active = 1');
            $st->execute([$uid]);
            $tid = (int) $st->fetchColumn();
            return $tid ? ['uid' => $uid, 'role' => 'tutor', 'tutor_id' => $tid, 'name' => sp_user_name($db, $uid)] : null;
        }
        return null;
    }

    function sp_user_name(PDO $db, int $uid): string
    {
        $st = $db->prepare("SELECT COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))), ''), u.username)
                            FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = ?");
        $st->execute([$uid]);
        return (string) ($st->fetchColumn() ?: 'Staff');
    }

    function sp_installed(PDO $db): bool
    {
        static $ok = null;
        if ($ok === null) {
            try { $db->query('SELECT 1 FROM progress_notes WHERE 1 = 0'); $db->query('SELECT 1 FROM progress_activity_log WHERE 1 = 0'); $ok = true; }
            catch (Throwable $e) { $ok = false; }
        }
        return $ok;
    }

    /** Of these student user ids, the ones this viewer may see (an approved registration, and for a tutor a link to them). */
    function sp_allowed_ids(PDO $db, array $viewer, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
        $ok = [];
        foreach (array_chunk($ids, SP_CHUNK) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "SELECT DISTINCT r.user_id FROM registrations r WHERE r.status = 'approved' AND r.user_id IN ($in)";
            $args = $chunk;
            if ($viewer['role'] === 'tutor') {
                $sql .= ' AND EXISTS (SELECT 1 FROM tutor_students ts WHERE ts.user_id = r.user_id AND ts.tutor_id = ?)';
                $args[] = (int) $viewer['tutor_id'];
            }
            $st = $db->prepare($sql);
            $st->execute($args);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $u) $ok[(int) $u] = true;
        }
        return array_keys($ok);
    }

    /* ---------------- settings ---------------- */

    function sp_settings(PDO $db): array
    {
        $s = SP_DEFAULTS;
        try {
            foreach ($db->query('SELECT setting_key, setting_value FROM progress_settings')->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
                if (isset($s[$k]) && is_numeric($v) && (int) $v >= 0) $s[$k] = (int) $v;
            }
        } catch (Throwable $e) { /* migration 103 not run: defaults */ }
        return $s;
    }

    /** @return array{ok:bool, error?:string} */
    function sp_settings_save(PDO $db, array $in, int $uid): array
    {
        $new = [];
        foreach (SP_DEFAULTS as $k => $_) {
            $v = trim((string) ($in[$k] ?? ''));
            if ($v === '' || !ctype_digit($v) || (int) $v < 1 || (int) $v > 365) return ['ok' => false, 'error' => 'Every number must be a whole number from 1 to 365.'];
            $new[$k] = (int) $v;
        }
        if ($new['working_days'] >= $new['slowing_days']) return ['ok' => false, 'error' => '"Working well" days must be fewer than "Not working" days.'];
        if ($new['slowing_missing'] >= $new['notworking_missing']) return ['ok' => false, 'error' => '"Slowing down" missing assignments must be fewer than "Not working".'];
        $up = $db->prepare('INSERT INTO progress_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)');
        foreach ($new as $k => $v) $up->execute([$k, (string) $v, $uid, gmdate('Y-m-d H:i:s')]);
        return ['ok' => true];
    }

    /* ---------------- the list of students ---------------- */

    /**
     * Students this viewer may see, newest registration per student. Filters: course (id), tutor (id, admin only), q (name or number), ids.
     * Returns user_id => row with name, number, course, start date, tutors and photo.
     */
    function sp_students(PDO $db, array $viewer, array $f = []): array
    {
        $where = ["r.status = 'approved'", 'r.user_id IS NOT NULL', 'r.id = (SELECT MAX(r2.id) FROM registrations r2 WHERE r2.user_id = r.user_id AND r2.status = \'approved\')'];
        $args = [];
        if ($viewer['role'] === 'tutor') {
            $where[] = 'EXISTS (SELECT 1 FROM tutor_students ts WHERE ts.user_id = r.user_id AND ts.tutor_id = ?)';
            $args[] = (int) $viewer['tutor_id'];
        } elseif (!empty($f['tutor'])) {
            $where[] = 'EXISTS (SELECT 1 FROM tutor_students ts WHERE ts.user_id = r.user_id AND ts.tutor_id = ?)';
            $args[] = (int) $f['tutor'];
        }
        if (!empty($f['course'])) { $where[] = 'r.course_id = ?'; $args[] = (int) $f['course']; }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)) . '%';
            $where[] = '(LOWER(r.full_name) LIKE ? OR LOWER(r.registration_no) LIKE ?)';
            array_push($args, $like, $like);
        }
        if (isset($f['ids'])) {
            $ids = array_values(array_unique(array_map('intval', (array) $f['ids'])));
            if (!$ids) return [];
            $where[] = 'r.user_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $args = array_merge($args, $ids);
        }
        $st = $db->prepare(
            "SELECT r.user_id, r.id AS registration_id, r.study_mode, r.full_name, r.registration_no, r.course_id, c.title AS course_title,
                    c.duration_months, c.weeks_of_study, c.days_of_study, c.hours_of_study,
                    COALESCE((SELECT MIN(t3.start_date) FROM tutor_students t3 WHERE t3.user_id = r.user_id AND t3.start_date IS NOT NULL),
                             CAST(r.reviewed_at AS DATE), CAST(r.created_at AS DATE)) AS start_date
             FROM registrations r JOIN courses c ON c.id = r.course_id
             WHERE " . implode(' AND ', $where) . ' ORDER BY r.full_name, r.id'
        );
        $st->execute($args);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r['user_id']] = $r + ['tutors' => [], 'tutor_ids' => [], 'photo' => null];
        if (!$out) return [];

        // Tutors and photos for everyone listed: one query each per chunk.
        foreach (array_chunk(array_keys($out), SP_CHUNK) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $st = $db->prepare("SELECT ts.user_id, t.id AS tutor_id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))), ''), u.username) AS name
                                FROM tutor_students ts JOIN tutors t ON t.id = ts.tutor_id JOIN users u ON u.id = t.user_id LEFT JOIN user_profiles up ON up.user_id = u.id
                                WHERE ts.user_id IN ($in) ORDER BY name");
            $st->execute($chunk);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
                $out[(int) $t['user_id']]['tutors'][(int) $t['tutor_id']] = $t['name'];
                $out[(int) $t['user_id']]['tutor_ids'][] = (int) $t['tutor_id'];
            }
            $st = $db->prepare("SELECT user_id, profile_photo_path FROM user_profiles WHERE profile_photo_path IS NOT NULL AND profile_photo_path <> '' AND profile_photo_status <> 'rejected' AND user_id IN ($in)");
            $st->execute($chunk);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $rel = ltrim(preg_replace('#^/?Registration-System/public/#', '', (string) $p['profile_photo_path']), '/');
                $abs = dirname(__DIR__, 3) . '/public/' . $rel;
                if (is_file($abs)) $out[(int) $p['user_id']]['photo'] = ['file' => $abs, 'url' => url('profile/photo.php?u=' . (int) $p['user_id'] . '&v=' . substr(md5($abs . '|' . @filemtime($abs)), 0, 10))];
            }
        }
        return $out;
    }

    function sp_initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$parts) return '?';
        $a = mb_substr($parts[0], 0, 1);
        $b = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
        return mb_strtoupper($a . $b);
    }

    /* ---------------- marks: one query for everyone ---------------- */

    /** Assignment rows for all these students, in the exact shape AssignmentAverage::compute() wants. @return array<int,array[]> user id => rows */
    function sp_rows(PDO $db, array $userIds): array
    {
        $by = [];
        foreach (array_chunk(array_values($userIds), SP_CHUNK) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $st = $db->prepare("SELECT r.student_user_id AS uid, a.id, a.title, a.tutor_id, a.course_id, a.kind, a.weight, a.grading_mode, a.max_score,
                                       a.release_grades_at, a.sort_order, a.subject_id, COALESCE(r.current_due_at, a.due_at) AS due,
                                       s.status AS sub_status, s.submitted_at, s.score, s.pass_result, s.published_at AS result_published_at, s.is_late
                                FROM tutor_assignment_recipients r
                                JOIN tutor_assignments a ON a.id = r.assignment_id AND a.status = 'published' AND a.is_template = 0
                                LEFT JOIN tutor_assignment_submissions s ON s.assignment_id = a.id AND s.student_user_id = r.student_user_id
                                WHERE r.student_user_id IN ($in) AND r.removed_at IS NULL AND r.skipped_at IS NULL
                                ORDER BY a.course_id, a.kind, a.sort_order, a.id");
            $st->execute($chunk);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $by[(int) $r['uid']][] = $r;
            // The capstone counts like any other marked item in the course average (same rows the average service gets elsewhere).
            foreach (sp_capstone_rows($db, $chunk) as $uid => $rows) foreach ($rows as $r) $by[$uid][] = $r;
        }
        return $by;
    }

    /** Capstone rows for many students in one query (the same mapping as cap_average_rows()). */
    function sp_capstone_rows(PDO $db, array $chunk): array
    {
        static $installed = null;
        if ($installed === null) {
            try { $db->query('SELECT 1 FROM capstone_students WHERE 1 = 0'); $db->query('SELECT 1 FROM capstone_marks WHERE 1 = 0'); $installed = true; }
            catch (Throwable $e) { $installed = false; }
        }
        if (!$installed || !$chunk) return [];
        $in = implode(',', array_fill(0, count($chunk), '?'));
        $st = $db->prepare("SELECT cs.student_user_id AS uid, c.id, c.title, c.tutor_id, c.course_id, c.weight, c.due_at, cs.own_due_at,
                                   a.status AS attempt_status, a.first_submitted_at, m.total, m.marked_at, m.held_until
                            FROM capstone_students cs JOIN capstones c ON c.id = cs.capstone_id AND c.status IN ('open','closed')
                            LEFT JOIN capstone_attempts a ON a.capstone_id = c.id AND a.student_user_id = cs.student_user_id
                                 AND a.attempt_no = (SELECT MAX(a2.attempt_no) FROM capstone_attempts a2 WHERE a2.capstone_id = c.id AND a2.student_user_id = cs.student_user_id)
                            LEFT JOIN capstone_marks m ON m.attempt_id = a.id
                            WHERE cs.removed_at IS NULL AND cs.student_user_id IN ($in)");
        $st->execute($chunk);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $graded = $r['attempt_status'] === 'marked' && $r['total'] !== null;
            $out[(int) $r['uid']][] = [
                'id' => 1000000 + (int) $r['id'], 'title' => $r['title'], 'tutor_id' => $r['tutor_id'], 'course_id' => $r['course_id'], 'subject_id' => null,
                'kind' => 'capstone', 'weight' => $r['weight'], 'grading_mode' => 'marks', 'max_score' => 100, 'release_grades_at' => $r['held_until'],
                'sort_order' => 999999, 'due' => $r['own_due_at'] ?: $r['due_at'],
                'sub_status' => $graded ? 'graded' : ($r['attempt_status'] === 'submitted' ? 'submitted' : ($r['attempt_status'] === 'returned' ? 'returned' : null)),
                'submitted_at' => $r['first_submitted_at'], 'score' => $graded ? $r['total'] : null, 'pass_result' => null,
                'result_published_at' => $graded ? $r['marked_at'] : null, 'is_late' => 0,
            ];
        }
        return $out;
    }

    function sp_handed(array $r): bool
    {
        return !empty($r['submitted_at']) || in_array((string) ($r['sub_status'] ?? ''), ['submitted', 'graded', 'returned'], true);
    }

    function sp_missing(array $r, int $now): bool
    {
        return !sp_handed($r) && !empty($r['due']) && strtotime($r['due'] . ' UTC') <= $now;
    }

    function sp_pct(?float $v): string
    {
        return $v === null ? '' : (int) round($v) . '%';
    }

    /** Days the course lasts, from its duration fields (months, weeks, days or hours). */
    function sp_course_days(array $c): ?float
    {
        $v = course_duration_values($c);
        if ($v['months'] > 0) return $v['months'] * 30.4375;
        if ($v['weeks'] > 0) return $v['weeks'] * 7.0;
        if ($v['days'] > 0) return (float) $v['days'];
        if ($v['hours'] > 0) return 30.0;
        return null;
    }

    /** Where the student should be today, in percent, from the start date and the length of the course; null when either is unknown. */
    function sp_should_be(array $s, int $now): ?float
    {
        $days = sp_course_days($s);
        $start = !empty($s['start_date']) ? strtotime($s['start_date'] . ' 00:00:00 Africa/Nairobi') : false;
        if (!$days || !$start) return null;
        return max(0.0, min(100.0, ($now - $start) / 86400 / $days * 100));
    }

    /* ---------------- status word and comment, worked out live ---------------- */

    /**
     * @param array $m  days_since (int|null: days since the last hand-in, or since the start when nothing was ever handed in), never (bool),
     *                  missing (int), total (int: assignments given so far)
     * @return array{key:string, word:string, reasons:string[], action:string}
     */
    function sp_status(array $m, array $set): array
    {
        $days = $m['days_since'];
        $missing = (int) $m['missing'];
        if ((int) $m['total'] === 0) {
            return ['key' => 'good', 'word' => 'Working well', 'reasons' => ['No assignments have been given to this student yet.'], 'action' => 'Nothing to do yet. Check again once assignments are given.'];
        }
        $dayText = function (int $d): string { return $d === 0 ? 'today' : ($d === 1 ? '1 day ago' : $d . ' days ago'); };
        $reasons = [];
        if ($days !== null && $days >= $set['working_days']) {
            $reasons[] = !empty($m['never']) ? 'Nothing handed in yet, ' . $days . ' days since the start' : 'No work handed in for ' . $days . ' days';
        } elseif ($days !== null) {
            $reasons[] = 'Last work handed in ' . $dayText($days);
        }
        if ($missing > 0) $reasons[] = $missing . ($missing === 1 ? ' assignment is late' : ' assignments are late');

        $notWorking = ($days !== null && $days > $set['slowing_days']) || $missing >= $set['notworking_missing'];
        $slowing = ($days !== null && $days >= $set['working_days']) || $missing >= $set['slowing_missing'];
        if ($notWorking) return ['key' => 'bad', 'word' => 'Not working', 'reasons' => $reasons, 'action' => 'Contact the student today to find out what is stopping them, and agree a date for the work that is late.'];
        if ($slowing) return ['key' => 'slow', 'word' => 'Slowing down', 'reasons' => $reasons, 'action' => 'Send a friendly nudge and ask whether the student needs help to catch up.'];
        return ['key' => 'good', 'word' => 'Working well', 'reasons' => $reasons, 'action' => 'Keep it up. A short word of encouragement helps.'];
    }

    /* ---------------- the numbers for each student ---------------- */

    /**
     * Fill in the numbers for the listed students (from sp_students()). One query for all their marks.
     * $detail adds the per-subject lines and the monthly projects (the full card and the PDF); the overview does not need them.
     * @return array<int,array> user id => student row + metrics, status and (with $detail) subjects and projects
     */
    function sp_compute(PDO $db, array $students, bool $detail = false, ?int $now = null): array
    {
        $now = $now ?? time();
        $set = sp_settings($db);
        $rows = sp_rows($db, array_keys($students));
        $subjects = $detail ? sp_subjects_for($db, $students, $rows) : [];
        $out = [];
        foreach ($students as $uid => $s) {
            $cid = (int) $s['course_id'];
            $mine = array_values(array_filter($rows[$uid] ?? [], fn($r) => (int) $r['course_id'] === $cid));
            $given = array_values(array_filter($mine, fn($r) => $r['kind'] !== 'capstone'));
            $avg = AssignmentAverage::compute($mine, $now);
            $handed = $missing = $late = $notDue = 0;
            $last = null;
            foreach ($given as $r) {
                if (sp_handed($r)) { $handed++; if (!empty($r['is_late'])) $late++; }
                elseif (sp_missing($r, $now)) $missing++;
                else $notDue++;
            }
            foreach ($mine as $r) {   // the last hand-in counts the capstone too
                if (!empty($r['submitted_at']) && ($last === null || $r['submitted_at'] > $last)) $last = $r['submitted_at'];
            }
            $monthlyRows = array_values(array_filter($mine, fn($r) => $r['kind'] === 'monthly'));
            $proj = $monthlyRows ? AssignmentAverage::compute($monthlyRows, $now) : null;
            $startTs = !empty($s['start_date']) ? strtotime($s['start_date'] . ' 00:00:00 Africa/Nairobi') : false;
            $daysSince = $last !== null ? max(0, (int) floor(($now - strtotime($last . ' UTC')) / 86400)) : ($startTs ? max(0, (int) floor(($now - $startTs) / 86400)) : null);
            $m = [
                'average' => $avg['average'], 'handed' => $handed, 'total' => count($given), 'missing' => $missing, 'late' => $late, 'not_due' => $notDue,
                'finish_date' => ($sd = sp_course_days($s)) && $startTs ? gmdate('Y-m-d', (int) ($startTs + $sd * 86400)) : null,
                'monthly_marked' => $proj ? $proj['counted'] : 0, 'monthly_total' => count($monthlyRows),
                'project_average' => $proj['average'] ?? null, 'last_handin' => $last,
                'days_since' => $daysSince, 'never' => $last === null,
                'actual_pct' => count($given) ? $handed / count($given) * 100 : 0.0,
                'should_be' => sp_should_be($s, $now),
            ];
            $m['status'] = sp_status($m, $set);
            $card = $s + $m;
            if ($detail) {
                $card['subject_lines'] = sp_subject_lines($subjects[$uid] ?? [], $mine, $now);
                $card['projects'] = sp_project_lines($monthlyRows, $now);
                $card['recent'] = sp_recent_lines($given, $avg['items'], $now);
            }
            $out[$uid] = $card;
        }
        return $out;
    }

    /** Subject definitions that apply to each student (their tutors' subjects for their course, plus any subject their work uses). */
    function sp_subjects_for(PDO $db, array $students, array $rows): array
    {
        $courses = array_values(array_unique(array_map(fn($s) => (int) $s['course_id'], $students)));
        if (!$courses) return [];
        $in = implode(',', array_fill(0, count($courses), '?'));
        $st = $db->prepare("SELECT * FROM course_subjects WHERE course_id IN ($in) ORDER BY tutor_id, sort_order, id");
        $st->execute($courses);
        $all = $st->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($students as $uid => $s) {
            $used = [];
            foreach ($rows[$uid] ?? [] as $r) if (!empty($r['subject_id'])) $used[(int) $r['subject_id']] = true;
            foreach ($all as $sub) {
                if ((int) $sub['course_id'] !== (int) $s['course_id']) continue;
                if (in_array((int) $sub['tutor_id'], $s['tutor_ids'], true) || isset($used[(int) $sub['id']])) {
                    if (!(int) $sub['is_active'] && !isset($used[(int) $sub['id']])) continue;
                    $out[$uid][] = $sub;
                }
            }
        }
        return $out;
    }

    /** One line per subject. Subjects with no work handed in are folded into a count. */
    function sp_subject_lines(array $subjects, array $courseRows, int $now): array
    {
        $lines = [];
        $notStarted = 0;
        $known = [];
        foreach ($subjects as $sub) {
            $known[(int) $sub['id']] = true;
            $rows = array_values(array_filter($courseRows, fn($r) => (int) ($r['subject_id'] ?? 0) === (int) $sub['id']));
            $plain = array_values(array_filter($rows, fn($r) => $r['kind'] !== 'monthly'));
            $handed = count(array_filter($rows, 'sp_handed'));
            if ($handed === 0) { $notStarted++; continue; }
            $c = subj_compute($sub, $rows, $now);   // the course average service, per subject
            $mrows = array_values(array_filter($rows, fn($r) => $r['kind'] === 'monthly'));
            $mavg = $mrows ? AssignmentAverage::compute($mrows, $now)['average'] : null;
            $lines[] = [
                'name' => (string) $sub['name'], 'handed' => count(array_filter($plain, 'sp_handed')), 'total' => count($plain),
                'mark' => $c['mark'], 'text' => $c['mark'] === null ? 'no mark yet' : sp_pct($c['mark']),
                'word' => subj_result_word($c['mark']), 'project' => $mavg === null ? 'no mark yet' : sp_pct($mavg),
            ];
        }
        // Work with no subject (older assignments) is shown as one line, so nothing handed in is hidden.
        $loose = array_values(array_filter($courseRows, fn($r) => empty($r['subject_id']) && !in_array($r['kind'], ['capstone', 'monthly'], true)));
        if ($loose && count(array_filter($loose, 'sp_handed'))) {
            $c = AssignmentAverage::compute($loose, $now);
            $lines[] = ['name' => 'Other assignments', 'handed' => count(array_filter($loose, 'sp_handed')), 'total' => count($loose),
                        'mark' => $c['average'], 'text' => $c['average'] === null ? 'no mark yet' : sp_pct($c['average']),
                        'word' => subj_result_word($c['average']), 'project' => '-'];
        }
        return ['lines' => $lines, 'not_started' => $notStarted];
    }

    /** The latest assignments (newest due first) with where each one stands, for the card's Assignments list. */
    function sp_recent_lines(array $given, array $items, int $now, int $limit = 6): array
    {
        $by = [];
        foreach ($items as $it) $by[$it['id']] = $it;
        $rows = array_values(array_filter($given, fn($r) => $r['kind'] !== 'monthly'));
        usort($rows, fn($a, $b) => [$b['due'] ?? '0', (int) $b['id']] <=> [$a['due'] ?? '0', (int) $a['id']]);
        $out = [];
        foreach (array_slice($rows, 0, $limit) as $r) {
            $pct = $by[(int) $r['id']]['percent'] ?? null;
            if ($pct !== null) { $state = !empty($r['is_late']) ? 'late' : 'marked'; $text = sp_pct($pct); }
            elseif (sp_handed($r)) { $state = 'waiting'; $text = 'Handed in'; }
            elseif (sp_missing($r, $now)) { $state = 'missing'; $text = 'Missing'; }
            else { $state = 'later'; $text = 'Not yet due'; }
            $note = sp_handed($r) ? 'Handed in ' . sp_local($r['submitted_at'] ?? null, 'j M') . (!empty($r['is_late']) ? ' (late)' : '')
                : (!empty($r['due']) ? (sp_missing($r, $now) ? 'Was due ' : 'Due ') . sp_local($r['due'], 'j M') : '');
            $out[] = ['title' => (string) $r['title'], 'state' => $state, 'text' => $text, 'note' => $note];
        }
        return $out;
    }

    /** The monthly projects, oldest first, each with its mark or why there is none. */
    function sp_project_lines(array $monthlyRows, int $now): array
    {
        if (!$monthlyRows) return ['items' => [], 'average' => null];
        usort($monthlyRows, fn($a, $b) => [$a['due'] ?? '9999', (int) $a['id']] <=> [$b['due'] ?? '9999', (int) $b['id']]);
        $res = AssignmentAverage::compute($monthlyRows, $now);
        $byId = [];
        foreach ($res['items'] as $it) $byId[$it['id']] = $it;
        $items = [];
        foreach ($monthlyRows as $i => $r) {
            $it = $byId[(int) $r['id']] ?? null;
            if ($it && $it['percent'] !== null) { $text = sp_pct($it['percent']); $state = 'marked'; }
            elseif (sp_handed($r)) { $text = 'handed in, mark not out yet'; $state = 'waiting'; }
            elseif (sp_missing($r, $now)) { $text = 'not handed in'; $state = 'missing'; }
            else { $text = 'not due yet'; $state = 'later'; }
            $items[] = ['label' => 'Month ' . ($i + 1), 'title' => (string) $r['title'], 'text' => $text, 'state' => $state, 'percent' => $it['percent'] ?? null];
        }
        return ['items' => $items, 'average' => $res['average']];
    }

    /* ---------------- sorting and filtering the overview ---------------- */

    /** Needing attention first: Not working, Slowing down, Working well; then the most late work, then the longest quiet. */
    function sp_sort(array $cards, string $sort = 'attention'): array
    {
        $rank = ['bad' => 0, 'slow' => 1, 'good' => 2];
        uasort($cards, function ($a, $b) use ($rank, $sort) {
            if ($sort === 'name') return strcasecmp($a['full_name'], $b['full_name']);
            if ($sort === 'progress') return $a['actual_pct'] <=> $b['actual_pct'] ?: strcasecmp($a['full_name'], $b['full_name']);
            return [$rank[$a['status']['key']], -$a['missing'], -($a['days_since'] ?? 0), $a['full_name']] <=> [$rank[$b['status']['key']], -$b['missing'], -($b['days_since'] ?? 0), $b['full_name']];
        });
        return $cards;
    }

    /** Filter tabs: all | good | slow | bad. */
    function sp_filter_status(array $cards, string $tab): array
    {
        return in_array($tab, ['good', 'slow', 'bad'], true) ? array_filter($cards, fn($c) => $c['status']['key'] === $tab) : $cards;
    }

    /** The filtered, sorted list for the overview or "Download all" (same function, so both mean the same students). */
    function sp_list(PDO $db, array $viewer, array $f): array
    {
        $students = sp_students($db, $viewer, $f);
        $cards = sp_compute($db, $students, false);
        return sp_sort(sp_filter_status($cards, (string) ($f['tab'] ?? 'all')), (string) ($f['sort'] ?? 'attention'));
    }

    function sp_counts(array $cards): array
    {
        $n = ['all' => count($cards), 'good' => 0, 'slow' => 0, 'bad' => 0];
        foreach ($cards as $c) $n[$c['status']['key']]++;
        return $n;
    }

    function sp_course_options(PDO $db, array $viewer): array
    {
        $out = [];
        foreach (sp_students($db, $viewer) as $s) $out[(int) $s['course_id']] = $s['course_title'];
        asort($out);
        return $out;
    }

    function sp_tutor_options(PDO $db): array
    {
        $st = $db->query("SELECT t.id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))), ''), u.username) AS name
                          FROM tutors t JOIN users u ON u.id = t.user_id LEFT JOIN user_profiles up ON up.user_id = u.id WHERE t.is_active = 1 ORDER BY name");
        return $st->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /* ---------------- private notes ---------------- */

    /** Notes the viewer may read: admin all, a tutor only their own. */
    function sp_notes(PDO $db, array $viewer, int $studentId): array
    {
        try {
            $sql = 'SELECT id, author_user_id, author_name, note, created_at FROM progress_notes WHERE student_user_id = ?';
            $args = [$studentId];
            if ($viewer['role'] !== 'admin') { $sql .= ' AND author_user_id = ?'; $args[] = $viewer['uid']; }
            $st = $db->prepare($sql . ' ORDER BY created_at DESC, id DESC');
            $st->execute($args);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return array{ok:bool, error?:string} */
    function sp_add_note(PDO $db, array $viewer, int $studentId, string $text): array
    {
        $text = trim(str_replace("\r\n", "\n", $text));
        if ($text === '') return ['ok' => false, 'error' => 'Write the note first.'];
        if (mb_strlen($text) > 2000) return ['ok' => false, 'error' => 'A note can be up to 2000 characters.'];
        if (!sp_installed($db)) return ['ok' => false, 'error' => 'Run System Updates (migration 103) first.'];
        if (!sp_allowed_ids($db, $viewer, [$studentId])) return ['ok' => false, 'error' => 'That student is not on your list.'];
        $db->prepare('INSERT INTO progress_notes (student_user_id, author_user_id, author_name, note, created_at) VALUES (?, ?, ?, ?, ?)')
           ->execute([$studentId, $viewer['uid'], $viewer['name'], $text, gmdate('Y-m-d H:i:s')]);
        return ['ok' => true];
    }

    /* ---------------- nudge (only when a tutor presses the button) ---------------- */

    /** @return array{ok:bool, message:string} */
    function sp_nudge(PDO $db, array $viewer, int $studentId): array
    {
        if ($viewer['role'] !== 'tutor') return ['ok' => false, 'message' => 'Only the tutor of a student can send a nudge.'];
        if (!sp_installed($db)) return ['ok' => false, 'message' => 'Run System Updates (migration 103) first.'];
        $students = sp_students($db, $viewer, ['ids' => [$studentId]]);
        if (!$students) return ['ok' => false, 'message' => 'That student is not on your list.'];
        $card = sp_compute($db, $students, false)[$studentId];
        $first = trim(explode(' ', trim($card['full_name']))[0]) ?: 'there';
        $body = 'Hi ' . $first . ', ' . $viewer['name'] . ' is checking in on your progress.'
            . ($card['missing'] > 0 ? ' You have ' . $card['missing'] . ' assignment' . ($card['missing'] === 1 ? '' : 's') . ' still to hand in.' : '')
            . ' Please catch up when you can, and reply if you need help.';
        $choices = PortalNotifications::choices($db, $studentId);
        $category = PortalNotifications::categoryOf('assignment');
        $delivered = $choices === null || !empty($choices[$category]);   // the student's own notification choices still apply
        PortalNotifications::notify($db, $studentId, 'assignment', 'A message from your tutor', $body, 'students/assignments/index.php', PortalNotifications::photoOf($db, $viewer['uid']));
        $db->prepare('INSERT INTO progress_nudges (student_user_id, sent_by_user_id, sent_by_name, message, delivered, created_at) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$studentId, $viewer['uid'], $viewer['name'], mb_substr($body, 0, 500), $delivered ? 1 : 0, gmdate('Y-m-d H:i:s')]);
        return $delivered
            ? ['ok' => true, 'message' => 'Nudge sent to ' . $card['full_name'] . '.']
            : ['ok' => true, 'message' => 'Nudge recorded, but ' . $card['full_name'] . ' has switched off assignment notifications, so it will not show on their phone or bell.'];
    }

    /* ---------------- activity log ---------------- */

    function sp_log(PDO $db, array $viewer, string $action, array $studentIds): void
    {
        if (!sp_installed($db)) return;
        $ids = array_values(array_map('intval', $studentIds));
        $db->prepare('INSERT INTO progress_activity_log (actor_user_id, actor_name, actor_role, action, student_count, student_ids, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
           ->execute([$viewer['uid'], $viewer['name'], $viewer['role'], $action, count($ids), implode(',', $ids), gmdate('Y-m-d H:i:s')]);
    }

    /* ---------------- dates for display ---------------- */

    function sp_local(?string $utc, string $fmt = 'j M Y'): string
    {
        if (!$utc) return '';
        return (new DateTime($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format($fmt);
    }
}
