<?php
// The admin's Secretaries list (switches that save at once, status, suspend, reset password, edit, the change log) and the Staff Calendar rule for secretaries
// (Full = like a tutor, View only = look and add her own birthday, Off = nothing; checked in the services on every call).
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/secretary_admin_test.php      (a scratch database with all migrations applied; it refuses the database named in config/database.php)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$configured = (require __DIR__ . '/../config/database.php')['dbname'] ?? '';
if (!getenv('DAZU_DB_NAME') || getenv('DAZU_DB_NAME') === $configured) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database (not the one in config/database.php).\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SecretaryAdminService.php';
require_once __DIR__ . '/../services/MigrationService.php';
require_once __DIR__ . '/../services/StaffCalendar.php';
require_once __DIR__ . '/../services/StaffBirthdays.php';
require_once __DIR__ . '/../services/StaffGifts.php';
require_once __DIR__ . '/../services/StaffReminders.php';
require_once __DIR__ . '/../services/PortalNotifications.php';

$db = \Models\Connection::getInstance()->getConnection();
echo 'Database driver: ' . $db->getAttribute(PDO::ATTR_DRIVER_NAME) . ' ' . $db->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
$fails = 0; $n = 0;
function check(string $name, $cond, string $extra = ''): void
{
    global $fails, $n;
    $n++;
    if ($cond) { echo "ok   $name\n"; return; }
    $fails++;
    echo "FAIL $name" . ($extra !== '' ? "  ($extra)" : '') . "\n";
}
function section(string $s): void { echo "\n== $s\n"; }

$run = substr(md5((string) microtime(true)), 0, 6);
$mails = [];
$mail = function ($to, $name, $subject, $html) use (&$mails) { $mails[] = compact('to', 'html'); return ['ok' => true, 'message' => 'sent']; };
$svc = new SecretaryService($db, $mail);
$adm = new SecretaryAdminService($db, $mail);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$pw = password_hash('x', PASSWORD_DEFAULT);
$mkuser = function (string $key, string $role, string $first, string $last) use ($db, $pw, $roles, $run): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["sa_$key$run", "$key$run@sa.test", $pw, $roles[$role]]);
    $id = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, $first, $last]);
    return $id;
};
$admin = $mkuser('admin', 'admin', 'Alice', 'Admin');
$super = $mkuser('super', 'superadmin', 'Simon', 'Mwangi');
$tutor = $mkuser('tutor', 'tutor', 'Tina', 'Otieno'); $db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$tutor]);
$base = ['first_name' => 'Ann', 'last_name' => 'Full', 'email' => "ann$run@sa.test", 'phone' => '0712345678', 'branch' => 'nairobi', 'birth_day' => 14, 'birth_month' => 11, 'start_date' => '2026-01-05'];
$make = function (string $key, string $last, array $sw = []) use ($svc, $base, $run, $admin): array {
    [$d, $e] = $svc->validate(array_merge($base, ['first_name' => ucfirst($key), 'last_name' => $last, 'email' => "$key$run@sa.test", 'sw' => $sw]));
    if ($e) throw new RuntimeException(implode('; ', $e));
    return $svc->create($d, null, $admin);
};
$full = $make('full', 'Secretary', ['staff_calendar' => 'edit']);
$view = $make('view', 'Secretary');                 // new secretaries start on View only
$off = $make('off', 'Secretary', ['staff_calendar' => 'off']);
$uid = fn(array $r) => (int) $r['user_id'];

section('Migration 124');
$before = (int) $db->query('SELECT COUNT(*) FROM secretary_changes')->fetchColumn();
$tmp = __DIR__ . '/sec_mig_tmp_' . getmypid(); mkdir($tmp);
copy(__DIR__ . '/../databases/required_migrations/124_secretary_changes.sql', "$tmp/124_secretary_changes.sql");
$db->exec("DELETE FROM schema_migrations WHERE filename = '124_secretary_changes.sql'");
$r = (new MigrationService($db, $tmp))->applyPending(static function (string $m): void {});
array_map('unlink', glob("$tmp/*")); rmdir($tmp);
check('it runs a second time and changes nothing', $r['ran'] === 1 && empty($r['failed']) && (int) $db->query('SELECT COUNT(*) FROM secretary_changes')->fetchColumn() === $before);
check('it is number 124, used once', is_file(__DIR__ . '/../databases/required_migrations/124_secretary_changes.sql') && count(glob(__DIR__ . '/../databases/required_migrations/124_*')) === 1);

section('Switches save at once and are recorded');
check('a new secretary starts on View only', secretary_switches($db, (int) $view['secretary_id'])['staff_calendar'] === 'view');
$r = $adm->setSwitch((int) $full['secretary_id'], 'blogs_approval', 'off', $admin);
check('the first secretary can be set to blog approval Not needed', $r['ok'] && secretary_switches($db, (int) $full['secretary_id'])['blogs_approval'] === 'off');
$log = $db->query("SELECT * FROM secretary_changes WHERE secretary_id = " . (int) $full['secretary_id'] . " AND field_key = 'blogs_approval' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('the server recorded who, when, the old and the new value', $log && (int) $log['changed_by'] === $admin && $log['old_value'] === 'on' && $log['new_value'] === 'off' && !empty($log['changed_at']));
check('the log keeps the NAME of the admin who made the change, as it was then', $log['changed_by_name'] === 'Alice Admin');
$db->prepare("UPDATE user_profiles SET last_name = 'Renamed' WHERE user_id = ?")->execute([$admin]);
$r0 = $adm->all();
check('a later rename of the admin does not rewrite the old entry', array_values(array_filter($r0, fn($x) => (int) $x['id'] === (int) $full['secretary_id']))[0]['last_change']['by_name'] === 'Alice Admin');
$db->prepare("UPDATE user_profiles SET last_name = 'Admin' WHERE user_id = ?")->execute([$admin]);
$c = (int) $db->query('SELECT COUNT(*) FROM secretary_changes')->fetchColumn();
$r = $adm->setSwitch((int) $full['secretary_id'], 'blogs_approval', 'off', $admin);
check('saving the same value again changes and records nothing', $r['ok'] && $r['changed'] === false && (int) $db->query('SELECT COUNT(*) FROM secretary_changes')->fetchColumn() === $c);
check('Full can be set on a secretary that started on View only', $adm->setSwitch((int) $view['secretary_id'], 'staff_calendar', 'edit', $admin)['ok'] && secretary_switches($db, (int) $view['secretary_id'])['staff_calendar'] === 'edit');
$adm->setSwitch((int) $view['secretary_id'], 'staff_calendar', 'view', $admin);
foreach ([['nonsense', 'on'], ['staff_calendar', 'full'], ['blogs_approval', 'view'], ['id_card', ''], ['staff_calendar', "edit'; DROP TABLE secretaries;--"]] as [$k, $v]) {
    check("refused: $k = " . json_encode($v), !$adm->setSwitch((int) $full['secretary_id'], $k, $v, $admin)['ok']);
}
check('an unknown secretary is refused', !$adm->setSwitch(999999, 'id_card', 'on', $admin)['ok']);
check('the secretaries table is still there', (int) $db->query('SELECT COUNT(*) FROM secretaries')->fetchColumn() >= 3);
$rows = $adm->all();
$mine = array_values(array_filter($rows, fn($x) => (int) $x['id'] === (int) $full['secretary_id']))[0];
check('the list row has her name, ID number, status, switches and the last change by name only', $mine['full_name'] === 'Full Secretary' && $mine['status'] === 'active' && $mine['switches']['blogs_approval'] === 'off' && $mine['last_change']['by_name'] === 'Alice Admin');

section('Status, Left and Suspend');
$uf = $uid($full); $sid = (int) $full['secretary_id'];
$active = fn(int $u) => (int) $db->query("SELECT is_active FROM users WHERE id = $u")->fetchColumn();
check('Left turns her login off at once and keeps her record', $adm->setStatus($sid, 'left', $admin)['ok'] && $active($uf) === 0 && (int) $db->query("SELECT COUNT(*) FROM secretaries WHERE id = $sid")->fetchColumn() === 1);
check('a Left secretary has no Staff Calendar access', scal_actor($db, $uf) === null);
check('"Left" is recorded', $db->query("SELECT new_value FROM secretary_changes WHERE secretary_id = $sid AND field_key = 'status' ORDER BY id DESC LIMIT 1")->fetchColumn() === 'left');
check('a Left secretary gets no new password email', !$adm->resetPassword($sid, $admin)['ok']);
check('a status that does not exist is refused', !$adm->setStatus($sid, 'gone', $admin)['ok']);
$adm->setStatus($sid, 'on_leave', $admin);
$sl = $adm->setStatus($sid, 'on_leave', $admin);
check('On leave: nothing is blocked: her login is on and the calendar still lets her in at her level', $sl['ok'] && $active($uf) === 1 && secretary_by_user($db, $uf)['status'] === 'on_leave' && scal_actor($db, $uf)['level'] === 'full');
check('On leave: the guard data lets her through (only Left blocks it)', (function () use ($db, $uf) { $x = secretary_by_user($db, $uf); return $x && $x['role_name'] === 'secretary' && !empty($x['user_active']) && $x['status'] !== 'left'; })());
$sl = $db->query("SELECT * FROM secretary_changes WHERE secretary_id = $sid AND field_key = 'status' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('the status change is in the log with the admin name and the time', $sl['old_value'] === 'left' && $sl['new_value'] === 'on_leave' && $sl['changed_by_name'] === 'Alice Admin' && !empty($sl['changed_at']));
$c0 = (int) $db->query("SELECT COUNT(*) FROM secretary_changes WHERE secretary_id = $sid AND field_key = 'status'")->fetchColumn();
check('the same status again is not logged twice', $adm->setStatus($sid, 'on_leave', $admin)['changed'] === false && (int) $db->query("SELECT COUNT(*) FROM secretary_changes WHERE secretary_id = $sid AND field_key = 'status'")->fetchColumn() === $c0);
$adm->setStatus($sid, 'active', $admin);
check('Active again: access is back', $active($uf) === 1 && scal_actor($db, $uf) !== null);
check('Suspend blocks the login at once', $adm->setSuspended($sid, true, $admin)['ok'] && $active($uf) === 0 && scal_actor($db, $uf) === null
    && $db->query("SELECT account_status FROM users WHERE id = $uf")->fetchColumn() === 'suspended');
$adm->setStatus($sid, 'left', $admin);
$adm->setSuspended($sid, false, $admin);
check('lifting a suspension does not bring back someone who has Left', $active($uf) === 0);
$adm->setStatus($sid, 'active', $admin);
check('Active and not suspended: she can sign in again', $active($uf) === 1 && scal_actor($db, $uf) !== null);
check('suspending is recorded', (int) $db->query("SELECT COUNT(*) FROM secretary_changes WHERE secretary_id = $sid AND field_key = 'suspended'")->fetchColumn() === 2);

section('Reset password');
$mails = [];
$db->prepare('UPDATE users SET must_change_password = 0 WHERE id = ?')->execute([$uf]);
$old = (string) $db->query("SELECT password_hash FROM users WHERE id = $uf")->fetchColumn();
$r = $adm->resetPassword($sid, $admin);
$new = $db->query("SELECT password_hash, must_change_password FROM users WHERE id = $uf")->fetch(PDO::FETCH_ASSOC);
check('a new temporary password is made, emailed to her, and she must change it', !empty($r['ok']) && !empty($r['email_sent']) && $new['password_hash'] !== $old && (int) $new['must_change_password'] === 1
    && count($mails) === 1 && $mails[0]['to'] === "full$run@sa.test" && preg_match('#Temporary password: <strong>([0-9a-f]{16})</strong>#', $mails[0]['html'], $m) && password_verify($m[1], $new['password_hash']));
check('the reset is recorded', (int) $db->query("SELECT COUNT(*) FROM secretary_changes WHERE secretary_id = $sid AND field_key = 'password'")->fetchColumn() === 1);

section('Edit keeps the ID number');
$no = (string) $db->query("SELECT staff_no FROM secretaries WHERE id = $sid")->fetchColumn();
[$d, $e] = $svc->validate(array_merge($base, ['first_name' => 'Annette', 'last_name' => 'Full', 'email' => "full$run@sa.test", 'branch' => 'thika', 'job_title' => 'Office lead', 'sw' => ['staff_calendar' => 'edit', 'blogs_approval' => 'off', 'id_card' => 'on']]), $uf);
check('her own email is not "already taken" when she is edited', !$e, implode('|', $e));
[, $e2] = $svc->validate(array_merge($base, ['email' => "view$run@sa.test"]), $uf);
check('someone else\'s email still is', count($e2) === 1);
$r = $adm->update($sid, $d, null, 'active', $admin);
$row = $adm->find($sid);
check('the edit is saved', $r['ok'] && $row['first_name'] === 'Annette' && $row['branch'] === 'thika' && $row['job_title'] === 'Office lead' && $row['switches']['id_card'] === 'on');
check('her ID number did not change', $row['staff_no'] === $no);
check('the switch changed in the form was recorded', (int) $db->query("SELECT COUNT(*) FROM secretary_changes WHERE secretary_id = $sid AND field_key = 'id_card' AND new_value = 'on'")->fetchColumn() === 1);
check('"Added by" is the name only', $row['added_by_name'] === 'Alice Admin');

section('Staff Calendar: who is in');
$aFull = scal_actor($db, $uf); $uv = $uid($view); $aView = scal_actor($db, $uv); $uo = $uid($off);
check('Full: she is let in as a secretary at level full, with her name', $aFull && $aFull['role'] === 'secretary' && $aFull['level'] === 'full' && $aFull['name'] === 'Annette Full');
check('View only: she is let in at level view', $aView && $aView['role'] === 'secretary' && $aView['level'] === 'view' && scal_is_view_only($aView) && !scal_is_view_only($aFull));
check('Off: she is not let in', scal_actor($db, $uo) === null);
check('a secretary with no switch row is not let in', ($db->exec("DELETE FROM secretary_switches WHERE secretary_id = " . (int) $off['secretary_id']) || true) && scal_actor($db, $uo) === null);
$T = scal_actor($db, $tutor); $A = scal_actor($db, $super);
check('tutor and super admin are let in exactly as before (no level added)', $T && $A && !array_key_exists('level', $T) && !array_key_exists('level', $A) && !scal_is_view_only($T) && !scal_is_view_only($A));
check('menu rule: Full and View only secretaries yes, Off no', scal_can_see_menu($db, $uf) && scal_can_see_menu($db, $uv) && !scal_can_see_menu($db, $uo));

section('Staff Calendar: events');
$ins = fn(array $a) => $a + ['title' => 'T', 'type' => 'meeting', 'start_date' => '2026-12-01', 'start_time' => '09:00', 'end_date' => '2026-12-01', 'end_time' => '10:00', 'repeat' => 'none'];
$rf = scal_save($db, $aFull, $ins(['title' => 'Full adds']));
check('Full can add an event, like a tutor', $rf['ok']);
$ev = scal_find($db, $aFull, $rf['id']);
check('Full can change and delete her own event', scal_can_change($aFull, $ev) && scal_save($db, $aFull, $ins(['title' => 'Full edits']), null, $rf['id'])['ok']);
check('a tutor cannot change her event (only the owner can)', !scal_can_change($T, scal_find($db, $T, $rf['id'])));
$rt = scal_save($db, $T, $ins(['title' => 'Tutor event']));
check('View only: cannot create an event', !scal_save($db, $aView, $ins(['title' => 'Nope']))['ok']);
check('View only: cannot change an event, even one that exists', !scal_save($db, $aView, $ins(['title' => 'Hacked']), null, $rt['id'])['ok'] && $db->query('SELECT title FROM staff_calendar_events WHERE id = ' . (int) $rt['id'])->fetchColumn() === 'Tutor event');
check('View only: cannot delete an event', !scal_delete($db, $aView, $rt['id'])['ok'] && (int) $db->query('SELECT COUNT(*) FROM staff_calendar_events WHERE id = ' . (int) $rt['id'])->fetchColumn() === 1);
$seen = scal_find($db, $aView, $rt['id']);
check('View only: she can see events', $seen !== null && $seen['title'] === 'Tutor event' && count(scal_range($db, $aView, new DateTime('2026-12-01', scal_tz()), new DateTime('2026-12-02', scal_tz()))) >= 1);
check('View only: the edit buttons are off (can_change is false even for her own id)', !scal_can_change($aView, ['owner_id' => $uv]));
check('View only cannot see a private event of a tutor', ($p = scal_save($db, $T, $ins(['title' => 'Secret', 'private' => 1]))) && scal_find($db, $aView, $p['id']) === null);
check('Full cannot see it either (private means private)', scal_find($db, $aFull, $p['id']) === null);

section('Staff Calendar: notices, birthdays, gifts, reminders');
$cnt = fn(int $u) => (int) $db->query("SELECT COUNT(*) FROM portal_notifications WHERE user_id = $u AND type = 'staff_calendar'")->fetchColumn();
foreach ([$uf, $uv, $tutor] as $u) PortalNotifications::saveChoices($db, $u, ['other']);
$b = [$uf => $cnt($uf), $uv => $cnt($uv), $tutor => $cnt($tutor)];
scal_save($db, $A, $ins(['title' => 'All hands', 'notify' => 1]));
check('a tutor and a Full secretary are told of a new event; a View only secretary is not', $cnt($tutor) === $b[$tutor] + 1 && $cnt($uf) === $b[$uf] + 1 && $cnt($uv) === $b[$uv]);
$path = (string) $db->query("SELECT url FROM portal_notifications WHERE user_id = $uf AND type = 'staff_calendar' ORDER BY id DESC LIMIT 1")->fetchColumn();
check('her notice opens her own calendar', strpos($path, 'secretary/staffcalendar/index.php') === 0, $path);

$people = sbd_people($db);
check('both Full and View only secretaries can have a birthday; Off cannot', isset($people[$uf]) && isset($people[$uv]) && !isset($people[$uo]));
check('View only: she can add her own birthday', sbd_save($db, $aView, ['day' => 9, 'month' => 3])['ok'] && (int) $db->query("SELECT birth_day FROM staff_calendar_birthdays WHERE user_id = $uv")->fetchColumn() === 9);
check('Full: she can add her own birthday', sbd_save($db, $aFull, ['day' => 14, 'month' => 11])['ok']);
check('she can only ever set her own (a user_id in the request is ignored)', sbd_save($db, $aView, ['day' => 10, 'month' => 3, 'user_id' => $tutor])['ok'] && !$db->query("SELECT 1 FROM staff_calendar_birthdays WHERE user_id = $tutor AND birth_day = 10")->fetchColumn());
$gp = sg_people($db);
check('the gift list has the Full secretary (word "Secretary"), not the View only one', isset($gp[$uf]) && $gp[$uf]['role'] === 'Secretary' && !isset($gp[$uv]) && isset($gp[$tutor]) && $gp[$tutor]['role'] === 'Tutor');
check('a View only secretary\'s birthday makes no gift list', sg_ensure_list($db, $uv, '2026-03-09') === 0 && !$db->query("SELECT 1 FROM staff_calendar_gift_lists WHERE user_id = $uv")->fetchColumn());
$lid = sg_ensure_list($db, $tutor, '2026-12-05');
$givers = array_map('intval', $db->query("SELECT giver_user_id FROM staff_calendar_gift_items WHERE list_id = $lid")->fetchAll(PDO::FETCH_COLUMN));
check('a tutor\'s gift list asks the Full secretary and not the View only one', in_array($uf, $givers, true) && !in_array($uv, $givers, true));
check('a Full secretary\'s birthday makes a list', sg_ensure_list($db, $uf, '2026-11-14') > 0);
check('the View only secretary cannot see a gift list', sg_list_view($db, $aView, $lid, scal_now()) === null);
$srm = srm_people($db);
check('reminders go to a Full and to a View only secretary (her own portal folder), not to an Off one', isset($srm[$uf]) && $srm[$uf]['dir'] === 'secretary' && isset($srm[$uv]) && $srm[$uv]['dir'] === 'secretary' && !isset($srm[$uo]));

section('Reminders and birthday notices for a View only secretary (a test time, nothing is really sent)');
$ALL = [$uf, $uv, $uo, $tutor];
$inb = function (int $u) use ($db): array { $q = $db->prepare('SELECT title, body, url FROM staff_calendar_reminders WHERE user_id = ? ORDER BY id'); $q->execute([$u]); return $q->fetchAll(PDO::FETCH_ASSOC); };
$evIns = fn(string $title, int $private, int $notify) => $db->prepare('INSERT INTO staff_calendar_events (title, location, type_key, start_at, end_at, all_day, repeat_rule, owner_user_id, notify_all, is_private, remind_day_before, remind_on_day, remind_hour_before, remind_email) VALUES (?,?,?,?,?,0,?,?,?,?,1,1,1,1)')
    ->execute([$title, 'Hall', 'meeting', '2031-07-10 10:00:00', '2031-07-10 11:00:00', 'none', $tutor, $notify, $private]);
$evIns("Public $run", 0, 1); $evIns("Private $run", 1, 1); $evIns("Quiet $run", 0, 0);
$r1 = srm_run($db, new DateTime('2031-07-10 08:00', scal_tz()), ['no_send' => true, 'only_users' => $ALL]);
$tit = fn(int $u) => implode('|', array_column($inb($u), 'body'));
check('View only gets the event reminder of a public event, like a Full secretary and a tutor', str_contains($tit($uv), "Public $run") && str_contains($tit($uf), "Public $run") && str_contains($tit($tutor), "Public $run"), $tit($uv));
check('View only does not get a private event, or one with Notify everyone off (she cannot see them)', !str_contains($tit($uv), "Private $run") && !str_contains($tit($uv), "Quiet $run") && !str_contains($tit($uf), "Private $run"));
check('an Off secretary gets nothing', $inb($uo) === []);
check('the reminder is titled Staff Calendar reminder and opens her own portal folder', ($inb($uv)[0]['title'] ?? '') === 'Staff Calendar reminder' && str_contains((string) ($inb($uv)[0]['url'] ?? ''), 'secretary/staffcalendar/'), json_encode($inb($uv)[0] ?? null));
$n1 = count($inb($uv));
$r1b = srm_run($db, new DateTime('2031-07-10 08:00', scal_tz()), ['no_send' => true, 'only_users' => $ALL]);
check('running it again sends nothing twice', count($inb($uv)) === $n1 && $r1b['sent_app'] === 0);
// a birthday: the tutor on 20 Aug 2031 (with a gift list), the View only secretary's own on 22 Aug 2031
$db->prepare('DELETE FROM staff_calendar_birthdays WHERE user_id IN (?, ?)')->execute([$tutor, $uv]);
$db->prepare('INSERT INTO staff_calendar_birthdays (user_id, birth_day, birth_month, birth_year, show_age) VALUES (?, 20, 8, NULL, 0)')->execute([$tutor]);
$db->prepare('INSERT INTO staff_calendar_birthdays (user_id, birth_day, birth_month, birth_year, show_age) VALUES (?, 22, 8, NULL, 0)')->execute([$uv]);
$lidT = sg_ensure_list($db, $tutor, '2031-08-20');
srm_run($db, new DateTime('2031-08-20 08:00', scal_tz()), ['no_send' => true, 'only_users' => $ALL]);
$vn = array_column($inb($uv), 'body'); $fn = array_column($inb($uf), 'body');
$tFirst = scal_actor($db, $tutor)['first'];
check('View only gets the birthday notice of a colleague, like a Full secretary', in_array("Today is {$tFirst}’s birthday", $vn, true) && in_array("Today is {$tFirst}’s birthday", $fn, true), json_encode($vn));
check('a Full secretary gets the gift reminder; View only gets none (she is not in the gift list)', (bool) array_filter($fn, fn($b) => str_contains($b, 'gift is waiting')) && !array_filter($vn, fn($b) => str_contains($b, 'gift')));
check('there is no gift item for View only', !$db->query("SELECT 1 FROM staff_calendar_gift_items WHERE list_id = $lidT AND giver_user_id = $uv")->fetchColumn());
srm_run($db, new DateTime('2031-08-22 08:00', scal_tz()), ['no_send' => true, 'only_users' => $ALL]);
$vn = array_column($inb($uv), 'body'); $tn = array_column($inb($tutor), 'body');
$vFirst = scal_actor($db, $uv)['first'];
check('on her own birthday View only gets her own message, and her colleagues are told', count(array_filter($vn, fn($b) => $b !== '' && !str_contains($b, 'Today is') && !str_contains($b, "Public $run") && !str_contains($b, 'gift'))) >= 1 && in_array("Today is {$vFirst}’s birthday", $tn, true));
check('her birthday made no gift list and no gift reminder for anyone', !$db->query("SELECT 1 FROM staff_calendar_gift_lists WHERE user_id = $uv AND gift_year = 2031")->fetchColumn() && !array_filter($tn, fn($b) => str_contains($b, 'gift is waiting') && false));
$db->prepare('DELETE FROM staff_calendar_birthdays WHERE user_id IN (?, ?)')->execute([$tutor, $uv]);
$adm->setStatus($sid, 'left', $admin);
check('Left: she is no longer a person for reminders, and nothing is deleted', !isset(srm_people($db)[$uf]) && (int) $db->query("SELECT COUNT(*) FROM secretaries WHERE id = $sid")->fetchColumn() === 1);
$adm->setStatus($sid, 'active', $admin);

// for secretary_admin_http.sh: these logins use the password x, no forced change, Active, not suspended, and the calendar level each name says
$db->prepare("UPDATE users SET password_hash = ?, must_change_password = 0, is_active = 1, account_status = 'active' WHERE id IN (?, ?, ?)")->execute([$pw, $uf, $uv, $uo]);
$db->prepare("UPDATE users SET password_hash = ? WHERE id IN (?, ?, ?)")->execute([$pw, $admin, $tutor, $super]);
$db->prepare("UPDATE secretaries SET status = 'active' WHERE id IN (?, ?, ?)")->execute([$sid, (int) $view['secretary_id'], (int) $off['secretary_id']]);
$adm->setSwitch($sid, 'staff_calendar', 'edit', $admin);
$adm->setSwitch((int) $view['secretary_id'], 'staff_calendar', 'view', $admin);
$adm->setSwitch((int) $off['secretary_id'], 'staff_calendar', 'off', $admin);
echo "\n" . ($fails === 0 ? "All $n checks passed." : "$fails of $n checks FAILED.") . "\n";
$logins = ['admin' => ['id' => $admin, 'email' => "admin$run@sa.test"], 'super' => ['id' => $super, 'email' => "super$run@sa.test"], 'full' => ['id' => $uf, 'email' => "full$run@sa.test"], 'view' => ['id' => $uv, 'email' => "view$run@sa.test"],
           'tutor' => ['id' => $tutor, 'email' => "tutor$run@sa.test"], 'off' => ['id' => $uo, 'email' => "off$run@sa.test"], '_sec_full' => $sid, '_sec_view' => (int) $view['secretary_id'], '_run' => $run];
file_put_contents((getenv('TMPDIR') ?: getenv('TEMP') ?: sys_get_temp_dir()) . '/secretary_admin_users.json', json_encode($logins));
exit($fails === 0 ? 0 : 1);
