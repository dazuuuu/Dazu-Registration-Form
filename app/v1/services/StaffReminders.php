<?php
// app/v1/services/StaffReminders.php — sends the Staff Calendar reminders (push, email and the in-app "Reminders" list). Run by app/v1/tools/staffcalendar_reminders.php every minute.
//
// HOW IT REUSES WHAT EXISTS (it only CALLS or READS existing code, nothing existing is edited):
//  * Push: WebPush::send() and WebPush::vapidKeys() send to the browsers saved in push_subscriptions (read only). PortalNotifications::notify() is NOT used: it only sends to people who switched on
//    "Everything else" on the portal's Notifications page, and it writes to the portal's own bell table. The push payload has no "id", because the service worker then opens payload.url instead of
//    notifications/index.php?open=<id>.
//  * Email: Mailer::send() with the config/mail.php setup. The HTML uses cid:ismanlogo, so the Mailer embeds the Dazu logo.
//  * In the app: the new table staff_calendar_reminders (the small "Reminders" bell). The portal bell is not touched.
//
// THE RULES:
//  * Every send is CLAIMED first in staff_calendar_reminder_log (UNIQUE on person, source, item, occurrence, kind, channel). Only the run that gets the row sends, so nothing goes out twice, even if the
//    script runs twice or overlaps. A failed send is logged and never stops the other people.
//  * A reminder goes out only when its time has passed and it is still useful: up to 4 hours late for the 8:00 am ones, until the start for "1 hour before". Later than that it is skipped quietly.
//  * It is sent only when the team default for it is on AND the person's own switch is on. Email also needs "Also send an email" on for the person, the team and (for event reminders) the event.
//  * A private ("Only me") event, and an event with "Notify everyone" off, reminds only its owner, so its details never reach anybody else.
//  * All times are Nairobi time (EAT).
require_once __DIR__ . '/StaffSettings.php';
require_once __DIR__ . '/StaffBirthdays.php';
require_once __DIR__ . '/StaffGifts.php';
require_once __DIR__ . '/StaffProjects.php';
require_once __DIR__ . '/StaffReminderEmail.php';
require_once __DIR__ . '/PortalNotifications.php';
require_once __DIR__ . '/Mailer.php';

if (!defined('SRM_LATE')) define('SRM_LATE', 4 * 3600);

if (!function_exists('srm_installed')) {

    function srm_installed(PDO $db): bool
    {
        try { $db->query('SELECT 1 FROM staff_calendar_reminder_log WHERE 1 = 0'); $db->query('SELECT 1 FROM staff_calendar_reminders WHERE 1 = 0'); return scal_installed($db) && sst_installed($db); }
        catch (Throwable $e) { return false; }
    }

    /** Everyone who can get a reminder: active tutors and the super admin, with their email and the folder of their portal. */
    function srm_people(PDO $db): array
    {
        $st = $db->query("SELECT u.id, u.username, u.email, r.role_name, up.first_name, up.last_name FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN user_profiles up ON up.user_id = u.id
                          WHERE u.is_active = 1 AND (r.role_name = 'superadmin' OR (r.role_name = 'tutor' AND EXISTS (SELECT 1 FROM tutors t WHERE t.user_id = u.id AND t.is_active = 1)))");
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $name = scal_display_name($r['first_name'], $r['last_name'], $r['username']);
            $first = trim((string) $r['first_name']) !== '' ? trim((string) $r['first_name']) : (strtok($name, ' ') ?: $name);
            $out[(int) $r['id']] = ['id' => (int) $r['id'], 'name' => $name, 'first' => $first, 'email' => trim((string) $r['email']), 'dir' => $r['role_name'] === 'superadmin' ? 'superadmin' : 'tutor'];
        }
        return $out;
    }

    /** Every person's own switches: user id => [pref => on/off]. A switch with no saved row is on. */
    function srm_prefs_bulk(PDO $db, array $ids): array
    {
        $off = [];
        foreach ($db->query('SELECT user_id, pref_key FROM staff_calendar_user_prefs WHERE enabled = 0')->fetchAll(PDO::FETCH_ASSOC) as $r) $off[(int) $r['user_id']][$r['pref_key']] = true;
        $out = [];
        foreach ($ids as $id) { $out[$id] = []; foreach (SST_PREFS as $k => $_) $out[$id][$k] = empty($off[$id][$k]); }
        return $out;
    }

    function srm_ts(string $s): DateTime { return new DateTime($s, scal_tz()); }

    function srm_job(array $a): array
    {
        return $a + ['pref' => null, 'email_ok' => true, 'to' => [], 'title' => 'Staff Calendar', 'body' => '', 'page' => 'index.php', 'after' => null, 'occ' => '', 'tag' => ''];
    }

    // ------------------------------------------------------------------ what is due (reading only)

    /** Event reminders: the day before at 8:00 am, on the day at 8:00 am, and 1 hour before (not for an all-day event). Every occurrence of a repeating event. */
    function srm_event_jobs(PDO $db, DateTime $now, array $people): array
    {
        $from = (clone $now)->modify('-1 day')->setTime(0, 0); $upto = (clone $now)->modify('+3 days');
        $on = scal_types_switchable($db) ? ' AND COALESCE(t.is_active, 1) = 1' : '';
        $st = $db->prepare("SELECT e.* FROM staff_calendar_events e LEFT JOIN staff_calendar_event_types t ON t.type_key = e.type_key
                            WHERE e.start_at < ? AND (e.repeat_rule <> 'none' OR e.end_at >= ?)" . $on);
        $st->execute([$upto->format('Y-m-d H:i:s'), $from->format('Y-m-d H:i:s')]);
        $jobs = []; $nowTs = $now->getTimestamp();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $ev) {
            $owner = (int) $ev['owner_user_id'];
            $private = (int) $ev['is_private'] === 1;
            $who = ($private || (int) $ev['notify_all'] !== 1) ? (isset($people[$owner]) ? [$owner] : []) : array_keys($people);   // a private event, or one with "Notify everyone" off, is only for its owner
            if (!$who) continue;
            $allDay = (int) $ev['all_day'] === 1;
            foreach (scal_occurrences($ev, $from, (clone $now)->modify('+3 days')) as [$s, $e]) {
                $startTs = $s->getTimestamp(); $day = $s->format('Y-m-d');
                $time = $s->format('g:i a'); $loc = trim((string) $ev['location']);
                $kinds = [
                    ['day_before', (int) $ev['remind_day_before'], (new DateTime($day, scal_tz()))->modify('-1 day')->setTime(8, 0), 'remind_day_before', 'tomorrow'],
                    ['on_day', (int) $ev['remind_on_day'], (new DateTime($day, scal_tz()))->setTime(8, 0), 'remind_on_day', 'today'],
                ];
                if (!$allDay) $kinds[] = ['hour_before', (int) $ev['remind_hour_before'], (clone $s)->modify('-1 hour'), 'remind_hour_before', 'in 1 hour'];
                $ready = [];
                foreach ($kinds as $k) {
                    [$kind, $flag, $due] = $k;
                    if (!$flag) continue;
                    $dueTs = $due->getTimestamp();
                    if ($nowTs < $dueTs) continue;                                                     // not yet time
                    if ($kind === 'hour_before') { if ($nowTs >= $startTs) continue; }                // useful until the start
                    else { if ($nowTs > $dueTs + SRM_LATE) continue; if (!$allDay && $nowTs >= $startTs) continue; }   // 4 hours late at most, and never after a timed event has started
                    $ready[] = $k;
                }
                if ($ready) $ready = [end($ready)];   // no burst: when the script was down and several are due, only the newest one goes (the older ones are not sent later either)
                foreach ($ready as [$kind, $flag, $due, $pref, $word]) {
                    $when = $allDay ? $word . ', all day' : ($kind === 'hour_before' ? 'starts ' . $word . ', ' . $time : $word . ' ' . $time);
                    $jobs[] = srm_job(['source' => 'event', 'item' => (int) $ev['id'], 'occ' => $s->format('Y-m-d H:i'), 'kind' => $kind, 'pref' => $pref, 'email_ok' => (int) $ev['remind_email'] === 1,
                        'to' => $who, 'title' => 'Staff Calendar reminder', 'body' => $ev['title'] . ' · ' . $when . ($loc !== '' ? ' · ' . $loc : ''), 'page' => 'index.php?view=day&d=' . $day]);
                }
            }
        }
        return $jobs;
    }

    /** The birthday person's own message: the one already picked (made now when nobody has opened the calendar yet; a dry run does not make one). */
    function srm_own_message(PDO $db, int $uid, int $year, string $first, bool $dry): string
    {
        $st = $db->prepare('SELECT message_id FROM staff_calendar_birthday_log WHERE user_id = ? AND birth_year = ?'); $st->execute([$uid, $year]);
        $mid = $st->fetchColumn();
        if ($mid !== false) { $m = sbd_msg($db, (int) $mid); return $m ? sbd_text($m['body'], $first) : ''; }
        if ($dry) return '(your birthday message, picked when it is sent)';
        $m = sbd_message_for($db, $uid, $year);
        return $m ? sbd_text($m['body'], $first) : '';
    }

    /** Birthdays at 8:00 am (up to 4 hours late): everyone but the birthday person is told; people who still owe the gift are reminded; the birthday person gets their own message. */
    function srm_birthday_jobs(PDO $db, DateTime $now, array $people, bool $dry): array
    {
        if (!sbd_installed($db) || !scal_type_is_on($db, 'birthday')) return [];
        $today = new DateTime($now->format('Y-m-d'), scal_tz()); $due = (clone $today)->setTime(8, 0);
        if ($now->getTimestamp() < $due->getTimestamp() || $now->getTimestamp() > $due->getTimestamp() + SRM_LATE) return [];
        $jobs = []; $date = $today->format('Y-m-d'); $year = (int) $today->format('Y');
        foreach (sbd_range($db, $today, (clone $today)->modify('+1 day')) as $b) {
            $uid = (int) $b['user_id']; if (!isset($people[$uid])) continue;
            $first = $people[$uid]['first'];
            $jobs[] = srm_job(['source' => 'birthday', 'item' => $uid, 'occ' => $date, 'kind' => 'birthday', 'pref' => 'notice_birthday', 'to' => array_values(array_diff(array_keys($people), [$uid])),
                'title' => 'Birthday today', 'body' => 'Today is ' . $first . '’s birthday', 'page' => 'index.php?view=month&d=' . $date]);
            if (sg_installed($db)) {
                $l = $db->prepare('SELECT id, amount FROM staff_calendar_gift_lists WHERE user_id = ? AND gift_year = ?'); $l->execute([$uid, $year]);
                if ($list = $l->fetch(PDO::FETCH_ASSOC)) {
                    $g = $db->prepare("SELECT giver_user_id FROM staff_calendar_gift_items WHERE list_id = ? AND status = 'not_yet'"); $g->execute([(int) $list['id']]);
                    $givers = array_values(array_filter(array_map('intval', $g->fetchAll(PDO::FETCH_COLUMN)), fn($x) => isset($people[$x])));
                    if ($givers) $jobs[] = srm_job(['source' => 'birthday', 'item' => $uid, 'occ' => $date, 'kind' => 'birthday_gift', 'pref' => 'notice_gift', 'to' => $givers,
                        'title' => 'Birthday gift', 'body' => 'Your ' . sg_kes($list['amount']) . ' gift is waiting. Send it by M-Pesa, then tap ‘I have sent’', 'page' => 'index.php?view=month&d=' . $date]);
                }
            }
            $jobs[] = srm_job(['source' => 'birthday', 'item' => $uid, 'occ' => $date, 'kind' => 'birthday_own', 'pref' => 'notice_birthday', 'to' => [$uid], 'title' => 'Happy Birthday, ' . $first . '!',
                'tag' => 'own', 'body' => srm_own_message($db, $uid, $year, $first, $dry), 'page' => 'index.php?view=month&d=' . $date]);
        }
        return array_values(array_filter($jobs, fn($j) => $j['body'] !== ''));
    }

    /** The gentle gift notes made by "Remind those who have not sent": only to people whose status is still Not yet, up to 4 hours after the tap. */
    function srm_gift_jobs(PDO $db, DateTime $now, array $people): array
    {
        if (!sg_installed($db)) return [];
        $since = (clone $now)->modify('-4 hours')->format('Y-m-d H:i:s');
        $st = $db->prepare("SELECT n.id, n.list_id, n.to_user_id, n.message, l.birthday_date FROM staff_calendar_gift_notes n
                            JOIN staff_calendar_gift_items i ON i.list_id = n.list_id AND i.giver_user_id = n.to_user_id JOIN staff_calendar_gift_lists l ON l.id = n.list_id
                            WHERE i.status = 'not_yet' AND n.dismissed_at IS NULL AND n.created_at >= ? AND n.created_at <= ?");
        $st->execute([$since, $now->format('Y-m-d H:i:s')]);
        $jobs = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $n) {
            if (!isset($people[(int) $n['to_user_id']])) continue;
            $jobs[] = srm_job(['source' => 'gift', 'item' => (int) $n['id'], 'kind' => 'gift_nudge', 'pref' => 'notice_gift', 'to' => [(int) $n['to_user_id']], 'title' => 'Birthday gift', 'body' => (string) $n['message'],
                'page' => 'index.php?view=month&d=' . $n['birthday_date']]);
        }
        return $jobs;
    }

    /** The pending rows of the project tasks: "[Name] gave you a task" when given (up to 4 hours late), and the day before it is due at 8:00 am. Never for a task that is Done. */
    function srm_task_jobs(PDO $db, DateTime $now, array $people, bool $dry): array
    {
        if (!spj_installed($db)) return [];
        $st = $db->query("SELECT tn.id, tn.task_id, tn.event_id, tn.to_user_id, tn.kind, tn.send_on, tn.created_at, c.label, c.status, c.is_done, c.assignee_user_id, c.due_date, e.title AS ptitle, e.owner_user_id
                          FROM staff_calendar_task_notices tn JOIN staff_calendar_checklist c ON c.id = tn.task_id JOIN staff_calendar_events e ON e.id = tn.event_id WHERE tn.sent_at IS NULL");
        $jobs = []; $nowTs = $now->getTimestamp(); $drop = $db->prepare('UPDATE staff_calendar_task_notices SET sent_at = ? WHERE id = ?');
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $to = (int) $r['to_user_id'];
            if ($r['status'] === 'done' || (int) $r['is_done'] === 1) continue;                 // a ticked-off task sends nothing more
            if (!isset($people[$to]) || (int) $r['assignee_user_id'] !== $to) continue;
            if ($r['kind'] === 'assigned') { $dueTs = srm_ts((string) $r['created_at'])->getTimestamp(); }
            else { if ((string) $r['send_on'] === '') continue; $dueTs = srm_ts($r['send_on'])->setTime(8, 0)->getTimestamp(); }
            if ($nowTs < $dueTs) continue;                                                      // not yet time
            if ($nowTs > $dueTs + SRM_LATE) { if (!$dry) $drop->execute([$now->format('Y-m-d H:i:s'), (int) $r['id']]); continue; }   // too late: skipped quietly, and not looked at again
            $owner = $people[(int) $r['owner_user_id']]['name'] ?? 'Someone';
            $due = (string) ($r['due_date'] ?? '') !== '' ? (new DateTime($r['due_date'], scal_tz()))->format('D j M') : '';
            $body = $r['kind'] === 'assigned' ? $owner . ' gave you a task: ' . $r['label'] . ($due !== '' ? ' · due ' . $due : '') : 'Task due tomorrow: ' . $r['label'] . ' · ' . $r['ptitle'];
            $jobs[] = srm_job(['source' => 'task', 'item' => (int) $r['id'], 'kind' => $r['kind'] === 'assigned' ? 'task_assigned' : 'task_due', 'to' => [$to], 'title' => 'Project task', 'body' => $body,
                'page' => 'project.php?id=' . (int) $r['event_id'], 'after' => (int) $r['id']]);
        }
        return $jobs;
    }

    /** Everything that is due at $now. */
    function srm_collect(PDO $db, DateTime $now, array $people, bool $dry = false): array
    {
        return array_merge(srm_event_jobs($db, $now, $people), srm_birthday_jobs($db, $now, $people, $dry), srm_gift_jobs($db, $now, $people), srm_task_jobs($db, $now, $people, $dry));
    }

    // ------------------------------------------------------------------ sending

    /** Claim one send: true only for the run that got it. A dry run only checks. */
    function srm_claim(PDO $db, int $uid, array $job, string $channel, bool $dry, string $now): bool
    {
        $args = [$uid, $job['source'], $job['item'], $job['occ'], $job['kind'], $channel];
        if ($dry) {
            $st = $db->prepare('SELECT 1 FROM staff_calendar_reminder_log WHERE user_id = ? AND source = ? AND item_id = ? AND occ_key = ? AND kind = ? AND channel = ?'); $st->execute($args);
            return !$st->fetchColumn();
        }
        try {
            $db->prepare("INSERT INTO staff_calendar_reminder_log (user_id, source, item_id, occ_key, kind, channel, status, detail, created_at) VALUES (?, ?, ?, ?, ?, ?, 'claimed', '', ?)")->execute(array_merge($args, [$now]));
            return true;
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000' || stripos($e->getMessage(), 'duplicate') !== false || stripos($e->getMessage(), 'unique') !== false) return false;
            throw $e;
        }
    }

    function srm_finish(PDO $db, int $uid, array $job, string $channel, string $status, string $detail): void
    {
        $db->prepare('UPDATE staff_calendar_reminder_log SET status = ?, detail = ? WHERE user_id = ? AND source = ? AND item_id = ? AND occ_key = ? AND kind = ? AND channel = ?')
           ->execute([$status, mb_substr($detail, 0, 250), $uid, $job['source'], $job['item'], $job['occ'], $job['kind'], $channel]);
    }

    function srm_vapid_subject(): string
    {
        return 'mailto:' . (filter_var($_SERVER['SERVER_ADMIN'] ?? '', FILTER_VALIDATE_EMAIL) ?: 'notifications@' . preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost.localdomain'));
    }

    /** Send one push to every browser of the person. Returns [ok, detail]. Expired subscriptions are logged, not deleted (the table belongs to the portal). */
    function srm_push(PDO $db, int $uid, string $title, string $body, string $path): array
    {
        $st = $db->prepare('SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?'); $st->execute([$uid]);
        $subs = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$subs) return [null, 'no push subscription'];
        if (!WebPush::supported()) return [false, 'push is not supported on this server'];
        $keys = WebPush::vapidKeys($db);
        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => PortalNotifications::absolute($path), 'icon' => PortalNotifications::absolute('assets/icons/icon-192.png')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);   // no "id": a tap opens the url
        $ok = 0; $codes = [];
        foreach ($subs as $s) {
            try { $code = WebPush::send($s, $payload, $keys, srm_vapid_subject()); } catch (Throwable $e) { $code = -1; error_log('[staffcalendar_reminders] push: ' . $e->getMessage()); }
            if ($code >= 200 && $code < 300) $ok++; else $codes[] = $code === 404 || $code === 410 ? 'subscription gone (' . $code . ')' : 'push error ' . $code;
        }
        return [$ok > 0, $ok > 0 ? $ok . ' device(s)' . ($codes ? ', ' . implode(', ', $codes) : '') : implode(', ', $codes)];
    }

    function srm_mailer(): Mailer { static $m = null; return $m ??= new Mailer(require __DIR__ . '/../config/mail.php'); }

    /** Deliver one job to one person on the channels that apply. Adds to $st (counts and the lines a dry run prints). */
    function srm_deliver(PDO $db, array $job, array $person, array $team, array $mine, array $opt, array &$st, string $nowS): void
    {
        $uid = $person['id']; $dry = !empty($opt['dry']); $noSend = !empty($opt['no_send']);
        if ($job['pref'] !== null && !(($team[$job['pref']] ?? true) && ($mine[$job['pref']] ?? true))) { $st['gated']++; return; }   // the team default AND the person's own switch
        $path = $person['dir'] . '/staffcalendar/' . $job['page'];
        $wantEmail = $job['email_ok'] && ($team['remind_email'] ?? true) && ($mine['remind_email'] ?? true) && filter_var($person['email'], FILTER_VALIDATE_EMAIL);
        $failFor = in_array($uid, (array) ($opt['simulate_fail'] ?? []), true);
        $channels = ['app', 'push'];
        if ($wantEmail) $channels[] = 'email';
        foreach ($channels as $ch) {
            try {
                if ($ch === 'push' && !$noSend && !$dry) {   // nothing to claim when the person has no browser subscribed
                    $has = $db->prepare('SELECT 1 FROM push_subscriptions WHERE user_id = ? LIMIT 1'); $has->execute([$uid]);
                    if (!$has->fetchColumn()) continue;
                }
                if ($ch === 'push' && ($noSend || $dry)) { $has = $db->prepare('SELECT 1 FROM push_subscriptions WHERE user_id = ? LIMIT 1'); $has->execute([$uid]); if (!$has->fetchColumn()) continue; }
                if (!srm_claim($db, $uid, $job, $ch, $dry, $nowS)) { $st['dupe']++; continue; }
                if ($dry) { $st['lines'][] = 'WOULD SEND ' . $ch . ' → ' . $person['name'] . ': ' . $job['body']; $st['would']++; continue; }
                if ($ch === 'app') {
                    $db->prepare('INSERT INTO staff_calendar_reminders (user_id, kind, title, body, url, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, ?)')->execute([$uid, $job['kind'], mb_substr($job['title'], 0, 160), mb_substr($job['body'], 0, 500), $path, $nowS]);
                    srm_finish($db, $uid, $job, 'app', 'sent', ''); $st['sent_app']++; continue;
                }
                if ($failFor) throw new RuntimeException('simulated failure');
                if ($ch === 'push') {
                    if ($noSend) { srm_finish($db, $uid, $job, 'push', 'sent', 'no-send (test)'); $st['sent_push']++; continue; }
                    [$ok, $detail] = srm_push($db, $uid, $job['title'], $job['body'], $path);
                    srm_finish($db, $uid, $job, 'push', $ok ? 'sent' : 'failed', $detail); $ok ? $st['sent_push']++ : $st['failed']++;
                    continue;
                }
                // email
                if ($noSend) { srm_finish($db, $uid, $job, 'email', 'sent', 'no-send (test)'); $st['sent_email']++; continue; }
                [$subject, $html] = StaffReminderEmail::render($job['title'], $job['body'], PortalNotifications::absolute($path), $person['first']);
                $r = srm_mailer()->send($person['email'], $person['name'], $subject, $html);
                $good = !empty($r['ok']) && empty($r['skipped']);
                srm_finish($db, $uid, $job, 'email', $good ? 'sent' : (!empty($r['skipped']) ? 'skipped' : 'failed'), $good ? '' : (string) ($r['message'] ?? 'not sent'));
                $good ? $st['sent_email']++ : (!empty($r['skipped']) ? null : $st['failed']++);
            } catch (Throwable $e) {   // one person's failure is logged and never stops the others
                $st['failed']++;
                error_log('[staffcalendar_reminders] ' . $ch . ' for user ' . $uid . ': ' . $e->getMessage());
                try { srm_finish($db, $uid, $job, $ch, 'failed', $e->getMessage()); } catch (Throwable $e2) { }
            }
        }
    }

    /**
     * One pass of the reminder job at $now. $opt: dry (only report), no_send (do everything but call push and mail: for tests), only_users (limit the people: for tests), simulate_fail (test).
     * @return array{jobs:int, sent_app:int, sent_push:int, sent_email:int, failed:int, dupe:int, gated:int, would:int, lines:array}
     */
    function srm_run(PDO $db, DateTime $now, array $opt = []): array
    {
        $st = ['jobs' => 0, 'sent_app' => 0, 'sent_push' => 0, 'sent_email' => 0, 'failed' => 0, 'dupe' => 0, 'gated' => 0, 'would' => 0, 'lines' => []];
        $people = srm_people($db);
        $dry = !empty($opt['dry']);
        $jobs = srm_collect($db, $now, $people, $dry);
        $team = sst_team_defaults($db);
        $prefs = srm_prefs_bulk($db, array_keys($people));
        $only = isset($opt['only_users']) ? array_map('intval', (array) $opt['only_users']) : null;
        $nowS = $now->format('Y-m-d H:i:s');
        $mark = $db->prepare('UPDATE staff_calendar_task_notices SET sent_at = ? WHERE id = ?');
        foreach ($jobs as $job) {
            $st['jobs']++;
            foreach ($job['to'] as $uid) {
                if ($only !== null && !in_array($uid, $only, true)) continue;
                if (!isset($people[$uid])) continue;
                srm_deliver($db, $job, $people[$uid], $team, $prefs[$uid], $opt, $st, $nowS);
            }
            if ($job['after'] !== null && !$dry && ($only === null || array_intersect($job['to'], $only))) $mark->execute([$nowS, (int) $job['after']]);   // the task notice has been dealt with
        }
        return $st;
    }

    // ------------------------------------------------------------------ the in-app list (the "Reminders" bell)

    function srm_inbox_unread(PDO $db, int $uid): int
    {
        $st = $db->prepare('SELECT COUNT(*) FROM staff_calendar_reminders WHERE user_id = ? AND is_read = 0'); $st->execute([$uid]);
        return (int) $st->fetchColumn();
    }

    function srm_inbox_list(PDO $db, int $uid, int $limit = 30): array
    {
        $st = $db->prepare('SELECT id, kind, title, body, url, is_read, created_at FROM staff_calendar_reminders WHERE user_id = ? ORDER BY id DESC LIMIT ' . (int) $limit); $st->execute([$uid]);
        return array_map(fn($r) => ['id' => (int) $r['id'], 'kind' => (string) $r['kind'], 'title' => (string) $r['title'], 'body' => (string) $r['body'], 'url' => (string) $r['url'], 'read' => (int) $r['is_read'] === 1, 'at' => (string) $r['created_at']], $st->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Mark one reminder (or all, with id 0) as read. Only the person's own. */
    function srm_inbox_read(PDO $db, int $uid, int $id = 0): void
    {
        if ($id > 0) $db->prepare('UPDATE staff_calendar_reminders SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        else $db->prepare('UPDATE staff_calendar_reminders SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
    }
}
