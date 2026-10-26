<?php
// POST action=in|out — clock in or out. JSON for the app (and its offline queue, which sends "at" = the time the button
// was pressed); a normal form post redirects back. Rate limited and logged with the time and IP (InternService::in_clock).
require __DIR__ . '/../_boot.php';

$json = !empty($_POST['ajax']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
    $res = ['ok' => false, 'error' => 'Security check failed. Refresh the page and try again.'];
} else {
    $lat = is_numeric($_POST['lat'] ?? null) ? (float) $_POST['lat'] : null;
    $lng = is_numeric($_POST['lng'] ?? null) ? (float) $_POST['lng'] : null;
    $at = (int) ($_POST['at'] ?? 0) ?: null;
    $res = in_clock($db, $intern, ($_POST['action'] ?? '') === 'out' ? 'out' : 'in', $lat, $lng, $at);
}
if ($json) {
    header('Content-Type: application/json');
    echo json_encode($res);
    exit;
}
SessionModel::setFlash($res['ok'] ? 'success' : 'error', in_e($res['ok'] ? $res['message'] : $res['error']));
header('Location: ' . url('intern/attendance/index.php'));
