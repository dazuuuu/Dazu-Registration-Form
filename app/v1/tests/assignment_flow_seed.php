<?php
// The starting data assignment_flow_test.php needs (its header lists it): tutor 1 (users.id 1) teaching students 11, 12, 13 in course 1,
// and tutor 2 (users.id 2) with no students. Without this the test crashes (there is no tutor/student to grade), which is a test-setup gap, not an app bug.
// With --files it also adds two old-style marked assignments with a private file and an attachment, so the checksums (row_checksums.php) have files to look at.
// Do NOT use --files for the flow test itself: it counts every submission in the database.
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/assignment_flow_seed.php [--files]      (a scratch database with migrations applied; safe to run twice)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!getenv('DAZU_DB_NAME')) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database.\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../helpers/assignment_files.php';

$db = \Models\Connection::getInstance()->getConnection();
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$pw = password_hash('x', PASSWORD_DEFAULT);
$user = function (int $id, string $role, string $name) use ($db, $roles, $pw): void {
    $ex = $db->prepare('SELECT COUNT(*) FROM users WHERE id = ?'); $ex->execute([$id]);
    if ((int) $ex->fetchColumn()) return;
    $db->prepare("INSERT INTO users (id, username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, ?, 1, 1, 'active')")
       ->execute([$id, "seed_$name$id", "seed_$name$id@seed.test", $pw, $roles[$role]]);
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, ucfirst($name), 'Seed']);
};
$user(1, 'tutor', 'tutorone'); $user(2, 'tutor', 'tutortwo');
foreach ([11, 12, 13] as $u) $user($u, 'student', 'student');
foreach ([[1, 1], [2, 2]] as [$tid, $uid]) {
    if (!(int) $db->query("SELECT COUNT(*) FROM tutors WHERE id = $tid")->fetchColumn()) $db->prepare('INSERT INTO tutors (id, user_id, is_active) VALUES (?, ?, 1)')->execute([$tid, $uid]);
}
if (!(int) $db->query('SELECT COUNT(*) FROM courses WHERE id = 1')->fetchColumn()) {
    $db->exec("INSERT INTO courses (id, title, abbreviation, duration_months, course_fee, is_active, qualification) VALUES (1, 'Seed Course', 'SEED1', 12, 100000, 1, 'diploma')");
}
foreach ([11, 12, 13] as $u) {
    if (!(int) $db->query("SELECT COUNT(*) FROM registrations WHERE user_id = $u")->fetchColumn()) {
        $db->prepare("INSERT INTO registrations (registration_no, full_name, email, phone, course_id, study_mode, registration_fee, course_fee, payment_plan, status, user_id, study_location, id_document_path, installment_count, installment_amount, payment_reference)
                      VALUES (?, ?, ?, '0700', 1, 'online', 1000, 100000, 'full', 'approved', ?, 'thika', 'x.pdf', 1, 100000, 'T')")->execute(["SEED$u", "Seed Student $u", "seed_student$u@seed.test", $u]);
    }
    if (!(int) $db->query("SELECT COUNT(*) FROM tutor_students WHERE user_id = $u AND tutor_id = 1")->fetchColumn()) $db->exec("INSERT INTO tutor_students (tutor_id, user_id) VALUES (1, $u)");
}

if (!in_array('--files', $argv ?? [], true)) { echo "Seeded tutors 1 and 2 and students 11-13 in course 1.
"; exit(0); }
// Two old-style assignments (made before subjects: no subject_id), one with an attachment file and one with a student's uploaded file, both marked.
$dir = asg_storage_dir() . '/seed_checksum_test';
@mkdir($dir, 0775, true);
file_put_contents("$dir/brief.txt", "attachment of the old assignment\n");
file_put_contents("$dir/work.txt", "student work for the old assignment\n");
$now = gmdate('Y-m-d H:i:s');
$ids = [];
foreach (['Seed old A', 'Seed old B'] as $i => $title) {
    $db->prepare("INSERT INTO tutor_assignments (tutor_id, course_id, title, status, max_score, grading_mode, sort_order, due_at, audience, kind, weight, created_at, updated_at)
                  VALUES (1, 1, ?, 'published', 100, 'marks', ?, ?, 'selected', 'daily', 1, ?, ?)")->execute([$title, $i + 1, gmdate('Y-m-d H:i:s', time() - 86400), $now, $now]);
    $ids[] = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO tutor_assignment_recipients (assignment_id, student_user_id, added_at) VALUES (?, 11, ?)')->execute([$ids[$i], $now]);
    $db->prepare("INSERT INTO tutor_assignment_submissions (assignment_id, student_user_id, status, submitted_at, score, published_at, file_path) VALUES (?, 11, 'graded', ?, ?, ?, ?)")
       ->execute([$ids[$i], $now, 70 + $i * 10, $now, $i === 0 ? 'seed_checksum_test/work.txt' : null]);
}
$db->prepare("INSERT INTO tutor_assignment_attachments (assignment_id, kind, file_path, original_name, label) VALUES (?, 'pdf', 'seed_checksum_test/brief.txt', 'brief.txt', 'Brief')")->execute([$ids[0]]);
echo "Seeded tutors 1 and 2, students 11-13 in course 1, and 2 old-style marked assignments with files.\n";
