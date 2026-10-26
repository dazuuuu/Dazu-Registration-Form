<?php
// Open submissions: work a student hands in without an assignment being set first (table from migration 067).
// Each one has a title and a GitHub link, a Google Drive/Docs link or a document, plus an optional note. Links are
// checked against GitHub / Google before they are accepted, and what was found (repo name, file title…) is kept in link_meta.
// Documents live in the private assignment storage and are served by public/assignments/file.php?t=open.

require_once __DIR__ . '/assignment_files.php';

const OS_DOC_TYPES = ['pdf', 'docx', 'xlsx', 'pptx', 'zip'];

/** GET a URL without following redirects. Returns [status, body, location] or null when the site cannot be reached. */
function os_http_get(string $url, array $headers = [], bool $follow = false): ?array
{
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'DazuPortal/1.0 (+link check)', CURLOPT_HTTPHEADER => $headers, CURLOPT_HEADER => true,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) return null;
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hlen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $loc = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    return [$status, mb_substr(substr((string) $raw, $hlen), 0, 200000), $loc];
}

/**
 * Check a submitted link. Only GitHub and Google Drive/Docs are accepted, and the repo/file must really exist and be
 * open to the tutor (public repo, or a Drive file shared with "anyone with the link").
 * @return array{ok:bool, kind?:string, url?:string, meta?:array, error?:string}
 */
function os_check_link(string $raw): array
{
    $raw = trim($raw);
    if ($raw !== '' && !preg_match('#^[a-z]+://#i', $raw)) $raw = 'https://' . $raw;   // "github.com/me/repo" as typed
    $raw = preg_replace('#^http://#i', 'https://', $raw);
    $url = asg_clean_link($raw, true);
    if (!$url) return ['ok' => false, 'error' => 'Enter a full link starting with https://'];
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

    if ($host === 'github.com' || $host === 'www.github.com') {
        $parts = array_values(array_filter(explode('/', $path), 'strlen'));
        if (!$parts || !preg_match('/^[A-Za-z0-9-]{1,39}$/', $parts[0])) return ['ok' => false, 'error' => 'That is not a GitHub repository or profile link.'];
        $repo = isset($parts[1]) ? preg_replace('/\.git$/', '', $parts[1]) : null;
        if ($repo !== null && !preg_match('/^[A-Za-z0-9._-]{1,100}$/', $repo)) return ['ok' => false, 'error' => 'That is not a valid GitHub repository link.'];
        return os_check_github($url, $parts[0], $repo);
    }

    if (in_array($host, ['drive.google.com', 'docs.google.com'], true)) {
        if (!preg_match('#/(?:file/d|document/d|spreadsheets/d|presentation/d|forms/d|drive/folders|drive/u/\d+/folders)/([A-Za-z0-9_-]{10,})|[?&]id=([A-Za-z0-9_-]{10,})#', $url)) {
            return ['ok' => false, 'error' => 'That is not a link to a Google Drive file or folder. Use Share → Copy link.'];
        }
        $r = os_http_get($url);
        if ($r === null || $r[0] >= 500) {
            return ['ok' => true, 'kind' => 'drive', 'url' => $url, 'meta' => ['verified' => false, 'name' => 'Google Drive file']];
        }
        if ($r[0] === 404) return ['ok' => false, 'error' => 'That Google Drive file does not exist. Check the link.'];
        if (($r[0] >= 300 && $r[0] < 400 && str_contains($r[2], 'accounts.google.com')) || $r[0] === 401 || $r[0] === 403) {
            return ['ok' => false, 'error' => 'That file is private. In Google Drive choose Share → General access → "Anyone with the link", then paste the link again.'];
        }
        $name = 'Google Drive file';
        if (preg_match('#<title>(.*?)</title>#is', $r[1], $m)) {
            $name = trim(preg_replace('/\s+-\s+Google (Drive|Docs|Sheets|Slides|Forms)$/', '', html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8'))) ?: $name;
        }
        return ['ok' => true, 'kind' => 'drive', 'url' => $url, 'meta' => ['verified' => true, 'name' => mb_substr($name, 0, 200)]];
    }

    return ['ok' => false, 'error' => 'Only GitHub and Google Drive / Google Docs links are accepted. For anything else, upload the document.'];
}

/** GitHub API headers; a token (GITHUB_TOKEN in the environment or app config 'github_token') raises the limit from 60 to 5000 checks an hour. */
function os_github_headers(): array
{
    $h = ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
    $token = getenv('GITHUB_TOKEN') ?: '';
    if ($token === '' && is_file(APP_V1_ROOT . '/config/app.php')) {
        $cfg = require APP_V1_ROOT . '/config/app.php';
        $token = is_array($cfg) ? (string) ($cfg['github_token'] ?? '') : '';
    }
    if ($token !== '') $h[] = 'Authorization: Bearer ' . $token;
    return $h;
}

/** Top-level files of a public repo read from its github.com page (used when the API is rate-limited). */
function os_github_page_files(string $html): array
{
    preg_match_all('#"name":"([^"]{1,120})","path":"[^"]*","contentType":"(file|directory)"#', $html, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $x) $out[$x[1]] = ['name' => $x[1], 'type' => $x[2] === 'directory' ? 'dir' : 'file'];
    return array_values($out);
}

/** Check a GitHub repo or profile really exists and is public, and list the repo's top-level files. */
function os_check_github(string $url, string $owner, ?string $repo): array
{
    $label = $owner . ($repo ? '/' . $repo : '');
    $h = os_github_headers();
    $r = os_http_get('https://api.github.com/' . ($repo !== null ? "repos/$owner/$repo" : "users/$owner"), $h, true);
    $limited = $r === null || in_array($r[0], [403, 429], true) || $r[0] >= 500;

    if ($limited) {
        // API unreachable or rate-limited: look at the public github.com page instead.
        $p = os_http_get('https://github.com/' . $label, ['Accept: text/html'], true);
        if ($p === null || $p[0] >= 500 || $p[0] === 429) {
            return ['ok' => true, 'kind' => 'github', 'url' => $url, 'meta' => ['verified' => false, 'name' => $label]];
        }
        if ($p[0] !== 200) {
            return ['ok' => false, 'error' => $repo !== null ? 'That GitHub repository does not exist or is private. Make it public so your tutor can open it.' : 'That GitHub account does not exist.'];
        }
        if ($repo === null) return ['ok' => true, 'kind' => 'github', 'url' => $url, 'meta' => ['verified' => true, 'type' => 'profile', 'name' => $owner]];
        $files = os_github_page_files($p[1]);
        if (!$files && stripos($p[1], 'This repository is empty') !== false) return ['ok' => false, 'error' => 'That repository is empty. Push your code to it first.'];
        $desc = preg_match('#<meta name="description" content="([^"]*)"#', $p[1], $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : '';
        return ['ok' => true, 'kind' => 'github', 'url' => $url, 'meta' => ['verified' => true, 'type' => 'repo', 'name' => $label,
            'description' => mb_substr($desc, 0, 300), 'files' => array_slice($files, 0, 40), 'file_count' => count($files)]];
    }
    if ($r[0] !== 200) {
        return ['ok' => false, 'error' => $repo !== null ? 'That GitHub repository does not exist or is private. Make it public so your tutor can open it.' : 'That GitHub account does not exist.'];
    }
    $j = json_decode($r[1], true) ?: [];
    if ($repo === null) {
        return ['ok' => true, 'kind' => 'github', 'url' => $url, 'meta' => ['verified' => true, 'type' => 'profile', 'name' => (string) ($j['login'] ?? $owner),
            'description' => mb_substr((string) ($j['bio'] ?? ''), 0, 300), 'repos' => (int) ($j['public_repos'] ?? 0)]];
    }
    $full = (string) ($j['full_name'] ?? $label);
    $meta = ['verified' => true, 'type' => 'repo', 'name' => $full, 'description' => mb_substr((string) ($j['description'] ?? ''), 0, 300),
             'language' => (string) ($j['language'] ?? ''), 'stars' => (int) ($j['stargazers_count'] ?? 0), 'pushed_at' => (string) ($j['pushed_at'] ?? ''),
             'branch' => (string) ($j['default_branch'] ?? '')];
    // The repo "size" is often 0 right after a push, so emptiness is judged from the actual file list.
    $c = os_http_get('https://api.github.com/repos/' . $full . '/contents', $h, true);
    if ($c !== null && $c[0] === 200) {
        $list = json_decode($c[1], true) ?: [];
        $meta['files'] = array_slice(array_map(fn($f) => ['name' => (string) ($f['name'] ?? ''), 'type' => ($f['type'] ?? '') === 'dir' ? 'dir' : 'file'], is_array($list) ? $list : []), 0, 40);
        $meta['file_count'] = is_array($list) ? count($list) : 0;
        if ($meta['file_count'] === 0) return ['ok' => false, 'error' => 'That repository is empty. Push your code to it first.'];
    } elseif ($c !== null && $c[0] === 404) {
        return ['ok' => false, 'error' => 'That repository is empty. Push your code to it first.'];
    }
    return ['ok' => true, 'kind' => 'github', 'url' => $url, 'meta' => $meta];
}

/** Tutors this student is assigned to: [tutors.id => name]. */
function os_student_tutors(PDO $db, int $uid): array
{
    $st = $db->prepare(
        "SELECT t.id, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS name
         FROM tutor_students ts JOIN tutors t ON t.id = ts.tutor_id AND t.is_active = 1 JOIN users u ON u.id = t.user_id
         LEFT JOIN user_profiles up ON up.user_id = u.id WHERE ts.user_id = ? ORDER BY ts.assigned_at"
    );
    $st->execute([$uid]);
    return $st->fetchAll(PDO::FETCH_KEY_PAIR);
}

function os_for_student(PDO $db, int $uid): array
{
    $st = $db->prepare('SELECT * FROM open_submissions WHERE student_user_id = ? ORDER BY submitted_at DESC, id DESC');
    $st->execute([$uid]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** One submission with the student's name and course. With $tutorId, only if it was sent to that tutor. */
function os_get(PDO $db, int $id, ?int $tutorId = null): ?array
{
    $st = $db->prepare(
        "SELECT s.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS full_name,
                c.title AS course_title, (SELECT r.registration_no FROM registrations r WHERE r.user_id = s.student_user_id ORDER BY r.id DESC LIMIT 1) AS registration_no
         FROM open_submissions s JOIN users u ON u.id = s.student_user_id LEFT JOIN user_profiles up ON up.user_id = u.id
         LEFT JOIN courses c ON c.id = s.course_id WHERE s.id = ?" . ($tutorId !== null ? ' AND s.tutor_id = ?' : '')
    );
    $st->execute($tutorId !== null ? [$id, $tutorId] : [$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** A tutor's queue: 'awaiting' (oldest first), 'graded', 'returned' or 'all'. */
function os_for_tutor(PDO $db, int $tutorId, string $tab, string $q = ''): array
{
    $sql = "SELECT s.*, COALESCE(NULLIF(TRIM(CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,''))), ''), u.username) AS full_name, c.title AS course_title
            FROM open_submissions s JOIN users u ON u.id = s.student_user_id LEFT JOIN user_profiles up ON up.user_id = u.id
            LEFT JOIN courses c ON c.id = s.course_id WHERE s.tutor_id = ?";
    $args = [$tutorId];
    $status = ['awaiting' => 'submitted', 'graded' => 'graded', 'returned' => 'returned'][$tab] ?? null;
    if ($status) { $sql .= ' AND s.status = ?'; $args[] = $status; }
    if ($q !== '') { $sql .= " AND (CONCAT(COALESCE(up.first_name,''), ' ', COALESCE(up.last_name,'')) LIKE ? OR u.username LIKE ? OR s.title LIKE ?)"; array_push($args, "%$q%", "%$q%", "%$q%"); }
    $sql .= $tab === 'awaiting' ? ' ORDER BY s.submitted_at ASC' : ' ORDER BY COALESCE(s.graded_at, s.submitted_at) DESC';
    $st = $db->prepare($sql);
    $st->execute($args);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function os_count_awaiting(PDO $db, int $tutorId): int
{
    try {
        $st = $db->prepare("SELECT COUNT(*) FROM open_submissions WHERE tutor_id = ? AND status = 'submitted'");
        $st->execute([$tutorId]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Save a new submission ($existing = null) or a resubmission of returned work. $work: kind, url, meta, file (stored file array), note.
 * A resubmission without a new link or file keeps what was there.
 */
function os_save(PDO $db, array $row, ?array $existing): int
{
    $now = date('Y-m-d H:i:s');
    $f = $row['file'] ?? null;
    $cols = ['title' => $row['title'], 'note' => $row['note'], 'tutor_id' => $row['tutor_id'], 'course_id' => $row['course_id']];
    if ($row['kind'] !== null) {
        $cols += ['kind' => $row['kind'], 'url' => $row['url'], 'link_meta' => $row['meta'] ? json_encode($row['meta']) : null,
                  'file_path' => $f['path'] ?? null, 'original_name' => $f['name'] ?? null, 'mime' => $f['mime'] ?? null, 'bytes' => $f['bytes'] ?? null];
    }
    if ($existing) {
        if ($row['kind'] !== null && $existing['file_path'] && $existing['file_path'] !== ($f['path'] ?? null) && ($old = asg_file_abs($existing['file_path']))) {
            @unlink($old);
        }
        $cols += ['status' => 'submitted', 'submitted_at' => $now, 'updated_at' => $now];
        $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($cols)));
        $db->prepare("UPDATE open_submissions SET $set WHERE id = ?")->execute([...array_values($cols), (int) $existing['id']]);
        return (int) $existing['id'];
    }
    $cols += ['student_user_id' => $row['student_user_id'], 'status' => 'submitted', 'submitted_at' => $now];
    $db->prepare('INSERT INTO open_submissions (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
       ->execute(array_values($cols));
    return (int) $db->lastInsertId();
}

/** Grade ('graded') or send back for changes ('returned'). */
function os_grade(PDO $db, int $id, string $status, ?float $score, float $max, string $feedback, int $byUserId): void
{
    $db->prepare('UPDATE open_submissions SET status = ?, score = ?, max_score = ?, feedback = ?, graded_by = ?, graded_at = ? WHERE id = ?')
       ->execute([$status, $score, $max, $feedback !== '' ? $feedback : null, $byUserId, date('Y-m-d H:i:s'), $id]);
}

function os_score_text(array $s): string
{
    if ($s['status'] !== 'graded' || $s['score'] === null) return '';
    $pct = (float) $s['max_score'] > 0 ? round((float) $s['score'] / (float) $s['max_score'] * 100) : 0;
    return rtrim(rtrim(number_format((float) $s['score'], 2), '0'), '.') . ' / ' . rtrim(rtrim(number_format((float) $s['max_score'], 2), '0'), '.') . " ($pct%)";
}

function os_kind_label(string $kind): string
{
    return ['github' => 'GitHub', 'drive' => 'Google Drive', 'file' => 'Document'][$kind] ?? $kind;
}
