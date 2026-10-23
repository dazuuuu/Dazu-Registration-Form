<?php
// public/auth/keepalive.php — called by assets/js/session-guard.js.
// POST action=ping: the user is active on an open page. POST action=close: the last tab was closed.
require_once __DIR__ . '/../../app/v1/init.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['logged_in'])) {
    echo json_encode(['ok' => false]);
    exit();
}

if (($_POST['action'] ?? '') === 'close') {
    // Ignored when a newer page has opened since (the report arrived late).
    if (hash_equals((string) ($_SESSION['page_token'] ?? ''), (string) ($_POST['page'] ?? ''))) {
        $_SESSION['closing_at'] = time();
    }
} else {
    $_SESSION['last_activity'] = time();
}
echo json_encode(['ok' => true, 'idle' => SESSION_IDLE_LIMIT]);
