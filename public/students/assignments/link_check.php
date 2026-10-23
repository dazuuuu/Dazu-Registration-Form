<?php
// Student: check one link while typing (POST, JSON). Only for an assignment given to this student; the check runs on the server.
require __DIR__ . '/../../components/assignments/student_boot.php';
require_once __DIR__ . '/../../../app/v1/services/LinkChecker.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
    echo json_encode(['ok' => false, 'error' => 'Session expired. Refresh the page.']);
    exit;
}
$item = $am->itemForStudent($uid, (int) ($_POST['id'] ?? 0));
if (!$item || (int) ($item['check_links'] ?? 1) !== 1 || !\Models\AssignmentModel::canEdit($item)) {
    echo json_encode(['ok' => false]);
    exit;
}
$tutor = $am->tutorById((int) $item['tutor_id']);
$r = (new LinkChecker($pdo))->check(mb_substr((string) ($_POST['url'] ?? ''), 0, 500), $item['link_sites'], $uid, null, $tutor['github_username'] ?? null);
echo json_encode(['ok' => true, 'status' => $r['status'], 'title' => $r['title'], 'message' => $r['message'], 'bad' => $r['bad'],
                  'blocks' => $r['bad'] && (int) ($item['block_bad_links'] ?? 0) === 1]);
