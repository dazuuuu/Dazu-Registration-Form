<?php
// app/v1/services/TimetableService.php
// Tutor timetables: an uploaded PDF and/or a weekly table (day, course, time) shared with all the tutor's students or chosen ones.
// The class-reminder cron calls sendAlerts() every 5 minutes: before each class the students and the tutor get a notification.

require_once __DIR__ . '/PortalNotifications.php';

class TimetableService
{
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    public static function installed(PDO $db): bool
    {
        try { $db->query("SELECT 1 FROM timetables LIMIT 1"); return true; } catch (Throwable $e) { return false; }
    }

    public static function forTutor(PDO $db, int $tutorUid): array
    {
        $st = $db->prepare("SELECT * FROM timetables WHERE tutor_user_id = ? ORDER BY created_at DESC");
        $st->execute([$tutorUid]);
        return array_map(fn($t) => self::hydrate($db, $t), $st->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function find(PDO $db, int $id, int $tutorUid): ?array
    {
        $st = $db->prepare("SELECT * FROM timetables WHERE id = ? AND tutor_user_id = ?");
        $st->execute([$id, $tutorUid]);
        $t = $st->fetch(PDO::FETCH_ASSOC);
        return $t ? self::hydrate($db, $t) : null;
    }

    /** Timetables a student can see: from their tutors, shared with everyone or with them. */
    public static function forStudent(PDO $db, int $studentUid): array
    {
        $st = $db->prepare("SELECT tt.*, COALESCE(NULLIF(t.display_name,''), CONCAT(COALESCE(up.first_name,''),' ',COALESCE(up.last_name,''))) AS tutor_name
            FROM timetables tt
            JOIN tutors t ON t.user_id = tt.tutor_user_id
            JOIN tutor_students ts ON ts.tutor_id = t.id AND ts.user_id = ?
            LEFT JOIN user_profiles up ON up.user_id = tt.tutor_user_id
            WHERE tt.audience = 'all'
               OR EXISTS (SELECT 1 FROM timetable_students x WHERE x.timetable_id = tt.id AND x.student_user_id = ?)
            ORDER BY tt.created_at DESC");
        $st->execute([$studentUid, $studentUid]);
        return array_map(fn($t) => self::hydrate($db, $t), $st->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function hydrate(PDO $db, array $t): array
    {
        $s = $db->prepare("SELECT * FROM timetable_slots WHERE timetable_id = ? ORDER BY day_of_week, start_time");
        $s->execute([$t['id']]);
        $t['slots'] = $s->fetchAll(PDO::FETCH_ASSOC);
        $s = $db->prepare("SELECT student_user_id FROM timetable_students WHERE timetable_id = ?");
        $s->execute([$t['id']]);
        $t['students'] = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
        return $t;
    }

    /** Create or update. $slots: list of [day, course, start, end, venue]. Returns the id. */
    public static function save(PDO $db, int $tutorUid, ?int $id, array $data, array $slots, array $students): int
    {
        $db->beginTransaction();
        try {
            if ($id) {
                $db->prepare("UPDATE timetables SET title=?, audience=?, timezone=?, alert_minutes=?, pdf_path=COALESCE(?, pdf_path), updated_at=NOW()
                              WHERE id=? AND tutor_user_id=?")
                   ->execute([$data['title'], $data['audience'], $data['timezone'], $data['alert_minutes'], $data['pdf_path'], $id, $tutorUid]);
                if (!empty($data['remove_pdf'])) $db->prepare("UPDATE timetables SET pdf_path=NULL WHERE id=?")->execute([$id]);
            } else {
                $db->prepare("INSERT INTO timetables (tutor_user_id, title, audience, timezone, alert_minutes, pdf_path) VALUES (?,?,?,?,?,?)")
                   ->execute([$tutorUid, $data['title'], $data['audience'], $data['timezone'], $data['alert_minutes'], $data['pdf_path']]);
                $id = (int) $db->lastInsertId();
            }
            $db->prepare("DELETE FROM timetable_slots WHERE timetable_id=?")->execute([$id]);
            $ins = $db->prepare("INSERT INTO timetable_slots (timetable_id, day_of_week, course, start_time, end_time, venue) VALUES (?,?,?,?,?,?)");
            foreach ($slots as [$d, $c, $s, $e, $v]) $ins->execute([$id, $d, $c, $s, $e ?: null, $v ?: null]);
            $db->prepare("DELETE FROM timetable_students WHERE timetable_id=?")->execute([$id]);
            if ($data['audience'] === 'selected') {
                $ins = $db->prepare("INSERT IGNORE INTO timetable_students (timetable_id, student_user_id) VALUES (?,?)");
                foreach ($students as $u) $ins->execute([$id, (int) $u]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return $id;
    }

    public static function delete(PDO $db, int $id, int $tutorUid): void
    {
        $st = $db->prepare("DELETE FROM timetables WHERE id=? AND tutor_user_id=?");
        $st->execute([$id, $tutorUid]);
        if ($st->rowCount()) {
            $db->prepare("DELETE FROM timetable_slots WHERE timetable_id=?")->execute([$id]);
            $db->prepare("DELETE FROM timetable_students WHERE timetable_id=?")->execute([$id]);
        }
    }

    /** Student user ids who receive a timetable. */
    public static function recipients(PDO $db, array $t): array
    {
        if ($t['audience'] === 'selected') return $t['students'];
        $st = $db->prepare("SELECT DISTINCT ts.user_id FROM tutor_students ts JOIN tutors t ON t.id = ts.tutor_id WHERE t.user_id = ?");
        $st->execute([$t['tutor_user_id']]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Tell the students a timetable was shared or changed. */
    public static function announce(PDO $db, int $id, int $tutorUid, bool $new): void
    {
        $t = self::find($db, $id, $tutorUid);
        if (!$t) return;
        $title = $new ? 'New timetable: ' . $t['title'] : 'Timetable updated: ' . $t['title'];
        foreach (self::recipients($db, $t) as $u) {
            PortalNotifications::notify($db, $u, 'class', $title, 'Open it to see your class days and times.', 'students/timetable/index.php', null, true);
        }
    }

    /** Alert students and the tutor shortly before each class. Returns how many classes were alerted. */
    public static function sendAlerts(PDO $db): int
    {
        if (!self::installed($db)) return 0;
        $count = 0;
        $tts = $db->query("SELECT * FROM timetables")->fetchAll(PDO::FETCH_ASSOC);
        $mark = $db->prepare("INSERT IGNORE INTO timetable_alerts_sent (slot_id, class_date) VALUES (?, ?)");
        foreach ($tts as $t) {
            try { $tz = new DateTimeZone($t['timezone'] ?: 'Africa/Nairobi'); } catch (Throwable $e) { $tz = new DateTimeZone('Africa/Nairobi'); }
            $now = new DateTime('now', $tz);
            $lead = max(0, (int) $t['alert_minutes']);
            $t = self::hydrate($db, $t);
            $students = null;
            foreach ($t['slots'] as $s) {
                if ((int) $s['day_of_week'] !== (int) $now->format('N')) continue;
                $start = new DateTime($now->format('Y-m-d') . ' ' . $s['start_time'], $tz);
                $mins = ($start->getTimestamp() - $now->getTimestamp()) / 60;
                if ($mins > $lead || $mins < -10) continue;   // inside the alert window (up to 10 min after start, for late cron runs)
                $mark->execute([$s['id'], $now->format('Y-m-d')]);
                if (!$mark->rowCount()) continue;               // already sent today
                $when = $start->format('H:i') . ($s['end_time'] ? '–' . substr($s['end_time'], 0, 5) : '');
                $body = $s['course'] . ' · ' . $when . ($s['venue'] ? ' · ' . $s['venue'] : '');
                $head = $mins > 1 ? 'Class in ' . (int) round($mins) . ' minutes' : 'Class starting now';
                $students ??= self::recipients($db, $t);
                foreach ($students as $u) PortalNotifications::notify($db, $u, 'class', $head, $body, 'students/timetable/index.php', null, true);
                PortalNotifications::notify($db, (int) $t['tutor_user_id'], 'class', $head . ' (you teach)', $body, 'tutor/timetable/index.php', null, true);
                $count++;
            }
        }
        return $count;
    }
}
