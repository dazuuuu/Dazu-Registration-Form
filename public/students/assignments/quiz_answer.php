<?php
// Student: check one quiz question instantly (POST, JSON). Only for an assignment given to this student;
// grading runs on the server. Up to ASG_QUIZ_MAX_ATTEMPTS attempts per question, pass at ASG_QUIZ_PASS_PERCENT.
require __DIR__ . '/../../components/assignments/student_boot.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
    echo json_encode(['ok' => false, 'error' => 'Session expired. Refresh the page.']);
    exit;
}
$item = $am->itemForStudent($uid, (int) ($_POST['id'] ?? 0));
if (!$item || !(int) ($item['submit_quiz'] ?? 0) || !\Models\AssignmentModel::canEdit($item)) {
    echo json_encode(['ok' => false, 'error' => 'This assignment can no longer be changed.']);
    exit;
}
$questionId = (int) ($_POST['question_id'] ?? 0);
$raw = isset($_POST['text']) ? (string) $_POST['text'] : (string) ($_POST['option'] ?? '');
$groupMembers = $am->groupMembers((int) $item['id'], $uid);
$workUid = $groupMembers ? $groupMembers[0] : $uid;
$r = $am->gradeQuizAnswer($item, $workUid, $questionId, $raw);
echo json_encode($r);
