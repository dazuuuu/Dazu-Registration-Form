<?php
// Streams the certificate PDF. Admins/superadmins can view any certificate;
// a student can view (and with &print=1 download) only their own certificates (any status except withdrawn).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/CertificateModel.php';
require_once __DIR__ . '/../../../app/v1/helpers/certificate_pdf.php';

$id = (int) ($_GET['id'] ?? 0);
$db = \Models\Connection::getInstance()->getConnection();
$cert = $id ? (new \Models\CertificateModel($db))->getCertificateById($id) : null;
if (!$cert) {
    http_response_code(404);
    exit('Certificate not found.');
}

$role = SessionModel::get('role');
if (!in_array($role, ['superadmin', 'admin'], true)) {
    if ($role !== 'student' || (int) ($_SESSION['user_id'] ?? 0) !== (int) $cert['user_id'] || $cert['status'] === 'withdrawn') {
        http_response_code(403);
        exit('Unauthorized access.');
    }
}

$pdf = cert_pdf_bytes($cert);
$filename = 'Certificate_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $cert['certificate_no']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (!empty($_GET['print']) ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-cache, must-revalidate');
echo $pdf;
