<?php
// app/v1/services/SelfStudyProgress.php — Self-study (step 7): how the students are doing on a course, and the reset requests.
//
// THE RULES, checked here on the server:
//  * Who sees whom: a tutor sees only their own students (the ones linked to them in My Students); the course OWNER sees every student on the course, with who added them;
//    admin sees everyone; staff and students cannot open the page (sst_viewer() returns nobody for them).
//  * The system comment (Working well / Slowing down / Not working) is worked out live every time, from the numbers in progress_settings (the same working days 7 and
//    not-working days 14 as Student Progress, plus the failed-tries-in-a-row number):
//      Working well  studied in the last 7 days and moving forward
//      Slowing down  nothing studied for 7 to 14 days, OR two failed tries in a row on the same quiz or exam
//      Not working   nothing studied for more than 14 days, OR every try of the week used up and failed
//      Not started yet   added less than 7 days ago and not opened: no warning colour
//  * A reset only clears the tries counted in the current day, week or month for that one quiz or exam. Marks and passed items are never touched.
//    A tutor may reset one student twice a month for module quizzes and twice a month for exams; after that only admin can. Every reset is a record: who, when, why.
//  * Everything is loaded in a few queries (students, progress, attempts), never one per student.
require_once __DIR__ . '/SelfStudyAccess.php';

if (!function_exists('ssd_overview')) {

    define('SSD_DEFAULTS', ['failed_in_row' => 2, 'reset_quiz_month' => 2, 'reset_exam_month' => 2]);
    define('SSD_ADMIN_SHAPE', ['role' => 'admin', 'tutor_id' => null, 'uid' => 0, 'name' => '']);

    /** The numbers behind the comment: working_days (7) and slowing_days (14) are the Student Progress ones; the rest are saved by admin Settings > Self-study. */
    function ssd_settings(PDO $db): array
    {
        $sp = sp_settings($db);
        $s = ['working_days' => (int) $sp['working_days'], 'slowing_days' => (int) $sp['slowing_days']] + SSD_DEFAULTS;
        try {
            foreach ($db->query("SELECT setting_key, setting_value FROM progress_settings WHERE setting_key LIKE 'ss\\_%'")->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
                $k = substr((string) $k, 3);
                if (isset(SSD_DEFAULTS[$k]) && is_numeric($v) && (int) $v >= 0) $s[$k] = (int) $v;
            }
        } catch (Throwable $e) { /* defaults */ }
        return $s;
    }

    /** @return array{ok:bool,error?:string} */
    function ssd_settings_save(PDO $db, array $in, int $uid): array
    {
        $new = [];
        foreach (SSD_DEFAULTS as $k => $_) {
            $v = trim((string) ($in[$k] ?? ''));
            if ($v === '' || !ctype_digit($v) || (int) $v < 1 || (int) $v > 30) return ['ok' => false, 'error' => 'Every number must be a whole number from 1 to 30.'];
            $new[$k] = (int) $v;
        }
        $up = $db->prepare('INSERT INTO progress_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)');
        foreach ($new as $k => $v) $up->execute(['ss_' . $k, (string) $v, $uid, gmdate('Y-m-d H:i:s')]);
        return ['ok' => true];
    }

    /** The cron job (app/v1/tools/selfstudy_expire.php) leaves its last run time here, so admin can see it is working. Saved next to the other self-study numbers. */
    function ssd_cron_beat(PDO $db, ?int $now = null): void
    {
        $now ??= time();
        $db->prepare('INSERT INTO progress_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, NULL, ?)
                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)')->execute(['ss_cron_last_run', (string) $now, gmdate('Y-m-d H:i:s', $now)]);
    }

    function ssd_cron_last(PDO $db): ?int
    {
        try { $v = $db->prepare("SELECT setting_value FROM progress_settings WHERE setting_key = 'ss_cron_last_run'"); $v->execute(); $x = $v->fetchColumn(); return $x !== false && ctype_digit((string) $x) ? (int) $x : null; }
        catch (Throwable $e) { return null; }
    }

    /** @return array{last:?int, late:bool} late = it has not run in the last 10 minutes (or never) */
    function ssd_cron_status(PDO $db, ?int $now = null): array
    {
        $now ??= time(); $last = ssd_cron_last($db);
        return ['last' => $last, 'late' => $last === null || $now - $last > 600];
    }

    function ssd_category(string $kind): string { return $kind === 'module_quiz' ? 'quiz' : 'exam'; }

    /** Days between two moments, counted in Nairobi days (so "yesterday" means yesterday there). */
    function ssd_days_since(int $then, int $now): int
    {
        $tz = new DateTimeZone('Africa/Nairobi');
        $a = (new DateTime('@' . $then))->setTimezone($tz)->setTime(0, 0); $b = (new DateTime('@' . $now))->setTimezone($tz)->setTime(0, 0);
        return max(0, (int) $a->diff($b)->days);
    }

    function ssd_when(?int $ts, int $now): string
    {
        if ($ts === null) return 'Not yet';
        $d = ssd_days_since($ts, $now);
        return $d === 0 ? 'Today' : ($d === 1 ? 'Yesterday' : ($d < 30 ? $d . ' days ago' : (new DateTime('@' . $ts))->setTimezone(new DateTimeZone('Africa/Nairobi'))->format('j M Y')));
    }

    /**
     * One student's status word and its short reason. $items = per quiz / exam: ['label','passed','failed','run','blocked','topic'].
     * @return array{key:string, word:string, reason:string}
     */
    function ssd_status(array $s, array $set, array $items, int $now): array
    {
        if (!empty($s['completed'])) return ['key' => 'finished', 'word' => 'Finished', 'reason' => 'Passed the Final Exam.'];
        $help = fn(array $it) => $it['topic'] !== '' ? ' May need help with topic ' . $it['topic'] . '.' : '';
        $times = fn(int $n) => $n === 1 ? 'once' : $n . ' times';
        $blocked = null; $stuck = null;
        foreach ($items as $it) {
            if ($it['passed']) continue;
            if ($it['blocked'] && ($blocked === null || $it['failed'] > $blocked['failed'])) $blocked = $it;
            if ($it['run'] >= $set['failed_in_row'] && ($stuck === null || $it['run'] > $stuck['run'])) $stuck = $it;
        }
        $last = $s['last_ts'];
        if ($last === null) {                                                     // never opened
            $age = ssd_days_since($s['added_ts'], $now);
            if ($age < $set['working_days']) return ['key' => 'notstarted', 'word' => 'Not started yet', 'reason' => 'Added ' . ($age === 0 ? 'today' : ($age === 1 ? 'yesterday' : $age . ' days ago')) . '.'];
            if ($age > $set['slowing_days']) return ['key' => 'notworking', 'word' => 'Not working', 'reason' => 'Has not opened the course in ' . $age . ' days.'];
            return ['key' => 'slowing', 'word' => 'Slowing down', 'reason' => 'Has not opened the course in ' . $age . ' days.'];
        }
        $idle = ssd_days_since($last, $now);
        if ($idle > $set['slowing_days']) return ['key' => 'notworking', 'word' => 'Not working', 'reason' => 'No study for ' . $idle . ' days.'];
        if ($blocked) return ['key' => 'notworking', 'word' => 'Not working', 'reason' => 'Used every try on the ' . $blocked['label'] . ' and failed (' . $blocked['failed'] . ' ' . ($blocked['failed'] === 1 ? 'try' : 'tries') . ').' . $help($blocked)];
        if ($stuck) return ['key' => 'slowing', 'word' => 'Slowing down', 'reason' => 'Failed the ' . $stuck['label'] . ' ' . $times($stuck['failed']) . '.' . $help($stuck)];
        if ($idle >= $set['working_days']) return ['key' => 'slowing', 'word' => 'Slowing down', 'reason' => 'Nothing studied for ' . $idle . ' days.'];
        $passed = count(array_filter($items, fn($i) => $i['passed']));
        return ['key' => 'working', 'word' => 'Working well', 'reason' => ($idle === 0 ? 'Studied today. ' : 'Studied ' . ($idle === 1 ? 'yesterday' : $idle . ' days ago') . '. ') . ($passed ? 'Moving forward. Passed ' . ($passed === 1 ? 'a quiz' : $passed . ' quizzes and exams') . ' so far.' : 'Moving forward.')];
    }

    /** What the person looking is allowed to do with this student. */
    function ssd_can_act(PDO $db, array $viewer, array $course, int $studentId): bool
    {
        if (!ss2_rows($db, 'SELECT id FROM self_study_access WHERE course_id = ? AND student_id = ? AND removed_at IS NULL', [(int) $course['id'], $studentId])) return false;
        if ($viewer['role'] === 'admin' || ($course['access'] ?? '') === 'owner') return true;
        return (bool) ss2_rows($db, 'SELECT 1 AS x FROM tutor_students WHERE tutor_id = ? AND user_id = ?', [(int) ($viewer['tutor_id'] ?? 0), $studentId]);
    }

    function ssd_guard(PDO $db, array $viewer, int $courseId): array
    {
        $c = sst_course($db, $viewer, $courseId);
        if (!$c) throw new SscException('That course was not found, or it was not shared with you.', 404);
        return $c;
    }

    /** How many resets this tutor already did for each student this month, by category: [student][quiz|exam] => n. */
    function ssd_usage(PDO $db, int $userId, int $now): array
    {
        $from = ss2_ts(ss3_windows($now)['month'][0]); $out = [];
        foreach (ss2_rows($db, 'SELECT student_id, kind, COUNT(*) AS n FROM self_study_resets WHERE reset_by = ? AND created_at >= ? GROUP BY student_id, kind', [$userId, $from]) as $r) {
            $cat = ssd_category($r['kind']); $out[(int) $r['student_id']][$cat] = ($out[(int) $r['student_id']][$cat] ?? 0) + (int) $r['n'];
        }
        return $out;
    }

    function ssd_item_label(array $outline, string $kind, ?int $moduleId): string
    {
        if ($kind === 'checkpoint_exam') return 'Checkpoint Exam';
        if ($kind === 'final_exam') return 'Final Exam';
        foreach ($outline['modules'] as $m) if ($m['id'] === (int) $moduleId) return 'Module ' . $m['no'] . ' quiz';
        return 'Quiz';
    }

    /**
     * The state of each quiz and exam of ONE student on ONE course, from their attempts: passed, failed tries, failed in a row, all tries used, the topic to read again.
     * Shared by the Students page and the Student Progress card, so both give the same comment.
     * @param array $att   attempts by item key ('module_quiz:<id>', 'checkpoint_exam', 'final_exam')
     * @param array $wrong wrong-answer counts by item key and topic id
     */
    function ssd_items_for(array $att, array $wrong, array $itemKeys, array $topicNo, array $win): array
    {
        $items = [];
        foreach ($itemKeys as $key => $meta) {
            $list = $att[$key] ?? []; if (!$list) continue;
            $passed = (bool) array_filter($list, fn($a) => (int) $a['passed'] === 1);
            $failed = count(array_filter($list, fn($a) => in_array($a['status'], ['submitted', 'timed_out'], true) && (int) $a['passed'] === 0));
            $run = 0; for ($i = count($list) - 1; $i >= 0; $i--) { if ($list[$i]['status'] === 'in_progress') continue; if ((int) $list[$i]['passed'] === 1) break; $run++; }
            $info2 = SS3_KINDS[$meta['kind']]; $counted = array_filter($list, fn($a) => $a['tries_cleared_at'] === null);
            $inWin = function (string $w) use ($counted, $win) { return count(array_filter($counted, function ($a) use ($w, $win) { $t = ss2_parse($a['started_at']); return $t >= $win[$w][0] && $t < $win[$w][1]; })); };
            $blocked = false;
            foreach ($info2['limits'] as $w => $lim) if ($inWin($w) >= $lim) $blocked = true;
            $tc = $wrong[$key] ?? []; arsort($tc); $topic = $tc ? ($topicNo[(int) array_key_first($tc)] ?? '') : '';
            $items[$key] = ['label' => $meta['label'], 'passed' => $passed, 'failed' => $failed, 'run' => $passed ? 0 : $run, 'blocked' => !$passed && $blocked && $failed > 0, 'topic' => $topic,
                            'week' => $inWin('week'), 'day' => $inWin('day'), 'month' => $inWin('month')];
        }
        return $items;
    }

    /**
     * The "Self-study" section of the Student Progress card: one row per self-study course, for ALL these students in a handful of queries (never one per student).
     * Visibility follows the card: admin sees every self-study course the student is on; a tutor only the courses they own, were given access to, or added the student to.
     * Only published courses the student can actually see are listed. Read-only: nothing here changes a mark or the course average.
     * @param int[] $studentIds
     * @return array<int,array[]> student id => rows (course title and picture, modules passed, percent, latest quiz, exams, system comment)
     */
    function ssd_cards(PDO $db, array $viewer, array $studentIds, ?int $now = null): array
    {
        $now ??= time();
        $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds), fn($i) => $i > 0)));
        if (!$studentIds || !in_array($viewer['role'] ?? '', ['admin', 'tutor'], true)) return [];
        try {
            $in = implode(',', $studentIds); $isAdmin = $viewer['role'] === 'admin'; $me = (int) ($viewer['tutor_id'] ?? 0);
            $sql = "SELECT a.student_id, a.course_id, a.added_at, a.added_by_tutor_id, c.title, c.owner_tutor_id, c.cover_original, c.cover_400, c.cover_800, c.cover_1600,
                           p.percent_complete, p.last_studied_at, p.completed_at, e.id AS editor_id
                    FROM self_study_access a JOIN self_study_courses c ON c.id = a.course_id
                    LEFT JOIN self_study_progress p ON p.course_id = a.course_id AND p.student_id = a.student_id
                    LEFT JOIN self_study_course_editors e ON e.course_id = c.id AND e.tutor_id = ?
                    WHERE a.student_id IN ($in) AND a.removed_at IS NULL AND c.status = 'published'";
            $args = [$me];
            if (!$isAdmin) { $sql .= ' AND (c.owner_tutor_id = ? OR e.id IS NOT NULL OR a.added_by_tutor_id = ?)'; array_push($args, $me, $me); }
            $base = ss2_rows($db, $sql . ' ORDER BY c.title, c.id', $args);
        } catch (Throwable $e) { return []; }          // the self-study tables are not installed yet: the section stays hidden
        if (!$base) return [];
        $courseIds = array_values(array_unique(array_map(fn($r) => (int) $r['course_id'], $base))); $cin = implode(',', $courseIds);
        $win = ss3_windows($now); $set = ssd_settings($db);

        // the structure of every course involved (modules in order, topics in order), then all the students' progress on them
        $mods = []; $topicsOf = []; $topicNo = []; $quizQ = [];
        foreach (ss2_rows($db, "SELECT id, course_id FROM self_study_modules WHERE course_id IN ($cin) AND archived_at IS NULL ORDER BY position, id") as $m) $mods[(int) $m['course_id']][] = (int) $m['id'];
        foreach (ss2_rows($db, "SELECT id, course_id, module_id FROM self_study_topics WHERE course_id IN ($cin) AND archived_at IS NULL AND is_intro = 0 ORDER BY position, id") as $t) $topicsOf[(int) $t['course_id']][(int) $t['module_id']][] = (int) $t['id'];
        foreach ($mods as $c => $list) foreach ($list as $mi => $mid) foreach ($topicsOf[$c][$mid] ?? [] as $ti => $tid) $topicNo[$c][$tid] = ($mi + 1) . '.' . $ti;
        foreach (ss2_rows($db, "SELECT course_id, module_id, COUNT(*) AS n FROM self_study_questions WHERE course_id IN ($cin) AND place = 'module_quiz' AND archived_at IS NULL GROUP BY course_id, module_id") as $r) $quizQ[(int) $r['course_id']][(int) $r['module_id']] = (int) $r['n'];
        $done = $att = $wrong = [];
        foreach (ss2_rows($db, "SELECT student_id, course_id, topic_id FROM self_study_topic_progress WHERE student_id IN ($in) AND course_id IN ($cin) AND done_at IS NOT NULL") as $r) $done[(int) $r['student_id']][(int) $r['course_id']][(int) $r['topic_id']] = true;
        foreach (ss2_rows($db, "SELECT id, student_id, course_id, kind, module_id, passed, status, score_percent, started_at, tries_cleared_at FROM self_study_attempts WHERE student_id IN ($in) AND course_id IN ($cin) ORDER BY id") as $r)
            $att[(int) $r['student_id']][(int) $r['course_id']][$r['kind'] === 'module_quiz' ? 'module_quiz:' . (int) $r['module_id'] : $r['kind']][] = $r;
        foreach (ss2_rows($db, "SELECT at.student_id, at.course_id, at.kind, at.module_id, q.topic_id, COUNT(*) AS n FROM self_study_attempts at
                                JOIN self_study_attempt_answers aa ON aa.attempt_id = at.id AND aa.is_correct = 0 JOIN self_study_questions q ON q.id = aa.question_id
                                WHERE at.student_id IN ($in) AND at.course_id IN ($cin) AND at.passed = 0 AND at.status IN ('submitted', 'timed_out') AND q.topic_id IS NOT NULL
                                GROUP BY at.student_id, at.course_id, at.kind, at.module_id, q.topic_id") as $r)
            $wrong[(int) $r['student_id']][(int) $r['course_id']][$r['kind'] === 'module_quiz' ? 'module_quiz:' . (int) $r['module_id'] : $r['kind']][(int) $r['topic_id']] = (int) $r['n'];

        $out = [];
        foreach ($base as $b) {
            $sid = (int) $b['student_id']; $cid = (int) $b['course_id']; $last = ss2_parse($b['last_studied_at']);
            $itemKeys = [];
            foreach ($mods[$cid] ?? [] as $i => $mid) $itemKeys['module_quiz:' . $mid] = ['label' => 'Module ' . ($i + 1) . ' quiz', 'kind' => 'module_quiz'];
            $itemKeys['checkpoint_exam'] = ['label' => 'Checkpoint Exam', 'kind' => 'checkpoint_exam']; $itemKeys['final_exam'] = ['label' => 'Final Exam', 'kind' => 'final_exam'];
            $myAtt = $att[$sid][$cid] ?? [];
            $items = ssd_items_for($myAtt, $wrong[$sid][$cid] ?? [], $itemKeys, $topicNo[$cid] ?? [], $win);
            $nMods = count($mods[$cid] ?? []); $passedMods = 0; $allPassed = $nMods > 0;
            foreach ($mods[$cid] ?? [] as $mid) {
                $topics = $topicsOf[$cid][$mid] ?? []; $topicsDone = !array_filter($topics, fn($t) => empty($done[$sid][$cid][$t]));
                $ok = !empty($items['module_quiz:' . $mid]['passed']);
                if ($ok) $passedMods++; else $allPassed = false;
            }
            $isDone = $b['completed_at'] !== null;
            $best = function (string $key) use ($myAtt) { $m = null; foreach ($myAtt[$key] ?? [] as $a) if (in_array($a['status'], ['submitted', 'timed_out'], true)) $m = max($m ?? 0, (int) $a['score_percent']); return $m; };
            $quizAtt = []; foreach ($myAtt as $k => $list) if (str_starts_with($k, 'module_quiz:')) foreach ($list as $a) if (in_array($a['status'], ['submitted', 'timed_out'], true)) $quizAtt[] = $a;
            usort($quizAtt, fn($x, $y) => (int) $x['id'] <=> (int) $y['id']);
            $latest = $quizAtt ? (int) end($quizAtt)['score_percent'] : null;
            $cp = $best('checkpoint_exam'); $fn = $best('final_exam'); $cpPassed = !empty($items['checkpoint_exam']['passed']);
            if ($isDone && $cp === null && $fn === null) $exams = 'Completed';
            elseif ($isDone || $fn !== null) $exams = 'Checkpoint ' . ($cp ?? '—') . ($cp !== null ? '%' : '') . ' · Final ' . ($fn !== null ? $fn . '%' : 'not yet taken');
            elseif ($cp !== null) $exams = 'Checkpoint ' . $cp . '%' . ($cpPassed ? ' · Final not yet taken' : ' (not passed yet)');
            elseif ($allPassed) $exams = 'Checkpoint not yet taken';
            else $exams = 'Not yet open';
            $st = ssd_status(['completed' => $isDone, 'last_ts' => $last, 'added_ts' => ss2_parse($b['added_at']) ?? $now], $set, $items, $now);
            if ($st['key'] === 'finished') $st = ['key' => 'working', 'word' => 'Working well', 'reason' => 'Completed badge earned.'];
            $linkable = $isAdminRow = ($viewer['role'] === 'admin') || (int) $b['owner_tutor_id'] === (int) ($viewer['tutor_id'] ?? -1) || $b['editor_id'] !== null;
            $out[$sid][] = ['course_id' => $cid, 'title' => $b['title'], 'cover' => ['cover_original' => $b['cover_original'], 'cover_400' => $b['cover_400'], 'cover_800' => $b['cover_800'], 'cover_1600' => $b['cover_1600']],
                            'modules_passed' => $passedMods, 'modules_total' => $nMods, 'percent' => $isDone ? 100 : (int) ($b['percent_complete'] ?? 0), 'latest_quiz' => $latest, 'exams' => $exams, 'status' => $st, 'can_open' => (bool) $linkable];
        }
        return $out;
    }

    /**
     * The whole page: numbers, rows and requests. A few queries for everyone, never one per student.
     * @return array<string,mixed>
     */
    function ssd_overview(PDO $db, array $viewer, int $courseId, ?int $now = null): array
    {
        $now ??= time();
        $c = ssd_guard($db, $viewer, $courseId);
        $all = $viewer['role'] === 'admin' || $c['access'] === 'owner';
        $set = ssd_settings($db); $win = ss3_windows($now);
        $sql = 'SELECT a.student_id, a.added_at, a.added_by_tutor_id, p.percent_complete, p.last_studied_at, p.completed_at FROM self_study_access a
                LEFT JOIN self_study_progress p ON p.course_id = a.course_id AND p.student_id = a.student_id WHERE a.course_id = ? AND a.removed_at IS NULL';
        $args = [$courseId];
        if (!$all) { $sql .= ' AND EXISTS (SELECT 1 FROM tutor_students ts WHERE ts.user_id = a.student_id AND ts.tutor_id = ?)'; $args[] = (int) $viewer['tutor_id']; }
        $base = ss2_rows($db, $sql, $args);
        $ids = array_map(fn($r) => (int) $r['student_id'], $base);
        $info = [];
        foreach (array_chunk($ids, 400) as $ch) foreach (sp_students($db, SSD_ADMIN_SHAPE, ['ids' => $ch]) as $id => $s) $info[$id] = $s;
        $outline = ssc_outline($db, $courseId);
        $mods = $outline['modules']; $nMods = count($mods);
        $topicNo = []; foreach ($mods as $m) foreach ($m['topics'] as $t) $topicNo[$t['id']] = $t['number'];
        $adder = $all ? ssa_tutor_names($db, array_map(fn($r) => (int) ($r['added_by_tutor_id'] ?? 0), $base)) : [];

        $done = []; $att = []; $wrong = []; $long = [];
        if ($ids) {
            foreach (ss2_rows($db, 'SELECT student_id, topic_id FROM self_study_topic_progress WHERE course_id = ? AND done_at IS NOT NULL', [$courseId]) as $r) $done[(int) $r['student_id']][(int) $r['topic_id']] = true;
            foreach (ss2_rows($db, 'SELECT student_id, kind, module_id, passed, status, score_percent, started_at, tries_cleared_at FROM self_study_attempts WHERE course_id = ? ORDER BY id', [$courseId]) as $r)
                $att[(int) $r['student_id']][$r['kind'] === 'module_quiz' ? 'module_quiz:' . (int) $r['module_id'] : $r['kind']][] = $r;
            foreach (ss2_rows($db, "SELECT at.student_id, at.kind, at.module_id, q.topic_id, COUNT(*) AS n FROM self_study_attempts at
                                    JOIN self_study_attempt_answers aa ON aa.attempt_id = at.id AND aa.is_correct = 0 JOIN self_study_questions q ON q.id = aa.question_id
                                    WHERE at.course_id = ? AND at.passed = 0 AND at.status IN ('submitted', 'timed_out') AND q.topic_id IS NOT NULL GROUP BY at.student_id, at.kind, at.module_id, q.topic_id", [$courseId]) as $r)
                $wrong[(int) $r['student_id']][$r['kind'] === 'module_quiz' ? 'module_quiz:' . (int) $r['module_id'] : $r['kind']][(int) $r['topic_id']] = (int) $r['n'];
            foreach (ss2_rows($db, "SELECT a.student_id, COUNT(*) AS n FROM self_study_answers a JOIN self_study_questions q ON q.id = a.question_id AND q.qtype = 'long_answer' WHERE a.course_id = ? AND a.marked_at IS NULL GROUP BY a.student_id", [$courseId]) as $r) $long[(int) $r['student_id']] = (int) $r['n'];
        }
        $itemKeys = [];
        foreach ($mods as $m) $itemKeys['module_quiz:' . $m['id']] = ['label' => 'Module ' . $m['no'] . ' quiz', 'kind' => 'module_quiz'];
        $itemKeys['checkpoint_exam'] = ['label' => 'Checkpoint Exam', 'kind' => 'checkpoint_exam']; $itemKeys['final_exam'] = ['label' => 'Final Exam', 'kind' => 'final_exam'];

        $rows = []; $courses = []; $started = 0; $sumPct = 0; $finished = 0; $itemsBy = [];
        foreach ($base as $b) {
            $sid = (int) $b['student_id']; $inf = $info[$sid] ?? null; $last = ss2_parse($b['last_studied_at']); $addedTs = ss2_parse($b['added_at']) ?? $now;
            $items = ssd_items_for($att[$sid] ?? [], $wrong[$sid] ?? [], $itemKeys, $topicNo, $win);
            // "Module k of N": the first module the student has not finished (every topic done and its quiz passed)
            $cur = $nMods;
            foreach ($mods as $i => $m) {
                $topicsDone = !array_filter($m['topics'], fn($t) => empty($done[$sid][$t['id']]));
                $quizOk = !empty($items['module_quiz:' . $m['id']]['passed']);
                if (!($topicsDone && $quizOk)) { $cur = $i + 1; break; }
            }
            $isDone = $b['completed_at'] !== null;
            $s = ['completed' => $isDone, 'last_ts' => $last, 'added_ts' => $addedTs];
            $st = ssd_status($s, $set, $items, $now);
            $pct = $isDone ? 100 : (int) ($b['percent_complete'] ?? 0);
            if ($last !== null) $started++; if ($isDone) $finished++; $sumPct += $pct;
            $cid2 = (int) ($inf['course_id'] ?? 0); $courses[$cid2] = true;
            $name = $inf['full_name'] ?? ss3_student_name($db, $sid);
            $rows[] = ['id' => $sid, 'name' => $name, 'number' => $inf['registration_no'] ?? '', 'course' => $inf['course_title'] ?? '', 'initials' => sp_initials($name), 'photo' => $inf['photo']['url'] ?? null,
                       'percent' => $pct, 'module' => $nMods ? ($isDone ? 'Finished' : 'Module ' . min($cur, $nMods) . ' of ' . $nMods) : '', 'last' => ssd_when($last, $now), 'status' => $st,
                       'added_by' => $all ? (($tid = (int) ($b['added_by_tutor_id'] ?? 0)) ? ($adder[$tid] ?? 'a tutor') : 'Admin') : '', 'long' => $long[$sid] ?? 0, 'items' => $items];
            $itemsBy[$sid] = $items;
        }
        usort($rows, fn($a, $b) => [$b['percent'], $a['name']] <=> [$a['percent'], $b['name']]);
        $n = count($rows);
        $counts = ['all' => $n, 'slowing' => 0, 'notworking' => 0];
        foreach ($rows as $r) if (isset($counts[$r['status']['key']])) $counts[$r['status']['key']]++;
        $summary = ['students' => $n, 'courses' => count(array_filter(array_keys($courses))), 'started' => $started, 'not_opened' => $n - $started, 'average' => $n ? (int) round($sumPct / $n) : 0, 'finished' => $finished];

        // reset requests of the students this person may act on
        $visible = array_flip($ids); $usage = $viewer['role'] === 'admin' ? [] : ssd_usage($db, (int) $viewer['uid'], $now);
        $reqs = [];
        foreach (ss2_rows($db, "SELECT id, student_id, kind, module_id, reason, created_at FROM self_study_reset_requests WHERE course_id = ? AND status = 'waiting' ORDER BY created_at, id", [$courseId]) as $q) {
            $sid = (int) $q['student_id']; if (!isset($visible[$sid])) continue;
            $cat = ssd_category($q['kind']); $it = $itemsBy[$sid][$q['kind'] === 'module_quiz' ? 'module_quiz:' . (int) $q['module_id'] : $q['kind']] ?? null; $lim = $set['reset_' . $cat . '_month'];
            $used = (int) ($usage[$sid][$cat] ?? 0); $admin = $viewer['role'] === 'admin';
            $tries = $q['kind'] === 'module_quiz' ? ($it['week'] ?? 0) . ' of ' . SS3_KINDS['module_quiz']['limits']['week'] . ' tries used this week' : ($it['day'] ?? 0) . ' of ' . SS3_KINDS[$q['kind']]['limits']['day'] . ' tries used today';
            $row = array_values(array_filter($rows, fn($r) => $r['id'] === $sid))[0] ?? null;
            $reqs[] = ['id' => (int) $q['id'], 'student_id' => $sid, 'student' => $row['name'] ?? ss3_student_name($db, $sid), 'initials' => $row['initials'] ?? '?', 'photo' => $row['photo'] ?? null, 'kind' => $q['kind'], 'module_id' => $q['module_id'] !== null ? (int) $q['module_id'] : null,
                       'item' => ssd_item_label($outline, $q['kind'], $q['module_id'] !== null ? (int) $q['module_id'] : null), 'tries' => $tries, 'reason' => (string) $q['reason'], 'category' => $cat,
                       'limit_text' => $admin ? 'Admin: no monthly limit' : $used . ' of ' . $lim . ' used this month', 'can_reset' => $admin || $used < $lim];
        }
        $mine = ['quiz' => array_sum(array_map(fn($u) => $u['quiz'] ?? 0, $usage)), 'exam' => array_sum(array_map(fn($u) => $u['exam'] ?? 0, $usage))];
        return ['course' => $c, 'all' => $all, 'summary' => $summary, 'rows' => $rows, 'counts' => $counts, 'requests' => $reqs, 'mine' => $mine, 'settings' => $set, 'outline' => $outline, 'items' => array_map(fn($k, $v) => ['key' => $k] + $v, array_keys($itemKeys), $itemKeys)];
    }

    /** The small panel for one student: modules and quiz scores, exams, tries, long answers waiting, reset history. */
    function ssd_student_detail(PDO $db, array $viewer, int $courseId, int $studentId, ?int $now = null): array
    {
        $now ??= time();
        $c = ssd_guard($db, $viewer, $courseId);
        if (!ssd_can_act($db, $viewer, $c, $studentId)) throw new SscException('That student was not found on this course, or is not one of your students.', 404);
        $outline = ssc_outline($db, $courseId); $m = ss2_model($db, $courseId, $studentId);
        $mods = [];
        foreach ($m['modules'] as $mod) {
            $tries = ss3_tries($db, $studentId, $courseId, 'module_quiz', $mod['id'], $now);
            $mods[] = ['title' => $mod['title'], 'topics_done' => count(array_filter($mod['topics'], fn($t) => $t['done'])), 'topics' => count($mod['topics']), 'percent' => $mod['percent'],
                       'quiz' => ['passed' => $mod['quiz']['passed'], 'best' => $mod['quiz']['best'], 'attempts' => $mod['quiz']['tries'], 'week_used' => $tries['windows']['week']['used'], 'week_limit' => $tries['windows']['week']['limit'], 'open' => $mod['quiz']['open'], 'label' => 'Module ' . $mod['no'] . ' quiz', 'id' => $mod['id']]];
        }
        $exams = [];
        foreach ([['checkpoint_exam', 'Checkpoint Exam', $m['checkpoint']], ['final_exam', 'Final Exam', $m['final']]] as [$k, $label, $x]) {
            $t = ss3_tries($db, $studentId, $courseId, $k, null, $now);
            $exams[] = ['kind' => $k, 'label' => $label, 'passed' => $x['passed'], 'best' => $x['best'], 'attempts' => $x['tries'], 'open' => $x['open'], 'day_used' => $t['windows']['day']['used'], 'day_limit' => $t['windows']['day']['limit'], 'month_used' => $t['windows']['month']['used'], 'month_limit' => $t['windows']['month']['limit']];
        }
        $longRows = ss2_rows($db, "SELECT a.id, a.answer, a.answered_at, q.question_text FROM self_study_answers a JOIN self_study_questions q ON q.id = a.question_id AND q.qtype = 'long_answer' WHERE a.course_id = ? AND a.student_id = ? AND a.marked_at IS NULL ORDER BY a.id DESC LIMIT 20", [$courseId, $studentId]);
        $res = ss2_rows($db, 'SELECT r.created_at, r.kind, r.module_id, r.reason, r.note, r.tries_cleared, r.reset_by FROM self_study_resets r WHERE r.course_id = ? AND r.student_id = ? ORDER BY r.id DESC LIMIT 50', [$courseId, $studentId]);
        $by = ss3_student_names($db, array_column($res, 'reset_by'));
        $history = array_map(fn($r) => ['when' => ss2_parse($r['created_at']), 'item' => ssd_item_label($outline, $r['kind'], $r['module_id'] !== null ? (int) $r['module_id'] : null), 'by' => $by[(int) $r['reset_by']] ?? '', 'reason' => $r['reason'], 'note' => $r['note'], 'cleared' => (int) $r['tries_cleared']], $res);
        return ['student' => ['id' => $studentId, 'name' => ss3_student_name($db, $studentId), 'percent' => $m['percent']], 'modules' => $mods, 'exams' => $exams, 'long' => array_map(fn($r) => ['question' => $r['question_text'], 'answer' => $r['answer'], 'at' => ss2_parse($r['answered_at'])], $longRows), 'history' => $history,
                'questions_url' => true];
    }

    /**
     * Reset the tries of ONE quiz or exam for ONE student. From a request ($requestId) or started by the tutor ($studentId, $kind, $moduleId, $reason).
     * Only the tries counted in the current day, week or month are cleared; marks and passed items are never touched. Writes a record and tells the student.
     * A tutor: two resets per student a month for module quizzes and two for exams; after that only admin.
     * @return array{ok:true, cleared:int, window:string}
     */
    function ssd_reset(PDO $db, array $viewer, int $courseId, ?int $requestId, ?int $studentId, string $kind, ?int $moduleId, string $reason, string $note = '', ?int $now = null): array
    {
        $now ??= time();
        $c = ssd_guard($db, $viewer, $courseId);
        if ($requestId) {
            $q = ss2_rows($db, "SELECT id, student_id, kind, module_id, reason FROM self_study_reset_requests WHERE id = ? AND course_id = ? AND status = 'waiting'", [$requestId, $courseId])[0] ?? null;
            if (!$q) throw new SscException('That request was not found, or it was already handled.', 404);
            $studentId = (int) $q['student_id']; $kind = $q['kind']; $moduleId = $q['module_id'] !== null ? (int) $q['module_id'] : null; $reason = (string) $q['reason'];
        } else {
            $reason = trim(preg_replace('/\s+/u', ' ', $reason));
            if ($reason === '') throw new SscException('Please write why the reset is needed.', 422);
            $reason = mb_substr($reason, 0, 200);
        }
        if (!$studentId || !isset(SS3_KINDS[$kind])) throw new SscException('Choose a student and a quiz or exam.', 422);
        if (!ssd_can_act($db, $viewer, $c, $studentId)) throw new SscException('You can only reset your own students.', 403);
        $it = ss3_item(ss2_model($db, $courseId, $studentId), $kind, $moduleId);
        if (!$it) throw new SscException('That quiz or exam is not part of this course.', 404);
        if ($it['item']['passed']) throw new SscException('The student has already passed this, so there is nothing to reset.', 422);
        $cat = ssd_category($kind);
        if ($viewer['role'] !== 'admin') {
            $lim = ssd_settings($db)['reset_' . $cat . '_month']; $used = (int) (ssd_usage($db, (int) $viewer['uid'], $now)[$studentId][$cat] ?? 0);
            if ($used >= $lim) throw new SscException('Only admin can reset now. You have used ' . $used . ' of ' . $lim . ' ' . ($cat === 'quiz' ? 'module quiz' : 'exam') . ' resets for this student this month.', 403);
        }
        $r = ss3_grant_reset($db, $studentId, $courseId, $kind, $moduleId, (int) $viewer['uid'], $reason, $requestId, $now, $note);
        if (!$r['ok']) throw new SscException($r['error'] ?? 'The reset could not be saved.', 500);
        if ($requestId && trim($note) !== '') $db->prepare('UPDATE self_study_reset_requests SET handled_note = ? WHERE id = ?')->execute([mb_substr(trim($note), 0, 300), $requestId]);
        ssa_log($db, $viewer, $courseId, 'reset', 1, ['student' => $studentId, 'kind' => $kind, 'module' => $moduleId, 'request' => $requestId, 'cleared' => $r['cleared']], $now);
        $label = $it['label'];
        ss3_notify([$studentId], 'selfstudy_reset', 'Your tutor reset your tries', $label . ' in "' . $c['title'] . '": you can try again now.' . (trim($note) !== '' ? ' ' . trim($note) : ''),
            'students/selfstudy/quiz.php?id=' . $courseId . ($kind === 'module_quiz' ? '&module=' . (int) $moduleId : '&exam=' . ($kind === 'checkpoint_exam' ? 'checkpoint' : 'final')), $db);
        return ['ok' => true, 'cleared' => (int) $r['cleared'], 'window' => $r['window']];
    }

    /** "Not now", with an optional note. The student is told. */
    function ssd_decline(PDO $db, array $viewer, int $courseId, int $requestId, string $note = '', ?int $now = null): array
    {
        $now ??= time();
        $c = ssd_guard($db, $viewer, $courseId);
        $q = ss2_rows($db, "SELECT id, student_id, kind, module_id FROM self_study_reset_requests WHERE id = ? AND course_id = ? AND status = 'waiting'", [$requestId, $courseId])[0] ?? null;
        if (!$q) throw new SscException('That request was not found, or it was already handled.', 404);
        if (!ssd_can_act($db, $viewer, $c, (int) $q['student_id'])) throw new SscException('You can only answer requests of your own students.', 403);
        $note = mb_substr(trim($note), 0, 300);
        $db->prepare("UPDATE self_study_reset_requests SET status = 'declined', handled_by = ?, handled_at = ?, handled_note = ? WHERE id = ? AND status = 'waiting'")->execute([$viewer['uid'], ss2_ts($now), $note !== '' ? $note : null, $requestId]);
        ssa_log($db, $viewer, $courseId, 'reset_declined', 1, ['student' => (int) $q['student_id'], 'request' => $requestId], $now);
        $label = ssd_item_label(ssc_outline($db, $courseId), $q['kind'], $q['module_id'] !== null ? (int) $q['module_id'] : null);
        ss3_notify([(int) $q['student_id']], 'selfstudy_reset', 'Your reset request was not granted yet', $label . ' in "' . $c['title'] . '". ' . ($note !== '' ? $note : 'Please read the topics again, then ask your tutor if you still need help.'), 'students/selfstudy/index.php', $db);
        return ['ok' => true];
    }

    /** Admin: every reset on the course with who did it and why. */
    function ssd_all_resets(PDO $db, array $viewer, int $courseId, int $limit = 200): array
    {
        $c = ssd_guard($db, $viewer, $courseId);
        if ($viewer['role'] !== 'admin') throw new SscException('Only admin can see every reset.', 403);
        $outline = ssc_outline($db, $courseId); $rows = ss2_rows($db, 'SELECT created_at, student_id, kind, module_id, reset_by, reason, note, tries_cleared, request_id FROM self_study_resets WHERE course_id = ? ORDER BY id DESC LIMIT ' . (int) $limit, [$courseId]);
        $names = ss3_student_names($db, array_merge(array_column($rows, 'student_id'), array_column($rows, 'reset_by')));
        return array_map(fn($r) => ['when' => ss2_parse($r['created_at']), 'student' => $names[(int) $r['student_id']], 'item' => ssd_item_label($outline, $r['kind'], $r['module_id'] !== null ? (int) $r['module_id'] : null),
                                    'by' => $names[(int) $r['reset_by']], 'reason' => $r['reason'], 'note' => $r['note'], 'cleared' => (int) $r['tries_cleared'], 'from_request' => $r['request_id'] !== null], $rows);
    }
}
