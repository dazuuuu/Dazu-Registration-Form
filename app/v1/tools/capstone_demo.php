<?php
// Capstone demo project for local testing.
//   php app/v1/tools/capstone_demo.php            create the demo (does nothing if it already exists)
//   php app/v1/tools/capstone_demo.php --reset    remove the demo capstone and its demo students, then create it again
//
// Creates a demo tutor, a demo course, six demo students and one capstone, with every state to look at:
//   Aisha   not started      Kevin   in progress (a draft)       Wanjiru  sent, waiting to be marked
//   Brian   returned 1 of 2  Lucy    marked: Distinction, held    Maina    returned twice, failed, retake waiting for the fee
// Sign in with the demo accounts (password: Passw0rd!). Demo accounts end in @capstone-demo.local.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$opts = getopt('', ['reset']);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/CapstoneService.php';
require_once __DIR__ . '/../services/CapstoneNotifier.php';

use Models\AssignmentModel;

$db = \Models\Connection::getInstance()->getConnection();
if (!cap_installed($db)) { fwrite(STDERR, "Run the migrations first: php app/v1/databases/migrate.php\n"); exit(1); }

const DEMO_TITLE = 'Capstone: Build a Small Business Website (DEMO)';
const DEMO_DOMAIN = '@capstone-demo.local';
$pw = password_hash('Passw0rd!', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$utc = fn(int $offsetDays, int $hour = 15): string => gmdate('Y-m-d', time() + $offsetDays * 86400) . sprintf(' %02d:00:00', $hour);

if (isset($opts['reset'])) {
    $ids = $db->prepare('SELECT id FROM capstones WHERE title = ?');
    $ids->execute([DEMO_TITLE]);
    foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $cid) {
        $cid = (int) $cid;
        $att = $db->prepare('SELECT id FROM capstone_attempts WHERE capstone_id = ?');
        $att->execute([$cid]);
        $aids = array_map('intval', $att->fetchAll(PDO::FETCH_COLUMN));
        foreach ($aids as $a) {
            foreach (['capstone_marks', 'capstone_returns', 'capstone_milestone_submissions', 'capstone_slot_bookings', 'capstone_own_work', 'capstone_retake_payments'] as $t) $db->prepare("DELETE FROM $t WHERE attempt_id = ?")->execute([$a]);
            $sub = $db->prepare('SELECT id FROM capstone_submissions WHERE attempt_id = ?');
            $sub->execute([$a]);
            foreach ($sub->fetchAll(PDO::FETCH_COLUMN) as $s) $db->prepare('DELETE FROM capstone_submission_items WHERE submission_id = ?')->execute([(int) $s]);
            $db->prepare('DELETE FROM capstone_submissions WHERE attempt_id = ?')->execute([$a]);
        }
        $cls = $db->prepare('SELECT class_id FROM capstone_slots WHERE capstone_id = ? AND class_id IS NOT NULL');
        $cls->execute([$cid]);
        foreach ($cls->fetchAll(PDO::FETCH_COLUMN) as $c) { $db->prepare('DELETE FROM class_students WHERE class_id = ?')->execute([(int) $c]); $db->prepare('DELETE FROM classes WHERE id = ?')->execute([(int) $c]); }
        foreach (['capstone_slots', 'capstone_attempts', 'capstone_students', 'capstone_deliverables', 'capstone_milestones', 'capstone_example_ack', 'capstone_events'] as $t) $db->prepare("DELETE FROM $t WHERE capstone_id = ?")->execute([$cid]);
        $db->prepare('DELETE FROM capstones WHERE id = ?')->execute([$cid]);
    }
    echo "Demo capstone removed.\n";
}
$exists = $db->prepare('SELECT id FROM capstones WHERE title = ?');
$exists->execute([DEMO_TITLE]);
if ($exists->fetchColumn()) { echo "The demo capstone already exists. Use --reset to make it again.\n"; exit(0); }

function demo_user(PDO $db, array $roles, string $pw, string $username, string $email, string $role, string $first, string $last): int
{
    $st = $db->prepare('SELECT id FROM users WHERE email = ?');
    $st->execute([$email]);
    if ($id = (int) $st->fetchColumn()) return $id;
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute([$username, $email, $pw, $roles[$role]]);
    $id = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, $first, $last]);
    return $id;
}

// Demo tutor and course
$tutorUid = demo_user($db, $roles, $pw, 'demo.tutor', 'demo.tutor' . DEMO_DOMAIN, 'tutor', 'Demo', 'Tutor');
$st = $db->prepare('SELECT id FROM tutors WHERE user_id = ?');
$st->execute([$tutorUid]);
if (!($tutorId = (int) $st->fetchColumn())) { $db->prepare('INSERT INTO tutors (user_id, is_active) VALUES (?, 1)')->execute([$tutorUid]); $tutorId = (int) $db->lastInsertId(); }
$st = $db->prepare("SELECT id FROM courses WHERE title = 'Web Development (DEMO)'");
$st->execute();
if (!($courseId = (int) $st->fetchColumn())) {
    $db->prepare("INSERT INTO courses (title, abbreviation, description, duration_months, course_fee, is_active, qualification) VALUES ('Web Development (DEMO)', 'WDD', 'Demo course for the capstone.', 12, 120000, 1, 'diploma')")->execute();
    $courseId = (int) $db->lastInsertId();
}
$db->prepare('INSERT IGNORE INTO course_tutors (course_id, tutor_id) VALUES (?, ?)')->execute([$courseId, $tutorId]);

// Demo students: approved registration + assigned to the tutor
$people = [['aisha', 'Aisha', 'Mohamed'], ['kevin', 'Kevin', 'Mwangi'], ['wanjiru', 'Wanjiru', 'Kamau'], ['brian', 'Brian', 'Otieno'], ['lucy', 'Lucy', 'Achieng'], ['maina', 'Maina', 'Karanja']];
$stu = [];
foreach ($people as $i => [$key, $first, $last]) {
    $uid = demo_user($db, $roles, $pw, 'DEMO' . ($i + 1) . '/26', $key . DEMO_DOMAIN, 'student', $first, $last);
    $has = $db->prepare('SELECT id FROM registrations WHERE user_id = ? AND course_id = ?');
    $has->execute([$uid, $courseId]);
    if (!$has->fetchColumn()) {
        $db->prepare("INSERT INTO registrations (registration_no, full_name, email, phone, course_id, study_mode, registration_fee, course_fee, payment_plan, status, user_id, study_location, id_document_path, installment_count, installment_amount, payment_reference)
                      VALUES (?, ?, ?, ?, ?, 'online', 1000, 120000, 'full', 'approved', ?, 'thika', 'uploads/demo.pdf', 1, 120000, 'DEMO')")
           ->execute(['DEMO' . ($i + 1) . '/26', "$first $last", $key . DEMO_DOMAIN, '0722000' . str_pad((string) $i, 3, '0', STR_PAD_LEFT), $courseId, $uid]);
    }
    $db->prepare('INSERT IGNORE INTO tutor_students (tutor_id, user_id) VALUES (?, ?)')->execute([$tutorId, $uid]);
    $stu[$key] = $uid;
}

// The capstone (opens now, due in 14 days)
$d = [
    'course_id' => $courseId, 'intake_id' => null, 'title' => DEMO_TITLE,
    'brief' => "Build a small website for a real or imaginary local business (a salon, a shop, a school club). It must work on a phone, have at least four pages, a contact form that shows a thank-you message, and be published on the internet so anyone can open it.",
    'instructions' => "1. Pick your business and write a one-page plan.\n2. Build the site step by step and save your work often on GitHub.\n3. Write a short report (PDF) about what you built and what you learned.\n4. Record a 3 minute demo video.\n5. Book a presentation slot and present your site to your tutor.\nSend each item below. Every link must be open for your tutor to view.",
    'requirements' => "Works on a phone and a laptop\nAt least 4 pages with a menu\nA contact form with a thank-you message\nPublished online (a link anyone can open)\nCode on GitHub with a README\nYour own work: do not copy the example",
    'example_link' => 'https://github.com/dazu-demo/example-bakery-site', 'mode' => 'individual', 'team_size' => 3, 'visibility' => 'now', 'opens_at' => null,
    'due_at' => $utc(14), 'w_project' => 50, 'w_docs' => 20, 'w_present' => 20, 'w_time' => 10, 'weight' => 5,
    'deliverables' => [
        ['kind' => 'project_link', 'label' => CAP_DELIVERABLES['project_link'][0], 'item_type' => 'link', 'is_required' => 1],
        ['kind' => 'report', 'label' => CAP_DELIVERABLES['report'][0], 'item_type' => 'file', 'is_required' => 1],
        ['kind' => 'video', 'label' => CAP_DELIVERABLES['video'][0], 'item_type' => 'link', 'is_required' => 1],
        ['kind' => 'slides', 'label' => CAP_DELIVERABLES['slides'][0], 'item_type' => 'either', 'is_required' => 0],
    ],
    'milestones' => [
        ['title' => 'Project proposal', 'kind' => 'work', 'due_at' => $utc(-10), 'marks' => 3],
        ['title' => 'Progress check', 'kind' => 'work', 'due_at' => $utc(-3), 'marks' => 3],
        ['title' => 'Final project sent', 'kind' => 'final', 'due_at' => $utc(14), 'marks' => 2],
        ['title' => 'Presentation on the booked day', 'kind' => 'presentation', 'due_at' => $utc(16), 'marks' => 2],
    ],
    'students' => array_map(fn($uid) => ['uid' => $uid, 'team_no' => null, 'own_due_at' => null], array_values($stu)),
];
$r = cap_save($db, $tutorId, null, $d, $tutorUid);
if (!$r['ok']) { fwrite(STDERR, "Could not create the capstone:\n - " . implode("\n - ", $r['errors']) . "\n"); exit(1); }
$capId = (int) $r['id'];
$db->prepare('UPDATE capstones SET opened_manually_at = ?, notified_open_at = ? WHERE id = ?')->execute([cap_now(), cap_now(), $capId]);
cap_make_attempts($db, $capId);
$cap = cap_get($db, $capId);
$dels = array_column(cap_deliverables($db, $capId), null, 'kind');
$ms = array_column(cap_milestones($db, $capId), null, 'kind');
$msw = cap_milestones($db, $capId);

// Presentation slots
$tu = $utc(15, 7);
cap_slot_create($db, $cap, $tutorUid, $tu, 20, 'online', 'https://meet.google.com/demo-capstone', 1, $tutorUid);
cap_slot_create($db, $cap, $tutorUid, $utc(15, 8), 20, 'online', 'https://meet.google.com/demo-capstone', 1, $tutorUid);
cap_slot_create($db, $cap, $tutorUid, $utc(16, 7), 20, 'in_person', 'Thika branch, room 529', 1, $tutorUid);

// helpers to put work in without network checks
$work = function (int $uid, string $who, int $ver = 0) use ($db, $cap, $dels): array {
    $att = cap_latest_attempt($db, (int) $cap['id'], $uid);
    $owner = cap_owner($db, $att);
    $w = cap_work($db, $owner, true);
    $items = ['project_link' => ['https://github.com/demo-' . $who . '/small-business-site', 'link'], 'video' => ['https://www.youtube.com/watch?v=demo-' . $who, 'link'],
              'slides' => ['https://docs.google.com/presentation/d/demo-' . $who, 'link']];
    foreach ($items as $kind => [$url]) cap_put_item($db, (int) $w['id'], (int) $dels[$kind]['id'], $dels[$kind], ['url' => $url, 'file_path' => null, 'file_name' => null, 'link_status' => 'ok', 'link_message' => 'Link works: opened without signing in.', 'same' => 0]);
    cap_put_item($db, (int) $w['id'], (int) $dels['report']['id'], $dels['report'], ['url' => null, 'file_path' => 'capstone/demo/report-' . $who . '.pdf', 'file_name' => 'report-' . $who . '.pdf', 'link_status' => null, 'link_message' => null, 'same' => 0]);
    $db->prepare('INSERT IGNORE INTO capstone_own_work (attempt_id, student_user_id, version, ticked_at, ai_note) VALUES (?, ?, ?, ?, ?)')->execute([(int) $owner['id'], $uid, (int) $w['version'], cap_now(), 'I used an AI helper to explain errors, not to write my code.']);
    return [$att, $owner];
};
$send = function (int $uid, string $who) use ($db, $work, $cap): void {
    [$att] = $work($uid, $who);
    $row = cap_for_student($db, (int) $cap['id'], $uid);
    $r = cap_submit($db, $row, cap_latest_attempt($db, (int) $cap['id'], $uid), $uid, true);
    if (!$r['ok']) echo "  ! $who: " . $r['error'] . "\n";
};
$sentMilestone = function (int $uid, int $milestoneId, string $when, string $text) use ($db): void {
    $a = cap_latest_attempt($db, (int) $GLOBALS['capId'], $uid);
    $o = cap_owner($db, $a);
    $db->prepare('INSERT IGNORE INTO capstone_milestone_submissions (milestone_id, attempt_id, note, submitted_at) VALUES (?, ?, ?, ?)')->execute([$milestoneId, (int) $o['id'], $text, $when]);
};
$proposal = (int) $msw[0]['id'];
$progress = (int) $msw[1]['id'];
$ago = fn(int $days, int $h = 10) => gmdate('Y-m-d', time() - $days * 86400) . sprintf(' %02d:00:00', $h);

// Kevin: in progress (a draft with one link)
$a = cap_latest_attempt($db, $capId, $stu['kevin']);
$w = cap_work($db, cap_owner($db, $a), true);
cap_put_item($db, (int) $w['id'], (int) $dels['project_link']['id'], $dels['project_link'], ['url' => 'https://github.com/demo-kevin/site', 'file_path' => null, 'file_name' => null, 'link_status' => 'needs_access', 'link_message' => 'Needs access: the repository is private.', 'same' => 0]);
$db->prepare("UPDATE capstone_attempts SET status = 'in_progress' WHERE id = ?")->execute([(int) $a['id']]);
$sentMilestone($stu['kevin'], $proposal, $ago(11), 'My plan: a barber shop site with 4 pages.');

// Wanjiru: sent, on time for both milestones
$sentMilestone($stu['wanjiru'], $proposal, $ago(12), 'A website for a small bakery in Thika.');
$sentMilestone($stu['wanjiru'], $progress, $ago(4), 'Home and menu pages done; contact form next.');
$send($stu['wanjiru'], 'wanjiru');

// Brian: sent, then returned once
$sentMilestone($stu['brian'], $proposal, $ago(11), 'A site for my football club.');
$send($stu['brian'], 'brian');
$bo = cap_latest_attempt($db, $capId, $stu['brian']);
cap_return($db, (int) $bo['id'], $tutorId, "The site works, but the contact form shows no thank-you message and the menu breaks on a phone. Fix both and add a README that tells me how to run it.", $utc(5), $tutorUid);

// Lucy: sent, marked Distinction, held until tomorrow
$sentMilestone($stu['lucy'], $proposal, $ago(13), 'A site for a hair salon in Juja.');
$sentMilestone($stu['lucy'], $progress, $ago(5), 'Booking page and gallery are ready.');
$send($stu['lucy'], 'lucy');
$lo = cap_latest_attempt($db, $capId, $stu['lucy']);
$ev = cap_time_evidence($db, $cap, $lo);
cap_save_mark($db, (int) $lo['id'], $tutorId, ['p_project' => 41, 'p_docs' => 16, 'p_present' => 17, 'p_time' => min(6, $ev['suggested']), 'c_project' => 'Clean code, works on every screen, form validates well.',
    'c_docs' => 'Clear report with screenshots.', 'c_present' => 'Calm and clear. Explained the choices well.', 'c_time' => 'Both milestones were on time.', 'feedback' => 'Well done Lucy. A very professional first site.',
    'held_until' => $utc(1, 6), 'time_evidence' => $ev['rows']], $tutorUid);

// Maina: late milestones, returned twice, then failed (retake waiting for the fee)
$sentMilestone($stu['maina'], $proposal, $ago(8), 'Late plan: a school website.');
$send($stu['maina'], 'maina');
$mo = cap_latest_attempt($db, $capId, $stu['maina']);
cap_return($db, (int) $mo['id'], $tutorId, 'Only the home page exists. Build the other three pages and the contact form.', $utc(9), $tutorUid);
$row = cap_for_student($db, $capId, $stu['maina']);
$work($stu['maina'], 'maina2');
cap_submit($db, $row, cap_latest_attempt($db, $capId, $stu['maina']), $stu['maina'], true);
$mo = cap_latest_attempt($db, $capId, $stu['maina']);
cap_return($db, (int) $mo['id'], $tutorId, 'Better, but the form still does not work. This is your last send, so test it carefully.', $utc(11), $tutorUid);
$work($stu['maina'], 'maina3');
cap_submit($db, cap_for_student($db, $capId, $stu['maina']), cap_latest_attempt($db, $capId, $stu['maina']), $stu['maina'], true);
$mo = cap_latest_attempt($db, $capId, $stu['maina']);
$ev = cap_time_evidence($db, $cap, $mo);
cap_save_mark($db, (int) $mo['id'], $tutorId, ['p_project' => 20, 'p_docs' => 9, 'p_present' => 8, 'p_time' => $ev['suggested'], 'c_project' => 'The form still does not send. Only two pages work.',
    'c_docs' => 'The report is short.', 'c_present' => 'Not ready to explain the code.', 'c_time' => 'The proposal was late.', 'feedback' => 'Not passed this time. You can retake the capstone.', 'time_evidence' => $ev['rows']], $tutorUid);
$db->prepare('INSERT INTO capstone_retake_fees (course_id, amount) VALUES (?, 5000) ON DUPLICATE KEY UPDATE amount = VALUES(amount)')->execute([$courseId]);
$rr = cap_start_retake($db, $capId, $stu['maina'], $tutorId, $tutorUid);
if (!$rr['ok']) echo "  ! retake: " . $rr['error'] . "\n";

// Tell the story
echo "Demo capstone created (id $capId).\n";
echo "Tutor:    demo.tutor" . DEMO_DOMAIN . "  /  Passw0rd!   (tutor portal: Capstone)\n";
foreach ($people as [$key, $first, $last]) echo sprintf("Student:  %-30s /  Passw0rd!   %s\n", $key . DEMO_DOMAIN, $first . ' ' . $last);
AssignmentNotifier::flush();
