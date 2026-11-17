<?php
// The secretary dashboard and menu: greeting by Nairobi time, initials, which menu items show for which switches, what the dashboard data seam returns, the "up next" event.
// It also makes the secretaries the HTTP test (secretary_portal_http.sh) signs in as, and writes them to <temp>/secretary_portal_users.json.
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/secretary_portal_test.php     (a scratch database with all migrations applied; it refuses the database named in config/database.php)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$configured = (require __DIR__ . '/../config/database.php')['dbname'] ?? '';
if (!getenv('DAZU_DB_NAME') || getenv('DAZU_DB_NAME') === $configured) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database (not the one in config/database.php).\n"); exit(2); }
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SecretaryAdminService.php';
require_once __DIR__ . '/../helpers/secretary_ui.php';
require_once __DIR__ . '/../services/SecretaryDiaryService.php';
require_once __DIR__ . '/../services/StaffCalendar.php';

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
$svc = new SecretaryService($db, fn() => ['ok' => true, 'message' => 'sent']);
$adm = new SecretaryAdminService($db);
$pw = password_hash('x', PASSWORD_DEFAULT);

section('The greeting (Nairobi time)');
$g = fn(string $t, string $tz = 'Africa/Nairobi') => secretary_greeting(new DateTime($t, new DateTimeZone($tz)));
check('05:00 is morning', $g('2026-11-16 05:00') === 'Good morning');
check('11:59 is still morning', $g('2026-11-16 11:59') === 'Good morning');
check('12:00 is afternoon', $g('2026-11-16 12:00') === 'Good afternoon');
check('16:59 is still afternoon', $g('2026-11-16 16:59') === 'Good afternoon');
check('17:00 is evening', $g('2026-11-16 17:00') === 'Good evening');
check('23:30 is evening', $g('2026-11-16 23:30') === 'Good evening');
check('it follows Nairobi, not the server: 15:00 UTC is 18:00 in Nairobi, so evening', $g('2026-11-16 15:00', 'UTC') === 'Good evening');
check('and 08:00 UTC is 11:00 in Nairobi, so morning', $g('2026-11-16 08:00', 'UTC') === 'Good morning');
check('a New York time of 05:00 is 13:00 in Nairobi, so afternoon', $g('2026-11-16 05:00', 'America/New_York') === 'Good afternoon');

section('Names');
check('first name and initials', secretary_first_name('Mercy Wanjiru') === 'Mercy' && secretary_initials('Mercy Wanjiru') === 'MW' && secretary_initials('mercy') === 'M' && secretary_initials('Mary Jane Wambui') === 'MW');
check('a name with spaces and a non-English letter', secretary_initials("  Émilie   Ng'ang'a ") === 'ÉN');

section('The menu follows the switches');
$keys = fn(array $sw) => array_column(secretary_menu($sw), 0);
$allOn = ['blogs' => 'on', 'enquiries' => 'on', 'tasks' => 'on', 'room_visitors' => 'on', 'staff_calendar' => 'edit', 'id_card' => 'on'];
check('everything on: all eight items in order', $keys($allOn) === ['dashboard', 'blogs', 'enquiries', 'tasks', 'room_visitors', 'staff_calendar', 'id_card', 'notifications']);
check('everything off: only Dashboard and Notifications', $keys([]) === ['dashboard', 'notifications'] && $keys(['blogs' => 'off', 'staff_calendar' => 'off']) === ['dashboard', 'notifications']);
check('Staff Calendar shows for View only and Full, hidden for Off', in_array('staff_calendar', $keys(['staff_calendar' => 'view']), true) && in_array('staff_calendar', $keys(['staff_calendar' => 'edit']), true) && !in_array('staff_calendar', $keys(['staff_calendar' => 'off']), true));
check('one switch off hides only its item', $keys(array_merge($allOn, ['tasks' => 'off'])) === ['dashboard', 'blogs', 'enquiries', 'room_visitors', 'staff_calendar', 'id_card', 'notifications']);
check('Notifications is in the account group; the others are in main', array_column(array_filter(secretary_menu($allOn), fn($i) => $i[5] === 'account'), 0) === ['notifications']);
check('the phone bar has Home, Blogs, Enquiries, Tasks, Calendar (only the ones on)', array_column(array_filter(secretary_menu($allOn), fn($i) => $i[4] !== null), 4) === ['Home', 'Blogs', 'Enquiries', 'Tasks', 'Calendar']
    && array_column(array_filter(secretary_menu(['tasks' => 'on', 'room_visitors' => 'on']), fn($i) => $i[4] !== null), 4) === ['Home', 'Tasks']);
check('every item has an address inside the secretary portal', !array_filter(secretary_menu($allOn), fn($i) => strpos($i[3], 'secretary/') !== 0));

section('Secretaries for the pages');
$mk = function (string $key, string $first, string $last, array $sw, string $status = 'active') use ($svc, $adm, $db, $run, $pw): array {
    [$d, $e] = $svc->validate(['first_name' => $first, 'last_name' => $last, 'email' => "$key$run@sp.test", 'phone' => '0712345678', 'branch' => 'nairobi', 'birth_day' => 3, 'birth_month' => 5, 'start_date' => '2026-01-05', 'sw' => $sw]);
    if ($e) throw new RuntimeException(implode('; ', $e));
    $r = $svc->create($d, null, 0);
    $db->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')->execute([$pw, (int) $r['user_id']]);
    if ($status !== 'active') $adm->setStatus((int) $r['secretary_id'], $status, 0);
    return ['id' => (int) $r['user_id'], 'sid' => (int) $r['secretary_id'], 'email' => "$key$run@sp.test"];
};
$U = [
    'all'     => $mk('all', 'Mercy', 'Wanjiru', ['blogs_approval' => 'on', 'blogs' => 'on', 'staff_calendar' => 'edit', 'enquiries' => 'on', 'tasks' => 'on', 'room_visitors' => 'on', 'id_card' => 'on']),
    'trusted' => $mk('trusted', 'Faith', 'Njeri', ['blogs_approval' => 'off', 'blogs' => 'on', 'staff_calendar' => 'view', 'enquiries' => 'on', 'tasks' => 'on', 'room_visitors' => 'on', 'id_card' => 'off']),
    'none'    => $mk('none', 'Nelly', 'Off', ['blogs_approval' => 'off', 'blogs' => 'off', 'staff_calendar' => 'off', 'enquiries' => 'off', 'tasks' => 'off', 'room_visitors' => 'off', 'id_card' => 'off']),
    'tasks'   => $mk('tasks', 'Tina', 'Tasker', ['blogs_approval' => 'off', 'blogs' => 'off', 'staff_calendar' => 'off', 'enquiries' => 'off', 'tasks' => 'on', 'room_visitors' => 'off', 'id_card' => 'off']),
    'leave'   => $mk('leave', 'Lena', 'Leave', ['blogs_approval' => 'on', 'blogs' => 'on', 'staff_calendar' => 'edit', 'enquiries' => 'on', 'tasks' => 'on', 'room_visitors' => 'on', 'id_card' => 'on'], 'on_leave'),
    'left'    => $mk('left', 'Lucy', 'Left', ['blogs' => 'on'], 'left'),
    'susp'    => $mk('susp', 'Sue', 'Suspended', ['blogs' => 'on']),
];
$adm->setSuspended($U['susp']['sid'], true, 0);
check('seven secretaries were made', count($U) === 7 && $U['all']['sid'] > 0);

section('The dashboard data and the up next box');
$sec = secretary_by_user($db, $U['all']['id']); $sec['switches'] = secretary_switches($db, (int) $sec['id']);
$now = new DateTime('2026-11-16 18:30', new DateTimeZone('Africa/Nairobi'));
$d = secretary_dash_data($db, $sec, $now);
$pend = (int) $db->query("SELECT COUNT(*) FROM secretary_enquiries WHERE status = 'pending'")->fetchColumn();
check('a new secretary has no tasks and no blogs waiting; the diary and the enquiries come from their own tables', $d['tasks_today'] === 0 && $d['tasks'] === [] && $d['blogs_waiting'] === 0 && $d['diary'] === (new SecretaryDiaryService($db))->dashboard($now) && $d['enquiries_pending'] === $pend && count($d['enquiries']) === min(3, $pend));
$tutor = (function () use ($db, $run, $pw) {
    $rid = (int) $db->query("SELECT id FROM roles WHERE role_name = 'tutor'")->fetchColumn();
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["sp_t$run", "tutor$run@sp.test", $pw, $rid]);
    $id = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, 'Tina', 'Otieno']);
    $db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$id]);
    return $id;
})();
$db->exec("DELETE FROM staff_calendar_events WHERE title LIKE 'Staff meeting %'");   // scratch database: earlier runs' events
$db->prepare('INSERT INTO staff_calendar_events (title, location, type_key, start_at, end_at, all_day, repeat_rule, owner_user_id, notify_all, is_private) VALUES (?,?,?,?,?,0,?,?,0,0)')
   ->execute(["Staff meeting $run", 'Nairobi House', 'meeting', '2031-01-04 10:00:00', '2031-01-04 12:00:00', 'none', $tutor]);
$nowFar = new DateTime('2031-01-01 09:00', new DateTimeZone('Africa/Nairobi'));
$nx = secretary_next_event($db, $sec, $nowFar);
check('Full: the next event shows (title, place, when, "In 3 days")', $nx && $nx['title'] === "Staff meeting $run" && $nx['place'] === 'Nairobi House' && $nx['in'] === 'In 3 days' && str_contains($nx['when'], '10 am'), json_encode($nx));
$secV = secretary_by_user($db, $U['trusted']['id']); $secV['switches'] = secretary_switches($db, (int) $secV['id']);
check('View only: the next event shows too', secretary_next_event($db, $secV, $nowFar)['title'] === "Staff meeting $run");
$secO = secretary_by_user($db, $U['none']['id']); $secO['switches'] = secretary_switches($db, (int) $secO['id']);
check('Off: nothing, and no calendar code is asked', secretary_next_event($db, $secO, $nowFar) === null);
$secL = secretary_by_user($db, $U['left']['id']); $secL['switches'] = ['staff_calendar' => 'edit'];
check('Left: nothing even if a switch were still on (the calendar refuses her)', secretary_next_event($db, $secL, $nowFar) === null);
check('On leave still sees it (only Left blocks)', secretary_next_event($db, array_merge(secretary_by_user($db, $U['leave']['id']), ['switches' => ['staff_calendar' => 'edit']]), $nowFar) !== null);

$out = [];
foreach ($U as $k => $u) $out[$k] = ['id' => $u['id'], 'email' => $u['email'], 'sid' => $u['sid']];
$out['_run'] = $run; $out['_tutor'] = ['id' => $tutor, 'email' => "tutor$run@sp.test"];
file_put_contents((getenv('TMPDIR') ?: getenv('TEMP') ?: sys_get_temp_dir()) . '/secretary_portal_users.json', json_encode($out));
echo "\n" . ($fails === 0 ? "All $n checks passed." : "$fails of $n checks FAILED.") . "\n";
exit($fails === 0 ? 0 : 1);
