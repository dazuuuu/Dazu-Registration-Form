<?php
// POST id=<announcement id>: the signed-in person closed the announcement pop-up (components/announcements/overlay.php).
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/AnnouncementService.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['logged_in']) || !csrf_verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}
$db = \Models\Connection::getInstance()->getConnection();
if (($_POST['action'] ?? '') === 'confirm') {
    // "Confirm attendance" button in the announcements list: a normal form post that goes back to the page.
    ann_confirm($db, (int) $_SESSION['user_id'], (int) ($_POST['id'] ?? 0));
    $back = (string) ($_POST['back'] ?? '');
    header('Location: ' . (preg_match('#^/[^/]#', $back) ? $back : url('')));
    exit;
}
ann_dismiss($db, (int) $_SESSION['user_id'], (int) ($_POST['id'] ?? 0));
echo json_encode(['ok' => true]);
