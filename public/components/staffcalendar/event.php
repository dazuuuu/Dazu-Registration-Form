<?php
// public/components/staffcalendar/event.php — the small request behind the event card (GET ?id=<event>&d=<day>). Returns the event as JSON.
// The role is checked again here (boot.php, plain mode): only a tutor or the super admin may read an event, and a private "only me" event only its owner.
// Anyone else gets a plain "Not allowed." with status 403.
$sc_plain = true;
require __DIR__ . '/boot.php';
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Content-Type: text/plain; charset=utf-8'); exit('Use GET.'); }
if (!$sc_ready) { http_response_code(503); header('Content-Type: text/plain; charset=utf-8'); exit('Not installed.'); }
$id = (int) ($_GET['id'] ?? 0);
$o = $id > 0 ? scal_occurrence($db, $actor, $id, (string) ($_GET['d'] ?? '')) : null;
if (!$o) {
    $exists = $id > 0 && (bool) $db->query('SELECT 1 FROM staff_calendar_events WHERE id = ' . $id)->fetchColumn();
    http_response_code($exists ? 403 : 404);
    header('Content-Type: text/plain; charset=utf-8');
    exit($exists ? 'Not allowed.' : 'Event not found.');
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(isset($_GET['full']) ? scal_panel($db, $actor, $o, scal_now()) : scal_card($db, $actor, $o, scal_now()), JSON_UNESCAPED_UNICODE);
