<?php
// Admin: save one secretary switch (or her status) the moment it is changed. POST only, JSON back. The admin role and the security token are checked on every call.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryAdminService.php';

secretary_admin_require();   // the normal admin only: everyone else, the super admin included, gets a plain 403 "Not allowed."
header('Content-Type: application/json; charset=utf-8');
$out = static function (int $code, array $body): void { http_response_code($code); echo json_encode($body, JSON_UNESCAPED_UNICODE); exit; };

if ($_SERVER['REQUEST_METHOD'] !== 'POST') $out(405, ['ok' => false, 'error' => 'Use the Secretaries page.']);
if (!csrf_verify($_POST['csrf_token'] ?? null)) $out(403, ['ok' => false, 'error' => 'The security check failed. Please reload the page.']);

$pdo = \Models\Connection::getInstance()->getConnection();
$svc = new SecretaryAdminService($pdo);
$by = (int) ($_SESSION['user_id'] ?? 0);
$id = (int) ($_POST['id'] ?? 0);
$key = (string) ($_POST['key'] ?? '');
$value = (string) ($_POST['value'] ?? '');

$r = $key === 'status' ? $svc->setStatus($id, $value, $by) : $svc->setSwitch($id, $key, $value, $by);
if (empty($r['ok'])) $out(strpos((string) $r['error'], 'not found') !== false ? 404 : 422, $r);
$r['by'] = secretary_person_name($pdo, $by);
$r['at'] = (new DateTime('now', new DateTimeZone('Africa/Nairobi')))->format('j M, g:i a');
$out(200, $r);
