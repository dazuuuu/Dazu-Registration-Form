<?php
// app/v1/services/SelfStudyStudy.php — Self-study step 2: the study screen.
// Every rule here runs on the server (the page and progress.php both call it); the screen only shows what this file decides.
//
// Order of a course:   Knowledge Check (never blocks) -> Course Introduction -> Module 1 topics -> Module 1 quiz -> Module 2 ... -> Checkpoint Exam -> Final Exam
//  * a topic opens when the topic before it is done (and, for the first topic of a module, the quiz of the module before it is passed)
//  * a module quiz opens when every topic of its module is done; it is passed when a saved attempt reaches the pass mark
//  * the Checkpoint Exam opens when every module is passed; the Final Exam when the Checkpoint Exam is passed
//  * a quiz or exam with NO questions saved yet cannot be taken, so it counts as passed as soon as it opens (otherwise a course
//    would be stuck while the tutor is still writing it). Once questions exist, the saved attempts decide.
require_once __DIR__ . '/SelfStudy.php';
require_once __DIR__ . '/SelfStudyHtml.php';

if (!function_exists('ss2_model')) {

    define('SS2_TOPIC_QUESTIONS', 6);
    define('SS2_DEFAULT_PASS', 70);
    define('SS3_QUIZ_PASS', 50);      // the same for every course: a module quiz needs 50%, the two exams 70%
    define('SS3_EXAM_PASS', 70);

    function ss2_installed(PDO $db): bool
    {
        try { foreach (['self_study_modules', 'self_study_topics', 'self_study_questions', 'self_study_topic_progress', 'self_study_video_progress', 'self_study_answers', 'self_study_files'] as $t) $db->query("SELECT 1 FROM $t WHERE 1 = 0"); return true; }
        catch (Throwable $e) { return false; }
    }

    function ss2_rows(PDO $db, string $sql, array $args = []): array
    {
        $st = $db->prepare($sql);
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    function ss2_ts(int $t): string { return gmdate('Y-m-d H:i:s', $t); }
    function ss2_parse(?string $s): ?int { if (!$s) return null; $t = strtotime($s . ' UTC'); return $t === false ? null : $t; }

    /** The fee lock that also guards the course lessons. */
    function ss2_portal_locked(PDO $db, int $uid): bool
    {
        try {
            $st = $db->prepare("SELECT portal_locked FROM registrations WHERE user_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 1");
            $st->execute([$uid]);
            return (bool) $st->fetchColumn();
        } catch (Throwable $e) { return false; }
    }

    /**
     * The whole outline with every lock decided, in 6 small queries (no query per topic).
     * @return array<string,mixed>
     */
    function ss2_model(PDO $db, int $courseId, int $uid): array
    {
        $mods = ss2_rows($db, 'SELECT id, title, position, pass_mark FROM self_study_modules WHERE course_id = ? AND archived_at IS NULL ORDER BY position, id', [$courseId]);
        $tops = ss2_rows($db, 'SELECT id, module_id, title, position, is_intro, video_id, video_duration, created_at FROM self_study_topics WHERE course_id = ? AND archived_at IS NULL ORDER BY position, id', [$courseId]);
        $tp = [];
        foreach (ss2_rows($db, 'SELECT topic_id, last_seen_at, video_watched, questions_submitted, score_correct, score_total, done_at FROM self_study_topic_progress WHERE student_id = ? AND course_id = ?', [$uid, $courseId]) as $r) $tp[(int) $r['topic_id']] = $r;
        $qn = ['topic' => [], 'module_quiz' => [], 'checkpoint_exam' => 0, 'final_exam' => 0, 'knowledge_check' => 0];
        foreach (ss2_rows($db, 'SELECT place, module_id, topic_id, qtype, COUNT(*) AS n FROM self_study_questions WHERE course_id = ? AND archived_at IS NULL GROUP BY place, module_id, topic_id, qtype', [$courseId]) as $r) {
            $n = (int) $r['n'];
            if ($r['qtype'] === 'long_answer' && $r['place'] !== 'topic') continue;      // a quiz or exam never draws a long answer, so it does not count towards "enough"
            if ($r['place'] === 'topic') $qn['topic'][(int) $r['topic_id']] = ($qn['topic'][(int) $r['topic_id']] ?? 0) + $n;
            elseif ($r['place'] === 'module_quiz') $qn['module_quiz'][(int) $r['module_id']] = ($qn['module_quiz'][(int) $r['module_id']] ?? 0) + $n;
            elseif (isset($qn[$r['place']])) $qn[$r['place']] += $n;
        }
        // best saved attempt (percent) per quiz / exam, and the latest Knowledge Check
        $best = ['module_quiz' => [], 'checkpoint_exam' => null, 'final_exam' => null]; $tries = ['module_quiz' => [], 'checkpoint_exam' => 0, 'final_exam' => 0];
        $kb = null;
        foreach (ss2_rows($db, "SELECT q.place, q.module_id, a.attempt, SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END) AS c, COUNT(a.is_correct) AS t
                                 FROM self_study_answers a JOIN self_study_questions q ON q.id = a.question_id
                                 WHERE a.student_id = ? AND a.course_id = ? AND q.place IN ('module_quiz', 'checkpoint_exam', 'final_exam', 'knowledge_check')
                                 GROUP BY q.place, q.module_id, a.attempt", [$uid, $courseId]) as $r) {
            $c = (int) $r['c']; $t = (int) $r['t']; $pct = $t > 0 ? (int) floor($c * 100 / $t) : 100;
            if ($r['place'] === 'knowledge_check') { if ($kb === null || (int) $r['attempt'] > $kb['attempt']) $kb = ['attempt' => (int) $r['attempt'], 'correct' => $c, 'total' => $t]; continue; }
            if ($r['place'] === 'module_quiz') {
                $m = (int) $r['module_id'];
                $best['module_quiz'][$m] = max($best['module_quiz'][$m] ?? 0, $pct); $tries['module_quiz'][$m] = ($tries['module_quiz'][$m] ?? 0) + 1;
            } else { $best[$r['place']] = max($best[$r['place']] ?? 0, $pct); $tries[$r['place']]++; }
        }
        // attempts made on the quiz / exam screens (migration 106): once an attempt passed, the item stays passed
        $flag = ['module_quiz' => [], 'checkpoint_exam' => false, 'final_exam' => false]; $passAt = [];
        try {
            foreach (ss2_rows($db, "SELECT kind, module_id, MAX(score_percent) AS s, MAX(passed) AS p, MIN(CASE WHEN passed = 1 THEN submitted_at END) AS first_pass, COUNT(*) AS n FROM self_study_attempts
                                     WHERE student_id = ? AND course_id = ? AND status IN ('submitted', 'timed_out') GROUP BY kind, module_id", [$uid, $courseId]) as $r) {
                $s = (int) $r['s']; $p = (int) $r['p'] > 0;
                if ($r['kind'] === 'module_quiz') { $m = (int) $r['module_id']; $best['module_quiz'][$m] = max($best['module_quiz'][$m] ?? 0, $s); $tries['module_quiz'][$m] = ($tries['module_quiz'][$m] ?? 0) + (int) $r['n']; $flag['module_quiz'][$m] = ($flag['module_quiz'][$m] ?? false) || $p; if ($p) $passAt[$m] = ss2_parse($r['first_pass'] ?? null); }
                elseif (isset($flag[$r['kind']])) { $best[$r['kind']] = max($best[$r['kind']] ?? 0, $s); $tries[$r['kind']] += (int) $r['n']; $flag[$r['kind']] = $flag[$r['kind']] || $p; }
            }
        } catch (Throwable $e) { /* migration 106 not applied yet */ }
        $cpPass = SS3_EXAM_PASS;
        $fnPass = SS3_EXAM_PASS;
        // how many questions one attempt needs (course settings; 12 / 30 / 40 by default). A quiz or exam with fewer is "being updated": it cannot be started and never counts as passed
        $needQ = ['quiz' => 12, 'cp' => 30, 'fn' => 40];
        try { $nr = ss2_rows($db, 'SELECT quiz_questions, checkpoint_questions, final_questions FROM self_study_courses WHERE id = ?', [$courseId])[0] ?? []; $needQ = ['quiz' => max(1, (int) ($nr['quiz_questions'] ?? 12)), 'cp' => max(1, (int) ($nr['checkpoint_questions'] ?? 30)), 'fn' => max(1, (int) ($nr['final_questions'] ?? 40))]; } catch (Throwable $e) { /* migration 106 not applied yet */ }

        $byMod = []; $intro = [];
        $modIds = array_map('intval', array_column($mods, 'id'));
        foreach ($tops as $t) {
            if ((int) $t['is_intro'] === 1) $intro[] = $t;
            elseif ($t['module_id'] !== null && in_array((int) $t['module_id'], $modIds, true)) $byMod[(int) $t['module_id']][] = $t;
        }
        $flat = []; $topicsById = []; $prevDone = true;
        $mk = function (array $t, ?int $modIdx, int $idx, int $count, bool $open, string $reason, bool $optional = false) use ($tp, $qn): array {
            $id = (int) $t['id']; $p = $tp[$id] ?? null;
            $done = !empty($p['done_at']);
            return [
                'id' => $id, 'title' => (string) $t['title'], 'is_intro' => (int) $t['is_intro'] === 1, 'module_id' => $t['module_id'] !== null ? (int) $t['module_id'] : null,
                'module_no' => $modIdx !== null ? $modIdx + 1 : null, 'idx' => $idx, 'count' => $count,
                'number' => $modIdx !== null ? ($modIdx + 1) . '.' . $idx : '',
                'video_id' => $t['video_id'] ?: null, 'video_duration' => (int) ($t['video_duration'] ?? 0),
                'done' => $done, 'open' => $open, 'reason' => $open ? '' : $reason, 'optional' => $optional,
                'video_watched' => !empty($p['video_watched']), 'submitted' => !empty($p['questions_submitted']),
                'score_correct' => $p['score_correct'] ?? null, 'score_total' => $p['score_total'] ?? null,
                'last_seen' => ss2_parse($p['last_seen_at'] ?? null), 'q_count' => (int) ($qn['topic'][$id] ?? 0),
            ];
        };
        $introOut = [];
        foreach ($intro as $i => $t) {
            $row = $mk($t, null, $i, count($intro), $prevDone, 'Finish the topic before it first.');
            $introOut[] = $row; $flat[] = $row['id']; $topicsById[$row['id']] = $row; $prevDone = $row['done'];
        }
        $introDone = $prevDone;
        $out = []; $modOpen = $introDone; $allPassed = true; $prevMark = '';
        foreach ($mods as $k => $m) {
            $mid = (int) $m['id'];
            $passMark = SS3_QUIZ_PASS;
            $reason = $k === 0 ? 'Finish the Course Introduction to open this module.' : 'Pass the Module ' . $k . ' quiz (' . $prevMark . '%) to open this module.';
            $list = []; $doneN = 0; $cnt = count($byMod[$mid] ?? []); $req = 0;
            foreach ($byMod[$mid] ?? [] as $i => $t) {
                // a topic the tutor added AFTER this student passed the module is optional for them (and compulsory for students who have not reached it)
                $created = ss2_parse($t['created_at'] ?? null);
                if (!empty($flag['module_quiz'][$mid]) && !empty($passAt[$mid]) && $created !== null && $created > $passAt[$mid] && empty($tp[(int) $t['id']]['done_at'])) {
                    $row = $mk($t, $k, $i, $cnt, true, '', true);
                    $list[] = $row; $flat[] = $row['id']; $topicsById[$row['id']] = $row;
                    continue;
                }
                $req++;
                $open = $modOpen && $prevDone;
                $row = $mk($t, $k, $i, $cnt, $open, $modOpen ? 'Finish the topic before it first.' : $reason);
                $list[] = $row; $flat[] = $row['id']; $topicsById[$row['id']] = $row; $prevDone = $row['done']; if ($row['done']) $doneN++;
            }
            $quizOpen = $modOpen && $doneN === $req;
            $enough = (int) ($qn['module_quiz'][$mid] ?? 0) >= $needQ['quiz'];
            $bestPct = $best['module_quiz'][$mid] ?? null;
            $passed = !empty($flag['module_quiz'][$mid]) || ($quizOpen && $enough && $bestPct !== null && $bestPct >= $passMark);      // a pass already earned stays; a new pass needs a full question bank
            $quizOpen = $quizOpen || $passed;
            $updating = !$passed && $quizOpen && !$enough;
            $quiz = ['open' => $quizOpen, 'passed' => $passed, 'pass' => $passMark, 'best' => $bestPct, 'tries' => $tries['module_quiz'][$mid] ?? 0, 'questions' => $qn['module_quiz'][$mid] ?? 0, 'need' => $needQ['quiz'], 'updating' => $updating,
                     'reason' => $quizOpen ? '' : 'Finish every topic of Module ' . ($k + 1) . ' to open its quiz.'];
            $out[] = ['id' => $mid, 'no' => $k + 1, 'title' => (string) $m['title'], 'pass' => $passMark, 'open' => $modOpen, 'reason' => $modOpen ? '' : $reason, 'topics' => $list, 'quiz' => $quiz,
                      'items' => $req + 1, 'done_items' => $doneN + ($passed ? 1 : 0), 'percent' => (int) floor(($doneN + ($passed ? 1 : 0)) * 100 / ($req + 1))];
            $modOpen = $passed; $prevMark = (string) $passMark; $allPassed = $allPassed && $passed;
        }
        $hasContent = $flat || $mods;
        $cpOpen = (bool) ($flag['checkpoint_exam'] || ($hasContent && $introDone && $allPassed));
        $cpEnough = (int) $qn['checkpoint_exam'] >= $needQ['cp'];
        $cpPassed = $flag['checkpoint_exam'] || ($cpOpen && $cpEnough && $best['checkpoint_exam'] !== null && $best['checkpoint_exam'] >= $cpPass);
        $cpUpdating = !$cpPassed && $cpOpen && !$cpEnough;
        $fnOpen = $cpPassed || $flag['final_exam'];
        $fnEnough = (int) $qn['final_exam'] >= $needQ['fn'];
        $fnPassed = $flag['final_exam'] || ($fnOpen && $fnEnough && $best['final_exam'] !== null && $best['final_exam'] >= $fnPass);
        $fnUpdating = !$fnPassed && $fnOpen && !$fnEnough;
        $checkpoint = ['open' => $cpOpen, 'passed' => $cpPassed, 'pass' => $cpPass, 'best' => $best['checkpoint_exam'], 'tries' => $tries['checkpoint_exam'], 'questions' => $qn['checkpoint_exam'], 'need' => $needQ['cp'], 'updating' => $cpUpdating,
                       'reason' => $cpOpen ? '' : 'Opens after all modules · pass mark ' . $cpPass . '%'];
        $final = ['open' => $fnOpen, 'passed' => $fnPassed, 'pass' => $fnPass, 'best' => $best['final_exam'], 'tries' => $tries['final_exam'], 'questions' => $qn['final_exam'], 'need' => $needQ['fn'], 'updating' => $fnUpdating,
                  'reason' => $fnOpen ? '' : 'Opens after the Checkpoint Exam is passed'];

        // the bars: topics + module quizzes + the two exams
        $items = 0; $doneItems = 0;
        foreach ($topicsById as $t) { if (!empty($t['optional']) && !$t['done']) continue; $items++; if ($t['done']) $doneItems++; }
        foreach ($out as $m) { $items++; if ($m['quiz']['passed']) $doneItems++; }
        if ($hasContent) { $items += 2; $doneItems += ($cpPassed ? 1 : 0) + ($fnPassed ? 1 : 0); }
        $percent = $items > 0 ? (int) floor($doneItems * 100 / $items) : 0;

        $frontier = null; $resume = null; $resumeAt = 0;
        foreach ($flat as $id) {
            $t = $topicsById[$id];
            if ($frontier === null && $t['open'] && !$t['done'] && empty($t['optional'])) $frontier = $id;
            if ($t['open'] && $t['last_seen'] && $t['last_seen'] >= $resumeAt) { $resumeAt = $t['last_seen']; $resume = $id; }
        }
        if ($frontier === null && $flat) $frontier = end($flat);
        return [
            'intro' => $introOut, 'modules' => $out, 'checkpoint' => $checkpoint, 'final' => $final, 'topics' => $topicsById, 'flat' => $flat,
            'kb' => ['questions' => $qn['knowledge_check'], 'last' => $kb], 'items' => $items, 'done_items' => $doneItems, 'percent' => $percent,
            'frontier' => $frontier, 'resume' => $resume ?? $frontier, 'has_content' => (bool) $hasContent,
        ];
    }

    function ss2_next_topic(array $model, int $topicId): ?int
    {
        $i = array_search($topicId, $model['flat'], true);
        if ($i === false || !isset($model['flat'][$i + 1])) return null;
        $n = $model['flat'][$i + 1];
        return $model['topics'][$n]['open'] ? $n : null;
    }

    /** Remember the topic the student is on, so the course reopens exactly there. */
    function ss2_touch(PDO $db, int $uid, int $courseId, int $topicId, int $now): void
    {
        $ts = ss2_ts($now);
        $st = $db->prepare('UPDATE self_study_topic_progress SET last_seen_at = ?, started_at = COALESCE(started_at, ?) WHERE student_id = ? AND topic_id = ?');
        $st->execute([$ts, $ts, $uid, $topicId]);
        if ($st->rowCount() === 0) ss2_tp_ensure($db, $uid, $courseId, $topicId, $now);
        ss2_save_progress($db, $uid, $courseId, $now);          // opening a topic also moves "Last studied" on the Self-study card
    }

    function ss2_tp_ensure(PDO $db, int $uid, int $courseId, int $topicId, int $now): array
    {
        $sel = $db->prepare('SELECT * FROM self_study_topic_progress WHERE student_id = ? AND topic_id = ?');
        $sel->execute([$uid, $topicId]);
        $r = $sel->fetch(PDO::FETCH_ASSOC);
        if ($r) return $r;
        try {
            $ts = ss2_ts($now);
            $db->prepare('INSERT INTO self_study_topic_progress (student_id, topic_id, course_id, started_at, last_seen_at) VALUES (?, ?, ?, ?, ?)')->execute([$uid, $topicId, $courseId, $ts, $ts]);
        } catch (Throwable $e) { /* another request created it first */ }
        $sel->execute([$uid, $topicId]);
        return $sel->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** Saves the course percent and the last studied date to self_study_progress (the numbers the Self-study cards read). */
    function ss2_save_progress(PDO $db, int $uid, int $courseId, int $now, ?array $model = null, bool $keepLast = false): int
    {
        $model ??= ss2_model($db, $courseId, $uid);
        $pct = (int) $model['percent']; $ts = ss2_ts($now);
        $sel = $db->prepare('SELECT id, completed_at, last_studied_at FROM self_study_progress WHERE course_id = ? AND student_id = ?');
        $sel->execute([$courseId, $uid]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        $completed = ($pct >= 100 || !empty($model['final']['passed'])) ? (($row['completed_at'] ?? null) ?: $ts) : null;      // a passed Final Exam stays passed even if the tutor adds content later
        $lastTs = ($keepLast && $row && !empty($row['last_studied_at'])) ? $row['last_studied_at'] : $ts;
        if ($row) {
            $db->prepare('UPDATE self_study_progress SET percent_complete = ?, last_studied_at = ?, completed_at = ? WHERE id = ?')->execute([$pct, $lastTs, $completed, $row['id']]);
        } else {
            try { $db->prepare('INSERT INTO self_study_progress (course_id, student_id, percent_complete, last_studied_at, completed_at) VALUES (?, ?, ?, ?, ?)')->execute([$courseId, $uid, $pct, $ts, $completed]); }
            catch (Throwable $e) { $db->prepare('UPDATE self_study_progress SET percent_complete = ?, last_studied_at = ?, completed_at = ? WHERE course_id = ? AND student_id = ?')->execute([$pct, $ts, $completed, $courseId, $uid]); }
        }
        return $pct;
    }

    /* ------------------------------ topic content ------------------------------ */

    /** The topic with its text cleaned (allow-list) and its video id checked. Null when it is not in this course. */
    function ss2_topic_content(PDO $db, int $courseId, int $topicId): ?array
    {
        $r = ss2_rows($db, 'SELECT id, title, content_html, video_id FROM self_study_topics WHERE id = ? AND course_id = ?', [$topicId, $courseId])[0] ?? null;
        if (!$r) return null;
        $r['html'] = str_replace('src="uploads/', 'src="' . url('uploads/'), ss_clean_html($r['content_html']));      // pictures saved by the tutor editor
        $r['video_id'] = ss_youtube_id($r['video_id']);      // only a real YouTube id is ever shown
        unset($r['content_html']);
        return $r;
    }

    function ss2_files(PDO $db, int $courseId): array
    {
        return ss2_rows($db, 'SELECT id, topic_id, file_name FROM self_study_files WHERE course_id = ? ORDER BY file_name, id', [$courseId]);
    }

    /* ------------------------------ video tracking ------------------------------ */

    /** Saved video state for the player: where to resume, and how much counts as watched. */
    function ss2_video_state(PDO $db, int $uid, int $topicId, int $topicDuration = 0): array
    {
        $r = ss2_rows($db, 'SELECT furthest_second, seconds_watched, duration, completed FROM self_study_video_progress WHERE student_id = ? AND topic_id = ?', [$uid, $topicId])[0] ?? null;
        $dur = $topicDuration > 0 ? $topicDuration : (int) ($r['duration'] ?? 0);
        $w = (int) ($r['seconds_watched'] ?? 0);
        return ['furthest' => (int) ($r['furthest_second'] ?? 0), 'watched' => $w, 'duration' => $dur, 'completed' => !empty($r['completed']),
                'percent' => $dur > 0 ? min(100, (int) floor($w * 100 / $dur)) : 0];
    }

    /**
     * One progress report from the player (about every 5 seconds). The server decides how much of it is believable:
     * the video can only move forward as fast as real time passed (a little over 1x), so a faked "I am at 5:00" is cut back to what the clock allows.
     * Only the seconds between the old furthest point and the new one are credited, so replaying earlier parts counts nothing twice.
     * @return array{furthest:int, watched:int, duration:int, percent:int, completed:bool}
     */
    function ss2_video_tick(PDO $db, int $uid, int $courseId, int $topicId, int $topicDuration, int $pos, int $reportedDuration, ?int $now = null): array
    {
        $now ??= time();
        $pos = max(0, min(86400, $pos));
        $reportedDuration = ($reportedDuration >= 10 && $reportedDuration <= 43200) ? $reportedDuration : 0;
        $st = $db->prepare('SELECT id, furthest_second, seconds_watched, duration, last_tick_at FROM self_study_video_progress WHERE student_id = ? AND topic_id = ?');
        $st->execute([$uid, $topicId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        $furthest = (int) ($r['furthest_second'] ?? 0); $watched = (int) ($r['seconds_watched'] ?? 0);
        $dur = $topicDuration > 0 ? $topicDuration : max((int) ($r['duration'] ?? 0), $reportedDuration);   // the saved length wins; a reported length can only grow
        $last = $r ? ss2_parse($r['last_tick_at']) : null;
        $window = $last === null ? 0 : max(0, min(15, $now - $last));
        $allowed = (int) floor($furthest + $window * 1.25 + 1.5);
        if ($pos > $furthest) {
            $new = min($pos, $allowed);
            if ($dur > 0) $new = min($new, $dur);
            $watched += max(0, $new - $furthest);
            $furthest = max($furthest, $new);
        }
        $watched = min($watched, $furthest);
        $done = $dur >= 10 && $furthest >= 0.95 * $dur && $watched >= 0.95 * $dur;
        $ts = ss2_ts($now);
        if ($r) {
            $db->prepare('UPDATE self_study_video_progress SET furthest_second = ?, seconds_watched = ?, duration = ?, completed = ?, last_tick_at = ?, updated_at = ? WHERE id = ?')
               ->execute([$furthest, $watched, $dur, $done ? 1 : 0, $ts, $ts, $r['id']]);
        } else {
            try { $db->prepare('INSERT INTO self_study_video_progress (student_id, topic_id, furthest_second, seconds_watched, duration, completed, last_tick_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                     ->execute([$uid, $topicId, $furthest, $watched, $dur, $done ? 1 : 0, $ts, $ts]); } catch (Throwable $e) { /* parallel first tick */ }
        }
        if ($done) {
            ss2_tp_ensure($db, $uid, $courseId, $topicId, $now);
            $db->prepare('UPDATE self_study_topic_progress SET video_watched = 1 WHERE student_id = ? AND topic_id = ?')->execute([$uid, $topicId]);
        }
        return ['furthest' => $furthest, 'watched' => $watched, 'duration' => $dur, 'percent' => $dur > 0 ? min(100, (int) floor($watched * 100 / $dur)) : 0, 'completed' => $done];
    }

    /* ------------------------------ topic questions ------------------------------ */

    function ss2_norm(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = str_replace('.', '', $s);                 // full stops are ignored
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    function ss2_options(?string $json): array
    {
        $o = json_decode((string) $json, true);
        return is_array($o) ? array_values(array_map('strval', $o)) : [];
    }

    /**
     * The up-to-six questions drawn for this student. They are drawn once and saved, so the same questions
     * (in the same shuffled order) come back after a reload. Correct answers are never part of what is returned.
     * @return array<int,array{id:int,type:string,text:string,options:array<int,array{0:int,1:string}>}>
     */
    function ss2_topic_questions(PDO $db, int $uid, int $courseId, int $topicId, int $now): array
    {
        $tp = ss2_tp_ensure($db, $uid, $courseId, $topicId, $now);
        $set = json_decode((string) ($tp['question_set'] ?? ''), true);
        if (!is_array($set)) {
            $st = $db->prepare("SELECT id, options_json FROM self_study_questions WHERE place = 'topic' AND topic_id = ? AND course_id = ? AND archived_at IS NULL");
            $st->execute([$topicId, $courseId]);
            $all = $st->fetchAll(PDO::FETCH_ASSOC);
            shuffle($all);
            $set = [];
            foreach (array_slice($all, 0, SS2_TOPIC_QUESTIONS) as $q) {
                $o = range(0, max(0, count(ss2_options($q['options_json'])) - 1));
                shuffle($o);
                $set[] = ['q' => (int) $q['id'], 'o' => $o];
            }
            $db->prepare('UPDATE self_study_topic_progress SET question_set = ? WHERE student_id = ? AND topic_id = ? AND question_set IS NULL')->execute([json_encode($set), $uid, $topicId]);
            $tp = ss2_tp_ensure($db, $uid, $courseId, $topicId, $now);
            $set = json_decode((string) ($tp['question_set'] ?? ''), true) ?: $set;      // if two requests raced, the saved draw wins
        }
        $ids = array_map(fn($x) => (int) $x['q'], $set);
        if (!$ids) return [];
        $by = [];
        foreach (ss2_rows($db, 'SELECT id, qtype, question_text, options_json FROM self_study_questions WHERE course_id = ? AND id IN (' . implode(',', $ids) . ')', [$courseId]) as $q) $by[(int) $q['id']] = $q;
        $out = [];
        foreach ($set as $x) {
            $q = $by[(int) $x['q']] ?? null;
            if (!$q) continue;                                   // the tutor deleted it since
            $opts = ss2_options($q['options_json']); $list = [];
            if ($q['qtype'] === 'multiple_choice') foreach ($x['o'] as $oi) if (isset($opts[$oi])) $list[] = [(int) $oi, $opts[$oi]];
            $out[] = ['id' => (int) $q['id'], 'type' => $q['qtype'], 'text' => (string) $q['question_text'], 'options' => $list];
        }
        return $out;
    }

    /** The same shape as ss2_topic_questions() for a tutor's preview, but nothing is saved: a fresh random draw each time. */
    function ss2_preview_questions(PDO $db, int $courseId, int $topicId): array
    {
        $rows = ss2_rows($db, "SELECT id, qtype, question_text, options_json FROM self_study_questions WHERE place = 'topic' AND topic_id = ? AND course_id = ? AND archived_at IS NULL", [$topicId, $courseId]);
        shuffle($rows);
        $out = [];
        foreach (array_slice($rows, 0, SS2_TOPIC_QUESTIONS) as $q) {
            $opts = ss2_options($q['options_json']); $order = $q['qtype'] === 'multiple_choice' ? array_keys($opts) : []; shuffle($order);
            $out[] = ['id' => (int) $q['id'], 'type' => $q['qtype'], 'text' => (string) $q['question_text'], 'options' => array_map(fn($i) => [(int) $i, $opts[$i]], $order)];
        }
        return $out;
    }

    function ss2_has_topic_questions(PDO $db, int $courseId, int $topicId): bool
    {
        $st = $db->prepare("SELECT COUNT(*) FROM self_study_questions WHERE place = 'topic' AND course_id = ? AND topic_id = ? AND archived_at IS NULL");
        $st->execute([$courseId, $topicId]);
        return (int) $st->fetchColumn() > 0;
    }

    /** What the student wrote on a finished topic, for the read-only view (never marks, never correct answers). */
    function ss2_topic_answers(PDO $db, int $uid, int $courseId, array $questions): array
    {
        if (!$questions) return [];
        $ids = implode(',', array_map(fn($q) => (int) $q['id'], $questions));
        $got = [];
        foreach (ss2_rows($db, "SELECT question_id, answer FROM self_study_answers WHERE student_id = ? AND course_id = ? AND question_id IN ($ids) ORDER BY attempt ASC, id ASC", [$uid, $courseId]) as $r) $got[(int) $r['question_id']] = (string) $r['answer'];
        $out = [];
        foreach ($questions as $q) {
            $a = $got[$q['id']] ?? '';
            if ($q['type'] === 'multiple_choice') { foreach ($q['options'] as [$oi, $label]) if ((string) $oi === $a) { $a = $label; break; } }
            $out[$q['id']] = $a;
        }
        return $out;
    }

    /** The tutor's comments on this student's long answers (question id => comment), shown under the question on a finished topic. */
    function ss2_topic_comments(PDO $db, int $uid, int $courseId, array $questions): array
    {
        if (!$questions) return [];
        $ids = implode(',', array_map(fn($q) => (int) $q['id'], $questions));
        $out = [];
        foreach (ss2_rows($db, "SELECT question_id, tutor_comment FROM self_study_answers WHERE student_id = ? AND course_id = ? AND question_id IN ($ids) AND tutor_comment IS NOT NULL ORDER BY id ASC", [$uid, $courseId]) as $r) $out[(int) $r['question_id']] = (string) $r['tutor_comment'];
        return $out;
    }

    /** Marks one answer. Returns [correct|null, stored answer]. null = a long answer, left for the tutor. */
    function ss2_grade(array $q, $given): array
    {
        $type = $q['qtype'];
        if ($type === 'multiple_choice') {
            $n = count(ss2_options($q['options_json']));
            $i = (is_int($given) || (is_string($given) && ctype_digit($given))) ? (int) $given : -1;
            if ($i < 0 || $i >= $n) return [false, ''];
            return [$q['correct_index'] !== null && (int) $q['correct_index'] === $i, (string) $i];
        }
        $text = mb_substr(trim((string) (is_scalar($given) ? $given : '')), 0, $type === 'long_answer' ? 5000 : 300);
        if ($type === 'long_answer') return [null, $text];
        $acc = json_decode((string) $q['accepted_answers'], true);
        $acc = is_array($acc) ? array_map(fn($a) => ss2_norm((string) $a), $acc) : [];
        return [$text !== '' && in_array(ss2_norm($text), $acc, true), $text];
    }

    /**
     * Submit the topic questions. There is no pass mark: marking, saving the score, marking the topic done and the next topic opening all happen here.
     * @return array{ok:bool, error?:string, next?:?int, done?:bool}
     */
    function ss2_submit_topic(PDO $db, int $uid, int $courseId, int $topicId, array $answers, ?int $now = null): array
    {
        $now ??= time();
        $model = ss2_model($db, $courseId, $uid);
        $t = $model['topics'][$topicId] ?? null;
        if (!$t) return ['ok' => false, 'error' => 'That topic was not found.'];
        if (!$t['open']) return ['ok' => false, 'error' => $t['reason'] ?: 'That topic is locked.', 'locked' => true];
        if ($t['done']) return ['ok' => true, 'done' => true, 'next' => ss2_next_topic($model, $topicId)];          // already finished: nothing is changed
        if ($t['video_id'] && ss_youtube_id($t['video_id']) && !$t['video_watched']) {
            $v = ss2_video_state($db, $uid, $topicId, $t['video_duration']);
            return ['ok' => false, 'error' => 'Watched ' . $v['percent'] . '%. Watch to the end to finish this video.'];
        }
        $questions = ss2_topic_questions($db, $uid, $courseId, $topicId, $now);
        if (!$questions) return ['ok' => false, 'error' => 'This topic has no questions.'];
        $ids = array_map(fn($q) => (int) $q['id'], $questions);
        $rows = [];
        foreach (ss2_rows($db, 'SELECT id, qtype, options_json, correct_index, accepted_answers FROM self_study_questions WHERE id IN (' . implode(',', $ids) . ')') as $r) $rows[(int) $r['id']] = $r;
        $graded = []; $correct = 0; $auto = 0;
        foreach ($questions as $q) {
            $given = $answers[(string) $q['id']] ?? null;
            if ($given === null || (is_string($given) && trim($given) === '')) return ['ok' => false, 'error' => 'Please answer every question before you submit.'];
            [$ok, $stored] = ss2_grade($rows[$q['id']], $given);
            if ($stored === '') return ['ok' => false, 'error' => 'Please answer every question before you submit.'];
            if ($ok !== null) { $auto++; if ($ok) $correct++; }
            $graded[] = [$q['id'], $stored, $ok];
        }
        $db->beginTransaction();
        try {
            $ins = $db->prepare('INSERT INTO self_study_answers (student_id, question_id, course_id, answer, is_correct, attempt, answered_at) VALUES (?, ?, ?, ?, ?, 1, ?)');
            foreach ($graded as [$qid, $stored, $ok]) $ins->execute([$uid, $qid, $courseId, $stored, $ok === null ? null : ($ok ? 1 : 0), ss2_ts($now)]);
            $db->prepare('UPDATE self_study_topic_progress SET questions_submitted = 1, score_correct = ?, score_total = ?, done_at = ?, last_seen_at = ? WHERE student_id = ? AND topic_id = ? AND done_at IS NULL')
               ->execute([$correct, $auto, ss2_ts($now), ss2_ts($now), $uid, $topicId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['ok' => false, 'error' => 'Your answers could not be saved. Please try again.'];
        }
        $model = ss2_model($db, $courseId, $uid);
        ss2_save_progress($db, $uid, $courseId, $now, $model);
        return ['ok' => true, 'done' => true, 'next' => ss2_next_topic($model, $topicId)];
    }

    /** A topic that has no questions saved is finished with one button (after its video, if it has one). */
    function ss2_mark_done(PDO $db, int $uid, int $courseId, int $topicId, ?int $now = null): array
    {
        $now ??= time();
        $model = ss2_model($db, $courseId, $uid);
        $t = $model['topics'][$topicId] ?? null;
        if (!$t) return ['ok' => false, 'error' => 'That topic was not found.'];
        if (!$t['open']) return ['ok' => false, 'error' => $t['reason'] ?: 'That topic is locked.', 'locked' => true];
        if ($t['done']) return ['ok' => true, 'done' => true, 'next' => ss2_next_topic($model, $topicId)];
        if (ss2_has_topic_questions($db, $courseId, $topicId)) return ['ok' => false, 'error' => 'Answer the topic questions to finish this topic.'];
        if ($t['video_id'] && ss_youtube_id($t['video_id']) && !$t['video_watched']) {
            $v = ss2_video_state($db, $uid, $topicId, $t['video_duration']);
            return ['ok' => false, 'error' => 'Watched ' . $v['percent'] . '%. Watch to the end to finish this video.'];
        }
        ss2_tp_ensure($db, $uid, $courseId, $topicId, $now);
        $db->prepare('UPDATE self_study_topic_progress SET done_at = ?, last_seen_at = ? WHERE student_id = ? AND topic_id = ? AND done_at IS NULL')->execute([ss2_ts($now), ss2_ts($now), $uid, $topicId]);
        $model = ss2_model($db, $courseId, $uid);
        ss2_save_progress($db, $uid, $courseId, $now, $model);
        return ['ok' => true, 'done' => true, 'next' => ss2_next_topic($model, $topicId)];
    }

    /* ------------------------------ Knowledge Check ------------------------------ */

    /** Multiple choice only, no pass mark, never blocks. Options are shown in the saved order. */
    function ss2_kb_questions(PDO $db, int $courseId): array
    {
        $out = [];
        foreach (ss2_rows($db, "SELECT id, question_text, options_json FROM self_study_questions WHERE course_id = ? AND place = 'knowledge_check' AND qtype = 'multiple_choice' AND archived_at IS NULL ORDER BY position, id", [$courseId]) as $q) {
            $opts = ss2_options($q['options_json']);
            $out[] = ['id' => (int) $q['id'], 'text' => (string) $q['question_text'], 'options' => array_map(null, array_keys($opts), $opts)];
        }
        return $out;
    }

    /** @return array{ok:bool,error?:string,correct?:int,total?:int,attempt?:int} */
    function ss2_submit_kb(PDO $db, int $uid, int $courseId, array $answers, ?int $now = null): array
    {
        $now ??= time();
        $qs = ss2_rows($db, "SELECT id, qtype, options_json, correct_index, accepted_answers FROM self_study_questions WHERE course_id = ? AND place = 'knowledge_check' AND qtype = 'multiple_choice' AND archived_at IS NULL ORDER BY position, id", [$courseId]);
        if (!$qs) return ['ok' => false, 'error' => 'This course has no Knowledge Check.'];
        $attempt = 1 + (int) ss2_rows($db, "SELECT COALESCE(MAX(a.attempt), 0) AS m FROM self_study_answers a JOIN self_study_questions q ON q.id = a.question_id WHERE a.student_id = ? AND a.course_id = ? AND q.place = 'knowledge_check'", [$uid, $courseId])[0]['m'];
        $correct = 0;
        $db->beginTransaction();
        try {
            $ins = $db->prepare('INSERT INTO self_study_answers (student_id, question_id, course_id, answer, is_correct, attempt, answered_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            foreach ($qs as $q) {
                [$ok, $stored] = ss2_grade($q, $answers[(string) $q['id']] ?? null);
                if ($ok) $correct++;
                $ins->execute([$uid, $q['id'], $courseId, $stored, $ok ? 1 : 0, $attempt, ss2_ts($now)]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            return ['ok' => false, 'error' => 'Your answers could not be saved. Please try again.'];
        }
        ss2_save_progress($db, $uid, $courseId, $now);       // moves the "last studied" date; the Knowledge Check itself never changes the bar
        return ['ok' => true, 'correct' => $correct, 'total' => count($qs), 'attempt' => $attempt];
    }

    /* ------------------------------ files ------------------------------ */

    /** The file, only when this student may open its course (and, for a topic file, that topic). Otherwise null. */
    function ss2_file_for_student(PDO $db, int $fileId, int $uid): ?array
    {
        $f = ss2_rows($db, 'SELECT id, course_id, topic_id, file_name, file_path FROM self_study_files WHERE id = ?', [$fileId])[0] ?? null;
        if (!$f || !ss_course_for_student($db, (int) $f['course_id'], $uid)) return null;
        if ($f['topic_id'] !== null) {
            $m = ss2_model($db, (int) $f['course_id'], $uid);
            if (!isset($m['topics'][(int) $f['topic_id']]) || !$m['topics'][(int) $f['topic_id']]['open']) return null;
        }
        $base = realpath(storage_path('selfstudy'));
        $abs = $base ? realpath($base . '/' . ltrim(str_replace('\\', '/', (string) $f['file_path']), '/')) : false;
        if (!$abs || !is_file($abs) || strncmp($abs, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0) return null;
        $f['abs'] = $abs;
        return $f;
    }
}
