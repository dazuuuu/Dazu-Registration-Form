<?php
// Serves a private intern file after a permission check. ?k=doc|task|learn|photo|teach&id=…  (&dl=1 downloads)
//  - doc / task / photo: the intern it belongs to, or Dazu staff (admins).
//  - learn: interns the module is assigned to, or staff.
//  - teach: only the tutor who owns the topic and the assigned intern (never admins or students).
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/services/InternService.php';

Middleware::requireAuth();
$db = \Models\Connection::getInstance()->getConnection();
$uid = (int) ($_SESSION['user_id'] ?? 0);
$staff = in_is_staff();
$k = (string) ($_GET['k'] ?? '');
$id = (int) ($_GET['id'] ?? 0);
$me = ($_SESSION['role'] ?? '') === 'intern' ? in_current($db) : null;
$row = null;
switch ($k) {
    case 'doc':
        $st = $db->prepare('SELECT path, title AS name, mime, intern_id FROM intern_documents WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row && !$staff && (!$me || (int) $me['id'] !== (int) $row['intern_id'])) $row = null;
        break;
    case 'task':
        $st = $db->prepare('SELECT f.path, f.name, f.mime, t.intern_id FROM intern_task_files f JOIN intern_tasks t ON t.id = f.task_id WHERE f.id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row && !$staff && (!$me || (int) $me['id'] !== (int) $row['intern_id'])) $row = null;
        break;
    case 'learn':
        $st = $db->prepare('SELECT file_path AS path, title AS name, NULL AS mime, module_id FROM intern_learning_items WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row && !$staff) {
            $ok = false;
            if ($me) {
                $c = $db->prepare('SELECT 1 FROM intern_learning_assignments WHERE module_id = ? AND intern_id = ?');
                $c->execute([(int) $row['module_id'], (int) $me['id']]);
                $ok = (bool) $c->fetchColumn();
            }
            if (!$ok) $row = null;
        }
        break;
    case 'photo':
        $st = $db->prepare('SELECT photo_path AS path, \'photo\' AS name, NULL AS mime, id AS intern_id FROM interns WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row && !$staff && (!$me || (int) $me['id'] !== (int) $row['intern_id'])) $row = null;
        break;
    case 'teach':
        require_once __DIR__ . '/../../app/v1/services/InternTeachingService.php';
        $t = it_file_allowed($db, $id, $uid);   // tutor owner or assigned intern only
        $row = $t ? ['path' => $t['file_path'], 'name' => $t['file_name'], 'mime' => null] : null;
        break;
}
$abs = $row ? in_file_abs($row['path']) : null;
if (!$abs) {
    http_response_code(404);
    exit('File not found.');
}
$mime = $row['mime'] ?: ((new finfo(FILEINFO_MIME_TYPE))->file($abs) ?: 'application/octet-stream');
$inline = empty($_GET['dl']) && (str_starts_with($mime, 'image/') || $mime === 'application/pdf' || str_starts_with($mime, 'video/') || $mime === 'text/plain');
$name = preg_replace('/[^\w .()-]+/u', '_', (string) $row['name']) ?: 'file';
if (!pathinfo($name, PATHINFO_EXTENSION)) $name .= '.' . pathinfo($abs, PATHINFO_EXTENSION);
header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"');
header('Content-Length: ' . filesize($abs));
header('Cache-Control: private, max-age=0, no-store');
readfile($abs);
