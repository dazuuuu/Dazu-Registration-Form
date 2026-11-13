<?php
// app/v1/services/StaffBirthdays.php — birthdays and birthday messages on the Staff Calendar (tutors and the super admin only).
//
// THE RULES, checked here on the server (the screens only show them):
//  * Birthdays are kept in staff_calendar_birthdays (day, month, optional year, "show my age"). They are worked out for every year, never stored as events.
//    A 29 February birthday shows on 28 February in years that are not leap years.
//  * Nobody's age is shown unless that person switched "show my age" on (and gave a year). The year is only given to the person themselves and the super admin.
//  * A tutor sets only their own birthday. The super admin can set it for any tutor or for themselves.
//  * On a birthday the first person to open the calendar makes the day's message: one active, approved message the person has not had before, saved in the log with a
//    unique key on (person, year), so it can never be made twice. When every message has been used for that person the cycle starts again.
//  * Only the super admin adds, edits, switches off, approves or rejects messages. Any tutor can suggest one, which waits as "pending". Messages are never deleted.
//  * Names are names only, never roles.
require_once __DIR__ . '/StaffCalendar.php';

if (!defined('SBD_TAGS')) define('SBD_TAGS', ['Funny', 'Cherished', 'Tech', 'Sheng', 'Blessing']);
if (!defined('SBD_COLOR')) define('SBD_COLOR', '#d6457f');

if (!function_exists('sbd_installed')) {

    function sbd_installed(PDO $db): bool
    {
        try {
            foreach (['staff_calendar_birthdays', 'staff_calendar_birthday_messages', 'staff_calendar_birthday_log'] as $t) $db->query("SELECT 1 FROM $t WHERE 1 = 0");
            return true;
        } catch (Throwable $e) { return false; }
    }

    /** The birthday date of a person in a year ("Y-m-d"); 29 February becomes 28 February when the year is not a leap year. */
    function sbd_date(int $day, int $month, int $year): string
    {
        if ($month === 2 && $day === 29 && !checkdate(2, 29, $year)) $day = 28;
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /** Everyone who can have a birthday here: active tutors and the super admin. [users.id => ['id','name','first']]. Roles are used only to choose who; they are never shown. */
    function sbd_people(PDO $db): array
    {
        $st = $db->query("SELECT u.id, u.username, up.first_name, up.last_name
                          FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN user_profiles up ON up.user_id = u.id
                          WHERE u.is_active = 1 AND (r.role_name = 'superadmin' OR (r.role_name = 'tutor' AND EXISTS (SELECT 1 FROM tutors t WHERE t.user_id = u.id AND t.is_active = 1)))
                          ORDER BY up.first_name, u.username");
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $name = scal_display_name($r['first_name'], $r['last_name'], $r['username']);
            $first = trim((string) $r['first_name']) !== '' ? trim((string) $r['first_name']) : (strtok($name, ' ') ?: $name);
            $out[(int) $r['id']] = ['id' => (int) $r['id'], 'name' => $name, 'first' => $first];
        }
        return $out;
    }

    function sbd_initials_of(string $name): string { return scal_initials($name); }

    function sbd_photo(PDO $db, int $uid): string
    {
        try { require_once __DIR__ . '/../helpers/user_photo.php'; return (string) user_photo_url($db, $uid); } catch (Throwable $e) { return ''; }
    }

    /** All saved birthdays of the people who can have one: [users.id => row + name]. */
    function sbd_rows(PDO $db): array
    {
        $people = sbd_people($db);
        $out = [];
        foreach ($db->query('SELECT user_id, birth_day, birth_month, birth_year, show_age FROM staff_calendar_birthdays')->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $p = $people[(int) $r['user_id']] ?? null;
            if (!$p) continue;
            $out[$p['id']] = $p + ['day' => (int) $r['birth_day'], 'month' => (int) $r['birth_month'], 'year' => $r['birth_year'] !== null ? (int) $r['birth_year'] : null, 'show_age' => (int) $r['show_age'] === 1];
        }
        return $out;
    }

    /** One birthday as the calendar draws it (pink chip, row). The age is only there when the person chose to show it. */
    function sbd_occurrence(PDO $db, array $b, string $date): array
    {
        $age = ($b['show_age'] && $b['year']) ? (int) substr($date, 0, 4) - $b['year'] : null;
        return [
            'birthday' => true, 'id' => 0, 'user_id' => $b['id'], 'title' => $b['first'], 'full_name' => $b['name'], 'initials' => sbd_initials_of($b['name']), 'photo' => sbd_photo($db, $b['id']),
            'type' => 'birthday', 'type_label' => 'Birthday', 'color' => scal_type_color($db, 'birthday') ?? SBD_COLOR, 'age' => $age,
            'occ_start' => $date . ' 00:00:00', 'occ_end' => $date . ' 23:59:59', 'occ_date' => $date, 'all_day' => true,
            'location' => '', 'description' => '', 'repeat' => 'none', 'private' => false, 'owner' => $b['name'], 'start_at' => $date . ' 00:00:00', 'end_at' => $date . ' 23:59:59',
        ];
    }

    /** The birthdays that fall in [$from, $to), every year, as calendar entries. */
    function sbd_range(PDO $db, DateTime $from, DateTime $to): array
    {
        $out = [];
        $y1 = (int) $from->format('Y'); $y2 = (int) $to->format('Y');
        $a = $from->format('Y-m-d'); $b = $to->format('Y-m-d');
        foreach (sbd_rows($db) as $row) {
            for ($y = $y1; $y <= $y2; $y++) {
                $d = sbd_date($row['day'], $row['month'], $y);
                if ($d >= $a && $d < $b) $out[] = sbd_occurrence($db, $row, $d);
            }
        }
        return $out;
    }

    /** The next birthdays from today (today included), soonest first: each with days = how many days away. */
    function sbd_upcoming(PDO $db, DateTime $now, int $limit = 5): array
    {
        $today = $now->format('Y-m-d'); $y = (int) $now->format('Y'); $out = [];
        foreach (sbd_rows($db) as $row) {
            $d = sbd_date($row['day'], $row['month'], $y);
            if ($d < $today) $d = sbd_date($row['day'], $row['month'], $y + 1);
            $o = sbd_occurrence($db, $row, $d);
            $o['days'] = (int) (new DateTime($today, scal_tz()))->diff(new DateTime($d, scal_tz()))->format('%a');
            $out[] = $o;
        }
        usort($out, fn($a, $b) => [$a['days'], $a['full_name']] <=> [$b['days'], $b['full_name']]);
        return array_slice($out, 0, $limit);
    }

    function sbd_in_days_text(int $days): string { return $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : 'in ' . $days . ' days'); }

    // ------------------------------------------------------------------ setting a birthday

    /**
     * Add or change a birthday. A tutor can only set their own; the super admin can set anyone's (user_id). $in: day, month, year (optional), show_age.
     * @return array{ok:bool, error?:string}
     */
    function sbd_save(PDO $db, array $actor, array $in): array
    {
        $target = $actor['uid'];
        if ($actor['role'] === 'superadmin' && (int) ($in['user_id'] ?? 0) > 0) $target = (int) $in['user_id'];
        if ($target !== $actor['uid'] && !isset(sbd_people($db)[$target])) return ['ok' => false, 'error' => 'That person was not found.'];
        $d = (int) ($in['day'] ?? 0); $m = (int) ($in['month'] ?? 0);
        $yRaw = trim((string) ($in['year'] ?? ''));
        $y = $yRaw === '' ? null : (ctype_digit($yRaw) ? (int) $yRaw : -1);
        if ($m < 1 || $m > 12 || $d < 1 || $d > 31 || !checkdate($m, $d, 2024)) return ['ok' => false, 'error' => 'That day and month do not exist together.'];   // 2024 is a leap year, so 29 February is allowed
        $thisYear = (int) scal_now()->format('Y');
        if ($y !== null && ($y < 1900 || $y > $thisYear)) return ['ok' => false, 'error' => 'Check the year of birth.'];
        if ($y !== null && $m === 2 && $d === 29 && !checkdate(2, 29, $y)) return ['ok' => false, 'error' => 'That year was not a leap year, so 29 February is not possible.'];
        $showAge = (!empty($in['show_age']) && $y !== null) ? 1 : 0;   // an age can only be shown when the year is known
        $st = $db->prepare('SELECT id FROM staff_calendar_birthdays WHERE user_id = ?'); $st->execute([$target]);
        $now = scal_now()->format('Y-m-d H:i:s');
        if ($st->fetchColumn()) $db->prepare('UPDATE staff_calendar_birthdays SET birth_day = ?, birth_month = ?, birth_year = ?, show_age = ?, updated_at = ? WHERE user_id = ?')->execute([$d, $m, $y, $showAge, $now, $target]);
        else $db->prepare('INSERT INTO staff_calendar_birthdays (user_id, birth_day, birth_month, birth_year, show_age, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([$target, $d, $m, $y, $showAge, $now]);
        return ['ok' => true, 'user_id' => $target];
    }

    function sbd_remove(PDO $db, array $actor, array $in): array
    {
        $target = $actor['uid'];
        if ($actor['role'] === 'superadmin' && (int) ($in['user_id'] ?? 0) > 0) $target = (int) $in['user_id'];
        $db->prepare('DELETE FROM staff_calendar_birthdays WHERE user_id = ?')->execute([$target]);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------ the birthday message

    /** The messages that can be picked: approved and switched on. */
    function sbd_pool(PDO $db): array
    {
        return array_map('intval', $db->query("SELECT id FROM staff_calendar_birthday_messages WHERE status = 'approved' AND is_active = 1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
    }

    function sbd_msg(PDO $db, int $id): ?array
    {
        $st = $db->prepare("SELECT id, body, tag FROM staff_calendar_birthday_messages WHERE id = ?"); $st->execute([$id]);
        $m = $st->fetch(PDO::FETCH_ASSOC);
        if (!$m) return null;
        $rank = $db->prepare("SELECT COUNT(*) FROM staff_calendar_birthday_messages WHERE status = 'approved' AND id <= ?"); $rank->execute([$id]);
        return ['id' => (int) $m['id'], 'body' => (string) $m['body'], 'tag' => (string) $m['tag'], 'rank' => (int) $rank->fetchColumn(),
                'total' => (int) $db->query("SELECT COUNT(*) FROM staff_calendar_birthday_messages WHERE status = 'approved'")->fetchColumn()];
    }

    /**
     * The message for this person's birthday this year. The first call of the day makes it: one at random that this person has not had before, saved in the log.
     * The unique key (person, year) means two people opening the calendar at the same moment still end up with the same single message.
     */
    function sbd_message_for(PDO $db, int $userId, int $year): ?array
    {
        $get = function () use ($db, $userId, $year): ?int {
            $st = $db->prepare('SELECT message_id FROM staff_calendar_birthday_log WHERE user_id = ? AND birth_year = ?'); $st->execute([$userId, $year]);
            $v = $st->fetchColumn();
            return $v === false ? null : (int) $v;
        };
        $id = $get();
        if ($id === null) {
            $pool = sbd_pool($db);
            if (!$pool) return null;
            $st = $db->prepare('SELECT message_id FROM staff_calendar_birthday_log WHERE user_id = ? ORDER BY id DESC'); $st->execute([$userId]);
            $history = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
            // messages used in the current round: the latest (count mod size) of them. When every message was used, the round starts again.
            $round = array_slice($history, 0, count($history) % count($pool));
            $fresh = array_values(array_diff($pool, $round));
            if (!$fresh) $fresh = $pool;
            $pick = $fresh[random_int(0, count($fresh) - 1)];
            try {
                $db->prepare('INSERT INTO staff_calendar_birthday_log (user_id, birth_year, message_id, shown_at) VALUES (?, ?, ?, ?)')->execute([$userId, $year, $pick, scal_now()->format('Y-m-d H:i:s')]);
                $id = $pick;
            } catch (PDOException $e) {
                $id = $get();   // someone else made it a moment ago: use theirs
                if ($id === null) throw $e;
            }
        }
        return sbd_msg($db, $id);
    }

    function sbd_text(string $body, string $first): string { return str_replace('{first_name}', $first, $body); }

    /** Today's birthdays with their message, for the banner. The viewer's own comes first. */
    function sbd_today(PDO $db, array $actor, DateTime $now): array
    {
        $today = new DateTime($now->format('Y-m-d'), scal_tz());
        $list = sbd_range($db, $today, (clone $today)->modify('+1 day'));
        usort($list, fn($a, $b) => [$a['user_id'] === $actor['uid'] ? 0 : 1, $a['full_name']] <=> [$b['user_id'] === $actor['uid'] ? 0 : 1, $b['full_name']]);
        $people = sbd_people($db);
        foreach ($list as &$o) {
            $o['first'] = $people[$o['user_id']]['first'] ?? $o['title'];
            $m = sbd_message_for($db, $o['user_id'], (int) $now->format('Y'));
            $o['message'] = $m ? sbd_text($m['body'], $o['first']) : '';
            $o['message_no'] = $m ? $m['rank'] : 0; $o['message_total'] = $m ? $m['total'] : 0;
            $o['own'] = $o['user_id'] === $actor['uid'];
            $o['sees_count'] = $o['own'] || $actor['role'] === 'superadmin';   // the "Message 14 of 64" line: the birthday person and the super admin only
        }
        unset($o);
        return array_slice($list, 0, 3);
    }

    // ------------------------------------------------------------------ managing the messages (super admin) and suggestions (everyone)

    function sbd_clean_msg(array $in): array
    {
        $body = trim((string) ($in['body'] ?? '')); $tag = (string) ($in['tag'] ?? '');
        if ($body === '') return ['error' => 'Write the message.'];
        if (mb_strlen($body) > 400) return ['error' => 'The message is too long (400 letters at most).'];
        if (!in_array($tag, SBD_TAGS, true)) return ['error' => 'Choose a tag.'];
        return ['body' => $body, 'tag' => $tag];
    }

    function sbd_require_super(array $actor): ?array { return $actor['role'] === 'superadmin' ? null : ['ok' => false, 'error' => 'Only the super admin can manage the birthday messages.']; }

    function sbd_msg_add(PDO $db, array $actor, array $in): array
    {
        if ($e = sbd_require_super($actor)) return $e;
        $c = sbd_clean_msg($in); if (isset($c['error'])) return ['ok' => false, 'error' => $c['error']];
        $db->prepare("INSERT INTO staff_calendar_birthday_messages (body, tag, is_active, status, suggested_by, created_at) VALUES (?, ?, 1, 'approved', NULL, ?)")->execute([$c['body'], $c['tag'], scal_now()->format('Y-m-d H:i:s')]);
        return ['ok' => true];
    }

    function sbd_msg_edit(PDO $db, array $actor, int $id, array $in): array
    {
        if ($e = sbd_require_super($actor)) return $e;
        $c = sbd_clean_msg($in); if (isset($c['error'])) return ['ok' => false, 'error' => $c['error']];
        $st = $db->prepare('UPDATE staff_calendar_birthday_messages SET body = ?, tag = ? WHERE id = ?'); $st->execute([$c['body'], $c['tag'], $id]);
        return sbd_msg_exists($db, $id) ? ['ok' => true] : ['ok' => false, 'error' => 'That message was not found.'];
    }

    function sbd_msg_exists(PDO $db, int $id): bool { $st = $db->prepare('SELECT 1 FROM staff_calendar_birthday_messages WHERE id = ?'); $st->execute([$id]); return (bool) $st->fetchColumn(); }

    /** Switch a message on or off (never deleted, so the log stays correct). Only an approved message can be switched on. */
    function sbd_msg_active(PDO $db, array $actor, int $id, bool $on): array
    {
        if ($e = sbd_require_super($actor)) return $e;
        if (!sbd_msg_exists($db, $id)) return ['ok' => false, 'error' => 'That message was not found.'];
        $db->prepare("UPDATE staff_calendar_birthday_messages SET is_active = ? WHERE id = ? AND status = 'approved'")->execute([$on ? 1 : 0, $id]);
        return ['ok' => true];
    }

    /** Approve (the message becomes active) or reject (it stays on the list as rejected) a suggestion waiting as pending. */
    function sbd_msg_review(PDO $db, array $actor, int $id, bool $approve): array
    {
        if ($e = sbd_require_super($actor)) return $e;
        $chk = $db->prepare('SELECT status FROM staff_calendar_birthday_messages WHERE id = ?'); $chk->execute([$id]);
        $now = $chk->fetchColumn();
        if ($now === false) return ['ok' => false, 'error' => 'That message was not found.'];
        if ($now !== 'pending') return ['ok' => false, 'error' => 'That suggestion was already handled.'];
        $db->prepare("UPDATE staff_calendar_birthday_messages SET status = ?, is_active = ? WHERE id = ? AND status = 'pending'")->execute([$approve ? 'approved' : 'rejected', $approve ? 1 : 0, $id]);
        return ['ok' => true];
    }

    /** Any tutor (or the super admin) can suggest a message. It waits as pending until the super admin approves it. */
    function sbd_suggest(PDO $db, array $actor, array $in): array
    {
        $c = sbd_clean_msg($in); if (isset($c['error'])) return ['ok' => false, 'error' => $c['error']];
        $st = $db->prepare("SELECT COUNT(*) FROM staff_calendar_birthday_messages WHERE suggested_by = ? AND status = 'pending'"); $st->execute([$actor['uid']]);
        if ((int) $st->fetchColumn() >= 5) return ['ok' => false, 'error' => 'You already have 5 suggestions waiting. Please wait for the super admin to look at them.'];
        $db->prepare("INSERT INTO staff_calendar_birthday_messages (body, tag, is_active, status, suggested_by, created_at) VALUES (?, ?, 0, 'pending', ?, ?)")->execute([$c['body'], $c['tag'], $actor['uid'], scal_now()->format('Y-m-d H:i:s')]);
        return ['ok' => true];
    }

    /** The list for the messages page: optional tag, and status (all, approved, pending, rejected, off). Names only for "Suggested by". */
    function sbd_msg_list(PDO $db, string $tag = '', string $status = ''): array
    {
        $sql = "SELECT m.id, m.body, m.tag, m.is_active, m.status, m.suggested_by, up.first_name, up.last_name, u.username, (SELECT COUNT(*) FROM staff_calendar_birthday_log l WHERE l.message_id = m.id) AS used
                FROM staff_calendar_birthday_messages m LEFT JOIN users u ON u.id = m.suggested_by LEFT JOIN user_profiles up ON up.user_id = m.suggested_by WHERE 1 = 1";
        $args = [];
        if (in_array($tag, SBD_TAGS, true)) { $sql .= ' AND m.tag = ?'; $args[] = $tag; }
        if (in_array($status, ['approved', 'pending', 'rejected'], true)) { $sql .= ' AND m.status = ?'; $args[] = $status; }
        elseif ($status === 'off') $sql .= " AND m.status = 'approved' AND m.is_active = 0";
        $st = $db->prepare($sql . " ORDER BY CASE m.status WHEN 'pending' THEN 0 ELSE 1 END, m.id");
        $st->execute($args);
        return array_map(function ($r) {
            return ['id' => (int) $r['id'], 'body' => (string) $r['body'], 'tag' => (string) $r['tag'], 'active' => (int) $r['is_active'] === 1, 'status' => (string) $r['status'],
                    'used' => (int) $r['used'], 'by' => $r['suggested_by'] ? scal_display_name($r['first_name'], $r['last_name'], $r['username']) : ''];
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    function sbd_msg_counts(PDO $db): array
    {
        $out = ['all' => 0, 'pending' => 0, 'tags' => array_fill_keys(SBD_TAGS, 0)];
        foreach ($db->query("SELECT tag, status, COUNT(*) c FROM staff_calendar_birthday_messages GROUP BY tag, status")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($r['status'] === 'approved') { $out['all'] += (int) $r['c']; if (isset($out['tags'][$r['tag']])) $out['tags'][$r['tag']] += (int) $r['c']; }
            if ($r['status'] === 'pending') $out['pending'] += (int) $r['c'];
        }
        return $out;
    }
}
