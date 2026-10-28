<?php
// On-screen preview of a tutor's staff ID card (?doc=card) or sample certificate (?doc=cert), without a watermark.
// The tutor gets only their own; admins pass ?tutor=<users.id>. This is never the print file (bleed + crop marks),
// which stays at id_cards/pdf.php?staff=<id>&print=1 for admins.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/TutorIdService.php';

Middleware::requireAuth();
$db = \Models\Connection::getInstance()->getConnection();
if (($_GET['doc'] ?? '') === 'sample' && tid_is_admin()) {
    // Admin Settings: a sample card with the current director signature and card wording.
    $pdf = tcard_pdf_bytes([['_kind' => 'staff', 'snapshot_name' => 'Sample Tutor', 'snapshot_course' => 'Tutor, Your Subject', 'snapshot_reg_no' => 'DTI-STF-0000',
        '_work_email' => 'tutor@dazu.ac.ke', '_verify_url' => staff_verify_url($db, str_repeat('0', 40)), '_photo_file' => null,
        '_director' => dsig_active($db), '_settings' => idset_all($db)]]);
    header('Content-Type: application/pdf');
    header('Cache-Control: private, no-store, max-age=0');
    echo $pdf;
    exit;
}
$me = (int) ($_SESSION['user_id'] ?? 0);
$who = tid_is_admin() && !empty($_GET['tutor']) ? (int) $_GET['tutor'] : $me;
$t = staff_tutor($db, $who);
if (!$t) {
    // A logged-in account that is not a tutor (and is not an admin asking for a tutor) gets 403, not 404.
    // 404 is only for an admin asking for a tutor that does not exist. The reason goes to the error log.
    $missing = tid_is_admin() && $who !== $me;
    error_log('[tutor id preview] ' . ($missing ? '404 no such tutor' : '403 this account is not a tutor (an admin must pass ?tutor=)')
        . ' (user ' . $me . ', role ' . ($_SESSION['role'] ?? 'none') . ', session ' . session_name() . ', asked for ' . $who . ')');
    http_response_code($missing ? 404 : 403);
    exit($missing ? 'Not found.' : 'This preview is not available for this account.');
}
$doc = ($_GET['doc'] ?? 'card') === 'cert' ? 'cert' : 'card';
// No photo yet: the card is still drawn (the photo frame shows a placeholder), so the tutor sees how it will look.
$pdf = $doc === 'cert' ? tid_sample_certificate_pdf($db, $t) : tid_card_preview_pdf($db, $t);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="preview.pdf"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
echo $pdf;
