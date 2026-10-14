<?php
// Streams a student ID card PDF (front + back), or a tutor's staff card (?staff=<users.id>).
//  - Admins/superadmins: any card; ?print=1 gives the print file (3 mm bleed + crop marks).
//  - A student: only their own card, once it has been generated (?dl=1 downloads instead of opening).
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/helpers/id_card_pdf.php';

SessionModel::start();
$id = (int) ($_GET['id'] ?? 0);
$db = \Models\Connection::getInstance()->getConnection();

// Tutor staff card: ?staff=<tutor's users.id>. The tutor gets their own; admins any.
if (isset($_GET['staff'])) {
    require_once __DIR__ . '/../../app/v1/services/StaffIdService.php';
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    $who = (int) $_GET['staff'];
    $admin = SessionModel::get('logged_in') === true && in_array(SessionModel::get('role'), ['superadmin', 'admin'], true);
    $t = ($admin || ($who === $uid && SessionModel::get('logged_in') === true)) ? staff_tutor($db, $who) : null;
    if (!$t || empty($t['staff_no'])) {
        http_response_code(404);
        exit('Staff ID card not found.');
    }
    $card = staff_card($db, $t);
    $print = $admin && !empty($_GET['print']);
    $pdf = idc_pdf_bytes($card, $print);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (!empty($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . idc_pdf_filename($card, $print) . '"');
    header('Cache-Control: private, no-cache, must-revalidate');
    echo $pdf;
    exit;
}
$card = $id ? idc_get($db, $id) : null;
if (!$card || in_array($card['status'], ['requested', 'declined'], true)) {
    http_response_code(404);
    exit('ID card not found.');
}

$role = SessionModel::get('role');
$isStaff = SessionModel::get('logged_in') === true && in_array($role, ['superadmin', 'admin'], true);
if (!$isStaff) {
    $owner = (int) ($card['user_id'] ?: $card['reg_user_id']);
    if ($role !== 'student' || (int) ($_SESSION['user_id'] ?? 0) !== $owner) {
        http_response_code(403);
        exit('Unauthorized access.');
    }
}

$print = $isStaff && !empty($_GET['print']);
try {
    $pdf = idc_pdf_bytes($card, $print);
} catch (Throwable $e) {
    error_log('[id card pdf] ' . $e->getMessage());
    http_response_code(500);
    exit('The ID card could not be created.');
}
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (!empty($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . idc_pdf_filename($card, $print) . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-cache, must-revalidate');
echo $pdf;
