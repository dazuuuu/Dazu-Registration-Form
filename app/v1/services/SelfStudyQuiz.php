<?php
// app/v1/services/SelfStudyQuiz.php — Self-study step 3: module quizzes, the Checkpoint Exam, the Final Exam, tries, resets and the result.
// Same rules for every course, all enforced here on the server:
//   module quiz:        pass 50%, 3 tries per week (per module), no timer
//   Checkpoint / Final: pass 70%, 2 tries per day and 10 tries per month (each exam counted on its own), a countdown kept by the server
//   a day ends at midnight Nairobi time, a week starts on Monday, a month is the calendar month (Nairobi)
//   a try counts the moment the attempt STARTS; only one attempt can be open at a time; starting and submitting run in a transaction
//   questions: multiple choice and short answers only (never long answers), drawn at random, shuffled, marked at once, correct answers never shown
// A passed item stays passed. Nothing here touches marks, the course average or certificates.
require_once __DIR__ . '/SelfStudyStudy.php';

if (!function_exists('ss3_start')) {

    define('SS3_KINDS', [
        'module_quiz'     => ['label' => 'Quiz',            'pass' => SS3_QUIZ_PASS, 'limits' => ['week' => 3],              'timed' => false, 'place' => 'module_quiz',     'count_col' => 'quiz_questions',       'min_col' => null],
        'checkpoint_exam' => ['label' => 'Checkpoint Exam', 'pass' => SS3_EXAM_PASS, 'limits' => ['day' => 2, 'month' => 10], 'timed' => true,  'place' => 'checkpoint_exam', 'count_col' => 'checkpoint_questions', 'min_col' => 'checkpoint_minutes'],
        'final_exam'      => ['label' => 'Final Exam',      'pass' => SS3_EXAM_PASS, 'limits' => ['day' => 2, 'month' => 10], 'timed' => true,  'place' => 'final_exam',      'count_col' => 'final_questions',      'min_col' => 'final_minutes'],
    ]);

    function ss3_installed(PDO $db): bool
    {
        try { foreach (['self_study_attempts', 'self_study_attempt_answers', 'self_study_reset_requests', 'self_study_resets'] as $t) $db->query("SELECT 1 FROM $t WHERE 1 = 0"); return true; }
        catch (Throwable $e) { return false; }
    }

    /* ------------------------------ Nairobi time windows ------------------------------ */

    /** [start, end) timestamps of today, this week (Monday to Monday) and this month, in Nairobi time. */
    function ss3_windows(int $now): array
    {
        $tz = new DateTimeZone('Africa/Nairobi');
        $d = (new DateTime('@' . $now))->setTimezone($tz);
        $day = (clone $d)->setTime(0, 0, 0);
        $week = (clone $day)->modify('-' . ((int) $d->format('N') - 1) . ' days');
        $month = (clone $day)->setDate((int) $d->format('Y'), (int) $d->format('n'), 1);
        return [
            'day'   => [$day->getTimestamp(),   (clone $day)->modify('+1 day')->getTimestamp()],
            'week'  => [$week->getTimestamp(),  (clone $week)->modify('+7 days')->getTimestamp()],
            'month' => [$month->getTimestamp(), (clone $month)->modify('+1 month')->getTimestamp()],
        ];
    }

    /** "resets on Monday", "back at midnight", "back on 1 December". */
    function ss3_back_text(string $window, int $at): string
    {
        $d = (new DateTime('@' . $at))->setTimezone(new DateTimeZone('Africa/Nairobi'));
        return $window === 'day' ? 'back at midnight' : ($window === 'week' ? 'resets on Monday' : 'back on ' . $d->format('j F'));
    }

    /**
     * How many tries are used in each window and how many are left. A try cleared by a granted reset no longer counts.
     * @return array{windows:array<string,array{limit:int,used:int,left:int,resets_at:int}>, blocked:bool, blocking:?string, next_at:?int, left:int}
     */
    function ss3_tries(PDO $db, int $uid, int $courseId, string $kind, ?int $moduleId, int $now): array
    {
        $info = SS3_KINDS[$kind]; $w = ss3_windows($now);
        $from = min(array_map(fn($n) => $w[$n][0], array_keys($info['limits'])));
        $sql = 'SELECT started_at FROM self_study_attempts WHERE student_id = ? AND course_id = ? AND kind = ? AND tries_cleared_at IS NULL AND started_at >= ?';
        $args = [$uid, $courseId, $kind, ss2_ts($from)];
        if ($kind === 'module_quiz') { $sql .= ' AND module_id = ?'; $args[] = (int) $moduleId; }
        $times = array_map(fn($r) => ss2_parse($r['started_at']), ss2_rows($db, $sql, $args));
        $out = []; $blocking = null; $next = null; $min = PHP_INT_MAX;
        foreach ($info['limits'] as $name => $limit) {
            $used = count(array_filter($times, fn($t) => $t >= $w[$name][0] && $t < $w[$name][1]));
            $out[$name] = ['limit' => $limit, 'used' => $used, 'left' => max(0, $limit - $used), 'resets_at' => $w[$name][1]];
            $min = min($min, $out[$name]['left']);
            if ($used >= $limit && ($next === null || $w[$name][1] > $next)) { $next = $w[$name][1]; $blocking = $name; }
        }
        return ['windows' => $out, 'blocked' => $blocking !== null, 'blocking' => $blocking, 'next_at' => $next, 'left' => $min];
    }

    /** The line under the ring: "Try 2 of 3 used this week · 1 try left · resets on Monday". */
    function ss3_tries_text(array $t, string $kind): string
    {
        $parts = [];
        foreach ($t['windows'] as $name => $x) {
            $label = $name === 'week' ? 'this week' : ($name === 'day' ? 'today' : 'this month');
            $left = $x['left'] === 1 ? '1 try left' : $x['left'] . ' tries left';
            $parts[] = ($name === 'month' ? 'Tries ' : 'Try ') . $x['used'] . ' of ' . $x['limit'] . ' used ' . $label . ' · ' . $left . ' · ' . ss3_back_text($name, $x['resets_at']);
        }
        return implode('  |  ', $parts);
    }

    /* ------------------------------ course settings, state of one item ------------------------------ */

    function ss3_course_settings(PDO $db, int $courseId): array
    {
        $r = ss2_rows($db, 'SELECT quiz_questions, checkpoint_questions, final_questions, checkpoint_minutes, final_minutes FROM self_study_courses WHERE id = ?', [$courseId])[0] ?? [];
        return ['quiz_questions' => (int) ($r['quiz_questions'] ?? 12), 'checkpoint_questions' => (int) ($r['checkpoint_questions'] ?? 30), 'final_questions' => (int) ($r['final_questions'] ?? 40),
                'checkpoint_minutes' => (int) ($r['checkpoint_minutes'] ?? 45), 'final_minutes' => (int) ($r['final_minutes'] ?? 60)];
    }

    /** The quiz / exam in the study-screen model: [item, label, module] or null when this kind and module are not in the course. */
    function ss3_item(array $model, string $kind, ?int $moduleId): ?array
    {
        if ($kind === 'module_quiz') {
            foreach ($model['modules'] as $m) if ($m['id'] === (int) $moduleId) return ['item' => $m['quiz'], 'label' => 'Module ' . $m['no'] . ' quiz', 'module' => $m];
            return null;
        }
        if ($kind === 'checkpoint_exam') return ['item' => $model['checkpoint'], 'label' => 'Checkpoint Exam', 'module' => null];
        if ($kind === 'final_exam') return ['item' => $model['final'], 'label' => 'Final Exam', 'module' => null];
        return null;
    }

    function ss3_open_attempt(PDO $db, int $uid): ?array
    {
        return ss2_rows($db, "SELECT * FROM self_study_attempts WHERE student_id = ? AND status = 'in_progress' ORDER BY id DESC LIMIT 1", [$uid])[0] ?? null;
    }

    function ss3_latest_attempt(PDO $db, int $uid, int $courseId, string $kind, ?int $moduleId): ?array
    {
        $sql = "SELECT * FROM self_study_attempts WHERE student_id = ? AND course_id = ? AND kind = ? AND status IN ('submitted', 'timed_out')";
        $args = [$uid, $courseId, $kind];
        if ($kind === 'module_quiz') { $sql .= ' AND module_id = ?'; $args[] = (int) $moduleId; }
        return ss2_rows($db, $sql . ' ORDER BY id DESC LIMIT 1', $args)[0] ?? null;
    }

    /* ------------------------------ starting an attempt ------------------------------ */

    /**
     * Start (or continue) an attempt. One transaction, and the student's row is locked first, so two quick clicks can never make two attempts or two tries.
     * @return array{ok:bool, attempt?:array, resumed?:bool, error?:string, code?:string, next_at?:?int}
     */
    function ss3_start(PDO $db, int $uid, int $courseId, string $kind, ?int $moduleId, ?int $now = null): array
    {
        $now ??= time();
        if (!isset(SS3_KINDS[$kind])) return ['ok' => false, 'code' => 'bad', 'error' => 'That is not a quiz or exam.'];
        $own = !$db->inTransaction();
        if ($own) $db->beginTransaction();
        try {
            $db->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE')->execute([$uid]);       // serialises this student's starts and submits
            ss3_expire_due($db, $now, $uid);
            $open = ss3_open_attempt($db, $uid);
            if ($open) {
                $same = (int) $open['course_id'] === $courseId && $open['kind'] === $kind && (int) ($open['module_id'] ?? 0) === (int) ($moduleId ?? 0);
                if ($own) $db->commit();
                return $same ? ['ok' => true, 'attempt' => $open, 'resumed' => true]
                             : ['ok' => false, 'code' => 'other_open', 'attempt' => $open, 'error' => 'You already have a quiz or exam open. Finish it first.'];
            }
            $model = ss2_model($db, $courseId, $uid);
            $it = ss3_item($model, $kind, $moduleId);
            if (!$it || !$it['item']['open']) { if ($own) $db->rollBack(); return ['ok' => false, 'code' => 'locked', 'error' => $it['item']['reason'] ?? 'That is not open yet.']; }
            if ($it['item']['passed']) { if ($own) $db->rollBack(); return ['ok' => false, 'code' => 'passed', 'error' => 'You have already passed this.']; }
            if (!empty($it['item']['updating'])) { if ($own) $db->rollBack(); return ['ok' => false, 'code' => 'updating', 'error' => 'This ' . ($kind === 'module_quiz' ? 'quiz' : 'exam') . ' is being updated. Please check again soon.']; }
            $tries = ss3_tries($db, $uid, $courseId, $kind, $moduleId, $now);
            if ($tries['blocked']) { if ($own) $db->rollBack(); return ['ok' => false, 'code' => 'tries', 'error' => 'You have used all your tries.', 'next_at' => $tries['next_at']]; }

            $info = SS3_KINDS[$kind]; $set = ss3_course_settings($db, $courseId);
            $sql = "SELECT id, qtype, options_json FROM self_study_questions WHERE course_id = ? AND place = ? AND qtype IN ('multiple_choice', 'short_answer') AND archived_at IS NULL";
            $args = [$courseId, $info['place']];
            if ($kind === 'module_quiz') { $sql .= ' AND module_id = ?'; $args[] = (int) $moduleId; }
            $bank = ss2_rows($db, $sql, $args);
            if (count($bank) < max(1, $set[$info['count_col']])) { if ($own) $db->rollBack(); return ['ok' => false, 'code' => 'updating', 'error' => 'This ' . ($kind === 'module_quiz' ? 'quiz' : 'exam') . ' is being updated. Please check again soon.']; }
            shuffle($bank);
            $drawn = [];
            foreach (array_slice($bank, 0, max(1, $set[$info['count_col']])) as $q) {
                $o = $q['qtype'] === 'multiple_choice' ? range(0, max(0, count(ss2_options($q['options_json'])) - 1)) : [];
                shuffle($o);
                $drawn[] = ['q' => (int) $q['id'], 'o' => $o];
            }
            $ends = $info['timed'] ? ss2_ts($now + max(1, $set[$info['min_col']]) * 60) : null;
            $db->prepare("INSERT INTO self_study_attempts (student_id, course_id, kind, module_id, started_at, ends_at, status, question_ids) VALUES (?, ?, ?, ?, ?, ?, 'in_progress', ?)")
               ->execute([$uid, $courseId, $kind, $kind === 'module_quiz' ? $moduleId : null, ss2_ts($now), $ends, json_encode($drawn)]);
            $id = (int) $db->lastInsertId();
            if ($own) $db->commit();
        } catch (Throwable $e) {
            if ($own && $db->inTransaction()) $db->rollBack();
            error_log('selfstudy start: ' . $e->getMessage());
            return ['ok' => false, 'code' => 'error', 'error' => 'The attempt could not be started. Please try again.'];
        }
        return ['ok' => true, 'attempt' => ss2_rows($db, 'SELECT * FROM self_study_attempts WHERE id = ?', [$id])[0], 'resumed' => false];
    }

    /* ------------------------------ taking it ------------------------------ */

    function ss3_seconds_left(array $attempt, int $now): ?int
    {
        $e = ss2_parse($attempt['ends_at'] ?? null);
        return $e === null ? null : max(0, $e - $now);
    }

    /** Everything the take screen needs. Correct answers are never part of it. Null when the attempt is not this student's. */
    function ss3_attempt_view(PDO $db, int $uid, int $attemptId, ?int $now = null): ?array
    {
        $now ??= time();
        ss3_expire_due($db, $now, $uid);
        $a = ss2_rows($db, 'SELECT * FROM self_study_attempts WHERE id = ? AND student_id = ?', [$attemptId, $uid])[0] ?? null;
        if (!$a) return null;
        $set = json_decode((string) $a['question_ids'], true) ?: [];
        $ids = array_map(fn($x) => (int) $x['q'], $set);
        $by = [];
        if ($ids) foreach (ss2_rows($db, 'SELECT id, qtype, question_text, options_json FROM self_study_questions WHERE id IN (' . implode(',', $ids) . ')') as $q) $by[(int) $q['id']] = $q;
        $saved = [];
        foreach (ss2_rows($db, 'SELECT question_id, answer FROM self_study_attempt_answers WHERE attempt_id = ?', [$attemptId]) as $r) $saved[(int) $r['question_id']] = (string) $r['answer'];
        $qs = [];
        foreach ($set as $x) {
            $q = $by[(int) $x['q']] ?? null; if (!$q) continue;
            $opts = ss2_options($q['options_json']); $list = [];
            if ($q['qtype'] === 'multiple_choice') foreach ($x['o'] as $oi) if (isset($opts[$oi])) $list[] = [(int) $oi, $opts[$oi]];
            $qs[] = ['id' => (int) $q['id'], 'type' => $q['qtype'], 'text' => (string) $q['question_text'], 'options' => $list, 'saved' => $saved[(int) $q['id']] ?? null];
        }
        return ['attempt' => $a, 'questions' => $qs, 'seconds_left' => ss3_seconds_left($a, $now)];
    }

    /** Save one answer as the student goes. Refused after the time is up (the attempt is then submitted for them). */
    function ss3_save_answer(PDO $db, int $uid, int $attemptId, int $questionId, $answer, ?int $now = null): array
    {
        $now ??= time();
        $a = ss2_rows($db, 'SELECT * FROM self_study_attempts WHERE id = ? AND student_id = ?', [$attemptId, $uid])[0] ?? null;
        if (!$a) return ['ok' => false, 'error' => 'That attempt was not found.'];
        if ($a['status'] !== 'in_progress') return ['ok' => false, 'done' => true, 'error' => 'This attempt is finished.'];
        $left = ss3_seconds_left($a, $now);
        if ($left !== null && $left <= 0) { ss3_finalize($db, $attemptId, 'timed_out', $now); return ['ok' => false, 'done' => true, 'error' => 'Time is up. Your answers were submitted.']; }
        $set = json_decode((string) $a['question_ids'], true) ?: [];
        if (!in_array($questionId, array_map(fn($x) => (int) $x['q'], $set), true)) return ['ok' => false, 'error' => 'That question is not in this attempt.'];
        $q = ss2_rows($db, 'SELECT qtype, options_json FROM self_study_questions WHERE id = ?', [$questionId])[0] ?? null;
        if (!$q) return ['ok' => false, 'error' => 'That question was not found.'];
        if ($q['qtype'] === 'multiple_choice') {
            $i = (is_int($answer) || (is_string($answer) && ctype_digit($answer))) ? (int) $answer : -1;
            if ($i < 0 || $i >= count(ss2_options($q['options_json']))) return ['ok' => false, 'error' => 'Choose one of the answers.'];
            $val = (string) $i;
        } else {
            $val = mb_substr(trim((string) (is_scalar($answer) ? $answer : '')), 0, 300);
        }
        $ts = ss2_ts($now);
        $up = $db->prepare('UPDATE self_study_attempt_answers SET answer = ?, saved_at = ? WHERE attempt_id = ? AND question_id = ?');
        $up->execute([$val, $ts, $attemptId, $questionId]);
        if ($up->rowCount() === 0) {
            try { $db->prepare('INSERT INTO self_study_attempt_answers (attempt_id, question_id, answer, saved_at) VALUES (?, ?, ?, ?)')->execute([$attemptId, $questionId, $val, $ts]); }
            catch (Throwable $e) { $up->execute([$val, $ts, $attemptId, $questionId]); }       // a parallel save created it first
        }
        return ['ok' => true, 'seconds_left' => $left];
    }

    /** The student presses Submit. */
    function ss3_submit(PDO $db, int $uid, int $attemptId, ?int $now = null): array
    {
        $now ??= time();
        $a = ss2_rows($db, 'SELECT * FROM self_study_attempts WHERE id = ? AND student_id = ?', [$attemptId, $uid])[0] ?? null;
        if (!$a) return ['ok' => false, 'error' => 'That attempt was not found.'];
        $left = ss3_seconds_left($a, $now);
        $r = ss3_finalize($db, $attemptId, ($left !== null && $left <= 0) ? 'timed_out' : 'submitted', $now);
        return $r ? ['ok' => true, 'attempt' => $r] : ['ok' => false, 'error' => 'The attempt could not be submitted.'];
    }

    /** Submit every attempt whose time is up (for one student, or for everyone from the cron tool): the answers saved so far are marked. */
    function ss3_expire_due(PDO $db, int $now, ?int $uid = null): int
    {
        $sql = "SELECT id FROM self_study_attempts WHERE status = 'in_progress' AND ends_at IS NOT NULL AND ends_at <= ?";
        $args = [ss2_ts($now)];
        if ($uid) { $sql .= ' AND student_id = ?'; $args[] = $uid; }
        $n = 0;
        foreach (ss2_rows($db, $sql, $args) as $r) if (ss3_finalize($db, (int) $r['id'], 'timed_out', $now)) $n++;
        return $n;
    }

    /**
     * Mark an attempt (once, under a row lock), then update the study-screen progress. Unanswered questions count as wrong.
     * @return array|null the attempt as it now is
     */
    function ss3_finalize(PDO $db, int $attemptId, string $status, int $now): ?array
    {
        $own = !$db->inTransaction();
        $justDone = false;
        if ($own) $db->beginTransaction();
        try {
            $st = $db->prepare('SELECT * FROM self_study_attempts WHERE id = ? FOR UPDATE');
            $st->execute([$attemptId]);
            $a = $st->fetch(PDO::FETCH_ASSOC);
            if (!$a) { if ($own) $db->rollBack(); return null; }
            if ($a['status'] === 'in_progress') {
                $set = json_decode((string) $a['question_ids'], true) ?: [];
                $ids = array_map(fn($x) => (int) $x['q'], $set);
                $qs = []; $given = [];
                if ($ids) {
                    foreach (ss2_rows($db, 'SELECT id, qtype, options_json, correct_index, accepted_answers FROM self_study_questions WHERE id IN (' . implode(',', $ids) . ')') as $q) $qs[(int) $q['id']] = $q;
                    foreach (ss2_rows($db, 'SELECT question_id, answer FROM self_study_attempt_answers WHERE attempt_id = ?', [$attemptId]) as $r) $given[(int) $r['question_id']] = $r['answer'];
                }
                $correct = 0; $total = 0; $mark = $db->prepare('UPDATE self_study_attempt_answers SET is_correct = ? WHERE attempt_id = ? AND question_id = ?');
                $ins = $db->prepare('INSERT INTO self_study_attempt_answers (attempt_id, question_id, answer, is_correct, saved_at) VALUES (?, ?, NULL, 0, ?)');
                foreach ($ids as $qid) {
                    if (!isset($qs[$qid])) continue;
                    $total++;
                    $ok = false;
                    if (array_key_exists($qid, $given) && $given[$qid] !== null && $given[$qid] !== '') [$ok] = ss2_grade($qs[$qid], $given[$qid]);
                    if ($ok) $correct++;
                    if (array_key_exists($qid, $given)) $mark->execute([$ok ? 1 : 0, $attemptId, $qid]);
                    else $ins->execute([$attemptId, $qid, ss2_ts($now)]);
                }
                $pct = $total > 0 ? (int) floor($correct * 100 / $total) : 0;
                $pass = $pct >= SS3_KINDS[$a['kind']]['pass'];
                $db->prepare('UPDATE self_study_attempts SET status = ?, submitted_at = ?, score_percent = ?, correct_count = ?, total_count = ?, passed = ? WHERE id = ?')
                   ->execute([$status === 'timed_out' ? 'timed_out' : 'submitted', ss2_ts($now), $pct, $correct, $total, $pass ? 1 : 0, $attemptId]);
                $justDone = true;
            }
            if ($own) $db->commit();
        } catch (Throwable $e) {
            if ($own && $db->inTransaction()) $db->rollBack();
            error_log('selfstudy finalize: ' . $e->getMessage());
            return null;
        }
        $a = ss2_rows($db, 'SELECT * FROM self_study_attempts WHERE id = ?', [$attemptId])[0];
        if ($justDone) {
            try {
                $uid = (int) $a['student_id']; $cid = (int) $a['course_id'];
                if ($a['passed']) {
                    $model = ss2_model($db, $cid, $uid);
                    ss2_save_progress($db, $uid, $cid, $now, $model);             // the bars and the saved percent move on
                    if ($a['kind'] === 'final_exam') ss3_course_finished($db, $uid, $cid, $now);
                } else {
                    ss2_save_progress($db, $uid, $cid, $now);                     // a failed try still counts as "last studied"
                }
            } catch (Throwable $e) { error_log('selfstudy after-submit: ' . $e->getMessage()); }
        }
        return $a;
    }

    /** Final Exam passed: set the completed date (the green Completed badge) and tell the tutor. Never a certificate, never a mark. */
    function ss3_course_finished(PDO $db, int $uid, int $courseId, int $now): void
    {
        $ts = ss2_ts($now);
        $db->prepare('UPDATE self_study_progress SET completed_at = COALESCE(completed_at, ?), percent_complete = 100, last_studied_at = ? WHERE course_id = ? AND student_id = ?')->execute([$ts, $ts, $courseId, $uid]);
        $c = ss2_rows($db, 'SELECT c.title, t.user_id AS tutor_user FROM self_study_courses c LEFT JOIN tutors t ON t.id = c.owner_tutor_id WHERE c.id = ?', [$courseId])[0] ?? null;
        if ($c && (int) $c['tutor_user'] > 0) {
            ss3_notify([(int) $c['tutor_user']], 'quiz', ss3_student_name($db, $uid) . ' passed the Final Exam', 'They finished the self-study course "' . $c['title'] . '".', 'tutor/progress/index.php', $db);
        }
    }

    /* ------------------------------ the result ------------------------------ */

    /**
     * The result screen's data: score, message numbers, the wrong questions only (with the topic or module to read again), and the side boxes.
     * The correct answers and the student's own answers are never in it.
     */
    function ss3_result(PDO $db, int $uid, array $attempt, array $model, ?int $now = null): array
    {
        $now ??= time();
        $kind = $attempt['kind']; $courseId = (int) $attempt['course_id']; $moduleId = $attempt['module_id'] !== null ? (int) $attempt['module_id'] : null;
        $set = json_decode((string) $attempt['question_ids'], true) ?: [];
        $ids = array_map(fn($x) => (int) $x['q'], $set);
        $by = [];
        if ($ids) foreach (ss2_rows($db, 'SELECT id, question_text, topic_id, module_id FROM self_study_questions WHERE id IN (' . implode(',', $ids) . ')') as $q) $by[(int) $q['id']] = $q;
        $wrong = [];
        foreach (ss2_rows($db, 'SELECT question_id FROM self_study_attempt_answers WHERE attempt_id = ? AND (is_correct = 0 OR is_correct IS NULL)', [(int) $attempt['id']]) as $r) $wrong[(int) $r['question_id']] = true;
        $look = [];
        foreach ($set as $n => $x) {
            $qid = (int) $x['q'];
            if (!isset($wrong[$qid]) || !isset($by[$qid])) continue;
            $q = $by[$qid]; $link = null;
            $tid = $q['topic_id'] !== null ? (int) $q['topic_id'] : 0;
            if ($tid && isset($model['topics'][$tid])) { $t = $model['topics'][$tid]; $link = ['topic' => $tid, 'label' => ($t['number'] !== '' ? $t['number'] . ' ' : '') . $t['title'], 'open' => $t['open']]; }
            else {                                                       // no topic tag: the module (its first topic)
                $mid = $moduleId ?? ($q['module_id'] !== null ? (int) $q['module_id'] : 0);
                foreach ($model['modules'] as $m) if ($m['id'] === $mid && $m['topics']) $link = ['topic' => $m['topics'][0]['id'], 'label' => $m['title'], 'open' => $m['topics'][0]['open']];
            }
            $look[] = ['n' => $n + 1, 'text' => (string) $q['question_text'], 'link' => $link];
        }
        $info = SS3_KINDS[$kind];
        $it = ss3_item($model, $kind, $moduleId);
        $tries = ss3_tries($db, $uid, $courseId, $kind, $moduleId, $now);
        $set2 = ss3_course_settings($db, $courseId);
        $firstLink = null; foreach ($look as $l) if ($l['link'] && $l['link']['open']) { $firstLink = $l['link']; break; }
        if (!$firstLink && $it && $it['module'] && $it['module']['topics']) $firstLink = ['topic' => $it['module']['topics'][0]['id'], 'label' => 'topic ' . $it['module']['topics'][0]['number'], 'open' => true];
        $waiting = (int) ss2_rows($db, "SELECT COUNT(*) AS n FROM self_study_reset_requests WHERE student_id = ? AND course_id = ? AND kind = ? AND status = 'waiting'" . ($kind === 'module_quiz' ? ' AND module_id = ?' : ''),
            $kind === 'module_quiz' ? [$uid, $courseId, $kind, $moduleId] : [$uid, $courseId, $kind])[0]['n'] > 0;
        return [
            'label' => $it['label'] ?? $info['label'], 'percent' => (int) $attempt['score_percent'], 'correct' => (int) $attempt['correct_count'], 'total' => (int) $attempt['total_count'],
            'passed' => (bool) $attempt['passed'], 'timed_out' => $attempt['status'] === 'timed_out', 'pass' => $info['pass'], 'tries' => $tries, 'tries_text' => ss3_tries_text($tries, $kind),
            'look' => $look, 'first_link' => $firstLink, 'waiting' => $waiting, 'item_passed' => (bool) ($it['item']['passed'] ?? false),
            'next_module' => ($it && $it['module']) ? (function () use ($model, $it) { foreach ($model['modules'] as $m) if ($m['no'] === $it['module']['no'] + 1) return $m; return null; })() : null,
            'rules' => ['pass' => $info['pass'], 'timed' => $info['timed'], 'minutes' => $info['min_col'] ? $set2[$info['min_col']] : null, 'limits' => $info['limits']],
            'next' => ss3_next_steps($model, $kind, $moduleId),
        ];
    }

    /** "What happens next": every earlier item, the current one, and the locked ones (later modules are grouped, e.g. "Modules 3, 4 and 5"). */
    function ss3_next_steps(array $model, string $kind, ?int $moduleId): array
    {
        $rows = []; $pendingLocked = [];
        $flush = function () use (&$rows, &$pendingLocked) {
            if (!$pendingLocked) return;
            $nums = array_map(fn($m) => $m['no'], $pendingLocked);
            $last = array_pop($nums);
            $rows[] = ['state' => 'locked', 'title' => ($nums ? 'Modules ' . implode(', ', $nums) . ' and ' . $last : 'Module ' . $last), 'sub' => ''];
            $pendingLocked = [];
        };
        foreach ($model['modules'] as $m) {
            $cur = $kind === 'module_quiz' && $m['id'] === $moduleId; $q = $m['quiz'];
            if ($q['passed']) { $flush(); $rows[] = ['state' => 'passed', 'title' => 'Module ' . $m['no'] . ' quiz', 'sub' => 'Passed' . ($q['best'] !== null ? ' ' . $q['best'] . '%' : '')]; }
            elseif ($cur) { $flush(); $rows[] = ['state' => 'current', 'title' => 'Module ' . $m['no'] . ' quiz' . ($q['best'] !== null ? ' · ' . $q['best'] . '%' : ''), 'sub' => $q['pass'] . '% needed']; }
            elseif ($q['open']) { $flush(); $rows[] = ['state' => 'open', 'title' => 'Module ' . $m['no'] . ' quiz', 'sub' => 'Open · ' . $q['pass'] . '% needed']; }
            else $pendingLocked[] = $m;
        }
        $flush();
        foreach ([['checkpoint_exam', 'Checkpoint Exam', $model['checkpoint']], ['final_exam', 'Final Exam', $model['final']]] as [$k, $name, $x]) {
            $rows[] = $x['passed'] ? ['state' => 'passed', 'title' => $name, 'sub' => 'Passed' . ($x['best'] !== null ? ' ' . $x['best'] . '%' : '')]
                : ($kind === $k ? ['state' => 'current', 'title' => $name . ($x['best'] !== null ? ' · ' . $x['best'] . '%' : ''), 'sub' => $x['pass'] . '% needed']
                : ($x['open'] ? ['state' => 'open', 'title' => $name, 'sub' => 'Open · ' . $x['pass'] . '% needed'] : ['state' => 'locked', 'title' => $name, 'sub' => '']));
        }
        return $rows;
    }

    /* ------------------------------ resets ------------------------------ */

    /** The names of many users in ONE query: id => name. */
    function ss3_student_names(PDO $db, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
        $out = [];
        foreach (array_chunk($ids, 400) as $ch) {
            foreach (ss2_rows($db, "SELECT u.id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))), ''), u.username, 'A student') AS n FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id IN (" . implode(',', $ch) . ')') as $r) $out[(int) $r['id']] = (string) $r['n'];
        }
        return $out;
    }

    function ss3_student_name(PDO $db, int $uid): string
    {
        $r = ss2_rows($db, "SELECT COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))), ''), u.username, 'A student') AS n FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = ?", [$uid])[0] ?? null;
        return (string) ($r['n'] ?? 'A student');
    }

    /** Notification through the existing portal notification system. */
    function ss3_notify(array $userIds, string $type, string $title, string $body, string $path, PDO $db): void
    {
        if (!class_exists('PortalNotifications') && is_file(__DIR__ . '/PortalNotifications.php')) require_once __DIR__ . '/PortalNotifications.php';
        if (!class_exists('PortalNotifications')) return;
        foreach (array_unique(array_filter(array_map('intval', $userIds))) as $u) {
            try { PortalNotifications::notify($db, $u, $type, $title, $body, $path); } catch (Throwable $e) { error_log('selfstudy notify: ' . $e->getMessage()); }
        }
    }

    /**
     * "Ask my tutor for a reset": saves a waiting request and tells the course tutor, the student's own tutor and admin.
     * @return array{ok:bool,error?:string,id?:int}
     */
    function ss3_request_reset(PDO $db, int $uid, int $courseId, string $kind, ?int $moduleId, string $reason, ?int $now = null): array
    {
        $now ??= time();
        $reason = trim(preg_replace('/\s+/u', ' ', $reason));
        if ($reason === '') return ['ok' => false, 'error' => 'Please write a short reason.'];
        $reason = mb_substr($reason, 0, 200);
        if (!isset(SS3_KINDS[$kind])) return ['ok' => false, 'error' => 'That is not a quiz or exam.'];
        $it = ss3_item(ss2_model($db, $courseId, $uid), $kind, $moduleId);
        if (!$it || !$it['item']['open'] || $it['item']['passed']) return ['ok' => false, 'error' => 'There is nothing to reset here.'];
        $mod = $kind === 'module_quiz' ? (int) $moduleId : null;
        $dupe = ss2_rows($db, "SELECT id FROM self_study_reset_requests WHERE student_id = ? AND course_id = ? AND kind = ? AND status = 'waiting'" . ($mod ? ' AND module_id = ?' : ''), $mod ? [$uid, $courseId, $kind, $mod] : [$uid, $courseId, $kind]);
        if ($dupe) return ['ok' => false, 'error' => 'Your request is already waiting for your tutor.'];
        $db->prepare("INSERT INTO self_study_reset_requests (student_id, course_id, kind, module_id, reason, status, created_at) VALUES (?, ?, ?, ?, ?, 'waiting', ?)")->execute([$uid, $courseId, $kind, $mod, $reason, ss2_ts($now)]);
        $id = (int) $db->lastInsertId();
        $c = ss2_rows($db, 'SELECT c.title, t.user_id AS tutor_user FROM self_study_courses c LEFT JOIN tutors t ON t.id = c.owner_tutor_id WHERE c.id = ?', [$courseId])[0] ?? [];
        $own = array_column(ss2_rows($db, 'SELECT t.user_id FROM tutor_students ts JOIN tutors t ON t.id = ts.tutor_id WHERE ts.user_id = ?', [$uid]), 'user_id');
        $admins = array_column(ss2_rows($db, "SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.role_name IN ('admin', 'superadmin') AND u.is_active = 1"), 'id');
        $title = ss3_student_name($db, $uid) . ' asked for a reset';
        $body = $it['label'] . ' · ' . ($c['title'] ?? 'Self-study') . ': ' . $reason;
        ss3_notify(array_merge([(int) ($c['tutor_user'] ?? 0)], $own), 'quiz', $title, $body, 'tutor/progress/index.php', $db);
        ss3_notify($admins, 'quiz', $title, $body, 'admin/progress/index.php', $db);
        return ['ok' => true, 'id' => $id];
    }

    /**
     * Grant a reset (the tutor screen calls this in a later step). It clears only the tries counted in the window that is blocking the student
     * (this week for a module quiz; for an exam this month if the month is used up, otherwise today), keeps every attempt, and writes the record.
     * @return array{ok:bool,cleared?:int,window?:string,error?:string}
     */
    function ss3_grant_reset(PDO $db, int $uid, int $courseId, string $kind, ?int $moduleId, int $byUserId, string $reason, ?int $requestId = null, ?int $now = null, ?string $note = null): array
    {
        $now ??= time();
        if (!isset(SS3_KINDS[$kind])) return ['ok' => false, 'error' => 'That is not a quiz or exam.'];
        $mod = $kind === 'module_quiz' ? (int) $moduleId : null;
        $t = ss3_tries($db, $uid, $courseId, $kind, $mod, $now);
        $window = $kind === 'module_quiz' ? 'week' : (($t['windows']['month']['left'] ?? 1) === 0 ? 'month' : 'day');
        [$from, $to] = ss3_windows($now)[$window];
        $own = !$db->inTransaction();
        if ($own) $db->beginTransaction();
        try {
            $sql = "UPDATE self_study_attempts SET tries_cleared_at = ? WHERE student_id = ? AND course_id = ? AND kind = ? AND tries_cleared_at IS NULL AND status <> 'in_progress' AND started_at >= ? AND started_at < ?";
            $args = [ss2_ts($now), $uid, $courseId, $kind, ss2_ts($from), ss2_ts($to)];
            if ($mod) { $sql .= ' AND module_id = ?'; $args[] = $mod; }
            $st = $db->prepare($sql); $st->execute($args);
            $n = $st->rowCount();
            $db->prepare('INSERT INTO self_study_resets (student_id, course_id, kind, module_id, reset_by, reason, request_id, tries_cleared, created_at, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
               ->execute([$uid, $courseId, $kind, $mod, $byUserId, mb_substr($reason, 0, 200), $requestId, $n, ss2_ts($now), $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 300) : null]);
            $resetId = (int) $db->lastInsertId();
            if ($requestId) $db->prepare("UPDATE self_study_reset_requests SET status = 'granted', handled_by = ?, handled_at = ? WHERE id = ? AND status = 'waiting'")->execute([$byUserId, ss2_ts($now), $requestId]);
            if ($own) $db->commit();
        } catch (Throwable $e) {
            if ($own && $db->inTransaction()) $db->rollBack();
            return ['ok' => false, 'error' => 'The reset could not be saved.'];
        }
        return ['ok' => true, 'cleared' => $n, 'window' => $window, 'id' => $resetId ?? 0];
    }

    function ss3_decline_request(PDO $db, int $requestId, int $byUserId, ?int $now = null): bool
    {
        $st = $db->prepare("UPDATE self_study_reset_requests SET status = 'declined', handled_by = ?, handled_at = ? WHERE id = ? AND status = 'waiting'");
        $st->execute([$byUserId, ss2_ts($now ?? time()), $requestId]);
        return $st->rowCount() > 0;
    }
}
