<?php
// Staff Calendar reminders (app/v1/services/StaffReminders.php + app/v1/tools/staffcalendar_reminders.php).
//   DAZU_DB_NAME=<scratch db> DAZU_DB_DRIVER=mysql DAZU_DB_PORT=3307 DAZU_DB_HOST=127.0.0.1 DAZU_DB_USER=root DAZU_DB_PASS= php app/v1/tests/staffcalendar_reminders_test.php
// Uses a scratch database with all migrations applied. It NEVER sends a real push or email: every run uses the no_send / dry options, and the people used are limited with only_users.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/StaffCalendar.php';
require_once __DIR__ . '/../services/StaffReminders.php';

$db = \Models\Connection::getInstance()->getConnection();
echo 'Database driver: ' . $db->getAttribute(PDO::ATTR_DRIVER_NAME) . ' ' . $db->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
$fails = 0; $n = 0;
function check(string $name, $cond, string $extra = ''): void { global $fails, $n; $n++; if ($cond) { echo "ok   $name\n"; return; } $fails++; echo "FAIL $name" . ($extra !== '' ? "  ($extra)" : '') . "\n"; }
function section(string $s): void { echo "\n== $s\n"; }

$run = substr(md5((string) microtime(true)), 0, 6);
$pw = password_hash('x', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$mk = function (string $key, string $role, string $first, string $email, bool $tutorRow = false) use ($db, $pw, $roles, $run): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["rm_$key$run", $email, $pw, $roles[$role]]);
    $id = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, $first, 'Test']);
    if ($tutorRow) $db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$id]);
    return $id;
};
$sa = $mk('sa', 'superadmin', 'Simon', "sa$run@rm.test");
$t1 = $mk('t1', 'tutor', 'Tina', "t1$run@rm.test", true);
$t2 = $mk('t2', 'tutor', 'Tom', '', true);                      // no email address
$stu = $mk('stu', 'student', 'Stu', "stu$run@rm.test");        // not a staff member
$ALL = [$sa, $t1, $t2, $stu];
$staff = [$sa, $t1, $t2];
$db->prepare("INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth) VALUES (?, ?, ?, 'p', 'a')")->execute([$t1, "https://push.invalid/$run", hash('sha256', "https://push.invalid/$run")]);

$tz = scal_tz();
$at = fn(string $s) => new DateTime($s, $tz);
$ev = function (array $o) use ($db, $sa): int {
    $o += ['title' => 'Staff meeting', 'location' => 'Nairobi House (Cianda House), 4th Floor, Room 406B', 'type_key' => 'meeting', 'start' => '2031-03-10 10:00:00', 'end' => '2031-03-10 12:00:00', 'all_day' => 0, 'repeat' => 'none',
           'owner' => $sa, 'notify_all' => 1, 'private' => 0, 'day_before' => 1, 'on_day' => 1, 'hour_before' => 1, 'email' => 1];
    $db->prepare('INSERT INTO staff_calendar_events (title, location, type_key, start_at, end_at, all_day, repeat_rule, owner_user_id, notify_all, is_private, remind_day_before, remind_on_day, remind_hour_before, remind_email) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
       ->execute([$o['title'], $o['location'], $o['type_key'], $o['start'], $o['end'], $o['all_day'], $o['repeat'], $o['owner'], $o['notify_all'], $o['private'], $o['day_before'], $o['on_day'], $o['hour_before'], $o['email']]);
    return (int) $db->lastInsertId();
};
$go = function (string $when, array $opt = []) use ($db, &$ALL) { return srm_run($db, new DateTime($when, scal_tz()), $opt + ['no_send' => true, 'only_users' => $ALL]); };
$cnt = function (array $w) use ($db): int {
    $sql = 'SELECT COUNT(*) FROM staff_calendar_reminder_log WHERE 1=1'; $a = [];
    foreach ($w as $k => $v) { if (is_array($v)) { $sql .= " AND $k IN (" . implode(',', array_map('intval', $v)) . ')'; } else { $sql .= " AND $k = ?"; $a[] = $v; } }
    $s = $db->prepare($sql); $s->execute($a); return (int) $s->fetchColumn();
};
$inbox = function (int $uid) use ($db): array { $s = $db->prepare('SELECT body FROM staff_calendar_reminders WHERE user_id = ? ORDER BY id'); $s->execute([$uid]); return $s->fetchAll(PDO::FETCH_COLUMN); };
$setTeam = function (string $key, ?string $v) use ($db): void {
    $db->prepare('DELETE FROM staff_calendar_settings WHERE setting_key = ?')->execute([$key]);
    if ($v !== null) $db->prepare('INSERT INTO staff_calendar_settings (setting_key, setting_value) VALUES (?, ?)')->execute([$key, $v]);
};
$setMine = function (int $uid, string $pref, int $on) use ($db): void {
    $db->prepare('DELETE FROM staff_calendar_user_prefs WHERE user_id = ? AND pref_key = ?')->execute([$uid, $pref]);
    $db->prepare('INSERT INTO staff_calendar_user_prefs (user_id, pref_key, enabled) VALUES (?, ?, ?)')->execute([$uid, $pref, $on]);
};

section('Installed');
check('the reminder tables exist', srm_installed($db));
check('the log has a unique key on person, source, item, occurrence, kind, channel', (int) $db->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'staff_calendar_reminder_log' AND index_name = 'uq_scrl_once'")->fetchColumn() === 6);
$pp = srm_people($db); check('only staff are people (no student)', count(array_intersect(array_keys($pp), $staff)) === 3 && !isset($pp[$stu]));

section('Event reminders: timing');
$E1 = $ev([]);
$go('2031-03-09 07:59');
check('nothing before 8:00 the day before', $cnt(['item_id' => $E1, 'source' => 'event']) === 0);
$r = $go('2031-03-09 08:00');
check('day before 8:00 am: everyone on the team (in the app)', $cnt(['item_id' => $E1, 'kind' => 'day_before', 'channel' => 'app', 'user_id' => $staff]) === 3);
check('the text is short and plain', in_array('Staff meeting · tomorrow 10:00 am · Nairobi House (Cianda House), 4th Floor, Room 406B', $inbox($t1), true), json_encode($inbox($t1)));
check('push only for the person with a browser', $cnt(['item_id' => $E1, 'kind' => 'day_before', 'channel' => 'push']) === 1 && $cnt(['item_id' => $E1, 'kind' => 'day_before', 'channel' => 'push', 'user_id' => $t1]) === 1);
check('email only for people with an address', $cnt(['item_id' => $E1, 'kind' => 'day_before', 'channel' => 'email', 'user_id' => [$sa, $t1]]) === 2 && $cnt(['item_id' => $E1, 'kind' => 'day_before', 'channel' => 'email', 'user_id' => $t2]) === 0);
check('the student gets nothing', $cnt(['user_id' => $stu]) === 0);
$before = $cnt(['item_id' => $E1]);
$r = $go('2031-03-09 08:01'); $r2 = $go('2031-03-09 08:00');
check('running again sends nothing again', $cnt(['item_id' => $E1]) === $before && $r['sent_app'] === 0 && $r2['sent_app'] === 0 && $r['dupe'] > 0);
$go('2031-03-10 08:00');
check('on the day 8:00 am: "today"', in_array('Staff meeting · today 10:00 am · Nairobi House (Cianda House), 4th Floor, Room 406B', $inbox($t1), true));
$go('2031-03-10 08:30', ['no_send' => true]);
check('no 1-hour reminder at 8:30', $cnt(['item_id' => $E1, 'kind' => 'hour_before']) === 0);
$go('2031-03-10 09:00');
check('1 hour before: sent at 9:00', $cnt(['item_id' => $E1, 'kind' => 'hour_before', 'channel' => 'app']) === 3);
check('1 hour before: text says it starts in 1 hour', in_array('Staff meeting · starts in 1 hour, 10:00 am · Nairobi House (Cianda House), 4th Floor, Room 406B', $inbox($sa), true));
check('exactly three reminders in the app per person for the whole day', count(array_filter($inbox($sa), fn($b) => str_starts_with($b, 'Staff meeting'))) === 3);

section('Event reminders: stale and late');
$E2 = $ev(['start' => '2031-03-12 10:00:00', 'end' => '2031-03-12 11:00:00']);
$go('2031-03-11 12:31');   // 4 h 31 min after the 8:00 day-before time
check('the day-before reminder is skipped quietly when 4 hours late', $cnt(['item_id' => $E2, 'kind' => 'day_before']) === 0);
$go('2031-03-11 12:00');   // exactly 4 hours: still sent
check('exactly 4 hours late is still sent', $cnt(['item_id' => $E2, 'kind' => 'day_before', 'channel' => 'app']) === 3);
$go('2031-03-12 10:00');
check('nothing once the event has started', $cnt(['item_id' => $E2, 'kind' => 'on_day']) === 0 && $cnt(['item_id' => $E2, 'kind' => 'hour_before']) === 0);
$go('2031-03-12 09:30');
check('1 hour before can still go out late, until the start (no burst of other kinds)', $cnt(['item_id' => $E2, 'kind' => 'hour_before', 'channel' => 'app']) === 3 && $cnt(['item_id' => $E2, 'kind' => 'on_day']) === 0);

section('All-day, private, owner-only, switches');
$E3 = $ev(['all_day' => 1, 'start' => '2031-03-20 00:00:00', 'end' => '2031-03-20 23:59:00', 'title' => 'Public holiday cover']);
$go('2031-03-20 08:00'); $go('2031-03-20 09:30'); $go('2031-03-20 12:00');
check('all-day: the on-the-day reminder goes out', $cnt(['item_id' => $E3, 'kind' => 'on_day', 'channel' => 'app']) === 3);
check('all-day: never a 1-hour reminder', $cnt(['item_id' => $E3, 'kind' => 'hour_before']) === 0);
$E4 = $ev(['private' => 1, 'notify_all' => 1, 'owner' => $t1, 'title' => 'Dentist PRIVATE', 'start' => '2031-03-25 10:00:00', 'end' => '2031-03-25 11:00:00']);
$go('2031-03-25 08:00');
check('a private event reminds only its owner', $cnt(['item_id' => $E4, 'channel' => 'app', 'user_id' => $t1]) === 1 && $cnt(['item_id' => $E4, 'user_id' => [$sa, $t2]]) === 0);
check('no private detail reaches anybody else', !in_array(true, array_map(fn($u) => (bool) array_filter($inbox($u), fn($b) => str_contains($b, 'Dentist PRIVATE')), [$sa, $t2]), true));
$E5 = $ev(['notify_all' => 0, 'owner' => $t2, 'title' => 'Own list', 'start' => '2031-03-26 10:00:00', 'end' => '2031-03-26 11:00:00']);
$go('2031-03-26 08:00');
check('"Notify every tutor" off: only the owner', $cnt(['item_id' => $E5, 'channel' => 'app', 'user_id' => $t2]) === 1 && $cnt(['item_id' => $E5, 'user_id' => [$sa, $t1]]) === 0);
$E6 = $ev(['start' => '2031-04-02 10:00:00', 'end' => '2031-04-02 11:00:00', 'email' => 0, 'title' => 'No email event']);
$go('2031-04-02 08:00');
check('event with "Also send an email" off: app and push but no email', $cnt(['item_id' => $E6, 'channel' => 'email']) === 0 && $cnt(['item_id' => $E6, 'channel' => 'app']) === 3);
$E7 = $ev(['start' => '2031-04-03 10:00:00', 'end' => '2031-04-03 11:00:00', 'title' => 'Switches']);
$setMine($t1, 'remind_on_day', 0);
$setMine($sa, 'remind_email', 0);
$setTeam('team_remind_on_day', null);
$go('2031-04-03 08:00');
check('the person\'s own switch off: that person gets nothing', $cnt(['item_id' => $E7, 'user_id' => $t1, 'kind' => 'on_day']) === 0 && $cnt(['item_id' => $E7, 'user_id' => $sa, 'kind' => 'on_day', 'channel' => 'app']) === 1);
check('the person\'s email switch off: no email for them', $cnt(['item_id' => $E7, 'user_id' => $sa, 'channel' => 'email']) === 0);
$E8 = $ev(['start' => '2031-04-04 10:00:00', 'end' => '2031-04-04 11:00:00', 'title' => 'Team off']);
$setTeam('team_remind_on_day', '0');
$go('2031-04-04 08:00');
check('the team default off: nobody gets it', $cnt(['item_id' => $E8, 'kind' => 'on_day']) === 0);
$setTeam('team_remind_on_day', null);
$db->prepare('DELETE FROM staff_calendar_user_prefs WHERE user_id IN (?, ?, ?)')->execute($staff);
$setTeam('team_remind_email', '0');
$E9 = $ev(['start' => '2031-04-05 10:00:00', 'end' => '2031-04-05 11:00:00', 'title' => 'Team email off']);
$go('2031-04-05 08:00');
check('the team email default off: no email for anyone, the rest still goes', $cnt(['item_id' => $E9, 'channel' => 'email']) === 0 && $cnt(['item_id' => $E9, 'channel' => 'app']) === 3);
$setTeam('team_remind_email', null);
$db->prepare('DELETE FROM staff_calendar_user_prefs WHERE user_id IN (?, ?, ?)')->execute($staff);
$E10 = $ev(['start' => '2031-04-06 10:00:00', 'end' => '2031-04-06 11:00:00', 'title' => 'Flag off', 'on_day' => 0]);
$go('2031-04-06 08:00');
check('the event\'s own reminder switch off: that one is not sent', $cnt(['item_id' => $E10, 'kind' => 'on_day']) === 0);

section('Repeats, changed time, deleted events');
$E11 = $ev(['start' => '2031-05-05 14:00:00', 'end' => '2031-05-05 15:00:00', 'repeat' => 'weekly', 'title' => 'Weekly stand-up']);
$go('2031-05-05 08:00'); $go('2031-05-12 08:00');
$s = $db->prepare("SELECT COUNT(DISTINCT occ_key) FROM staff_calendar_reminder_log WHERE source='event' AND item_id = ? AND kind = 'on_day'"); $s->execute([$E11]);
check('every occurrence of a repeating event is reminded once', (int) $s->fetchColumn() === 2);
$E12 = $ev(['start' => '2031-06-10 10:00:00', 'end' => '2031-06-10 11:00:00', 'title' => 'Moves']);
$go('2031-06-10 08:00');
$db->prepare("UPDATE staff_calendar_events SET start_at = '2031-06-10 15:00:00', end_at = '2031-06-10 16:00:00' WHERE id = ?")->execute([$E12]);
$go('2031-06-10 08:05');
check('a changed time gets new reminders', $cnt(['item_id' => $E12, 'kind' => 'on_day', 'channel' => 'app', 'user_id' => $sa]) === 2 && in_array('Moves · today 3:00 pm · Nairobi House (Cianda House), 4th Floor, Room 406B', $inbox($sa), true));
$E13 = $ev(['start' => '2031-06-11 10:00:00', 'end' => '2031-06-11 11:00:00', 'title' => 'Cancelled']);
$db->prepare('DELETE FROM staff_calendar_events WHERE id = ?')->execute([$E13]);
$go('2031-06-11 08:00');
check('a deleted event sends nothing', $cnt(['item_id' => $E13]) === 0);

section('Failures never stop the others');
$E14 = $ev(['start' => '2031-07-01 10:00:00', 'end' => '2031-07-01 11:00:00', 'title' => 'Fails for one']);
$err = ini_set('error_log', sys_get_temp_dir() . '/staffrem_err.log');
$r = $go('2031-07-01 08:00', ['simulate_fail' => [$t1]]);
check('one person\'s push/email failure is logged', $cnt(['item_id' => $E14, 'user_id' => $t1, 'status' => 'failed']) >= 1 && $r['failed'] >= 1);
check('the others still get everything', $cnt(['item_id' => $E14, 'user_id' => $sa, 'channel' => 'email', 'status' => 'sent']) === 1 && $cnt(['item_id' => $E14, 'user_id' => $t2, 'channel' => 'app', 'status' => 'sent']) === 1);
check('the failed person still gets the in-app reminder', $cnt(['item_id' => $E14, 'user_id' => $t1, 'channel' => 'app', 'status' => 'sent']) === 1);
$r = $go('2031-07-01 08:01');
check('a failed send is not retried (no repeat sending)', $r['sent_push'] === 0 && $r['sent_email'] === 0);

section('Dry run');
$E15 = $ev(['start' => '2031-07-10 10:00:00', 'end' => '2031-07-10 11:00:00', 'title' => 'Dry']);
$r = $go('2031-07-10 08:00', ['dry' => true]);
check('a dry run writes nothing', $cnt(['item_id' => $E15]) === 0 && !in_array('Dry · today 10:00 am · Nairobi House (Cianda House), 4th Floor, Room 406B', $inbox($t1), true));
check('a dry run says what it WOULD send', $r['would'] >= 3 && (bool) array_filter($r['lines'], fn($l) => str_contains($l, 'Dry · today 10:00 am')));
$go('2031-07-10 08:00');
$r = $go('2031-07-10 08:00', ['dry' => true]);
check('a dry run after the real send finds nothing left to send', $r['would'] === 0);

section('Birthdays and gifts');
$bd = $mk('bd', 'tutor', 'Daniel', "bd$run@rm.test", true);
$ALL[] = $bd; $staff[] = $bd;
$db->prepare('INSERT INTO staff_calendar_birthdays (user_id, birth_day, birth_month, birth_year, show_age) VALUES (?, 20, 8, NULL, 0)')->execute([$bd]);
$go('2031-08-20 07:59');
check('no birthday message before 8:00', $cnt(['source' => 'birthday', 'item_id' => $bd]) === 0);
$list = sg_ensure_list($db, $bd, '2031-08-20');
$db->prepare("UPDATE staff_calendar_gift_items SET status = 'sent' WHERE list_id = ? AND giver_user_id = ?")->execute([$list, $t2]);
$go('2031-08-20 08:00');
check('others are told: "Today is Daniel’s birthday"', in_array('Today is Daniel’s birthday', $inbox($t1), true) && in_array('Today is Daniel’s birthday', $inbox($sa), true));
check('the birthday person is not told it is their own birthday that way', !in_array('Today is Daniel’s birthday', $inbox($bd), true));
$own = $inbox($bd);
check('the birthday person gets their own message', count($own) === 1 && $own[0] !== '', json_encode($own));
check('the gift reminder goes to those who have not sent', in_array('Your KES 1,500 gift is waiting. Send it by M-Pesa, then tap ‘I have sent’', $inbox($t1), true), json_encode($inbox($t1)));
check('not to the person who already sent', !in_array('Your KES 1,500 gift is waiting. Send it by M-Pesa, then tap ‘I have sent’', $inbox($t2), true));
$go('2031-08-20 08:10');
check('nothing twice', count($inbox($bd)) === 1 && substr_count(implode('|', $inbox($t1)), 'Today is Daniel’s birthday') === 1);
$go('2031-08-20 13:00');
$bd2 = $mk('bd2', 'tutor', 'Late', "bd2$run@rm.test", true); $ALL[] = $bd2;
$db->prepare('INSERT INTO staff_calendar_birthdays (user_id, birth_day, birth_month, birth_year, show_age) VALUES (?, 21, 8, NULL, 0)')->execute([$bd2]);
$go('2031-08-21 12:30');
check('a birthday later than 4 hours after 8:00 is not announced', $cnt(['source' => 'birthday', 'item_id' => $bd2]) === 0);
$setMine($t1, 'notice_birthday', 0);
$bd3 = $mk('bd3', 'tutor', 'Quiet', "bd3$run@rm.test", true); $ALL[] = $bd3;
$db->prepare('INSERT INTO staff_calendar_birthdays (user_id, birth_day, birth_month, birth_year, show_age) VALUES (?, 22, 8, NULL, 0)')->execute([$bd3]);
$go('2031-08-22 08:00');
check('birthday notices switched off by a person: not sent to them', $cnt(['source' => 'birthday', 'item_id' => $bd3, 'user_id' => $t1, 'kind' => 'birthday']) === 0 && $cnt(['source' => 'birthday', 'item_id' => $bd3, 'user_id' => $sa, 'kind' => 'birthday', 'channel' => 'app']) === 1);
$db->prepare('DELETE FROM staff_calendar_user_prefs WHERE user_id = ?')->execute([$t1]);

section('Gift reminder notes');
$db->prepare('INSERT INTO staff_calendar_gift_notes (list_id, to_user_id, message, created_at) VALUES (?, ?, ?, ?)')->execute([$list, $t1, 'Gentle reminder: Daniel’s gift', '2031-08-20 12:00:00']);
$nid = (int) $db->lastInsertId();
$db->prepare('INSERT INTO staff_calendar_gift_notes (list_id, to_user_id, message, created_at) VALUES (?, ?, ?, ?)')->execute([$list, $t2, 'Should not go (already sent)', '2031-08-20 12:00:00']);
$nid2 = (int) $db->lastInsertId();
$go('2031-08-20 12:05');
check('a reminder note goes to someone whose status is still Not yet', $cnt(['source' => 'gift', 'item_id' => $nid, 'user_id' => $t1, 'channel' => 'app']) === 1 && in_array('Gentle reminder: Daniel’s gift', $inbox($t1), true));
check('and never to someone who has sent', $cnt(['source' => 'gift', 'item_id' => $nid2]) === 0);

section('Project tasks');
$P = $ev(['type_key' => 'project', 'title' => 'Website', 'start' => '2031-09-01 00:00:00', 'end' => '2031-09-30 23:59:00', 'all_day' => 1, 'owner' => $sa, 'notify_all' => 0]);
$db->prepare("INSERT INTO staff_calendar_checklist (event_id, label, assignee_user_id, due_date, status, sort_order) VALUES (?, 'Write the report', ?, '2031-09-12', 'todo', 1)")->execute([$P, $t1]);
$T1 = (int) $db->lastInsertId();
$db->prepare("INSERT INTO staff_calendar_checklist (event_id, label, assignee_user_id, due_date, status, sort_order) VALUES (?, 'Done already', ?, '2031-09-12', 'done', 2)")->execute([$P, $t1]);
$T2 = (int) $db->lastInsertId();
$db->prepare('INSERT INTO staff_calendar_task_notices (task_id, event_id, to_user_id, kind, send_on, created_at) VALUES (?, ?, ?, \'assigned\', NULL, ?)')->execute([$T1, $P, $t1, '2031-09-02 10:00:00']);
$N1 = (int) $db->lastInsertId();
$db->prepare('INSERT INTO staff_calendar_task_notices (task_id, event_id, to_user_id, kind, send_on, created_at) VALUES (?, ?, ?, \'assigned\', NULL, ?)')->execute([$T2, $P, $t1, '2031-09-02 10:00:00']);
$N2 = (int) $db->lastInsertId();
spj_notice_due($db, $T1, $P, $t1, '2031-09-12', false);
$N3 = (int) $db->query("SELECT id FROM staff_calendar_task_notices WHERE task_id = $T1 AND kind = 'due_before'")->fetchColumn();
$go('2031-09-02 09:59');
check('the "gave you a task" reminder waits for its time', $cnt(['source' => 'task', 'item_id' => $N1]) === 0);
$go('2031-09-02 10:30');
check('"[Name] gave you a task: [task] · due [date]"', in_array('Simon Test gave you a task: Write the report · due Fri 12 Sep', $inbox($t1), true), json_encode($inbox($t1)));
check('a task already Done sends nothing', $cnt(['source' => 'task', 'item_id' => $N2]) === 0);
$sent = $db->query("SELECT sent_at FROM staff_calendar_task_notices WHERE id = $N1")->fetchColumn();
check('the pending row is marked as sent', $sent !== null && $sent !== false);
$go('2031-09-11 08:00');
check('the day before it is due, 8:00 am', in_array('Task due tomorrow: Write the report · Website', $inbox($t1), true), json_encode($inbox($t1)));
$db->prepare("UPDATE staff_calendar_checklist SET status = 'done', is_done = 1 WHERE id = ?")->execute([$T1]);
$db->prepare('UPDATE staff_calendar_task_notices SET sent_at = NULL WHERE id = ?')->execute([$N3]);
$db->prepare("DELETE FROM staff_calendar_reminder_log WHERE source = 'task' AND item_id = ?")->execute([$N3]);
$go('2031-09-11 08:30');
check('a ticked-off task sends nothing more', $cnt(['source' => 'task', 'item_id' => $N3]) === 0);

section('The reminders list (bell)');
$before = srm_inbox_unread($db, $t1);
check('unread count counts the person\'s own reminders', $before > 3);
$first = srm_inbox_list($db, $t1)[0]['id'];
srm_inbox_read($db, $sa, $first);   // somebody else cannot mark it
check('somebody else cannot mark another person\'s reminder', srm_inbox_unread($db, $t1) === $before);
srm_inbox_read($db, $t1, $first);
check('marking one as read lowers the count by one', srm_inbox_unread($db, $t1) === $before - 1);
srm_inbox_read($db, $t1, 0);
check('mark all as read', srm_inbox_unread($db, $t1) === 0);
check('the list links to the Staff Calendar', (bool) array_filter(srm_inbox_list($db, $t1), fn($r) => str_starts_with($r['url'], 'tutor/staffcalendar/')));
check('the super admin\'s links open the super admin pages', (bool) array_filter(srm_inbox_list($db, $sa), fn($r) => str_starts_with($r['url'], 'superadmin/staffcalendar/')));

section('The email');
[$subj, $html] = StaffReminderEmail::render('Staff Calendar reminder', 'A <b>"x"</b> & more', 'https://x.test/tutor/staffcalendar/index.php?view=day&d=2031-03-10', 'Tina <script>');
check('email has the logo, the green button and escapes everything', str_contains($html, 'cid:ismanlogo') && str_contains($html, 'Open Staff Calendar') && str_contains($html, '#048C44') && !str_contains($html, '<script>') && !str_contains($html, '<b>"x"</b>') && str_contains($html, '&amp; more') && str_contains($html, 'view=day&amp;d=2031-03-10'));

section('The script');
$php = PHP_BINARY; $script = realpath(__DIR__ . '/../tools/staffcalendar_reminders.php');
$out = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' --host=dazu.test --https --now="2031-03-09 08:00" --dry-run 2>&1');
check('script --now --dry-run runs and reports', is_string($out) && str_contains($out, 'DRY RUN') && str_contains($out, '2031-03-09 08:00 EAT'), (string) $out);
check('script says nothing is left (already sent)', is_string($out) && !str_contains($out, 'Tina Test') && !str_contains($out, 'Simon Test'), (string) $out);
$out = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($script) . ' --now="not a date" 2>&1');
check('script refuses a bad --now', is_string($out) && str_contains($out, 'Bad --now'));
check('script refuses to run from the web', str_contains((string) file_get_contents($script), "PHP_SAPI !== 'cli'"));

section('Clean up my test data');
$ids = implode(',', array_map('intval', $ALL));
$db->exec("DELETE FROM staff_calendar_reminder_log WHERE user_id IN ($ids)");
$db->exec("DELETE FROM staff_calendar_reminders WHERE user_id IN ($ids)");
$db->exec("DELETE FROM staff_calendar_events WHERE owner_user_id IN ($ids)");
$db->exec("DELETE FROM staff_calendar_checklist WHERE assignee_user_id IN ($ids)");
$db->exec("DELETE FROM staff_calendar_task_notices WHERE to_user_id IN ($ids)");
$db->exec("DELETE FROM staff_calendar_gift_notes WHERE to_user_id IN ($ids)");
$db->exec("DELETE FROM staff_calendar_gift_items WHERE list_id = $list");
$db->exec("DELETE FROM staff_calendar_gift_lists WHERE id = $list");
$db->exec("DELETE FROM staff_calendar_birthdays WHERE user_id IN ($ids)");
$db->exec("DELETE FROM staff_calendar_birthday_log WHERE user_id IN ($ids)");
$db->exec("DELETE FROM push_subscriptions WHERE user_id IN ($ids)");
$db->exec("DELETE FROM staff_calendar_user_prefs WHERE user_id IN ($ids)");
$db->exec("DELETE FROM tutors WHERE user_id IN ($ids)");
$db->exec("DELETE FROM user_profiles WHERE user_id IN ($ids)");
$db->exec("DELETE FROM users WHERE id IN ($ids)");

echo "\n";
if ($fails) { echo "$fails of $n checks FAILED.\n"; exit(1); }
echo "All $n checks passed.\n";
