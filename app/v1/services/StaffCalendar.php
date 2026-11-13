<?php
// app/v1/services/StaffCalendar.php — the Staff Calendar: one calendar shared by the tutors and the super admin. Separate from the Class Calendar.
//
// THE RULES, checked here on the server on every request (the menu only hides the link):
//  * Only a real tutor or the super admin may open it. The role is read from the database (users.role_id), never from the page or the session alone, so an admin,
//    the "staff" role, a student, or an admin who also teaches is refused (scal_actor() returns null).
//  * A private event ("private to me only") is seen only by the person who added it, everywhere: month, week, day, agenda, search, up next, counts.
//  * Only the owner (or the super admin) can change or delete an event. The owner is shown as "Added by <name>", the name only, never the role.
//  * All times are Nairobi time (EAT). They are stored as Nairobi time and shown as they are, so no conversion can shift them.
//  * Events are loaded for the visible range only; a repeating event is kept once and worked out for the days asked for.

if (!defined('SCAL_TZ')) define('SCAL_TZ', 'Africa/Nairobi');
if (!defined('SCAL_REPEATS')) define('SCAL_REPEATS', ['none' => 'Does not repeat', 'weekly' => 'Every week', 'monthly' => 'Every month', 'yearly' => 'Every year']);

if (!function_exists('scal_installed')) {

    function scal_tz(): DateTimeZone { static $tz = null; return $tz ??= new DateTimeZone(SCAL_TZ); }

    function scal_now(): DateTime { return new DateTime('now', scal_tz()); }

    function scal_installed(PDO $db): bool
    {
        try { $db->query('SELECT 1 FROM staff_calendar_events WHERE 1 = 0'); $db->query('SELECT 1 FROM staff_calendar_event_types WHERE 1 = 0'); $db->query('SELECT remind_email FROM staff_calendar_events WHERE 1 = 0'); $db->query('SELECT 1 FROM staff_calendar_checklist WHERE 1 = 0'); $db->query('SELECT last_edited_at FROM staff_calendar_events WHERE 1 = 0'); return true; }
        catch (Throwable $e) { return false; }
    }

    /** A person's display name: first and last name, else the username. Never the role. */
    function scal_display_name(?string $first, ?string $last, ?string $username): string
    {
        $n = trim(trim((string) $first) . ' ' . trim((string) $last));
        return $n !== '' ? $n : trim((string) $username);
    }

    /**
     * Who is asking, or null when they may not use the Staff Calendar. Only a tutor (active, with an active tutor record) or the super admin passes.
     * @return array{uid:int, name:string, first:string, role:string}|null role is 'tutor' or 'superadmin'
     */
    function scal_actor(PDO $db, ?int $userId = null): ?array
    {
        $uid = $userId ?? (int) ($_SESSION['user_id'] ?? 0);
        if ($uid <= 0) return null;
        try {
            $st = $db->prepare("SELECT u.id, u.username, r.role_name, up.first_name, up.last_name,
                                       (SELECT COUNT(*) FROM tutors t WHERE t.user_id = u.id AND t.is_active = 1) AS live_tutor
                                FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN user_profiles up ON up.user_id = u.id
                                WHERE u.id = ? AND u.is_active = 1");
            $st->execute([$uid]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return null;
        }
        if (!$r) return null;
        $role = (string) $r['role_name'];
        if ($role === 'tutor' && (int) $r['live_tutor'] < 1) return null;
        if (!in_array($role, ['tutor', 'superadmin'], true)) return null;
        $name = scal_display_name($r['first_name'], $r['last_name'], $r['username']);
        return ['uid' => (int) $r['id'], 'name' => $name, 'first' => trim((string) $r['first_name']) !== '' ? trim((string) $r['first_name']) : $name, 'role' => $role];
    }

    /** For the menus: should this person see the "Staff Calendar" item? */
    function scal_can_see_menu(PDO $db, int $userId): bool { return scal_actor($db, $userId) !== null; }

    /** Is the "type is on" switch there (migration 119)? */
    function scal_types_switchable(PDO $db): bool
    {
        static $ok = null;
        if ($ok === null) { try { $db->query('SELECT is_active FROM staff_calendar_event_types WHERE 1 = 0'); $ok = true; } catch (Throwable $e) { $ok = false; } }
        return $ok;
    }

    /** The event types that are switched on (the ones the calendar, the filter and the New event form use). Pass true to get every type. */
    function scal_types(PDO $db, bool $all = false): array
    {
        $on = (!$all && scal_types_switchable($db)) ? ' WHERE is_active = 1' : '';
        return $db->query('SELECT type_key, label, color FROM staff_calendar_event_types' . $on . ' ORDER BY sort_order, id')->fetchAll(PDO::FETCH_ASSOC);
    }

    /** SQL that leaves out events of a type that is switched off (their events are hidden everywhere, not deleted). */
    function scal_type_on_sql(PDO $db): string { return scal_types_switchable($db) ? ' AND COALESCE(t.is_active, 1) = 1' : ''; }

    function scal_type_color(PDO $db, string $key): ?string
    {
        $st = $db->prepare('SELECT color FROM staff_calendar_event_types WHERE type_key = ?'); $st->execute([$key]);
        $c = $st->fetchColumn();
        return $c !== false && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $c) ? (string) $c : null;
    }

    function scal_type_is_on(PDO $db, string $key): bool
    {
        if (!scal_types_switchable($db)) return true;
        $st = $db->prepare('SELECT is_active FROM staff_calendar_event_types WHERE type_key = ?'); $st->execute([$key]);
        $v = $st->fetchColumn();
        return $v === false || (int) $v === 1;
    }

    /** "2 pm", "8:30 am". */
    function scal_time(DateTimeInterface $d): string { return $d->format('i') === '00' ? $d->format('g a') : $d->format('g:i a'); }

    // ------------------------------------------------------------------ repeats

    /** Add whole months or years to a date keeping the day of the month (31 Jan + 1 month = 28/29 Feb). */
    function scal_add_months(DateTime $d, int $months): DateTime
    {
        $y = (int) $d->format('Y'); $m = (int) $d->format('n') - 1 + $months; $day = (int) $d->format('j');
        $y += intdiv($m, 12); $m %= 12; if ($m < 0) { $m += 12; $y--; }
        $dim = (int) (new DateTime(sprintf('%04d-%02d-01', max(1, $y), $m + 1), $d->getTimezone()))->format('t');
        $out = clone $d;
        $out->setDate($y, $m + 1, min($day, $dim));
        return $out;
    }

    /**
     * The times an event happens within [$from, $to): [[start, end], ...] as DateTime. A repeating event has no end date.
     * @return array<int, array{0:DateTime,1:DateTime}>
     */
    function scal_occurrences(array $e, DateTime $from, DateTime $to): array
    {
        $tz = scal_tz();
        $start = new DateTime((string) $e['start_at'], $tz);
        $end = new DateTime((string) $e['end_at'], $tz);
        $len = max(0, $end->getTimestamp() - $start->getTimestamp());
        $out = [];
        $rule = (string) ($e['repeat_rule'] ?? 'none');
        $hit = fn(DateTime $s): bool => $s < $to && $s->getTimestamp() + $len >= $from->getTimestamp();
        $mk = function (DateTime $s) use ($len, $tz): array { $x = clone $s; $x->setTimezone($tz); return [$x, (clone $x)->setTimestamp($x->getTimestamp() + $len)]; };
        if ($rule === 'weekly') {
            $n = max(0, (int) floor(($from->getTimestamp() - $len - $start->getTimestamp()) / 604800));
            for ($i = 0; $i < 400; $i++, $n++) {
                $s = (clone $start)->modify('+' . $n . ' weeks');
                if ($s >= $to) break;
                if ($hit($s)) $out[] = $mk($s);
            }
        } elseif ($rule === 'monthly' || $rule === 'yearly') {
            $step = $rule === 'yearly' ? 12 : 1;
            $gap = ((int) $from->format('Y') - (int) $start->format('Y')) * 12 + ((int) $from->format('n') - (int) $start->format('n'));
            $k = max(0, intdiv(max(0, $gap - 1 - intdiv($len, 2419200)), $step));   // 2419200 s = 28 days
            for ($i = 0; $i < 400; $i++, $k++) {
                $s = scal_add_months($start, $k * $step);
                if ($s >= $to) break;
                if ($hit($s)) $out[] = $mk($s);
            }
        } elseif ($hit($start)) {
            $out[] = $mk($start);
        }
        return $out;
    }

    // ------------------------------------------------------------------ reading

    function scal_select(): string
    {
        return "SELECT e.*, t.label AS type_label, t.color AS type_color,
                       up.first_name AS owner_first, up.last_name AS owner_last, u.username AS owner_username,
                       ep.first_name AS editor_first, ep.last_name AS editor_last, eu.username AS editor_username
                FROM staff_calendar_events e
                LEFT JOIN staff_calendar_event_types t ON t.type_key = e.type_key
                LEFT JOIN users u ON u.id = e.owner_user_id
                LEFT JOIN user_profiles up ON up.user_id = e.owner_user_id
                LEFT JOIN users eu ON eu.id = e.last_edited_by
                LEFT JOIN user_profiles ep ON ep.user_id = e.last_edited_by";
    }

    /** One event row ready for the screen (owner name only, colour, plain flags). */
    function scal_shape(array $r): array
    {
        $owner = scal_display_name($r['owner_first'] ?? '', $r['owner_last'] ?? '', $r['owner_username'] ?? '');
        return [
            'id' => (int) $r['id'], 'title' => (string) $r['title'], 'description' => (string) ($r['description'] ?? ''), 'location' => (string) ($r['location'] ?? ''),
            'cover' => (string) ($r['cover_image'] ?? ''), 'type' => (string) $r['type_key'], 'type_label' => (string) ($r['type_label'] ?? ucfirst((string) $r['type_key'])),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($r['type_color'] ?? '')) ? $r['type_color'] : '#048C44',
            'start_at' => (string) $r['start_at'], 'end_at' => (string) $r['end_at'], 'all_day' => (int) $r['all_day'] === 1, 'repeat' => (string) $r['repeat_rule'],
            'owner_id' => (int) $r['owner_user_id'], 'owner' => $owner !== '' ? $owner : 'A former colleague',
            'notify' => (int) $r['notify_all'] === 1, 'private' => (int) $r['is_private'] === 1,
            'created_at' => (string) ($r['created_at'] ?? ''), 'edited_at' => (string) ($r['last_edited_at'] ?? ''),
            'editor' => $r['last_edited_by'] ? scal_display_name($r['editor_first'] ?? '', $r['editor_last'] ?? '', $r['editor_username'] ?? '') : '',
            'rem_day_before' => (int) ($r['remind_day_before'] ?? 1) === 1, 'rem_on_day' => (int) ($r['remind_on_day'] ?? 1) === 1,
            'rem_hour_before' => (int) ($r['remind_hour_before'] ?? 1) === 1, 'rem_email' => (int) ($r['remind_email'] ?? 1) === 1,
        ];
    }

    /** Events the viewer may see that happen in [$from, $to), one entry per occurrence, in time order. Loads that range only. */
    function scal_range(PDO $db, array $actor, DateTime $from, DateTime $to, ?string $titleLike = null): array
    {
        $sql = scal_select() . ' WHERE (e.is_private = 0 OR e.owner_user_id = ?) AND e.start_at < ? AND (e.repeat_rule <> \'none\' OR e.end_at >= ?)' . scal_type_on_sql($db);
        $args = [$actor['uid'], $to->format('Y-m-d H:i:s'), $from->format('Y-m-d H:i:s')];
        if ($titleLike !== null && $titleLike !== '') { $sql .= ' AND LOWER(e.title) LIKE LOWER(?)'; $args[] = '%' . addcslashes($titleLike, '%_\\') . '%'; }
        $st = $db->prepare($sql . ' ORDER BY e.start_at, e.id');
        $st->execute($args);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ev = scal_shape($row);
            foreach (scal_occurrences($row, $from, $to) as [$s, $e]) {
                $out[] = $ev + ['occ_start' => $s->format('Y-m-d H:i:s'), 'occ_end' => $e->format('Y-m-d H:i:s'), 'occ_date' => $s->format('Y-m-d')];
            }
        }
        usort($out, fn($a, $b) => [$a['occ_start'], $b['all_day'] ? 0 : 1, $a['id']] <=> [$b['occ_start'], $a['all_day'] ? 0 : 1, $b['id']]);
        return $out;
    }

    /** Group occurrences by each day they cover (a 3-day event appears on all 3 days), inside [$from, $to). */
    function scal_by_day(array $occ, DateTime $from, DateTime $to): array
    {
        $by = [];
        foreach ($occ as $o) {
            $d = new DateTime(substr($o['occ_start'], 0, 10), scal_tz());
            $last = new DateTime(substr($o['occ_end'], 0, 10), scal_tz());
            for ($i = 0; $i < 400 && $d <= $last; $i++, $d->modify('+1 day')) {
                if ($d < (clone $from)->setTime(0, 0) || $d >= $to) continue;
                $by[$d->format('Y-m-d')][] = $o;
            }
        }
        return $by;
    }

    /** The next event that has not finished yet (starting from $now), or null. Looks ahead 400 days, so a yearly event is found too. */
    function scal_next(PDO $db, array $actor, DateTime $now): ?array
    {
        $occ = scal_range($db, $actor, $now, (clone $now)->modify('+400 days'));
        foreach ($occ as $o) if ($o['occ_end'] >= $now->format('Y-m-d H:i:s')) return $o;
        return null;
    }

    /** "Today", "Tomorrow" or "In 8 days" from the start day of an event (a day count, so an event today is "Today"). */
    function scal_in_days(array $occ, DateTime $now): string
    {
        $today = new DateTime($now->format('Y-m-d'), scal_tz());
        $day = new DateTime(max($occ['occ_date'], $today->format('Y-m-d')), scal_tz());
        $n = (int) $today->diff($day)->format('%r%a');
        return $n <= 0 ? 'Today' : ($n === 1 ? 'Tomorrow' : 'In ' . $n . ' days');
    }

    function scal_in_days_words(array $occ, DateTime $now): string
    {
        $t = scal_in_days($occ, $now);
        return $t === 'Today' || $t === 'Tomorrow' ? strtolower($t) : lcfirst($t);
    }

    /** When an occurrence happens, in words: "Sat 21 Nov 2026 · 10 am – 12 pm", "Mon 2 Nov – Wed 4 Nov 2026 · All day". Nairobi time. */
    function scal_when(array $o): string
    {
        $s = new DateTime($o['occ_start'], scal_tz());
        $e = new DateTime($o['occ_end'], scal_tz());
        $sameDay = $s->format('Y-m-d') === $e->format('Y-m-d');
        if ($o['all_day']) return $sameDay ? $s->format('D j M Y') . ' · All day' : $s->format('D j M') . ' – ' . $e->format('D j M Y') . ' · All day';
        if ($sameDay) return $s->format('D j M Y') . ' · ' . scal_time($s) . ($e > $s ? ' – ' . scal_time($e) : '');
        return $s->format('D j M') . ', ' . scal_time($s) . ' – ' . $e->format('D j M Y') . ', ' . scal_time($e);
    }

    /** The one occurrence of an event on a day ("d"), or the first time it happens when it does not fall on that day. Null when the viewer may not see the event. */
    function scal_occurrence(PDO $db, array $actor, int $id, string $day): ?array
    {
        $ev = scal_find($db, $actor, $id);
        if (!$ev) return null;
        $d = DateTime::createFromFormat('!Y-m-d', $day, scal_tz());
        $hit = null;
        if ($d && $d->format('Y-m-d') === $day) {
            $hit = scal_occurrences($ev + ['repeat_rule' => $ev['repeat']], $d, (clone $d)->modify('+1 day'))[0] ?? null;
        }
        [$s, $e] = $hit ?? [new DateTime($ev['start_at'], scal_tz()), new DateTime($ev['end_at'], scal_tz())];
        return $ev + ['occ_start' => $s->format('Y-m-d H:i:s'), 'occ_end' => $e->format('Y-m-d H:i:s'), 'occ_date' => $s->format('Y-m-d')];
    }

    /** Reminder times as text only (nothing is sent yet): 8:00 am the day before, 8:00 am on the day, and one hour before a timed event.
     *  "20 Nov 8:00 am · 21 Nov 8:00 am · 9:00 am" (the date is left out when it is the same as the one before). */
    function scal_reminder_times(array $o): array
    {
        $s = new DateTime($o['occ_start'], scal_tz());
        $list = [];
        $dayOf = (clone $s)->setTime(8, 0);
        if ($o['rem_day_before'] ?? true) $list[] = (clone $s)->setTime(8, 0)->modify('-1 day');
        if (($o['rem_on_day'] ?? true) && ($o['all_day'] || $dayOf < $s)) $list[] = $dayOf;
        if (($o['rem_hour_before'] ?? true) && !$o['all_day']) { $hb = (clone $s)->modify('-1 hour'); if (!in_array($hb, $list, false)) $list[] = $hb; }
        usort($list, fn($a, $b) => $a <=> $b);
        return $list;
    }

    function scal_reminders(array $o): string
    {
        $out = []; $prev = '';
        foreach (scal_reminder_times($o) as $t) {
            $out[] = ($t->format('Y-m-d') === $prev ? '' : $t->format('j M') . ' ') . $t->format('g:i a');
            $prev = $t->format('Y-m-d');
        }
        return implode(' · ', $out);
    }

    function scal_initials(string $name): string
    {
        $w = preg_split('/\s+/', trim($name)) ?: [];
        $i = '';
        foreach (array_slice($w, 0, 2) as $p) $i .= mb_strtoupper(mb_substr($p, 0, 1));
        return $i !== '' ? $i : '?';
    }

    /** The checklist of an event: [['t' => label, 'd' => ticked], ...] in order. */
    function scal_checklist(PDO $db, int $eventId): array
    {
        $st = $db->prepare('SELECT label, is_done FROM staff_calendar_checklist WHERE event_id = ? ORDER BY sort_order, id');
        $st->execute([$eventId]);
        return array_map(fn($r) => ['t' => (string) $r['label'], 'd' => (int) $r['is_done'] === 1], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Everything the small card needs for one occurrence. The owner is the name only, never the role. */
    function scal_card(PDO $db, array $actor, array $o, DateTime $now): array
    {
        $s = new DateTime($o['occ_start'], scal_tz()); $e = new DateTime($o['occ_end'], scal_tz());
        $same = $s->format('Y-m-d') === $e->format('Y-m-d');
        $yr = $s->format('Y') !== $now->format('Y') ? ' Y' : '';
        $dateLine = $same ? $s->format('l, j F' . $yr) : $s->format('D j M') . ' – ' . $e->format('D j M Y');
        $timeLine = $o['all_day'] ? 'All day' : ($s->format('g:i a') . ($e > $s ? ' to ' . $e->format('g:i a') : ''));
        $photo = '';
        try { require_once __DIR__ . '/../helpers/user_photo.php'; $photo = (string) user_photo_url($db, (int) $o['owner_id']); } catch (Throwable $x) { $photo = ''; }
        $finished = $o['occ_end'] < $now->format('Y-m-d H:i:s');
        return [
            'id' => $o['id'], 'title' => $o['title'], 'color' => $o['color'], 'type' => $o['type'], 'type_label' => $o['type_label'],
            'date_line' => $dateLine, 'time_line' => $timeLine, 'location' => $o['location'], 'description' => $o['description'],
            'reminders' => scal_reminders($o), 'owner' => $o['owner'], 'initials' => scal_initials($o['owner']), 'photo' => $photo,
            'cover' => $o['cover'] !== '' ? url($o['cover']) : '', 'badge' => scal_badge($o, $now),
            'repeat_label' => $o['repeat'] !== 'none' ? (SCAL_REPEATS[$o['repeat']] ?? '') : '', 'private' => $o['private'],
            'can_change' => scal_can_change($actor, $o),
            // what the edit form is filled with: only handed to the owner and the super admin
            'edit' => scal_can_change($actor, $o) ? [
                'id' => $o['id'], 'title' => $o['title'], 'type' => $o['type'], 'description' => $o['description'], 'location' => $o['location'],
                'all_day' => $o['all_day'], 'repeat' => $o['repeat'], 'owner' => $o['owner'], 'notify' => $o['notify'], 'private' => $o['private'],
                'start_date' => substr($o['start_at'], 0, 10), 'start_time' => substr($o['start_at'], 11, 5), 'end_date' => substr($o['end_at'], 0, 10), 'end_time' => substr($o['end_at'], 11, 5),
                'cover' => $o['cover'] !== '' ? url($o['cover']) : '', 'rem_day_before' => $o['rem_day_before'], 'rem_on_day' => $o['rem_on_day'],
                'rem_hour_before' => $o['rem_hour_before'], 'rem_email' => $o['rem_email'], 'checklist' => scal_checklist($db, (int) $o['id']),
                'tasks' => (function () use ($db, $o) { require_once __DIR__ . '/StaffProjects.php'; return spj_installed($db) ? spj_tasks($db, (int) $o['id']) : []; })(),
            ] : null,
        ];
    }

    function scal_badge(array $o, DateTime $now): string
    {
        if ($o['occ_end'] >= $now->format('Y-m-d H:i:s')) return scal_in_days($o, $now);
        $today = new DateTime($now->format('Y-m-d'), scal_tz());
        $n = (int) (new DateTime(substr($o['occ_end'], 0, 10), scal_tz()))->diff($today)->format('%a');
        return $n <= 0 ? 'Today' : ($n === 1 ? 'Yesterday' : $n . ' days ago');
    }

    /** First line of a place and the rest: "Nairobi House (Cianda House), 4th Floor, Room 406B" -> ["Nairobi House (Cianda House)", "4th Floor, Room 406B"] (a comma inside brackets does not split). */
    function scal_split_place(string $loc): array
    {
        $depth = 0;
        for ($i = 0, $n = mb_strlen($loc); $i < $n; $i++) {
            $c = mb_substr($loc, $i, 1);
            if ($c === '(') $depth++;
            elseif ($c === ')') $depth = max(0, $depth - 1);
            elseif ($c === ',' && $depth === 0) return [trim(mb_substr($loc, 0, $i)), trim(mb_substr($loc, $i + 1))];
        }
        return [trim($loc), ''];
    }

    /** The checklist with ids, for the panel: [['id' => 5, 't' => 'Term review', 'd' => false], ...]. */
    function scal_checklist_rows(PDO $db, int $eventId): array
    {
        $st = $db->prepare('SELECT id, label, is_done FROM staff_calendar_checklist WHERE event_id = ? ORDER BY sort_order, id');
        $st->execute([$eventId]);
        return array_map(fn($r) => ['id' => (int) $r['id'], 't' => (string) $r['label'], 'd' => (int) $r['is_done'] === 1], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Tick or untick one checklist item. Every tutor and the super admin who can see the event may do it (a private event: only its owner). It saves at once for everyone. */
    function scal_tick(PDO $db, array $actor, int $eventId, int $itemId, bool $done): array
    {
        $ev = scal_find($db, $actor, $eventId);
        if (!$ev) return ['ok' => false, 'error' => 'That event was not found.'];
        require_once __DIR__ . '/StaffProjects.php';
        if ($ev['type'] === 'project' && spj_installed($db)) {   // a project's task: the person it is given to, the owner and the super admin only
            $r = spj_task_status($db, $actor, $eventId, $itemId, $done ? 'done' : 'todo');
            return $r['ok'] ? ['ok' => true, 'done' => $done] : $r;
        }
        $st = spj_installed($db) ? $db->prepare("UPDATE staff_calendar_checklist SET is_done = ?, status = CASE WHEN ? = 1 THEN 'done' ELSE 'todo' END WHERE id = ? AND event_id = ?") : $db->prepare('UPDATE staff_calendar_checklist SET is_done = ? WHERE id = ? AND event_id = ?');
        $st->execute(spj_installed($db) ? [$done ? 1 : 0, $done ? 1 : 0, $itemId, $eventId] : [$done ? 1 : 0, $itemId, $eventId]);
        $chk = $db->prepare('SELECT is_done FROM staff_calendar_checklist WHERE id = ? AND event_id = ?');
        $chk->execute([$itemId, $eventId]);
        $v = $chk->fetchColumn();
        if ($v === false) return ['ok' => false, 'error' => 'That item was not found.'];
        return ['ok' => true, 'done' => (int) $v === 1];
    }

    /** Everything the full details panel shows (the small card's data plus the rest). Names only, never roles. */
    function scal_panel(PDO $db, array $actor, array $o, DateTime $now): array
    {
        $card = scal_card($db, $actor, $o, $now);
        $s = new DateTime($o['occ_start'], scal_tz()); $e = new DateTime($o['occ_end'], scal_tz());
        $same = $s->format('Y-m-d') === $e->format('Y-m-d');
        [$w1, $w2] = scal_split_place($o['location']);
        $fmt = fn(string $ts) => $ts !== '' ? (new DateTime($ts, scal_tz()))->format('j M Y, g:i a') : '';
        $times = array_map(fn($t) => $t->format('D j M g:i a'), scal_reminder_times($o));
        return $card + [
            'badge' => scal_badge($o, $now),
            'date_full' => $same ? $s->format('l, j F Y') : $s->format('D j M') . ' – ' . $e->format('D j M Y'),
            'time_full' => ($o['all_day'] ? 'All day' : $s->format('g:i a') . ($e > $s ? ' to ' . $e->format('g:i a') : '')) . ' · Nairobi time',
            'repeat_text' => $o['repeat'] === 'none' ? '' : 'Repeats ' . strtolower(SCAL_REPEATS[$o['repeat']] ?? ''),
            'where1' => $w1, 'where2' => $w2,
            'checklist_rows' => scal_checklist_rows($db, (int) $o['id']),
            'added_at' => $fmt($o['created_at']),
            'edited_text' => $o['editor'] !== '' && $o['edited_at'] !== '' ? 'Last edited by ' . $o['editor'] . ' · ' . $fmt($o['edited_at']) : 'Not edited yet',
            'reminder_times' => $times,
            'reminder_note' => $o['rem_email'] ? 'Push, email and in the app' : 'Push and in the app',
        ];
    }

    /** A past deadline is crossed out on the calendar. */
    function scal_is_past_deadline(array $o, DateTime $now): bool { return $o['type'] === 'deadline' && $o['occ_end'] < $now->format('Y-m-d H:i:s'); }

    /** Events whose title contains the words typed (any time, any year). One line per event, the next time it happens (or the last, for a finished one). */
    function scal_search(PDO $db, array $actor, string $q, DateTime $now, int $limit = 60): array
    {
        $q = trim($q);
        if ($q === '') return [];
        $nowS = $now->format('Y-m-d H:i:s');
        $sql = scal_select() . ' WHERE (e.is_private = 0 OR e.owner_user_id = ?) AND LOWER(e.title) LIKE LOWER(?)' . scal_type_on_sql($db) . ' ORDER BY e.start_at DESC, e.id DESC';
        $st = $db->prepare($sql . ' LIMIT ' . (int) $limit);
        $st->execute([$actor['uid'], '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%']);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ev = scal_shape($row);
            $next = scal_occurrences($row, $now, (clone $now)->modify('+400 days'));
            [$s, $e] = $next[0] ?? [new DateTime($row['start_at'], scal_tz()), new DateTime($row['end_at'], scal_tz())];
            $out[] = $ev + ['occ_start' => $s->format('Y-m-d H:i:s'), 'occ_end' => $e->format('Y-m-d H:i:s'), 'occ_date' => $s->format('Y-m-d'), 'finished' => $e->format('Y-m-d H:i:s') < $nowS];
        }
        usort($out, fn($a, $b) => [$a['finished'], $a['finished'] ? '' : $a['occ_start']] <=> [$b['finished'], $b['finished'] ? '' : $b['occ_start']]);
        return $out;
    }

    /** One event the viewer may open (private ones only for the owner). */
    function scal_find(PDO $db, array $actor, int $id): ?array
    {
        $st = $db->prepare(scal_select() . ' WHERE e.id = ? AND (e.is_private = 0 OR e.owner_user_id = ?)' . scal_type_on_sql($db));
        $st->execute([$id, $actor['uid']]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? scal_shape($r) : null;
    }

    function scal_can_change(array $actor, array $ev): bool { return $actor['role'] === 'superadmin' || $actor['uid'] === (int) $ev['owner_id']; }

    // ------------------------------------------------------------------ writing

    function scal_upload_dir(): array { return ['abs' => rtrim(str_replace('\\', '/', PROJECT_ROOT), '/') . '/public/uploads/staffcalendar', 'web' => 'uploads/staffcalendar']; }

    function scal_remove_cover(?string $path): void
    {
        $path = (string) $path;
        if ($path === '' || (strpos($path, 'uploads/staffcalendar/') !== 0 && strpos($path, 'uploads/staff_calendar/') !== 0) || strpos($path, '..') !== false) return;
        $abs = rtrim(str_replace('\\', '/', PROJECT_ROOT), '/') . '/public/' . $path;
        if (is_file($abs)) @unlink($abs);
    }

    /**
     * Add (id 0) or change an event. $in: title, description, location, type, all_day, start_date, start_time, end_date, end_time, repeat, notify, private, remove_cover.
     * @return array{ok:bool, error?:string, id?:int}
     */
    function scal_save(PDO $db, array $actor, array $in, ?array $file = null, int $id = 0): array
    {
        $old = null;
        if ($id > 0) {
            $old = scal_find($db, $actor, $id);
            if (!$old) return ['ok' => false, 'error' => 'That event was not found.'];
            if (!scal_can_change($actor, $old)) return ['ok' => false, 'error' => 'Only the person who added an event can change it.'];
        }
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') return ['ok' => false, 'error' => 'Give the event a title.'];
        if (mb_strlen($title) > 200) return ['ok' => false, 'error' => 'The title is too long (200 letters at most).'];
        $desc = trim((string) ($in['description'] ?? ''));
        if (mb_strlen($desc) > 3000) return ['ok' => false, 'error' => 'The description is too long (3000 letters at most).'];
        $loc = trim((string) ($in['location'] ?? ''));
        if (mb_strlen($loc) > 255) return ['ok' => false, 'error' => 'The location is too long (255 letters at most).'];
        $type = (string) ($in['type'] ?? '');
        if (!in_array($type, array_column(scal_types($db), 'type_key'), true)) return ['ok' => false, 'error' => 'Choose a type for the event.'];
        $repeat = (string) ($in['repeat'] ?? 'none');
        if (!isset(SCAL_REPEATS[$repeat])) return ['ok' => false, 'error' => 'Choose how the event repeats.'];
        $allDay = !empty($in['all_day']);
        require_once __DIR__ . '/StaffProjects.php';
        $isProject = $type === 'project';   // the type Project turns the event into a project: always all day, never repeats, never private, needs a start and an end date
        if ($isProject) {
            $allDay = true; $repeat = 'none';
            if (trim((string) ($in['end_date'] ?? '')) === '') return ['ok' => false, 'error' => 'A project needs a start date and an end date.'];
        }
        $tz = scal_tz();
        $sd = DateTime::createFromFormat('!Y-m-d', (string) ($in['start_date'] ?? ''), $tz);
        $ed = DateTime::createFromFormat('!Y-m-d', (string) (($in['end_date'] ?? '') !== '' ? $in['end_date'] : ($in['start_date'] ?? '')), $tz);
        if (!$sd || $sd->format('Y-m-d') !== (string) $in['start_date']) return ['ok' => false, 'error' => 'Choose the start date.'];
        if (!$ed || $ed->format('Y-m-d') !== (string) (($in['end_date'] ?? '') !== '' ? $in['end_date'] : $in['start_date'])) return ['ok' => false, 'error' => 'The end date is not a real date.'];
        if ($allDay) {
            $start = $sd->format('Y-m-d') . ' 00:00:00';
            $end = $ed->format('Y-m-d') . ' 23:59:59';
        } else {
            $st = (string) ($in['start_time'] ?? ''); $et = (string) (($in['end_time'] ?? '') !== '' ? $in['end_time'] : $st);
            if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $st) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $et)) return ['ok' => false, 'error' => 'Choose the start and end times.'];
            $start = $sd->format('Y-m-d') . ' ' . $st . ':00';
            $end = $ed->format('Y-m-d') . ' ' . $et . ':00';
        }
        if ($end < $start) return ['ok' => false, 'error' => 'The event cannot end before it starts.'];
        // the four reminder switches (only saved for now). A request that does not come from the form (no form marker) keeps the defaults: all on.
        $rem = []; foreach (['remind_day_before', 'remind_on_day', 'remind_hour_before', 'remind_email'] as $k) $rem[$k] = isset($in['form_v']) ? (!empty($in[$k]) ? 1 : 0) : ($old ? (int) $old['rem_' . substr($k, 7)] : 1);
        $items = null;   // null = leave the checklist as it is
        if (!$isProject && array_key_exists('checklist', $in)) {
            $list = json_decode((string) $in['checklist'], true);
            if (!is_array($list)) return ['ok' => false, 'error' => 'The checklist could not be read.'];
            if (count($list) > 50) return ['ok' => false, 'error' => 'A checklist can have 50 items at most.'];
            $items = [];
            foreach ($list as $it) {
                $t = trim((string) ($it['t'] ?? ''));
                if ($t === '') continue;
                if (mb_strlen($t) > 200) return ['ok' => false, 'error' => 'A checklist item is too long (200 letters at most).'];
                $items[] = [$t, !empty($it['d']) ? 1 : 0];
            }
        }
        $tasks = null;   // a project's tasks (null = leave them as they are)
        if ($isProject && array_key_exists('tasks', $in) && spj_installed($db)) {
            [$tasks, $terr] = spj_clean_tasks($db, (string) $in['tasks']);
            if ($terr) return ['ok' => false, 'error' => $terr];
        }
        $private = (!$isProject && !empty($in['private'])) ? 1 : 0;   // a project is always visible to everyone
        $notify = (!empty($in['notify']) && !$private) ? 1 : 0;   // a private event is for nobody else, so nobody is told

        $cover = $old ? $old['cover'] : '';
        $newCover = null;
        if ($file && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            require_once __DIR__ . '/../helpers/uploads.php';
            $dir = scal_upload_dir();
            $up = upload_image($file, $dir['abs'], $dir['web'], ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024);
            if (empty($up['ok'])) return ['ok' => false, 'error' => (string) ($up['error'] ?? 'The picture could not be saved.')];
            $newCover = (string) $up['path'];
        }
        $dropOld = ($newCover !== null || !empty($in['remove_cover'])) && $cover !== '';
        $coverValue = $newCover ?? (!empty($in['remove_cover']) ? null : ($cover !== '' ? $cover : null));

        $now = scal_now()->format('Y-m-d H:i:s');
        if ($old) {
            $db->prepare('UPDATE staff_calendar_events SET title = ?, description = ?, location = ?, cover_image = ?, type_key = ?, start_at = ?, end_at = ?, all_day = ?, repeat_rule = ?, notify_all = ?, is_private = ?, remind_day_before = ?, remind_on_day = ?, remind_hour_before = ?, remind_email = ?, updated_at = ?, last_edited_by = ?, last_edited_at = ? WHERE id = ?')
               ->execute([$title, $desc, $loc, $coverValue, $type, $start, $end, $allDay ? 1 : 0, $repeat, $notify, $private, $rem['remind_day_before'], $rem['remind_on_day'], $rem['remind_hour_before'], $rem['remind_email'], $now, $actor['uid'], $now, $id]);
        } else {
            $db->prepare('INSERT INTO staff_calendar_events (title, description, location, cover_image, type_key, start_at, end_at, all_day, repeat_rule, owner_user_id, notify_all, is_private, remind_day_before, remind_on_day, remind_hour_before, remind_email, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
               ->execute([$title, $desc, $loc, $coverValue, $type, $start, $end, $allDay ? 1 : 0, $repeat, $actor['uid'], $notify, $private, $rem['remind_day_before'], $rem['remind_on_day'], $rem['remind_hour_before'], $rem['remind_email'], $now, $now]);
            $id = (int) $db->lastInsertId();
        }
        if ($items !== null) {
            if (spj_installed($db)) spj_delete_event_notices($db, $id);   // the simple checklist has no persons, so no task notices
            $db->prepare('DELETE FROM staff_calendar_checklist WHERE event_id = ?')->execute([$id]);
            $ins = $db->prepare('INSERT INTO staff_calendar_checklist (event_id, label, is_done, sort_order) VALUES (?, ?, ?, ?)');
            foreach ($items as $i => [$t, $d]) $ins->execute([$id, $t, $d, $i]);
        }
        if ($tasks !== null) spj_sync_tasks($db, $id, $tasks);
        if ($dropOld) scal_remove_cover($old['cover']);
        if ($notify && (!$old || !$old['notify'])) scal_notify($db, $actor, $id, $title, $start, $allDay, $loc, $old !== null);
        return ['ok' => true, 'id' => $id];
    }

    function scal_delete(PDO $db, array $actor, int $id): array
    {
        $ev = scal_find($db, $actor, $id);
        if (!$ev) return ['ok' => false, 'error' => 'That event was not found.'];
        if (!scal_can_change($actor, $ev)) return ['ok' => false, 'error' => 'Only the person who added an event can delete it.'];
        require_once __DIR__ . '/StaffProjects.php';
        if (spj_installed($db)) spj_delete_event_notices($db, $id);
        $db->prepare('DELETE FROM staff_calendar_checklist WHERE event_id = ?')->execute([$id]);
        $db->prepare('DELETE FROM staff_calendar_events WHERE id = ?')->execute([$id]);
        scal_remove_cover($ev['cover']);
        return ['ok' => true, 'title' => $ev['title']];
    }

    /** Tell every other tutor and the super admin through the portal notifications. */
    function scal_notify(PDO $db, array $actor, int $id, string $title, string $startAt, bool $allDay, string $loc, bool $isChange): void
    {
        try {
            require_once __DIR__ . '/PortalNotifications.php';
            $rows = $db->query("SELECT u.id, r.role_name FROM users u JOIN roles r ON r.id = u.role_id
                                WHERE u.is_active = 1 AND (r.role_name = 'superadmin' OR (r.role_name = 'tutor' AND EXISTS (SELECT 1 FROM tutors t WHERE t.user_id = u.id AND t.is_active = 1)))")->fetchAll(PDO::FETCH_ASSOC);
            $s = new DateTime($startAt, scal_tz());
            $when = $s->format('D j M Y') . ($allDay ? ', all day' : ', ' . scal_time($s));
            $body = 'Added by ' . $actor['name'] . ' · ' . $when . ($loc !== '' ? ' · ' . $loc : '');
            foreach ($rows as $r) {
                if ((int) $r['id'] === $actor['uid']) continue;
                $path = ($r['role_name'] === 'superadmin' ? 'superadmin' : 'tutor') . '/staffcalendar/index.php?d=' . $s->format('Y-m-d');
                \PortalNotifications::notify($db, (int) $r['id'], 'staff_calendar', ($isChange ? 'Staff event: ' : 'New staff event: ') . $title, $body, $path);
            }
        } catch (Throwable $e) {
            error_log('[StaffCalendar] notify: ' . $e->getMessage());
        }
    }
}
