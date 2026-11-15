<?php
// Makes the people the tutor ID preview test (tutor_preview_http.sh) needs, in a scratch database with all migrations applied:
// an admin, two real tutors (the first has a photo), a student. Password for all of them is "x". Writes <temp>/tpv_users.json.
//   DAZU_DB_NAME=<scratch db> [DAZU_DB_DRIVER=mysql DAZU_DB_PORT=3307 DAZU_DB_HOST=127.0.0.1 DAZU_DB_USER=root DAZU_DB_PASS=] php -d extension=gd app/v1/tests/tutor_preview_setup.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/TutorIdService.php';

$db = \Models\Connection::getInstance()->getConnection();
$run = substr(md5((string) microtime(true)), 0, 6);
$pw = password_hash('x', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$out = ['_run' => $run];
$mk = function (string $key, string $role, string $first) use ($db, $pw, $roles, $run, &$out): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["v_$key$run", "$key$run@v.test", $pw, $roles[$role]]);
    $id = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, $first, 'Preview']);
    $out[$key] = ['id' => $id, 'email' => "$key$run@v.test"];
    return $id;
};
$mk('admin', 'admin', 'Ad');
$mk('student', 'student', 'Stu');
foreach (['tutor1' => 'One', 'tutor2' => 'Two'] as $k => $name) {
    $uid = $mk($k, 'tutor', $name);
    $db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$uid]);
    $out[$k]['tutor_id'] = (int) $db->lastInsertId();
}
// The Tutor portal preview needs at least one active course to show.
if (!(int) $db->query('SELECT COUNT(*) FROM courses WHERE is_active = 1')->fetchColumn()) {
    $db->prepare("INSERT INTO courses (title, abbreviation, duration_months, course_fee, is_active, qualification) VALUES ('Preview Course', ?, 6, 50000, 1, 'certificate')")->execute(['PV' . strtoupper(substr($run, 0, 4))]);
}
// A passport photo for the first tutor (a plain 4:5 picture in the private photo folder).
$img = imagecreatetruecolor(400, 500);
imagefill($img, 0, 0, imagecolorallocate($img, 120, 160, 200));
imagefilledellipse($img, 200, 200, 180, 220, imagecolorallocate($img, 240, 200, 170));
$rel = 'tutor_photos/tpv_' . $run . '.jpg';
$abs = dirname(__DIR__) . '/storage/' . $rel;
@mkdir(dirname($abs), 0775, true);
imagejpeg($img, $abs, 85);
try {
    $db->prepare('UPDATE tutors SET id_photo_path = ? WHERE id = ?')->execute(['storage:' . $rel, $out['tutor1']['tutor_id']]);
} catch (Throwable $e) {
    fwrite(STDERR, 'note: could not set the photo column: ' . $e->getMessage() . "\n");
}
$out['_photo_file'] = $abs;
file_put_contents((getenv('TEMP') ?: sys_get_temp_dir()) . '/tpv_users.json', json_encode($out));
echo "Made admin, 2 tutors (the first with a photo) and a student.\n";
