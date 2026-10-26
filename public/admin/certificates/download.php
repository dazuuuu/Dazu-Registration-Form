<?php
/**
 * Generate and stream a certificate PDF for a given certificate ID.
 * Also saves the generated PDF path back to the DB.
 */
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/CertificateModel.php';
require_once __DIR__ . '/../../../app/v1/helpers/certificate_pdf.php';

Middleware::requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    exit('Missing certificate ID.');
}

$db        = \Models\Connection::getInstance()->getConnection();
$certModel = new \Models\CertificateModel($db);
$cert      = $certModel->getCertificateById($id);

if (!$cert) {
    http_response_code(404);
    exit('Certificate not found.');
}

// Locate the global template
$templateDir  = __DIR__ . '/../../uploads/certificates';
$templatePath = get_certificate_template_path($templateDir);

// Output directory for generated PDFs
$outputDir  = __DIR__ . '/../../uploads/certificates/generated';

$result = generate_certificate_pdf($cert, $templatePath ?? '', $outputDir);

if (!$result['ok']) {
    http_response_code(500);
    exit('Failed to generate certificate: ' . $result['error']);
}

// Save the path back to the DB (so it can be linked to)
$certModel->updateCertificate($id, ['generated_pdf_path' => $result['web_path']]);

// Stream as PDF download
$filename = 'Certificate_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $cert['certificate_no']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($result['path']));
header('Cache-Control: no-cache, must-revalidate');
readfile($result['path']);
exit;
