<?php
// public/components/staffcalendar/boot.php — common start of every Staff Calendar request, for BOTH portals.
// The entry file sets $sc_portal ('tutor' or 'superadmin') first. The portal login is checked, and then the person's real role is read from the database:
// only a tutor (in the tutor portal) or the super admin (in the super admin portal) gets past this file. Everyone else gets a 403, even with the address typed in.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/StaffCalendar.php';

if (!empty($sc_plain)) {
    // The small JSON request: anyone who is not allowed gets a plain "Not allowed." (no redirect, no page), before anything else runs.
    $sc_db0 = \Models\Connection::getInstance()->getConnection();
    if (session_status() === PHP_SESSION_NONE) session_start();
    $sc_a0 = scal_actor($sc_db0);
    if (!in_array($sc_portal ?? '', ['tutor', 'superadmin'], true) || !$sc_a0 || $sc_a0['role'] !== $sc_portal) {
        http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); echo 'Not allowed.'; exit;
    }
}
if (($sc_portal ?? '') === 'tutor') Middleware::requireTutor();
elseif (($sc_portal ?? '') === 'superadmin') Middleware::requireSuperPortal();
else { http_response_code(403); exit('Not allowed.'); }

$db = \Models\Connection::getInstance()->getConnection();
$actor = scal_actor($db);
if (!$actor || $actor['role'] !== $sc_portal) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Not allowed</title>'
        . '<body style="font-family:sans-serif;max-width:520px;margin:12vh auto;padding:0 16px"><h2>Not allowed</h2><p>The Staff Calendar is only for tutors and the super admin.</p></body>';
    exit;
}
$sc_base = url($sc_portal . '/staffcalendar/index.php');
$sc_layout = __DIR__ . '/../../templates/' . $sc_portal . '/layout.php';
$sc_ready = scal_installed($db);
