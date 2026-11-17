<?php
// Secretary blogs: live posting when approval is off, waiting posts and pending changes when it is on, unpublish / publish again, approve, send back, compare, the record and the notices,
// and that the existing blog tables are untouched. Also makes the users secretary_blogs_http.sh signs in as (<temp>/secretary_blogs_users.json).
//   DAZU_DB_NAME=<scratch db> php app/v1/tests/secretary_blogs_test.php      (a scratch database with all migrations applied; it refuses the database named in config/database.php)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$configured = (require __DIR__ . '/../config/database.php')['dbname'] ?? '';
if (!getenv('DAZU_DB_NAME') || getenv('DAZU_DB_NAME') === $configured) { fwrite(STDERR, "Set DAZU_DB_NAME to a scratch database (not the one in config/database.php).\n"); exit(2); }
define('UPLOAD_TEST_MODE', 1);
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../models/Connection.php';
require_once __DIR__ . '/../services/SecretaryAdminService.php';
require_once __DIR__ . '/../services/SecretaryBlogService.php';
require_once __DIR__ . '/../services/MigrationService.php';
if (!isset($_SESSION) || !is_array($_SESSION)) $_SESSION = [];

$db = \Models\Connection::getInstance()->getConnection();
$schemaNow = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? 'DATABASE()' : 'current_schema()';   // the current database, on MySQL and on PostgreSQL
echo 'Database driver: ' . $db->getAttribute(PDO::ATTR_DRIVER_NAME) . ' ' . $db->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
$fails = 0; $n = 0;
function check(string $name, $cond, string $extra = ''): void
{
    global $fails, $n;
    $n++;
    if ($cond) { echo "ok   $name\n"; return; }
    $fails++;
    echo "FAIL $name" . ($extra !== '' ? "  ($extra)" : '') . "\n";
}
function section(string $s): void { echo "\n== $s\n"; }

$run = substr(md5((string) microtime(true)), 0, 6);
$pw = password_hash('x', PASSWORD_DEFAULT);
$roles = $db->query('SELECT role_name, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
$mkuser = function (string $key, string $role, string $first, string $last) use ($db, $pw, $roles, $run): int {
    $db->prepare("INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status) VALUES (?, ?, ?, ?, 1, 1, 'active')")->execute(["bg_$key$run", "$key$run@bg.test", $pw, $roles[$role]]);
    $id = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)')->execute([$id, $first, $last]);
    return $id;
};
$admin = $mkuser('admin', 'admin', 'Alice', 'Admin');
$super = $mkuser('super', 'superadmin', 'Simon', 'Super');
$svc = new SecretaryService($db, fn() => ['ok' => true, 'message' => 'sent']);
$mksec = function (string $key, string $first, string $last, array $sw) use ($svc, $db, $run, $pw): array {
    [$d, $e] = $svc->validate(['first_name' => $first, 'last_name' => $last, 'email' => "$key$run@bg.test", 'phone' => '0712345678', 'branch' => 'nairobi', 'birth_day' => 3, 'birth_month' => 5, 'start_date' => '2026-01-05', 'sw' => $sw]);
    if ($e) throw new RuntimeException(implode('; ', $e));
    $r = $svc->create($d, null, 0);
    $db->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')->execute([$pw, (int) $r['user_id']]);
    return ['id' => (int) $r['secretary_id'], 'user_id' => (int) $r['user_id'], 'email' => "$key$run@bg.test", 'full_name' => "$first $last", 'switches' => secretary_switches($db, (int) $r['secretary_id'])];
};
$S1 = $mksec('first', 'Faith', 'First', ['blogs' => 'on', 'blogs_approval' => 'off']);    // the first secretary: posts go live at once
$S2 = $mksec('later', 'Lena', 'Later', ['blogs' => 'on', 'blogs_approval' => 'on']);      // every later secretary: approval needed
$S3 = $mksec('noblog', 'Nina', 'Noblogs', ['blogs' => 'off', 'blogs_approval' => 'on']);
$db->prepare("INSERT INTO blog_categories (name, slug) VALUES (?, ?)")->execute(["Cat $run", "cat-$run"]);
$cat = (int) $db->lastInsertId();
$blogSvc = new SecretaryBlogService($db);
$logStart = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM secretary_blog_log')->fetchColumn();   // only this run's record is checked (a scratch database keeps older runs)
$blogCount = fn() => (int) $db->query('SELECT COUNT(*) FROM blogs')->fetchColumn();
$blogRow = fn(int $id) => $db->query("SELECT * FROM blogs WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
$cover = '/uploads/blogs/test-cover.jpg';
$form = fn(array $o = []) => $o + ['title' => "Post $run", 'excerpt' => 'Short', 'content' => '<p>Hello <strong>world</strong></p>', 'meta_description' => 'Desc', 'meta_title' => '', 'meta_keywords' => '', 'category_id' => $cat, 'tags' => 'Alpha, beta', 'is_featured' => '1'];
$sess = fn(array $s) => $s;   // her record as secretary_require() returns it

section('Migrations 125 and 126');
$tmp = __DIR__ . '/sec_mig_tmp_' . getmypid(); mkdir($tmp);
foreach (['125_secretary_blog_submissions.sql', '126_secretary_blog_log.sql'] as $f) copy(__DIR__ . '/../databases/required_migrations/' . $f, "$tmp/$f");
$db->exec("DELETE FROM schema_migrations WHERE filename IN ('125_secretary_blog_submissions.sql', '126_secretary_blog_log.sql')");
$r = (new MigrationService($db, $tmp))->applyPending(static function (string $m): void {});
array_map('unlink', glob("$tmp/*")); rmdir($tmp);
check('they run a second time and change nothing', $r['ran'] === 2 && empty($r['failed']));
check('they are numbers 125 and 126, each used once', count(glob(__DIR__ . '/../databases/required_migrations/125_*')) === 1 && count(glob(__DIR__ . '/../databases/required_migrations/126_*')) === 1);
$cols = array_column($db->query("SELECT column_name AS cn FROM information_schema.columns WHERE table_schema = $schemaNow AND table_name = 'blogs'")->fetchAll(PDO::FETCH_ASSOC), 'cn');
check('the existing blogs table has no new column (nothing was added to it)', !array_filter($cols, fn($c) => preg_match('/approv|waiting|submission|secretary/i', $c)) && in_array('status', $cols, true), implode(',', $cols));

section('The approval switch');
check('off means no approval needed; on means needed', !SecretaryBlogService::approvalNeeded($db, $S1['id']) && SecretaryBlogService::approvalNeeded($db, $S2['id']));
$db->exec('DELETE FROM secretary_switches WHERE secretary_id = ' . $S3['id'] . " AND switch_key = 'blogs_approval'");
check('a missing switch row counts as needed (the safe side)', SecretaryBlogService::approvalNeeded($db, $S3['id']));

section('Cleaning the body');
$dirty = '<p onclick="x()">Hi</p><script>alert(1)</script><a href="javascript:alert(2)">l</a><img src="/a.png" onerror=alert(3)><div class="dz-yt"><iframe src="https://www.youtube.com/embed/abcdefghijk"></iframe></div>';
$c = $blogSvc->clean($dirty);
check('scripts, inline handlers and javascript: links are removed', !preg_match('/<script|onclick|onerror|javascript:/i', $c), $c);
check('text, images and the YouTube video of the editor are kept', str_contains($c, 'Hi') && str_contains($c, '<img src="/a.png"') && str_contains($c, 'youtube.com/embed/abcdefghijk'));
$png = 'data:image/png;base64,' . base64_encode((function () { $i = imagecreatetruecolor(8, 8); ob_start(); imagepng($i); return ob_get_clean(); })());
$c2 = $blogSvc->clean('<p><img src="' . $png . '"></p>');
check('a pasted base64 picture becomes a file, like the admin editor does', !str_contains($c2, 'base64') && preg_match('#/uploads/blogs/content/#', $c2) === 1, substr($c2, 0, 120));
if (preg_match('#src="([^"]+)"#', $c2, $m)) { foreach ([dirname(__DIR__, 3) . '/public' . $m[1]] as $fp) @unlink($fp); }

section('The form checks');
$chk = fn(array $o) => $blogSvc->fields($form($o), true, false)[1];
check('a good form passes', !$chk([]));
check('empty title, empty content, a long tag, a missing category are refused', $chk(['title' => ' ']) && $chk(['content' => '<p><br></p>']) && $chk(['tags' => str_repeat('x', 60)]) && $chk(['category_id' => 999999]));
check('a video-only post is fine as content', !$chk(['content' => '<iframe src="https://www.youtube.com/embed/abcdefghijk"></iframe>']));
[$pf] = $blogSvc->fields($form(['tags' => 'PHP, php, MySQL']), true, false);
check('tags are trimmed and not repeated', $pf['tags'] === ['PHP', 'MySQL']);

section('Approval OFF: live at once, as the admin');
$before = $blogCount();
$r = $blogSvc->save($S1, $form(['status' => 'published']), $cover, null, null);
$b1 = (int) ($r['blog_id'] ?? 0);
check('a new post is created in the blogs table, published', !empty($r['ok']) && $r['mode'] === 'live' && $blogCount() === $before + 1 && $blogRow($b1)['status'] === 'published' && !empty($blogRow($b1)['published_at']), json_encode($r));
$row = $blogRow($b1);
check('the author is the secretary; fields, slug, category, featured flag and cover are as the admin code makes them', (int) $row['author_id'] === $S1['user_id'] && $row['slug'] === strtolower("post-$run") && (int) $row['category_id'] === $cat && (int) $row['is_featured'] === 1 && $row['featured_image'] === $cover && $row['excerpt'] === 'Short');
check('tags were made by the existing code', (int) $db->query("SELECT COUNT(*) FROM blog_tag_relations WHERE blog_id = $b1")->fetchColumn() === 2);
$r = $blogSvc->save($S1, $form(['title' => "Draft $run", 'status' => 'draft']), $cover, null, null);
$b2 = (int) $r['blog_id'];
check('a draft is saved as a draft', $blogRow($b2)['status'] === 'draft' && empty($blogRow($b2)['published_at']));
$r = $blogSvc->save($S1, $form(['title' => "Post $run v2", 'content' => '<p>Changed text</p>']), null, $b1, null);
check('changing a published blog goes live at once', !empty($r['ok']) && $r['mode'] === 'live' && $blogRow($b1)['title'] === "Post $run v2" && str_contains($blogRow($b1)['content'], 'Changed text') && $blogRow($b1)['status'] === 'published');
check('the cover stays when no new one is sent', $blogRow($b1)['featured_image'] === $cover);
check('no waiting rows were made for her', (int) $db->query("SELECT COUNT(*) FROM secretary_blog_submissions WHERE secretary_id = " . $S1['id'])->fetchColumn() === 0);
$lg = fn(string $a, ?int $blog = null) => $db->query("SELECT * FROM secretary_blog_log WHERE action = '$a'" . ($blog ? " AND blog_id = $blog" : '') . ' ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
check('created and updated are recorded with her name and the time', ($lg('created', $b1)['actor_name'] ?? '') === 'Faith First' && !empty($lg('created', $b1)['created_at']) && ($lg('updated', $b1)['actor_name'] ?? '') === 'Faith First');
check('a post needs a cover', !$blogSvc->save($S1, $form(['title' => 'No cover']), null, null, null)['ok']);

section('Unpublish and publish again');
$r = $blogSvc->setPublished($S1, $b1, false);
check('unpublish: the status becomes draft and it is recorded with her name', $r['ok'] && $blogRow($b1)['status'] === 'draft' && ($lg('unpublished', $b1)['actor_name'] ?? '') === 'Faith First');
check('unpublishing twice is refused', !$blogSvc->setPublished($S1, $b1, false)['ok']);
$r = $blogSvc->setPublished($S1, $b1, true);
check('publish again: published, recorded', $r['ok'] && $blogRow($b1)['status'] === 'published' && ($lg('republished', $b1)['actor_name'] ?? '') === 'Faith First');
check('publishing a published blog is refused', !$blogSvc->setPublished($S1, $b1, true)['ok']);
check('with approval off she can publish a draft that was never published', $blogSvc->setPublished($S1, $b2, true)['ok']);
$blogSvc->setPublished($S1, $b2, false);
check('there is no delete anywhere in the service', !array_filter(get_class_methods($blogSvc), fn($m) => stripos($m, 'delete') !== false || stripos($m, 'remove') !== false));

section('Approval ON: it waits');
PortalNotifications::saveChoices($db, $admin, ['other']);
PortalNotifications::saveChoices($db, $S2['user_id'], ['other']);
$nAdmin = fn() => (int) $db->query("SELECT COUNT(*) FROM portal_notifications WHERE user_id = $admin AND type = 'blog_approval'")->fetchColumn();
$nSec = fn(int $u) => (int) $db->query("SELECT COUNT(*) FROM portal_notifications WHERE user_id = $u AND type = 'blog_approval'")->fetchColumn();
$before = $blogCount(); $na = $nAdmin();
$r = $blogSvc->save($S2, $form(['title' => "Later new $run", 'status' => 'draft']), $cover, null, null);
$sub1 = (int) ($r['sub_id'] ?? 0);
check('a new post is saved as waiting and NOTHING is added to the blogs table', !empty($r['ok']) && $r['mode'] === 'waiting' && $blogCount() === $before && $sub1 > 0);
$sr = $blogSvc->submission($sub1);
check('the waiting row holds the fields', $sr['status'] === 'waiting' && $sr['kind'] === 'new' && $sr['data']['title'] === "Later new $run" && $sr['data']['tags'] === ['Alpha', 'beta'] && $sr['data']['featured_image'] === $cover);
check('the public query never finds it (no blog with that title)', (int) $db->query("SELECT COUNT(*) FROM blogs WHERE title = 'Later new $run'")->fetchColumn() === 0);
check('the admin was told (existing notification code), and it was recorded', $nAdmin() === $na + 1 && ($lg('submitted')['actor_name'] ?? '') === 'Lena Later');
[$rows, $counts] = $blogSvc->listing('all');
$mine = array_values(array_filter($rows, fn($x) => $x['sub_id'] === $sub1))[0] ?? null;
check('her list shows it as Waiting for approval', $mine && $mine['status'] === 'waiting' && $counts['waiting'] >= 1);
check('the Waiting filter has it; Published and Unpublished do not', in_array($sub1, array_column($blogSvc->listing('waiting')[0], 'sub_id'), true) && !in_array($sub1, array_column($blogSvc->listing('published')[0], 'sub_id'), true) && !in_array($sub1, array_column($blogSvc->listing('unpublished')[0], 'sub_id'), true));
$r = $blogSvc->save($S2, $form(['title' => "Later new $run (edited)"]), null, null, $sub1);
check('editing a waiting post keeps one row and raises its version', $r['ok'] && (int) $db->query("SELECT COUNT(*) FROM secretary_blog_submissions WHERE secretary_id = " . $S2['id'])->fetchColumn() === 1 && (int) $blogSvc->submission($sub1)['rev'] === 2);

section('Approval ON: a change to a live blog is a pending change');
$live = $blogRow($b1);
$r = $blogSvc->save($S2, $form(['title' => "Post $run NEW TITLE", 'content' => '<p>Hello <strong>world</strong></p><p>Second paragraph added</p>', 'tags' => 'Alpha']), null, $b1, null);
$sub2 = (int) ($r['sub_id'] ?? 0);
check('the change waits; the live blog is exactly as it was', !empty($r['ok']) && $r['mode'] === 'waiting' && $blogRow($b1)['title'] === $live['title'] && $blogRow($b1)['content'] === $live['content'] && $blogRow($b1)['updated_at'] === $live['updated_at']);
$s2r = $blogSvc->submission($sub2);
check('it is a change to that blog, with the live version noted', $s2r['kind'] === 'change' && (int) $s2r['blog_id'] === $b1 && $s2r['base_updated_at'] !== '');
[$rows] = $blogSvc->listing('all');
$bl = array_values(array_filter($rows, fn($x) => $x['blog_id'] === $b1))[0];
check('the blog still shows Published, with "change waiting"', $bl['status'] === 'published' && $bl['pending'] === 'waiting');
check('opening the editor shows her pending version', $blogSvc->load($b1, null)[0]['title'] === "Post $run NEW TITLE" && $blogSvc->load($b1, null)[1]['sub_id'] === $sub2);
$r = $blogSvc->save($S2, $form(['title' => "Post $run NEW TITLE 2", 'content' => '<p>Hello <strong>world</strong></p><p>Second paragraph added</p>', 'tags' => 'Alpha']), null, $b1, null);
check('a second save updates the same pending change (one open change per blog)', (int) $db->query("SELECT COUNT(*) FROM secretary_blog_submissions WHERE blog_id = $b1 AND status IN ('waiting','sent_back')")->fetchColumn() === 1);
check('a draft (admin\'s or hers) changed with approval on also only waits', !empty($blogSvc->save($S2, $form(['title' => 'Edit of draft']), null, $b2, null)['sub_id']) && $blogRow($b2)['title'] === "Draft $run");

section('Approval ON: unpublish and publish again');
check('she can unpublish a live blog', $blogSvc->setPublished($S2, $b1, false)['ok'] && $blogRow($b1)['status'] === 'draft' && ($lg('unpublished', $b1)['actor_name'] ?? '') === 'Lena Later');
check('she can publish it again, because it was published (approved) before', $blogSvc->setPublished($S2, $b1, true)['ok'] && $blogRow($b1)['status'] === 'published');
$b3 = (int) $blogSvc->save($S1, $form(['title' => "Never published $run", 'status' => 'draft']), $cover, null, null)['blog_id'];
check('but not a post that was never published (it needs approval)', !$blogSvc->setPublished($S2, $b3, true)['ok'] && $blogRow($b3)['status'] === 'draft');

section('Compare');
$cmp = $blogSvc->compare($blogSvc->submission($sub2));
check('the fields that differ are listed (title, tags)', isset($cmp['fields']['Title']) && $cmp['fields']['Title'][1] === "Post $run NEW TITLE 2" && isset($cmp['fields']['Tags']));
check('the text shows what was added and what was removed', (bool) array_filter($cmp['lines'], fn($l) => $l[0] === '+' && str_contains($l[1], 'Second paragraph')) && (bool) array_filter($cmp['lines'], fn($l) => $l[0] === '-' && str_contains($l[1], 'Changed text')));
check('the line diff itself: removed, added, same', SecretaryBlogService::diffLines(['a', 'b', 'c'], ['a', 'x', 'c']) === [['=', 'a'], ['-', 'b'], ['+', 'x'], ['=', 'c']]);
check('html becomes lines', SecretaryBlogService::textLines('<p>One &amp; two</p><p>Three<br>four</p>') === ['One & two', 'Three', 'four']);
$db->prepare('UPDATE blogs SET meta_title = ?, updated_at = ? WHERE id = ?')->execute(['Admin edit', '2031-01-01 00:00:00', $b1]);
check('if the live blog changed after the change was started, Compare warns', $blogSvc->compare($blogSvc->submission($sub2))['live_changed'] === true);

section('Approve and publish (the admin)');
$na2 = $nSec($S2['user_id']);
check('approving a version the admin did not see is refused', !$blogSvc->approve($admin, $sub1, 1)['ok']);
$r = $blogSvc->approve($admin, $sub1, 2);
$nb = (int) ($r['blog_id'] ?? 0);
check('the new post is published through the existing blog code', !empty($r['ok']) && $nb > 0 && $blogRow($nb)['status'] === 'published' && $blogRow($nb)['title'] === "Later new $run (edited)" && !empty($blogRow($nb)['published_at']));
$nr = $blogRow($nb);
check('the result matches an admin post: author is the secretary, slug, category, tags, featured, cover', (int) $nr['author_id'] === $S2['user_id'] && (int) $nr['category_id'] === $cat && (int) $nr['is_featured'] === 1 && $nr['featured_image'] === $cover
    && (int) $db->query("SELECT COUNT(*) FROM blog_tag_relations WHERE blog_id = $nb")->fetchColumn() === 2);
$sr = $blogSvc->submission($sub1);
check('the row is closed with the admin\'s name and the time, and points at the blog', $sr['status'] === 'approved' && $sr['decided_by_name'] === 'Alice Admin' && !empty($sr['decided_at']) && (int) $sr['result_blog_id'] === $nb);
check('approved is recorded with the admin\'s name; the secretary was told', ($lg('approved', $nb)['actor_name'] ?? '') === 'Alice Admin' && $nSec($S2['user_id']) === $na2 + 1);
check('approving twice is refused', !$blogSvc->approve($admin, $sub1, 2)['ok']);
$r = $blogSvc->approve($admin, $sub2, 2);
check('approving the change updates the live blog with her version', !empty($r['ok']) && $blogRow($b1)['title'] === "Post $run NEW TITLE 2" && str_contains($blogRow($b1)['content'], 'Second paragraph added') && $blogRow($b1)['status'] === 'published');
check('the change leaves the blog\'s author and slug rules as the admin code does', (int) $blogRow($b1)['author_id'] === $S1['user_id']);
check('nothing is waiting for that blog any more', (int) $db->query("SELECT COUNT(*) FROM secretary_blog_submissions WHERE blog_id = $b1 AND status IN ('waiting','sent_back')")->fetchColumn() === 0);

section('Send back');
$r = $blogSvc->save($S2, $form(['title' => "Needs fixing $run"]), $cover, null, null);
$sub3 = (int) $r['sub_id'];
check('a reason is required, and it must be short', !$blogSvc->sendBack($admin, $sub3, '  ', 1)['ok'] && !$blogSvc->sendBack($admin, $sub3, str_repeat('x', 301), 1)['ok'] && $blogSvc->submission($sub3)['status'] === 'waiting');
$na3 = $nSec($S2['user_id']);
$r = $blogSvc->sendBack($admin, $sub3, 'Please add a better cover.', 1);
$s3 = $blogSvc->submission($sub3);
check('sent back with the reason; nothing was published', $r['ok'] && $s3['status'] === 'sent_back' && $s3['reason'] === 'Please add a better cover.' && (int) $db->query("SELECT COUNT(*) FROM blogs WHERE title = 'Needs fixing $run'")->fetchColumn() === 0);
check('recorded with the admin\'s name and the reason; the secretary was told', ($lg('sent_back')['actor_name'] ?? '') === 'Alice Admin' && ($lg('sent_back')['detail'] ?? '') === 'Please add a better cover.' && $nSec($S2['user_id']) === $na3 + 1);
[$rows] = $blogSvc->listing('all');
$sb = array_values(array_filter($rows, fn($x) => $x['sub_id'] === $sub3))[0];
check('her list shows Sent back with the reason', $sb['status'] === 'sent_back' && $sb['reason'] === 'Please add a better cover.' && $blogSvc->listing('all')[3] >= 1);
check('a sent-back post cannot be approved', !$blogSvc->approve($admin, $sub3, 1)['ok']);
$r = $blogSvc->save($S2, $form(['title' => "Fixed $run"]), null, null, $sub3);
$s3 = $blogSvc->submission($sub3);
check('she fixes it and sends it again: waiting, reason cleared, version up', $r['ok'] && $s3['status'] === 'waiting' && $s3['reason'] === null && (int) $s3['rev'] === 2 && ($lg('resubmitted')['actor_name'] ?? '') === 'Lena Later');
check('the admin\'s list of waiting items has it', in_array($sub3, array_column($blogSvc->waiting(), 'id'), true) && $blogSvc->waitingCount() >= 1);

section('Edge cases');
$r = $blogSvc->save($S2, $form(['title' => "Change for a deleted blog $run"]), null, $b2, null);
$sub4 = (int) $r['sub_id'];
$db->exec("DELETE FROM blogs WHERE id = $b2");
$rev4 = (int) $blogSvc->submission($sub4)['rev'];
check('a change for a blog the admin deleted cannot be approved, but can be sent back', !$blogSvc->approve($admin, $sub4, $rev4)['ok'] && $blogSvc->sendBack($admin, $sub4, 'The blog was removed.', $rev4)['ok']);
check('editing a blog that does not exist is refused', !$blogSvc->save($S1, $form(), null, 999999, null)['ok']);
$r = $blogSvc->save($S2, $form(['title' => "Open when switched off $run"]), $cover, null, null);
$sub5 = (int) $r['sub_id'];
$db->exec("UPDATE secretary_switches SET switch_value = 'off' WHERE secretary_id = " . $S2['id'] . " AND switch_key = 'blogs_approval'");
$r = $blogSvc->save($S2, $form(['title' => "Open when switched off $run", 'status' => 'published']), null, null, $sub5);
check('if the admin switches approval off while a post is waiting, her next save publishes it live and closes the row', !empty($r['ok']) && $r['mode'] === 'live' && $blogSvc->submission($sub5)['status'] === 'approved'
    && (int) $db->query("SELECT COUNT(*) FROM blogs WHERE title = 'Open when switched off $run' AND status = 'published'")->fetchColumn() === 1);
$db->exec("UPDATE secretary_switches SET switch_value = 'on' WHERE secretary_id = " . $S2['id'] . " AND switch_key = 'blogs_approval'");

section('Notices and the record');
check('the record has an entry for every kind of action, each with a name and a time', (function () use ($db, $logStart) {
    $a = $db->query("SELECT DISTINCT action FROM secretary_blog_log WHERE id > $logStart")->fetchAll(PDO::FETCH_COLUMN);
    $miss = array_diff(['created', 'updated', 'submitted', 'change_submitted', 'resubmitted', 'unpublished', 'republished', 'approved', 'sent_back'], $a);
    return !$miss && !$db->query("SELECT 1 FROM secretary_blog_log WHERE id > $logStart AND (actor_name = '' OR created_at IS NULL)")->fetchColumn();
})());
check('every approval or send-back names the admin; every secretary action names the secretary', !$db->query("SELECT 1 FROM secretary_blog_log WHERE id > $logStart AND ((action IN ('approved','sent_back') AND (actor_role <> 'admin' OR actor_name <> 'Alice Admin')) OR (action NOT IN ('approved','sent_back') AND actor_role <> 'secretary'))")->fetchColumn());

// for secretary_blogs_http.sh
$db->exec('DELETE FROM secretary_blog_submissions');
$db->prepare("UPDATE secretary_switches SET switch_value = 'on' WHERE secretary_id = ? AND switch_key IN ('blogs')")->execute([$S3['id']]);
$db->prepare("UPDATE secretary_switches SET switch_value = 'off' WHERE secretary_id = ? AND switch_key = 'blogs'")->execute([$S3['id']]);
$db->prepare("UPDATE secretary_switches SET switch_value = 'on' WHERE secretary_id = ? AND switch_key = 'blogs_approval'")->execute([$S2['id']]);
$out = ['first' => ['id' => $S1['user_id'], 'sid' => $S1['id'], 'email' => $S1['email']], 'later' => ['id' => $S2['user_id'], 'sid' => $S2['id'], 'email' => $S2['email']],
        'noblog' => ['id' => $S3['user_id'], 'sid' => $S3['id'], 'email' => $S3['email']], 'admin' => ['id' => $admin, 'email' => "admin$run@bg.test"], 'super' => ['id' => $super, 'email' => "super$run@bg.test"],
        '_run' => $run, '_cat' => $cat];
file_put_contents((getenv('TMPDIR') ?: getenv('TEMP') ?: sys_get_temp_dir()) . '/secretary_blogs_users.json', json_encode($out));
echo "\n" . ($fails === 0 ? "All $n checks passed." : "$fails of $n checks FAILED.") . "\n";
exit($fails === 0 ? 0 : 1);
