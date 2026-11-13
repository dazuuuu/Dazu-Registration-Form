<?php
// public/components/selfstudy/boot.php — common start of every tutor-side Self-study page, for BOTH portals.
// The page sets $sst_portal ('tutor' or 'admin') first. Login and role are checked here on the server, before any input is read.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/SelfStudyContent.php';
require_once __DIR__ . '/../../../app/v1/services/SelfStudyAccess.php';
require_once __DIR__ . '/../../../app/v1/services/SelfStudyProgress.php';

if (($sst_portal ?? '') === 'admin') Middleware::requireAdmin();
elseif (($sst_portal ?? '') === 'tutor') Middleware::requireTutor();
else { http_response_code(403); exit('Not allowed.'); }

$db = \Models\Connection::getInstance()->getConnection();
$viewer = sst_viewer($db, $sst_portal);
if (!$viewer) { http_response_code(403); exit('Only tutors and admin can open Self-study.'); }
$sst_base = url($sst_portal . '/selfstudy/');
$sst_layout = __DIR__ . '/../../templates/' . $sst_portal . '/layout.php';
$sst_ready = sst_installed($db);

function sst_flash(string $type, string $msg): void { SessionModel::setFlash($type, htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')); }

/** Refuse with a real status code (a redirect would hide the refusal). */
function sst_deny(int $code, string $msg, string $back): void
{
    http_response_code($code);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Not allowed</title>'
        . '<body style="font-family:sans-serif;max-width:520px;margin:12vh auto;padding:0 16px"><h2>' . ($code === 404 ? 'Not found' : 'Not allowed') . '</h2><p>'
        . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p><p><a href="' . htmlspecialchars($back, ENT_QUOTES, 'UTF-8') . '">Back to Self-study</a></p></body>';
    exit;
}
