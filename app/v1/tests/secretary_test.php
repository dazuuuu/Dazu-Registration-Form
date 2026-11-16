<?php
// Secretary role: form checks, the automatic ID number (also when two are created at the same moment), temporary password and email,
// switches, the server-side guard's data rules, and the two migrations (including running them twice).
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/secretary_test.php      (add DAZU_DB_DRIVER/HOST/PORT/USER/PASS like the other tests when needed)
// Uses a scratch database that has all migrations applied. It refuses to run on the database named in config/database.php. Writes the logins it made to <temp>/secretary_users.json for secretary_http.sh.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$configured = (require __DIR__ . '/../config/database.php')['dbname'] ?? '';
if (!getenv('DAZU_DB_NAME') || getenv('DAZU_DB_NAME') === $configured) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database (not the one in config/database.php).\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SecretaryService.php';
require_once __DIR__ . '/../services/MigrationService.php';

$db = \Models\Connection::getInstance()->getConnection();

// ---- child process for the "same moment" check: waits for the start time, creates one secretary, prints the ID number
if (($argv[1] ?? '') === 'child') {
    $start = (float) $argv[2];
    while (microtime(true) < $start) usleep(200);
    $svc = new SecretaryService($db, fn() => ['ok' => true, 'message' => 'sent']);
    [$d, $e] = $svc->validate(['first_name' => 'Race', 'last_name' => 'N' . $argv[3], 'email' => 'race' . $argv[3] . '@sec.test', 'phone' => '0712345678', 'branch' => 'thika', 'birth_day' => 3, 'birth_month' => 4, 'start_date' => '2026-01-05']);
    $r = $e ? ['ok' => false, 'error' => implode('; ', $e)] : $svc->create($d, null, 0);
    echo json_encode($r) . "\n";
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
$yy = date('y');
$good = ['first_name' => 'Grace', 'last_name' => 'Wanjiru', 'email' => "Grace$run@sec.test", 'phone' => '0712 345 678', 'branch' => 'nairobi', 'job_title' => '', 'birth_day' => 29, 'birth_month' => 2,
         'start_date' => '2026-11-02', 'emergency_name' => 'Mum', 'emergency_phone' => '+254700111222'];
$mails = [];
$svc = new SecretaryService($db, function ($to, $name, $subject, $html) use (&$mails) { $mails[] = compact('to', 'name', 'subject', 'html'); return ['ok' => true, 'message' => 'sent']; });

section('Migrations 122 and 123');
$tables = fn(string $t) => (int) $db->query("SELECT COUNT(*) FROM $t")->fetchColumn();
check('the secretary role exists exactly once', (int) $db->query("SELECT COUNT(*) FROM roles WHERE role_name = 'secretary'")->fetchColumn() === 1);
check('both tables exist', $tables('secretaries') >= 0 && $tables('secretary_switches') >= 0);
$before = [$tables('secretaries'), $tables('secretary_switches'), (int) $db->query('SELECT COUNT(*) FROM roles')->fetchColumn()];
$tmp = __DIR__ . '/sec_mig_tmp_' . getmypid(); mkdir($tmp);
foreach (['122_secretaries.sql', '123_secretary_switches.sql'] as $f) copy(__DIR__ . '/../databases/required_migrations/' . $f, "$tmp/$f");
$db->exec("DELETE FROM schema_migrations WHERE filename IN ('122_secretaries.sql', '123_secretary_switches.sql')");
$r = (new MigrationService($db, $tmp))->applyPending(static function (string $m): void {});
array_map('unlink', glob("$tmp/*")); rmdir($tmp);
check('running them a second time works and changes nothing', $r['ran'] === 2 && empty($r['failed'])
    && $before === [$tables('secretaries'), $tables('secretary_switches'), (int) $db->query('SELECT COUNT(*) FROM roles')->fetchColumn()]
    && (int) $db->query("SELECT COUNT(*) FROM roles WHERE role_name = 'secretary'")->fetchColumn() === 1,
    json_encode([$r['ran'], $r['failed'] ?? null, $before, [$tables('secretaries'), $tables('secretary_switches')]]));
$files = glob(__DIR__ . '/../databases/required_migrations/*.sql');
$nums = array_map(fn($f) => (int) basename($f), $files);
check('they use the next free numbers after 121', in_array(122, $nums, true) && in_array(123, $nums, true) && count(array_keys($nums, 122, true)) === 1 && count(array_keys($nums, 123, true)) === 1);

section('The form checks');
[$d, $e] = $svc->validate($good);
check('a good form passes and the email is lower-cased', !$e && $d['email'] === "grace$run@sec.test", implode('|', $e));
check('the job title is Secretary when left empty', $d['job_title'] === 'Secretary');
check('29 February is accepted as a birthday', $d['birth_day'] === 29 && $d['birth_month'] === 2);
check('the switches start as agreed: approval on, calendar view only, ID card off, the rest on',
    $d['switches'] === ['blogs_approval' => 'on', 'blogs' => 'on', 'staff_calendar' => 'view', 'enquiries' => 'on', 'tasks' => 'on', 'room_visitors' => 'on', 'id_card' => 'off']);
foreach ([['first_name', ''], ['last_name', ''], ['email', 'nope'], ['phone', 'abc'], ['branch', 'mombasa'], ['birth_month', 13], ['birth_day', 31, 'birth_month', 4], ['start_date', '2026-13-45'], ['start_date', ''],
          ['emergency_phone', 'x!'], ['birth_year', '1800']] as $bad) {
    $in = $good; $in[$bad[0]] = $bad[1]; if (isset($bad[2])) $in[$bad[2]] = $bad[3];
    [, $er] = $svc->validate($in);
    check('rejected: ' . $bad[0] . ' = ' . json_encode($bad[1]) . (isset($bad[2]) ? ' with ' . $bad[2] . ' = ' . $bad[3] : ''), count($er) >= 1);
}
[$d2] = $svc->validate($good + ['sw' => ['staff_calendar' => 'hack', 'id_card' => 'on', 'blogs_approval' => ['x'], 'unknown' => 'on']]);
check('an unknown switch value falls back, a known one is taken, unknown switches are ignored', $d2['switches']['staff_calendar'] === 'view' && $d2['switches']['id_card'] === 'on' && $d2['switches']['blogs_approval'] === 'on' && !isset($d2['switches']['unknown']));
[, $er] = $svc->validate($good + ['emergency_name' => '', 'emergency_phone' => '']);
check('the emergency contact is optional', !$er);

section('Creating a secretary');
$adminId = (int) $db->query("SELECT MIN(id) FROM users")->fetchColumn();
$res = $svc->create($d, '/uploads/secretaries/x.jpg', $adminId);
check('it is saved', !empty($res['ok']), json_encode($res));
check('her ID number is DTI/SEC/NNN/yy with the year of today', (bool) preg_match('#^DTI/SEC/\d{3}/' . $yy . '$#', $res['staff_no']), $res['staff_no'] ?? '');
$row = $db->query('SELECT s.*, u.role_id, u.must_change_password, u.is_active, u.password_hash, u.email, r.role_name FROM secretaries s JOIN users u ON u.id = s.user_id JOIN roles r ON r.id = u.role_id WHERE s.id = ' . (int) $res['secretary_id'])->fetch(PDO::FETCH_ASSOC);
check('her login has the secretary role (not admin, staff, tutor or student) and is active', $row['role_name'] === 'secretary' && (int) $row['is_active'] === 1);
check('she must choose her own password at first sign-in', (int) $row['must_change_password'] === 1);
check('the details were stored', $row['branch'] === 'nairobi' && $row['status'] === 'active' && $row['phone'] === '0712 345 678' && (int) $row['birth_day'] === 29 && (int) $row['birth_month'] === 2
    && $row['start_date'] === '2026-11-02' && $row['emergency_name'] === 'Mum' && $row['photo_path'] === '/uploads/secretaries/x.jpg' && (int) $row['added_by'] === $adminId && $row['job_title'] === 'Secretary');
check('the email went out once, to her address, with the temporary password that works', count($mails) === 1 && $mails[0]['to'] === "grace$run@sec.test"
    && preg_match('#Temporary password: <strong>([0-9a-f]{16})</strong>#', $mails[0]['html'], $m) && password_verify($m[1], $row['password_hash']));
check('the email names her email, has a sign-in link and the Dazu green', str_contains($mails[0]['html'], "grace$run@sec.test") && str_contains($mails[0]['html'], '/login') && str_contains($mails[0]['html'], '#048C44'), substr($mails[0]['html'], 0, 600));
check('credentials_sent_at is set', !empty($db->query('SELECT credentials_sent_at FROM secretaries WHERE id = ' . (int) $res['secretary_id'])->fetchColumn()));
$sw = secretary_switches($db, (int) $res['secretary_id']);
check('her switches were saved', $sw === $d['switches'], json_encode($sw));
check('a switch with no row counts as off', ($db->exec('DELETE FROM secretary_switches WHERE secretary_id = ' . (int) $res['secretary_id'] . " AND switch_key = 'tasks'") || true) && secretary_switches($db, (int) $res['secretary_id'])['tasks'] === 'off');

$res2 = $svc->create($svc->validate(array_merge($good, ['email' => "second$run@sec.test"]))[0], null, $adminId);
check('the next secretary gets the next number', (int) substr($res2['staff_no'], 8, 3) === (int) substr($res['staff_no'], 8, 3) + 1, $res2['staff_no']);
[, $er] = $svc->validate(array_merge($good, ['email' => strtoupper("grace$run@sec.test")]));
check('the same email twice is refused (any capitals)', count($er) === 1 && str_contains($er[0], 'already exists'));
$cnt = fn() => [$tables('secretaries'), $tables('users')];
$c0 = $cnt();
$dup = $svc->create($d, null, $adminId);
check('even if the form check is skipped, a duplicate email saves nothing at all', empty($dup['ok']) && $cnt() === $c0);
check('the ID number cannot be reused: the database refuses a second secretary with the same number', (function () use ($db, $res, $res2) {
    try { $db->prepare('UPDATE secretaries SET staff_no = ? WHERE id = ?')->execute([$res['staff_no'], (int) $res2['secretary_id']]); return false; }
    catch (PDOException $e) { return true; }
})());
$no1 = $res['staff_no'];
$svc->resendCredentials((int) $res['secretary_id']);
check('sending the login again changes the password and the must-change flag but never her ID number', count($mails) === 3 && (string) $db->query('SELECT staff_no FROM secretaries WHERE id = ' . (int) $res['secretary_id'])->fetchColumn() === $no1);
$failSvc = new SecretaryService($db, fn() => ['ok' => false, 'message' => 'SMTP down']);
$res3 = $failSvc->create($failSvc->validate(array_merge($good, ['email' => "third$run@sec.test"]))[0], null, $adminId);
check('if the email fails she is still saved and the admin is told', !empty($res3['ok']) && $res3['email_sent'] === false && $res3['email_error'] === 'SMTP down');
$offSvc = new SecretaryService($db, fn() => ['ok' => true, 'message' => 'skipped', 'skipped' => true]);
$res4 = $offSvc->create($offSvc->validate(array_merge($good, ['email' => "fourth$run@sec.test"]))[0], null, $adminId);
check('mail switched off is not reported as sent', !empty($res4['ok']) && $res4['email_sent'] === false);

section('Two secretaries created at the same moment');
$procs = []; $start = microtime(true) + 3.0; $N = 8;
$flags = extension_loaded('pdo_pgsql') ? ' -d extension=pgsql -d extension=pdo_pgsql' : '';
$pipes = [];
for ($i = 1; $i <= $N; $i++) {
    $cmd = '"' . PHP_BINARY . '"' . $flags . ' ' . escapeshellarg(__FILE__) . ' child ' . $start . ' ' . $run . $i;
    $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$i]);
}
$nos = []; $okc = 0;
foreach ($procs as $i => $p) {
    $out = stream_get_contents($pipes[$i][1]); $err = stream_get_contents($pipes[$i][2]); proc_close($p);
    $j = json_decode(trim(strrchr("\n" . trim($out), "\n")), true);
    if (!empty($j['ok'])) { $okc++; $nos[] = $j['staff_no']; } else echo "  child $i: " . trim($out . ' ' . $err) . "\n";
}
check("all $N were created", $okc === $N, "created $okc");
check('every one got a different ID number', count(array_unique($nos)) === $N);
sort($nos);
$nums = array_map(fn($x) => (int) substr($x, 8, 3), $nos);
check('the numbers are one after the other with no gap', $nums === range(min($nums), min($nums) + $N - 1), implode(', ', $nos));
check('the database holds no duplicate ID numbers', (int) $db->query('SELECT COUNT(*) - COUNT(DISTINCT staff_no) FROM secretaries')->fetchColumn() === 0);

section('The guard reads the database');
$mk = function (string $key, string $role, string $status = 'active', int $active = 1, int $mustChange = 0, array $switches = []) use ($db, $run, &$seq) {
    $rid = (int) $db->query("SELECT id FROM roles WHERE role_name = '$role'")->fetchColumn();
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status, must_change_password) VALUES (?, ?, ?, ?, ?, 1, 'active', ?)")
       ->execute(["sg_$key$run", "$key$run@sec.test", password_hash('x', PASSWORD_DEFAULT), $rid, $active, $mustChange]);
    $uid = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$uid, ucfirst($key), 'Test']);
    if ($role === 'secretary') {
        $db->prepare("INSERT INTO secretaries (user_id, staff_no, branch, status) VALUES (?, ?, 'online', ?)")->execute([$uid, sprintf('DTI/SEC/T%s%d/99', $run, ++$seq), $status]);
        $sid = (int) $db->lastInsertId();
        foreach ($switches as $k => $v) $db->prepare('INSERT INTO secretary_switches (secretary_id, switch_key, switch_value) VALUES (?, ?, ?)')->execute([$sid, $k, $v]);
    }
    return ['id' => $uid, 'email' => "$key$run@sec.test"];
};
$seq = 0;
$logins = [
    'sec_ok'     => $mk('secok', 'secretary', 'active', 1, 0, ['enquiries' => 'on', 'staff_calendar' => 'view', 'id_card' => 'off']),
    'sec_edit'   => $mk('secedit', 'secretary', 'active', 1, 0, ['staff_calendar' => 'edit']),
    'sec_new'    => $mk('secnew', 'secretary', 'active', 1, 1, ['enquiries' => 'on']),
    'sec_leave'  => $mk('secleave', 'secretary', 'on_leave', 1, 0, ['enquiries' => 'on']),
    'sec_left'   => $mk('secleft', 'secretary', 'left', 1, 0, ['enquiries' => 'on']),
    'sec_off'    => $mk('secoff', 'secretary', 'active', 0, 0, ['enquiries' => 'on']),
    'student'    => $mk('stud', 'student'),
    'tutor'      => $mk('tut', 'tutor'),
    'admin'      => $mk('adm', 'admin'),
    'super'      => $mk('sup', 'superadmin'),
];
$sec = fn(string $k) => secretary_by_user($db, $logins[$k]['id']);
check('her record is found by her login, with her name, and the role name comes from the database', ($sec('sec_ok')['full_name'] ?? '') === 'Secok Test' && ($sec('sec_ok')['role_name'] ?? '') === 'secretary');
check('a student, tutor, admin and the super admin have no secretary record', !secretary_by_user($db, $logins['student']['id']) && !secretary_by_user($db, $logins['tutor']['id']) && !secretary_by_user($db, $logins['admin']['id']) && !secretary_by_user($db, $logins['super']['id']));
$swk = fn(string $k) => secretary_switches($db, (int) $sec($k)['id']);
check('switch rules: on/off, view and edit', secretary_switch_allows($swk('sec_ok')['enquiries'], 'on') && !secretary_switch_allows($swk('sec_ok')['id_card'], 'on')
    && secretary_switch_allows($swk('sec_ok')['staff_calendar'], 'view') && !secretary_switch_allows($swk('sec_ok')['staff_calendar'], 'edit')
    && secretary_switch_allows($swk('sec_edit')['staff_calendar'], 'edit') && !secretary_switch_allows('off', 'view'));
check('the person name helper gives the name only', secretary_person_name($db, $logins['admin']['id']) === 'Adm Test');

$logins['_run'] = $run;
file_put_contents((getenv('TMPDIR') ?: getenv('TEMP') ?: sys_get_temp_dir()) . '/secretary_users.json', json_encode($logins));
echo "\n" . ($fails === 0 ? "All $n checks passed." : "$fails of $n checks FAILED.") . "\n";
exit($fails === 0 ? 0 : 1);
