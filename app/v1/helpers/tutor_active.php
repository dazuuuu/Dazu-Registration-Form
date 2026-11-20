<?php
// app/v1/helpers/tutor_active.php
// "Still studying with this tutor": not marked completed, studies not completed, no certificate, and not paused today.
// Completed, certified and paused students disappear from everything the tutor works with (My Students, progress, assignments, grading,
// calendar, timetables). The admin still sees them (Admin → Students → Completed & paused). Payroll already stops paying for them.

if (!function_exists('tutor_active_sql')) {
    /** SQL condition (no leading AND) for the tutor_students row aliased $ts. Parts whose tables are not installed are left out. */
    function tutor_active_sql(PDO $db, string $ts = 'ts'): string
    {
        static $has = null;
        if ($has === null) {
            $probe = function (string $sql) use ($db): bool { try { $db->query($sql); return true; } catch (Throwable $e) { return false; } };
            $has = [
                'completed' => $probe('SELECT completed_on FROM tutor_students LIMIT 0'),
                'pauses'    => $probe('SELECT start_date, end_date FROM tutor_pauses LIMIT 0'),
                'studies'   => $probe('SELECT studies_completed_at FROM registrations LIMIT 0'),
                'certs'     => $probe('SELECT registration_id, status FROM certificates LIMIT 0'),
            ];
        }
        $w = [];
        if ($has['completed']) $w[] = "$ts.completed_on IS NULL";
        if ($has['pauses']) {
            $w[] = "NOT EXISTS (SELECT 1 FROM tutor_pauses tpz WHERE tpz.tutor_id = $ts.tutor_id AND tpz.user_id = $ts.user_id
                    AND tpz.start_date <= CURDATE() AND (tpz.end_date IS NULL OR tpz.end_date > CURDATE()))";
        }
        if ($has['studies'] || $has['certs']) {
            $or = [];
            if ($has['studies']) $or[] = 'rfx.studies_completed_at IS NOT NULL';
            if ($has['certs']) $or[] = "EXISTS (SELECT 1 FROM certificates cfx WHERE cfx.registration_id = rfx.id AND cfx.status <> 'withdrawn')";
            $w[] = "NOT EXISTS (SELECT 1 FROM registrations rfx WHERE rfx.id = (SELECT MAX(r2fx.id) FROM registrations r2fx WHERE r2fx.user_id = $ts.user_id AND r2fx.status = 'approved')
                    AND (" . implode(' OR ', $or) . "))";
        }
        return $w ? '(' . implode(' AND ', $w) . ')' : '1=1';
    }
}
