<?php
// public/components/progress/boot.php — common start of every Student Progress page, for BOTH portals.
// The page sets $sp_portal ('tutor' or 'admin') first. Login and role are checked here on the server, before any input is read:
// students, staff, interns and everyone else are turned away; this is the real check, the menu link is only a convenience.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/StudentProgress.php';
require_once __DIR__ . '/ui.php';

if (($sp_portal ?? '') === 'admin') Middleware::requireAdmin();
elseif (($sp_portal ?? '') === 'tutor') Middleware::requireTutor();
else { http_response_code(403); exit('Not allowed.'); }

$db = \Models\Connection::getInstance()->getConnection();
$viewer = sp_viewer($db, $sp_portal);
if (!$viewer) {
    http_response_code(403);
    exit('Only admin and tutors can open Student Progress.');
}
$sp_base = url($sp_portal . '/progress/');
$sp_layout = __DIR__ . '/../../templates/' . $sp_portal . '/layout.php';

function sp_flash(string $type, string $msg): void { SessionModel::setFlash($type, htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')); }

/** Refuse with a real status code and a short page (a redirect would hide the refusal). */
function sp_deny(int $code, string $msg, string $back): void
{
    http_response_code($code);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Not allowed</title>'
        . '<body style="font-family:sans-serif;max-width:520px;margin:12vh auto;padding:0 16px"><h2>' . ($code === 404 ? 'Not found' : 'Not allowed') . '</h2><p>'
        . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p><p><a href="' . htmlspecialchars($back, ENT_QUOTES, 'UTF-8') . '">Back to Student Progress</a></p></body>';
    exit;
}
