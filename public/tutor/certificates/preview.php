<?php
// Tutor: the certificate as it will print, with a PREVIEW mark. Only the tutor of that student. Nothing is saved or numbered.
require __DIR__ . '/../../components/assignments/tutor_boot.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateApplication.php';
require_once __DIR__ . '/../../../app/v1/helpers/certificate_pdf.php';

$a = certapp_get_for_tutor($pdo, (int) ($_GET['id'] ?? 0), $tutorId);
if (!$a) {
    http_response_code(404);
    exit('Request not found.');
}
$pdf = cert_pdf_bytes(certapp_preview_cert($pdo, $a));
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Certificate_preview.pdf"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store');
echo $pdf;
