<?php
// The secretary enquiries log: adding, the shared list and search, status / follow-up / notes / take over, the history, no delete, prospects only (no student record), the dashboard numbers,
// and the follow-up reminders at 8:00 am Nairobi time (always at a TEST time with no real push or email). Also makes the users secretary_enquiries_http.sh signs in as.
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/secretary_enquiries_test.php      (a scratch database with all migrations applied; it refuses the database named in config/database.php)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$configured = (require __DIR__ . '/../config/database.php')['dbname'] ?? '';
if (!getenv('DAZU_DB_NAME') || getenv('DAZU_DB_NAME') === $configured) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database (not the one in config/database.php).\n"); exit(2); }
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SecretaryAdminService.php';
require_once __DIR__ . '/../services/SecretaryEnquiryService.php';
require_once __DIR__ . '/../services/MigrationService.php';
require_once __DIR__ . '/../helpers/secretary_ui.php';

$db = \Models\Connection::getInstance()->getConnection();
$schemaNow = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'DATABASE()' : 'current_schema()';   // the current database, on MySQL and on PostgreSQL
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
$svc = new SecretaryService($db, fn() => ['ok' => true, 'message' => 'sent']);
$adm = new SecretaryAdminService($db);
$mk = function (string $key, string $first, string $last, array $sw, string $status = 'active') use ($svc, $adm, $db, $run, $pw): array {
    [$d, $e] = $svc->validate(['first_name' => $first, 'last_name' => $last, 'email' => "$key$run@enq.test", 'phone' => '0712345678', 'branch' => 'nairobi', 'birth_day' => 3, 'birth_month' => 5, 'start_date' => '2026-01-05', 'sw' => $sw]);
    if ($e) throw new RuntimeException(implode('; ', $e));
    $r = $svc->create($d, null, 0);
    $db->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')->execute([$pw, (int) $r['user_id']]);
    if ($status !== 'active') $adm->setStatus((int) $r['secretary_id'], $status, 0);
    $sec = secretary_by_user($db, (int) $r['user_id']); $sec['switches'] = secretary_switches($db, (int) $r['secretary_id']);
    return $sec + ['email_addr' => "$key$run@enq.test"];
};
$on = ['enquiries' => 'on'];
$A = $mk('mercy', 'Mercy', 'Wanjiku', $on); $B = $mk('faith', 'Faith', 'Achieng', $on); $C = $mk('grace', 'Grace', 'Njeri', $on, 'on_leave');
$D = $mk('noenq', 'Nora', 'Noenq', ['enquiries' => 'off']); $L = $mk('lucy', 'Lucy', 'Left', $on, 'left');
$E = new SecretaryEnquiryService($db);
$todayN = SecretaryEnquiryService::today();
$good = ['name' => "Brian Otieno $run", 'phone' => '0712 345 678', 'email' => 'Brian@Example.com', 'course' => 'Data Science', 'source' => 'phone', 'enquiry_date' => $todayN, 'follow_up_date' => $todayN, 'note' => 'Asked about fees.'];

section('Migrations 127 and 128');
$tmp = __DIR__ . '/sec_mig_tmp_' . getmypid(); mkdir($tmp);
foreach (['127_secretary_enquiries.sql', '128_secretary_enquiry_reminder_log.sql'] as $f) copy(__DIR__ . '/../databases/required_migrations/' . $f, "$tmp/$f");
$db->exec("DELETE FROM schema_migrations WHERE filename IN ('127_secretary_enquiries.sql', '128_secretary_enquiry_reminder_log.sql')");
$r = (new MigrationService($db, $tmp))->applyPending(static function (string $m): void {});
array_map('unlink', glob("$tmp/*")); rmdir($tmp);
check('they run a second time and change nothing', $r['ran'] === 2 && empty($r['failed']));
check('they are the next free numbers after 126', is_file(__DIR__ . '/../databases/required_migrations/127_secretary_enquiries.sql') && count(glob(__DIR__ . '/../databases/required_migrations/127_*')) === 1 && count(glob(__DIR__ . '/../databases/required_migrations/128_*')) === 1);
$cols = array_column($db->query("SELECT column_name AS cn FROM information_schema.columns WHERE table_schema = $schemaNow AND table_name = 'secretary_enquiries'")->fetchAll(PDO::FETCH_ASSOC), 'cn');
check('prospects only: the table has no link to a student, a registration, a payment or a course id', !array_filter($cols, fn($c) => preg_match('/student|registration|payment|course_id|user_id/i', $c)), implode(',', $cols));
$src = file_get_contents(__DIR__ . '/../services/SecretaryEnquiryService.php') . file_get_contents(__DIR__ . '/../../../public/secretary/enquiries/index.php') . file_get_contents(__DIR__ . '/../../../public/secretary/enquiries/view.php')
     . file_get_contents(__DIR__ . '/../../../public/admin/secretaries/enquiries.php') . file_get_contents(__DIR__ . '/../../../public/admin/secretaries/enquiry.php');
check('no code of the log mentions the registrations, students, payments or student_ tables', !preg_match('/\b(FROM|JOIN|INTO|UPDATE)\s+(registrations|students|student_\w+|payments|orders)\b/i', $src));
$allTables = array_column($db->query("SELECT table_name AS tn FROM information_schema.tables WHERE table_schema = $schemaNow")->fetchAll(PDO::FETCH_ASSOC), 'tn');
preg_match_all('/(?:FROM|JOIN)\s+(\w+)/i', file_get_contents(__DIR__ . '/../services/SecretaryEnquiryService.php'), $m);
check('the only existing tables it reads are the course names (read only) and the secretary own name (staff): nothing about students', !array_diff(array_intersect(array_unique($m[1]), $allTables), ['secretary_enquiries', 'secretary_enquiry_notes', 'secretary_enquiry_history', 'secretary_enquiry_reminder_log', 'courses', 'secretaries', 'users', 'user_profiles', 'secretary_switches']), json_encode(array_intersect(array_unique($m[1]), $allTables)));

section('The add form');
[$d, $e] = $E->validate($good);
check('a good form passes; the email is lower-cased', !$e && $d['email'] === 'brian@example.com', implode('|', $e));
foreach ([['name', ''], ['phone', 'abc'], ['email', 'nope'], ['course', ''], ['source', 'telegram'], ['enquiry_date', '2026-13-45'], ['follow_up_date', 'tomorrow'], ['note', str_repeat('x', 1001)]] as [$k, $v]) {
    [, $er] = $E->validate([$k => $v] + $good);
    check("refused: $k = " . json_encode(mb_substr((string) $v, 0, 12)), count($er) >= 1);
}
[$d2, $e2] = $E->validate(['email' => '', 'follow_up_date' => '', 'note' => ''] + $good);
check('the email, the follow-up date and the note are optional', !$e2 && $d2['email'] === null && $d2['follow_up_date'] === null);
check('the course can be typed or chosen: the course names are read from the existing courses', is_array($E->courseNames()));
check('all five sources and four statuses exist', array_keys(SecretaryEnquiryService::SOURCES) === ['phone', 'walk_in', 'website', 'social', 'other'] && array_values(SecretaryEnquiryService::STATUSES) === ['Pending', 'Called back', 'Registered', 'Not interested']);

section('Adding, and everyone sees everything');
$r = $E->add($A, $d);
$id1 = (int) $r['id'];
$row = $db->query("SELECT * FROM secretary_enquiries WHERE id = $id1")->fetch(PDO::FETCH_ASSOC);
check('it is saved as Pending, with who added it (name only) and the dates', !empty($r['ok']) && $row['status'] === 'pending' && $row['added_by_name'] === 'Mercy Wanjiku' && (int) $row['added_by'] === $A['id'] && $row['follow_up_date'] === $todayN && $row['source'] === 'phone');
$g = $E->get($id1);
check('the first note is there with its writer and time', count($g['notes']) === 1 && $g['notes'][0]['author_name'] === 'Mercy Wanjiku' && $g['notes'][0]['body'] === 'Asked about fees.' && !empty($g['notes'][0]['created_at']));
check('the history says created (and the note) with name and time', ($g['history'][count($g['history']) - 1]['action'] ?? '') === 'created' && $g['history'][count($g['history']) - 1]['actor_name'] === 'Mercy Wanjiku' && !empty($g['history'][0]['created_at']));
$r2 = $E->add($B, $E->validate(['name' => "Lucy Wambui $run", 'phone' => '0733111222', 'course' => 'Cyber Security', 'source' => 'walk_in', 'enquiry_date' => $todayN, 'follow_up_date' => '2031-03-12'] + $good)[0]);
$id2 = (int) $r2['id'];
[$rowsA] = $E->search('', '', 500); [$rowsB] = $E->search('', '', 500);
check('every secretary sees every enquiry (the list does not depend on who she is)', in_array($id1, array_column($rowsA, 'id'), true) && in_array($id2, array_column($rowsB, 'id'), true) && $rowsA === $rowsB);
check('each row has the name of who added it, never a role', ($rowsA[0]['added_by_name'] ?? '') !== '' && !array_key_exists('role', $rowsA[0]) && !preg_match('/secretary|admin/i', (string) $rowsA[0]['added_by_name']));

section('Search and filters');
check('search by name, phone, email, course (any capitals)', in_array($id1, array_column($E->search("brian otieno $run")[0], 'id'), true) && in_array($id1, array_column($E->search('0712 345')[0], 'id'), true)
    && in_array($id1, array_column($E->search('BRIAN@EXAMPLE')[0], 'id'), true) && in_array($id2, array_column($E->search('cyber sec')[0], 'id'), true));
check('a search with % or _ is a plain text search, not a wildcard', $E->search('%')[1] === 0 && $E->search('_')[1] === 0 && $E->search("' OR 1=1 --")[1] === 0);
check('the status filter', in_array($id1, array_column($E->search('', 'pending')[0], 'id'), true) && !in_array($id1, array_column($E->search('', 'registered')[0], 'id'), true));
$E->setStatus($A, $id2, 'called_back');
$st = $E->stats();
check('the four boxes count: pending, follow up today, called back, registered', $st['pending'] >= 1 && $st['called_back'] >= 1 && $st['follow_today'] >= 1 && isset($st['registered']));
check('"follow up today" lists only a follow-up of today that is still Pending or Called back', in_array($id1, array_column($E->search('', '', 100, 0, true)[0], 'id'), true) && !in_array($id2, array_column($E->search('', '', 100, 0, true)[0], 'id'), true));
$E->setStatus($B, $id1, 'registered');
check('a Registered enquiry leaves "follow up today"', !in_array($id1, array_column($E->search('', '', 100, 0, true)[0], 'id'), true));
$E->setStatus($B, $id1, 'pending');

section('Status, follow-up, notes, take over');
$hCount = fn(int $id, string $a) => (int) $db->query("SELECT COUNT(*) FROM secretary_enquiry_history WHERE enquiry_id = $id AND action = '$a'")->fetchColumn();
$r = $E->setStatus($B, $id1, 'called_back');
$h1 = $db->query("SELECT * FROM secretary_enquiry_history WHERE enquiry_id = $id1 AND action = 'status' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('she changes the status to Called back; the history has old and new, her name and the time', $r['ok'] && $r['changed'] && $h1['old_value'] === 'Pending' && $h1['new_value'] === 'Called back' && $h1['actor_name'] === 'Faith Achieng' && !empty($h1['created_at']));
$c0 = $hCount($id1, 'status');
check('the same status again is not recorded twice', $E->setStatus($B, $id1, 'called_back')['changed'] === false && $hCount($id1, 'status') === $c0);
foreach (['registered', 'not_interested', 'pending'] as $s) check("status $s can be set (Registered is by hand)", $E->setStatus($A, $id1, $s)['ok'] && $db->query("SELECT status FROM secretary_enquiries WHERE id = $id1")->fetchColumn() === $s);
check('an unknown status or enquiry is refused', !$E->setStatus($A, $id1, 'student')['ok'] && !$E->setStatus($A, 999999, 'pending')['ok']);
$r = $E->setFollowUp($B, $id1, '2031-05-05');
check('the follow-up date changes and is recorded', $r['ok'] && $db->query("SELECT follow_up_date FROM secretary_enquiries WHERE id = $id1")->fetchColumn() === '2031-05-05' && $hCount($id1, 'follow_up') === 1);
check('it can be cleared; a wrong date is refused', $E->setFollowUp($B, $id1, '')['ok'] && $db->query("SELECT follow_up_date FROM secretary_enquiries WHERE id = $id1")->fetchColumn() === null && !$E->setFollowUp($B, $id1, '2031-02-31')['ok']);
$E->setFollowUp($A, $id1, $todayN);
$r = $E->addNote($B, $id1, "Called, will visit on Friday. <b>x</b>");
$g = $E->get($id1); $last = end($g['notes']);
check('a note shows who wrote it and when (the text is stored as typed and escaped when shown)', $r['ok'] && $last['author_name'] === 'Faith Achieng' && !empty($last['created_at']) && $last['body'] === 'Called, will visit on Friday. <b>x</b>');
check('an empty or long note is refused', !$E->addNote($B, $id1, '  ')['ok'] && !$E->addNote($B, $id1, str_repeat('x', 1001))['ok']);
$r = $E->takeOver($B, $id1);
$g = $E->get($id1);
check('she takes it over: it is hers, still visible to all, and the history says from whom', $r['ok'] && (int) $g['handled_by'] === $B['id'] && $g['handled_by_name'] === 'Faith Achieng' && $g['added_by_name'] === 'Mercy Wanjiku' && $g['history'][0]['action'] === 'taken_over' && $g['history'][0]['old_value'] === 'Mercy Wanjiku' && $g['history'][0]['new_value'] === 'Faith Achieng');
check('taking over what you already have is refused', !$E->takeOver($B, $id1)['ok']);
$E->takeOver($A, $id1);
check('someone can take it back', (int) $E->get($id1)['handled_by'] === $A['id']);
$id3 = (int) $E->add($C, $E->validate(['name' => "Away Person $run"] + $good)[0])['id'];
check('an enquiry of a secretary on leave carries a small "On leave" badge for the team (nothing is blocked)', $E->get($id3)['away'] === 'On leave');
check('there is no delete anywhere in the service', !array_filter(get_class_methods($E), fn($m) => stripos($m, 'delete') !== false || stripos($m, 'remove') !== false));
check('every action above is in the history with a name and a time', !$db->query("SELECT 1 FROM secretary_enquiry_history WHERE actor_name = '' OR created_at IS NULL")->fetchColumn());

section('The dashboard numbers');
foreach (['Dash One', 'Dash Two'] as $dn) $E->add($B, $E->validate(['name' => "$dn $run"] + $good)[0]);
$secA = secretary_by_user($db, $A['user_id']); $secA['switches'] = secretary_switches($db, $A['id']);
$dd = secretary_dash_data($db, $secA, new DateTime('now', SecretaryEnquiryService::tz()));
check('the dashboard shows the pending count and the latest three pending, each with who added it', $dd['enquiries_pending'] === $E->stats()['pending'] && count($dd['enquiries']) === 3 && !array_filter($dd['enquiries'], fn($x) => ($x['added_by'] ?? '') === '' || $x['status'] !== 'Pending'));
$secD = secretary_by_user($db, $D['user_id']); $secD['switches'] = secretary_switches($db, $D['id']);
check('a secretary with the Enquiries switch off gets no enquiries on her dashboard', secretary_dash_data($db, $secD, new DateTime())['enquiries'] === []);

section('Follow-up reminders (a test time, nothing is really sent)');
$fu = '2031-03-10';
$db->exec("DELETE FROM secretary_enquiry_reminder_log");
$db->exec("UPDATE secretary_enquiries SET follow_up_date = NULL");
$mkE = fn(array $sec, string $name, string $status = 'pending') => (function () use ($E, $sec, $name, $status, $good, $fu, $db) { $i = (int) $E->add($sec, $E->validate(['name' => $name, 'follow_up_date' => $fu] + $good)[0])['id']; if ($status !== 'pending') $E->setStatus($sec, $i, $status); return $i; })();
$q1 = $mkE($A, "Reminder One $run");           // Mercy
$q2 = $mkE($B, "Reminder Two $run");           // Faith, then taken over by Mercy
$E->takeOver($A, $q2);
$q3 = $mkE($A, "Reminder Registered $run", 'registered');
$q4 = $mkE($A, "Reminder NotInterested $run", 'not_interested');
$q5 = $mkE($C, "Reminder OnLeave $run");       // Grace, on leave: still reminded
$q6 = $mkE($D, "Reminder SwitchOff $run");     // Nora: Enquiries switch off: not reminded
$q7 = $mkE($L, "Reminder Left $run");          // Lucy: Left: not reminded
$q8 = $mkE($A, "Reminder CalledBack $run", 'called_back');
$tz = SecretaryEnquiryService::tz();
$at = fn(string $t, string $z = 'Africa/Nairobi') => new DateTime($t, new DateTimeZone($z));
$users = [$A['user_id'], $B['user_id'], $C['user_id'], $D['user_id'], $L['user_id']];
check('before 8:00 am Nairobi time nothing is due', $E->dueReminders($at("$fu 07:59")) === []);
$due = $E->dueReminders($at("$fu 08:00"));
$pairs = array_map(fn($j) => $j['enquiry']['id'] . ':' . $j['user_id'], $due);
check('at 8:00 am the adder is due for her pending and called-back enquiries', in_array("$q1:" . $A['user_id'], $pairs, true) && in_array("$q8:" . $A['user_id'], $pairs, true) && in_array("$q5:" . $C['user_id'], $pairs, true));
check('the secretary who took it over is reminded as well as the one who added it', in_array("$q2:" . $B['user_id'], $pairs, true) && in_array("$q2:" . $A['user_id'], $pairs, true));
check('Registered and Not interested are not reminded; a secretary who has Left, or has the Enquiries switch off, is not reminded; On leave still is',
    !array_filter($pairs, fn($p) => in_array((int) explode(':', $p)[0], [$q3, $q4, $q6, $q7], true)) && in_array("$q5:" . $C['user_id'], $pairs, true));
check('it is 8:00 in Nairobi when it is 05:00 UTC: the same moment is due', count($E->dueReminders($at("$fu 05:00", 'UTC'))) === count($due));
check('it is still sent up to 4 hours late (12:00), never later, and not the day before or after', count($E->dueReminders($at("$fu 12:00"))) === count($due) && $E->dueReminders($at("$fu 12:01")) === [] && $E->dueReminders($at('2031-03-09 08:00')) === [] && $E->dueReminders($at('2031-03-11 08:00')) === []);
$dry = $E->reminders($at("$fu 08:00"), ['dry' => true, 'only_users' => $users]);
check('a dry run says what it would send and writes nothing', $dry['would'] > 0 && (int) $db->query('SELECT COUNT(*) FROM secretary_enquiry_reminder_log')->fetchColumn() === 0);
$ns = $E->reminders($at("$fu 08:00"), ['no_send' => true, 'only_users' => $users]);
check('a test run (no-send) claims each reminder in the log and calls nothing', $ns['sent_app'] > 0 && $ns['sent_email'] > 0 && (int) $db->query("SELECT COUNT(*) FROM secretary_enquiry_reminder_log WHERE detail = 'no-send (test)'")->fetchColumn() === $ns['sent_app'] + $ns['sent_email']);
$ns2 = $E->reminders($at("$fu 08:30"), ['no_send' => true, 'only_users' => $users]);
check('running it again sends nothing twice (the log)', $ns2['sent_app'] === 0 && $ns2['sent_email'] === 0 && $ns2['dupe'] > 0);
$db->exec("DELETE FROM secretary_enquiry_reminder_log");
$mails = [];
$nb = (int) $db->query("SELECT COUNT(*) FROM portal_notifications WHERE type = 'enquiry_followup'")->fetchColumn();
$real = $E->reminders($at("$fu 08:00"), ['only_users' => [$A['user_id']], 'mail_fn' => function ($to, $name, $subject, $html) use (&$mails) { $mails[] = compact('to', 'html'); return ['ok' => true, 'message' => 'sent']; }]);
check('a real pass (mail replaced by a recorder): the in-app notice goes through the existing notification code, with a link to the enquiry', (int) $db->query("SELECT COUNT(*) FROM portal_notifications WHERE type = 'enquiry_followup'")->fetchColumn() === $nb + $real['sent_app'] && $real['sent_app'] === 3
    && str_contains((string) $db->query("SELECT url FROM portal_notifications WHERE type = 'enquiry_followup' ORDER BY id DESC LIMIT 1")->fetchColumn(), 'secretary/enquiries/view.php?id='));
check('one email per reminder to her address, escaped, with the Dazu green button', count($mails) === 3 && $mails[0]['to'] === $A['email_addr'] && str_contains($mails[0]['html'], '#048C44') && str_contains($mails[0]['html'], 'Follow up today'));
$real2 = $E->reminders($at("$fu 08:05"), ['only_users' => [$A['user_id']], 'mail_fn' => function () use (&$mails) { $mails[] = 'again'; return ['ok' => true]; }]);
check('and nothing again on the next pass', $real2['sent_app'] === 0 && $real2['sent_email'] === 0 && count($mails) === 3);
$failRun = $E->reminders($at("$fu 08:00"), ['only_users' => [$C['user_id']], 'mail_fn' => function () { throw new RuntimeException('SMTP down'); }]);
check('a mail failure is logged and does not stop the rest', $failRun['failed'] >= 1 && $failRun['sent_app'] >= 1 && (int) $db->query("SELECT COUNT(*) FROM secretary_enquiry_reminder_log WHERE status = 'failed' AND channel = 'email'")->fetchColumn() >= 1);

section('The cron script');
$flags = extension_loaded('pdo_pgsql') ? ' -d extension=pgsql -d extension=pdo_pgsql' : '';
$run1 = shell_exec('"' . PHP_BINARY . '"' . $flags . ' ' . escapeshellarg(__DIR__ . '/../tools/secretary_enquiry_reminders.php') . ' --host=test.local --https --now="2031-03-10 08:00" --dry-run 2>&1');
check('--now --dry-run runs, names the Nairobi time and says it would send', is_string($run1) && str_contains($run1, 'DRY RUN') && str_contains($run1, '2031-03-10 08:00 EAT') && str_contains($run1, 'would send'), (string) $run1);
$db->exec("DELETE FROM secretary_enquiry_reminder_log");
$run2 = shell_exec('"' . PHP_BINARY . '"' . $flags . ' ' . escapeshellarg(__DIR__ . '/../tools/secretary_enquiry_reminders.php') . ' --host=test.local --https --now="2031-03-10 08:00" --no-send 2>&1');
check('--no-send claims the reminders without calling push or mail', is_string($run2) && preg_match('/sent app (\d+), email (\d+)/', $run2, $m) && (int) $m[1] > 0 && (int) $db->query('SELECT COUNT(*) FROM secretary_enquiry_reminder_log')->fetchColumn() > 0, (string) $run2);
$run3 = shell_exec('"' . PHP_BINARY . '"' . $flags . ' ' . escapeshellarg(__DIR__ . '/../tools/secretary_enquiry_reminders.php') . ' --host=test.local --https --now="2031-03-10 08:00" --no-send 2>&1');
check('a second run sends nothing', is_string($run3) && str_contains($run3, 'sent app 0, email 0'), (string) $run3);
$run4 = shell_exec('"' . PHP_BINARY . '"' . $flags . ' ' . escapeshellarg(__DIR__ . '/../tools/secretary_enquiry_reminders.php') . ' --now="nonsense" 2>&1');
check('a bad --now is refused', is_string($run4) && str_contains($run4, 'Bad --now'));

// for secretary_enquiries_http.sh: clear the follow-ups so the page numbers are simple, and set one "today"
$db->exec("UPDATE secretary_enquiries SET follow_up_date = NULL WHERE follow_up_date <> '$todayN' OR follow_up_date IS NULL");
$out = [];
foreach (['A' => $A, 'B' => $B, 'C' => $C, 'D' => $D, 'L' => $L] as $k => $s) $out[$k] = ['id' => $s['user_id'], 'sid' => $s['id'], 'email' => $s['email_addr']];
$rid = $db->query("SELECT id FROM roles WHERE role_name = 'admin'")->fetchColumn(); $sid2 = $db->query("SELECT id FROM roles WHERE role_name = 'superadmin'")->fetchColumn();
foreach (['admin' => $rid, 'super' => $sid2] as $k => $roleId) {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["en_$k$run", "$k$run@enq.test", $pw, $roleId]);
    $uid = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$uid, ucfirst($k), 'Person']);
    $out[$k] = ['id' => $uid, 'email' => "$k$run@enq.test"];
}
$out['_run'] = $run; $out['_id1'] = $id1; $out['_id2'] = $id2;
file_put_contents((getenv('TMPDIR') ?: getenv('TEMP') ?: sys_get_temp_dir()) . '/secretary_enquiries_users.json', json_encode($out));
echo "\n" . ($fails === 0 ? "All $n checks passed." : "$fails of $n checks FAILED.") . "\n";
exit($fails === 0 ? 0 : 1);
