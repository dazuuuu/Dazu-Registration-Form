<?php
// Streams a letter of recommendation PDF (?dl=1 downloads instead of opening).
//  - ?id=<recommendation_letters.id>: admins/superadmins any; the student their own; a tutor their assigned students'.
//  - ?k=<verify_hash>: anyone with the link (shown on the public verify page).
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/RecommendationService.php';

SessionModel::start();
$db = \Models\Connection::getInstance()->getConnection();

if (isset($_GET['k'])) {
    $l = lor_get_by_hash($db, strtolower((string) $_GET['k']));
} else {
    $l = lor_get($db, (int) ($_GET['id'] ?? 0));
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    $role = SessionModel::get('logged_in') === true ? SessionModel::get('role') : null;
    $allowed = $l && (in_array($role, ['superadmin', 'admin'], true)
        || ($role === 'student' && $uid === (int) $l['user_id'])
        || ($role === 'tutor' && lor_tutor_can($db, $uid, (int) $l['registration_id'])));
    if ($l && !$allowed) {
        http_response_code(403);
        exit('Unauthorized access.');
    }
}
if (!$l || $l['certificate_status'] === 'withdrawn') {
    http_response_code(404);
    exit('Letter of recommendation not found.');
}

try {
    $pdf = lor_pdf_bytes($l);
} catch (Throwable $e) {
    error_log('[lor pdf] ' . $e->getMessage());
    http_response_code(500);
    exit('The letter could not be created.');
}
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (!empty($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . lor_pdf_filename($l) . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-cache, must-revalidate');
echo $pdf;
