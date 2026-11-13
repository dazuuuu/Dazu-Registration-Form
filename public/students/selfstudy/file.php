<?php
// public/students/selfstudy/file.php — a Resources file. The files are stored privately (app/v1/storage/selfstudy), so every download is checked here.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/SelfStudyStudy.php';

Middleware::requireStudent();
$pdo = \Models\Connection::getInstance()->getConnection();
$uid = (int) SessionModel::get('user_id');
$f = (!ss2_portal_locked($pdo, $uid) && ss2_installed($pdo)) ? ss2_file_for_student($pdo, (int) ($_GET['id'] ?? 0), $uid) : null;
if (!$f) { http_response_code(404); echo 'File not found.'; exit; }
$name = preg_replace('/[^A-Za-z0-9._ -]+/', '_', basename((string) $f['file_name'])) ?: 'file';
header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($f['abs']));
header('Cache-Control: private, no-store');
readfile($f['abs']);
