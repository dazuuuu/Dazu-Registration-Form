<?php
// Student: check one capstone link while typing (POST, JSON). Only for an item of a capstone that is open for this student.
require __DIR__ . '/../../components/capstone/student_boot.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
    echo json_encode(['ok' => false, 'error' => 'Session expired. Refresh the page.']);
    exit;
}
$row = cap_for_student($pdo, (int) ($_POST['id'] ?? 0), $uid);
$att = $row ? cap_latest_attempt($pdo, (int) $row['id'], $uid) : null;
if (!$row || !cap_can_edit($row, $att)) { echo json_encode(['ok' => false]); exit; }
$del = null;
foreach (cap_deliverables($pdo, (int) $row['id']) as $d) if ((int) $d['id'] === (int) ($_POST['del'] ?? 0)) $del = $d;
if (!$del || $del['item_type'] === 'file') { echo json_encode(['ok' => false]); exit; }
$sites = (CAP_DELIVERABLES[$del['kind']][2] ?? '') ?: implode(',', array_keys(ASG_LINK_SITES));
$tutor = $am->tutorById((int) $row['tutor_id']);
$url = mb_substr(trim((string) ($_POST['url'] ?? '')), 0, 500);
if ($url !== '' && !asg_link_site_ok($url, $sites)) {
    echo json_encode(['ok' => true, 'status' => 'not_allowed', 'title' => 'This site is not allowed', 'message' => 'Use one of: ' . str_replace(',', ', ', $sites) . '.', 'bad' => true]);
    exit;
}
$r = (new LinkChecker($pdo))->check($url, $sites, $uid, null, $tutor['github_username'] ?? null);
echo json_encode(['ok' => true, 'status' => $r['status'], 'title' => $r['title'], 'message' => $r['message'], 'bad' => $r['bad']]);
