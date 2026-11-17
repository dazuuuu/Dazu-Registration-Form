<?php
// A picture inserted in the secretary's blog editor: saved (compressed) as a file, the editor gets its address. The same call the admin's editor makes, for a secretary
// (the admin's endpoint refuses anyone who is not an admin). Checked: active secretary with the Blogs switch on, POST, security token.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary.php';

$sec = secretary_require('blogs');
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}
$up = upload_image($_FILES['image'] ?? [], public_path('uploads/blogs/content'), url('uploads/blogs/content'), ['jpg', 'jpeg', 'png', 'webp', 'gif'], 20 * 1024 * 1024);
echo json_encode($up['ok'] ? ['ok' => true, 'url' => $up['path']] : ['ok' => false, 'error' => $up['error'] ?? 'Upload failed']);
