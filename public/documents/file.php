<?php
// Streams a personal document (stored outside the web root) to its owner or to an admin.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/helpers/lms_files.php';

Middleware::requireAuth();
$db = \Models\Connection::getInstance()->getConnection();
$st = $db->prepare("SELECT * FROM personal_documents WHERE id = ?");
$st->execute([(int) ($_GET['id'] ?? 0)]);
$doc = $st->fetch();

$isAdmin = in_array(SessionModel::get('role'), ['admin', 'superadmin'], true);
if (!$doc || (!$isAdmin && (int) $doc['user_id'] !== (int) ($_SESSION['user_id'] ?? 0))) {
    http_response_code(404);
    exit('Document not found.');
}
$path = realpath(docs_storage_dir() . '/' . $doc['file_path']);
if (!$path || strpos($path, realpath(docs_storage_dir())) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('File missing.');
}
$name = preg_replace('/[^A-Za-z0-9 _.-]/', '_', $doc['doc_name']) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . $name . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
