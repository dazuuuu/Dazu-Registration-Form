<?php
/**
 * The shared Room and Visitors diary (tables 131). Everything here is new; nothing existing is edited.
 *
 *  - Room bookings and visitors are plain text entries; nothing links to a student.
 *  - A room cannot be booked twice at the same time. The server checks it inside a short lock on that room and day (secretary_diary_locks), so two secretaries booking at the same moment cannot both succeed. A clash
 *    also counts a Staff Calendar event that has that room in its location. A clash saves nothing and says clearly who has the room.
 *  - Staff Calendar events with a room in their location (for example "Nairobi House (Cianda House), 4th Floor, Room 406B") appear in the diary as read-only bookings that come from the calendar. They are READ
 *    through the calendar's own range function; nothing in the calendar is changed and nothing here can change them.
 *  - A secretary can cancel her OWN booking or visitor, never delete (there is no delete here). Every cancel, arrival and leaving is recorded with her name and the time, and goes to the activity record.
 */
require_once __DIR__ . '/../helpers/secretary.php';
require_once __DIR__ . '/../helpers/secretary_activity.php';

class SecretaryDiaryService
{
    public const BRANCHES = ['nairobi' => 'Nairobi', 'thika' => 'Thika'];
    public const FILTERS = ['today' => 'Today', 'week' => 'This week', 'rooms' => 'Rooms', 'visitors' => 'Visitors'];
    public const AHEAD_DAYS = 60;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function tz(): DateTimeZone { return new DateTimeZone('Africa/Nairobi'); }
    public static function today(?DateTime $now = null): string { return ($now ? (clone $now)->setTimezone(self::tz()) : new DateTime('now', self::tz()))->format('Y-m-d'); }
    private function stamp(): string { return (new DateTime('now', self::tz()))->format('Y-m-d H:i:s'); }

    private static function hm(string $t): string { return date('g:i a', strtotime('2000-01-01 ' . $t)); }
    private static function roomKey(string $branch, string $room): string { return $branch . '|' . mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $room))); }

    // ------------------------------------------------------------------ checking the forms

    private static function validDate(string $d): bool { $x = DateTime::createFromFormat('!Y-m-d', $d); return $x && $x->format('Y-m-d') === $d; }
    private static function validTime(string $t): bool { return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t); }

    /** Check a room booking form. Returns [data, errors]. */
    public function validateRoom(array $in, string $defaultName): array
    {
        $e = []; $t = static fn($k) => trim((string) ($in[$k] ?? ''));
        $d = ['room' => $t('room'), 'branch' => $t('branch'), 'entry_date' => $t('entry_date'), 'start_time' => $t('start_time'), 'end_time' => $t('end_time'), 'purpose' => $t('purpose'), 'booked_by' => $t('booked_by') ?: $defaultName];
        if ($d['room'] === '' || mb_strlen($d['room']) > 80) $e[] = 'Enter the room (up to 80 letters).';
        if (!isset(self::BRANCHES[$d['branch']])) $e[] = 'Choose the branch: Nairobi or Thika.';
        if (!self::validDate($d['entry_date'])) $e[] = 'Choose the date.';
        if (!self::validTime($d['start_time']) || !self::validTime($d['end_time'])) $e[] = 'Choose the start and end time.';
        elseif ($d['end_time'] <= $d['start_time']) $e[] = 'The end time must be after the start time.';
        if ($d['purpose'] === '' || mb_strlen($d['purpose']) > 300) $e[] = 'Write the purpose (up to 300 letters).';
        if (mb_strlen($d['booked_by']) > 120) $e[] = 'The name the room is booked for is too long.';
        return [$d, $e];
    }

    /** Check a visitor form. Returns [data, errors]. */
    public function validateVisitor(array $in): array
    {
        $e = []; $t = static fn($k) => trim((string) ($in[$k] ?? ''));
        $d = ['person_name' => $t('person_name'), 'sees_who' => $t('sees_who'), 'entry_date' => $t('entry_date'), 'start_time' => $t('start_time'), 'purpose' => $t('purpose')];
        if ($d['person_name'] === '' || mb_strlen($d['person_name']) > 120) $e[] = 'Enter the visitor’s name (up to 120 letters).';
        if ($d['sees_who'] === '' || mb_strlen($d['sees_who']) > 120) $e[] = 'Enter who they come to see.';
        if (!self::validDate($d['entry_date'])) $e[] = 'Choose the date.';
        if (!self::validTime($d['start_time'])) $e[] = 'Choose the time they are expected.';
        if ($d['purpose'] === '' || mb_strlen($d['purpose']) > 300) $e[] = 'Write the purpose (up to 300 letters).';
        return [$d, $e];
    }

    // ------------------------------------------------------------------ the Staff Calendar, read only

    /** "Room 406B" from a calendar location, or ''. */
    public static function roomOf(string $location): string
    {
        return preg_match('/\broom\s*([A-Za-z0-9][A-Za-z0-9\-]*)/i', $location, $m) ? 'Room ' . strtoupper($m[1]) : '';
    }

    /**
     * Calendar events with a room in their location that happen between two dates (inclusive), as read-only rows: key, title, room, branch, date, start, end, note. Private events are never read.
     * Uses the calendar's own range function; nothing in the calendar is changed.
     */
    public function calendarRooms(string $fromDate, string $toDate): array
    {
        $out = [];
        try {
            require_once __DIR__ . '/StaffCalendar.php';
            if (!scal_installed($this->db)) return [];
            $from = new DateTime($fromDate . ' 00:00:00', scal_tz()); $to = (new DateTime($toDate . ' 00:00:00', scal_tz()))->modify('+1 day');
            foreach (scal_range($this->db, ['uid' => 0, 'name' => '', 'first' => '', 'role' => 'tutor'], $from, $to) as $o) {
                $room = self::roomOf((string) ($o['location'] ?? ''));
                if ($room === '' || !empty($o['private'])) continue;
                $branch = stripos((string) $o['location'], 'thika') !== false ? 'thika' : 'nairobi';
                $out[] = ['key' => 'c' . $o['id'] . ':' . $o['occ_date'], 'source' => 'calendar', 'title' => (string) $o['title'], 'room' => $room, 'branch' => $branch, 'date' => $o['occ_date'],
                          'start' => substr((string) $o['occ_start'], 11, 8), 'end' => substr((string) $o['occ_end'], 11, 8), 'all_day' => !empty($o['all_day']), 'note' => 'from the Staff Calendar'];
            }
        } catch (Throwable $e) {
            error_log('[SecretaryDiaryService] calendar: ' . $e->getMessage());
        }
        return $out;
    }

    // ------------------------------------------------------------------ adding

    /** Add a room booking. Returns ['ok', 'id'] or ['ok' => false, 'error', 'clash' => text]. A clash saves nothing. */
    public function addRoom(array $sec, array $d): array
    {
        $key = self::roomKey($d['branch'], $d['room']) . '|' . $d['entry_date'];
        $me = secretary_person_name($this->db, (int) $sec['user_id']);
        try {   // make sure the lock row exists (outside the transaction, so a duplicate does not abort it)
            $this->db->prepare('INSERT INTO secretary_diary_locks (lock_key) VALUES (?)')->execute([mb_substr($key, 0, 120)]);
        } catch (PDOException $e) { /* already there */ }
        try {
            $this->db->beginTransaction();
            $l = $this->db->prepare('SELECT lock_key FROM secretary_diary_locks WHERE lock_key = ? FOR UPDATE');   // one booking at a time for this room and day
            $l->execute([mb_substr($key, 0, 120)]);
            $clash = $this->clashWith($d['branch'], $d['room'], $d['entry_date'], $d['start_time'] . ':00', $d['end_time'] . ':00');
            if ($clash) { $this->db->rollBack(); return ['ok' => false, 'error' => $clash, 'clash' => $clash]; }
            $this->db->prepare("INSERT INTO secretary_diary_entries (kind, room, branch, entry_date, start_time, end_time, purpose, booked_by, status, created_by, created_by_name, created_at, updated_at)
                                VALUES ('room', ?, ?, ?, ?, ?, ?, ?, 'booked', ?, ?, ?, ?)")
                     ->execute([$d['room'], $d['branch'], $d['entry_date'], $d['start_time'] . ':00', $d['end_time'] . ':00', $d['purpose'], $d['booked_by'], (int) $sec['id'], $me, $this->stamp(), $this->stamp()]);
            $id = (int) $this->db->lastInsertId();
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('[SecretaryDiaryService::addRoom] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not save the booking. Nothing was saved. Please try again.'];
        }
        secretary_activity($this->db, 'diary', 'booked a room', (int) $sec['id'], $me, $d['room'] . ' · ' . $d['entry_date'] . ' ' . $d['start_time'] . '-' . $d['end_time'], $d['purpose'], 'diary:' . $id . ':add');
        return ['ok' => true, 'id' => $id];
    }

    /** The clear message for a clash, or '' when the room is free at that time. Looks at the diary and at the Staff Calendar. */
    public function clashWith(string $branch, string $room, string $date, string $start, string $end, int $ignoreId = 0): string
    {
        $q = $this->db->prepare("SELECT id, room, start_time, end_time, booked_by, created_by_name FROM secretary_diary_entries
                                 WHERE kind = 'room' AND branch = ? AND entry_date = ? AND status <> 'cancelled' AND id <> ? AND start_time < ? AND end_time > ?");
        $q->execute([$branch, $date, $ignoreId, $end, $start]);
        $want = self::roomKey($branch, $room);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if (self::roomKey($branch, (string) $r['room']) !== $want) continue;
            return $r['room'] . ' is already booked on ' . date('D j M', strtotime($date)) . ' from ' . self::hm($r['start_time']) . ' to ' . self::hm($r['end_time']) . ' for ' . ($r['booked_by'] ?: $r['created_by_name']) . '. Choose another time or room. Nothing was saved.';
        }
        foreach ($this->calendarRooms($date, $date) as $c) {
            if ($c['all_day'] || $c['branch'] !== $branch || self::roomKey($branch, $c['room']) !== $want) continue;
            if ($c['start'] < $end && $c['end'] > $start) return $c['room'] . ' is already used on ' . date('D j M', strtotime($date)) . ' from ' . self::hm($c['start']) . ' to ' . self::hm($c['end']) . ' by “' . $c['title'] . '” on the Staff Calendar. Choose another time or room. Nothing was saved.';
        }
        return '';
    }

    public function addVisitor(array $sec, array $d): array
    {
        $me = secretary_person_name($this->db, (int) $sec['user_id']);
        try {
            $this->db->prepare("INSERT INTO secretary_diary_entries (kind, person_name, sees_who, entry_date, start_time, purpose, status, created_by, created_by_name, created_at, updated_at)
                                VALUES ('visitor', ?, ?, ?, ?, ?, 'expected', ?, ?, ?, ?)")
                     ->execute([$d['person_name'], $d['sees_who'], $d['entry_date'], $d['start_time'] . ':00', $d['purpose'], (int) $sec['id'], $me, $this->stamp(), $this->stamp()]);
        } catch (Throwable $e) {
            error_log('[SecretaryDiaryService::addVisitor] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not save the visitor. Nothing was saved. Please try again.'];
        }
        $id = (int) $this->db->lastInsertId();
        secretary_activity($this->db, 'diary', 'added a visitor', (int) $sec['id'], $me, $d['person_name'] . ' · sees ' . $d['sees_who'], $d['purpose'], 'diary:' . $id . ':add');
        return ['ok' => true, 'id' => $id];
    }

    // ------------------------------------------------------------------ cancel, arrived, left

    private function entry(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM secretary_diary_entries WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function subject(array $e): string { return $e['kind'] === 'room' ? (string) $e['room'] . ' · ' . $e['entry_date'] : (string) $e['person_name']; }

    /** She cancels HER OWN booking or visitor (never anyone else's, never deleted). Recorded with her name and the time. */
    public function cancel(array $sec, int $id): array
    {
        $e = $this->entry($id);
        if (!$e) return ['ok' => false, 'error' => 'That entry was not found.'];
        if ((int) $e['created_by'] !== (int) $sec['id']) return ['ok' => false, 'error' => 'You can only cancel the entries you added.'];
        if ($e['status'] === 'cancelled') return ['ok' => false, 'error' => 'That entry is already cancelled.'];
        if (in_array($e['status'], ['arrived', 'left'], true)) return ['ok' => false, 'error' => 'The visitor has already arrived, so it cannot be cancelled.'];
        $me = secretary_person_name($this->db, (int) $sec['user_id']);
        $u = $this->db->prepare("UPDATE secretary_diary_entries SET status = 'cancelled', cancelled_at = ?, cancelled_by_name = ?, updated_at = ? WHERE id = ? AND status IN ('booked', 'expected')");
        $u->execute([$this->stamp(), $me, $this->stamp(), $id]);
        if ($u->rowCount() !== 1) return ['ok' => false, 'error' => 'That entry could not be cancelled.'];
        secretary_activity($this->db, 'diary', 'cancelled', (int) $sec['id'], $me, $this->subject($e), $e['kind'] === 'room' ? 'room booking' : 'visitor', 'diary:' . $id . ':cancel');
        return ['ok' => true];
    }

    /** Any secretary marks a visitor Arrived or Left, on the day of the visit only. */
    public function mark(array $sec, int $id, string $to, ?DateTime $now = null): array
    {
        $e = $this->entry($id);
        if (!$e || $e['kind'] !== 'visitor') return ['ok' => false, 'error' => 'That visitor was not found.'];
        if ($e['entry_date'] !== self::today($now)) return ['ok' => false, 'error' => 'A visitor can only be marked Arrived or Left on the day of the visit.'];
        $me = secretary_person_name($this->db, (int) $sec['user_id']);
        if ($to === 'arrived') {
            $u = $this->db->prepare("UPDATE secretary_diary_entries SET status = 'arrived', arrived_at = ?, updated_at = ? WHERE id = ? AND status = 'expected'");
            $u->execute([$this->stamp(), $this->stamp(), $id]);
            if ($u->rowCount() !== 1) return ['ok' => false, 'error' => 'This visitor is not waiting to arrive.'];
        } elseif ($to === 'left') {
            $u = $this->db->prepare("UPDATE secretary_diary_entries SET status = 'left', left_at = ?, updated_at = ? WHERE id = ? AND status = 'arrived'");
            $u->execute([$this->stamp(), $this->stamp(), $id]);
            if ($u->rowCount() !== 1) return ['ok' => false, 'error' => 'Mark the visitor Arrived first.'];
        } else {
            return ['ok' => false, 'error' => 'Unknown action.'];
        }
        secretary_activity($this->db, 'diary', $to, (int) $sec['id'], $me, (string) $e['person_name'], 'visitor', 'diary:' . $id . ':' . $to);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------ listing

    /** The date range a filter shows. */
    public static function range(string $filter, ?DateTime $now = null): array
    {
        $today = self::today($now);
        if ($filter === 'week') {
            $d = new DateTime($today, self::tz());
            $mon = (clone $d)->modify('-' . ((int) $d->format('N') - 1) . ' days');
            return [$mon->format('Y-m-d'), (clone $mon)->modify('+6 days')->format('Y-m-d')];
        }
        if ($filter === 'rooms' || $filter === 'visitors') return [$today, (new DateTime($today, self::tz()))->modify('+' . self::AHEAD_DAYS . ' days')->format('Y-m-d')];
        return [$today, $today];
    }

    /**
     * The diary rows for a filter, in time order (diary entries and read-only calendar bookings together). $secretaryId marks which entries she may cancel.
     * Row: key, source (diary|calendar), id, kind (room|visitor), title, sub, date, time, status, label, by, mine, can_cancel, can_arrive, can_leave.
     */
    public function rows(string $filter, int $secretaryId = 0, ?DateTime $now = null): array
    {
        $filter = isset(self::FILTERS[$filter]) ? $filter : 'today';
        [$from, $to] = self::range($filter, $now);
        $today = self::today($now);
        $st = $this->db->prepare('SELECT * FROM secretary_diary_entries WHERE entry_date >= ? AND entry_date <= ? ORDER BY entry_date, start_time, id');
        $st->execute([$from, $to]);
        $rows = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $e) {
            if ($filter === 'rooms' && $e['kind'] !== 'room') continue;
            if ($filter === 'visitors' && $e['kind'] !== 'visitor') continue;
            $isRoom = $e['kind'] === 'room';
            $labels = ['booked' => 'Booked', 'expected' => 'Expected', 'arrived' => 'Arrived', 'left' => 'Left', 'cancelled' => 'Cancelled'];
            $rows[] = ['key' => 'd' . $e['id'], 'source' => 'diary', 'id' => (int) $e['id'], 'kind' => $e['kind'],
                       'title' => $isRoom ? $e['room'] . ' · ' . self::hm($e['start_time']) . ' to ' . self::hm($e['end_time']) : $e['person_name'] . ' · ' . self::hm($e['start_time']),
                       'sub' => $isRoom ? $e['purpose'] . ' · booked by ' . ($e['booked_by'] ?: $e['created_by_name']) . ' · ' . (self::BRANCHES[$e['branch']] ?? $e['branch']) : $e['purpose'] . ' · meets ' . $e['sees_who'],
                       'date' => $e['entry_date'], 'time' => $e['start_time'], 'status' => $e['status'], 'label' => $labels[$e['status']] ?? $e['status'], 'by' => $e['created_by_name'],
                       'mine' => (int) $e['created_by'] === $secretaryId && $secretaryId > 0, 'can_cancel' => (int) $e['created_by'] === $secretaryId && $secretaryId > 0 && in_array($e['status'], ['booked', 'expected'], true),
                       'can_arrive' => !$isRoom && $e['status'] === 'expected' && $e['entry_date'] === $today, 'can_leave' => !$isRoom && $e['status'] === 'arrived' && $e['entry_date'] === $today];
        }
        if ($filter !== 'visitors') {
            foreach ($this->calendarRooms($from, $to) as $c) {
                $rows[] = ['key' => $c['key'], 'source' => 'calendar', 'id' => 0, 'kind' => 'room', 'title' => $c['room'] . ' · ' . ($c['all_day'] ? 'All day' : self::hm($c['start']) . ' to ' . self::hm($c['end'])),
                           'sub' => $c['title'] . ' · from the Staff Calendar', 'date' => $c['date'], 'time' => $c['all_day'] ? '00:00:00' : $c['start'], 'status' => 'calendar', 'label' => 'Booked', 'by' => '', 'mine' => false,
                           'can_cancel' => false, 'can_arrive' => false, 'can_leave' => false];
            }
        }
        usort($rows, static fn($a, $b) => [$a['date'], $a['time'], $a['key']] <=> [$b['date'], $b['time'], $b['key']]);
        return $rows;
    }

    /** Rooms already used (for the form's suggestions): the diary's own and the calendar's of the coming weeks. */
    public function roomNames(): array
    {
        $n = array_map('strval', $this->db->query("SELECT DISTINCT room FROM secretary_diary_entries WHERE kind = 'room' AND room IS NOT NULL ORDER BY room")->fetchAll(PDO::FETCH_COLUMN));
        foreach ($this->calendarRooms(self::today(), (new DateTime('now', self::tz()))->modify('+90 days')->format('Y-m-d')) as $c) $n[] = $c['room'];
        return array_values(array_unique($n));
    }

    /** Today's entries for the dashboard box: [['title', 'time', 'note']] (cancelled ones left out). */
    public function dashboard(?DateTime $now = null): array
    {
        $out = [];
        foreach ($this->rows('today', 0, $now) as $r) {
            if ($r['status'] === 'cancelled') continue;
            $out[] = ['title' => $r['title'], 'time' => '', 'note' => $r['sub']];
        }
        return $out;
    }
}
