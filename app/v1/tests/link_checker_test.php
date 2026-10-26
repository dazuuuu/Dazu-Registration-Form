<?php
// Run: php app/v1/tests/link_checker_test.php
// No network: the fetch and DNS steps are replaced by fakes, so these test the rules, the SSRF guard, cache and rate limit.
require_once __DIR__ . '/../services/LinkChecker.php';

$fail = 0;
function check(string $name, $actual, $expected): void {
    global $fail;
    $ok = $actual === $expected;
    if (!$ok) { $fail++; }
    echo ($ok ? "ok   " : "FAIL ") . $name . ($ok ? '' : ' — got ' . var_export($actual, true) . ', expected ' . var_export($expected, true)) . "\n";
}

$public = fn($h) => ['140.82.112.3'];
/** Fake site: url => [code, location, body]. Records every URL fetched. */
function site(array $map, array &$seen = []): callable {
    return function ($url, $ip, $left) use ($map, &$seen) {
        $seen[] = $url;
        foreach ($map as $prefix => $r) if (str_starts_with($url, $prefix)) return ['code' => $r[0], 'location' => $r[1] ?? null, 'body' => $r[2] ?? ''];
        return ['code' => 0, 'location' => null, 'body' => '', 'error' => 'Timed out'];
    };
}
$run = fn(callable $f, string $url, ?string $sites = null, ?callable $dns = null) => (new LinkChecker(null, $f, $dns ?? $public))->check($url, $sites);

// GitHub
check('public GitHub = works', $run(site(['https://github.com/' => [200]]), 'https://github.com/amina/phishing')['status'], 'works');
$r = $run(site(['https://github.com/' => [404]]), 'https://github.com/amina/secret');
check('private or missing GitHub = not found', $r['status'], 'not_found');
check('not found message is clear', $r['message'], 'The link may have a spelling mistake, or the project is private.');
check('GitHub rate limit = could not check', $run(site(['https://github.com/' => [429]]), 'https://github.com/a/b')['status'], 'unknown');
check('GitHub 403 is not proof', $run(site(['https://github.com/' => [403]]), 'https://github.com/a/b')['status'], 'unknown');

// Google Drive / Docs
check('public Drive file = works', $run(site(['https://drive.google.com/file/d/' => [200, null, '<title>report.pdf - Google Drive</title>']]), 'https://drive.google.com/file/d/1AbCdEfGhIjK/view?usp=sharing')['status'], 'works');
$r = $run(site(['https://drive.google.com/file/d/' => [302, 'https://accounts.google.com/ServiceLogin?continue=x']]), 'https://drive.google.com/file/d/1AbCdEfGhIjK/view');
check('private Drive file = private', $r['status'], 'private');
check('private Drive fix text', $r['fix'], 'Press Share, then choose "Anyone with the link" and "Viewer".');
check('Docs request access page = amber', $run(site(['https://docs.google.com/document/d/' => [200, null, '<div>You need access</div><button>Request access</button>']]), 'https://docs.google.com/document/d/1ZyXwVuTsRqP/edit')['status'], 'request_access');
check('Docs 404 = not found', $run(site(['https://docs.google.com/' => [404]]), 'https://docs.google.com/document/d/1ZyXwVuTsRqP/edit')['status'], 'not_found');
check('sign-in page body = private', $run(site(['https://docs.google.com/' => [200, null, '<title>Sign in - Google Accounts</title>']]), 'https://docs.google.com/document/d/1ZyXwVuTsRqP/edit')['status'], 'private');
check('Drive probe uses the file id', LinkRules::probeUrl('google', 'https://drive.google.com/open?id=1AbCdEfGhIjKlM'), 'https://drive.google.com/file/d/1AbCdEfGhIjKlM/view');

// YouTube / Loom oEmbed, Figma unsure
$seen = [];
check('YouTube unlisted = works', $run(site(['https://www.youtube.com/oembed' => [200]], $seen), 'https://youtu.be/abc123')['status'], 'works');
check('YouTube uses oEmbed', str_starts_with($seen[0], 'https://www.youtube.com/oembed?'), true);
check('YouTube private = private', $run(site(['https://www.youtube.com/oembed' => [401]]), 'https://www.youtube.com/watch?v=abc')['status'], 'private');
check('YouTube fix text', LinkRules::fixText('youtube', 'youtube.com'), 'Public or Unlisted works. Private does not.');
check('Figma 200 is unsure', $run(site(['https://www.figma.com/' => [200]]), 'https://www.figma.com/design/abc')['status'], 'unknown');

// SSRF and allow-list
$seen = [];
$r = $run(site(['https://github.com/' => [302, 'https://evil.example.com/x']], $seen), 'https://github.com/a/b');
check('redirect to an off-list site is blocked', [$r['status'], count($seen)], ['unknown', 1]);
$dns = fn($h) => $h === 'gist.github.com' ? ['10.0.0.5'] : ['140.82.112.3'];
$seen = [];
$r = $run(site(['https://github.com/' => [301, 'https://gist.github.com/x'], 'https://gist.github.com/' => [200]], $seen), 'https://github.com/a/b', null, $dns);
check('redirect to an internal IP is blocked', [$r['status'], count($seen)], ['unknown', 1]);
check('internal IP for the first host is blocked', $run(site(['https://github.com/' => [200]]), 'https://github.com/a/b', null, fn($h) => ['127.0.0.1'])['status'], 'unknown');
check('any private address blocks the host', $run(site(['https://github.com/' => [200]]), 'https://github.com/a/b', null, fn($h) => ['140.82.112.3', '192.168.1.4'])['status'], 'unknown');
foreach (['10.1.2.3', '172.16.0.1', '192.168.0.1', '127.0.0.1', '169.254.169.254', '100.64.0.1', '0.0.0.0', '::1', 'fe80::1', 'fd00::1', '::ffff:127.0.0.1'] as $ip) {
    check("blocked IP $ip", LinkChecker::isPublicIp($ip), false);
}
check('public IPv4 allowed', LinkChecker::isPublicIp('8.8.8.8'), true);
check('public IPv6 allowed', LinkChecker::isPublicIp('2606:4700::1111'), true);
check('http (not https) refused', $run(site([]), 'http://github.com/a/b')['status'], 'not_allowed');
check('off-list site refused', $run(site([]), 'https://example.com/a', 'github.com')['status'], 'not_allowed');
check('site not on the assignment list refused', $run(site([]), 'https://gitlab.com/a/b', 'github.com')['status'], 'not_allowed');
$seen = [];
$run(site(['https://github.com/' => [302, 'https://github.com/a'], 'https://github.com/a' => [302, 'https://github.com/b']], $seen), 'https://github.com/x');
check('at most 4 redirects followed', count($seen), 5);

// Timeout never blocks
$r = $run(site([]), 'https://github.com/a/b');
check('timeout = could not check', [$r['status'], $r['bad']], ['unknown', false]);

// Cache and rate limit (in-memory database)
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE tutor_assignment_link_checks (id INTEGER PRIMARY KEY AUTOINCREMENT, submission_id INT, draft_key TEXT, checked_by INT, url_hash TEXT, host TEXT, site TEXT, status TEXT, http_code INT, reason TEXT, checked_at TEXT)");
$db->exec("CREATE TABLE tutor_assignment_events (id INTEGER PRIMARY KEY AUTOINCREMENT, assignment_id INT, actor_user_id INT, student_user_id INT, event TEXT, details TEXT, created_at TEXT)");
$seen = [];
$lc = new LinkChecker($db, site(['https://github.com/' => [200]], $seen), $public);
$lc->check('https://github.com/a/b', null, 7);
$r = $lc->check('https://github.com/a/b', null, 7);
check('results are cached', [count($seen), $r['cached'], $r['status']], [1, true, 'works']);
check('check is in the audit log', (int) $db->query("SELECT COUNT(*) FROM tutor_assignment_events WHERE event = 'link_check'")->fetchColumn() >= 1, true);
check('page contents never stored', (int) $db->query("SELECT COUNT(*) FROM tutor_assignment_link_checks WHERE reason LIKE '%<%'")->fetchColumn(), 0);
for ($i = 0; $i < 25; $i++) $r = $lc->check('https://github.com/a/r' . $i, null, 8);
check('rate limit per user', [$r['status'], count($seen)], ['unknown', 1 + LinkChecker::USER_LIMIT]);

// A bad link never changes Late, the sent time or the mark: the checker has no access to submissions at all.
$src = file_get_contents(__DIR__ . '/../services/LinkChecker.php');
check('checker never writes submissions', preg_match('/UPDATE\s+tutor_assignment_submissions/i', $src), 0);

echo $fail ? "\n$fail failed\n" : "\nAll passed\n";
exit($fail ? 1 : 0);
