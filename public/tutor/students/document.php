<?php
// Lets a tutor open the certificate (?doc=cert) or student ID card (?doc=id) of a student assigned to them.
// ?id=<registration id>. Letters of recommendation open through letters/recommendation.php.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/RecommendationService.php';

Middleware::requireTutor();
$db = \Models\Connection::getInstance()->getConnection();
$regId = (int) ($_GET['id'] ?? 0);
if (!lor_tutor_can($db, (int) $_SESSION['user_id'], $regId)) {
    http_response_code(403);
    exit('This student is not assigned to you.');
}

if (($_GET['doc'] ?? '') === 'id') {
    require_once __DIR__ . '/../../../app/v1/services/IdCardService.php';
    require_once __DIR__ . '/../../../app/v1/helpers/id_card_pdf.php';
    $st = $db->prepare("SELECT id FROM student_id_cards WHERE registration_id = ? AND status IN ('generated','ready','collected') ORDER BY id DESC LIMIT 1");
    $st->execute([$regId]);
    $card = ($cid = (int) $st->fetchColumn()) ? idc_get($db, $cid) : null;
    if (!$card) {
        http_response_code(404);
        exit('This student has no ID card yet.');
    }
    $pdf = idc_pdf_bytes($card);
    $name = idc_pdf_filename($card);
} else {
    require_once __DIR__ . '/../../../app/v1/models/CertificateModel.php';
    require_once __DIR__ . '/../../../app/v1/helpers/certificate_pdf.php';
    $c = lor_certificate($db, $regId);
    $cert = $c ? (new \Models\CertificateModel($db))->getCertificateById((int) $c['id']) : null;
    if (!$cert) {
        http_response_code(404);
        exit('No certificate has been generated for this student yet.');
    }
    $pdf = cert_pdf_bytes($cert);
    $name = 'Certificate_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $cert['certificate_no']) . '.pdf';
}
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (!empty($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . $name . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-cache, must-revalidate');
echo $pdf;
