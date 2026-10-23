<?php
// app/v1/services/AssignmentAverage.php
// The course average for a student, from their assignment marks. All average rules live here (and only here):
//  1 only released marks count (held marks are not counted and not returned at all);
//  2 percent = mark ÷ marks out of;  3 average = weighted average of percents (weight, default 1), exact value kept, rounded only for display;
//  4 Pass or Not yet / Feedback only never count, but still show;  5 practical marks count the same way;
//  6 the submission row always holds the newest grade, so a resubmission uses the newest marked version;
//  7 late work is never reduced;  8 missing work is not zero unless the tutor marked 0;  9 nothing counted = "No marks yet";
// 10 for the student and their tutor only: never used for certificates and never shown to admin.
// Kinds counted now: daily, weekly. Later kinds (monthly, module_assessment, capstone) count the same way through weight.

class AssignmentAverage
{
    public const KINDS = ['daily' => 'Daily', 'weekly' => 'Weekly'];

    /** Whether the student may see this grade now. $now is a UTC timestamp. */
    public static function isReleased(array $row, ?int $now = null): bool
    {
        $now = $now ?? time();
        if (($row['sub_status'] ?? null) !== 'graded' || empty($row['result_published_at'])) return false;
        $at = $row['release_grades_at'] ?? null;
        return !$at || strtotime($at . ' UTC') <= $now;
    }

    /**
     * One course, pure (no database). Each $rows item: id, title, kind, weight, grading_mode, max_score, score, pass_result,
     * sub_status, submitted_at, result_published_at, release_grades_at, is_late, due (UTC or null).
     * Held marks are stripped from the output (score, pass_result) so they cannot leak to a page or JSON.
     * @return array{average:?float, display:string, counted:int, by_kind:array<string,?float>, items:array, not_counted:array}
     */
    public static function compute(array $rows, ?int $now = null): array
    {
        $now = $now ?? time();
        $items = [];
        $notCounted = [];
        $sum = $weights = 0.0;
        $kindSum = $kindW = [];
        foreach ($rows as $r) {
            $kind = (string) ($r['kind'] ?? 'daily') ?: 'daily';
            $weight = isset($r['weight']) && is_numeric($r['weight']) && (float) $r['weight'] > 0 ? (float) $r['weight'] : 1.0;
            $released = self::isReleased($r, $now);
            $submitted = !empty($r['submitted_at']);
            $pastDue = !empty($r['due']) && strtotime($r['due'] . ' UTC') <= $now;
            $item = [
                'id' => (int) $r['id'], 'title' => (string) $r['title'], 'kind' => $kind, 'weight' => $weight,
                'grading_mode' => (string) $r['grading_mode'], 'max_score' => (float) $r['max_score'],
                'late' => (bool) ($r['is_late'] ?? false), 'released' => $released,
                'score' => null, 'pass_result' => null, 'percent' => null, 'counts' => false,
            ];
            if ($released) {
                $item['score'] = $r['score'] !== null ? (float) $r['score'] : null;
                $item['pass_result'] = $r['pass_result'] ?? null;
                if ($item['grading_mode'] === 'marks' && $item['score'] !== null && $item['max_score'] > 0) {
                    $item['percent'] = $item['score'] / $item['max_score'] * 100;
                    $item['counts'] = true;
                    $sum += $item['percent'] * $weight;
                    $weights += $weight;
                    $kindSum[$kind] = ($kindSum[$kind] ?? 0) + $item['percent'] * $weight;
                    $kindW[$kind] = ($kindW[$kind] ?? 0) + $weight;
                }
                $item['status'] = 'graded';
            } elseif (($r['sub_status'] ?? null) === 'graded' && !empty($r['result_published_at'])) {
                $item['status'] = 'held';
                $notCounted[] = $item + ['reason' => 'held', 'release_at' => $r['release_grades_at']];
            } elseif ($submitted && in_array($r['sub_status'] ?? '', ['submitted'], true)) {
                $item['status'] = 'waiting';
                $notCounted[] = $item + ['reason' => 'waiting', 'release_at' => null];
            } elseif (($r['sub_status'] ?? '') === 'returned') {
                $item['status'] = 'returned';
            } else {
                $item['status'] = $pastDue ? 'missing' : 'not_due';
            }
            $items[] = $item;
        }
        $avg = $weights > 0 ? $sum / $weights : null;
        $byKind = [];
        foreach (array_keys(self::KINDS) as $k) $byKind[$k] = !empty($kindW[$k]) ? $kindSum[$k] / $kindW[$k] : null;
        return [
            'average' => $avg,
            'display' => self::display($avg),
            'counted' => count(array_filter($items, fn($i) => $i['counts'])),
            'by_kind' => $byKind,
            'items' => $items,
            'not_counted' => $notCounted,
        ];
    }

    public static function display(?float $percent): string
    {
        return $percent === null ? 'No marks yet' : (int) round($percent) . '%';
    }

    /** SQL rows for one student: every published assignment given to them, joined to their submission. */
    public static function rowsForStudent(PDO $db, int $uid, ?int $tutorId = null): array
    {
        $sql = "SELECT a.id, a.title, a.tutor_id, a.course_id, c.title AS course_title, a.kind, a.weight, a.grading_mode, a.max_score,
                       a.release_grades_at, a.sort_order, COALESCE(r.current_due_at, a.due_at) AS due,
                       s.status AS sub_status, s.submitted_at, s.score, s.pass_result, s.published_at AS result_published_at, s.is_late
                FROM tutor_assignment_recipients r
                JOIN tutor_assignments a ON a.id = r.assignment_id AND a.status = 'published' AND a.is_template = 0
                LEFT JOIN courses c ON c.id = a.course_id
                LEFT JOIN tutor_assignment_submissions s ON s.assignment_id = a.id AND s.student_user_id = r.student_user_id
                WHERE r.student_user_id = ? AND r.removed_at IS NULL AND r.skipped_at IS NULL";
        $args = [$uid];
        if ($tutorId !== null) { $sql .= " AND a.tutor_id = ?"; $args[] = $tutorId; }
        $st = $db->prepare($sql . " ORDER BY c.title, a.course_id, a.kind, a.sort_order, a.id");
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Per course for one student: course_id => [course, ...compute()]. */
    public static function forStudent(PDO $db, int $uid, ?int $tutorId = null, ?int $now = null): array
    {
        $groups = [];
        foreach (self::rowsForStudent($db, $uid, $tutorId) as $r) {
            $cid = (int) $r['course_id'];
            $groups[$cid]['course'] ??= $r['course_title'] ?: 'My course';
            $groups[$cid]['rows'][] = $r;
        }
        $out = [];
        foreach ($groups as $cid => $g) $out[$cid] = ['course' => $g['course']] + self::compute($g['rows'], $now);
        return $out;
    }
}
