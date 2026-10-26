<?php
/**
 * Class Calendar (tutor) and My Classes (student), with class attendance. Tables: migration 076_class_calendar.sql.
 *
 * Rules (see the build prompt):
 *  - A tutor works only with their own students (tutor_students). Every read and write checks it here.
 *  - All times are stored in UTC and shown in the viewer's own time zone (user_profiles.timezone), with its name.
 *  - Each student has an effective mode for a class (class_students.mode_override, else classes.mode).
 *    cal_student_view() is the only way class data reaches a student, an email, a PDF or a calendar file: the meeting
 *    link is removed unless the effective mode is Online or Hybrid, and campus/room unless Physical or Hybrid.
 *  - Past classes (end time passed) are locked: no delete, no date/time change. Attendance, notes and materials stay open.
 *  - A student only gets classes from their joining date (first approved registration) onward.
 *  - One notification + one email per student per change, emails queued (class_email_queue) and retried.
 * Nothing here touches the intern teaching feature.
 */
require_once __DIR__ . '/PortalNotifications.php';

const CAL_MODES = ['physical' => 'Physical', 'online' => 'Online', 'hybrid' => 'Hybrid'];
const CAL_ATT = ['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'excused' => 'Excused'];
const CAL_DEFAULT_TZ = 'Africa/Nairobi';
const CAL_MAX_SERIES = 400;

if (!function_exists('cal_e')) {
    function cal_e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    // ── Settings & audit ─────────────────────────────────────────────────────────────────────────────────
    function cal_settings(PDO $db): array
    {
        static $s = null;
        if ($s === null) {
            $s = ['join_open_minutes' => '10', 'reminder_24h' => '1', 'reminder_1h' => '1', 'campuses' => 'Nairobi,Thika',
                  'rooms' => 'Room 1,Room 2,Computer Lab,Board Room', 'self_checkin' => '0', 'late_counts' => '1'];
            try {
                foreach ($db->query('SELECT setting_key, setting_value FROM class_settings')->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) $s[$k] = $v;
            } catch (Throwable $e) {
            }
        }
        return $s;
    }

    function cal_list_setting(PDO $db, string $key): array
    {
        return array_values(array_filter(array_map('trim', explode(',', cal_settings($db)[$key] ?? ''))));
    }

    function cal_audit(PDO $db, ?int $classId, ?int $seriesId, string $action, string $detail = ''): void
    {
        try {
            $db->prepare('INSERT INTO class_audit (actor_id, class_id, series_id, action, detail, ip) VALUES (?, ?, ?, ?, ?, ?)')
               ->execute([(int) ($_SESSION['user_id'] ?? 0) ?: null, $classId, $seriesId, $action, mb_substr($detail, 0, 500) ?: null, $_SERVER['REMOTE_ADDR'] ?? null]);
        } catch (Throwable $e) {
            error_log('[class audit] ' . $e->getMessage());
        }
    }

    function cal_installed(PDO $db): bool
    {
        static $ok = null;
        if ($ok === null) {
            try { $ok = (bool) $db->query("SHOW TABLES LIKE 'classes'")->fetch(); } catch (Throwable $e) { $ok = false; }
        }
        return $ok;
    }

    // ── Time zones ───────────────────────────────────────────────────────────────────────────────────────
    function cal_tz_valid(?string $tz): bool
    {
        return $tz !== null && $tz !== '' && in_array($tz, DateTimeZone::listIdentifiers(), true);
    }

    /** The user's own time zone (default Africa/Nairobi). */
    function cal_user_tz(PDO $db, int $uid): string
    {
        static $cache = [];
        if (!isset($cache[$uid])) {
            $tz = null;
            try {
                $st = $db->prepare('SELECT timezone FROM user_profiles WHERE user_id = ?');
                $st->execute([$uid]);
                $tz = $st->fetchColumn() ?: null;
            } catch (Throwable $e) {
            }
            $cache[$uid] = cal_tz_valid($tz) ? $tz : CAL_DEFAULT_TZ;
        }
        return $cache[$uid];
    }

    function cal_tz_confirmed(PDO $db, int $uid): bool
    {
        try {
            $st = $db->prepare('SELECT timezone_confirmed FROM user_profiles WHERE user_id = ?');
            $st->execute([$uid]);
            return (bool) $st->fetchColumn();
        } catch (Throwable $e) {
            return true;   // columns not installed yet: never nag
        }
    }

    function cal_set_tz(PDO $db, int $uid, string $tz): bool
    {
        if (!cal_tz_valid($tz)) return false;
        $db->prepare('INSERT INTO user_profiles (user_id, timezone, timezone_confirmed) VALUES (?, ?, 1)
                      ON DUPLICATE KEY UPDATE timezone = VALUES(timezone), timezone_confirmed = 1')->execute([$uid, $tz]);
        cal_audit($db, null, null, 'timezone_set', $tz);
        return true;
    }

    /** 'Y-m-d H:i' in $tz → 'Y-m-d H:i:s' UTC, or null. DateTimeZone handles daylight saving. */
    function cal_to_utc(string $local, string $tz): ?string
    {
        $d = DateTime::createFromFormat('Y-m-d H:i', $local, new DateTimeZone($tz));
        if (!$d) return null;
        return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    function cal_from_utc(string $utc, string $tz): DateTime
    {
        return (new DateTime($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($tz));
    }

    function cal_fmt(string $utc, string $tz, string $fmt = 'H:i'): string
    {
        return cal_from_utc($utc, $tz)->format($fmt);
    }

    /** "Mon 3 Nov 2025 · 09:00–11:00 Africa/Khartoum" */
    function cal_fmt_range(string $startUtc, string $endUtc, string $tz, bool $withDate = true): string
    {
        $s = cal_from_utc($startUtc, $tz);
        $e = cal_from_utc($endUtc, $tz);
        return ($withDate ? $s->format('D j M Y') . ' · ' : '') . $s->format('H:i') . '–' . $e->format('H:i') . ' ' . $tz;
    }

    function cal_now_utc(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    function cal_is_past(array $c): bool
    {
        return strtotime($c['end_utc'] . ' UTC') <= time();
    }

    // ── People ───────────────────────────────────────────────────────────────────────────────────────────
    /** The tutor's own approved students: user id, registration, name, course, mode, campus, joining date. */
    function cal_tutor_students(PDO $db, int $tutorUid): array
    {
        static $cache = [];
        if (!isset($cache[$tutorUid])) {
            $st = $db->prepare("SELECT r.user_id, r.id AS reg_id, r.full_name, r.course_id, c.title AS course, r.study_mode, r.study_location,
                                       DATE(r.created_at) AS join_date, r.email
                                FROM registrations r JOIN courses c ON c.id = r.course_id
                                JOIN tutor_students ts ON ts.user_id = r.user_id JOIN tutors t ON t.id = ts.tutor_id AND t.user_id = ?
                                WHERE r.status = 'approved' AND r.user_id IS NOT NULL AND r.registration_no NOT LIKE 'PREVIEW-%'
                                ORDER BY c.title, r.full_name");
            $st->execute([$tutorUid]);
            $rows = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $rows[(int) $r['user_id']] ??= $r;   // one row per student
            $cache[$tutorUid] = $rows;
        }
        return $cache[$tutorUid];
    }

    function cal_join_date(PDO $db, int $studentUid): string
    {
        static $cache = [];
        if (!isset($cache[$studentUid])) {
            $st = $db->prepare("SELECT MIN(DATE(created_at)) FROM registrations WHERE user_id = ? AND status = 'approved'");
            $st->execute([$studentUid]);
            $cache[$studentUid] = (string) ($st->fetchColumn() ?: '1970-01-01');
        }
        return $cache[$studentUid];
    }

    function cal_student_identity(PDO $db, int $uid): ?array
    {
        $st = $db->prepare("SELECT u.id, u.email AS login_email, r.email, r.full_name, COALESCE(up.class_email_reminders, 1) AS email_reminders
                            FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id
                            LEFT JOIN registrations r ON r.user_id = u.id AND r.status = 'approved'
                            WHERE u.id = ? ORDER BY r.id DESC LIMIT 1");
        $st->execute([$uid]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return null;
        $r['to'] = trim((string) ($r['email'] ?: $r['login_email']));
        $r['name'] = $r['full_name'] ?: 'Student';
        return $r;
    }

    function cal_tutor_name(PDO $db, int $uid): string
    {
        $st = $db->prepare("SELECT COALESCE(NULLIF(TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))), ''), u.username)
                            FROM users u LEFT JOIN user_profiles up ON up.user_id = u.id WHERE u.id = ?");
        $st->execute([$uid]);
        return (string) ($st->fetchColumn() ?: 'Your tutor');
    }

    // ── Reading classes ──────────────────────────────────────────────────────────────────────────────────
    function cal_class(PDO $db, int $id): ?array
    {
        $st = $db->prepare('SELECT c.*, co.title AS course_title FROM classes c LEFT JOIN courses co ON co.id = c.course_id WHERE c.id = ?');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** A class of this tutor, or null. */
    function cal_tutor_class(PDO $db, int $tutorUid, int $id): ?array
    {
        $c = cal_class($db, $id);
        return $c && (int) $c['tutor_id'] === $tutorUid ? $c : null;
    }

    function cal_class_students(PDO $db, int $classId): array
    {
        $st = $db->prepare("SELECT cs.*, c.mode, COALESCE(cs.mode_override, c.mode) AS eff_mode,
                                   (SELECT r.full_name FROM registrations r WHERE r.user_id = cs.student_id AND r.status = 'approved' ORDER BY r.id DESC LIMIT 1) AS full_name,
                                   a.status AS att_status, a.note AS att_note, a.self_checkin_at, a.marked_at
                            FROM class_students cs JOIN classes c ON c.id = cs.class_id
                            LEFT JOIN class_attendance a ON a.class_id = cs.class_id AND a.student_id = cs.student_id
                            WHERE cs.class_id = ? ORDER BY full_name");
        $st->execute([$classId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** The tutor's classes between two UTC times, with filters (student, mode, course). */
    function cal_tutor_classes(PDO $db, int $tutorUid, string $fromUtc, string $toUtc, array $f = []): array
    {
        $where = ['c.tutor_id = ?', 'c.start_utc < ?', 'c.end_utc > ?'];
        $args = [$tutorUid, $toUtc, $fromUtc];
        if (!empty($f['mode']) && isset(CAL_MODES[$f['mode']])) { $where[] = 'c.mode = ?'; $args[] = $f['mode']; }
        if (!empty($f['course'])) { $where[] = 'c.course_id = ?'; $args[] = (int) $f['course']; }
        if (!empty($f['student'])) { $where[] = 'EXISTS (SELECT 1 FROM class_students x WHERE x.class_id = c.id AND x.student_id = ?)'; $args[] = (int) $f['student']; }
        $st = $db->prepare('SELECT c.*, co.title AS course_title, (SELECT COUNT(*) FROM class_students x WHERE x.class_id = c.id) AS students
                            FROM classes c LEFT JOIN courses co ON co.id = c.course_id WHERE ' . implode(' AND ', $where) . ' ORDER BY c.start_utc');
        $st->execute($args);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * What ONE student may see of a class. Link only for an Online/Hybrid student, campus/room only for Physical/Hybrid.
     * Everything a student receives (page, JSON, email, PDF, .ics) is built from this.
     */
    function cal_student_view(array $c, ?string $override, string $tutorName = ''): array
    {
        $mode = $override ?: $c['mode'];
        $physical = in_array($mode, ['physical', 'hybrid'], true);
        $online = in_array($mode, ['online', 'hybrid'], true);
        return [
            'id' => (int) $c['id'], 'title' => $c['title'], 'topic' => $c['topic'], 'subtopics' => $c['subtopics'], 'course' => $c['course_title'] ?? null,
            'start_utc' => $c['start_utc'], 'end_utc' => $c['end_utc'], 'status' => $c['status'], 'mode' => $mode,
            'campus' => $physical ? $c['campus'] : null, 'room' => $physical ? $c['room'] : null,
            'meeting_link' => $online ? $c['meeting_link'] : null,
            'cancel_reason' => $c['cancel_reason'], 'moved_from_utc' => $c['moved_from_utc'], 'moved_reason' => $c['moved_reason'],
            'notes' => $c['notes'],   // the tutor's notes show to the class's students as soon as they are written
            'tutor' => $tutorName, 'makeup_for_class_id' => $c['makeup_for_class_id'],
        ];
    }

    /** A student's classes (only theirs, only from their joining date) between two UTC times, as student views. */
    function cal_student_classes(PDO $db, int $studentUid, string $fromUtc, string $toUtc): array
    {
        $join = cal_join_date($db, $studentUid) . ' 00:00:00';
        $st = $db->prepare("SELECT c.*, co.title AS course_title, cs.mode_override, a.status AS att_status,
                                   COALESCE(NULLIF(TRIM(CONCAT(COALESCE(tp.first_name,''), ' ', COALESCE(tp.last_name,''))), ''), tu.username) AS tutor_name
                            FROM class_students cs JOIN classes c ON c.id = cs.class_id
                            LEFT JOIN courses co ON co.id = c.course_id
                            LEFT JOIN users tu ON tu.id = c.tutor_id LEFT JOIN user_profiles tp ON tp.user_id = tu.id
                            LEFT JOIN class_attendance a ON a.class_id = c.id AND a.student_id = cs.student_id
                            WHERE cs.student_id = ? AND c.start_utc >= ? AND c.start_utc < ? AND c.end_utc > ? ORDER BY c.start_utc");
        $st->execute([$studentUid, max($join, $fromUtc), $toUtc, $fromUtc]);
        return array_map(fn($r) => cal_student_view($r, $r['mode_override'], (string) $r['tutor_name']) + ['att_status' => $r['att_status']], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** One class for one student (null when not theirs or before their joining date). */
    function cal_student_class(PDO $db, int $studentUid, int $classId): ?array
    {
        $c = cal_class($db, $classId);
        if (!$c) return null;
        $st = $db->prepare('SELECT mode_override FROM class_students WHERE class_id = ? AND student_id = ?');
        $st->execute([$classId, $studentUid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row || substr($c['start_utc'], 0, 10) < cal_join_date($db, $studentUid)) return null;
        return cal_student_view($c, $row['mode_override'], cal_tutor_name($db, (int) $c['tutor_id']));
    }

    /** Is the "Join class" button open now? From X minutes before the start until the end. */
    function cal_join_open(PDO $db, array $view): bool
    {
        if (!$view['meeting_link'] || $view['status'] === 'cancelled') return false;
        $open = strtotime($view['start_utc'] . ' UTC') - 60 * (int) cal_settings($db)['join_open_minutes'];
        return time() >= $open && time() < strtotime($view['end_utc'] . ' UTC');
    }

    // ── Holidays ─────────────────────────────────────────────────────────────────────────────────────────
    function cal_holidays(PDO $db): array
    {
        try {
            return $db->query('SELECT * FROM holidays ORDER BY start_date')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    function cal_is_holiday(array $holidays, string $date): ?string
    {
        foreach ($holidays as $h) if ($date >= $h['start_date'] && $date <= $h['end_date']) return $h['name'];
        return null;
    }

    // ── Input & validation ───────────────────────────────────────────────────────────────────────────────
    function cal_valid_link(string $link): bool
    {
        return (bool) preg_match('#^https://[a-z0-9.-]+\.[a-z]{2,}(/\S*)?$#i', $link) && filter_var($link, FILTER_VALIDATE_URL);
    }

    /**
     * Clean a create/edit form. Times are in the tutor's time zone. Returns ['ok'=>true, 'd'=>[…]] or ['ok'=>false,'error'].
     * $forEdit: students/repeat optional.
     */
    function cal_clean(PDO $db, int $tutorUid, array $in, bool $forEdit = false): array
    {
        $tz = cal_user_tz($db, $tutorUid);
        $title = mb_substr(trim((string) ($in['title'] ?? '')), 0, 200);
        if ($title === '') return ['ok' => false, 'error' => 'Enter a title, for example "Python: Loops".'];
        $mode = isset(CAL_MODES[$in['mode'] ?? '']) ? $in['mode'] : 'physical';
        $date = (string) ($in['date'] ?? '');
        $startT = (string) ($in['start_time'] ?? '');
        $endT = (string) ($in['end_time'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $startT) || !preg_match('/^\d{2}:\d{2}$/', $endT)) {
            return ['ok' => false, 'error' => 'Choose the date, start time and end time.'];
        }
        if ($endT <= $startT) return ['ok' => false, 'error' => 'The end time must be after the start time.'];
        $campuses = cal_list_setting($db, 'campuses');
        $campus = trim((string) ($in['campus'] ?? ''));
        $room = mb_substr(trim((string) ($in['room'] ?? '')), 0, 80);
        $link = trim((string) ($in['meeting_link'] ?? ''));
        $overrides = [];
        foreach ((array) ($in['student_mode'] ?? []) as $sid => $m) if (isset(CAL_MODES[$m]) && $m !== $mode) $overrides[(int) $sid] = $m;
        $needsPlace = $mode !== 'online' || in_array('physical', $overrides, true) || in_array('hybrid', $overrides, true);
        $needsLink = $mode !== 'physical' || in_array('online', $overrides, true) || in_array('hybrid', $overrides, true);
        if ($needsPlace && !in_array($campus, $campuses, true)) return ['ok' => false, 'error' => 'Choose the campus (' . implode(' or ', $campuses) . ').'];
        if ($needsLink && !cal_valid_link($link)) return ['ok' => false, 'error' => 'Add a proper https meeting link (Google Meet, Zoom, Teams…).'];
        $mine = cal_tutor_students($db, $tutorUid);
        $students = array_values(array_intersect(array_map('intval', (array) ($in['students'] ?? [])), array_keys($mine)));
        $group = (int) ($in['group_course_id'] ?? 0) ?: null;
        if ($group) {
            foreach ($mine as $uid => $s) if ((int) $s['course_id'] === $group) $students[] = $uid;
            $students = array_values(array_unique($students));
        }
        if (!$forEdit && !$students) return ['ok' => false, 'error' => 'Choose at least one of your students (or a whole course class).'];
        $course = (int) ($in['course_id'] ?? 0) ?: ($group ?: null);
        $repeat = in_array($in['repeat'] ?? '', ['daily', 'weekly', 'monthly'], true) ? $in['repeat'] : 'none';
        $days = array_values(array_intersect(array_map('intval', (array) ($in['repeat_days'] ?? [])), [1, 2, 3, 4, 5, 6, 7]));
        if ($repeat === 'weekly' && !$days) $days = [(int) date('N', strtotime($date))];
        $ends = (string) ($in['ends'] ?? 'date');
        $endsOn = match ($ends) {
            'month' => date('Y-m-d', strtotime($date . ' +1 month')),
            'year' => date('Y-m-d', strtotime($date . ' +1 year')),
            default => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($in['ends_on'] ?? '')) ? $in['ends_on'] : $date,
        };
        if ($repeat !== 'none' && $endsOn < $date) return ['ok' => false, 'error' => 'The "ends on" date is before the first class.'];
        return ['ok' => true, 'd' => [
            'title' => $title, 'course_id' => $course, 'topic' => mb_substr(trim((string) ($in['topic'] ?? '')), 0, 200) ?: null,
            'subtopics' => trim((string) ($in['subtopics'] ?? '')) ?: null, 'notes' => trim((string) ($in['notes'] ?? '')) ?: null,
            'mode' => $mode, 'campus' => $needsPlace ? $campus : null, 'room' => $needsPlace ? ($room ?: null) : null, 'meeting_link' => $needsLink ? $link : null,
            'date' => $date, 'start_time' => $startT, 'end_time' => $endT, 'tz' => $tz,
            'students' => $students, 'overrides' => $overrides, 'group_course_id' => $group,
            'repeat' => $repeat, 'repeat_days' => $days, 'ends_on' => $endsOn,
            'reminders_on' => array_key_exists('reminders_on', $in) ? (!empty($in['reminders_on']) ? 1 : 0) : 1,
            'makeup_for' => (int) ($in['makeup_for'] ?? 0) ?: null,
        ]];
    }

    /** Dates of a new class or series (Y-m-d in the tutor's zone), minus holidays. Returns [dates, skipped]. */
    function cal_series_dates(PDO $db, array $d): array
    {
        $start = new DateTime($d['date']);
        $end = new DateTime($d['repeat'] === 'none' ? $d['date'] : $d['ends_on']);
        $dates = [];
        if ($d['repeat'] === 'none') {
            $dates[] = $d['date'];
        } elseif ($d['repeat'] === 'daily') {
            for ($x = clone $start; $x <= $end; $x->modify('+1 day')) $dates[] = $x->format('Y-m-d');
        } elseif ($d['repeat'] === 'weekly') {
            for ($x = clone $start; $x <= $end; $x->modify('+1 day')) if (in_array((int) $x->format('N'), $d['repeat_days'], true)) $dates[] = $x->format('Y-m-d');
        } else {
            $day = (int) $start->format('j');
            for ($i = 0; ; $i++) {
                $x = (clone $start)->modify('first day of +' . $i . ' month');
                if ($day > (int) $x->format('t')) continue;   // e.g. no 31st this month
                $x->setDate((int) $x->format('Y'), (int) $x->format('n'), $day);
                if ($x > $end) break;
                $dates[] = $x->format('Y-m-d');
            }
        }
        $dates = array_slice($dates, 0, CAL_MAX_SERIES);
        if (count($dates) <= 1) return [$dates, []];
        $holidays = cal_holidays($db);
        $keep = $skip = [];
        foreach ($dates as $dt) (cal_is_holiday($holidays, $dt) ? $skip[] = $dt : $keep[] = $dt);
        return [$keep, $skip];
    }

    /** Warnings for overlaps with the tutor's other classes and with the chosen students' other classes. */
    function cal_clashes(PDO $db, int $tutorUid, array $slots, array $students, array $excludeIds = []): array
    {
        $warn = [];
        $ex = $excludeIds ? ' AND c.id NOT IN (' . implode(',', array_map('intval', $excludeIds)) . ')' : '';
        $mine = $db->prepare("SELECT c.title, c.start_utc FROM classes c WHERE c.tutor_id = ? AND c.status <> 'cancelled' AND c.start_utc < ? AND c.end_utc > ?$ex LIMIT 1");
        $theirs = $students ? $db->prepare("SELECT c.title, c.start_utc, cs.student_id FROM classes c JOIN class_students cs ON cs.class_id = c.id
                                            WHERE cs.student_id IN (" . implode(',', array_map('intval', $students)) . ") AND c.status <> 'cancelled' AND c.start_utc < ? AND c.end_utc > ?$ex LIMIT 3") : null;
        $tz = cal_user_tz($db, $tutorUid);
        $names = cal_tutor_students($db, $tutorUid);
        foreach (array_slice($slots, 0, 120) as [$s, $e]) {
            $mine->execute([$tutorUid, $e, $s]);
            if ($r = $mine->fetch(PDO::FETCH_ASSOC)) $warn[] = 'You already teach "' . $r['title'] . '" at ' . cal_fmt($r['start_utc'], $tz, 'D j M H:i') . '.';
            if ($theirs) {
                $theirs->execute([$e, $s]);
                foreach ($theirs->fetchAll(PDO::FETCH_ASSOC) as $r) $warn[] = ($names[(int) $r['student_id']]['full_name'] ?? 'A student') . ' already has "' . $r['title'] . '" at ' . cal_fmt($r['start_utc'], $tz, 'D j M H:i') . '.';
            }
            if (count($warn) >= 6) break;
        }
        return array_values(array_unique($warn));
    }

    // ── Create ───────────────────────────────────────────────────────────────────────────────────────────
    /** Create one class or a whole series. Returns ['ok', 'ids', 'skipped' => [dates], 'series_id', 'warnings'?]. */
    function cal_create(PDO $db, int $tutorUid, array $d, bool $force = false): array
    {
        [$dates, $skipped] = cal_series_dates($db, $d);
        if (!$dates) return ['ok' => false, 'error' => 'No class dates are left after skipping holidays.'];
        $slots = array_map(fn($dt) => [cal_to_utc($dt . ' ' . $d['start_time'], $d['tz']), cal_to_utc($dt . ' ' . $d['end_time'], $d['tz'])], $dates);
        if ($slots[0][0] <= cal_now_utc() && $d['repeat'] === 'none') return ['ok' => false, 'error' => 'That time is already in the past. Choose a future time.'];
        $slots = array_values(array_filter($slots, fn($s) => $s[1] > cal_now_utc()));
        if (!$slots) return ['ok' => false, 'error' => 'All of these classes are in the past.'];
        if (!$force && ($warn = cal_clashes($db, $tutorUid, $slots, $d['students']))) return ['ok' => false, 'clash' => $warn];

        $seriesId = null;
        if ($d['repeat'] !== 'none') {
            $db->prepare('INSERT INTO class_series (tutor_id, title, course_id, repeat_rule, repeat_days, starts_on, ends_on, group_course_id, skipped_dates) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
               ->execute([$tutorUid, $d['title'], $d['course_id'], $d['repeat'], $d['repeat'] === 'weekly' ? implode(',', $d['repeat_days']) : null, $d['date'], $d['ends_on'], $d['group_course_id'], $skipped ? implode(',', $skipped) : null]);
            $seriesId = (int) $db->lastInsertId();
        }
        $ins = $db->prepare('INSERT INTO classes (series_id, tutor_id, title, course_id, topic, subtopics, notes, start_utc, end_utc, tutor_timezone, mode, campus, room, meeting_link, makeup_for_class_id, reminders_on)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $ids = [];
        $per = [];   // student => [class ids] for the one summary message each
        foreach ($slots as [$s, $e]) {
            $ins->execute([$seriesId, $tutorUid, $d['title'], $d['course_id'], $d['topic'], $d['subtopics'], $d['notes'], $s, $e, $d['tz'], $d['mode'],
                           $d['campus'], $d['room'], $d['meeting_link'], $d['makeup_for'], $d['reminders_on']]);
            $cid = (int) $db->lastInsertId();
            $ids[] = $cid;
            foreach (cal_add_students($db, $cid, $s, $d['students'], $d['overrides']) as $sid) $per[$sid][] = $cid;
        }
        cal_audit($db, $ids[0], $seriesId, $d['makeup_for'] ? 'makeup_created' : ($seriesId ? 'series_created' : 'class_created'), count($ids) . ' classes, ' . count($d['students']) . ' students');
        cal_notify($db, $d['makeup_for'] ? 'makeup' : 'added', $per);
        return ['ok' => true, 'ids' => $ids, 'series_id' => $seriesId, 'skipped' => $skipped];
    }

    /** Put students into a class — only those who had joined by the class date. Returns the ones added. */
    function cal_add_students(PDO $db, int $classId, string $startUtc, array $students, array $overrides): array
    {
        $added = [];
        $st = $db->prepare('INSERT IGNORE INTO class_students (class_id, student_id, mode_override) VALUES (?, ?, ?)');
        foreach ($students as $sid) {
            if (substr($startUtc, 0, 10) < cal_join_date($db, (int) $sid)) continue;   // before the student joined
            $st->execute([$classId, (int) $sid, $overrides[(int) $sid] ?? null]);
            if ($st->rowCount()) $added[] = (int) $sid;
        }
        return $added;
    }

    /** The classes an edit or cancel applies to: this one, or this and all future ones of its series. */
    function cal_scope_classes(PDO $db, array $c, string $scope): array
    {
        if ($scope !== 'future' || !$c['series_id']) return [$c];
        $st = $db->prepare("SELECT c.*, co.title AS course_title FROM classes c LEFT JOIN courses co ON co.id = c.course_id
                            WHERE c.series_id = ? AND c.start_utc >= ? AND c.status <> 'cancelled' ORDER BY c.start_utc");
        $st->execute([(int) $c['series_id'], $c['start_utc']]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Edit ─────────────────────────────────────────────────────────────────────────────────────────────
    /**
     * Edit one class or "this and all future". Past classes accept only notes. A time change on a future class marks
     * it "moved" (old time kept). Only students affected by a change are told, once each.
     */
    function cal_update(PDO $db, int $tutorUid, int $classId, array $in, string $scope, bool $force = false): array
    {
        $c = cal_tutor_class($db, $tutorUid, $classId);
        if (!$c) return ['ok' => false, 'error' => 'Class not found.'];
        if (cal_is_past($c)) {   // locked: notes only
            $db->prepare('UPDATE classes SET notes = ? WHERE id = ?')->execute([trim((string) ($in['notes'] ?? '')) ?: null, $classId]);
            cal_audit($db, $classId, null, 'notes_updated');
            return ['ok' => true, 'locked' => true];
        }
        if ($c['status'] === 'cancelled') return ['ok' => false, 'error' => 'This class was cancelled.'];
        $r = cal_clean($db, $tutorUid, $in, true);
        if (!$r['ok']) return $r;
        $d = $r['d'];
        $tz = $d['tz'];
        $newStart = cal_to_utc($d['date'] . ' ' . $d['start_time'], $tz);
        $newEnd = cal_to_utc($d['date'] . ' ' . $d['end_time'], $tz);
        if ($newEnd <= cal_now_utc()) return ['ok' => false, 'error' => 'The new time is in the past.'];
        $shift = strtotime($newStart . ' UTC') - strtotime($c['start_utc'] . ' UTC');
        $length = strtotime($newEnd . ' UTC') - strtotime($newStart . ' UTC');
        $targets = array_values(array_filter(cal_scope_classes($db, $c, $scope), fn($t) => !cal_is_past($t)));
        $slots = array_map(fn($t) => [gmdate('Y-m-d H:i:s', strtotime($t['start_utc'] . ' UTC') + $shift), gmdate('Y-m-d H:i:s', strtotime($t['start_utc'] . ' UTC') + $shift + $length)], $targets);
        $students = $d['students'];
        if (!$force && ($warn = cal_clashes($db, $tutorUid, $slots, $students, array_column($targets, 'id')))) return ['ok' => false, 'clash' => $warn];

        $reason = mb_substr(trim((string) ($in['reason'] ?? '')), 0, 255) ?: null;
        $changed = $added = $removed = [];
        foreach ($targets as $i => $t) {
            [$s, $e] = $slots[$i];
            $timeChanged = $s !== $t['start_utc'] || $e !== $t['end_utc'];
            $detailsChanged = $d['mode'] !== $t['mode'] || $d['campus'] !== $t['campus'] || $d['room'] !== $t['room'] || $d['meeting_link'] !== $t['meeting_link']
                || $d['title'] !== $t['title'] || $d['topic'] !== $t['topic'];
            $db->prepare('UPDATE classes SET title = ?, course_id = ?, topic = ?, subtopics = ?, notes = ?, mode = ?, campus = ?, room = ?, meeting_link = ?, reminders_on = ?,
                          start_utc = ?, end_utc = ?, status = ?, moved_from_utc = ?, moved_reason = ? WHERE id = ?')
               ->execute([$d['title'], $d['course_id'] ?? $t['course_id'], $d['topic'], $d['subtopics'], $d['notes'], $d['mode'], $d['campus'], $d['room'], $d['meeting_link'], $d['reminders_on'],
                          $s, $e, $timeChanged ? 'moved' : $t['status'], $timeChanged ? $t['start_utc'] : $t['moved_from_utc'], $timeChanged ? $reason : $t['moved_reason'], (int) $t['id']]);
            if ($timeChanged) $db->prepare('DELETE FROM class_reminders_sent WHERE class_id = ?')->execute([(int) $t['id']]);
            // students: keep, add, remove; per-student mode
            $now = array_map('intval', array_column(cal_class_students($db, (int) $t['id']), 'student_id'));
            if ($students) {
                foreach (array_diff($now, $students) as $sid) {
                    $db->prepare('DELETE FROM class_students WHERE class_id = ? AND student_id = ?')->execute([(int) $t['id'], $sid]);
                    $removed[$sid][] = (int) $t['id'];
                }
                foreach (cal_add_students($db, (int) $t['id'], $s, array_diff($students, $now), $d['overrides']) as $sid) $added[$sid][] = (int) $t['id'];
            }
            foreach (array_intersect($now, $students ?: $now) as $sid) {
                $ov = $d['overrides'][$sid] ?? null;
                $st = $db->prepare('UPDATE class_students SET mode_override = ? WHERE class_id = ? AND student_id = ? AND NOT (mode_override <=> ?)');
                $st->execute([$ov, (int) $t['id'], $sid, $ov]);
                if ($timeChanged || $detailsChanged || $st->rowCount()) $changed[$sid][] = (int) $t['id'];
            }
        }
        cal_audit($db, $classId, $c['series_id'] ? (int) $c['series_id'] : null, 'class_edited', count($targets) . ' classes (' . $scope . ')');
        cal_notify($db, 'changed', $changed, $reason);
        cal_notify($db, 'added', $added);
        cal_notify($db, 'removed', $removed, $reason);
        return ['ok' => true, 'count' => count($targets)];
    }

    /** Cancel ("delete") a future class or "this and all future". Students are told, with the reason. */
    function cal_cancel(PDO $db, int $tutorUid, int $classId, string $scope, string $reason): array
    {
        $c = cal_tutor_class($db, $tutorUid, $classId);
        if (!$c) return ['ok' => false, 'error' => 'Class not found.'];
        if (cal_is_past($c)) return ['ok' => false, 'error' => 'Past classes cannot be deleted.'];
        $reason = mb_substr(trim($reason), 0, 255) ?: null;
        $per = [];
        $n = 0;
        foreach (cal_scope_classes($db, $c, $scope) as $t) {
            if (cal_is_past($t) || $t['status'] === 'cancelled') continue;
            $db->prepare("UPDATE classes SET status = 'cancelled', cancel_reason = ? WHERE id = ?")->execute([$reason, (int) $t['id']]);
            foreach (cal_class_students($db, (int) $t['id']) as $s) $per[(int) $s['student_id']][] = (int) $t['id'];
            $n++;
        }
        cal_audit($db, $classId, $c['series_id'] ? (int) $c['series_id'] : null, 'class_cancelled', $n . ' classes (' . $scope . ') ' . (string) $reason);
        cal_notify($db, 'cancelled', $per, $reason);
        return ['ok' => true, 'count' => $n];
    }

    /** Add back the classes a series skipped for holidays (future dates only). */
    function cal_add_skipped(PDO $db, int $tutorUid, int $seriesId): array
    {
        $st = $db->prepare('SELECT * FROM class_series WHERE id = ? AND tutor_id = ?');
        $st->execute([$seriesId, $tutorUid]);
        $ser = $st->fetch(PDO::FETCH_ASSOC);
        if (!$ser || !$ser['skipped_dates']) return ['ok' => false, 'error' => 'Nothing to add back.'];
        $tpl = $db->prepare("SELECT * FROM classes WHERE series_id = ? ORDER BY start_utc LIMIT 1");
        $tpl->execute([$seriesId]);
        $t = $tpl->fetch(PDO::FETCH_ASSOC);
        if (!$t) return ['ok' => false, 'error' => 'The series has no classes left.'];
        $tz = $t['tutor_timezone'];
        $startT = cal_fmt($t['start_utc'], $tz, 'H:i');
        $endT = cal_fmt($t['end_utc'], $tz, 'H:i');
        $students = array_map(fn($r) => (int) $r['student_id'], cal_class_students($db, (int) $t['id']));
        $ovr = [];
        foreach (cal_class_students($db, (int) $t['id']) as $r) if ($r['mode_override']) $ovr[(int) $r['student_id']] = $r['mode_override'];
        $per = [];
        $n = 0;
        foreach (explode(',', $ser['skipped_dates']) as $dt) {
            $s = cal_to_utc($dt . ' ' . $startT, $tz);
            $e = cal_to_utc($dt . ' ' . $endT, $tz);
            if (!$s || $e <= cal_now_utc()) continue;
            $db->prepare('INSERT INTO classes (series_id, tutor_id, title, course_id, topic, subtopics, notes, start_utc, end_utc, tutor_timezone, mode, campus, room, meeting_link, reminders_on)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
               ->execute([$seriesId, $tutorUid, $t['title'], $t['course_id'], $t['topic'], $t['subtopics'], $t['notes'], $s, $e, $tz, $t['mode'], $t['campus'], $t['room'], $t['meeting_link'], $t['reminders_on']]);
            $cid = (int) $db->lastInsertId();
            foreach (cal_add_students($db, $cid, $s, $students, $ovr) as $sid) $per[$sid][] = $cid;
            $n++;
        }
        $db->prepare('UPDATE class_series SET skipped_dates = NULL WHERE id = ?')->execute([$seriesId]);
        cal_audit($db, null, $seriesId, 'holiday_classes_added', (string) $n);
        cal_notify($db, 'added', $per);
        return ['ok' => true, 'count' => $n];
    }

    /** Whole-course classes: students who joined the tutor's course later get the future classes of the series. */
    function cal_sync_groups(PDO $db, ?int $tutorUid = null): void
    {
        static $done = [];
        $key = (string) $tutorUid;
        if (isset($done[$key])) return;
        $done[$key] = true;
        try {
            $sql = 'SELECT * FROM class_series WHERE group_course_id IS NOT NULL AND ends_on >= CURDATE()' . ($tutorUid ? ' AND tutor_id = ' . (int) $tutorUid : '');
            foreach ($db->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $ser) {
                $want = array_keys(array_filter(cal_tutor_students($db, (int) $ser['tutor_id']), fn($s) => (int) $s['course_id'] === (int) $ser['group_course_id']));
                if (!$want) continue;
                $st = $db->prepare("SELECT * FROM classes WHERE series_id = ? AND start_utc > UTC_TIMESTAMP() AND status <> 'cancelled'");
                $st->execute([(int) $ser['id']]);
                $per = [];
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
                    foreach (cal_add_students($db, (int) $c['id'], $c['start_utc'], $want, []) as $sid) $per[$sid][] = (int) $c['id'];
                }
                if ($per) {
                    cal_audit($db, null, (int) $ser['id'], 'group_students_added', implode(',', array_keys($per)));
                    cal_notify($db, 'group_added', $per);
                }
            }
        } catch (Throwable $e) {
            error_log('[class groups] ' . $e->getMessage());
        }
    }

    // ── Attendance ───────────────────────────────────────────────────────────────────────────────────────
    /** Tutor marks attendance (any time from the class start). Every change goes to class_attendance_history. */
    function cal_mark(PDO $db, int $tutorUid, int $classId, array $marks): array
    {
        $c = cal_tutor_class($db, $tutorUid, $classId);
        if (!$c) return ['ok' => false, 'error' => 'Class not found.'];
        if (strtotime($c['start_utc'] . ' UTC') > time()) return ['ok' => false, 'error' => 'You can mark attendance once the class has started.'];
        if ($c['status'] === 'cancelled') return ['ok' => false, 'error' => 'This class was cancelled.'];
        $inClass = array_map('intval', array_column(cal_class_students($db, $classId), 'student_id'));
        $old = $db->prepare('SELECT status, note FROM class_attendance WHERE class_id = ? AND student_id = ?');
        $n = 0;
        foreach ($marks as $sid => $m) {
            $sid = (int) $sid;
            $status = $m['status'] ?? '';
            if (!in_array($sid, $inClass, true) || !isset(CAL_ATT[$status])) continue;
            $note = mb_substr(trim((string) ($m['note'] ?? '')), 0, 255) ?: null;
            $old->execute([$classId, $sid]);
            $o = $old->fetch(PDO::FETCH_ASSOC) ?: ['status' => null, 'note' => null];
            if ($o['status'] === $status && $o['note'] === $note) continue;
            $db->prepare('INSERT INTO class_attendance (class_id, student_id, status, note, marked_by, marked_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())
                          ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note), marked_by = VALUES(marked_by), marked_at = VALUES(marked_at)')
               ->execute([$classId, $sid, $status, $note, $tutorUid]);
            $db->prepare('INSERT INTO class_attendance_history (class_id, student_id, old_status, new_status, note, changed_by, changed_at) VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())')
               ->execute([$classId, $sid, $o['status'], $status, $note, $tutorUid]);
            $n++;
        }
        if ($n) cal_audit($db, $classId, null, 'attendance_marked', $n . ' students');
        return ['ok' => true, 'count' => $n];
    }

    /** Student self check-in ("I'm here") for an online/hybrid class while it is open, when the admin turned it on. */
    function cal_checkin(PDO $db, int $studentUid, int $classId): array
    {
        if (cal_settings($db)['self_checkin'] !== '1') return ['ok' => false, 'error' => 'Self check-in is turned off.'];
        $v = cal_student_class($db, $studentUid, $classId);
        if (!$v || !in_array($v['mode'], ['online', 'hybrid'], true) || !cal_join_open($db, $v)) return ['ok' => false, 'error' => 'You can check in only while your online class is open.'];
        $db->prepare('INSERT INTO class_attendance (class_id, student_id, self_checkin_at) VALUES (?, ?, UTC_TIMESTAMP())
                      ON DUPLICATE KEY UPDATE self_checkin_at = COALESCE(self_checkin_at, VALUES(self_checkin_at))')->execute([$classId, $studentUid]);
        cal_audit($db, $classId, null, 'self_checkin', 'student #' . $studentUid);
        return ['ok' => true];
    }

    /**
     * Attendance for a student: Present + Late (if late counts) ÷ classes held (marked, not Excused, not Cancelled),
     * counted from the joining date. $tutorUid limits it to one tutor's classes. Exposed for certificate rules later.
     */
    function cal_attendance_summary(PDO $db, int $studentUid, ?int $tutorUid = null, ?int $courseId = null): array
    {
        $out = ['held' => 0, 'present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'percent' => null];
        try {
            $sql = "SELECT a.status, COUNT(*) n FROM class_attendance a JOIN classes c ON c.id = a.class_id
                    WHERE a.student_id = ? AND a.status IS NOT NULL AND c.status <> 'cancelled' AND c.start_utc >= ?"
                 . ($tutorUid ? ' AND c.tutor_id = ' . (int) $tutorUid : '') . ($courseId ? ' AND c.course_id = ' . (int) $courseId : '') . ' GROUP BY a.status';
            $st = $db->prepare($sql);
            $st->execute([$studentUid, cal_join_date($db, $studentUid) . ' 00:00:00']);
            foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $n) $out[$k] = (int) $n;
        } catch (Throwable $e) {
            return $out;
        }
        $lateCounts = cal_settings($db)['late_counts'] === '1';
        $out['held'] = $out['present'] + $out['late'] + $out['absent'];   // excused never counts against the student
        $attended = $out['present'] + ($lateCounts ? $out['late'] : 0);
        $out['percent'] = $out['held'] ? (int) round($attended / $out['held'] * 100) : null;
        $out['attended'] = $attended;
        return $out;
    }

    // ── Materials ────────────────────────────────────────────────────────────────────────────────────────
    function cal_materials(PDO $db, int $classId): array
    {
        $st = $db->prepare('SELECT * FROM class_materials WHERE class_id = ? ORDER BY id');
        $st->execute([$classId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    function cal_material_add(PDO $db, int $tutorUid, int $classId, string $title, string $link, array $file): array
    {
        if (!cal_tutor_class($db, $tutorUid, $classId)) return ['ok' => false, 'error' => 'Class not found.'];
        $link = trim($link);
        if ($link !== '' && !preg_match('#^https?://#i', $link)) return ['ok' => false, 'error' => 'The link must start with http:// or https://'];
        $path = null;
        $name = null;
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $r = cal_store_file($file, $classId);
            if (!$r['ok']) return $r;
            [$path, $name] = [$r['path'], $r['name']];
        }
        if (!$path && $link === '') return ['ok' => false, 'error' => 'Upload a file or paste a link.'];
        $db->prepare('INSERT INTO class_materials (class_id, title, file_path, link) VALUES (?, ?, ?, ?)')
           ->execute([$classId, mb_substr(trim($title), 0, 200) ?: ($name ?: $link), $path, $link ?: null]);
        cal_audit($db, $classId, null, 'material_added', $title);
        return ['ok' => true];
    }

    /** Save a class material in private storage (storage/classes/<class id>/). */
    function cal_store_file(array $file, int $classId): array
    {
        $types = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'text/plain' => 'txt', 'application/zip' => 'zip',
                  'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                  'application/vnd.ms-powerpoint' => 'ppt', 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                  'application/vnd.ms-excel' => 'xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx', 'video/mp4' => 'mp4'];
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 20 * 1048576) return ['ok' => false, 'error' => 'The file did not upload or is larger than 20 MB.'];
        if (!is_uploaded_file($file['tmp_name'])) return ['ok' => false, 'error' => 'The upload failed the security check.'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        if (!isset($types[$mime])) return ['ok' => false, 'error' => 'Use PDF, Word, PowerPoint, Excel, an image, text, ZIP or MP4.'];
        $dir = storage_path('classes/' . $classId);
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) return ['ok' => false, 'error' => 'Could not create the storage folder.'];
        if (!is_file(storage_path('classes/.htaccess'))) @file_put_contents(storage_path('classes/.htaccess'), "Require all denied\nDeny from all\n");
        $name = bin2hex(random_bytes(12)) . '.' . $types[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) return ['ok' => false, 'error' => 'Could not save the file.'];
        return ['ok' => true, 'path' => 'classes/' . $classId . '/' . $name, 'name' => mb_substr(preg_replace('/[^\w .()-]+/u', '_', basename((string) $file['name'])), 0, 200)];
    }

    function cal_material_abs(?string $rel): ?string
    {
        if (!$rel || !str_starts_with($rel, 'classes/')) return null;
        $base = realpath(storage_path('classes'));
        $abs = realpath(storage_path($rel));
        return $abs && $base && str_starts_with($abs, $base . DIRECTORY_SEPARATOR) && is_file($abs) ? $abs : null;
    }

    // ── Notifications & email ────────────────────────────────────────────────────────────────────────────
    function cal_queue_mail(PDO $db, string $to, string $name, string $subject, string $html): void
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;
        $db->prepare('INSERT INTO class_email_queue (to_email, to_name, subject, html) VALUES (?, ?, ?, ?)')->execute([$to, $name, $subject, $html]);
        static $hooked = false;
        if (!$hooked) {
            $hooked = true;
            register_shutdown_function(function () use ($db) {   // send after the page is delivered
                if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
                ignore_user_abort(true);
                @set_time_limit(120);
                cal_process_queue($db, 50);
            });
        }
    }

    /** Send queued emails; a failure is logged and retried up to 5 times (also by the cron job). */
    function cal_process_queue(PDO $db, int $limit = 100): array
    {
        $sent = $failed = 0;
        try {
            $rows = $db->query('SELECT * FROM class_email_queue WHERE sent_at IS NULL AND attempts < 5 ORDER BY id LIMIT ' . (int) $limit)->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) return ['sent' => 0, 'failed' => 0];
            require_once __DIR__ . '/Mailer.php';
            $mailer = new Mailer(require __DIR__ . '/../config/mail.php');
            foreach ($rows as $r) {
                $res = $mailer->send($r['to_email'], (string) $r['to_name'], $r['subject'], $r['html']);
                if (!empty($res['ok'])) {
                    $db->prepare('UPDATE class_email_queue SET sent_at = UTC_TIMESTAMP(), attempts = attempts + 1 WHERE id = ?')->execute([(int) $r['id']]);
                    $sent++;
                } else {
                    $db->prepare('UPDATE class_email_queue SET attempts = attempts + 1, last_error = ? WHERE id = ?')->execute([mb_substr((string) ($res['message'] ?? 'failed'), 0, 255), (int) $r['id']]);
                    error_log('[class email] ' . $r['to_email'] . ': ' . ($res['message'] ?? 'failed'));
                    $failed++;
                }
            }
        } catch (Throwable $e) {
            error_log('[class email queue] ' . $e->getMessage());
        }
        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * One dashboard notification + one email per student, summarising every class of the change.
     * kind: added | cancelled | changed | removed | makeup | group_added. Times in the student's zone; links only per cal_student_view().
     */
    function cal_notify(PDO $db, string $kind, array $perStudent, ?string $reason = null): void
    {
        foreach ($perStudent as $sid => $classIds) {
            $classIds = array_values(array_unique(array_map('intval', $classIds)));
            if (!$classIds) continue;
            $who = cal_student_identity($db, (int) $sid);
            if (!$who) continue;
            $tz = cal_user_tz($db, (int) $sid);
            $views = [];
            foreach ($classIds as $cid) {
                $c = cal_class($db, $cid);
                if (!$c) continue;
                $st = $db->prepare('SELECT mode_override FROM class_students WHERE class_id = ? AND student_id = ?');
                $st->execute([$cid, (int) $sid]);
                $views[] = cal_student_view($c, $st->fetchColumn() ?: null, cal_tutor_name($db, (int) $c['tutor_id']));
            }
            if (!$views) continue;
            usort($views, fn($a, $b) => strcmp($a['start_utc'], $b['start_utc']));
            $n = count($views);
            $first = $views[0];
            $summary = $n === 1
                ? $first['title'] . ' — ' . cal_fmt_range($first['start_utc'], $first['end_utc'], $tz)
                : cal_series_summary($views, $tz);
            [$title, $lead] = match ($kind) {
                'added' => [$n === 1 ? 'New class: ' . $first['title'] : $n . ' new classes', 'Your tutor added ' . ($n === 1 ? 'a class' : $n . ' classes') . ' for you.'],
                'group_added' => [$n . ' class' . ($n === 1 ? '' : 'es') . ' added for you', 'You joined a class group, so its future classes are now in your calendar.'],
                'makeup' => ['Make-up class offered', 'Your tutor offered a make-up class for the one you missed.'],
                'cancelled' => [$n === 1 ? 'Class cancelled: ' . $first['title'] : $n . ' classes cancelled', ($n === 1 ? 'This class was cancelled.' : 'These classes were cancelled.') . ($reason ? ' Reason: ' . $reason : '')],
                'removed' => [$n === 1 ? 'Class removed: ' . $first['title'] : $n . ' classes removed', 'You are no longer in ' . ($n === 1 ? 'this class.' : 'these classes.') . ($reason ? ' Reason: ' . $reason : '')],
                default => [$n === 1 ? 'Class changed: ' . $first['title'] : $n . ' classes changed', 'Your tutor changed ' . ($n === 1 ? 'a class' : $n . ' classes') . '.' . ($reason ? ' Reason: ' . $reason : '')],
            };
            $path = $n === 1 ? 'students/classes/class.php?id=' . $first['id'] : 'students/classes/index.php';
            PortalNotifications::notify($db, (int) $sid, 'class', $title, $summary, $path);
            cal_queue_mail($db, $who['to'], $who['name'], $title . ' — Dazu Training Institute', cal_email_html($who['name'], $lead, $views, $tz, $kind, PortalNotifications::absolute('students/classes/index.php')));
        }
    }

    /** "5 new classes in November, Mon and Wed, 10:00 to 12:00 (Africa/Nairobi)" */
    function cal_series_summary(array $views, string $tz): string
    {
        $months = $days = $times = [];
        foreach ($views as $v) {
            $s = cal_from_utc($v['start_utc'], $tz);
            $months[$s->format('F Y')] = $s->format('F');
            $days[(int) $s->format('N')] = $s->format('D');
            $times[$s->format('H:i') . ' to ' . cal_fmt($v['end_utc'], $tz, 'H:i')] = true;
        }
        ksort($days);
        $d = array_values($days);
        $dayText = count($d) > 1 ? implode(', ', array_slice($d, 0, -1)) . ' and ' . end($d) : $d[0];
        $monthText = count($months) > 2 ? reset($months) . ' to ' . end($months) : implode(' and ', $months);
        return count($views) . ' classes in ' . $monthText . ', ' . $dayText . (count($times) === 1 ? ', ' . array_key_first($times) : '') . ' (' . $tz . ')';
    }

    function cal_email_html(string $name, string $lead, array $views, string $tz, string $kind, string $url): string
    {
        $p = 'font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.55;color:#1f2937;margin:0 0 12px;';
        $rows = '';
        foreach (array_slice($views, 0, 12) as $v) {
            $place = $v['mode'] === 'online' ? 'Online' : trim(($v['campus'] ? $v['campus'] . ' campus' : '') . ($v['room'] ? ', ' . $v['room'] : ''));
            $rows .= '<tr><td style="padding:6px 10px;border-bottom:1px solid #eee;"><strong>' . cal_e($v['title']) . '</strong><br>' . cal_e(cal_fmt_range($v['start_utc'], $v['end_utc'], $tz))
                   . ($v['moved_from_utc'] && $kind === 'changed' ? '<br><span style="color:#b45309">Moved from ' . cal_e(cal_fmt($v['moved_from_utc'], $tz, 'D j M H:i')) . '</span>' : '') . '</td>'
                   . '<td style="padding:6px 10px;border-bottom:1px solid #eee;">' . cal_e(CAL_MODES[$v['mode']] ?? $v['mode']) . ($place ? '<br>' . cal_e($place) : '')
                   . ($v['meeting_link'] && $kind !== 'cancelled' && $kind !== 'removed' ? '<br><a href="' . cal_e($v['meeting_link']) . '">Join link</a>' : '') . '</td></tr>';
        }
        if (count($views) > 12) $rows .= '<tr><td colspan="2" style="padding:6px 10px;color:#6b7280">… and ' . (count($views) - 12) . ' more</td></tr>';
        return '<div style="max-width:600px;margin:0 auto">'
            . '<p style="' . $p . '">Hi ' . cal_e(explode(' ', trim($name))[0]) . ',</p><p style="' . $p . '">' . cal_e($lead) . '</p>'
            . '<table style="border-collapse:collapse;width:100%;font-family:Arial,Helvetica,sans-serif;font-size:14px;margin-bottom:14px">' . $rows . '</table>'
            . '<p style="' . $p . 'font-size:13px;color:#6b7280">Times are in your time zone: ' . cal_e($tz) . '. You can change it in My Classes.</p>'
            . '<p style="' . $p . '"><a href="' . cal_e($url) . '" style="display:inline-block;background:#44A024;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:bold">View my classes</a></p>'
            . '<p style="' . $p . '">Dazu Training Institute</p></div>';
    }

    /** Reminders 24 hours and 1 hour before each class. class_reminders_sent makes each one go out only once. */
    function cal_send_reminders(PDO $db): int
    {
        $s = cal_settings($db);
        $types = array_filter(['24h' => $s['reminder_24h'] === '1' ? 24 * 3600 : 0, '1h' => $s['reminder_1h'] === '1' ? 3600 : 0]);
        $n = 0;
        foreach ($types as $type => $ahead) {
            $st = $db->prepare("SELECT c.*, cs.student_id, cs.mode_override FROM classes c JOIN class_students cs ON cs.class_id = c.id
                                WHERE c.status <> 'cancelled' AND c.reminders_on = 1 AND c.start_utc > UTC_TIMESTAMP() AND c.start_utc <= UTC_TIMESTAMP() + INTERVAL ? SECOND"
                             . ($type === '24h' ? ' AND c.start_utc > UTC_TIMESTAMP() + INTERVAL 3600 SECOND' : ''));
            $st->execute([$ahead]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $sid = (int) $c['student_id'];
                if (substr($c['start_utc'], 0, 10) < cal_join_date($db, $sid)) continue;
                $ins = $db->prepare('INSERT IGNORE INTO class_reminders_sent (class_id, student_id, type) VALUES (?, ?, ?)');
                $ins->execute([(int) $c['id'], $sid, $type]);
                if (!$ins->rowCount()) continue;   // already sent
                $tz = cal_user_tz($db, $sid);
                $v = cal_student_view($c, $c['mode_override'], cal_tutor_name($db, (int) $c['tutor_id']));
                $when = $type === '24h' ? 'tomorrow' : 'in 1 hour';
                $title = 'Reminder: ' . $v['title'] . ' ' . $when;
                PortalNotifications::notify($db, $sid, 'class', $title, cal_fmt_range($v['start_utc'], $v['end_utc'], $tz), 'students/classes/class.php?id=' . $v['id']);
                $who = cal_student_identity($db, $sid);
                if ($who && $who['email_reminders']) {
                    cal_queue_mail($db, $who['to'], $who['name'], $title . ' — Dazu Training Institute',
                        cal_email_html($who['name'], 'Your class starts ' . $when . '.', [$v], $tz, 'reminder', PortalNotifications::absolute('students/classes/class.php?id=' . $v['id'])));
                }
                $n++;
            }
        }
        return $n;
    }

    // ── Calendar file (.ics) ─────────────────────────────────────────────────────────────────────────────
    function cal_feed_token(PDO $db, int $studentUid, bool $reset = false): string
    {
        $st = $db->prepare('SELECT token FROM calendar_feed_tokens WHERE student_id = ?');
        $st->execute([$studentUid]);
        $tok = $st->fetchColumn();
        if ($tok && !$reset) return $tok;
        $tok = bin2hex(random_bytes(24));
        $db->prepare('INSERT INTO calendar_feed_tokens (student_id, token) VALUES (?, ?) ON DUPLICATE KEY UPDATE token = VALUES(token), created_at = NOW()')->execute([$studentUid, $tok]);
        cal_audit($db, null, null, $reset ? 'feed_token_reset' : 'feed_token_created', 'student #' . $studentUid);
        return $tok;
    }

    /** iCalendar text for student views. Times in UTC ("Z"), so the phone shows its own local time. No link for physical. */
    function cal_ics(array $views, string $name = 'Dazu classes'): string
    {
        $esc = fn($s) => str_replace(["\\", ';', ',', "\r\n", "\n"], ["\\\\", '\;', '\,', '\n', '\n'], (string) $s);
        $host = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'dazu');
        $out = ["BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//Dazu Training Institute//Classes//EN", "CALSCALE:GREGORIAN", "METHOD:PUBLISH", 'X-WR-CALNAME:' . $esc($name)];
        foreach ($views as $v) {
            $place = trim(($v['campus'] ? $v['campus'] . ' campus' : '') . ($v['room'] ? ', ' . $v['room'] : ''));
            $desc = trim(($v['topic'] ? 'Topic: ' . $v['topic'] . "\n" : '') . ($v['tutor'] ? 'Tutor: ' . $v['tutor'] . "\n" : '') . ($v['meeting_link'] ? 'Join: ' . $v['meeting_link'] : ''));
            $out[] = 'BEGIN:VEVENT';
            $out[] = 'UID:class-' . $v['id'] . '@' . $host;
            $out[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $out[] = 'DTSTART:' . gmdate('Ymd\THis\Z', strtotime($v['start_utc'] . ' UTC'));
            $out[] = 'DTEND:' . gmdate('Ymd\THis\Z', strtotime($v['end_utc'] . ' UTC'));
            $out[] = 'SUMMARY:' . $esc(($v['status'] === 'cancelled' ? 'CANCELLED: ' : '') . $v['title']);
            if ($place) $out[] = 'LOCATION:' . $esc($place);
            elseif ($v['meeting_link']) $out[] = 'LOCATION:Online';
            if ($desc !== '') $out[] = 'DESCRIPTION:' . $esc($desc);
            if ($v['meeting_link']) $out[] = 'URL:' . $esc($v['meeting_link']);
            $out[] = 'STATUS:' . ($v['status'] === 'cancelled' ? 'CANCELLED' : 'CONFIRMED');
            $out[] = 'END:VEVENT';
        }
        $out[] = 'END:VCALENDAR';
        return implode("\r\n", array_map(fn($l) => wordwrap($l, 74, "\r\n ", true), $out)) . "\r\n";
    }

    // ── Printable timetable (PDF, one month) ─────────────────────────────────────────────────────────────
    /** $rows: student views (one student) or tutor classes (a group: no links at all). */
    function cal_timetable_pdf(string $who, string $monthLabel, array $rows, string $tz, bool $showLinks): string
    {
        require_once __DIR__ . '/../helpers/certificate_pdf.php';   // dompdf + logo helpers
        $e = 'cal_e';
        $logo = ($f = cert_logo_file()) ? '<img src="' . _cert_data_uri($f) . '" style="height:14mm" />' : '<b>DAZU</b>';
        $body = '';
        foreach ($rows as $r) {
            $s = cal_from_utc($r['start_utc'], $tz);
            $place = $r['mode'] === 'online' ? 'Online' : trim(($r['campus'] ?? '') . ($r['room'] ? ' · ' . $r['room'] : ''));
            $status = $r['status'] === 'cancelled' ? ' <span style="color:#c0362c">(cancelled)</span>' : ($r['status'] === 'moved' ? ' <span style="color:#b45309">(moved)</span>' : '');
            $body .= '<tr><td>' . $s->format('j M') . '</td><td>' . $s->format('D') . '</td><td>' . $s->format('H:i') . '–' . cal_fmt($r['end_utc'], $tz, 'H:i') . '</td>'
                   . '<td>' . $e($r['title']) . $status . '</td><td>' . $e(CAL_MODES[$r['mode']] ?? $r['mode']) . '</td><td>' . $e($place)
                   . ($showLinks && !empty($r['meeting_link']) ? '<br><span style="font-size:8pt">' . $e($r['meeting_link']) . '</span>' : '') . '</td></tr>';
        }
        if ($body === '') $body = '<tr><td colspan="6" style="text-align:center;color:#888">No classes this month.</td></tr>';
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>@page{margin:14mm}body{font-family:Helvetica,Arial,sans-serif;color:#373435;font-size:10pt}
            table{width:100%;border-collapse:collapse}th{background:#44A024;color:#fff;text-align:left;padding:6px;font-size:9pt}td{padding:6px;border-bottom:1px solid #e5e7eb;vertical-align:top}</style></head><body>'
            . '<table style="margin-bottom:8mm"><tr><td style="border:0;padding:0">' . $logo . '</td><td style="border:0;text-align:right;padding:0"><div style="font-size:16pt;font-weight:bold">Class timetable</div>'
            . '<div>' . $e($who) . '</div><div style="color:#44A024;font-weight:bold">' . $e($monthLabel) . '</div><div style="font-size:8pt;color:#777">Times in ' . $e($tz) . '</div></td></tr></table>'
            . '<table><thead><tr><th>Date</th><th>Day</th><th>Time</th><th>Class</th><th>Mode</th><th>Campus / room</th></tr></thead><tbody>' . $body . '</tbody></table>'
            . '<div style="margin-top:8mm;font-size:8pt;color:#777">Dazu Training Institute · printed ' . gmdate('j M Y') . '</div></body></html>';
        $o = new \Dompdf\Options();
        $o->setIsRemoteEnabled(false);
        $pdf = new \Dompdf\Dompdf($o);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        return $pdf->output();
    }

    /** First and last UTC instants of a calendar month in a time zone: ['Y-m', from, to]. */
    function cal_month_bounds(string $ym, string $tz): array
    {
        $ym = preg_match('/^\d{4}-\d{2}$/', $ym) ? $ym : (new DateTime('now', new DateTimeZone($tz)))->format('Y-m');
        $from = new DateTime($ym . '-01 00:00', new DateTimeZone($tz));
        $to = (clone $from)->modify('+1 month');
        $u = new DateTimeZone('UTC');
        return [$ym, (clone $from)->setTimezone($u)->format('Y-m-d H:i:s'), (clone $to)->setTimezone($u)->format('Y-m-d H:i:s')];
    }
}
