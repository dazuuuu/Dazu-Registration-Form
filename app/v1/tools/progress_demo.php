<?php
// Student Progress demo data for local testing.
//   php app/v1/tools/progress_demo.php            create the demo (does nothing if it already exists)
//   php app/v1/tools/progress_demo.php --reset    remove the demo and create it again
// Creates a demo admin, a demo tutor, a demo course with six subjects and eight demo students in every state:
//   Amina, Hassan = Working well   Brian = Working well (1 late)   Cynthia, David = Slowing down   Esther, Felix = Not working   Grace = no work given yet
// Sign in (password: Passw0rd!):  admin@progress-demo.local at /admin/portal.php   tutor@progress-demo.local at /login
// Everything created ends in @progress-demo.local or is named "(Progress DEMO)"; --reset removes only that.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$opts = getopt('', ['reset']);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';

$db = \Models\Connection::getInstance()->getConnection();
try { $db->query('SELECT 1 FROM progress_notes WHERE 1 = 0'); } catch (Throwable $e) { fwrite(STDERR, "Run the migrations first: php app/v1/databases/migrate.php\n"); exit(1); }
const DOM = '@progress-demo.local';
const COURSE = 'Data Analytics (Progress DEMO)';

$uids = $db->query("SELECT id FROM users WHERE email LIKE '%" . DOM . "'")->fetchAll(PDO::FETCH_COLUMN);
$cid = (int) $db->query("SELECT id FROM courses WHERE title = '" . COURSE . "'")->fetchColumn();
if ($uids || $cid) {
    if (!isset($opts['reset'])) { echo "The demo already exists. Use --reset to rebuild it.\n"; exit(0); }
    if ($uids) {
        $in = implode(',', array_map('intval', $uids));
        $aids = $db->query("SELECT id FROM tutor_assignments WHERE tutor_id IN (SELECT id FROM tutors WHERE user_id IN ($in))")->fetchAll(PDO::FETCH_COLUMN);
        if ($aids) {
            $a = implode(',', array_map('intval', $aids));
            $db->exec("DELETE FROM tutor_assignment_submissions WHERE assignment_id IN ($a)");
            $db->exec("DELETE FROM tutor_assignment_recipients WHERE assignment_id IN ($a)");
            $db->exec("DELETE FROM tutor_assignments WHERE id IN ($a)");
        }
        $db->exec("DELETE FROM course_subjects WHERE tutor_id IN (SELECT id FROM tutors WHERE user_id IN ($in))");
        $db->exec("DELETE FROM progress_notes WHERE student_user_id IN ($in) OR author_user_id IN ($in)");
        $db->exec("DELETE FROM progress_nudges WHERE student_user_id IN ($in)");
        $db->exec("DELETE FROM tutor_students WHERE user_id IN ($in)");
        $db->exec("DELETE FROM registrations WHERE user_id IN ($in)");
        $db->exec("DELETE FROM tutors WHERE user_id IN ($in)");
        $db->exec("DELETE FROM user_profiles WHERE user_id IN ($in)");
        $db->exec("DELETE FROM users WHERE id IN ($in)");
    }
    if ($cid) $db->exec("DELETE FROM courses WHERE id = $cid");
    echo "Old demo removed.\n";
}

$pw = password_hash('Passw0rd!', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$mkuser = function (string $key, string $role, string $first, string $last) use ($db, $pw, $roles): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")
       ->execute(['pdemo_' . $key, $key . DOM, $pw, $roles[$role]]);
    $id = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, $first, $last]);
    return $id;
};
$mkuser('admin', 'admin', 'Demo', 'Admin');
$tUid = $mkuser('tutor', 'tutor', 'Demo', 'Tutor');
$db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$tUid]);
$tId = (int) $db->lastInsertId();
$db->prepare("INSERT INTO courses (title, abbreviation, duration_months, course_fee, is_active, qualification) VALUES (?, 'PDEMO', 6, 60000, 1, 'diploma')")->execute([COURSE]);
$course = (int) $db->lastInsertId();

$subjects = [];
foreach (['Excel for Analysis', 'SQL Databases', 'Python Basics', 'Power BI', 'Statistics', 'Machine Learning'] as $i => $name) {
    $db->prepare('INSERT INTO course_subjects (course_id, tutor_id, name, monthly_expected, sort_order, is_active) VALUES (?, ?, ?, 1, ?, 1)')->execute([$course, $tId, $name, $i + 1]);
    $subjects[] = (int) $db->lastInsertId();
}
$ts = fn(int $daysAgo): string => gmdate('Y-m-d H:i:s', time() - $daysAgo * 86400);

// name, key, upto (assignments handed in), lastAgo (days since the last hand-in), monthly projects marked, base score, started days ago, give work?
$people = [
    ['Amina Wanjiku', 'amina', 12, 1, 3, 88, 90, true], ['Brian Otieno', 'brian', 11, 2, 2, 74, 90, true],
    ['Cynthia Achieng', 'cynthia', 12, 9, 2, 66, 90, true], ['David Kamau', 'david', 10, 3, 1, 58, 90, true],
    ['Esther Njeri', 'esther', 6, 25, 1, 45, 90, true], ['Felix Mutua', 'felix', 0, 0, 0, 0, 40, true],
    ['Grace Chebet', 'grace', 0, 0, 0, 0, 20, false], ['Hassan Ali', 'hassan', 12, 0, 3, 92, 90, true],
];
$students = [];
foreach ($people as $n => [$full, $key, $upto, $last, $proj, $base, $started, $give]) {
    [$first, $lastName] = explode(' ', $full);
    $uid = $mkuser($key, 'student', $first, $lastName);
    $db->prepare("INSERT INTO registrations (registration_no, full_name, email, phone, course_id, study_mode, registration_fee, course_fee, payment_plan, status, user_id, study_location, id_document_path, installment_count, installment_amount, payment_reference, reviewed_at)
                  VALUES (?, ?, ?, '0700000000', ?, 'online', 1000, 60000, 'full', 'approved', ?, 'thika', 'x.pdf', 1, 60000, 'DEMO', ?)")
       ->execute([sprintf('DD-PD%02d/2026', $n + 1), $full, $key . DOM, $course, $uid, $ts($started)]);
    $db->prepare('INSERT INTO tutor_students (tutor_id, user_id, start_date) VALUES (?, ?, ?)')->execute([$tId, $uid, gmdate('Y-m-d', time() - $started * 86400)]);
    $students[$key] = [$uid, $upto, $last, $proj, $base, $give];
}

// 12 assignments (subjects 1 to 3 only, so three subjects stay "not started") and 3 monthly projects, given to every student that has work.
$dues = [60, 55, 50, 45, 40, 35, 30, 25, 20, 15, 10, 5];
$items = [];
foreach ($dues as $i => $d) $items[] = ['title' => 'Assignment ' . ($i + 1), 'kind' => $i % 4 === 3 ? 'weekly' : 'daily', 'subject' => $subjects[$i % 3], 'due' => $d, 'weight' => $i % 4 === 3 ? 2 : 1, 'i' => $i];
foreach ([60, 30, 3] as $m => $d) $items[] = ['title' => 'Monthly project ' . ($m + 1), 'kind' => 'monthly', 'subject' => $subjects[$m % 3], 'due' => $d, 'weight' => 3, 'm' => $m];
foreach ($items as $k => $it) {
    $db->prepare("INSERT INTO tutor_assignments (tutor_id, course_id, subject_id, title, kind, weight, status, max_score, grading_mode, sort_order, due_at, is_template, audience)
                  VALUES (?, ?, ?, ?, ?, ?, 'published', 100, 'marks', ?, ?, 0, 'selected')")
       ->execute([$tId, $course, $it['subject'], $it['title'], $it['kind'], $it['weight'], $k + 1, $ts($it['due'])]);
    $aid = (int) $db->lastInsertId();
    foreach ($students as $key => [$uid, $upto, $last, $proj, $base, $give]) {
        if (!$give) continue;
        $db->prepare('INSERT INTO tutor_assignment_recipients (assignment_id, student_user_id, added_at) VALUES (?, ?, ?)')->execute([$aid, $uid, $ts(100)]);
        $score = max(20, min(100, $base + (($k * 7) % 15) - 7));
        if ($it['kind'] === 'monthly') {
            if ($it['m'] >= $proj + ($proj >= 2 ? 0 : 0) && !($key === 'brian' && $it['m'] === 2)) continue;   // not handed in
            $handed = $ts(max(1, $it['due'] - 2));
            $graded = !($key === 'brian' && $it['m'] === 2);
        } else {
            if ($it['i'] >= $upto) continue;                      // not handed in
            $handed = $ts($last + ($upto - 1 - $it['i']) * 3);
            $graded = true;
        }
        if ($key === 'brian' && $it['kind'] === 'monthly' && $it['m'] === 2) {   // handed in, mark not out yet
            $db->prepare("INSERT INTO tutor_assignment_submissions (assignment_id, student_user_id, status, submitted_at, is_late) VALUES (?, ?, 'submitted', ?, 0)")->execute([$aid, $uid, $handed]);
            continue;
        }
        $db->prepare("INSERT INTO tutor_assignment_submissions (assignment_id, student_user_id, status, submitted_at, score, published_at, is_late) VALUES (?, ?, 'graded', ?, ?, ?, 0)")
           ->execute([$aid, $uid, $handed, $score, $ts(max(0, (int) (($last ?: 1) - 1)))]);
    }
}
$db->prepare("INSERT INTO progress_notes (student_user_id, author_user_id, author_name, note, created_at) VALUES (?, ?, 'Demo Tutor', 'Called Esther on Monday. She had a family issue and promised to catch up.', ?)")->execute([$students['esther'][0], $tUid, $ts(3)]);

echo "Demo created: 8 students, 6 subjects, 15 pieces of work.\n";
echo "Admin:  admin@progress-demo.local / Passw0rd!   (sign in at /admin/portal.php, then Student Progress in the sidebar)\n";
echo "Tutor:  tutor@progress-demo.local / Passw0rd!   (sign in at /login, then Student Progress in the menu)\n";
