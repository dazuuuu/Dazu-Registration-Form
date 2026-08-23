<?php
require_once __DIR__ . '/../_init.php';
require_once __DIR__ . '/../../../../app/v1/services/DocumentComposeService.php';

sms_require_tables($pdo);

$docService = new DocumentComposeService($pdo);
$source = $_GET['source'] ?? 'composed';
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(404);
    echo 'Document not found.';
    exit;
}

$row = null;
if ($source === 'enrollment') {
    $row = $docService->getAdmissionDocument($id);
    if ($row) {
        $row['file_path'] = (string) ($row['file_path'] ?? '');
        $row['file_name'] = basename($row['file_path']) ?: 'admission-letter.pdf';
    }
} else {
    $row = $docService->getIssuedDocument($id);
}

if (!$row || empty($row['file_path'])) {
    http_response_code(404);
    echo 'Document not found.';
    exit;
}

$path = $docService->absoluteDocumentPath((string) $row['file_path']);
if (!$path) {
    http_response_code(404);
    echo 'Document file is missing from storage.';
    exit;
}

$filename = (string) ($row['file_name'] ?? basename($path));
if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
    $filename .= '.pdf';
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($filename) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
