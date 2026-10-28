<?php
// app/v1/services/SubjectService.php
// Subjects for assignments (migration 091). A subject belongs to ONE course and ONE tutor. Every assignment and monthly project may be linked to a subject.
//
// THE RULES, in one place:
//  1 A subject mark is NEVER typed by anyone. subjectMarks() calculates it from the marks of the assignments under the subject, by calling
//    AssignmentAverage::compute() (the existing course average service): released marks only, assignment weights as set, newest resubmission,
//    Pass/Not yet and Feedback-only excluded, late work not reduced, missing work not counted as zero. There is no second copy of the maths.
//  2 Status: Not yet (nothing counted) -> In progress -> Final (as many monthly projects marked and released as the tutor said the subject has).
//  3 Result words: Distinction 70+, Credit 60-69, Pass 50-59, Failed below 50. 50 or more passes.
//  4 Admin pages never use this file (a test greps for it). Students never see the subject setup.
//  5 Every creation, change, rename, reorder, removal and every change of an assignment's subject is written to course_subject_events.
// Courses with no subjects behave exactly as before: nothing here touches the course average.

require_once __DIR__ . '/AssignmentAverage.php';

if (!function_exists('subjectMarks')) {
    define('SUBJ_PASS', 50);
    define('SUBJ_MAX_MONTHLY', 12);

    /* ---------------- reading ---------------- */

    /** Subjects of one course for one tutor, in order. */
    function subj_list(PDO $db, int $courseId, int $tutorId, bool $activeOnly = false): array
    {
        $st = $db->prepare('SELECT * FROM course_subjects WHERE course_id = ? AND tutor_id = ?' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, id');
        $st->execute([$courseId, $tutorId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** A subject only if this tutor owns it (tutors.id). */
    function subj_get_own(PDO $db, int $id, int $tutorId): ?array
    {
        $st = $db->prepare('SELECT * FROM course_subjects WHERE id = ? AND tutor_id = ?');
        $st->execute([$id, $tutorId]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** How many assignments (any status, templates too) use this subject. */
    function subj_usage_count(PDO $db, int $subjectId): int
    {
        $st = $db->prepare('SELECT COUNT(*) FROM tutor_assignments WHERE subject_id = ?');
        $st->execute([$subjectId]);
        return (int) $st->fetchColumn();
    }

    /** The subject box is required when the tutor has at least one active subject in the course. */
    function subj_required(PDO $db, int $courseId, int $tutorId): bool
    {
        return $courseId > 0 && count(subj_list($db, $courseId, $tutorId, true)) > 0;
    }

    function subj_log(PDO $db, ?int $subjectId, ?int $courseId, ?int $assignmentId, ?int $actorUserId, string $event, array $details = []): void
    {
        $db->prepare('INSERT INTO course_subject_events (subject_id, course_id, assignment_id, actor_user_id, event, details) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$subjectId, $courseId, $assignmentId, $actorUserId, $event, $details ? json_encode($details) : null]);
    }

    /* ---------------- weights ---------------- */

    /** What the capstone takes off the top, in percent points: its weight when the tutor has a capstone in this course, else 0. */
    function subj_capstone_share(PDO $db, int $courseId, int $tutorId): float
    {
        try {
            $st = $db->prepare('SELECT MAX(weight) FROM capstones WHERE course_id = ? AND tutor_id = ?');
            $st->execute([$courseId, $tutorId]);
            return max(0.0, min(100.0, (float) $st->fetchColumn()));
        } catch (Throwable $e) {
            return 0.0;   // migration 088 not run
        }
    }

    /**
     * The weight each ACTIVE subject really has: the tutor's own number, or the default, an equal share of what is left
     * after the capstone and the weights the tutor set.
     * @return array<int,float> subject id => weight
     */
    function subj_effective_weights(PDO $db, int $courseId, int $tutorId): array
    {
        $active = subj_list($db, $courseId, $tutorId, true);
        $left = 100.0 - subj_capstone_share($db, $courseId, $tutorId);
        $auto = [];
        $out = [];
        foreach ($active as $s) {
            if ($s['weight'] !== null) { $out[(int) $s['id']] = (float) $s['weight']; $left -= (float) $s['weight']; }
            else $auto[] = (int) $s['id'];
        }
        $share = $auto ? round(max(0.0, $left) / count($auto), 2) : 0.0;
        foreach ($auto as $id) $out[$id] = $share;
        return $out;
    }

    /** What a NEW subject would get by default (an equal share, counting it as one more). */
    function subj_default_weight(PDO $db, int $courseId, int $tutorId): float
    {
        $active = subj_list($db, $courseId, $tutorId, true);
        $left = 100.0 - subj_capstone_share($db, $courseId, $tutorId);
        $n = 1;
        foreach ($active as $s) { if ($s['weight'] !== null) $left -= (float) $s['weight']; else $n++; }
        return round(max(0.0, $left) / $n, 2);
    }

    /* ---------------- writing (all check ownership) ---------------- */

    /** Clean the fields of the subject form. Weight '' = the default. @return array{0:array,1:string[]} */
    function subj_clean(array $in): array
    {
        $errors = [];
        $name = trim(preg_replace('/\s+/', ' ', (string) ($in['name'] ?? '')));
        $code = trim((string) ($in['code'] ?? ''));
        $wRaw = trim((string) ($in['weight'] ?? ''));
        $monthly = (int) ($in['monthly_expected'] ?? 1);
        if ($name === '' || mb_strlen($name) > 120) $errors[] = 'Give the subject a name (up to 120 characters).';
        if ($code !== '' && !preg_match('/^[A-Za-z0-9._-]{1,20}$/', $code)) $errors[] = 'The short code can use letters, numbers, dash and dot only (up to 20), for example DS-02.';
        $weight = null;
        if ($wRaw !== '') {
            if (!is_numeric($wRaw) || (float) $wRaw <= 0 || (float) $wRaw > 100) $errors[] = 'Weight must be more than 0 and up to 100, or empty for the default.';
            else $weight = round((float) $wRaw, 2);
        }
        if ($monthly < 1 || $monthly > SUBJ_MAX_MONTHLY) $errors[] = 'Monthly projects must be between 1 and ' . SUBJ_MAX_MONTHLY . '.';
        return [['name' => $name, 'code' => $code === '' ? null : strtoupper($code), 'weight' => $weight, 'monthly_expected' => max(1, min(SUBJ_MAX_MONTHLY, $monthly))], $errors];
    }

    function subj_name_taken(PDO $db, int $courseId, int $tutorId, string $name, int $exceptId = 0): bool
    {
        $st = $db->prepare('SELECT COUNT(*) FROM course_subjects WHERE course_id = ? AND tutor_id = ? AND LOWER(name) = LOWER(?) AND id <> ?');
        $st->execute([$courseId, $tutorId, $name, $exceptId]);
        return (int) $st->fetchColumn() > 0;
    }

    /** @return array{ok:bool, id?:int, error?:string} */
    function subj_create(PDO $db, int $tutorId, int $actorUserId, int $courseId, array $in): array
    {
        [$d, $errors] = subj_clean($in);
        if ($errors) return ['ok' => false, 'error' => implode(' ', $errors)];
        if (subj_name_taken($db, $courseId, $tutorId, $d['name'])) return ['ok' => false, 'error' => 'You already have a subject with that name in this course.'];
        $st = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM course_subjects WHERE course_id = ? AND tutor_id = ?');
        $st->execute([$courseId, $tutorId]);
        $order = (int) $st->fetchColumn();
        $db->prepare('INSERT INTO course_subjects (course_id, tutor_id, name, code, weight, monthly_expected, sort_order, is_active, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)')
           ->execute([$courseId, $tutorId, $d['name'], $d['code'], $d['weight'], $d['monthly_expected'], $order, gmdate('Y-m-d H:i:s')]);
        $id = (int) $db->lastInsertId();
        // The transcript number of the subject (101, 102, ...): given now, once, by the system.
        require_once __DIR__ . '/TranscriptService.php';
        tr_number_one_subject($db, $id, $courseId);
        subj_log($db, $id, $courseId, null, $actorUserId, 'subject_created', $d);
        return ['ok' => true, 'id' => $id];
    }

    /** Rename, code, weight, monthly projects. Allowed even when assignments use the subject. */
    function subj_update(PDO $db, int $tutorId, int $actorUserId, int $id, array $in): array
    {
        $s = subj_get_own($db, $id, $tutorId);
        if (!$s) return ['ok' => false, 'error' => 'Subject not found.'];
        [$d, $errors] = subj_clean($in);
        if ($errors) return ['ok' => false, 'error' => implode(' ', $errors)];
        if (subj_name_taken($db, (int) $s['course_id'], $tutorId, $d['name'], $id)) return ['ok' => false, 'error' => 'You already have a subject with that name in this course.'];
        $changes = [];
        foreach (['name', 'code', 'weight', 'monthly_expected'] as $c) {
            $old = $s[$c] === null ? null : ($c === 'weight' ? (float) $s[$c] : ($c === 'monthly_expected' ? (int) $s[$c] : (string) $s[$c]));
            if ($old !== $d[$c]) $changes[$c] = ['from' => $old, 'to' => $d[$c]];
        }
        if ($changes) {
            $db->prepare('UPDATE course_subjects SET name = ?, code = ?, weight = ?, monthly_expected = ?, updated_at = ? WHERE id = ? AND tutor_id = ?')
               ->execute([$d['name'], $d['code'], $d['weight'], $d['monthly_expected'], gmdate('Y-m-d H:i:s'), $id, $tutorId]);
            subj_log($db, $id, (int) $s['course_id'], null, $actorUserId, isset($changes['name']) ? 'subject_renamed' : 'subject_changed', $changes);
        }
        return ['ok' => true];
    }

    function subj_set_active(PDO $db, int $tutorId, int $actorUserId, int $id, bool $on): array
    {
        $s = subj_get_own($db, $id, $tutorId);
        if (!$s) return ['ok' => false, 'error' => 'Subject not found.'];
        if ((int) $s['is_active'] !== (int) $on) {
            $db->prepare('UPDATE course_subjects SET is_active = ?, updated_at = ? WHERE id = ? AND tutor_id = ?')->execute([(int) $on, gmdate('Y-m-d H:i:s'), $id, $tutorId]);
            subj_log($db, $id, (int) $s['course_id'], null, $actorUserId, $on ? 'subject_activated' : 'subject_inactive', ['name' => $s['name']]);
        }
        return ['ok' => true];
    }

    /** Move one place up (-1) or down (+1). The order is renumbered so there are never ties. */
    function subj_move(PDO $db, int $tutorId, int $actorUserId, int $id, int $dir): array
    {
        $s = subj_get_own($db, $id, $tutorId);
        if (!$s) return ['ok' => false, 'error' => 'Subject not found.'];
        $ids = array_map(fn($r) => (int) $r['id'], subj_list($db, (int) $s['course_id'], $tutorId));
        $i = array_search($id, $ids, true);
        $j = $i + ($dir < 0 ? -1 : 1);
        if ($i === false || $j < 0 || $j >= count($ids)) return ['ok' => true];
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        $up = $db->prepare('UPDATE course_subjects SET sort_order = ? WHERE id = ? AND tutor_id = ?');
        foreach ($ids as $pos => $sid) $up->execute([$pos + 1, $sid, $tutorId]);
        subj_log($db, $id, (int) $s['course_id'], null, $actorUserId, 'subject_reordered', ['order' => $ids]);
        return ['ok' => true];
    }

    /** Remove only when no assignment uses the subject. A subject in use can be renamed or set inactive, never deleted. */
    function subj_remove(PDO $db, int $tutorId, int $actorUserId, int $id): array
    {
        $s = subj_get_own($db, $id, $tutorId);
        if (!$s) return ['ok' => false, 'error' => 'Subject not found.'];
        $n = subj_usage_count($db, $id);
        if ($n > 0) return ['ok' => false, 'error' => 'This subject is used by ' . $n . ' assignment' . ($n === 1 ? '' : 's') . ', so it cannot be removed. You can rename it or set it inactive.'];
        $db->prepare('DELETE FROM course_subjects WHERE id = ? AND tutor_id = ?')->execute([$id, $tutorId]);
        subj_log($db, $id, (int) $s['course_id'], null, $actorUserId, 'subject_removed', ['name' => $s['name']]);
        return ['ok' => true];
    }

    /**
     * The check for the Subject box when an assignment is saved. null = fine, else the message to show.
     * Required only for NEW work ($isNew) in a course where the tutor has active subjects. An assignment that already exists is never forced
     * to get a subject: with no subject it saves without one, as before. (One that already has a subject cannot be set back to none.)
     * The chosen subject must be this tutor's, in this course, and active (an assignment may keep an inactive subject it already has).
     */
    function subj_check_choice(PDO $db, int $courseId, int $tutorId, int $subjectId, int $currentSubjectId = 0, bool $isNew = true): ?string
    {
        if ($subjectId <= 0) {
            $mustHave = $isNew || $currentSubjectId > 0;
            return $mustHave && subj_required($db, $courseId, $tutorId) ? 'Choose a subject for this assignment. It is needed so the subject mark can be worked out.' : null;
        }
        $s = subj_get_own($db, $subjectId, $tutorId);
        if (!$s || (int) $s['course_id'] !== $courseId) return 'That subject is not one of yours in this course.';
        if (!(int) $s['is_active'] && $subjectId !== $currentSubjectId) return 'That subject is inactive. Choose an active subject.';
        return null;
    }

    /** Audit a change of an assignment's subject (call after the save). */
    function subj_log_assignment_change(PDO $db, int $assignmentId, int $courseId, int $actorUserId, int $from, int $to): void
    {
        if ($from === $to) return;
        subj_log($db, $to ?: null, $courseId, $assignmentId, $actorUserId, 'assignment_subject_changed', ['from_subject_id' => $from ?: null, 'to_subject_id' => $to ?: null]);
    }

    /* ---------------- the automatic subject mark ---------------- */

    function subj_result_word(?float $mark): ?string
    {
        if ($mark === null) return null;
        return $mark >= 70 ? 'Distinction' : ($mark >= 60 ? 'Credit' : ($mark >= SUBJ_PASS ? 'Pass' : 'Failed'));
    }

    /** Mark, status and counts of ONE subject from that subject's rows, through the course average service. Pure (no database). */
    function subj_compute(array $subject, array $rows, ?int $now = null): array
    {
        $r = AssignmentAverage::compute($rows, $now);
        $assignments = $monthly = $assignmentsTotal = $monthlyTotal = 0;
        foreach ($r['items'] as $it) {
            $isMonthly = $it['kind'] === 'monthly';
            $isMonthly ? $monthlyTotal++ : $assignmentsTotal++;
            if ($it['released'] && $it['status'] === 'graded') $isMonthly ? $monthly++ : $assignments++;
        }
        $mark = $r['average'] === null ? null : round($r['average'], 2);
        $expected = max(1, (int) $subject['monthly_expected']);
        $status = $r['counted'] === 0 ? 'Not yet' : ($monthly >= $expected ? 'Final' : 'In progress');
        return [
            'subject_id' => (int) $subject['id'], 'name' => (string) $subject['name'], 'code' => $subject['code'],
            'weight' => isset($subject['effective_weight']) ? (float) $subject['effective_weight'] : ($subject['weight'] === null ? null : (float) $subject['weight']),
            'monthly_expected' => $expected, 'active' => (bool) $subject['is_active'],
            'mark' => $mark, 'display' => $mark === null ? 'No marks yet' : rtrim(rtrim(number_format($mark, 1, '.', ''), '0'), '.') . '%',
            'status' => $status, 'word' => subj_result_word($mark), 'passed' => $mark === null ? null : $mark >= SUBJ_PASS,
            'counted' => $r['counted'], 'assignments_marked' => $assignments, 'monthly_marked' => $monthly,
            'assignments_total' => $assignmentsTotal, 'monthly_total' => $monthlyTotal,
        ];
    }

    /**
     * Each subject of the course for one student: mark, status (Not yet, In progress, Final) and result word.
     * $at (UTC timestamp) returns the marks as they were released at that time (marks released later are left out); null = now.
     * Nobody can type these numbers: they are worked out from the assignment marks every time.
     */
    function subjectMarks(PDO $db, int $studentUserId, int $courseId, ?int $at = null): array
    {
        $now = $at ?? time();
        $by = [];
        foreach (AssignmentAverage::rowsForStudent($db, $studentUserId) as $r) {
            if ((int) $r['course_id'] !== $courseId || empty($r['subject_id'])) continue;
            // A mark published after $at did not exist yet at that time.
            if ($at !== null && !empty($r['result_published_at']) && strtotime($r['result_published_at'] . ' UTC') > $at) {
                $r['sub_status'] = 'submitted'; $r['result_published_at'] = null; $r['score'] = null; $r['pass_result'] = null;
            }
            $by[(int) $r['subject_id']][] = $r;
        }
        $st = $db->prepare('SELECT * FROM course_subjects WHERE course_id = ? AND (tutor_id IN (SELECT tutor_id FROM tutor_students WHERE user_id = ?)'
            . ($by ? ' OR id IN (' . implode(',', array_map('intval', array_keys($by))) . ')' : '') . ') ORDER BY tutor_id, sort_order, id');
        $st->execute([$courseId, $studentUserId]);
        $out = [];
        $weights = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $tid = (int) $s['tutor_id'];
            $weights[$tid] ??= subj_effective_weights($db, $courseId, $tid);
            $s['effective_weight'] = $weights[$tid][(int) $s['id']] ?? ($s['weight'] === null ? null : (float) $s['weight']);
            if (!(int) $s['is_active'] && empty($by[(int) $s['id']])) continue;   // an inactive subject with no work does not show
            $out[] = subj_compute($s, $by[(int) $s['id']] ?? [], $now);
        }
        return $out;
    }

    /** The card on the tutor's assignment page: this subject for every student who has the assignment. */
    function subj_card(PDO $db, array $assignment, array $studentUserIds): ?array
    {
        $sid = (int) ($assignment['subject_id'] ?? 0);
        if (!$sid) return null;
        $st = $db->prepare('SELECT * FROM course_subjects WHERE id = ? AND tutor_id = ?');
        $st->execute([$sid, (int) $assignment['tutor_id']]);
        $s = $st->fetch(PDO::FETCH_ASSOC);
        if (!$s) return null;
        $students = [];
        foreach ($studentUserIds as $uid) {
            foreach (subjectMarks($db, (int) $uid, (int) $s['course_id']) as $m) {
                if ($m['subject_id'] === $sid) { $students[(int) $uid] = $m; break; }
            }
        }
        $q = $db->prepare('SELECT kind, COUNT(*) FROM tutor_assignments WHERE subject_id = ? AND status = ? AND COALESCE(is_template, 0) = 0 GROUP BY kind');
        $q->execute([$sid, 'published']);
        $kinds = $q->fetchAll(PDO::FETCH_KEY_PAIR);
        return ['subject' => $s, 'students' => $students, 'assignments' => (int) array_sum(array_diff_key($kinds, ['monthly' => 1])), 'monthly' => (int) ($kinds['monthly'] ?? 0)];
    }
}
