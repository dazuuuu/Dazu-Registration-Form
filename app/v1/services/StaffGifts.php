<?php
// app/v1/services/StaffGifts.php — the birthday gift tracker of the Staff Calendar. It ONLY records who says they sent a gift and who received it.
// Dazu does not move any money, and nothing here connects to M-Pesa or any payment system.
//
// THE RULES, checked here on the server on every tap (the buttons only show them):
//  * The gift amount (default KES 1,500) and "Excluded from gifts" are changed by the super admin only. A new amount applies to new lists only: each list keeps the amount it was made with.
//  * The first person to open the calendar on a birthday makes that year's list (unique key on person + year, made inside one transaction). It holds every tutor and the super admin except the
//    birthday person, each "not_yet" (or "excluded", with the note, for people excluded at that moment).
//  * A list is open for 30 days after the birthday so late senders can still tap; after that it is read-only history.
//  * Each person can only change their OWN row: "I have sent" (not_yet -> says_sent) and undo (says_sent -> not_yet, until it is confirmed). Nobody can change another person's status.
//  * Only the birthday person or the super admin can see a list, confirm "Says sent" rows (says_sent -> received, recorded with who did it) and use the reminder button.
//  * The reminder button works once a day per birthday and sends gentle in-app notes (shown on the Staff Calendar page) to people whose status is "not_yet".
require_once __DIR__ . '/StaffBirthdays.php';

if (!defined('SG_DEFAULT_AMOUNT')) define('SG_DEFAULT_AMOUNT', 1500);
if (!defined('SG_OPEN_DAYS')) define('SG_OPEN_DAYS', 30);

if (!function_exists('sg_installed')) {

    function sg_installed(PDO $db): bool
    {
        try { foreach (['settings', 'gift_exclusions', 'gift_lists', 'gift_items', 'gift_notes'] as $t) $db->query("SELECT 1 FROM staff_calendar_$t WHERE 1 = 0"); return true; }
        catch (Throwable $e) { return false; }
    }

    function sg_kes($n): string { return 'KES ' . number_format((int) $n); }

    // ------------------------------------------------------------------ settings (super admin only)

    function sg_amount(PDO $db): int
    {
        $st = $db->prepare("SELECT setting_value FROM staff_calendar_settings WHERE setting_key = 'gift_amount'"); $st->execute();
        $v = $st->fetchColumn();
        return ($v !== false && (int) $v > 0) ? (int) $v : SG_DEFAULT_AMOUNT;
    }

    function sg_set_amount(PDO $db, array $actor, $amount): array
    {
        if ($actor['role'] !== 'superadmin') return ['ok' => false, 'error' => 'Only the super admin can change the gift amount.'];
        $raw = trim(str_replace(',', '', (string) $amount));
        if (!ctype_digit($raw) || (int) $raw < 100 || (int) $raw > 1000000) return ['ok' => false, 'error' => 'Enter the amount in KES, from 100 to 1,000,000.'];
        $st = $db->prepare("SELECT 1 FROM staff_calendar_settings WHERE setting_key = 'gift_amount'"); $st->execute();
        $now = scal_now()->format('Y-m-d H:i:s');
        if ($st->fetchColumn()) $db->prepare("UPDATE staff_calendar_settings SET setting_value = ?, updated_by = ?, updated_at = ? WHERE setting_key = 'gift_amount'")->execute([(string) (int) $raw, $actor['uid'], $now]);
        else $db->prepare("INSERT INTO staff_calendar_settings (setting_key, setting_value, updated_by, updated_at) VALUES ('gift_amount', ?, ?, ?)")->execute([(string) (int) $raw, $actor['uid'], $now]);
        return ['ok' => true];
    }

    /** [users.id => note] for people excluded from gifts right now. */
    function sg_exclusions(PDO $db): array
    {
        $out = [];
        foreach ($db->query('SELECT user_id, note FROM staff_calendar_gift_exclusions WHERE is_excluded = 1')->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r['user_id']] = (string) $r['note'];
        return $out;
    }

    function sg_set_excluded(PDO $db, array $actor, int $userId, bool $excluded, string $note): array
    {
        if ($actor['role'] !== 'superadmin') return ['ok' => false, 'error' => 'Only the super admin can exclude someone from gifts.'];
        if (!isset(sbd_people($db)[$userId])) return ['ok' => false, 'error' => 'That person was not found.'];
        $note = trim($note);
        if (mb_strlen($note) > 120) return ['ok' => false, 'error' => 'The note is too long (120 letters at most).'];
        $st = $db->prepare('SELECT 1 FROM staff_calendar_gift_exclusions WHERE user_id = ?'); $st->execute([$userId]);
        $now = scal_now()->format('Y-m-d H:i:s');
        if ($st->fetchColumn()) $db->prepare('UPDATE staff_calendar_gift_exclusions SET is_excluded = ?, note = ?, updated_by = ?, updated_at = ? WHERE user_id = ?')->execute([$excluded ? 1 : 0, $note !== '' ? $note : null, $actor['uid'], $now, $userId]);
        else $db->prepare('INSERT INTO staff_calendar_gift_exclusions (user_id, is_excluded, note, updated_by, updated_at) VALUES (?, ?, ?, ?, ?)')->execute([$userId, $excluded ? 1 : 0, $note !== '' ? $note : null, $actor['uid'], $now]);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------ people

    /** Everyone who can be on a list: active tutors and the super admin, with the role word the list shows ("Tutor" / "Super admin"). */
    function sg_people(PDO $db): array
    {
        $st = $db->query("SELECT u.id, r.role_name FROM users u JOIN roles r ON r.id = u.role_id
                          WHERE u.is_active = 1 AND (r.role_name = 'superadmin' OR (r.role_name = 'tutor' AND EXISTS (SELECT 1 FROM tutors t WHERE t.user_id = u.id AND t.is_active = 1)))");
        $roles = $st->fetchAll(PDO::FETCH_KEY_PAIR);
        $out = [];
        foreach (sbd_people($db) as $id => $p) if (isset($roles[$id])) $out[$id] = $p + ['role' => $roles[$id] === 'superadmin' ? 'Super admin' : 'Tutor'];
        return $out;
    }

    // ------------------------------------------------------------------ the list

    function sg_list_row(PDO $db, int $listId): ?array
    {
        $st = $db->prepare('SELECT * FROM staff_calendar_gift_lists WHERE id = ?'); $st->execute([$listId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    function sg_is_open(array $list, DateTime $now): bool
    {
        $last = (new DateTime((string) $list['birthday_date'], scal_tz()))->modify('+' . SG_OPEN_DAYS . ' days')->format('Y-m-d');
        return $now->format('Y-m-d') >= $list['birthday_date'] && $now->format('Y-m-d') <= $last;
    }

    /**
     * Make this year's list for a birthday person the first time anyone opens the calendar on the birthday. The unique key (person, year) and the one transaction mean it is made
     * once, with every item in it, even if two people open the calendar at the same moment. Returns the list id.
     */
    function sg_ensure_list(PDO $db, int $userId, string $date): int
    {
        $year = (int) substr($date, 0, 4);
        $find = function () use ($db, $userId, $year): ?int {
            $st = $db->prepare('SELECT id FROM staff_calendar_gift_lists WHERE user_id = ? AND gift_year = ?'); $st->execute([$userId, $year]);
            $v = $st->fetchColumn();
            return $v === false ? null : (int) $v;
        };
        if ($id = $find()) return $id;
        $people = sg_people($db); $excl = sg_exclusions($db); $amount = sg_amount($db);
        $now = scal_now()->format('Y-m-d H:i:s');
        try {
            $db->beginTransaction();
            $db->prepare('INSERT INTO staff_calendar_gift_lists (user_id, gift_year, birthday_date, amount, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$userId, $year, $date, $amount, $now]);
            $listId = (int) $db->lastInsertId();
            $ins = $db->prepare('INSERT INTO staff_calendar_gift_items (list_id, giver_user_id, status, excluded_note, updated_at) VALUES (?, ?, ?, ?, ?)');
            foreach ($people as $pid => $p) {
                if ($pid === $userId) continue;   // the birthday person does not send a gift to themselves
                $isEx = array_key_exists($pid, $excl);
                $ins->execute([$listId, $pid, $isEx ? 'excluded' : 'not_yet', $isEx && $excl[$pid] !== '' ? $excl[$pid] : null, $now]);
            }
            $db->commit();
            return $listId;
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($id = $find()) return $id;   // someone else made it a moment ago
            throw $e;
        }
    }

    /** What a person sees on a list: items, counts, amounts. Returns null unless the viewer is the birthday person or the super admin. */
    function sg_list_view(PDO $db, array $actor, int $listId, DateTime $now): ?array
    {
        $list = sg_list_row($db, $listId);
        if (!$list) return null;
        $isOwner = (int) $list['user_id'] === $actor['uid'];
        if (!$isOwner && $actor['role'] !== 'superadmin') return null;   // no other tutor can ever see the list
        $ppl = sg_people($db);
        $names = sbd_people($db);
        $st = $db->prepare('SELECT giver_user_id, status, excluded_note, confirmed_by FROM staff_calendar_gift_items WHERE list_id = ?'); $st->execute([$listId]);
        $rank = ['received' => 0, 'says_sent' => 1, 'not_yet' => 2, 'excluded' => 3];
        $items = []; $received = 0; $expected = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $uid = (int) $r['giver_user_id']; $p = $ppl[$uid] ?? ($names[$uid] ?? ['name' => 'Former colleague', 'first' => 'Former colleague', 'role' => 'Tutor']);
            $role = $p['role'] ?? 'Tutor';
            if ($r['status'] !== 'excluded') { $expected++; if ($r['status'] === 'received') $received++; }
            $items[] = ['giver_id' => $uid, 'name' => $p['name'], 'role' => $role . ($r['status'] === 'excluded' && (string) $r['excluded_note'] !== '' ? ' · ' . $r['excluded_note'] : ''), 'status' => (string) $r['status'],
                        'initials' => sbd_initials_of($p['name']), 'photo' => sbd_photo($db, $uid), 'confirmed_by' => $r['confirmed_by'] ? ($names[(int) $r['confirmed_by']]['name'] ?? '') : ''];
        }
        usort($items, fn($a, $b) => [$rank[$a['status']] ?? 9, $a['name']] <=> [$rank[$b['status']] ?? 9, $b['name']]);
        $owner = $names[(int) $list['user_id']] ?? ['name' => 'Former colleague', 'first' => 'Former colleague'];
        $open = sg_is_open($list, $now);
        $today = $now->format('Y-m-d');
        return [
            'id' => (int) $list['id'], 'open' => $open, 'amount' => (int) $list['amount'], 'date' => (string) $list['birthday_date'], 'year' => (int) $list['gift_year'],
            'person' => $owner['name'], 'first' => $owner['first'], 'person_id' => (int) $list['user_id'], 'own' => $isOwner,
            'received' => $received, 'expected' => $expected, 'received_kes' => $received * (int) $list['amount'], 'expected_kes' => $expected * (int) $list['amount'],
            'items' => $items, 'reminded_today' => (string) $list['last_reminder_on'] === $today,
            'can_confirm' => $open, 'can_remind' => $open,
        ];
    }

    // ------------------------------------------------------------------ taps (each one checks who is asking)

    /** The open list a tap is about, or an error. */
    function sg_open_list(PDO $db, int $listId, DateTime $now): array
    {
        $list = sg_list_row($db, $listId);
        if (!$list) return [null, 'That gift list was not found.'];
        if (!sg_is_open($list, $now)) return [null, 'This list is closed. It is read-only history now.'];
        return [$list, null];
    }

    /** "I have sent": only for yourself. */
    function sg_send(PDO $db, array $actor, int $listId, ?DateTime $now = null): array
    {
        $now ??= scal_now();
        [$list, $err] = sg_open_list($db, $listId, $now); if ($err) return ['ok' => false, 'error' => $err];
        if ((int) $list['user_id'] === $actor['uid']) return ['ok' => false, 'error' => 'You do not send a gift to yourself.'];
        $st = $db->prepare("UPDATE staff_calendar_gift_items SET status = 'says_sent', sent_at = ?, updated_at = ? WHERE list_id = ? AND giver_user_id = ? AND status = 'not_yet'");
        $t = $now->format('Y-m-d H:i:s'); $st->execute([$t, $t, $listId, $actor['uid']]);
        if ($st->rowCount() === 1) return ['ok' => true];
        $cur = $db->prepare('SELECT status FROM staff_calendar_gift_items WHERE list_id = ? AND giver_user_id = ?'); $cur->execute([$listId, $actor['uid']]);
        $s = $cur->fetchColumn();
        if ($s === false) return ['ok' => false, 'error' => 'You are not on this gift list.'];
        if ($s === 'excluded') return ['ok' => false, 'error' => 'You are excluded from this gift.'];
        return ['ok' => true, 'unchanged' => true];   // already says sent or received: nothing to do
    }

    /** Undo "I have sent", only for yourself and only until the birthday person has confirmed. */
    function sg_undo(PDO $db, array $actor, int $listId, ?DateTime $now = null): array
    {
        $now ??= scal_now();
        [$list, $err] = sg_open_list($db, $listId, $now); if ($err) return ['ok' => false, 'error' => $err];
        $st = $db->prepare("UPDATE staff_calendar_gift_items SET status = 'not_yet', sent_at = NULL, updated_at = ? WHERE list_id = ? AND giver_user_id = ? AND status = 'says_sent'");
        $st->execute([$now->format('Y-m-d H:i:s'), $listId, $actor['uid']]);
        if ($st->rowCount() === 1) return ['ok' => true];
        $cur = $db->prepare('SELECT status FROM staff_calendar_gift_items WHERE list_id = ? AND giver_user_id = ?'); $cur->execute([$listId, $actor['uid']]);
        $s = $cur->fetchColumn();
        if ($s === 'received') return ['ok' => false, 'error' => 'It has been confirmed as received, so it cannot be undone.'];
        return $s === false ? ['ok' => false, 'error' => 'You are not on this gift list.'] : ['ok' => true, 'unchanged' => true];
    }

    /** "Confirm received": the birthday person, or the super admin on their behalf (recorded with the name). Only a "Says sent" row. */
    function sg_confirm(PDO $db, array $actor, int $listId, int $giverId, ?DateTime $now = null): array
    {
        $now ??= scal_now();
        [$list, $err] = sg_open_list($db, $listId, $now); if ($err) return ['ok' => false, 'error' => $err];
        if ((int) $list['user_id'] !== $actor['uid'] && $actor['role'] !== 'superadmin') return ['ok' => false, 'error' => 'Only the birthday person or the super admin can confirm a gift.'];
        $t = $now->format('Y-m-d H:i:s');
        $st = $db->prepare("UPDATE staff_calendar_gift_items SET status = 'received', received_at = ?, confirmed_by = ?, updated_at = ? WHERE list_id = ? AND giver_user_id = ? AND status = 'says_sent'");
        $st->execute([$t, $actor['uid'], $t, $listId, $giverId]);
        if ($st->rowCount() === 1) return ['ok' => true];
        $cur = $db->prepare('SELECT status FROM staff_calendar_gift_items WHERE list_id = ? AND giver_user_id = ?'); $cur->execute([$listId, $giverId]);
        $s = $cur->fetchColumn();
        if ($s === false) return ['ok' => false, 'error' => 'That person is not on this list.'];
        if ($s === 'received') return ['ok' => true, 'unchanged' => true];
        return ['ok' => false, 'error' => 'Only a "Says sent" row can be confirmed.'];
    }

    /** "Remind those who have not sent": once a day per birthday. Gentle in-app notes to everyone whose status is "Not yet". */
    function sg_remind(PDO $db, array $actor, int $listId, ?DateTime $now = null): array
    {
        $now ??= scal_now();
        [$list, $err] = sg_open_list($db, $listId, $now); if ($err) return ['ok' => false, 'error' => $err];
        if ((int) $list['user_id'] !== $actor['uid'] && $actor['role'] !== 'superadmin') return ['ok' => false, 'error' => 'Only the birthday person or the super admin can send a reminder.'];
        $today = $now->format('Y-m-d');
        $st = $db->prepare('UPDATE staff_calendar_gift_lists SET last_reminder_on = ? WHERE id = ? AND (last_reminder_on IS NULL OR last_reminder_on <> ?)');   // the one tap of the day wins, even if two people tap at once
        $st->execute([$today, $listId, $today]);
        if ($st->rowCount() !== 1) return ['ok' => false, 'already' => true, 'error' => 'Already sent today'];
        $first = sbd_people($db)[(int) $list['user_id']]['first'] ?? 'Your colleague';
        $who = $db->prepare("SELECT giver_user_id FROM staff_calendar_gift_items WHERE list_id = ? AND status = 'not_yet'"); $who->execute([$listId]);
        $ins = $db->prepare('INSERT INTO staff_calendar_gift_notes (list_id, to_user_id, message, created_at) VALUES (?, ?, ?, ?)');
        $n = 0;
        foreach ($who->fetchAll(PDO::FETCH_COLUMN) as $uid) { $ins->execute([$listId, (int) $uid, mb_substr($first . '’s birthday gift is still waiting', 0, 250), $now->format('Y-m-d H:i:s')]); $n++; }
        return ['ok' => true, 'sent' => $n];
    }

    function sg_note_dismiss(PDO $db, array $actor, int $noteId): array
    {
        $db->prepare('UPDATE staff_calendar_gift_notes SET dismissed_at = ? WHERE id = ? AND to_user_id = ?')->execute([scal_now()->format('Y-m-d H:i:s'), $noteId, $actor['uid']]);
        return ['ok' => true];
    }

    // ------------------------------------------------------------------ what the page shows one person

    /**
     * For the viewer: the lists they may see in full (their own birthday, or all of them for the super admin), the cards for gifts they still owe, and unread reminder notes.
     * A giver only ever gets their OWN status here, never the list.
     */
    function sg_for_viewer(PDO $db, array $actor, DateTime $now): array
    {
        $cut = (clone $now)->modify('-' . SG_OPEN_DAYS . ' days')->format('Y-m-d');
        $st = $db->prepare('SELECT id, user_id FROM staff_calendar_gift_lists WHERE birthday_date >= ? AND birthday_date <= ? ORDER BY birthday_date DESC, id DESC');
        $st->execute([$cut, $now->format('Y-m-d')]);
        $panels = []; $cards = []; $names = sbd_people($db);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $lid = (int) $l['id']; $owner = (int) $l['user_id'];
            if ($owner === $actor['uid'] || $actor['role'] === 'superadmin') { if (count($panels) < 4 && ($v = sg_list_view($db, $actor, $lid, $now))) $panels[] = $v; }
            if ($owner !== $actor['uid']) {
                $mine = $db->prepare('SELECT status FROM staff_calendar_gift_items WHERE list_id = ? AND giver_user_id = ?'); $mine->execute([$lid, $actor['uid']]);
                $s = $mine->fetchColumn();
                if ($s === 'not_yet' || $s === 'says_sent') {
                    $list = sg_list_row($db, $lid);
                    $cards[] = ['list_id' => $lid, 'first' => $names[$owner]['first'] ?? 'Your colleague', 'amount' => (int) $list['amount'], 'status' => $s];
                }
            }
        }
        $nt = $db->prepare("SELECT n.id, n.list_id, n.message FROM staff_calendar_gift_notes n JOIN staff_calendar_gift_items i ON i.list_id = n.list_id AND i.giver_user_id = n.to_user_id
                            WHERE n.to_user_id = ? AND n.dismissed_at IS NULL AND i.status = 'not_yet' ORDER BY n.id DESC LIMIT 5");
        $nt->execute([$actor['uid']]);
        $notes = []; $seenList = [];
        foreach ($nt->fetchAll(PDO::FETCH_ASSOC) as $n) { if (isset($seenList[$n['list_id']])) continue; $seenList[$n['list_id']] = true; $notes[] = ['id' => $n['id'], 'message' => $n['message']]; }   // the latest reminder of each birthday only
        return ['panels' => $panels, 'cards' => $cards, 'notes' => $notes];
    }

    /** Super admin's history: every list, newest first, with totals and the names of people who had not sent. Names only. */
    function sg_history(PDO $db, DateTime $now): array
    {
        $names = sbd_people($db); $out = [];
        foreach ($db->query('SELECT * FROM staff_calendar_gift_lists ORDER BY birthday_date DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $st = $db->prepare('SELECT giver_user_id, status FROM staff_calendar_gift_items WHERE list_id = ?'); $st->execute([(int) $l['id']]);
            $recv = 0; $exp = 0; $says = 0; $notYet = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $i) {
                if ($i['status'] === 'excluded') continue;
                $exp++;
                if ($i['status'] === 'received') $recv++;
                elseif ($i['status'] === 'says_sent') $says++;
                else $notYet[] = $names[(int) $i['giver_user_id']]['name'] ?? 'Former colleague';
            }
            sort($notYet);
            $out[] = ['id' => (int) $l['id'], 'person' => $names[(int) $l['user_id']]['name'] ?? 'Former colleague', 'date' => (string) $l['birthday_date'], 'amount' => (int) $l['amount'],
                      'received' => $recv, 'expected' => $exp, 'says_sent' => $says, 'received_kes' => $recv * (int) $l['amount'], 'expected_kes' => $exp * (int) $l['amount'],
                      'not_yet' => $notYet, 'open' => sg_is_open($l, $now)];
        }
        return $out;
    }
}
