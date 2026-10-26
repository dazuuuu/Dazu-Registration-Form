<?php
// Public certificate PDF, keyed by the certificate's unguessable QR hash (the link sent over WhatsApp).
// Withdrawn certificates are never served.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/CertificateService.php';
require_once __DIR__ . '/../../app/v1/models/CertificateModel.php';
require_once __DIR__ . '/../../app/v1/helpers/certificate_pdf.php';

$hash = strtolower(trim((string) ($_GET['c'] ?? '')));
if (!preg_match('/^[a-f0-9]{16,100}$/', $hash)) {
    http_response_code(404);
    exit('Certificate not found.');
}

$db = \Models\Connection::getInstance()->getConnection();
$st = $db->prepare("SELECT id FROM certificates WHERE qr_code_hash = ? AND status <> 'withdrawn' LIMIT 1");
$st->execute([$hash]);
$id = (int) $st->fetchColumn();
$cert = $id ? (new \Models\CertificateModel($db))->getCertificateById($id) : null;
if (!$cert) {
    http_response_code(404);
    exit('Certificate not found.');
}

$pdf = cert_pdf_bytes($cert);
$filename = 'Certificate_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $cert['certificate_no']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (!empty($_GET['dl']) ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-cache, must-revalidate');
header('X-Robots-Tag: noindex');
echo $pdf;
