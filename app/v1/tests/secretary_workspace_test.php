<?php
// Tasks from the admin, the Room and Visitors diary and the activity record: rules, the room clash check (also when two secretaries book at the same moment), the Staff Calendar link,
// the reminders (always at a TEST time, never a real push or email), the cron script, and the activity record. Also makes the users secretary_workspace_http.sh signs in as.
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/secretary_workspace_test.php      (a scratch database with all migrations applied; it refuses the database named in config/database.php)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$configured = (require __DIR__ . '/../config/database.php')['dbname'] ?? '';
if (!getenv('DAZU_DB_NAME') || getenv('DAZU_DB_NAME') === $configured) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database (not the one in config/database.php).\n"); exit(2); }
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SecretaryAdminService.php';
require_once __DIR__ . '/../services/SecretaryTaskService.php';
require_once __DIR__ . '/../services/SecretaryDiaryService.php';
require_once __DIR__ . '/../services/SecretaryEnquiryService.php';
require_once __DIR__ . '/../services/StaffCalendar.php';
require_once __DIR__ . '/../services/MigrationService.php';
require_once __DIR__ . '/../helpers/secretary_ui.php';

$db = \Models\Connection::getInstance()->getConnection();
$schemaNow = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'DATABASE()' : 'current_schema()';   // the current database, on MySQL and on PostgreSQL

// ---- child process for the "same moment" room booking: waits for the start time, books the room, prints the answer
if (($argv[1] ?? '') === 'child') {
    $start = (float) $argv[2];
    $sec = secretary_by_user($db, (int) $argv[3]);
    $d = ['room' => $argv[4], 'branch' => 'nairobi', 'entry_date' => $argv[5], 'start_time' => '10:00', 'end_time' => '11:00', 'purpose' => 'Race ' . $argv[3], 'booked_by' => 'Race'];
    while (microtime(true) < $start) usleep(200);
    echo json_encode((new SecretaryDiaryService($db))->addRoom($sec, $d)) . "\n";
    exit(0);
}

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
$mkuser = function (string $key, string $role, string $first, string $last) use ($db, $pw, $roles, $run): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["ws_$key$run", "$key$run@ws.test", $pw, $roles[$role]]);
    $id = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, $first, $last]);
    return $id;
};
$admin = $mkuser('admin', 'admin', 'Simon', 'Admin');
$super = $mkuser('super', 'superadmin', 'Sam', 'Super');
$tutor = $mkuser('tutor', 'tutor', 'Tina', 'Tutor'); $db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$tutor]);
$svc = new SecretaryService($db, fn() => ['ok' => true, 'message' => 'sent']);
$adm = new SecretaryAdminService($db);
$mk = function (string $key, string $first, string $last, array $sw, string $status = 'active') use ($svc, $adm, $db, $run, $pw): array {
    [$d, $e] = $svc->validate(['first_name' => $first, 'last_name' => $last, 'email' => "$key$run@ws.test", 'phone' => '0712345678', 'branch' => 'nairobi', 'birth_day' => 3, 'birth_month' => 5, 'start_date' => '2026-01-05', 'sw' => $sw]);
    if ($e) throw new RuntimeException(implode('; ', $e));
    $r = $svc->create($d, null, 0);
    $db->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')->execute([$pw, (int) $r['user_id']]);
    if ($status !== 'active') $adm->setStatus((int) $r['secretary_id'], $status, 0);
    $sec = secretary_by_user($db, (int) $r['user_id']); $sec['switches'] = secretary_switches($db, (int) $r['secretary_id']);
    return $sec + ['email_addr' => "$key$run@ws.test"];
};
$all = ['tasks' => 'on', 'room_visitors' => 'on', 'enquiries' => 'on', 'staff_calendar' => 'edit'];
$S1 = $mk('mercy', 'Mercy', 'Wanjiku', $all);
$S2 = $mk('faith', 'Faith', 'Achieng', ['tasks' => 'on', 'room_visitors' => 'on', 'staff_calendar' => 'view']);
$S3 = $mk('nora', 'Nora', 'Off', ['tasks' => 'off', 'room_visitors' => 'off', 'staff_calendar' => 'off']);
$S4 = $mk('grace', 'Grace', 'Njeri', $all, 'on_leave');
$S5 = $mk('lucy', 'Lucy', 'Left', $all, 'left');
$T = new SecretaryTaskService($db); $Dy = new SecretaryDiaryService($db);
$tz = new DateTimeZone('Africa/Nairobi');
$today = SecretaryDiaryService::today();
$rm = 'Room Z' . strtoupper($run);   // a room name of its own, so a scratch database that keeps older runs cannot clash with this one
$plus = fn(int $d) => (new DateTime($today, $tz))->modify(($d >= 0 ? '+' : '') . "$d days")->format('Y-m-d');

section('Migrations 129 to 132');
$tmp = __DIR__ . '/sec_mig_tmp_' . getmypid(); mkdir($tmp);
$files = ['129_secretary_tasks.sql', '130_secretary_task_reminder_log.sql', '131_secretary_diary.sql', '132_secretary_activity.sql'];
foreach ($files as $f) copy(__DIR__ . '/../databases/required_migrations/' . $f, "$tmp/$f");
$db->exec("DELETE FROM schema_migrations WHERE filename IN ('" . implode("','", $files) . "')");
$r = (new MigrationService($db, $tmp))->applyPending(static function (string $m): void {});
array_map('unlink', glob("$tmp/*")); rmdir($tmp);
check('they run a second time and change nothing', $r['ran'] === 4 && empty($r['failed']), json_encode($r['failed'] ?? []));
check('they are the next free numbers after 128, each used once', array_sum(array_map(fn($f) => count(glob(__DIR__ . '/../databases/required_migrations/' . substr($f, 0, 3) . '_*')), $files)) === 4);
$logCols = array_column($db->query("SELECT column_name AS cn FROM information_schema.columns WHERE table_schema = $schemaNow AND table_name = 'staff_calendar_events'")->fetchAll(PDO::FETCH_ASSOC), 'cn');
check('nothing was added to an existing table: the Staff Calendar events table has no task column', !array_filter($logCols, fn($c) => preg_match('/task|secretary/i', $c)));

section('The shared activity function');
check('it records an event and refuses the same event twice (the dedupe key)', secretary_activity($db, 'diary', 'test', $S1['id'], 'Mercy Wanjiku', 'Subject', null, "t:$run") === true && secretary_activity($db, 'diary', 'test', $S1['id'], 'Mercy Wanjiku', 'Subject', null, "t:$run") === false
    && (int) $db->query("SELECT COUNT(*) FROM secretary_activity WHERE dedupe_key = 't:$run'")->fetchColumn() === 1);
$db->beginTransaction();
$okIn = secretary_activity($db, 'diary', 'test', $S1['id'], 'Mercy', 'S', null, "t2:$run"); $dupIn = secretary_activity($db, 'diary', 'test', $S1['id'], 'Mercy', 'S', null, "t2:$run");
$stillOk = (int) $db->query('SELECT 1')->fetchColumn() === 1;
$db->commit();
check('it works inside a transaction too, and a repeat does not break the transaction', $okIn && !$dupIn && $stillOk);

section('Tasks: the admin');
$good = ['secretary_id' => $S1['id'], 'title' => "Update the Thika notice $run", 'description' => 'Check the dates.', 'due_date' => $plus(0), 'due_time' => '17:00'];
[$d, $e] = $T->validate($good);
check('a good task form passes', !$e && $d['due_at'] === $plus(0) . ' 17:00:00', implode('|', $e));
foreach ([['title', ''], ['due_date', '2026-13-40'], ['due_time', '25:00'], ['description', str_repeat('x', 2001)]] as [$k, $v]) check("refused: $k = " . json_encode(mb_substr((string) $v, 0, 10)), count($T->validate([$k => $v] + $good)[1]) >= 1);
check('a task can only go to a secretary who is still with the team', !$T->give($admin, $T->validate(['secretary_id' => $S5['id']] + $good)[0])['ok'] && !$T->give($admin, $T->validate(['secretary_id' => 999999] + $good)[0])['ok'] && !isset($T->assignable()[$S5['id']]) && isset($T->assignable()[$S4['id']]));
PortalNotifications::saveChoices($db, $admin, ['other']);
$nS = fn(int $u, string $t = '') => (int) $db->query("SELECT COUNT(*) FROM portal_notifications WHERE user_id = $u AND type = 'secretary_task'" . ($t ? " AND title LIKE '$t%'" : ''))->fetchColumn();
$n0 = $nS($S1['user_id']);
$r = $T->give($admin, $d);
$id1 = (int) $r['id'];
$row = $db->query("SELECT * FROM secretary_tasks WHERE id = $id1")->fetch(PDO::FETCH_ASSOC);
check('the task is saved as open, with the admin\'s name, for that secretary only', $r['ok'] && $row['status'] === 'open' && $row['given_by_name'] === 'Simon Admin' && (int) $row['secretary_id'] === $S1['id']);
check('she gets a notice of the new task (existing notification code)', $nS($S1['user_id']) === $n0 + 1 && str_contains((string) $db->query("SELECT title FROM portal_notifications WHERE user_id = {$S1['user_id']} AND type = 'secretary_task' ORDER BY id DESC LIMIT 1")->fetchColumn(), 'Simon Admin'));
$ev = $db->query("SELECT * FROM staff_calendar_events WHERE id = " . (int) $row['calendar_event_id'])->fetch(PDO::FETCH_ASSOC);
check('it is also on HER Staff Calendar: a private entry owned by her, with the reminders of the calendar switched off', $ev && (int) $ev['owner_user_id'] === $S1['user_id'] && (int) $ev['is_private'] === 1 && $ev['type_key'] === 'deadline' && str_starts_with($ev['title'], 'Task: ') && (int) $ev['remind_on_day'] === 0 && (int) $ev['remind_day_before'] === 0 && (int) $ev['remind_email'] === 0);
$a1 = scal_actor($db, $S1['user_id']); $a2 = scal_actor($db, $S2['user_id']);
$seen = fn($actor) => array_column(scal_range($db, $actor, new DateTime($plus(0) . ' 00:00', scal_tz()), new DateTime($plus(1) . ' 00:00', scal_tz())), 'id');
check('a Full secretary sees it on her calendar', $a1 && in_array((int) $ev['id'], $seen($a1), true));
$T->give($admin, $T->validate(['secretary_id' => $S2['id'], 'title' => "Faith task $run"] + $good)[0]);
$evF = (int) $db->query("SELECT calendar_event_id FROM secretary_tasks WHERE title = 'Faith task $run'")->fetchColumn();
check('a View only secretary sees her own task on her calendar, and not Mercy\'s', $a2 && in_array($evF, $seen($a2), true) && !in_array((int) $ev['id'], $seen($a2), true));
check('nobody else sees them: a tutor, and the super admin calendar actor', !in_array((int) $ev['id'], $seen(scal_actor($db, $tutor)), true) && !in_array((int) $ev['id'], $seen(scal_actor($db, $super)), true));

section('Tasks: the secretary');
$mine = $T->forSecretary($S1['id']); $faiths = $T->forSecretary($S2['id']);
check('she sees her own tasks only (not another secretary\'s)', in_array($id1, array_column($mine, 'id'), true) && !array_filter($mine, fn($t) => (int) $t['secretary_id'] !== $S1['id']) && !in_array($id1, array_column($faiths, 'id'), true));
check('another secretary cannot tick it or add a note to it (it looks like it does not exist)', !$T->tick($S2, $id1)['ok'] && !$T->addNote($S2, $id1, 'hi')['ok'] && $db->query("SELECT status FROM secretary_tasks WHERE id = $id1")->fetchColumn() === 'open');
$nA = fn() => (int) $db->query("SELECT COUNT(*) FROM portal_notifications WHERE user_id = $admin AND type = 'secretary_task'")->fetchColumn();
$na = $nA();
$r = $T->tick($S1, $id1, 'Done and checked.');
$row = $db->query("SELECT * FROM secretary_tasks WHERE id = $id1")->fetch(PDO::FETCH_ASSOC);
check('she ticks her own task with a note: done, with the time', $r['ok'] && $row['status'] === 'done' && !empty($row['done_at']) && (int) $db->query("SELECT COUNT(*) FROM secretary_task_notes WHERE task_id = $id1 AND author_name = 'Mercy Wanjiku' AND created_at IS NOT NULL")->fetchColumn() === 1);
check('the admin who gave it is told', $nA() === $na + 1 && str_contains((string) $db->query("SELECT title FROM portal_notifications WHERE user_id = $admin AND type = 'secretary_task' ORDER BY id DESC LIMIT 1")->fetchColumn(), 'Mercy Wanjiku'));
check('the tick is in the activity record once, with her name', (int) $db->query("SELECT COUNT(*) FROM secretary_activity WHERE dedupe_key = 'task:$id1:tick' AND actor_name = 'Mercy Wanjiku' AND area = 'tasks'")->fetchColumn() === 1);
check('ticking again is refused and records nothing more', !$T->tick($S1, $id1)['ok'] && (int) $db->query("SELECT COUNT(*) FROM secretary_activity WHERE dedupe_key = 'task:$id1:tick'")->fetchColumn() === 1);
check('the calendar entry says Done', str_starts_with((string) $db->query("SELECT title FROM staff_calendar_events WHERE id = " . (int) $ev['id'])->fetchColumn(), 'Done: '));
check('she can add a note to her task (recorded); an empty or long note is refused', $T->addNote($S1, $id1, 'One more thing.')['ok'] && !$T->addNote($S1, $id1, ' ')['ok'] && !$T->addNote($S1, $id1, str_repeat('x', 501))['ok'] && (int) $db->query("SELECT COUNT(*) FROM secretary_activity WHERE area = 'tasks' AND action = 'note' AND actor_name = 'Mercy Wanjiku'")->fetchColumn() >= 1);
check('the service has no secretary-side create, edit or delete (only the admin methods give, update, reopen and delete, which the secretary pages never call)', !array_filter(['tick', 'addNote', 'forSecretary'], fn($m) => !method_exists($T, $m)));
$base = new DateTime("$today 10:00", $tz);
$pill = fn(array $t) => SecretaryTaskService::pill($t, $base)[0];
check('the pills: Today, Tomorrow, In N days, Overdue, Done', $pill(['status' => 'open', 'due_at' => "$today 17:00:00"]) === 'Today' && $pill(['status' => 'open', 'due_at' => $plus(1) . ' 09:00:00']) === 'Tomorrow'
    && $pill(['status' => 'open', 'due_at' => $plus(2) . ' 09:00:00']) === 'In 2 days' && $pill(['status' => 'open', 'due_at' => $plus(-1) . ' 09:00:00']) === 'Overdue' && $pill(['status' => 'open', 'due_at' => "$today 09:00:00"]) === 'Overdue' && $pill(['status' => 'done', 'due_at' => $plus(-5) . ' 09:00:00']) === 'Done');
$r = $T->update($admin, $id1, $T->validate(['title' => "Renamed $run", 'due_date' => $plus(3)] + $good)[0]);
check('the admin edits a task; its calendar entry follows (title and time)', $r['ok'] && str_contains((string) $db->query("SELECT title FROM staff_calendar_events WHERE id = " . (int) $ev['id'])->fetchColumn(), "Renamed $run") && str_starts_with((string) $db->query("SELECT start_at FROM staff_calendar_events WHERE id = " . (int) $ev['id'])->fetchColumn(), $plus(3)));
check('the admin opens a ticked task again', $T->reopen($admin, $id1)['ok'] && $db->query("SELECT status FROM secretary_tasks WHERE id = $id1")->fetchColumn() === 'open');
$id9 = (int) $T->give($admin, $T->validate(['title' => "To delete $run"] + $good)[0])['id'];
$ev9 = (int) $db->query("SELECT calendar_event_id FROM secretary_tasks WHERE id = $id9")->fetchColumn();
$T->addNote($S1, $id9, 'a note');
check('the admin deletes a task: it, its notes and its calendar entry are gone', $T->delete($admin, $id9)['ok'] && !$db->query("SELECT 1 FROM secretary_tasks WHERE id = $id9")->fetchColumn() && !$db->query("SELECT 1 FROM secretary_task_notes WHERE task_id = $id9")->fetchColumn() && !$db->query("SELECT 1 FROM staff_calendar_events WHERE id = $ev9")->fetchColumn());
[$cnt, $rows] = $T->dashboard($S1['id']);
check('the dashboard numbers: tasks due today and her next three', is_int($cnt) && count($rows) <= 3 && !array_filter($rows, fn($x) => empty($x['title']) || empty($x['chip'])));

section('The diary: rooms');
$d1 = $plus(2);
[$rd, $re] = $Dy->validateRoom(['room' => $rm, 'branch' => 'nairobi', 'entry_date' => $d1, 'start_time' => '10:00', 'end_time' => '12:00', 'purpose' => 'Tutor interviews'], 'Mercy Wanjiku');
check('a good booking form passes; "booked by" defaults to her name', !$re && $rd['booked_by'] === 'Mercy Wanjiku', implode('|', $re));
foreach ([['room', ''], ['branch', 'mombasa'], ['entry_date', '2026-02-31'], ['start_time', '9am'], ['end_time', '09:00'], ['purpose', '']] as [$k, $v]) check("refused: $k = " . json_encode($v), count($Dy->validateRoom([$k => $v] + ['room' => 'R', 'branch' => 'nairobi', 'entry_date' => $d1, 'start_time' => '10:00', 'end_time' => '12:00', 'purpose' => 'p'], 'X')[1]) >= 1);
$r = $Dy->addRoom($S1, $rd);
$rid = (int) ($r['id'] ?? 0);
check('the booking is saved, with who added it', !empty($r['ok']) && $db->query("SELECT created_by_name FROM secretary_diary_entries WHERE id = $rid")->fetchColumn() === 'Mercy Wanjiku');
$cnt = fn() => (int) $db->query("SELECT COUNT(*) FROM secretary_diary_entries WHERE kind = 'room'")->fetchColumn();
$c0 = $cnt();
$clash = fn(string $room, string $s, string $e, string $branch = 'nairobi') => $Dy->addRoom($S2, $Dy->validateRoom(['room' => $room, 'branch' => $branch, 'entry_date' => $d1, 'start_time' => $s, 'end_time' => $e, 'purpose' => 'Clash test'], 'Faith')[0]);
$r = $clash($rm, '11:00', '13:00');
check('the same room at an overlapping time is refused with a clear message, and nothing is saved', empty($r['ok']) && str_contains($r['error'], 'already booked') && str_contains($r['error'], '10:00 am') && str_contains($r['error'], 'Mercy Wanjiku') && str_contains($r['error'], 'Nothing was saved') && $cnt() === $c0, $r['error'] ?? '');
check('inside, around and equal times also clash', empty($clash($rm, '10:30', '11:00')['ok']) && empty($clash($rm, '09:00', '13:00')['ok']) && empty($clash($rm, '10:00', '12:00')['ok']) && $cnt() === $c0);
check('the room name is compared without capitals or extra spaces', empty($clash('  ' . str_replace(' ', '   ', strtolower($rm)) . ' ', '10:30', '11:30')['ok']) && $cnt() === $c0);
check('back to back is fine (11:59 end vs 12:00 start), another room is fine, another branch is fine', !empty($clash($rm, '12:00', '13:00')['ok']) && !empty($clash("$rm-B", '10:00', '12:00')['ok']) && !empty($clash($rm, '10:00', '12:00', 'thika')['ok']));
$r = $Dy->cancel($S1, $rid);
check('after the booking is cancelled the room is free again', $r['ok'] && !empty($clash($rm, '10:00', '11:00')['ok']));

section('The diary: two secretaries at the same moment');
$flags = extension_loaded('pdo_pgsql') ? ' -d extension=pgsql -d extension=pdo_pgsql' : '';
$d2 = $plus(5); $start = microtime(true) + 3.0; $N = 6; $procs = []; $pipes = [];
$users = [$S1, $S2, $S4];
for ($i = 0; $i < $N; $i++) {
    $u = $users[$i % 3]['user_id'];
    $procs[$i] = proc_open('"' . PHP_BINARY . '"' . $flags . ' ' . escapeshellarg(__FILE__) . ' child ' . $start . ' ' . $u . ' ' . escapeshellarg("Race Room $run") . ' ' . $d2, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i]);
}
$okc = 0; $msgs = 0;
foreach ($procs as $i => $p) { $out = stream_get_contents($pipes[$i][1]); stream_get_contents($pipes[$i][2]); proc_close($p); $j = json_decode(trim(strrchr("\n" . trim($out), "\n")), true); if (!empty($j['ok'])) $okc++; elseif (!empty($j['clash'])) $msgs++; }
check("of $N bookings at the same moment exactly one is saved, the others get the clash message", $okc === 1 && $msgs === $N - 1, "ok=$okc clash=$msgs");
check('the database holds one booking for that room and time', (int) $db->query("SELECT COUNT(*) FROM secretary_diary_entries WHERE kind = 'room' AND room = 'Race Room $run' AND entry_date = '$d2' AND status <> 'cancelled'")->fetchColumn() === 1);

section('The diary: the Staff Calendar');
$db->exec("DELETE FROM staff_calendar_events WHERE title LIKE 'WS meeting%'");
$ins = $db->prepare("INSERT INTO staff_calendar_events (title, location, type_key, start_at, end_at, all_day, repeat_rule, owner_user_id, notify_all, is_private, remind_day_before, remind_on_day, remind_hour_before, remind_email) VALUES (?,?,?,?,?,0,'none',?,0,?,0,0,0,0)");
$d3 = $plus(1);
$ins->execute(["WS meeting $run", "Nairobi House (Cianda House), 4th Floor, $rm", 'meeting', "$d3 14:00:00", "$d3 16:00:00", $tutor, 0]);
$ins->execute(["WS private $run", 'Room 999', 'meeting', "$d3 14:00:00", "$d3 16:00:00", $tutor, 1]);
$ins->execute(["WS no room $run", 'The hall', 'meeting', "$d3 14:00:00", "$d3 16:00:00", $tutor, 0]);
$cal = $Dy->calendarRooms($d3, $d3);
$titles = array_column($cal, 'title');
check('an event with a room in its location shows in the diary as a read-only booking', in_array("WS meeting $run", $titles, true) && $cal[array_search("WS meeting $run", $titles, true)]['room'] === $rm && $cal[array_search("WS meeting $run", $titles, true)]['branch'] === 'nairobi');
check('a private event and an event with no room never show', !in_array("WS private $run", $titles, true) && !in_array("WS no room $run", $titles, true));
$rows = $Dy->rows('week', $S1['id']);
$calRow = array_values(array_filter($rows, fn($r) => $r['source'] === 'calendar' && str_contains($r['sub'], "WS meeting $run")));
$wk = SecretaryDiaryService::range('week'); $inWeek = $d3 >= $wk[0] && $d3 <= $wk[1];
check('it is listed as a calendar booking nobody can change (no cancel, no arrive)', $inWeek ? ($calRow && !$calRow[0]['can_cancel'] && !$calRow[0]['mine'] && str_contains($calRow[0]['sub'], 'Staff Calendar')) : true);
$r = $Dy->addRoom($S2, $Dy->validateRoom(['room' => $rm, 'branch' => 'nairobi', 'entry_date' => $d3, 'start_time' => '15:00', 'end_time' => '15:30', 'purpose' => 'Clash with the calendar'], 'Faith')[0]);
check('a booking that overlaps a calendar event in that room is refused and names the calendar event', empty($r['ok']) && str_contains($r['error'], "WS meeting $run") && str_contains($r['error'], 'Staff Calendar'), $r['error'] ?? '');
check('before and after the calendar event is fine', !empty($Dy->addRoom($S2, $Dy->validateRoom(['room' => $rm, 'branch' => 'nairobi', 'entry_date' => $d3, 'start_time' => '10:00', 'end_time' => '14:00', 'purpose' => 'Before'], 'Faith')[0])['ok']));
check('the calendar event itself was not touched', (int) $db->query("SELECT COUNT(*) FROM staff_calendar_events WHERE title = 'WS meeting $run'")->fetchColumn() === 1 && !method_exists($Dy, 'updateCalendar'));

section('The diary: cancel, visitors, filters');
$ownId = (int) $Dy->addRoom($S2, $Dy->validateRoom(['room' => "Own Room $run", 'branch' => 'thika', 'entry_date' => $d3, 'start_time' => '09:00', 'end_time' => '10:00', 'purpose' => 'Mine'], 'Faith')[0])['id'];
check('a secretary cannot cancel another secretary\'s entry', !$Dy->cancel($S1, $ownId)['ok'] && $db->query("SELECT status FROM secretary_diary_entries WHERE id = $ownId")->fetchColumn() === 'booked');
$r = $Dy->cancel($S2, $ownId);
$e = $db->query("SELECT * FROM secretary_diary_entries WHERE id = $ownId")->fetch(PDO::FETCH_ASSOC);
check('she cancels her own: it stays (never deleted), with her name and the time', $r['ok'] && $e['status'] === 'cancelled' && $e['cancelled_by_name'] === 'Faith Achieng' && !empty($e['cancelled_at']));
check('the cancel is in the activity record; cancelling again is refused', (int) $db->query("SELECT COUNT(*) FROM secretary_activity WHERE dedupe_key = 'diary:$ownId:cancel' AND actor_name = 'Faith Achieng'")->fetchColumn() === 1 && !$Dy->cancel($S2, $ownId)['ok']);
$vd = $Dy->validateVisitor(['person_name' => "Mr. Karanja $run", 'sees_who' => 'the admin', 'entry_date' => $today, 'start_time' => '11:30', 'purpose' => 'Printing supplier'])[0];
$vid = (int) $Dy->addVisitor($S1, $vd)['id'];
check('a visitor is added as Expected; the form refuses missing fields', $db->query("SELECT status FROM secretary_diary_entries WHERE id = $vid")->fetchColumn() === 'expected' && count($Dy->validateVisitor(['person_name' => ''])[1]) >= 3);
check('any secretary marks the visitor Arrived on the day, and Left after that, each with the time', $Dy->mark($S2, $vid, 'arrived')['ok'] && !empty($db->query("SELECT arrived_at FROM secretary_diary_entries WHERE id = $vid")->fetchColumn()) && !$Dy->mark($S2, $vid, 'arrived')['ok'] && $Dy->mark($S4, $vid, 'left')['ok'] && !empty($db->query("SELECT left_at FROM secretary_diary_entries WHERE id = $vid")->fetchColumn()));
check('arrived and left are in the activity record', (int) $db->query("SELECT COUNT(*) FROM secretary_activity WHERE dedupe_key IN ('diary:$vid:arrived', 'diary:$vid:left')")->fetchColumn() === 2);
$vid2 = (int) $Dy->addVisitor($S1, ['entry_date' => $plus(3)] + $vd)['id'];
check('a visitor cannot be marked Arrived before the day, and Left needs Arrived first', !$Dy->mark($S1, $vid2, 'arrived')['ok'] && !$Dy->mark($S1, $vid2, 'left')['ok']);
$vid3 = (int) $Dy->addVisitor($S1, ['person_name' => "Gone $run"] + $vd)['id'];
$Dy->mark($S1, $vid3, 'arrived');
check('a visitor who has arrived cannot be cancelled', !$Dy->cancel($S1, $vid3)['ok']);
check('the filters: Today, This week, Rooms, Visitors', in_array($vid, array_column($Dy->rows('today'), 'id'), true) && in_array($vid, array_column($Dy->rows('visitors'), 'id'), true) && !in_array($vid, array_column($Dy->rows('rooms'), 'id'), true)
    && in_array($rid, array_column($Dy->rows('rooms'), 'id'), true) && !in_array($rid, array_column($Dy->rows('visitors'), 'id'), true) && in_array($vid2, array_column($Dy->rows('visitors'), 'id'), true) && !in_array($vid2, array_column($Dy->rows('today'), 'id'), true));
check('"mine" marks the entries she may cancel', (function () use ($Dy, $S1, $vid2) { $r = array_values(array_filter($Dy->rows('visitors', $S1['id']), fn($x) => $x['id'] === $vid2))[0]; return $r['mine'] && $r['can_cancel']; })());
check('there is no delete anywhere in the diary service', !array_filter(get_class_methods($Dy), fn($m) => stripos($m, 'delete') !== false || stripos($m, 'remove') !== false));
check('today\'s diary for the dashboard lists entries and leaves cancelled ones out', count($Dy->dashboard()) >= 1 && !array_filter($Dy->dashboard(), fn($x) => str_contains($x['note'], 'Mine')));

section('Task reminders (a test time, nothing is really sent)');
$db->exec('DELETE FROM secretary_task_reminder_log');
$T0 = '2031-03-10';
$mkT = fn(array $sec, string $title, string $due, string $status = 'open') => (function () use ($T, $admin, $sec, $title, $due, $status, $db) {
    $id = (int) $T->give($admin, $T->validate(['secretary_id' => $sec['id'], 'title' => $title, 'due_date' => substr($due, 0, 10), 'due_time' => substr($due, 11, 5)])[0])['id'];
    if ($status === 'done') $T->tick($sec, $id);
    return $id; })();
$q1 = $mkT($S1, "Due today $run", "$T0 17:00");        // on_day
$q2 = $mkT($S1, "Due tomorrow $run", '2031-03-11 09:30');  // day_before
$q3 = $mkT($S1, "Due later $run", '2031-03-13 09:30');     // not yet
$q4 = $mkT($S1, "Already done $run", "$T0 17:00", 'done');
$q5 = $mkT($S4, "On leave task $run", "$T0 12:00");    // on leave: still reminded
$q6 = $mkT($S2, "Faith task due $run", "$T0 10:00");
$db->exec("UPDATE secretary_switches SET switch_value = 'off' WHERE secretary_id = {$S3['id']}");
$q7 = $mkT($S1, "Overdue $run", '2031-03-09 09:00');       // overdue: no more reminders
$at = fn(string $t, string $z = 'Africa/Nairobi') => new DateTime($t, new DateTimeZone($z));
$us = [$S1['user_id'], $S2['user_id'], $S3['user_id'], $S4['user_id'], $S5['user_id']];
check('before 8:00 am nothing is due', $T->dueReminders($at("$T0 07:59")) === []);
$due = $T->dueReminders($at("$T0 08:00"));
$pairs = array_map(fn($j) => $j['task']['id'] . ':' . $j['kind'] . ':' . $j['user_id'], $due);
check('at 8:00 am: due today = on the day, due tomorrow = the day before', in_array("$q1:on_day:" . $S1['user_id'], $pairs, true) && in_array("$q2:day_before:" . $S1['user_id'], $pairs, true) && in_array("$q6:on_day:" . $S2['user_id'], $pairs, true));
check('a task due later, a task already done and an overdue task are not reminded; On leave still is', !array_filter($pairs, fn($p) => in_array((int) explode(':', $p)[0], [$q3, $q4, $q7], true)) && in_array("$q5:on_day:" . $S4['user_id'], $pairs, true));
check('it is 8:00 in Nairobi when it is 05:00 UTC; up to 12:00, never later, never another day', count($T->dueReminders($at("$T0 05:00", 'UTC'))) === count($due) && count($T->dueReminders($at("$T0 12:00"))) === count($due) && $T->dueReminders($at("$T0 12:01")) === [] && $T->dueReminders($at('2031-03-01 08:00')) === []);
$dry = $T->reminders($at("$T0 08:00"), ['dry' => true, 'only_users' => $us]);
check('a dry run says what it would send and writes nothing', $dry['would'] > 0 && (int) $db->query('SELECT COUNT(*) FROM secretary_task_reminder_log')->fetchColumn() === 0);
$ns = $T->reminders($at("$T0 08:00"), ['no_send' => true, 'only_users' => $us]);
check('a test run (no-send) claims each reminder in the log and calls nothing', $ns['sent_app'] > 0 && $ns['sent_email'] > 0 && (int) $db->query("SELECT COUNT(*) FROM secretary_task_reminder_log WHERE detail = 'no-send (test)'")->fetchColumn() === $ns['sent_app'] + $ns['sent_email']);
$ns2 = $T->reminders($at("$T0 09:00"), ['no_send' => true, 'only_users' => $us]);
check('running it again sends nothing twice (the log)', $ns2['sent_app'] === 0 && $ns2['sent_email'] === 0 && $ns2['dupe'] > 0);
$db->exec('DELETE FROM secretary_task_reminder_log');
$mails = [];
$nb = $nS($S1['user_id']);
PortalNotifications::saveChoices($db, $S1['user_id'], ['other']);
$real = $T->reminders($at("$T0 08:00"), ['only_users' => [$S1['user_id']], 'mail_fn' => function ($to, $name, $subject, $html) use (&$mails) { $mails[] = compact('to', 'subject', 'html'); return ['ok' => true, 'message' => 'sent']; }]);
check('a real pass (mail replaced by a recorder): the in-app notices go through the existing notification code', $real['sent_app'] === 2 && $nS($S1['user_id']) === $nb + 2 && $nS($S1['user_id'], 'Task due today') >= 1 && $nS($S1['user_id'], 'Task due tomorrow') >= 1);
check('one email per reminder to her address, escaped, with the Dazu green button', count($mails) === 2 && $mails[0]['to'] === $S1['email_addr'] && str_contains($mails[0]['html'], '#048C44') && str_contains($mails[0]['subject'], 'Task due'));
$real2 = $T->reminders($at("$T0 08:05"), ['only_users' => [$S1['user_id']], 'mail_fn' => function () use (&$mails) { $mails[] = 'again'; return ['ok' => true]; }]);
check('nothing again on the next pass', $real2['sent_app'] === 0 && $real2['sent_email'] === 0 && count($mails) === 2);
$failRun = $T->reminders($at("$T0 08:00"), ['only_users' => [$S4['user_id']], 'mail_fn' => function () { throw new RuntimeException('SMTP down'); }]);
check('a mail failure is logged and does not stop the rest', $failRun['failed'] >= 1 && $failRun['sent_app'] >= 1 && (int) $db->query("SELECT COUNT(*) FROM secretary_task_reminder_log WHERE status = 'failed' AND channel = 'email'")->fetchColumn() >= 1);
$db->exec("UPDATE secretaries SET status = 'left' WHERE id = {$S2['id']}");
check('a secretary who has Left is not reminded, and nor is one with the My Tasks switch off', !array_filter($T->dueReminders($at("$T0 08:00")), fn($j) => in_array($j['user_id'], [$S2['user_id'], $S3['user_id']], true)));
$db->exec("UPDATE secretaries SET status = 'active' WHERE id = {$S2['id']}");

section('The cron script');
$script = escapeshellarg(__DIR__ . '/../tools/secretary_task_reminders.php');
$db->exec('DELETE FROM secretary_task_reminder_log');
$run1 = shell_exec('"' . PHP_BINARY . '"' . $flags . ' ' . $script . ' --host=test.local --https --now="2031-03-10 08:00" --dry-run 2>&1');
check('--now --dry-run runs, names the Nairobi time and says it would send', is_string($run1) && str_contains($run1, 'DRY RUN') && str_contains($run1, '2031-03-10 08:00 EAT') && str_contains($run1, 'would send'), (string) $run1);
$run2 = shell_exec('"' . PHP_BINARY . '"' . $flags . ' ' . $script . ' --host=test.local --https --now="2031-03-10 08:00" --no-send 2>&1');
check('--no-send claims the reminders without calling push or mail', is_string($run2) && preg_match('/sent app (\d+), email (\d+)/', $run2, $m) && (int) $m[1] > 0 && (int) $db->query('SELECT COUNT(*) FROM secretary_task_reminder_log')->fetchColumn() > 0, (string) $run2);
$run3 = shell_exec('"' . PHP_BINARY . '"' . $flags . ' ' . $script . ' --host=test.local --https --now="2031-03-10 08:00" --no-send 2>&1');
check('a second run sends nothing', is_string($run3) && str_contains($run3, 'sent app 0, email 0'), (string) $run3);
check('a bad --now is refused', str_contains((string) shell_exec('"' . PHP_BINARY . '"' . $flags . ' ' . $script . ' --now="nonsense" 2>&1'), 'Bad --now'));

section('The activity record');
$E = new SecretaryEnquiryService($db);
$eid = (int) $E->add($S1, $E->validate(['name' => "Brian $run", 'phone' => '0712345678', 'course' => 'Data Science', 'source' => 'phone', 'enquiry_date' => $today, 'follow_up_date' => '', 'note' => 'hello'])[0])['id'];
$E->setStatus($S1, $eid, 'called_back');
check('enquiries write to it through the same function (added, note, status), once each, with her name', (int) $db->query("SELECT COUNT(*) FROM secretary_activity WHERE area = 'enquiries' AND subject = 'Brian $run' AND actor_name = 'Mercy Wanjiku'")->fetchColumn() === 3);
require_once __DIR__ . '/../services/SecretaryBlogService.php';
$bs = new SecretaryBlogService($db);
$bs->log('updated', null, null, $S1['id'], $S1['user_id'], 'Mercy Wanjiku', 'secretary', "Blog $run", null);
$bs->log('approved', null, null, $S1['id'], $admin, 'Simon Admin', 'admin', "Blog $run", null);
check('blog actions by a secretary are recorded (edited); the admin\'s approval is not a secretary\'s activity', (int) $db->query("SELECT COUNT(*) FROM secretary_activity WHERE area = 'blogs' AND subject = 'Blog $run' AND action = 'edited'")->fetchColumn() === 1 && (int) $db->query("SELECT COUNT(*) FROM secretary_activity WHERE area = 'blogs' AND subject = 'Blog $run'")->fetchColumn() === 1);
$bl0 = (int) $db->query('SELECT COUNT(*) FROM secretary_activity')->fetchColumn();
$db->exec("DELETE FROM secretary_activity WHERE area IN ('enquiries', 'blogs')");
$tmp = __DIR__ . '/sec_mig_tmp_' . getmypid(); mkdir($tmp); copy(__DIR__ . '/../databases/required_migrations/132_secretary_activity.sql', "$tmp/132_secretary_activity.sql");
$db->exec("DELETE FROM schema_migrations WHERE filename = '132_secretary_activity.sql'");
$mr = (new MigrationService($db, $tmp))->applyPending(static function (string $m): void {});
$after1 = (int) $db->query("SELECT COUNT(*) FROM secretary_activity WHERE area IN ('enquiries', 'blogs')")->fetchColumn();
$db->exec("DELETE FROM schema_migrations WHERE filename = '132_secretary_activity.sql'");
(new MigrationService($db, $tmp))->applyPending(static function (string $m): void {});
array_map('unlink', glob("$tmp/*")); rmdir($tmp);
check('the migration copies what the blog and enquiry logs already hold, once (running it again adds nothing)', $mr['ran'] === 1 && $after1 > 0 && $after1 === (int) $db->query("SELECT COUNT(*) FROM secretary_activity WHERE area IN ('enquiries', 'blogs')")->fetchColumn()
    && (int) $db->query("SELECT COUNT(*) FROM secretary_activity WHERE area = 'enquiries' AND subject = 'Brian $run'")->fetchColumn() === 3, "after1=$after1");
check('every record has a name, a time and a source key', !$db->query("SELECT 1 FROM secretary_activity WHERE actor_name = '' OR created_at IS NULL OR dedupe_key = ''")->fetchColumn());
check('the same dedupe key can never be in the table twice (a UNIQUE key)', (int) $db->query('SELECT COUNT(*) - COUNT(DISTINCT dedupe_key) FROM secretary_activity')->fetchColumn() === 0);
check('the four areas are in it', count(array_intersect(['blogs', 'enquiries', 'tasks', 'diary'], array_column($db->query('SELECT DISTINCT area FROM secretary_activity')->fetchAll(PDO::FETCH_ASSOC), 'area'))) === 4);

// for secretary_workspace_http.sh
$db->exec("DELETE FROM secretary_task_reminder_log");
$out = [];
foreach (['S1' => $S1, 'S2' => $S2, 'S3' => $S3, 'S4' => $S4, 'S5' => $S5] as $k => $s) $out[$k] = ['id' => $s['user_id'], 'sid' => $s['id'], 'email' => $s['email_addr']];
$out['admin'] = ['id' => $admin, 'email' => "admin$run@ws.test"]; $out['super'] = ['id' => $super, 'email' => "super$run@ws.test"]; $out['tutor'] = ['id' => $tutor, 'email' => "tutor$run@ws.test"];
$out['_run'] = $run; $out['_task1'] = $id1; $out['_task_f'] = (int) $db->query("SELECT id FROM secretary_tasks WHERE title = 'Faith task $run'")->fetchColumn();
file_put_contents((getenv('TMPDIR') ?: getenv('TEMP') ?: sys_get_temp_dir()) . '/secretary_workspace_users.json', json_encode($out));
echo "\n" . ($fails === 0 ? "All $n checks passed." : "$fails of $n checks FAILED.") . "\n";
exit($fails === 0 ? 0 : 1);
