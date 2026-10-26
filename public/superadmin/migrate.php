<?php
// public/superadmin/migrate.php — pull the whole site from the LIVE server into this one (run on the NEW host).
// Superadmin only, and the superadmin's password is re-checked before any connection is made. It connects to the live
// database, copies every table into the database this site is connected to, then downloads the uploaded files from the live site.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/Session.php';
require_once __DIR__ . '/../../app/v1/services/SiteBackupService.php';

Middleware::requireSuperPortal();

$pdo = \Models\Connection::getInstance()->getConnection();
$svc = new SiteBackupService($pdo);
$userId = (int) ($_SESSION['user_id'] ?? 0);

/** Only these file types are ever fetched from the live site: never scripts. */
const PULL_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico', 'mp4', 'webm', 'mov', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'zip'];

function pull_json(array $data, int $code = 200): void
{
    while (ob_get_level()) { ob_end_clean(); }
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

function pull_state_file(): string
{
    return sys_get_temp_dir() . '/dazu_pull_' . sha1(session_id()) . '.json';
}

function pull_connect(array $c): PDO
{
    $dsn = 'mysql:host=' . $c['host'] . ';port=' . (int) $c['port'] . ';dbname=' . $c['name'] . ';charset=utf8mb4';
    return new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 15,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
    ]);
}

function pull_download(string $url, string $dest): bool
{
    $tmp = $dest . '.part';
    $ok = false;
    if (function_exists('curl_init')) {
        $fh = @fopen($tmp, 'wb');
        if (!$fh) return false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 900,
            CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_FAILONERROR => true, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]);
        $ok = curl_exec($ch);
        curl_close($ch);
        fclose($fh);
    } elseif (ini_get('allow_url_fopen')) {
        $ok = @copy($url, $tmp);
    }
    if (!$ok || !is_file($tmp) || filesize($tmp) === 0) {
        @unlink($tmp);
        return false;
    }
    return @rename($tmp, $dest);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    @set_time_limit(0);
    @ignore_user_abort(true);
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        pull_json(['ok' => false, 'error' => 'Security check failed. Reload the page.'], 403);
    }
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'check') {
            if ($wait = (int) max(0, ($_SESSION['sa_pw_block_until'] ?? 0) - time())) {
                pull_json(['ok' => false, 'error' => 'Too many wrong passwords. Try again in ' . ceil($wait / 60) . ' minute(s).']);
            }
            $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? AND role_id = 1');
            $st->execute([$userId]);
            $hash = (string) $st->fetchColumn();
            if ($hash === '' || !password_verify((string) ($_POST['admin_password'] ?? ''), $hash)) {
                $_SESSION['sa_pw_fail'] = (int) ($_SESSION['sa_pw_fail'] ?? 0) + 1;
                if ($_SESSION['sa_pw_fail'] >= 5) { $_SESSION['sa_pw_block_until'] = time() + 300; $_SESSION['sa_pw_fail'] = 0; }
                pull_json(['ok' => false, 'error' => 'Your superadmin password is not correct.']);
            }
            $_SESSION['sa_pw_fail'] = 0;

            $site = rtrim(trim((string) ($_POST['site'] ?? '')), '/');
            $parts = parse_url($site);
            if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
                pull_json(['ok' => false, 'error' => 'Enter the live site address, for example https://yoursite.com']);
            }
            $src = [
                'host' => trim((string) ($_POST['db_host'] ?? '')),
                'port' => (int) ($_POST['db_port'] ?? 3306) ?: 3306,
                'name' => trim((string) ($_POST['db_name'] ?? '')),
                'user' => trim((string) ($_POST['db_user'] ?? '')),
                'pass' => (string) ($_POST['db_pass'] ?? ''),
            ];
            if ($src['host'] === '' || $src['name'] === '' || $src['user'] === '') {
                pull_json(['ok' => false, 'error' => 'Fill in the live database host, name and user.']);
            }
            try {
                $conn = pull_connect($src);
            } catch (Throwable $e) {
                pull_json(['ok' => false, 'error' => 'Could not connect to the live database: ' . $e->getMessage()
                    . ' (Many hosts only allow outside connections from addresses you whitelist, e.g. "Remote MySQL" in cPanel.)']);
            }
            $tables = [];
            foreach (SiteBackupService::listTables($conn) as $t) {
                $tables[] = ['name' => $t, 'rows' => (int) $conn->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $t) . '`')->fetchColumn()];
            }
            if (!$tables) {
                pull_json(['ok' => false, 'error' => 'Connected, but that database has no tables.']);
            }
            $_SESSION['pull'] = ['src' => $src, 'site' => $site, 'tables' => array_column($tables, 'name')];
            @unlink(pull_state_file());
            pull_json(['ok' => true, 'tables' => $tables, 'has_data' => (bool) SiteBackupService::listTables($pdo)]);
        }

        if (empty($_SESSION['pull'])) {
            pull_json(['ok' => false, 'error' => 'Session expired. Start again.'], 403);
        }
        $site = $_SESSION['pull']['site'];
        $p = parse_url($site);
        $manifest = ['web_root' => rtrim($p['path'] ?? '', '/'), 'site_url' => $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '')];
        $rewrite = $svc->rewriter($manifest);

        if ($action === 'begin') {
            $safety = null;
            if (SiteBackupService::listTables($pdo)) {
                $dir = SiteBackupService::backupsDir();
                if (!is_dir($dir)) @mkdir($dir, 0775, true);
                @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
                $safety = $dir . '/pre-pull-' . date('Ymd-His') . '.sql';
                $svc->dumpDatabase($safety);
            }
            file_put_contents(pull_state_file(), json_encode(['paths' => []]));
            pull_json(['ok' => true, 'safety' => $safety ? basename($safety) : null]);
        }

        if ($action === 'table') {
            $table = (string) ($_POST['table'] ?? '');
            if (!in_array($table, $_SESSION['pull']['tables'], true)) {
                pull_json(['ok' => false, 'error' => 'Unknown table.'], 400);
            }
            $src = pull_connect($_SESSION['pull']['src']);
            $found = [];
            $rows = $svc->copyTableFrom($src, $table, $rewrite, static function (string $v) use (&$found) {
                if (strpos($v, 'uploads/') === false) return;
                if (preg_match_all('#(?<![A-Za-z0-9])uploads/([A-Za-z0-9_\-./]+\.([A-Za-z0-9]{2,5}))(?![A-Za-z0-9])#', $v, $m, PREG_SET_ORDER)) {
                    foreach ($m as $hit) {
                        if (strpos($hit[1], '..') === false && in_array(strtolower($hit[2]), PULL_EXT, true)) {
                            $found['uploads/' . $hit[1]] = true;
                        }
                    }
                }
            });
            $state = json_decode((string) @file_get_contents(pull_state_file()), true) ?: ['paths' => []];
            foreach ($found as $rel => $_) $state['paths'][$rel] = true;
            file_put_contents(pull_state_file(), json_encode($state));
            pull_json(['ok' => true, 'rows' => $rows]);
        }

        if ($action === 'files') {
            $state = json_decode((string) @file_get_contents(pull_state_file()), true) ?: ['paths' => []];
            $list = array_keys($state['paths']);
            $offset = max(0, (int) ($_POST['offset'] ?? 0));
            $deadline = microtime(true) + 20;
            $done = 0; $got = 0; $skipped = 0; $failed = [];
            for ($i = $offset; $i < count($list) && microtime(true) < $deadline; $i++, $done++) {
                $rel = $list[$i];
                $dest = public_path($rel);
                if (is_file($dest) && filesize($dest) > 0) { $skipped++; continue; }
                if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0775, true);
                if (pull_download($site . '/' . implode('/', array_map('rawurlencode', explode('/', $rel))), $dest)) $got++;
                else $failed[] = $rel;
            }
            pull_json(['ok' => true, 'total' => count($list), 'next' => $offset + $done, 'got' => $got, 'skipped' => $skipped, 'failed' => $failed]);
        }

        if ($action === 'finish') {
            @unlink(pull_state_file());
            unset($_SESSION['pull']);
            pull_json(['ok' => true]);
        }
    } catch (Throwable $e) {
        error_log('[superadmin/migrate.php] ' . $e->getMessage());
        pull_json(['ok' => false, 'error' => $e->getMessage()], 500);
    }
    pull_json(['ok' => false, 'error' => 'Unknown action.'], 400);
}

$dbName = (require __DIR__ . '/../../app/v1/config/database.php')['dbname'];
$page_title = 'Migrate from live';
$sa_subtitle = 'Pull the live site\'s data and files into this one.';
ob_start();
?>
<div class="row justify-content-center"><div class="col-lg-7">
<p class="text-muted small">Run this on the <strong>new</strong> host. It reads the live database directly and copies every table into <strong><?php echo htmlspecialchars($dbName); ?></strong> (the database this site is connected to), then downloads the uploaded images and files from the live site. Nothing on the live site is changed.</p>

<div class="card mb-3" id="step1"><div class="card-header fw-semibold">1. Live site details</div><div class="card-body">
  <label class="form-label">Live site address</label><input id="site" class="form-control" placeholder="https://yoursite.com" autocomplete="off">
  <div class="form-text mb-2">Where the live website is served from. Uploaded files are downloaded from here.</div>
  <div class="row g-2"><div class="col-8"><label class="form-label">Live database host</label><input id="db_host" class="form-control" autocomplete="off"></div><div class="col-4"><label class="form-label">Port</label><input id="db_port" class="form-control" value="3306"></div></div>
  <label class="form-label mt-2">Live database name</label><input id="db_name" class="form-control" autocomplete="off">
  <label class="form-label mt-2">Live database user</label><input id="db_user" class="form-control" autocomplete="off">
  <label class="form-label mt-2">Live database password</label><input id="db_pass" type="password" class="form-control" autocomplete="off">
  <label class="form-label mt-2">Your superadmin password (this site)</label><input id="admin_password" type="password" class="form-control" autocomplete="current-password">
  <button id="check" class="btn btn-primary w-100 mt-3">Check the live database</button>
  <div class="text-danger mt-2" id="err"></div>
</div></div>

<div class="card mb-3 d-none" id="step2"><div class="card-header fw-semibold">2. Found on the live database</div><div class="card-body">
  <table class="table table-sm mb-2" id="tbl"></table>
  <div class="alert alert-warning small py-2 d-none" id="warn">This site already has data. Every table above will be <strong>replaced</strong>; a copy of the current database is saved first.</div>
  <button id="go" class="btn btn-danger w-100">Migrate now</button>
</div></div>

<div class="card d-none" id="step3"><div class="card-body">
  <strong id="phase">Migrating…</strong>
  <div class="progress my-2" style="height:10px"><div class="progress-bar bg-success" id="fill" style="width:0"></div></div>
  <div id="log" class="small font-monospace" style="max-height:240px;overflow:auto;white-space:pre-wrap"></div>
</div></div>
</div></div>
<script>
const $ = id => document.getElementById(id);
const CSRF = <?php echo json_encode(csrf_token()); ?>;
async function call(action, data = {}) {
  const body = new URLSearchParams({ csrf_token: CSRF, action, ...data });
  const r = await fetch(location.pathname, { method: 'POST', body });
  let j; try { j = await r.json(); } catch (e) { throw new Error('The server stopped responding (HTTP ' + r.status + ').'); }
  if (!j.ok) throw new Error(j.error || 'Failed');
  return j;
}
const log = t => { $('log').textContent += t + '\n'; $('log').scrollTop = 1e9; };
let tables = [];
$('check').onclick = async () => {
  $('err').textContent = ''; $('check').disabled = true;
  try {
    const j = await call('check', { site: $('site').value, db_host: $('db_host').value, db_port: $('db_port').value, db_name: $('db_name').value, db_user: $('db_user').value, db_pass: $('db_pass').value, admin_password: $('admin_password').value });
    tables = j.tables;
    $('tbl').innerHTML = tables.map(t => '<tr><td>' + t.name.replace(/</g, '&lt;') + '</td><td class="text-end text-muted">' + t.rows + ' rows</td></tr>').join('');
    $('warn').classList.toggle('d-none', !j.has_data);
    $('step1').classList.add('d-none'); $('step2').classList.remove('d-none');
  } catch (e) { $('err').textContent = e.message; }
  $('check').disabled = false;
};
$('go').onclick = async () => {
  $('go').disabled = true; $('step2').classList.add('d-none'); $('step3').classList.remove('d-none');
  try {
    const b = await call('begin');
    if (b.safety) log('Saved a copy of the current database (' + b.safety + ').');
    for (let i = 0; i < tables.length; i++) {
      $('phase').textContent = 'Copying tables (' + (i + 1) + '/' + tables.length + ')';
      const r = await call('table', { table: tables[i].name });
      log(tables[i].name + ': ' + r.rows + ' rows');
      $('fill').style.width = Math.round((i + 1) / tables.length * 60) + '%';
    }
    let offset = 0, got = 0, skipped = 0, failed = [], total = 0;
    do {
      $('phase').textContent = 'Downloading uploaded files…';
      const f = await call('files', { offset });
      total = f.total; offset = f.next; got += f.got; skipped += f.skipped; failed = failed.concat(f.failed);
      $('fill').style.width = (60 + (total ? Math.round(offset / total * 40) : 40)) + '%';
    } while (offset < total);
    log('Files: ' + got + ' downloaded, ' + skipped + ' already here' + (failed.length ? ', ' + failed.length + ' could not be fetched' : '') + '.');
    failed.slice(0, 30).forEach(x => log('  missing: ' + x));
    await call('finish');
    $('fill').style.width = '100%'; $('phase').textContent = 'Done. Sign in again with your live account.';
    log('Finished.');
  } catch (e) { $('phase').textContent = 'Stopped'; log('ERROR: ' + e.message); }
};
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/../templates/superadmin/layout.php';
