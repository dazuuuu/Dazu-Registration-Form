<?php
// Staff Calendar: who may use it, private events, owner name, repeats, saving rules, notifications and the two migrations (including running them twice).
//   DAZU_DB_NAME=<scratch db> [DAZU_DB_DRIVER=mysql DAZU_DB_PORT=3307 DAZU_DB_HOST=127.0.0.1 DAZU_DB_USER=root DAZU_DB_PASS=] php app/v1/tests/staffcalendar_test.php
// Uses a scratch database that has all migrations applied (it never touches a live one). Writes the logins it made to <temp>/staffcal_users.json for tests/staffcalendar_http.sh.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/StaffCalendar.php';
require_once __DIR__ . '/../services/MigrationService.php';
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
$pw = password_hash('x', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$logins = [];
$mkuser = function (string $key, string $role, string $first, string $last, bool $active = true) use ($db, $pw, $roles, $run, &$logins): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, ?, 1, 'active')")->execute(["sc_$key$run", "$key$run@sc.test", $pw, $roles[$role], $active ? 1 : 0]);
    $id = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, $first, $last]);
    $logins[$key] = ['id' => $id, 'email' => "$key$run@sc.test"];
    return $id;
};
$mktutorrow = fn(int $uid, int $active = 1) => $db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, ?)')->execute([$uid, $active]);
$sa = $mkuser('super', 'superadmin', 'Simon', 'Mwangi');
$t1 = $mkuser('tutor', 'tutor', 'Tina', 'Otieno');            $mktutorrow($t1);
$t2 = $mkuser('tutor2', 'tutor', 'Tom', 'Kamau');             $mktutorrow($t2);
$tOff = $mkuser('tutoroff', 'tutor', 'Off', 'Duty');           $mktutorrow($tOff, 0);
$tNone = $mkuser('tutornorow', 'tutor', 'No', 'Row');
$tIn = $mkuser('tutorinactive', 'tutor', 'In', 'Active', false); $mktutorrow($tIn);
$ad = $mkuser('admin', 'admin', 'Ad', 'Min');
$adT = $mkuser('adminteach', 'admin', 'Ada', 'Teaches');       $mktutorrow($adT);
$emp = $mkuser('employee', 'employee', 'Em', 'Ployee');
$usr = $mkuser('user', 'user', 'Us', 'Er');
$intn = $mkuser('intern', 'intern', 'In', 'Tern');
$stu = $mkuser('student', 'student', 'Stu', 'Dent');

section('Who may use the Staff Calendar (the role is read from the database)');
$act = fn(int $uid) => scal_actor($db, $uid);
check('a tutor may', ($act($t1)['role'] ?? '') === 'tutor');
check('the super admin may', ($act($sa)['role'] ?? '') === 'superadmin');
check('an admin may not', $act($ad) === null);
check('an admin who also teaches may not', $act($adT) === null);
check('the employee (staff) role may not', $act($emp) === null);
check('a user-role account may not', $act($usr) === null);
check('an intern may not', $act($intn) === null);
check('a student may not', $act($stu) === null);
check('a tutor whose tutor record is switched off may not', $act($tOff) === null);
check('a tutor with no tutor record may not', $act($tNone) === null);
check('a deactivated account may not', $act($tIn) === null);
check('nobody signed in may not', scal_actor($db, 0) === null && scal_actor($db, 999999) === null);
check('the menu item shows for a tutor and the super admin only',
    scal_can_see_menu($db, $t1) && scal_can_see_menu($db, $sa) && !scal_can_see_menu($db, $ad) && !scal_can_see_menu($db, $emp) && !scal_can_see_menu($db, $stu) && !scal_can_see_menu($db, $adT));
check('the name is the person\'s name, never the role', $act($sa)['name'] === 'Simon Mwangi' && $act($t1)['name'] === 'Tina Otieno' && $act($t1)['first'] === 'Tina');
check('times use Nairobi time', scal_now()->getTimezone()->getName() === 'Africa/Nairobi');

section('The migrations: tables, 11 types, one starter event, safe to run twice');
check('both tables exist', scal_installed($db));
$types = scal_types($db);
check('11 starter types', count($types) === 11, (string) count($types));
check('the types and their colours', array_column($types, 'label') === ['Meeting', 'Client meeting', 'Training', 'Deadline', 'Reminder', 'Birthday', 'Holiday', 'Project', 'Release', 'Payday', 'Out of office']
    && array_column($types, 'color', 'type_key')['meeting'] === '#048C44');
$runFile = function (string $f) use ($db): void {
    $ms = new MigrationService($db, DATABASES_PATH . '/required_migrations');
    $m = new ReflectionMethod($ms, 'statementsFromFile'); $m->setAccessible(true);
    foreach ($m->invoke($ms, DATABASES_PATH . '/required_migrations/' . $f) as $stmt) $db->exec($stmt);
};
$seedCount = fn() => (int) $db->query("SELECT COUNT(*) FROM staff_calendar_events WHERE title = 'Staff meeting' AND start_at = '2026-11-21 10:00:00'")->fetchColumn();
check('exactly one Staff meeting is seeded', $seedCount() === 1, (string) $seedCount());
$ev = $db->query("SELECT * FROM staff_calendar_events WHERE title = 'Staff meeting'")->fetch(PDO::FETCH_ASSOC);
$firstSuper = (int) $db->query("SELECT MIN(u.id) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.role_name = 'superadmin'")->fetchColumn();
check('seed: Meeting, Sat 21 Nov 2026 10:00-12:00, Nairobi House location, super admin owner, notify on, not private, no repeat',
    $ev['type_key'] === 'meeting' && $ev['end_at'] === '2026-11-21 12:00:00' && $ev['location'] === 'Nairobi House (Cianda House), 4th Floor, Room 406B'
    && (int) $ev['owner_user_id'] === $firstSuper && (int) $ev['notify_all'] === 1 && (int) $ev['is_private'] === 0 && $ev['repeat_rule'] === 'none' && (int) $ev['all_day'] === 0
    && (new DateTime('2026-11-21'))->format('l') === 'Saturday');
$runFile('111_staff_calendar.sql'); $runFile('112_staff_calendar_seed_event.sql'); $runFile('112_staff_calendar_seed_event.sql');
check('running them again adds no duplicate event and no duplicate type', $seedCount() === 1 && count(scal_types($db)) === 11);
$db->exec("DELETE FROM staff_calendar_events WHERE title = 'Staff meeting'");
$runFile('112_staff_calendar_seed_event.sql');
check('if the event was deleted the migration puts it back once', $seedCount() === 1);
// someone else owning a same-titled event on that day does not block it, but the same owner does
$db->exec("DELETE FROM staff_calendar_events WHERE title = 'Staff meeting'");
$db->prepare("INSERT INTO staff_calendar_events (title, type_key, start_at, end_at, owner_user_id) VALUES ('Staff meeting', 'meeting', '2026-11-21 09:00:00', '2026-11-21 09:30:00', ?)")->execute([$firstSuper]);
$runFile('112_staff_calendar_seed_event.sql');
check('same title, same day, same owner: no second one is made', $seedCount() === 0 && (int) $db->query("SELECT COUNT(*) FROM staff_calendar_events WHERE title = 'Staff meeting'")->fetchColumn() === 1);
$db->exec("DELETE FROM staff_calendar_events WHERE title = 'Staff meeting'");
$runFile('112_staff_calendar_seed_event.sql');

section('Repeats and the visible range');
$tz = scal_tz();
$occ = fn(array $e, string $a, string $b) => array_map(fn($p) => $p[0]->format('Y-m-d H:i'), scal_occurrences($e + ['end_at' => $e['end_at'] ?? $e['start_at']], new DateTime($a, $tz), new DateTime($b, $tz)));
check('no repeat: once, inside the range only', $occ(['start_at' => '2026-11-21 10:00:00', 'end_at' => '2026-11-21 12:00:00', 'repeat_rule' => 'none'], '2026-11-01', '2026-12-01') === ['2026-11-21 10:00']
    && $occ(['start_at' => '2026-11-21 10:00:00', 'end_at' => '2026-11-21 12:00:00', 'repeat_rule' => 'none'], '2026-12-01', '2027-01-01') === []);
check('weekly: every 7 days, never before the start', $occ(['start_at' => '2026-11-03 08:30:00', 'end_at' => '2026-11-03 09:30:00', 'repeat_rule' => 'weekly'], '2026-11-01', '2026-12-01') === ['2026-11-03 08:30', '2026-11-10 08:30', '2026-11-17 08:30', '2026-11-24 08:30']
    && $occ(['start_at' => '2026-11-03 08:30:00', 'end_at' => '2026-11-03 09:30:00', 'repeat_rule' => 'weekly'], '2026-10-01', '2026-10-31') === []);
check('weekly, years later, still lands on the right days', $occ(['start_at' => '2026-11-03 08:30:00', 'end_at' => '2026-11-03 09:30:00', 'repeat_rule' => 'weekly'], '2040-06-01', '2040-06-15') === ['2040-06-05 08:30', '2040-06-12 08:30']);
check('monthly: same day each month; the 31st falls on the last day of short months', $occ(['start_at' => '2026-01-31 09:00:00', 'end_at' => '2026-01-31 10:00:00', 'repeat_rule' => 'monthly'], '2026-02-01', '2026-05-01') === ['2026-02-28 09:00', '2026-03-31 09:00', '2026-04-30 09:00']);
check('yearly: same date each year (29 Feb falls on 28 Feb in other years)', $occ(['start_at' => '2024-02-29 00:00:00', 'end_at' => '2024-02-29 23:59:59', 'repeat_rule' => 'yearly'], '2025-01-01', '2029-01-01') === ['2025-02-28 00:00', '2026-02-28 00:00', '2027-02-28 00:00', '2028-02-29 00:00']);
check('a 3-day event is in range when only its last day is', count(scal_occurrences(['start_at' => '2026-11-02 00:00:00', 'end_at' => '2026-11-04 23:59:59', 'repeat_rule' => 'none'], new DateTime('2026-11-04', $tz), new DateTime('2026-11-05', $tz))) === 1);
check('a repeating 2-day event that began before the range still shows its tail', count(scal_occurrences(['start_at' => '2026-11-02 00:00:00', 'end_at' => '2026-11-03 23:59:59', 'repeat_rule' => 'weekly'], new DateTime('2026-11-10', $tz), new DateTime('2026-11-11', $tz))) === 1);

section('Saving, owner, private events and who can change what');
$ins = fn(array $a) => $a + ['title' => 'T', 'type' => 'meeting', 'start_date' => '2026-12-01', 'start_time' => '09:00', 'end_date' => '2026-12-01', 'end_time' => '10:00', 'repeat' => 'none'];
$A = $act($t1); $B = $act($t2); $S = $act($sa);
check('empty title refused', !scal_save($db, $A, $ins(['title' => '  ']))['ok']);
check('unknown type refused', !scal_save($db, $A, $ins(['type' => 'nonsense']))['ok']);
check('unknown repeat refused', !scal_save($db, $A, $ins(['repeat' => 'daily']))['ok']);
check('impossible date refused', !scal_save($db, $A, $ins(['start_date' => '2026-02-30', 'end_date' => '2026-02-30']))['ok']);
check('bad time refused', !scal_save($db, $A, $ins(['start_time' => '25:00']))['ok']);
check('ending before it starts refused', !scal_save($db, $A, $ins(['end_time' => '08:00']))['ok']);
check('too long a title refused', !scal_save($db, $A, $ins(['title' => str_repeat('x', 201)]))['ok']);
$r = scal_save($db, $A, $ins(['title' => 'Tina planning', 'location' => 'Room 1', 'description' => 'Plan the term']));
$tinaId = $r['id'] ?? 0;
check('a tutor adds an event', $r['ok'] && $tinaId > 0);
$row = $db->query("SELECT * FROM staff_calendar_events WHERE id = $tinaId")->fetch(PDO::FETCH_ASSOC);
check('the owner is the person who added it, saved as Nairobi time', (int) $row['owner_user_id'] === $t1 && $row['start_at'] === '2026-12-01 09:00:00' && $row['end_at'] === '2026-12-01 10:00:00');
$shape = scal_find($db, $B, $tinaId);
check('another tutor sees it, owner shown as the name only', $shape && $shape['owner'] === 'Tina Otieno');
$seedShape = scal_find($db, $B, (int) $db->query("SELECT id FROM staff_calendar_events WHERE title = 'Staff meeting'")->fetchColumn());
check('the seeded Staff meeting shows its owner by name', $seedShape && $seedShape['owner'] !== '' && (int) $seedShape['owner_id'] === $firstSuper);
$rs = scal_save($db, $S, $ins(['title' => 'Simon board', 'start_date' => '2026-12-02', 'end_date' => '2026-12-02']));
$simonEv = scal_find($db, $A, $rs['id']);
check('"Added by" for the super admin is Simon Mwangi, nothing about role', $simonEv['owner'] === 'Simon Mwangi' && !array_key_exists('role', $simonEv));
check('another tutor cannot change or delete it', !scal_save($db, $B, $ins(['title' => 'hijack']), null, $tinaId)['ok'] && !scal_delete($db, $B, $tinaId)['ok']
    && $db->query("SELECT title FROM staff_calendar_events WHERE id = $tinaId")->fetchColumn() === 'Tina planning');
check('the owner can change it', scal_save($db, $A, $ins(['title' => 'Tina planning v2', 'all_day' => 1, 'start_date' => '2026-12-01', 'end_date' => '2026-12-03']), null, $tinaId)['ok']);
$row = $db->query("SELECT * FROM staff_calendar_events WHERE id = $tinaId")->fetch(PDO::FETCH_ASSOC);
check('all-day covers whole days; owner did not change', $row['start_at'] === '2026-12-01 00:00:00' && $row['end_at'] === '2026-12-03 23:59:59' && (int) $row['all_day'] === 1 && (int) $row['owner_user_id'] === $t1);
check('the super admin can change someone else\'s event but stays not the owner', scal_save($db, $S, $ins(['title' => 'Tina planning v3']), null, $tinaId)['ok'] && (int) $db->query("SELECT owner_user_id FROM staff_calendar_events WHERE id = $tinaId")->fetchColumn() === $t1);
$rp = scal_save($db, $A, $ins(['title' => 'Secret chat', 'private' => 1, 'notify' => 1, 'start_date' => '2026-12-04', 'end_date' => '2026-12-04']));
$privId = $rp['id'];
check('private wins over notify (nobody is told about a private event)', (int) $db->query("SELECT notify_all FROM staff_calendar_events WHERE id = $privId")->fetchColumn() === 0);
check('the owner sees their private event', scal_find($db, $A, $privId) !== null);
check('another tutor and even the super admin do not', scal_find($db, $B, $privId) === null && scal_find($db, $S, $privId) === null);
$titles = fn(array $actor, string $a, string $b) => array_column(scal_range($db, $actor, new DateTime($a, $tz), new DateTime($b, $tz)), 'title');
check('private events are left out of the month for everyone else', in_array('Secret chat', $titles($A, '2026-12-01', '2027-01-01'), true) && !in_array('Secret chat', $titles($B, '2026-12-01', '2027-01-01'), true) && !in_array('Secret chat', $titles($S, '2026-12-01', '2027-01-01'), true));
check('...and out of search', count(scal_search($db, $A, 'Secret', scal_now())) === 1 && count(scal_search($db, $B, 'Secret', scal_now())) === 0 && count(scal_search($db, $S, 'Secret', scal_now())) === 0);
check('another tutor cannot change or delete a private event by id', !scal_save($db, $B, $ins(['title' => 'x']), null, $privId)['ok'] && !scal_delete($db, $B, $privId)['ok'] && !scal_delete($db, $S, $privId)['ok']);
check('the owner can delete it', scal_delete($db, $A, $privId)['ok'] && scal_find($db, $A, $privId) === null);

section('Search, up next, week count, deadlines');
check('search finds by title, any case, any date', count(scal_search($db, $B, 'staff MEET', scal_now())) >= 1 && scal_search($db, $B, 'staff MEET', scal_now())[0]['title'] === 'Staff meeting');
check('search treats % and _ as plain letters', scal_search($db, $B, '%', scal_now()) === [] && scal_search($db, $B, 'Sta_f', scal_now()) === []);
$now = new DateTime('2026-11-13 09:00:00', $tz);
$next = scal_next($db, $B, $now);
check('up next on Fri 13 Nov is the Staff meeting', $next && $next['title'] === 'Staff meeting' && $next['occ_date'] === '2026-11-21');
check('"In 8 days"', scal_in_days($next, $now) === 'In 8 days' && scal_in_days_words($next, $now) === 'in 8 days');
check('on the day it says Today; the day before, Tomorrow', scal_in_days($next, new DateTime('2026-11-21 11:00:00', $tz)) === 'Today' && scal_in_days($next, new DateTime('2026-11-20 23:00:00', $tz)) === 'Tomorrow');
check('once it has finished it is no longer up next', ($n2 = scal_next($db, $B, new DateTime('2026-11-21 12:30:00', $tz))) === null || $n2['title'] !== 'Staff meeting');
$weekN = count(scal_range($db, $B, new DateTime('2026-11-16', $tz), new DateTime('2026-11-23', $tz)));
check('"This week" (Mon 16 - Sun 22 Nov) counts the Staff meeting', $weekN >= 1);
$dl = ['type' => 'deadline', 'occ_end' => '2026-11-12 17:00:00'];
check('a past deadline is crossed out; a future one and other past types are not', scal_is_past_deadline($dl, $now) && !scal_is_past_deadline(['type' => 'deadline', 'occ_end' => '2026-11-14 17:00:00'], $now) && !scal_is_past_deadline(['type' => 'meeting', 'occ_end' => '2026-11-12 17:00:00'], $now));
check('when text', scal_when($next) === 'Sat 21 Nov 2026 · 10 am – 12 pm');

section('Notify everyone');
$cnt = fn(int $uid) => (int) $db->query("SELECT COUNT(*) FROM portal_notifications WHERE user_id = $uid AND type = 'staff_calendar'")->fetchColumn();
// The portal notifications are opt-in per category (Notifications page); a Staff Calendar notice is in "Everything else". Switch it on for the super admin and tutor 2 only.
$t3 = $mkuser('tutor3', 'tutor', 'Tracy', 'Wanjiru'); $mktutorrow($t3);
PortalNotifications::saveChoices($db, $t2, ['other']); PortalNotifications::saveChoices($db, $sa, ['other']); PortalNotifications::saveChoices($db, $t3, ['assignments']);
$before = [$t3 => $cnt($t3), $t1 => $cnt($t1), $t2 => $cnt($t2), $sa => $cnt($sa), $ad => $cnt($ad), $stu => $cnt($stu), $emp => $cnt($emp), $tOff => $cnt($tOff)];
$rn = scal_save($db, $A, $ins(['title' => 'Open day', 'notify' => 1, 'start_date' => '2026-12-10', 'end_date' => '2026-12-10']));
check('other tutors and the super admin are told, once', $cnt($t2) === $before[$t2] + 1 && $cnt($sa) === $before[$sa] + 1);
check('someone who has "Everything else" switched off is not told (their choice is respected)', $cnt($t3) === $before[$t3]);
check('the person who added it is not told; admin, employee, student and a switched-off tutor are not told', $cnt($t1) === $before[$t1] && $cnt($ad) === $before[$ad] && $cnt($stu) === $before[$stu] && $cnt($emp) === $before[$emp] && $cnt($tOff) === $before[$tOff]);
$body = (string) $db->query("SELECT body FROM portal_notifications WHERE user_id = $t2 AND type = 'staff_calendar' ORDER BY id DESC LIMIT 1")->fetchColumn();
check('the notification says Added by the name', strpos($body, 'Added by Tina Otieno') === 0);
scal_save($db, $A, $ins(['title' => 'Open day', 'notify' => 1, 'location' => 'Hall']), null, $rn['id']);
check('saving again with notify still on does not tell them twice', $cnt($t2) === $before[$t2] + 1);

section('Cover picture');
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
$td = is_dir((string) getenv('TEMP')) ? getenv('TEMP') : sys_get_temp_dir();
$tmp = tempnam($td, 'scc'); file_put_contents($tmp, $png);
$rc = scal_save($db, $A, $ins(['title' => 'With cover', 'start_date' => '2026-12-11', 'end_date' => '2026-12-11']), ['name' => 'c.png', 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => 0, 'size' => strlen($png)]);
$cover = (string) $db->query('SELECT cover_image FROM staff_calendar_events WHERE id = ' . (int) $rc['id'])->fetchColumn();
$abs = PROJECT_ROOT . '/public/' . $cover;
check('a picture is saved under uploads/staff_calendar', $rc['ok'] && strpos($cover, 'uploads/staffcalendar/') === 0 && is_file($abs));
$tmp2 = tempnam($td, 'scc'); file_put_contents($tmp2, '<?php echo 1;');
$bad = scal_save($db, $A, $ins(['title' => 'Bad cover']), ['name' => 'x.php', 'type' => 'image/png', 'tmp_name' => $tmp2, 'error' => 0, 'size' => 13]);
check('a script disguised as a picture is refused and no event is made', !$bad['ok'] && (int) $db->query("SELECT COUNT(*) FROM staff_calendar_events WHERE title = 'Bad cover'")->fetchColumn() === 0);
scal_delete($db, $A, (int) $rc['id']);
check('deleting the event removes its picture file', !is_file($abs));
@unlink($tmp); @unlink($tmp2);

section('The event card: occurrence, reminders, who can edit');
$seedId = (int) $db->query("SELECT id FROM staff_calendar_events WHERE title = 'Staff meeting'")->fetchColumn();
$oc = scal_occurrence($db, $B, $seedId, '2026-11-21');
$card = scal_card($db, $B, $oc, new DateTime('2026-11-13 09:00:00', $tz));
check('card: date with weekday, and the times', $card['date_line'] === 'Saturday, 21 November' && $card['time_line'] === '10:00 am to 12:00 pm');
check('card: reminders for the Staff meeting read "20 Nov 8:00 am · 21 Nov 8:00 am · 9:00 am"', $card['reminders'] === '20 Nov 8:00 am · 21 Nov 8:00 am · 9:00 am', $card['reminders']);
check('card: location, owner as a name only (no role), initials, In 8 days', $card['location'] === 'Nairobi House (Cianda House), 4th Floor, Room 406B' && $card['owner'] !== '' && !preg_match('/\brole\b|^tutor$/i', $card['owner']) && $card['initials'] !== '' && $card['badge'] === 'In 8 days');
check('card: another tutor cannot edit, the super admin can, the owner can', $card['can_change'] === false && scal_card($db, $S, $oc, $now)['can_change'] === true
    && scal_card($db, $A, scal_occurrence($db, $A, $tinaId, '2026-12-01'), $now)['can_change'] === true);
$ad = ['all_day' => true, 'occ_start' => '2026-12-01 00:00:00'];
check('reminders for an all-day event: day before and the morning', scal_reminders($ad) === '30 Nov 8:00 am · 1 Dec 8:00 am');
check('reminders for a 9:00 event do not repeat 8:00', scal_reminders(['all_day' => false, 'occ_start' => '2026-12-01 09:00:00']) === '30 Nov 8:00 am · 1 Dec 8:00 am');
check('reminders for an 8:30 event: 7:30 comes before 8:00', scal_reminders(['all_day' => false, 'occ_start' => '2026-12-01 08:30:00']) === '30 Nov 8:00 am · 1 Dec 7:30 am · 8:00 am');
check('a repeating event gives the occurrence of the day clicked', scal_occurrence($db, $A, $db->query("SELECT id FROM staff_calendar_events WHERE title = 'Tina planning v3' LIMIT 1")->fetchColumn(), '2026-12-02')['occ_date'] === '2026-12-01');
check('a private event gives no card to anyone else', scal_occurrence($db, $B, $privId2 = (int) scal_save($db, $A, $ins(['title' => 'Hidden card', 'private' => 1]))['id'], '2026-12-01') === null && scal_occurrence($db, $A, $privId2, '2026-12-01') !== null);
check('a day that is not a real date falls back to the first time', scal_occurrence($db, $B, $seedId, 'nonsense')['occ_date'] === '2026-11-21');
scal_delete($db, $A, $privId2);

section('The form: reminders, checklist, owner, cover folder, migration 113');
$runSafe = function (string $f) use ($db): void {   // like the updater: a column or table that already exists is skipped
    $ms = new MigrationService($db, DATABASES_PATH . '/required_migrations');
    $m = new ReflectionMethod($ms, 'statementsFromFile'); $m->setAccessible(true);
    foreach ($m->invoke($ms, DATABASES_PATH . '/required_migrations/' . $f) as $stmt) {
        try { $db->exec($stmt); } catch (PDOException $e) { if (!in_array($e->errorInfo[1] ?? 0, [1050, 1060, 1061], true)) throw $e; }
    }
};
$runSafe('113_staff_calendar_form.sql'); $runSafe('113_staff_calendar_form.sql');
$colN = (int) $db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'staff_calendar_events' AND column_name LIKE 'remind_%'")->fetchColumn();
check('migration 113 twice: four reminder columns once each and the checklist table', $colN === 4 && scal_installed($db));
$form = fn(array $a = []) => $ins($a) + ['form_v' => '1'];
$r = scal_save($db, $A, $form(['title' => 'Form test', 'remind_day_before' => 1, 'remind_email' => 1, 'checklist' => json_encode([['t' => 'Book room', 'd' => false], ['t' => '  Print agenda  ', 'd' => true], ['t' => '', 'd' => true]])]));
$fid = $r['id'];
$row = $db->query("SELECT * FROM staff_calendar_events WHERE id = $fid")->fetch(PDO::FETCH_ASSOC);
check('reminder switches are saved as chosen (day before + email on, the others off)', [(int) $row['remind_day_before'], (int) $row['remind_on_day'], (int) $row['remind_hour_before'], (int) $row['remind_email']] === [1, 0, 0, 1]);
check('checklist saved in order, trimmed, blank rows dropped, ticks kept', scal_checklist($db, $fid) === [['t' => 'Book room', 'd' => false], ['t' => 'Print agenda', 'd' => true]]);
$shape = scal_find($db, $A, $fid);
check('the card reminder text follows the switches', scal_reminders($shape + ['occ_start' => '2026-12-01 09:00:00']) === '30 Nov 8:00 am');
$r2 = scal_save($db, $A, $ins(['title' => 'Default test']));
$row2 = $db->query('SELECT remind_day_before, remind_on_day, remind_hour_before, remind_email FROM staff_calendar_events WHERE id = ' . (int) $r2['id'])->fetch(PDO::FETCH_NUM);
check('a request without the form marker keeps all four reminders on', array_map('intval', $row2) === [1, 1, 1, 1]);
scal_save($db, $A, $form(['title' => 'Form test', 'remind_on_day' => 1, 'checklist' => json_encode([['t' => 'Only one', 'd' => true]])]), null, $fid);
check('editing replaces the checklist and the switches', scal_checklist($db, $fid) === [['t' => 'Only one', 'd' => true]] && (int) $db->query("SELECT remind_on_day FROM staff_calendar_events WHERE id = $fid")->fetchColumn() === 1);
scal_save($db, $A, $ins(['title' => 'Form test']), null, $fid);
check('an edit that does not mention the checklist leaves it alone', count(scal_checklist($db, $fid)) === 1);
check('a checklist of 51 items, bad JSON, or a 201-letter item is refused', !scal_save($db, $A, $ins(['checklist' => json_encode(array_fill(0, 51, ['t' => 'x', 'd' => 0]))]))['ok']
    && !scal_save($db, $A, $ins(['checklist' => '{bad']))['ok'] && !scal_save($db, $A, $ins(['checklist' => json_encode([['t' => str_repeat('x', 201), 'd' => 0]])]))['ok']);
$before = (int) $db->query('SELECT COUNT(*) FROM staff_calendar_events')->fetchColumn();
scal_save($db, $A, $ins(['checklist' => '{bad']));
check('...and nothing was saved by a refused request', (int) $db->query('SELECT COUNT(*) FROM staff_calendar_events')->fetchColumn() === $before);
$r3 = scal_save($db, $A, $ins(['title' => 'Owner test', 'owner_user_id' => $t2, 'owner' => 'Tom Kamau']));
check('the owner is always the logged-in person: a posted owner is ignored on add', (int) $db->query('SELECT owner_user_id FROM staff_calendar_events WHERE id = ' . (int) $r3['id'])->fetchColumn() === $t1);
scal_save($db, $A, $ins(['title' => 'Owner test 2', 'owner_user_id' => $t2]), null, (int) $r3['id']);
check('...and on edit', (int) $db->query('SELECT owner_user_id FROM staff_calendar_events WHERE id = ' . (int) $r3['id'])->fetchColumn() === $t1);
scal_save($db, $S, $ins(['title' => 'Owner test 3', 'owner_user_id' => $sa]), null, (int) $r3['id']);
check('...even when the super admin edits it', (int) $db->query('SELECT owner_user_id FROM staff_calendar_events WHERE id = ' . (int) $r3['id'])->fetchColumn() === $t1);
check('a different tutor cannot save or delete (checked in the service, not the buttons)', !scal_save($db, $B, $ins(['title' => 'x']), null, $fid)['ok'] && !scal_delete($db, $B, $fid)['ok']);
$oc2 = scal_occurrence($db, $A, $fid, '2026-12-01');
check('the card gives the edit values to the owner only', scal_card($db, $A, $oc2, $now)['edit']['checklist'] === [['t' => 'Only one', 'd' => true]] && scal_card($db, $B, scal_occurrence($db, $B, $fid, '2026-12-01'), $now)['edit'] === null
    && scal_card($db, $S, scal_occurrence($db, $S, $fid, '2026-12-01'), $now)['edit']['owner'] === 'Tina Otieno');
scal_delete($db, $A, $fid);
check('deleting an event deletes its checklist', (int) $db->query("SELECT COUNT(*) FROM staff_calendar_checklist WHERE event_id = $fid")->fetchColumn() === 0);
check('a repeating event is saved once and shows on every matching date', count($titles($A, '2026-12-01', '2027-03-01')) >= 0 && (function () use ($db, $A, $ins, $tz) {
    $id = scal_save($db, $A, $ins(['title' => 'Series test', 'repeat' => 'weekly', 'start_date' => '2026-12-03', 'end_date' => '2026-12-03']))['id'];
    $n = count(array_filter(scal_range($db, $A, new DateTime('2026-12-01', $tz), new DateTime('2027-01-01', $tz)), fn($o) => $o['title'] === 'Series test' && $o['owner_id'] === $A['uid']));
    $rows = (int) $db->query("SELECT COUNT(*) FROM staff_calendar_events WHERE title = 'Series test' AND owner_user_id = " . $A['uid'])->fetchColumn();
    return $n === 5 && $rows >= 1;
})());

section('The details panel: badge, place, ticks, last edited, migration 114');
$runSafe('114_staff_calendar_last_edited.sql'); $runSafe('114_staff_calendar_last_edited.sql');
check('migration 114 twice: the two columns exist', scal_installed($db) && (int) $db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'staff_calendar_events' AND column_name IN ('last_edited_by','last_edited_at')")->fetchColumn() === 2);
$ev0 = ['occ_end' => '2026-11-21 12:00:00', 'occ_date' => '2026-11-21'];
$bd = fn(string $end, string $now) => scal_badge(['occ_end' => $end, 'occ_date' => substr($end, 0, 10)], new DateTime($now, $tz));
check('badge: In X days, Tomorrow, Today (before and after it ends), Yesterday, X days ago', $bd('2026-11-21 12:00:00', '2026-11-13 09:00:00') === 'In 8 days' && $bd('2026-11-21 12:00:00', '2026-11-20 20:00:00') === 'Tomorrow'
    && $bd('2026-11-21 12:00:00', '2026-11-21 09:00:00') === 'Today' && $bd('2026-11-21 12:00:00', '2026-11-21 15:00:00') === 'Today' && $bd('2026-11-21 12:00:00', '2026-11-22 08:00:00') === 'Yesterday'
    && $bd('2026-11-21 12:00:00', '2026-11-26 08:00:00') === '5 days ago');
check('place: first line and the smaller second line; a comma inside brackets does not split',
    scal_split_place('Nairobi House (Cianda House), 4th Floor, Room 406B') === ['Nairobi House (Cianda House)', '4th Floor, Room 406B'] && scal_split_place('Hall') === ['Hall', ''] && scal_split_place('A (b, c)') === ['A (b, c)', '']);
$pn = scal_panel($db, $B, scal_occurrence($db, $B, $seedId, '2026-11-21'), new DateTime('2026-11-13 09:00:00', $tz));
check('panel: full date, time in Nairobi time, place lines, In 8 days', $pn['date_full'] === 'Saturday, 21 November 2026' && $pn['time_full'] === '10:00 am to 12:00 pm · Nairobi time'
    && $pn['where1'] === 'Nairobi House (Cianda House)' && $pn['where2'] === '4th Floor, Room 406B' && $pn['badge'] === 'In 8 days' && $pn['repeat_text'] === '');
check('panel: reminder pills for the Staff meeting', $pn['reminder_times'] === ['Fri 20 Nov 8:00 am', 'Sat 21 Nov 8:00 am', 'Sat 21 Nov 9:00 am'] && $pn['reminder_note'] === 'Push, email and in the app');
check('panel: owner is a name only; "Not edited yet" for an event nobody changed', $pn['edited_text'] === 'Not edited yet' && !preg_match('/\|/', $pn['owner']) && strpos($pn['owner'], '·') === false && $pn['added_at'] !== '');
$rr = scal_save($db, $A, $ins(['title' => 'Panel test', 'repeat' => 'monthly', 'all_day' => 1, 'start_date' => '2026-12-03', 'end_date' => '2026-12-03', 'description' => 'Full text',
    'checklist' => json_encode([['t' => 'Alpha', 'd' => false], ['t' => 'Beta', 'd' => false]])]));
$pid = $rr['id'];
check('a new event: not edited yet, no editor stored', scal_find($db, $A, $pid)['editor'] === '' && $db->query("SELECT last_edited_by FROM staff_calendar_events WHERE id = $pid")->fetchColumn() === null);
$items = scal_checklist_rows($db, $pid);
check('every tutor can tick an item, it saves at once, and the owner sees it', scal_tick($db, $B, $pid, $items[0]['id'], true) === ['ok' => true, 'done' => true] && scal_checklist($db, $pid)[0]['d'] === true);
check('...and untick it again; the super admin can tick too', scal_tick($db, $B, $pid, $items[0]['id'], false)['done'] === false && scal_tick($db, $S, $pid, $items[1]['id'], true)['done'] === true);
check('ticking does not count as an edit', scal_find($db, $A, $pid)['editor'] === '');
check('an item of another event cannot be ticked through this one', scal_tick($db, $B, $seedId, $items[0]['id'], true)['ok'] === false && scal_checklist($db, $pid)[0]['d'] === false);
$priv = scal_save($db, $A, $ins(['title' => 'Private list', 'private' => 1, 'checklist' => json_encode([['t' => 'Secret', 'd' => false]])]))['id'];
$pitem = scal_checklist_rows($db, $priv)[0]['id'];
check('a private event\'s checklist: only its owner can tick it', scal_tick($db, $B, $priv, $pitem, true)['ok'] === false && scal_tick($db, $S, $priv, $pitem, true)['ok'] === false && scal_tick($db, $A, $priv, $pitem, true)['ok'] === true);
check('a private event gives no panel to anyone else', scal_occurrence($db, $B, $priv, '2026-12-01') === null);
scal_delete($db, $A, $priv);
scal_save($db, $S, $ins(['title' => 'Panel test v2', 'repeat' => 'monthly', 'all_day' => 1, 'start_date' => '2026-12-03', 'end_date' => '2026-12-03']), null, $pid);
$row = $db->query("SELECT last_edited_by, last_edited_at FROM staff_calendar_events WHERE id = $pid")->fetch(PDO::FETCH_ASSOC);
check('every edit records who and when (the super admin here)', (int) $row['last_edited_by'] === $sa && preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', (string) $row['last_edited_at']) === 1);
$pn2 = scal_panel($db, $A, scal_occurrence($db, $A, $pid, '2026-12-03'), $now);
check('panel: "Last edited by <name> · date" and the repeat sentence', preg_match('/^Last edited by Simon Mwangi · \d{1,2} \w{3} \d{4}, \d{1,2}:\d\d (am|pm)$/', $pn2['edited_text']) === 1 && $pn2['repeat_text'] === 'Repeats every month' && $pn2['time_full'] === 'All day · Nairobi time');
check('panel: the checklist keeps its ticks after an edit that leaves it alone', array_column($pn2['checklist_rows'], 'd') === [false, true]);
check('a person who is not the owner or the super admin gets can_change false and no edit values', $pn['can_change'] === false && $pn['edit'] === null);

section('Birthdays and birthday messages');
require_once __DIR__ . '/../services/StaffBirthdays.php';
$runSafe('115_staff_calendar_birthdays.sql'); $runSafe('115_staff_calendar_birthdays.sql'); $runSafe('116_staff_calendar_birthday_messages_seed.sql'); $runSafe('116_staff_calendar_birthday_messages_seed.sql');
$per = $db->query("SELECT tag, COUNT(*) FROM staff_calendar_birthday_messages WHERE status = 'approved' GROUP BY tag")->fetchAll(PDO::FETCH_KEY_PAIR);
check('migrations 115 and 116 twice: tables exist, 70 starter messages, 14 of each tag, no duplicates', sbd_installed($db) && (int) array_sum($per) >= 70 && count($per) === 5 && min($per) >= 14 && (int) $db->query('SELECT COUNT(*) FROM staff_calendar_birthday_messages WHERE id <= 70')->fetchColumn() === 70
    && (int) $db->query("SELECT COUNT(*) FROM (SELECT body FROM staff_calendar_birthday_messages WHERE id <= 70 GROUP BY body HAVING COUNT(*) > 1) d")->fetchColumn() === 0, json_encode($per));
check('at least 64 messages, and every one is short, has no template line and uses {first_name} in the text', (int) $db->query("SELECT COUNT(*) FROM staff_calendar_birthday_messages WHERE body NOT LIKE '%{first_name}%'")->fetchColumn() === 0
    && (int) $db->query("SELECT MAX(CHAR_LENGTH(body)) FROM staff_calendar_birthday_messages")->fetchColumn() <= 200);
check('the Sheng example from the brief is there', (int) $db->query("SELECT COUNT(*) FROM staff_calendar_birthday_messages WHERE body = 'Poa {first_name}! Leo ni siku yako, hakuna stress, ni keki tu na vibe. Happy Birthday!'")->fetchColumn() === 1);
check('29 February shows on 28 February in other years', sbd_date(29, 2, 2027) === '2027-02-28' && sbd_date(29, 2, 2028) === '2028-02-29' && sbd_date(18, 11, 2031) === '2031-11-18');

$bt = $mkuser('bdt', 'tutor', 'Bea', 'Wanjiku'); $mktutorrow($bt);
$bt2 = $mkuser('bdt2', 'tutor', 'Ben', 'Odhiambo'); $mktutorrow($bt2);
$BT = scal_actor($db, $bt); $BT2 = scal_actor($db, $bt2);
check('a bad day or month is refused (30 Feb, 31 April, month 13, day 0)', !sbd_save($db, $BT, ['day' => 30, 'month' => 2])['ok'] && !sbd_save($db, $BT, ['day' => 31, 'month' => 4])['ok'] && !sbd_save($db, $BT, ['day' => 1, 'month' => 13])['ok'] && !sbd_save($db, $BT, ['day' => 0, 'month' => 5])['ok']);
check('a year in the future or before 1900 or not a number is refused; 29 Feb needs a leap year if a year is given', !sbd_save($db, $BT, ['day' => 5, 'month' => 5, 'year' => 2999])['ok'] && !sbd_save($db, $BT, ['day' => 5, 'month' => 5, 'year' => '1850'])['ok']
    && !sbd_save($db, $BT, ['day' => 5, 'month' => 5, 'year' => 'abc'])['ok'] && !sbd_save($db, $BT, ['day' => 29, 'month' => 2, 'year' => 1991])['ok'] && sbd_save($db, $BT, ['day' => 29, 'month' => 2, 'year' => 1992])['ok']);
check('a tutor adds their own birthday: day and month only, age off by default', sbd_save($db, $BT, ['day' => 14, 'month' => 7])['ok'] && ($r = sbd_rows($db)[$bt]) && $r['day'] === 14 && $r['month'] === 7 && $r['year'] === null && $r['show_age'] === false);
sbd_save($db, $BT, ['day' => 14, 'month' => 7, 'year' => 1990]);
check('a year saved without ticking "show my age" never shows an age', sbd_range($db, new DateTime('2026-07-01', $tz), new DateTime('2026-08-01', $tz))[0]['age'] === null);
sbd_save($db, $BT, ['day' => 14, 'month' => 7, 'year' => 1990, 'show_age' => 1]);
$o = array_values(array_filter(sbd_range($db, new DateTime('2026-07-01', $tz), new DateTime('2026-08-01', $tz)), fn($x) => $x['user_id'] === $bt))[0];
check('only when the person chooses it does the age show', $o['age'] === 36 && $o['occ_date'] === '2026-07-14' && $o['type'] === 'birthday' && strtolower($o['color']) === '#d6457f');
sbd_save($db, $BT, ['day' => 14, 'month' => 7, 'show_age' => 1]);
check('"show my age" without a year is switched off', sbd_rows($db)[$bt]['show_age'] === false);
sbd_save($db, $BT, ['day' => 14, 'month' => 7, 'year' => 1990]);
check('a tutor cannot set another person\'s birthday (a posted user_id is ignored)', sbd_save($db, $BT, ['day' => 1, 'month' => 1, 'user_id' => $bt2])['ok'] && !isset(sbd_rows($db)[$bt2]) && sbd_rows($db)[$bt]['day'] === 1);
sbd_save($db, $BT, ['day' => 14, 'month' => 7, 'year' => 1990]);
check('the super admin can set anyone\'s, but not someone who is not a tutor or the super admin', sbd_save($db, $S, ['user_id' => $bt2, 'day' => 29, 'month' => 2])['ok'] && sbd_rows($db)[$bt2]['day'] === 29 && !sbd_save($db, $S, ['user_id' => $stu, 'day' => 1, 'month' => 1])['ok'] && !sbd_save($db, $S, ['user_id' => $logins['admin']['id'], 'day' => 1, 'month' => 1])['ok']);
$feb = fn(int $y) => array_values(array_filter(sbd_range($db, new DateTime("$y-02-01", $tz), new DateTime("$y-03-01", $tz)), fn($x) => $x['user_id'] === $bt2))[0]['occ_date'];
check('a 29 February birthday shows on 28 Feb in 2027 and on 29 Feb in 2028, every year', $feb(2027) === '2027-02-28' && $feb(2028) === '2028-02-29' && $feb(2040) === '2040-02-29' && $feb(2041) === '2041-02-28');
$yrs = array_map(fn($y) => count(array_filter(sbd_range($db, new DateTime("$y-07-01", $tz), new DateTime("$y-08-01", $tz)), fn($x) => $x['user_id'] === $bt)), [1999, 2026, 2099]);
check('a birthday shows in every year (1999, 2026, 2099)', $yrs === [1, 1, 1]);
check('a person who is no longer a tutor drops off the calendar', (function () use ($db, $bt2, $tz) { $db->exec("UPDATE tutors SET is_active = 0 WHERE user_id = $bt2"); $n = count(array_filter(sbd_range($db, new DateTime('2027-02-01', $tz), new DateTime('2027-03-01', $tz)), fn($x) => $x['user_id'] === $bt2)); $db->exec("UPDATE tutors SET is_active = 1 WHERE user_id = $bt2"); return $n === 0; })());
$up = sbd_upcoming($db, new DateTime('2026-11-13 09:00:00', $tz), 50);
$days = array_column($up, 'days'); $sorted = $days; sort($sorted);
check('"coming up" is in order, with the days to go', $days === $sorted && sbd_in_days_text(0) === 'today' && sbd_in_days_text(1) === 'tomorrow' && sbd_in_days_text(5) === 'in 5 days');
check('"coming up" shows an age only for people who chose it', (function () use ($up, $bt) { foreach ($up as $u) if ($u['user_id'] === $bt && $u['age'] !== null) return false; return true; })());
check('removing a birthday', sbd_remove($db, $BT2, [])['ok'] && sbd_remove($db, $S, ['user_id' => $bt2])['ok'] && !isset(sbd_rows($db)[$bt2]));

section('The birthday message: once, never repeated, cycles, unique key');
$active = array_map('intval', $db->query("SELECT id FROM staff_calendar_birthday_messages WHERE status = 'approved' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN));
$m1 = sbd_message_for($db, $bt, 2031); $m1b = sbd_message_for($db, $bt, 2031);
check('the first call makes the message, the next calls give the same one, and only one log row exists', $m1 && $m1['id'] === $m1b['id'] && (int) $db->query("SELECT COUNT(*) FROM staff_calendar_birthday_log WHERE user_id = $bt AND birth_year = 2031")->fetchColumn() === 1);
check('rank and total for "Message N of M"', $m1['rank'] >= 1 && $m1['total'] >= 70 && $m1['rank'] <= $m1['total']);
$dup = false; try { $db->prepare('INSERT INTO staff_calendar_birthday_log (user_id, birth_year, message_id) VALUES (?, 2031, ?)')->execute([$bt, $m1['id']]); } catch (PDOException $e) { $dup = true; }
check('the database itself refuses a second message for the same person and year (unique key)', $dup);
$db->prepare('INSERT INTO staff_calendar_birthday_log (user_id, birth_year, message_id) VALUES (?, 2032, ?)')->execute([$bt, $active[3]]);
check('if someone else made it a moment ago, that one is used (race)', sbd_message_for($db, $bt, 2032)['id'] === $active[3]);
check('{first_name} is replaced', sbd_text('Poa {first_name}! Happy Birthday', 'Bea') === 'Poa Bea! Happy Birthday');
// cycle with a small pool: switch all off except 3, restore after
$db->exec("UPDATE staff_calendar_birthday_messages SET is_active = 0 WHERE status = 'approved'");
$three = array_slice($active, 0, 3); $db->exec('UPDATE staff_calendar_birthday_messages SET is_active = 1 WHERE id IN (' . implode(',', $three) . ')');
$cy = $mkuser('bdcy', 'tutor', 'Cy', 'Cle'); $mktutorrow($cy);
$seen = []; foreach ([2041, 2042, 2043] as $yy) $seen[] = sbd_message_for($db, $cy, $yy)['id'];
check('never repeated for a person until all messages are used (3 years, 3 different)', count(array_unique($seen)) === 3 && !array_diff($seen, $three));
$seen2 = []; foreach ([2044, 2045, 2046] as $yy) $seen2[] = sbd_message_for($db, $cy, $yy)['id'];
check('once all were used the cycle starts again, and again covers every message once', count(array_unique($seen2)) === 3 && !array_diff($seen2, $three));
$db->exec("UPDATE staff_calendar_birthday_messages SET is_active = 0 WHERE status = 'approved'");
check('with no message switched on there is simply no message (and no log row)', sbd_message_for($db, $cy, 2050) === null && (int) $db->query("SELECT COUNT(*) FROM staff_calendar_birthday_log WHERE user_id = $cy AND birth_year = 2050")->fetchColumn() === 0);
$db->exec('UPDATE staff_calendar_birthday_messages SET is_active = 1 WHERE id IN (' . implode(',', $active) . ')');

section('Managing the messages');
$cnt0 = (int) $db->query('SELECT COUNT(*) FROM staff_calendar_birthday_messages')->fetchColumn();
check('a tutor cannot add, edit, switch off, approve or reject', !sbd_msg_add($db, $A, ['body' => 'x {first_name}', 'tag' => 'Funny'])['ok'] && !sbd_msg_edit($db, $A, $active[0], ['body' => 'x', 'tag' => 'Funny'])['ok']
    && !sbd_msg_active($db, $A, $active[0], false)['ok'] && !sbd_msg_review($db, $A, $active[0], true)['ok'] && !sbd_msg_review($db, $A, $active[0], false)['ok']
    && (int) $db->query('SELECT COUNT(*) FROM staff_calendar_birthday_messages')->fetchColumn() === $cnt0 && (int) $db->query("SELECT is_active FROM staff_calendar_birthday_messages WHERE id = {$active[0]}")->fetchColumn() === 1);
check('the super admin adds a message (approved, on, no suggester)', sbd_msg_add($db, $S, ['body' => 'Test message for {first_name}, happy birthday!', 'tag' => 'Tech'])['ok']);
$newId = (int) $db->query("SELECT id FROM staff_calendar_birthday_messages WHERE body LIKE 'Test message for%' ORDER BY id DESC LIMIT 1")->fetchColumn();
check('...a bad tag, an empty or a 401-letter text is refused', !sbd_msg_add($db, $S, ['body' => 'x', 'tag' => 'Rude'])['ok'] && !sbd_msg_add($db, $S, ['body' => '  ', 'tag' => 'Tech'])['ok'] && !sbd_msg_add($db, $S, ['body' => str_repeat('x', 401), 'tag' => 'Tech'])['ok']);
check('the super admin edits it', sbd_msg_edit($db, $S, $newId, ['body' => 'Edited for {first_name}!', 'tag' => 'Funny'])['ok'] && $db->query("SELECT CONCAT(tag, body) FROM staff_calendar_birthday_messages WHERE id = $newId")->fetchColumn() === 'FunnyEdited for {first_name}!' && !sbd_msg_edit($db, $S, 99999999, ['body' => 'x', 'tag' => 'Funny'])['ok']);
$logged = sbd_message_for($db, $bt, 2060)['id'];
sbd_msg_active($db, $S, $logged, false);
check('switching off keeps the row (nothing is deleted) and the log still reads it', (int) $db->query("SELECT COUNT(*) FROM staff_calendar_birthday_messages WHERE id = $logged")->fetchColumn() === 1 && sbd_message_for($db, $bt, 2060)['id'] === $logged && !in_array($logged, sbd_pool($db), true));
sbd_msg_active($db, $S, $logged, true);
check('...and switching on puts it back in the pool', in_array($logged, sbd_pool($db), true));
check('a tutor suggests a message: it waits as pending, switched off, with their name as the suggester', sbd_suggest($db, $A, ['body' => 'Suggested for {first_name}.', 'tag' => 'Sheng'])['ok']);
$sug = (int) $db->query("SELECT id FROM staff_calendar_birthday_messages WHERE body = 'Suggested for {first_name}.' ORDER BY id DESC LIMIT 1")->fetchColumn();
$row = $db->query("SELECT status, is_active, suggested_by FROM staff_calendar_birthday_messages WHERE id = $sug")->fetch(PDO::FETCH_ASSOC);
check('...pending, not in use, suggested_by is the tutor', $row['status'] === 'pending' && (int) $row['is_active'] === 0 && (int) $row['suggested_by'] === $t1 && !in_array($sug, sbd_pool($db), true));
$lst = array_values(array_filter(sbd_msg_list($db, 'Sheng', 'pending'), fn($m) => $m['id'] === $sug))[0];
check('the list shows "suggested by" as a name only', $lst['by'] === 'Tina Otieno');
check('a pending message cannot be switched on before it is approved', sbd_msg_active($db, $S, $sug, true)['ok'] && !in_array($sug, sbd_pool($db), true));
check('the super admin approves it: it is now in use; approving twice is refused', sbd_msg_review($db, $S, $sug, true)['ok'] && in_array($sug, sbd_pool($db), true) && !sbd_msg_review($db, $S, $sug, true)['ok']);
sbd_suggest($db, $A, ['body' => 'Another suggestion {first_name}.', 'tag' => 'Tech']);
$sug2 = (int) $db->query("SELECT id FROM staff_calendar_birthday_messages WHERE body = 'Another suggestion {first_name}.' ORDER BY id DESC LIMIT 1")->fetchColumn();
check('rejecting keeps it on the list as rejected and never in use', sbd_msg_review($db, $S, $sug2, false)['ok'] && !in_array($sug2, sbd_pool($db), true) && count(sbd_msg_list($db, '', 'rejected')) >= 1);
$pend = $mkuser('bdsug', 'tutor', 'Sue', 'Gester'); $mktutorrow($pend); $SU = scal_actor($db, $pend);
for ($i = 0; $i < 5; $i++) sbd_suggest($db, $SU, ['body' => "Flood $i {first_name}", 'tag' => 'Funny']);
check('a tutor can have 5 suggestions waiting, not more', !sbd_suggest($db, $SU, ['body' => 'Flood 6 {first_name}', 'tag' => 'Funny'])['ok']);
$db->exec("DELETE FROM staff_calendar_birthday_messages WHERE body LIKE 'Flood %'");
check('the counts for the messages page', sbd_msg_counts($db)['all'] >= 70 && array_sum(sbd_msg_counts($db)['tags']) === sbd_msg_counts($db)['all']);
// who sees the "Message N of M" line
$today = scal_now();
sbd_save($db, $BT, ['day' => (int) $today->format('j'), 'month' => (int) $today->format('n')]);
$bds = sbd_today($db, $BT, $today); $mine = array_values(array_filter($bds, fn($b) => $b['user_id'] === $bt))[0];
$seenBy = fn(array $viewer) => array_values(array_filter(sbd_today($db, $viewer, $today), fn($b) => $b['user_id'] === $bt))[0]['sees_count'];
check('today\'s birthday: the person and the super admin see the message count line, other tutors do not', $mine['own'] && $mine['sees_count'] && $seenBy($S) === true && $seenBy($BT2) === false && $mine['message'] !== '' && strpos($mine['message'], '{first_name}') === false && strpos($mine['message'], 'Bea') !== false);
check('...and the viewer\'s own birthday comes first', sbd_today($db, $BT, $today)[0]['user_id'] === $bt);
sbd_remove($db, $BT, []);

section('Birthday gifts: amount, exclusions, the list, the taps');
require_once __DIR__ . '/../services/StaffGifts.php';
$runSafe('117_staff_calendar_gifts.sql'); $runSafe('117_staff_calendar_gifts.sql');
$db->exec("UPDATE staff_calendar_settings SET setting_value = '1500' WHERE setting_key = 'gift_amount'");
check('migration 117 twice: one gift_amount setting, KES 1,500 by default', (int) $db->query("SELECT COUNT(*) FROM staff_calendar_settings WHERE setting_key = 'gift_amount'")->fetchColumn() === 1 && sg_installed($db) && sg_amount($db) === 1500);
check('only the super admin can change the amount', !sg_set_amount($db, $A, 1800)['ok'] && sg_amount($db) === 1500 && sg_set_amount($db, $S, '1,800')['ok'] && sg_amount($db) === 1800);
check('a bad amount (50, abc, 2,000,000, empty) is refused', !sg_set_amount($db, $S, 50)['ok'] && !sg_set_amount($db, $S, 'abc')['ok'] && !sg_set_amount($db, $S, 2000000)['ok'] && !sg_set_amount($db, $S, '')['ok'] && sg_amount($db) === 1800);
sg_set_amount($db, $S, 1500);

$gb = $mkuser('gb', 'tutor', 'Gina', 'Birthday'); $mktutorrow($gb);
$g1 = $mkuser('g1', 'tutor', 'Gus', 'One' . $run); $mktutorrow($g1);
$g2 = $mkuser('g2', 'tutor', 'Gwen', 'Two' . $run); $mktutorrow($g2);
$g3 = $mkuser('g3', 'tutor', 'Gil', 'Leave' . $run); $mktutorrow($g3);
$gl = $mkuser('gl', 'tutor', 'Gus', 'Late' . $run); $mktutorrow($gl);
$GB = scal_actor($db, $gb); $G1 = scal_actor($db, $g1); $G2 = scal_actor($db, $g2); $G3 = scal_actor($db, $g3); $GL = scal_actor($db, $gl);
check('only the super admin can exclude someone (with a note); an unknown person or a 121-letter note is refused', !sg_set_excluded($db, $A, $g3, true, 'on leave')['ok'] && !sg_set_excluded($db, $G3, $g3, true, 'x')['ok']
    && sg_set_excluded($db, $S, $g3, true, 'on leave')['ok'] && sg_exclusions($db)[$g3] === 'on leave' && !sg_set_excluded($db, $S, 99999999, true, '')['ok'] && !sg_set_excluded($db, $S, $g3, true, str_repeat('x', 121))['ok']);
check('a student or an admin cannot be put on a gift list or excluded', !sg_set_excluded($db, $S, $stu, true, '')['ok'] && !isset(sg_people($db)[$stu]) && !isset(sg_people($db)[$logins['admin']['id']]));
$day = '2026-11-13'; $at = fn(string $d) => new DateTime($d . ' 10:00:00', $tz);
$expected = count(sg_people($db)) - 1;
$lid = sg_ensure_list($db, $gb, $day);
$items = $db->query("SELECT giver_user_id, status, excluded_note FROM staff_calendar_gift_items WHERE list_id = $lid")->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
check('the list holds every tutor and the super admin except the birthday person', count($items) === $expected && !isset($items[$gb]) && isset($items[$g1]) && isset($items[$sa]));
check('...each "Not yet", and the excluded person is on it as "Excluded" with the note', $items[$g1]['status'] === 'not_yet' && $items[$g3]['status'] === 'excluded' && $items[$g3]['excluded_note'] === 'on leave');
check('the amount of that moment is saved on the list', (int) $db->query("SELECT amount FROM staff_calendar_gift_lists WHERE id = $lid")->fetchColumn() === 1500);
check('a second call (anyone opening the calendar again) gives the same list and no new rows', sg_ensure_list($db, $gb, $day) === $lid && (int) $db->query("SELECT COUNT(*) FROM staff_calendar_gift_items WHERE list_id = $lid")->fetchColumn() === $expected);
$dupL = false; try { $db->prepare('INSERT INTO staff_calendar_gift_lists (user_id, gift_year, birthday_date, amount) VALUES (?, 2026, ?, 1500)')->execute([$gb, $day]); } catch (PDOException $e2) { $dupL = true; }
check('the database refuses a second list for the same person and year (unique key)', $dupL);
sg_set_amount($db, $S, 2000); sg_set_excluded($db, $S, $g3, false, '');
check('a new amount and a removed exclusion do not change a list that is already made', (int) $db->query("SELECT amount FROM staff_calendar_gift_lists WHERE id = $lid")->fetchColumn() === 1500 && $db->query("SELECT status FROM staff_calendar_gift_items WHERE list_id = $lid AND giver_user_id = $g3")->fetchColumn() === 'excluded');
$lid2 = sg_ensure_list($db, $g2, $day);
check('...but a new birthday uses the new amount and the exclusions of that moment', (int) $db->query("SELECT amount FROM staff_calendar_gift_lists WHERE id = $lid2")->fetchColumn() === 2000 && $db->query("SELECT status FROM staff_calendar_gift_items WHERE list_id = $lid2 AND giver_user_id = $g3")->fetchColumn() === 'not_yet');
sg_set_amount($db, $S, 1500);
$lst = sg_list_row($db, $lid);
check('open for 30 days after the birthday, then read-only history', sg_is_open($lst, $at('2026-11-13')) && sg_is_open($lst, $at('2026-12-13')) && !sg_is_open($lst, $at('2026-12-14')) && !sg_is_open($lst, $at('2026-11-12')));

section('Gifts: each person changes only their own row');
$now = $at('2026-11-13');
check('"I have sent": Not yet becomes Says sent, for yourself only', sg_send($db, $G1, $lid, $now)['ok'] && $db->query("SELECT status FROM staff_calendar_gift_items WHERE list_id = $lid AND giver_user_id = $g1")->fetchColumn() === 'says_sent'
    && $db->query("SELECT status FROM staff_calendar_gift_items WHERE list_id = $lid AND giver_user_id = $g2")->fetchColumn() === 'not_yet');
check('...tapping again changes nothing', !empty(sg_send($db, $G1, $lid, $now)['unchanged']));
check('it can be undone until the birthday person confirms', sg_undo($db, $G1, $lid, $now)['ok'] && $db->query("SELECT status FROM staff_calendar_gift_items WHERE list_id = $lid AND giver_user_id = $g1")->fetchColumn() === 'not_yet');
check('the birthday person does not send to themselves; an excluded person and someone not on the list are refused', !sg_send($db, $GB, $lid, $now)['ok'] && !sg_send($db, $G3, $lid, $now)['ok'] && !sg_send($db, ['uid' => $stu, 'role' => 'tutor', 'name' => 'x', 'first' => 'x'], $lid, $now)['ok']);
sg_send($db, $G1, $lid, $now); sg_send($db, $G2, $lid, $now);
check('a tutor cannot confirm, not even their own row; a stranger cannot confirm', !sg_confirm($db, $G1, $lid, $g1, $now)['ok'] && !sg_confirm($db, $G2, $lid, $g1, $now)['ok'] && !sg_confirm($db, $A, $lid, $g1, $now)['ok']);
check('the birthday person confirms a Says sent row: Received, recorded with who did it', sg_confirm($db, $GB, $lid, $g1, $now)['ok'] && $db->query("SELECT CONCAT(status, ':', confirmed_by) FROM staff_calendar_gift_items WHERE list_id = $lid AND giver_user_id = $g1")->fetchColumn() === "received:$gb");
check('the super admin can confirm on their behalf, recorded with the super admin', sg_confirm($db, $S, $lid, $g2, $now)['ok'] && (int) $db->query("SELECT confirmed_by FROM staff_calendar_gift_items WHERE list_id = $lid AND giver_user_id = $g2")->fetchColumn() === $sa);
check('once received it cannot be undone, and a Not yet or Excluded row cannot be confirmed', !sg_undo($db, $G1, $lid, $now)['ok'] && !sg_confirm($db, $GB, $lid, $sa, $now)['ok'] && !sg_confirm($db, $GB, $lid, $g3, $now)['ok'] && !sg_confirm($db, $GB, $lid, 99999999, $now)['ok']);
check('sending again after Received changes nothing', !empty(sg_send($db, $G1, $lid, $now)['unchanged']) && $db->query("SELECT status FROM staff_calendar_gift_items WHERE list_id = $lid AND giver_user_id = $g1")->fetchColumn() === 'received');

section('Gifts: who sees the list, the counts, the reminder');
check('no other tutor can see the list, nor a stranger', sg_list_view($db, $G1, $lid, $now) === null && sg_list_view($db, $G2, $lid, $now) === null && sg_list_view($db, $A, $lid, $now) === null);
$v = sg_list_view($db, $GB, $lid, $now); $vs = sg_list_view($db, $S, $lid, $now);
check('the birthday person and the super admin see it, with the counts', $v && $vs && $v['received'] === 2 && $v['expected'] === $expected - 1 && $v['amount'] === 1500 && $v['received_kes'] === 3000 && $v['expected_kes'] === ($expected - 1) * 1500 && $v['own'] === true && $vs['own'] === false);
$byId = array_column($v['items'], 'status', 'giver_id'); $roleOf = array_column($v['items'], 'role', 'giver_id');
check('rows carry name, role, picture or initials and a status; the excluded one shows with the note', $byId[$g1] === 'received' && $byId[$g3] === 'excluded' && $roleOf[$g3] === 'Tutor · on leave'
    && $roleOf[$sa] === 'Super admin' && $roleOf[$g1] === 'Tutor' && $v['items'][0]['initials'] !== '' && $v['items'][0]['status'] === 'received');
$G4u = $mkuser('g4', 'tutor', 'Gro', 'Four'); $mktutorrow($G4u);
$lid4 = sg_ensure_list($db, $gb, '2027-11-13');
$db->prepare("UPDATE staff_calendar_gift_items SET status = 'says_sent' WHERE list_id = ? AND giver_user_id = ?")->execute([$lid4, $g1]);
check('"Says sent" rows are on the list for the birthday person to confirm', array_column(sg_list_view($db, $GB, $lid4, $at('2027-11-13'))['items'], 'status', 'giver_id')[$g1] === 'says_sent');
$cards = sg_for_viewer($db, $G2, $now);
check('a giver gets their own card only (never the list of someone else), the birthday person gets the panel and no card', !in_array($lid, array_column($cards['panels'], 'id'), true) && in_array($lid, array_column($cards['cards'], 'list_id'), true) === false && sg_for_viewer($db, $GB, $now)['panels'] !== [] && !in_array($lid, array_column(sg_for_viewer($db, $GB, $now)['cards'], 'list_id'), true));
$g5 = $mkuser('g5', 'tutor', 'Gem', 'Five'); $mktutorrow($g5); $G5 = scal_actor($db, $g5);
$lid5 = sg_ensure_list($db, $gb, '2026-11-14');   // not the same year as lid? (same year 2026: the unique key returns the existing list)
check('a second list for the same year is never made (the same one comes back)', $lid5 === $lid);
$cardsG = sg_for_viewer($db, $G1, $now);
check('a giver who is "Received" no longer gets the card; the super admin sees panels', !in_array($lid, array_column($cardsG['cards'], 'list_id'), true) && count(sg_for_viewer($db, $S, $now)['panels']) >= 1);
$notYetUser = $mkuser('g6', 'tutor', 'Gus', 'Six'); $mktutorrow($notYetUser); $G6 = scal_actor($db, $notYetUser);
$lid6 = sg_ensure_list($db, $notYetUser, '2026-11-13');
$cardsG2 = sg_for_viewer($db, $G2, $now);
check('a "Not yet" giver gets the card with the amount and the birthday person\'s first name', ($c = array_values(array_filter($cardsG2['cards'], fn($x) => $x['list_id'] === $lid6))[0] ?? null) && $c['first'] === 'Gus' && $c['amount'] === 1500 && $c['status'] === 'not_yet');
check('who may remind: not a giver; the birthday person and the super admin may', !sg_remind($db, $G2, $lid, $now)['ok'] && !sg_remind($db, $A, $lid, $now)['ok']);
$r1 = sg_remind($db, $GB, $lid, $now);
$notYetCount = (int) $db->query("SELECT COUNT(*) FROM staff_calendar_gift_items WHERE list_id = $lid AND status = 'not_yet'")->fetchColumn();
check('the reminder goes to everyone whose status is Not yet (and only them), as gentle in-app notes', $r1['ok'] && $r1['sent'] === $notYetCount && (int) $db->query("SELECT COUNT(*) FROM staff_calendar_gift_notes WHERE list_id = $lid")->fetchColumn() === $notYetCount
    && $db->query("SELECT message FROM staff_calendar_gift_notes WHERE list_id = $lid AND to_user_id = $sa")->fetchColumn() === 'Gina’s birthday gift is still waiting'
    && (int) $db->query("SELECT COUNT(*) FROM staff_calendar_gift_notes WHERE list_id = $lid AND to_user_id IN ($g1, $g2, $g3)")->fetchColumn() === 0);
$r2 = sg_remind($db, $S, $lid, $now);
check('a second tap the same day says "Already sent today" and does nothing', !$r2['ok'] && !empty($r2['already']) && $r2['error'] === 'Already sent today' && (int) $db->query("SELECT COUNT(*) FROM staff_calendar_gift_notes WHERE list_id = $lid")->fetchColumn() === $notYetCount);
check('the next day it can be used again', sg_remind($db, $S, $lid, $at('2026-11-14'))['ok']);
$noteFor = (int) $db->query("SELECT id FROM staff_calendar_gift_notes WHERE list_id = $lid AND to_user_id = $sa ORDER BY id DESC LIMIT 1")->fetchColumn();
check('the note is shown to the person while their status is Not yet', count(sg_for_viewer($db, $S, $at('2026-11-14'))['notes']) >= 1);
sg_note_dismiss($db, $A, $noteFor);
check('only the person can dismiss their note (another tutor cannot)', (int) $db->query("SELECT COUNT(*) FROM staff_calendar_gift_notes WHERE id = $noteFor AND dismissed_at IS NULL")->fetchColumn() === 1);
sg_note_dismiss($db, $S, $noteFor);
check('...and they can', (int) $db->query("SELECT COUNT(*) FROM staff_calendar_gift_notes WHERE id = $noteFor AND dismissed_at IS NULL")->fetchColumn() === 0);
sg_send($db, $S, $lid, $at('2026-11-14'));
check('once someone has sent, their reminder notes stop showing', sg_for_viewer($db, $S, $at('2026-11-14'))['notes'] === []);
$late = $at('2026-12-14');
check('after 30 days the list is read-only: send, undo, confirm and remind are refused', !sg_send($db, $GL, $lid, $late)['ok'] && !sg_undo($db, $G1, $lid, $late)['ok'] && !sg_confirm($db, $GB, $lid, $gl, $late)['ok'] && !sg_remind($db, $GB, $lid, $late)['ok']);
check('...and late senders can still tap on day 30', sg_send($db, $GL, $lid, $at('2026-12-13'))['ok']);
check('and the giver cards and panels stop after the 30 days', sg_for_viewer($db, $GB, $late)['panels'] === [] && !in_array($lid, array_column(sg_for_viewer($db, $G2, $late)['cards'], 'list_id'), true));
$hist = sg_history($db, $at('2026-12-14'));
$h = array_values(array_filter($hist, fn($x) => $x['id'] === $lid))[0];
check('history: each past birthday with received / expected and KES totals, names only of those who had not sent, and read-only after 30 days', $h['person'] === 'Gina Birthday' && $h['received'] === 2 && $h['expected'] === $expected - 1 && $h['received_kes'] === 3000
    && !$h['open'] && in_array('Gwen Two' . $run, $h['not_yet'], true) === false && in_array('Gus Late' . $run, $h['not_yet'], true) === false);

section('Projects: tasks, progress, who can do what, pending notices');
require_once __DIR__ . '/../services/StaffProjects.php';
$runSafe('118_staff_calendar_projects.sql'); $runSafe('118_staff_calendar_projects.sql');
check('migration 118 twice: the task columns and the notices table are there', spj_installed($db) && (int) $db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'staff_calendar_checklist' AND column_name IN ('assignee_user_id','start_date','due_date','status')")->fetchColumn() === 4);
$pjIn = fn(array $a = []) => array_merge($ins(['title' => 'Rollout ' . $run, 'type' => 'project', 'start_date' => '2026-11-16', 'end_date' => '2026-11-20']), $a);
$tk = fn(string $t, int $who, string $due, string $status = 'todo', string $start = '', int $id = 0) => ['id' => $id, 't' => $t, 'who' => $who, 'start' => $start, 'due' => $due, 'status' => $status];
check('a project needs an end date', !scal_save($db, $A, $pjIn(['end_date' => '']))['ok']);
$tasks8 = [$tk('Run migrations', $t1, '2026-11-16', 'done', '2026-11-16'), $tk('Add the cron line', $t1, '2026-11-16', 'done'), $tk('Delete demo files', $t1, '2026-11-16', 'done'), $tk('Test on staging', $t2, '2026-11-17', 'done', '2026-11-17'),
           $tk('Add first courses', $t2, '2026-11-17', 'done'), $tk('Train the tutors', $t2, '2026-11-18', 'in_progress', '2026-11-18'), $tk('Announce to students', $sa, '2026-11-20'), $tk('Collect feedback', $sa, '2026-11-20')];
$rp = scal_save($db, $A, $pjIn(['tasks' => json_encode($tasks8), 'private' => 1, 'repeat' => 'weekly', 'all_day' => 0, 'start_time' => '09:00', 'end_time' => '10:00', 'checklist' => json_encode([['t' => 'ignored', 'd' => 0]])]));
$pid = $rp['id'];
$row = $db->query("SELECT * FROM staff_calendar_events WHERE id = $pid")->fetch(PDO::FETCH_ASSOC);
check('a project is always all day, never repeats, never private (even if the form said so), and the owner is the person logged in', $rp['ok'] && (int) $row['all_day'] === 1 && $row['repeat_rule'] === 'none' && (int) $row['is_private'] === 0 && $row['start_at'] === '2026-11-16 00:00:00' && $row['end_at'] === '2026-11-20 23:59:59' && (int) $row['owner_user_id'] === $t1);
check('...everyone who can use the calendar can open it', spj_event($db, $B, $pid) !== null && spj_event($db, $S, $pid) !== null);
check('its 8 tasks were saved (the simple checklist of the form is ignored for a project)', count(spj_tasks($db, $pid)) === 8);
$today = '2026-11-13';
$s8 = spj_stats(spj_tasks($db, $pid), $today);
check('progress counts Done tasks only: 5 of 8 = 62%, On track', $s8['total'] === 8 && $s8['done'] === 5 && $s8['percent'] === 62 && $s8['state'] === 'On track');
check('"Behind" once an unfinished task is past its due date; an unfinished one due today is not behind', spj_stats(spj_tasks($db, $pid), '2026-11-19')['state'] === 'Behind' && spj_stats(spj_tasks($db, $pid), '2026-11-18')['state'] === 'On track' && spj_stats(spj_tasks($db, $pid), '2026-11-19')['behind'] === 1 && spj_stats(spj_tasks($db, $pid), '2026-11-21')['behind'] === 3);
check('"Done" when every task is done; no tasks is 0% On track', spj_stats(array_map(fn($t) => ['status' => 'done', 'due' => '2026-01-01'] , $tasks8), $today)['state'] === 'Done' && spj_stats([], $today) === ['total' => 0, 'done' => 0, 'percent' => 0, 'behind' => 0, 'state' => 'On track']);
check('bulk progress for several projects', spj_progress_bulk($db, [$pid], $today)[$pid]['percent'] === 62);

section('Projects: checking a task');
$base = fn(array $t) => scal_save($db, $A, $pjIn(['title' => 'Bad ' . $run, 'tasks' => json_encode([$t])]));
check('a task needs a title; a title over 200 letters is refused; blank rows are dropped', $base($tk('x', $t1, ''))['ok'] && !$base($tk(str_repeat('x', 201), $t1, ''))['ok']);
check('a person must be a tutor or the super admin (not a student, not an admin)', !$base($tk('x', $stu, ''))['ok'] && !$base($tk('x', $logins['admin']['id'], ''))['ok'] && !$base($tk('x', 99999999, ''))['ok']);
check('dates must be real, and a task cannot start after it is due', !$base($tk('x', $t1, '2026-02-30'))['ok'] && !$base($tk('x', $t1, '2026-11-16', 'todo', '2026-11-17'))['ok'] && $base($tk('x', 0, '', 'todo', ''))['ok']);
check('an unknown status becomes To do; 101 tasks are refused', (function () use ($db, $A, $pjIn, $tk) { $r = scal_save($db, $A, $pjIn(['title' => 'St ' . 'x', 'tasks' => json_encode([$tk('x', 0, '', 'weird')])])); return $r['ok'] && spj_tasks($db, $r['id'])[0]['status'] === 'todo'; })()
    && !scal_save($db, $A, $pjIn(['tasks' => json_encode(array_fill(0, 101, $tk('x', 0, '')))]))['ok']);

section('Projects: pending notices (nothing is sent)');
$notices = fn(int $task = 0) => $db->query('SELECT kind, to_user_id, send_on, sent_at FROM staff_calendar_task_notices WHERE ' . ($task ? "task_id = $task" : "event_id = $pid") . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$tid = array_column(spj_tasks($db, $pid), 'id', 't');
$nAssigned = (int) $db->query("SELECT COUNT(*) FROM staff_calendar_task_notices WHERE event_id = $pid AND kind = 'assigned'")->fetchColumn();
$nDue = (int) $db->query("SELECT COUNT(*) FROM staff_calendar_task_notices WHERE event_id = $pid AND kind = 'due_before'")->fetchColumn();
check('each task given to someone has a pending "assigned" notice (8), and unfinished tasks with a due date have one for the day before (3); done tasks have none', $nAssigned === 8 && $nDue === 3 && (int) $db->query("SELECT COUNT(*) FROM staff_calendar_task_notices WHERE event_id = $pid AND sent_at IS NOT NULL")->fetchColumn() === 0);
$n1 = $notices($tid['Train the tutors']);
check('the day-before notice is for the right person on the right day', array_values(array_filter($n1, fn($n) => $n['kind'] === 'due_before'))[0]['send_on'] === '2026-11-17' && (int) array_values(array_filter($n1, fn($n) => $n['kind'] === 'due_before'))[0]['to_user_id'] === $t2);
check('a task with nobody given to it records no notice', (function () use ($db, $A, $pjIn, $tk) { $r = scal_save($db, $A, $pjIn(['title' => 'Nobody', 'tasks' => json_encode([$tk('x', 0, '2026-11-18')])])); return (int) $db->query('SELECT COUNT(*) FROM staff_calendar_task_notices WHERE event_id = ' . (int) $r['id'])->fetchColumn() === 0; })());
// edit through the form: change the person of one task, mark another done, delete one, add one. The tasks keep their ids.
$cur = spj_tasks($db, $pid);
$edit = array_map(fn($t) => $tk($t['t'], $t['who'], $t['due'], $t['status'], $t['start'], $t['id']), $cur);
$edit = array_values(array_filter($edit, fn($t) => $t['t'] !== 'Collect feedback'));
foreach ($edit as &$t) { if ($t['t'] === 'Train the tutors') { $t['who'] = $sa; } if ($t['t'] === 'Announce to students') { $t['status'] = 'done'; } }
unset($t);
$edit[] = $tk('Brand new task', $t2, '2026-11-19');
scal_save($db, $A, $pjIn(['tasks' => json_encode($edit)]), null, $pid);
$tid2 = array_column(spj_tasks($db, $pid), 'id', 't');
check('editing keeps the ids of the tasks that stay, adds the new one and deletes the removed one (with its notices)', $tid2['Train the tutors'] === $tid['Train the tutors'] && isset($tid2['Brand new task']) && !isset($tid2['Collect feedback']) && count($notices($tid['Collect feedback'])) === 0);
$n2 = $notices($tid['Train the tutors']);
check('a changed person: the old person\'s waiting notices are gone, the new person gets "assigned" and the day-before notice', count(array_filter($n2, fn($n) => (int) $n['to_user_id'] === $t2)) === 0 && count(array_filter($n2, fn($n) => (int) $n['to_user_id'] === $sa && $n['kind'] === 'assigned')) === 1 && count(array_filter($n2, fn($n) => (int) $n['to_user_id'] === $sa && $n['kind'] === 'due_before')) === 1);
check('a task set to Done loses its day-before notice', count(array_filter($notices($tid['Announce to students']), fn($n) => $n['kind'] === 'due_before')) === 0);
check('the new task has both notices', count($notices($tid2['Brand new task'])) === 2);
$edit = array_map(fn($t) => $tk($t['t'], $t['who'], $t['due'], $t['status'], $t['start'], $t['id']), spj_tasks($db, $pid));   // from now on the tasks are sent with their ids, as the form does
$before = count($notices($tid['Train the tutors']));
scal_save($db, $A, $pjIn(['tasks' => json_encode($edit)]), null, $pid);
check('saving again without changes makes no new notices (no double "assigned")', count($notices($tid['Train the tutors'])) === $before);
$db->exec("UPDATE staff_calendar_task_notices SET sent_at = NOW() WHERE task_id = {$tid['Test on staging']} AND kind = 'assigned'");
check('notices that were already sent are never removed or edited by a later change', (function () use ($db, $tid, $tk, $edit, $A, $pjIn, $pid) { $e2 = $edit; foreach ($e2 as &$t) if ($t['t'] === 'Test on staging') $t['status'] = 'done'; unset($t); scal_save($db, $A, $pjIn(['tasks' => json_encode($e2)]), null, $pid);
    return (int) $db->query("SELECT COUNT(*) FROM staff_calendar_task_notices WHERE task_id = {$tid['Test on staging']} AND sent_at IS NOT NULL")->fetchColumn() === 1; })());

section('Projects: who can do what');
$ptid = $tid2['Brand new task'];   // given to tutor 2
check('the owner and the super admin can add a task and give it to anyone', spj_task_save($db, $A, $pid, ['title' => 'Owner added', 'who' => $t2, 'due' => '2026-11-19', 'status' => 'todo'])['ok'] && spj_task_save($db, $S, $pid, ['title' => 'Super added', 'who' => $t1, 'start' => '2026-11-17', 'due' => '2026-11-19'])['ok']);
check('another tutor (not the owner) cannot add, edit or delete tasks, even a task given to them', !spj_task_save($db, $B, $pid, ['title' => 'Hack', 'who' => $t2])['ok'] && !spj_task_save($db, $B, $pid, ['task' => $ptid, 'title' => 'Renamed', 'who' => $t2])['ok'] && !spj_task_delete($db, $B, $pid, $ptid)['ok']
    && !in_array('Hack', array_column(spj_tasks($db, $pid), 't'), true));
check('the person given a task can change its status, but cannot change the title, person or dates', spj_task_status($db, $B, $pid, $ptid, 'in_progress')['ok'] && array_column(spj_tasks($db, $pid), 'status', 'id')[$ptid] === 'in_progress' && !spj_task_save($db, $B, $pid, ['task' => $ptid, 'title' => 'Renamed', 'who' => $t2, 'due' => '2026-11-19'])['ok']);
check('...and can tick it (Done) through the checklist tick as well, which sets the status', scal_tick($db, $B, $pid, $ptid, true)['ok'] && array_column(spj_tasks($db, $pid), 'status', 'id')[$ptid] === 'done' && (int) $db->query("SELECT is_done FROM staff_calendar_checklist WHERE id = $ptid")->fetchColumn() === 1);
check('a tutor who is not given the task cannot tick it or change its status', !scal_tick($db, $GL, $pid, $tid['Test on staging'], false)['ok'] && !spj_task_status($db, $GL, $pid, $tid['Test on staging'], 'todo')['ok'] && array_column(spj_tasks($db, $pid), 'status', 'id')[$tid['Test on staging']] === 'done');
check('the owner and the super admin can change the status of any task; a bad status is refused', spj_task_status($db, $A, $pid, $tid['Train the tutors'], 'done')['ok'] && spj_task_status($db, $S, $pid, $tid['Train the tutors'], 'in_progress')['ok'] && !spj_task_status($db, $S, $pid, $tid['Train the tutors'], 'finished')['ok']);
check('ticking undone sets To do and brings back the day-before notice', scal_tick($db, $B, $pid, $ptid, false)['ok'] && array_column(spj_tasks($db, $pid), 'status', 'id')[$ptid] === 'todo' && count(array_filter($notices($ptid), fn($n) => $n['kind'] === 'due_before')) === 1);
check('a task of another event cannot be changed through this one', !spj_task_status($db, $S, $seedId, $ptid, 'done')['ok'] && !spj_task_delete($db, $S, $seedId, $ptid)['ok']);
$delId = spj_task_save($db, $A, $pid, ['title' => 'Temp', 'who' => $t2, 'due' => '2026-11-19'])['task'];
check('the owner deletes a task: its notices go too', spj_task_delete($db, $A, $pid, $delId)['ok'] && count($notices($delId)) === 0 && !spj_task_delete($db, $A, $pid, $delId)['ok']);
check('a normal event keeps its simple checklist: a tick by anyone, no person, no dates, status follows', (function () use ($db, $A, $B, $ins) { $id = scal_save($db, $A, $ins(['title' => 'Plain list ' . substr(md5((string) microtime()), 0, 5), 'checklist' => json_encode([['t' => 'One', 'd' => false]])]))['id']; $it = scal_checklist_rows($db, $id)[0]['id'];
    $r = scal_tick($db, $B, $id, $it, true); $row = $db->query("SELECT status, assignee_user_id, due_date FROM staff_calendar_checklist WHERE id = $it")->fetch(PDO::FETCH_ASSOC); return $r['ok'] && $row['status'] === 'done' && $row['assignee_user_id'] === null && $row['due_date'] === null; })());

section('Projects: the project page data');
$nowV = new DateTime('2026-11-13 10:00:00', $tz);
$V = spj_view($db, $B, $pid, $nowV);
check('the page data: dates, owner as a name only, added time, progress', $V && $V['start'] === '2026-11-16' && $V['end'] === '2026-11-20' && $V['owner'] === 'Tina Otieno' && $V['range_text'] === 'Mon 16 Nov to Fri 20 Nov' && $V['stats']['total'] >= 8 && $V['added_at'] !== '');
check('the timeline: one column per day, a bar per task from its start (or due date) to its due date, clipped to the project', count($V['days']) === 5 && $V['days'][0]['dow'] === 'Mon' && $V['days'][4]['day'] === '20');
$bars = array_column($V['bars'], null, 'title');
check('...Test on staging runs Tue 17 only; Train the tutors Wed 18; Announce to students Fri 20; a task with no dates spans the project', $bars['Test on staging']['from'] === 2 && $bars['Test on staging']['to'] === 2 && $bars['Train the tutors']['from'] === 3 && $bars['Train the tutors']['to'] === 3 && $bars['Announce to students']['from'] === 5 && $bars['Announce to students']['to'] === 5);
check('...a task that starts before it is due draws a longer bar (Super added: Tue 17 to Thu 19)', $bars['Super added']['from'] === 2 && $bars['Super added']['to'] === 4);
check('the line saying where today is: the week before, day N of M, ended N days ago', $V['today_text'] === 'Today is Fri 13 Nov, the week before' && spj_view($db, $B, $pid, new DateTime('2026-11-18 09:00', $tz))['today_text'] === 'Today is Wed 18 Nov, day 3 of 5' && spj_view($db, $B, $pid, new DateTime('2026-11-23 09:00', $tz))['today_text'] === 'Today is Mon 23 Nov, the project ended 3 days ago' && spj_view($db, $B, $pid, new DateTime('2026-11-18 09:00', $tz))['today_idx'] === 2 && $V['today_idx'] === null);
$ppl = array_column($V['people'], 'count', 'id');
check('"People": each person with how many tasks', $ppl[$t1] >= 4 && $ppl[$t2] >= 2 && isset($ppl[$sa]) && array_sum($ppl) === count(array_filter($V['tasks'], fn($t) => $t['who'] > 0)));
$VO = spj_view($db, $A, $pid, $nowV); $VB = spj_view($db, $B, $pid, $nowV); $VS = spj_view($db, $S, $pid, $nowV);
$ptasks = array_column($VB['tasks'], null, 'id');
check('can_manage: the owner and the super admin only; can_status: the person given the task (and the owner, the super admin), nobody else', $VO['can_manage'] && $VS['can_manage'] && !$VB['can_manage'] && $ptasks[$ptid]['can_status'] === true && $ptasks[$tid['Train the tutors']]['can_status'] === false && array_column($VO['tasks'], 'can_status') === array_fill(0, count($VO['tasks']), true));
check('a normal event is not a project: no page data', spj_view($db, $A, $seedId, $nowV) === null && spj_view($db, $A, 99999999, $nowV) === null);
$pe = scal_card($db, $A, scal_occurrence($db, $A, $pid, '2026-11-16'), $nowV);
check('the card and the edit values tell the type and give the tasks (to the owner only)', $pe['type'] === 'project' && count($pe['edit']['tasks']) >= 8 && scal_card($db, $B, scal_occurrence($db, $B, $pid, '2026-11-16'), $nowV)['edit'] === null);
$ptoNormal = scal_save($db, $A, $ins(['title' => 'Rollout ' . $run, 'type' => 'meeting', 'checklist' => json_encode([['t' => 'Back to simple', 'd' => false]])]), null, $pid);
check('changing a project to another type turns its tasks into a simple checklist and drops the task notices', $ptoNormal['ok'] && count($notices()) === 0 && count(scal_checklist($db, $pid)) === 1);
scal_save($db, $A, $pjIn(['tasks' => json_encode([$tk('Again', $t2, '2026-11-19')])]), null, $pid);
scal_delete($db, $A, $pid);
check('deleting a project deletes its tasks and its notices', (int) $db->query("SELECT COUNT(*) FROM staff_calendar_checklist WHERE event_id = $pid")->fetchColumn() === 0 && (int) $db->query("SELECT COUNT(*) FROM staff_calendar_task_notices WHERE event_id = $pid")->fetchColumn() === 0);

section('Settings and holidays: migrations, the holiday dates, the Sunday rule');
require_once __DIR__ . '/../services/StaffSettings.php';
require_once __DIR__ . '/../services/StaffHolidays.php';
$runSafe('119_staff_calendar_settings.sql'); $runSafe('119_staff_calendar_settings.sql'); $runSafe('120_staff_calendar_holidays_seed.sql'); $runSafe('120_staff_calendar_holidays_seed.sql');
check('migrations 119 and 120 twice: the switch, the tables, six team defaults once each and no duplicate holiday', sst_installed($db) && sh_installed($db)
    && (int) $db->query("SELECT COUNT(*) FROM staff_calendar_settings WHERE setting_key LIKE 'team\\_%'")->fetchColumn() === 6
    && (int) $db->query('SELECT COUNT(*) FROM (SELECT source_key FROM staff_calendar_holidays WHERE source_key IS NOT NULL GROUP BY source_key HAVING COUNT(*) > 1) d')->fetchColumn() === 0);
$easter = ['2025' => '04-20', '2026' => '04-05', '2027' => '03-28', '2028' => '04-16', '2029' => '04-01', '2030' => '04-21', '2031' => '04-13', '2032' => '03-28', '2033' => '04-17', '2034' => '04-09'];
$okE = true; foreach ($easter as $y => $md) { [$m, $d] = sh_easter((int) $y); if (sprintf('%02d-%02d', $m, $d) !== $md) $okE = false; }
check('Easter Sunday is worked out in code for 2025 to 2034 (no PHP extension)', $okE);
$h26 = array_column(sh_year_rows(2026), 'date', 'key');
check('2026: Good Friday 3 Apr and Easter Monday 6 Apr come from Easter; the fixed dates are where they should be', $h26['good_friday:2026'] === '2026-04-03' && $h26['easter_monday:2026'] === '2026-04-06' && $h26['new_years_day:2026'] === '2026-01-01' && $h26['labour_day:2026'] === '2026-05-01'
    && $h26['madaraka_day:2026'] === '2026-06-01' && $h26['mashujaa_day:2026'] === '2026-10-20' && $h26['jamhuri_day:2026'] === '2026-12-12' && $h26['christmas_day:2026'] === '2026-12-25' && $h26['boxing_day:2026'] === '2026-12-26');
check('2026: the 10 October holiday is Mazingira Day (formerly Utamaduni Day), and Eid ul-Fitr is only expected (20 Mar), marked not confirmed', $h26['mazingira_day:2026'] === '2026-10-10' && $h26['eid_ul_fitr:2026'] === '2026-03-20'
    && array_values(array_filter(sh_year_rows(2026), fn($r) => $r['kind'] === 'eid'))[0]['confirmed'] === 0 && count(sh_year_rows(2026)) === 11);
$r27 = array_column(sh_year_rows(2027), null, 'key');
check('a Sunday holiday moves to the Monday after it: Madaraka Day 2025 -> Mon 2 Jun; Jamhuri Day 2027 -> Mon 13 Dec; Mazingira Day 2027 -> Mon 11 Oct, with a note', array_column(sh_year_rows(2025), 'date', 'key')['madaraka_day:2025'] === '2025-06-02' && $r27['jamhuri_day:2027']['date'] === '2027-12-13'
    && $r27['mazingira_day:2027']['date'] === '2027-10-11' && $r27['mazingira_day:2027']['note'] === 'Moved from Sunday 10 Oct' && $r27['jamhuri_day:2027']['base'] === '2027-12-12');
check('Boxing Day on a Sunday (2027) is kept on the Monday; Christmas on a Saturday does not move', $r27['boxing_day:2027']['date'] === '2027-12-27' && $r27['christmas_day:2027']['date'] === '2027-12-25');
$r33 = array_column(sh_year_rows(2033), null, 'key');
check('when the Monday is already a holiday the next day is used: Christmas 2033 is Sunday, Boxing Day is Monday 26, so Christmas is kept on Tue 27', $r33['christmas_day:2033']['date'] === '2033-12-27' && $r33['boxing_day:2033']['date'] === '2033-12-26');
check('Eid ul-Fitr: the expected date is worked out from the Islamic calendar (can be a day out), twice in 2033, and the Sunday rule applies (2025: Mon 31 Mar)', sh_eid_fitr_expected(2025) === ['2025-03-31'] && sh_eid_fitr_expected(2027) === ['2027-03-10'] && count(sh_eid_fitr_expected(2033)) === 2
    && array_column(sh_year_rows(2025), 'date', 'key')['eid_ul_fitr:2025'] === '2025-03-31');
$seeded = $db->query("SELECT source_key, holiday_date FROM staff_calendar_holidays WHERE source_key IS NOT NULL AND holiday_date BETWEEN '2025-01-01' AND '2034-12-31'")->fetchAll(PDO::FETCH_KEY_PAIR);
$want = []; for ($y = 2025; $y <= 2034; $y++) foreach (sh_year_rows($y) as $r) $want[$r['key']] = $r['date'];
check('the migration put in exactly the dates the code works out, for all 10 years (2025 to 2034), no more and no less', count(array_diff_assoc($want, $seeded)) === 0 && count(array_diff_key($seeded, $want)) === 0 && count($want) >= 100);
check('every year has the 8 fixed holidays (with Good Friday and Easter Monday, 10 in all) and an expected Eid', (function () use ($db) { for ($y = 2025; $y <= 2034; $y++) { $rows = sh_year($db, $y); if (count($rows) < 11) return false; } return true; })());
$rg = sh_range($db, new DateTime('2026-03-01', $tz), new DateTime('2026-04-01', $tz));
check('holidays as calendar entries: teal, all day, Eid carries "Expected, to be confirmed"', count($rg) === 1 && $rg[0]['title'] === 'Eid ul-Fitr' && $rg[0]['label'] === 'Expected, to be confirmed' && $rg[0]['color'] === '#1A9A9A' && $rg[0]['all_day'] === true && $rg[0]['holiday'] === true);
$nx = sh_next($db, new DateTime('2026-11-13 09:00:00', $tz));
check('"Next holiday" from 13 Nov 2026 is Jamhuri Day on 12 Dec, in 29 days; on the day itself it is today', $nx['name'] === 'Jamhuri Day' && $nx['date'] === '2026-12-12' && $nx['days'] === 29 && sh_next($db, new DateTime('2026-12-12 08:00', $tz))['days'] === 0);

section('Holidays: only the super admin changes them');
check('a tutor cannot add, edit, confirm, fill in or remove', !sh_add($db, $A, ['name' => 'X', 'date' => '2027-01-02'])['ok'] && !sh_edit($db, $A, 1, ['name' => 'X', 'date' => '2027-01-02'])['ok'] && !sh_confirm($db, $A, 1, '2027-01-02')['ok'] && !sh_fill_year($db, $A, 2040)['ok'] && !sh_delete($db, $A, 1)['ok']);
check('a bad date, an empty or a 121-letter name is refused', !sh_add($db, $S, ['name' => 'X', 'date' => '2027-02-30'])['ok'] && !sh_add($db, $S, ['name' => ' ', 'date' => '2027-01-02'])['ok'] && !sh_add($db, $S, ['name' => str_repeat('x', 121), 'date' => '2027-01-02'])['ok']);
check('the super admin adds a holiday of their own', sh_add($db, $S, ['name' => 'Test holiday ' . $run, 'date' => '2036-02-03', 'note' => 'one-off'])['ok']);
$hid = (int) $db->query("SELECT id FROM staff_calendar_holidays WHERE name = 'Test holiday $run'")->fetchColumn();
check('...edits it and removes it', sh_edit($db, $S, $hid, ['name' => 'Test holiday ' . $run . ' v2', 'date' => '2036-02-04'])['ok'] && $db->query("SELECT holiday_date FROM staff_calendar_holidays WHERE id = $hid")->fetchColumn() === '2036-02-04' && sh_delete($db, $S, $hid)['ok'] && !sh_delete($db, $S, $hid)['ok']);
$eidId = (int) $db->query("SELECT id FROM staff_calendar_holidays WHERE source_key = 'eid_ul_fitr:2026'")->fetchColumn();
check('the super admin confirms Eid: the announced date is set and it is no longer "expected"', sh_confirm($db, $S, $eidId, '2026-03-21')['ok'] && sh_year($db, 2026)[1]['date'] === '2026-03-21' && sh_year($db, 2026)[1]['confirmed'] === true && sh_year($db, 2026)[1]['label'] === '');
check('confirming with a bad date or for an unknown holiday is refused', !sh_confirm($db, $S, $eidId, 'soon')['ok'] && !sh_confirm($db, $S, 99999999, '2026-03-21')['ok']);
$db->exec("UPDATE staff_calendar_holidays SET holiday_date = '2026-03-20', is_confirmed = 0, note = '' WHERE id = $eidId");
$f1 = sh_fill_year($db, $S, 2036); $f2 = sh_fill_year($db, $S, 2036);
check('"Fill in a year" adds the standard holidays of that year once (a second time adds nothing); a year already there adds nothing', $f1['ok'] && $f1['added'] >= 11 && $f2['ok'] && $f2['added'] === 0 && sh_fill_year($db, $S, 2026)['added'] === 0 && !sh_fill_year($db, $S, 1800)['ok']);
$db->exec("DELETE FROM staff_calendar_holidays WHERE holiday_date BETWEEN '2036-01-01' AND '2036-12-31'");

section('Event types: on and off, colours, add, delete');
$types0 = sst_types($db);
check('the 11 starter types, all on, with their event counts and the core flag', count($types0) >= 11 && count(array_filter(array_slice($types0, 0, 11), fn($t) => $t['active'])) === 11 && array_column($types0, 'core', 'key')['project'] === true && array_column($types0, 'core', 'key')['meeting'] === false);
check('a tutor cannot switch, recolour, add or delete a type', !sst_type_active($db, $A, 'meeting', false)['ok'] && !sst_type_color($db, $A, 'meeting', '#112233')['ok'] && !sst_type_add($db, $A, 'Hack', '#112233')['ok'] && !sst_type_delete($db, $A, 'payday')['ok'] && count(scal_types($db)) === count(array_filter(sst_types($db), fn($t) => $t['active'])));
$tn = 'Workshop' . substr($run, 0, 3);
check('the super admin adds a type: name and colour; a duplicate name, a bad colour, an empty or 41-letter name are refused', sst_type_add($db, $S, $tn, '#336699')['ok'] && !sst_type_add($db, $S, strtolower($tn), '#336699')['ok'] && !sst_type_add($db, $S, 'Other', 'blue')['ok'] && !sst_type_add($db, $S, ' ', '#336699')['ok'] && !sst_type_add($db, $S, str_repeat('x', 41), '#336699')['ok']);
$tkey = strtolower($tn);
check('...it is in the list and in the New event form (active types) with its colour', in_array($tkey, array_column(scal_types($db), 'type_key'), true) && array_column(scal_types($db), 'color', 'type_key')[$tkey] === '#336699');
$wid = scal_save($db, $A, $ins(['title' => 'Workshop event ' . $run, 'type' => $tkey, 'start_date' => '2026-12-09', 'end_date' => '2026-12-09']))['id'];
check('an event can be made with the new type; the type now has 1 event and cannot be deleted', $wid > 0 && array_column(sst_types($db), 'events', 'key')[$tkey] === 1 && !sst_type_delete($db, $S, $tkey)['ok'] && sst_type_exists($db, $tkey));
$titlesOn = fn() => array_column(scal_range($db, $A, new DateTime('2026-12-01', $tz), new DateTime('2027-01-01', $tz)), 'title');
check('the event shows on the calendar, is found by search and can be opened while its type is on', in_array('Workshop event ' . $run, $titlesOn(), true) && count(scal_search($db, $A, 'Workshop event ' . $run, scal_now())) === 1 && scal_find($db, $A, $wid) !== null);
check('switching the type off hides its events everywhere (calendar, search, card) and removes it from the form, but the event is kept', sst_type_active($db, $S, $tkey, false)['ok'] && !in_array('Workshop event ' . $run, $titlesOn(), true)
    && count(scal_search($db, $A, 'Workshop event ' . $run, scal_now())) === 0 && scal_find($db, $A, $wid) === null && !in_array($tkey, array_column(scal_types($db), 'type_key'), true) && in_array($tkey, array_column(scal_types($db, true), 'type_key'), true)
    && (int) $db->query("SELECT COUNT(*) FROM staff_calendar_events WHERE id = $wid")->fetchColumn() === 1);
check('...a new event of a type that is off is refused', !scal_save($db, $A, $ins(['title' => 'No way', 'type' => $tkey]))['ok']);
check('switching it on brings the events back; the colour can be changed', sst_type_active($db, $S, $tkey, true)['ok'] && in_array('Workshop event ' . $run, $titlesOn(), true) && sst_type_color($db, $S, $tkey, '#AA3366')['ok'] && array_column(scal_types($db), 'color', 'type_key')[$tkey] === '#AA3366' && !sst_type_color($db, $S, $tkey, 'red')['ok']);
scal_delete($db, $A, $wid);
check('a type with no events can be deleted; Birthday, Holiday and Project never can; an unknown type is refused', sst_type_delete($db, $S, $tkey)['ok'] && !sst_type_exists($db, $tkey) && !sst_type_delete($db, $S, 'birthday')['ok'] && !sst_type_delete($db, $S, 'holiday')['ok'] && !sst_type_delete($db, $S, 'project')['ok'] && !sst_type_delete($db, $S, 'nonsense')['ok']);
$bcol = array_column(scal_types($db), 'color', 'type_key')['birthday'];
sst_type_color($db, $S, 'birthday', '#112233');
check('the Birthday type colour is used for the birthday chips; switching Birthday and Holiday off is possible (they hide their chips) and back on restores', strtolower(sbd_occurrence($db, ['id' => $bt, 'name' => 'Bea', 'first' => 'Bea', 'show_age' => false, 'year' => null], '2026-07-14')['color']) === '#112233'
    && sst_type_active($db, $S, 'birthday', false)['ok'] && !scal_type_is_on($db, 'birthday') && sst_type_active($db, $S, 'birthday', true)['ok'] && scal_type_is_on($db, 'birthday') && scal_type_is_on($db, 'holiday'));
sst_type_color($db, $S, 'birthday', $bcol);

section('Team defaults and "My settings"');
check('the team defaults start on', sst_team_defaults($db) === ['remind_day_before' => true, 'remind_on_day' => true, 'remind_hour_before' => true, 'remind_email' => true, 'notice_birthday' => true, 'notice_gift' => true]);
check('only the super admin saves them', !sst_team_save($db, $A, ['remind_on_day' => 1])['ok'] && sst_team_defaults($db)['remind_day_before'] === true);
check('the super admin switches some off (a switch that is not sent is off) and back', sst_team_save($db, $S, ['remind_on_day' => 1, 'remind_hour_before' => 1, 'notice_gift' => 1])['ok'] && sst_team_defaults($db) === ['remind_day_before' => false, 'remind_on_day' => true, 'remind_hour_before' => true, 'remind_email' => false, 'notice_birthday' => false, 'notice_gift' => true]);
sst_team_save($db, $S, array_fill_keys(array_keys(SST_PREFS), 1));
check('back to all on', !in_array(false, sst_team_defaults($db), true));
$pu = $mkuser('prefa', 'tutor', 'Pat', 'Prefs'); $mktutorrow($pu); $PU = scal_actor($db, $pu);
check('every personal switch starts on', !in_array(false, sst_user_prefs($db, $pu), true) && count(sst_user_prefs($db, $pu)) === 6);
sst_user_save($db, $PU, ['remind_on_day' => 1, 'notice_gift' => 1, 'user_id' => $t1]);
check('a person saves their own switches; it only changes their own, even when another person is named in the request', sst_user_prefs($db, $pu) === ['remind_day_before' => false, 'remind_on_day' => true, 'remind_hour_before' => false, 'remind_email' => false, 'notice_birthday' => false, 'notice_gift' => true]
    && !in_array(false, sst_user_prefs($db, $t1), true));
check('saving again updates (one row per switch) and a super admin saves only their own too', (int) $db->query("SELECT COUNT(*) FROM staff_calendar_user_prefs WHERE user_id = $pu")->fetchColumn() === 6 && sst_user_save($db, $S, ['remind_email' => 1])['ok'] && sst_user_prefs($db, $sa)['remind_email'] === true && sst_user_prefs($db, $sa)['remind_on_day'] === false && sst_user_prefs($db, $pu)['remind_email'] === false);
sst_user_save($db, $S, array_fill_keys(array_keys(SST_PREFS), 1));

// the projects made above for the checks are removed again, so they never show up as "next" in the checks that run on another day or in the HTTP tests
foreach ($db->query("SELECT id FROM staff_calendar_events WHERE type_key = 'project' AND start_at = '2026-11-16 00:00:00' AND owner_user_id = $t1")->fetchAll(PDO::FETCH_COLUMN) as $pidc) scal_delete($db, $A, (int) $pidc);

$logins['_run'] = $run; $logins['_seed_event'] = $seedId; $logins['_priv_owner'] = $t1; $logins['_tina_event'] = $tinaId; $logins['_other_event'] = $rn['id'];
file_put_contents((getenv('TMPDIR') ?: getenv('TEMP') ?: sys_get_temp_dir()) . '/staffcal_users.json', json_encode($logins));
echo "\n" . ($fails === 0 ? "All $n checks passed." : "$fails of $n checks FAILED.") . "\n";
exit($fails === 0 ? 0 : 1);
