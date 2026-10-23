<?php
// On-screen preview of a tutor's staff ID card (?doc=card) or sample certificate (?doc=cert), watermarked.
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
        '_director' => dsig_active($db), '_settings' => idset_all($db), '_watermark' => 'SAMPLE']]);
    header('Content-Type: application/pdf');
    header('Cache-Control: private, no-store, max-age=0');
    echo $pdf;
    exit;
}
$me = (int) ($_SESSION['user_id'] ?? 0);
$who = tid_is_admin() && !empty($_GET['tutor']) ? (int) $_GET['tutor'] : $me;
$t = staff_tutor($db, $who);
if (!$t || ($who !== $me && !tid_is_admin())) {
    http_response_code(404);
    exit('Not found.');
}
$doc = ($_GET['doc'] ?? 'card') === 'cert' ? 'cert' : 'card';
if ($doc === 'card' && !idc_student_photo_file($db, $who)) {
    http_response_code(404);
    exit('Upload a passport photo first.');
}
$pdf = $doc === 'cert' ? tid_sample_certificate_pdf($db, $t) : tid_card_preview_pdf($db, $t);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="preview.pdf"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
echo $pdf;
