<?php
// Shared start of every intern page: signed in, intern role, and an intern record. Gives $db and $intern.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/InternService.php';

Middleware::requireIntern();
$db = \Models\Connection::getInstance()->getConnection();
$intern = in_current($db);
if (!$intern) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Intern portal</title>'
       . '<body style="font:16px Poppins,system-ui;padding:32px;max-width:520px;margin:auto"><h2>Your intern profile is not ready</h2>'
       . '<p>Your account exists, but HR has not finished your intern profile yet. Please contact the Dazu office.</p>'
       . '<p><a href="' . htmlspecialchars(url('auth/logout.php')) . '">Sign out</a></p></body>';
    exit;
}
// A turned-off account, or a suspended / withdrawn internship, signs out at once (also an already open session).
// A completed internship keeps access, so the intern can still download their documents and certificate.
if (empty($intern['account_active']) || in_array($intern['status'], ['suspended', 'withdrawn'], true)) {
    $_SESSION = [];
    session_regenerate_id(true);
    header('Location: ' . url('auth/login.php'));
    exit;
}
in_deadline_reminders($db);

/** POST guard for intern pages: CSRF check, then redirect back with a flash message. */
function intern_post_ok(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return false;
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        return false;
    }
    return true;
}
