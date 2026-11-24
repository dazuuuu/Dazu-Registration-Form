<?php
// public/intake/book.php — Book now now renders inline on courses/view.php (#book-now-form).
// This file stays only so old links (emails already sent, bookmarks, "Tell me when the next
// intake opens" which still posts here with action=interest) keep working.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../app/v1/services/EnrolmentService.php';

$pdo = \Models\Connection::getInstance()->getConnection();
$course = (new \Models\CourseModel($pdo))->getById((int) ($_GET['course'] ?? $_POST['course_id'] ?? 0));
$courseUrl = $course ? url('courses/view.php?id=' . (int) $course['id']) : url('courses/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'interest') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['en_interest_error'] = 'Security check failed. Please refresh the page and try again.';
    } elseif (trim((string) ($_POST['website'] ?? '')) !== '') {
        $_SESSION['en_interest_error'] = 'Please try again.';
    } else {
        $cid = $course ? (int) $course['id'] : 0;
        $r = en_interest_add($pdo, $cid, (string) ($_POST['full_name'] ?? ''), (string) ($_POST['email'] ?? ''), (string) ($_POST['phone'] ?? ''));
        if ($r['ok']) { header('Location: ' . $courseUrl . '&interest=1#intake-closed'); exit; }
        $_SESSION['en_interest_error'] = $r['error'];
    }
    header('Location: ' . $courseUrl . '#intake-closed');
    exit;
}

header('Location: ' . $courseUrl . (!empty($_GET['opening']) ? '#book-now-form' : ''));
exit;
