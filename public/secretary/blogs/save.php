<?php
// Saves the secretary's blog editor (POST, JSON back). Checked on every call: she is an active secretary (On leave is fine) with the Blogs switch on, and the security token.
// The work is done by SecretaryBlogService, which calls the existing blog code (live, approval off) or keeps the post waiting (approval on).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryBlogService.php';

$sec = secretary_require('blogs');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$out = static function (int $code, array $b): void { http_response_code($code); echo json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; };

if ($_SERVER['REQUEST_METHOD'] !== 'POST') $out(405, ['ok' => false, 'error' => 'Use the blog editor.']);
$max = (int) ini_get('post_max_size'); $unit = strtoupper(substr(trim((string) ini_get('post_max_size')), -1));
$max = $unit === 'G' ? $max * 1073741824 : ($unit === 'M' ? $max * 1048576 : ($unit === 'K' ? $max * 1024 : $max));
if (!$_POST && $max > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $max) $out(413, ['ok' => false, 'error' => 'That is too big for the server. Use a smaller cover picture.']);
if (!csrf_verify($_POST['csrf_token'] ?? null)) $out(403, ['ok' => false, 'error' => 'The security check failed. Please reload the page and try again.']);
$_SESSION['last_activity'] = time();   // saving a post is activity

$db = \Models\Connection::getInstance()->getConnection();
$cover = blog_save_cover($_FILES['featured_image'] ?? null);   // the existing cover code
$coverUrl = null;
if (!empty($cover['ok'])) $coverUrl = $cover['url'];
elseif (empty($cover['none'])) $out(422, ['ok' => false, 'error' => (string) $cover['error']]);

$r = (new SecretaryBlogService($db))->save($sec, $_POST, $coverUrl, (int) ($_POST['blog_id'] ?? 0) ?: null, (int) ($_POST['sub_id'] ?? 0) ?: null);
if (empty($r['ok'])) $out(422, ['ok' => false, 'error' => (string) $r['error']]);
SessionModel::setFlash('success', $r['mode'] === 'waiting' ? 'Sent to the admin. It is waiting for approval and is not shown to the public yet.' : 'Saved. It is live.');
$out(200, ['ok' => true, 'mode' => $r['mode'], 'blog_id' => $r['blog_id'] ?? null]);
