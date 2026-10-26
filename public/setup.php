<?php
// public/setup.php — moves the site onto a host, in three steps:
//   1. Database: the login is typed in, tested, and saved over app/v1/config/database.php (it always replaces the old one).
//   2. Email: the SMTP login (optional: a restored backup brings the old site's email settings with it).
//   3. Restore a backup made with Export Site on the old host (or start a fresh site). The backup is loaded first and
//      its database is then updated to this version (updating first would be undone, since the restore replaces every table).
// A brand-new host (no working database, or no accounts yet) opens straight away. Once the site is set up, the page is
// locked (app/v1/storage/setup.lock) and opens again only with the setup PIN; finishing locks it again.
define('DAZU_SETUP', true);
require_once __DIR__ . '/../app/v1/init.php';
require_once __DIR__ . '/../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../app/v1/models/Connection.php';
require_once __DIR__ . '/../app/v1/services/SiteBackupService.php';
require_once __DIR__ . '/../app/v1/services/MigrationService.php';
require_once __DIR__ . '/../app/v1/services/Mailer.php';
require_once __DIR__ . '/../app/v1/helpers/mail_settings.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const SETUP_PIN = '111000';   // opens the setup page on a site that is already set up

// Accounts the migrations seed with passwords that are public in the code (001 and 049).
const SETUP_SEED_USERNAMES = ['superadmin.dazuhub', 'Administrator'];

/** A fresh install: no users table yet, no users, or only the default accounts the migrations seed. */
function setup_is_fresh(PDO $pdo): bool
{
    try {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username NOT IN ('" . implode("','", SETUP_SEED_USERNAMES) . "')")->fetchColumn();
        return $n === 0;
    } catch (Throwable $e) {
        return true;
    }
}

/** Reads one account block (prefix sa_ or ad_) from the fresh-install form. Returns [fields, error]. */
function setup_account_input(string $p, string $label): array
{
    $a = [
        'first'    => trim((string) ($_POST[$p . 'first'] ?? '')),
        'last'     => trim((string) ($_POST[$p . 'last'] ?? '')),
        'username' => strtolower(trim((string) ($_POST[$p . 'username'] ?? ''))),
        'email'    => strtolower(trim((string) ($_POST[$p . 'email'] ?? ''))),
        'password' => (string) ($_POST[$p . 'password'] ?? ''),
    ];
    if ($a['first'] === '' || $a['last'] === '') {
        return [$a, "Enter the {$label}'s first and last name."];
    }
    if (!preg_match('/^[a-z0-9._-]{3,50}$/', $a['username'])) {
        return [$a, "The {$label} username must be 3–50 letters, numbers, dots, dashes or underscores."];
    }
    if (!filter_var($a['email'], FILTER_VALIDATE_EMAIL) || strlen($a['email']) > 100) {
        return [$a, "Enter a valid email for the {$label}."];
    }
    if (strlen($a['password']) < 8) {
        return [$a, "The {$label} password must be at least 8 characters."];
    }
    if (!hash_equals($a['password'], (string) ($_POST[$p . 'password2'] ?? ''))) {
        return [$a, "The two {$label} passwords do not match."];
    }
    return [$a, null];
}

/** Fresh install: applies every pending migration, then creates the superadmin and admin accounts. Returns an error or null. */
function setup_fresh_install(PDO $pdo, array $sa, array $ad): ?string
{
    $run = (new MigrationService($pdo, APP_V1_ROOT . '/databases/required_migrations'))->applyPending();
    if (!empty($run['failed'])) {
        $f = $run['failed'][0];
        return 'Creating the tables stopped at ' . $f['filename'] . ': ' . $f['error'];
    }

    $roles = $pdo->query("SELECT role_name, id FROM roles WHERE role_name IN ('superadmin','admin')")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (empty($roles['superadmin']) || empty($roles['admin'])) {
        return 'The roles table is missing the superadmin/admin roles.';
    }

    $pdo->beginTransaction();
    try {
        // The seeded defaults have passwords anyone can read in the code, so they are removed, never left behind.
        $pdo->exec("DELETE FROM users WHERE username IN ('" . implode("','", SETUP_SEED_USERNAMES) . "')");
        $ins = $pdo->prepare('INSERT INTO users (username, email, password_hash, role_id, is_active, email_verified, account_status)
                              VALUES (?, ?, ?, ?, 1, 1, \'active\')');
        $prof = $pdo->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (?, ?, ?)');
        foreach ([[$sa, $roles['superadmin']], [$ad, $roles['admin']]] as [$a, $roleId]) {
            $ins->execute([$a['username'], $a['email'], password_hash($a['password'], PASSWORD_DEFAULT), (int) $roleId]);
            $prof->execute([(int) $pdo->lastInsertId(), $a['first'], $a['last']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return 'Could not create the accounts: ' . $e->getMessage();
    }
    return null;
}


$lockFile = storage_path('setup.lock');
$dbFile = APP_V1_ROOT . '/config/database.php';
$dbCfg = require $dbFile;
$message = null;
$warning = null;
$dbSnippet = null;
$done = null;
$created = null;
$form = [];
$smtpForm = $_SESSION['setup_smtp'] ?? [];
$wiz = &$_SESSION['setup_wizard'];   // ['open' => bool, 'step' => 'db'|'smtp'|'import']

/** The site is set up: setup has finished before, or the database works and already has accounts. */
$isSetUp = is_file($lockFile) || ($pdo !== null && !setup_is_fresh(\Models\Connection::getInstance()->getConnection()));
if (!$isSetUp && empty($wiz['open'])) {
    $wiz = ['open' => true, 'step' => 'db'];
}

function setup_lock(string $lockFile): void
{
    if (!is_dir(dirname($lockFile))) @mkdir(dirname($lockFile), 0775, true);
    @file_put_contents($lockFile, 'Setup finished ' . date('c') . "\n");
    unset($_SESSION['setup_wizard'], $_SESSION['setup_smtp']);
}

/** Save the email login from step 2 (kept in the session until the restore is done). Returns an error or null. */
function setup_apply_smtp(PDO $pdo): ?string
{
    $smtp = $_SESSION['setup_smtp'] ?? [];
    if (!$smtp) return null;
    $r = mail_settings_save($pdo, $smtp + ['enabled' => 1]);
    return $r['ok'] ? null : 'The email settings could not be saved (' . $r['error'] . '). Set them in Settings → Email.';
}

/** Try a database login. Returns [PDO|null, error|null]. Creates the database when the user is allowed to. */
function setup_try_db(array $in): array
{
    try {
        $srv = new PDO("mysql:host={$in['host']};charset=utf8mb4", $in['username'], $in['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]);
        try {
            $srv->exec('CREATE DATABASE IF NOT EXISTS `' . $in['dbname'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (PDOException $e) {
            // Shared hosts often forbid CREATE DATABASE; the database made in the control panel is used as-is.
        }
        return [new PDO("mysql:host={$in['host']};dbname={$in['dbname']};charset=utf8mb4", $in['username'], $in['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]), null];
    } catch (PDOException $e) {
        return [null, 'Could not connect with those details: ' . $e->getMessage()];
    }
}

$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post && !csrf_verify($_POST['csrf_token'] ?? null)) {
    $message = 'Security check failed. Reload the page and try again.';
    $post = false;
}

// Locked: only the setup PIN opens it (wrong guesses are slowed down).
if (empty($wiz['open'])) {
    $state = 'locked';
    if ($post && isset($_POST['setup_pin'])) {
        if ($wait = (int) max(0, ($_SESSION['setup_block_until'] ?? 0) - time())) {
            $message = 'Too many wrong PINs. Try again in ' . ceil($wait / 60) . ' minute(s).';
        } elseif (!hash_equals(SETUP_PIN, (string) $_POST['setup_pin'])) {
            $_SESSION['setup_fail'] = (int) ($_SESSION['setup_fail'] ?? 0) + 1;
            if ($_SESSION['setup_fail'] >= 5) { $_SESSION['setup_block_until'] = time() + 900; $_SESSION['setup_fail'] = 0; }
            $message = 'That PIN is not correct.';
        } else {
            $_SESSION['setup_fail'] = 0;
            $wiz = ['open' => true, 'step' => 'db'];
            header('Location: ' . url('setup.php'));
            exit();
        }
    }
} else {
    $state = $wiz['step'] ?? 'db';
    if (($_GET['step'] ?? '') === 'db' || ($state !== 'db' && $pdo === null)) {
        $state = $wiz['step'] = 'db';   // "Back", or the saved login stopped working
    } elseif (($_GET['step'] ?? '') === 'smtp' && $state === 'import') {
        $state = $wiz['step'] = 'smtp';
    }
}

// Step 1: database login — tested, then written over the old one.
if ($state === 'db' && $post) {
    $in = [
        'host'     => trim((string) ($_POST['db_host'] ?? '')) ?: 'localhost',
        'dbname'   => trim((string) ($_POST['db_name'] ?? '')),
        'username' => trim((string) ($_POST['db_user'] ?? '')),
        'password' => (string) ($_POST['db_pass'] ?? ''),
        'charset'  => 'utf8mb4',
    ];
    // An empty password box keeps the saved password when the host and user are unchanged.
    if ($in['password'] === '' && !empty($_POST['keep_pass']) && $in['host'] === ($dbCfg['host'] ?? '') && $in['username'] === ($dbCfg['username'] ?? '')) {
        $in['password'] = (string) ($dbCfg['password'] ?? '');
    }
    if (!preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $in['dbname']) || $in['username'] === '') {
        $message = 'Enter the database name (letters, numbers, _ and -) and the database user.';
    } else {
        [$test, $err] = setup_try_db($in);
        if ($err) {
            $message = $err;
        } elseif (($_POST['db_action'] ?? '') === 'test') {
            $tables = (int) $test->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
            $warning = 'Connection works. The database "' . $in['dbname'] . '" has ' . $tables . ' table' . ($tables === 1 ? '' : 's') . '. Press Save and continue to use it.';
        } else {
            $php = "<?php\n// app/v1/config/database.php — the database login for this site. Edit these values directly.\n\nreturn " . var_export($in, true) . ";\n";
            if (@file_put_contents($dbFile, $php) === false) {
                $dbSnippet = $php;
            } else {
                if (function_exists('opcache_invalidate')) { @opcache_invalidate($dbFile, true); }
                $wiz['step'] = 'smtp';
                header('Location: ' . url('setup.php'));
                exit();
            }
        }
    }
    $dbCfg = $in + $dbCfg;
}

// Step 2: email. Checked with an optional test email; saved after the restore (the backup would overwrite it otherwise).
if ($state === 'smtp' && $post) {
    $smtpForm = ['host' => trim((string) ($_POST['host'] ?? '')), 'port' => (int) ($_POST['port'] ?? 587), 'encryption' => (string) ($_POST['encryption'] ?? 'tls'),
                 'username' => trim((string) ($_POST['username'] ?? '')), 'password' => (string) ($_POST['password'] ?? ''),
                 'from_email' => trim((string) ($_POST['from_email'] ?? '')), 'from_name' => trim((string) ($_POST['from_name'] ?? ''))];
    $test = trim((string) ($_POST['test_to'] ?? ''));
    if (($_POST['smtp_action'] ?? '') === 'skip') {
        $_SESSION['setup_smtp'] = [];
        $wiz['step'] = 'import';
        header('Location: ' . url('setup.php'));
        exit();
    } elseif ($smtpForm['host'] === '' || !preg_match('/^[a-z0-9.\-]+$/i', $smtpForm['host']) || $smtpForm['port'] < 1 || $smtpForm['port'] > 65535
              || !in_array($smtpForm['encryption'], ['tls', 'ssl', 'none'], true) || $smtpForm['username'] === '' || $smtpForm['password'] === '') {
        $message = 'Enter the SMTP host, port, username and password, or skip this step.';
    } elseif ($smtpForm['from_email'] !== '' && !filter_var($smtpForm['from_email'], FILTER_VALIDATE_EMAIL)) {
        $message = 'The "from" email address is not valid.';
    } elseif ($test !== '' && !filter_var($test, FILTER_VALIDATE_EMAIL)) {
        $message = 'The test address is not a valid email.';
    } else {
        $res = ['ok' => true];
        if ($test !== '') {
            $cfg = ['transport' => 'smtp', 'enabled' => true, 'password_reset_enabled' => true, 'from_email' => $smtpForm['from_email'] ?: $smtpForm['username'],
                    'from_name' => $smtpForm['from_name'] ?: 'Dazu Training Institute', 'logo_path' => public_path('assets/images/logo/logo.png')] + $smtpForm;
            $res = (new Mailer($cfg))->send($test, 'Test', 'Test email from your new site',
                '<div style="font-family:Arial,sans-serif"><h3 style="color:#15a24a">Email is working</h3><p>The SMTP details entered in site setup are correct.</p></div>');
        }
        if (empty($res['ok'])) {
            $message = 'The test email failed: ' . ($res['message'] ?? 'unknown error') . '. Check the details, or skip and set email up later.';
        } else {
            $_SESSION['setup_smtp'] = $smtpForm;
            $wiz['step'] = 'import';
            header('Location: ' . url('setup.php'));
            exit();
        }
    }
}

// Step 3: restore the backup (or, on an empty site, start fresh). Then lock.
$pdo = $pdo !== null ? \Models\Connection::getInstance()->getConnection() : null;
$fresh = $pdo !== null && setup_is_fresh($pdo);
if ($state === 'import' && $post) {
    @set_time_limit(0);
    if (($_POST['mode'] ?? '') === 'fresh') {
        [$sa, $err1] = setup_account_input('sa_', 'superadmin');
        [$ad, $err2] = setup_account_input('ad_', 'admin');
        $form = ['sa' => $sa, 'ad' => $ad];
        if (!$fresh) {
            $message = 'This database already has accounts. Restore a backup, or go back and choose an empty database.';
        } elseif ($message = $err1 ?: $err2) {
            // shown above the form
        } elseif ($sa['email'] === $ad['email'] || $sa['username'] === $ad['username']) {
            $message = 'The superadmin and the admin need different emails and usernames.';
        } elseif ($message = setup_fresh_install($pdo, $sa, $ad)) {
            // shown above the form
        } else {
            $created = ['sa' => $sa, 'ad' => $ad];
            $state = 'created';
            $warning = setup_apply_smtp($pdo);
            setup_lock($lockFile);
        }
    } else {
        $zipPath = null;
        $pick = basename((string) ($_POST['server_file'] ?? ''));
        if ($pick !== '' && isset(SiteBackupService::serverImports()[$pick])) {
            $zipPath = SiteBackupService::importsDir() . '/' . $pick;
        } elseif (!empty($_FILES['backup']['tmp_name']) && ($_FILES['backup']['error'] ?? 1) === UPLOAD_ERR_OK && is_uploaded_file($_FILES['backup']['tmp_name'])) {
            $zipPath = $_FILES['backup']['tmp_name'];
        } elseif (in_array($_FILES['backup']['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $message = 'That file is larger than this server allows for uploads. Ask your developer to place it on the server, then pick it from the list.';
        } else {
            $message = 'Choose the backup zip to restore.';
        }
        if ($zipPath) {
            // import() loads the backup, then updates its database to this version.
            $r = (new SiteBackupService($pdo))->import($zipPath);
            if ($r['ok']) {
                $done = $r;
                $state = 'done';
                // Step 2's email login wins over the old site's.
                $warning = setup_apply_smtp($pdo);
                if (!empty($r['update_error'])) {
                    $warning = trim(($warning ?? '') . ' Updating the database to this version stopped: ' . $r['update_error'] . ' Open System Updates after signing in.');
                }
                setup_lock($lockFile);
            } else {
                $message = $r['error'];
            }
        }
    }
}

$serverFiles = $state === 'import' ? SiteBackupService::serverImports() : [];
$limit = ini_get('upload_max_filesize');
$steps = ['db' => 1, 'smtp' => 2, 'import' => 3];
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Site setup</title>
<style>
:root{--bg:#f4f6f8;--card:#fff;--ink:#0f172a;--mut:#64748b;--line:#e2e8f0;--acc:#15a24a;--bad:#dc2626}
@media (prefers-color-scheme:dark){:root{--bg:#0b1220;--card:#131c2e;--ink:#e5e7eb;--mut:#94a3b8;--line:#243047}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 system-ui,sans-serif}
main{max-width:520px;margin:0 auto;padding:32px 16px}h1{font-size:1.4rem;margin:0 0 4px}.s{color:var(--mut);margin:0 0 20px}
.card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:20px}
label{display:block;font-weight:600;font-size:.85rem;margin:14px 0 4px}input,select{width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;background:transparent;color:inherit;font:inherit}
button,.btn{display:block;margin-top:18px;width:100%;padding:12px;border:0;border-radius:8px;background:var(--acc);color:#fff;font-weight:700;font-size:1rem;cursor:pointer;text-align:center;text-decoration:none}
.err{color:var(--bad);margin:0 0 12px}.mut{color:var(--mut);font-size:.85rem}code{background:var(--line);padding:1px 5px;border-radius:4px}
</style></head><body><main>
<h1>Site setup</h1>
<?php if (isset($steps[$state])): ?>
<p class="s">Step <?php echo $steps[$state]; ?> of 3: <?php echo ['db' => 'the database login.', 'smtp' => 'the email account the site sends from.',
    'import' => 'restore the backup from your old site (made with <strong>Export Site</strong>).'][$state]; ?></p>
<?php endif; ?>
<div class="card">
<?php if ($state === 'locked'): ?>
  <?php if ($message): ?><p class="err"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
  <p><strong>This site is already set up, so setup is locked.</strong></p>
  <p class="mut">Enter the setup PIN to run it again: you can change the database login and email, and restore a backup over this site.</p>
  <form method="POST" autocomplete="off"><?php echo csrf_field(); ?>
    <label>Setup PIN</label><input type="password" name="setup_pin" inputmode="numeric" required autocomplete="off" autofocus>
    <button>Unlock setup</button>
  </form>
  <p class="mut" style="margin-top:14px"><a href="<?php echo htmlspecialchars(url('auth/login.php')); ?>">Go to sign in instead</a></p>
<?php elseif ($state === 'db'): ?>
  <?php if ($message): ?><p class="err"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
  <?php if ($warning): ?><p style="color:var(--acc);margin:0 0 12px"><?php echo htmlspecialchars($warning); ?></p><?php endif; ?>
  <?php if ($dbSnippet): ?>
  <p><strong>Connected, but this server would not let the page save the settings.</strong></p>
  <p class="mut">Replace the contents of <code>app/v1/config/database.php</code> with the text below, then reload this page.</p>
  <textarea readonly rows="12" style="width:100%;font:12px monospace;background:transparent;color:inherit;border:1px solid var(--line);border-radius:8px;padding:8px"><?php echo htmlspecialchars($dbSnippet); ?></textarea>
  <?php else: ?>
  <p class="mut">The database and user from your hosting control panel. These replace the login saved now. If the database does not exist yet it is created when the user is allowed to.</p>
  <form method="POST" autocomplete="off"><?php echo csrf_field(); ?>
    <label>Database host</label><input name="db_host" value="<?php echo htmlspecialchars((string) $dbCfg['host']); ?>">
    <label>Database name</label><input name="db_name" required value="<?php echo htmlspecialchars((string) $dbCfg['dbname']); ?>">
    <label>Database user</label><input name="db_user" required value="<?php echo htmlspecialchars((string) $dbCfg['username']); ?>">
    <label>Database password</label><input type="password" name="db_pass" autocomplete="new-password">
    <?php if (($dbCfg['password'] ?? '') !== ''): ?>
      <label style="font-weight:400;display:flex;gap:8px;align-items:center"><input type="checkbox" name="keep_pass" value="1" checked style="width:auto"> Keep the saved password if the box above is empty</label>
    <?php endif; ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 10px">
      <button name="db_action" value="test" style="background:transparent;color:var(--ink);border:1px solid var(--line)">Test connection</button>
      <button name="db_action" value="save">Save and continue</button>
    </div>
  </form>
  <?php endif; ?>
<?php elseif ($state === 'smtp'): ?>
  <?php if ($message): ?><p class="err"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
  <p class="mut">The account the site sends emails from (admission letters, ID cards, password resets). For Gmail use smtp.gmail.com, port 587, TLS and an app password.</p>
  <form method="POST" autocomplete="off"><?php echo csrf_field(); ?>
    <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:0 10px">
      <div><label>SMTP host</label><input name="host" value="<?php echo htmlspecialchars($smtpForm['host'] ?? 'smtp.gmail.com'); ?>"></div>
      <div><label>Port</label><input name="port" inputmode="numeric" value="<?php echo (int) ($smtpForm['port'] ?? 587); ?>"></div>
      <div><label>Security</label><select name="encryption"><?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $k => $l): ?><option value="<?php echo $k; ?>" <?php echo ($smtpForm['encryption'] ?? 'tls') === $k ? 'selected' : ''; ?>><?php echo $l; ?></option><?php endforeach; ?></select></div>
    </div>
    <label>Username</label><input name="username" placeholder="you@example.com" value="<?php echo htmlspecialchars($smtpForm['username'] ?? ''); ?>">
    <label>Password</label><input type="password" name="password" autocomplete="new-password">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 10px">
      <div><label>From email <span class="mut">(optional)</span></label><input name="from_email" value="<?php echo htmlspecialchars($smtpForm['from_email'] ?? ''); ?>"></div>
      <div><label>From name <span class="mut">(optional)</span></label><input name="from_name" value="<?php echo htmlspecialchars($smtpForm['from_name'] ?? 'Dazu Training Institute'); ?>"></div>
    </div>
    <label>Send a test email to <span class="mut">(optional, checks the details now)</span></label><input type="email" name="test_to">
    <button name="smtp_action" value="save">Save and continue</button>
    <button name="smtp_action" value="skip" formnovalidate style="background:transparent;color:var(--ink);border:1px solid var(--line)">Skip: use the email settings in my backup</button>
  </form>
  <p class="mut" style="margin-top:14px"><a href="?step=db">‹ Back to the database</a></p>
<?php elseif ($state === 'created'): ?>
  <?php if ($warning): ?><p class="err"><?php echo htmlspecialchars($warning); ?></p><?php endif; ?>
  <p><strong>The site is ready.</strong> All tables were created along with these accounts:</p>
  <p class="mut"><strong>Superadmin</strong> — <?php echo htmlspecialchars($created['sa']['email']); ?>. Signs in at the superadmin portal with this email.<br>
  <strong>Admin</strong> — <?php echo htmlspecialchars($created['ad']['email']); ?> (or username <?php echo htmlspecialchars($created['ad']['username']); ?>). Signs in on the normal sign-in page.</p>
  <p class="mut">Setup is now locked. Change the superadmin PIN (default 111333) after signing in.</p>
  <a class="btn" href="<?php echo htmlspecialchars(url('superadmin/login.php')); ?>">Superadmin sign in</a>
  <a class="btn" style="background:transparent;color:var(--ink);border:1px solid var(--line)" href="<?php echo htmlspecialchars(url('auth/login.php')); ?>">Admin sign in</a>
<?php elseif ($state === 'done'): ?>
  <?php if ($warning): ?><p class="err"><?php echo htmlspecialchars($warning); ?></p><?php endif; ?>
  <p><strong>Backup restored.</strong> <?php echo (int) $done['tables']; ?> tables and <?php echo (int) $done['files']; ?> files.</p>
  <p class="mut">Everything from the old site is back: accounts, students, courses, payments, files and settings. Sign in with the same details as on the old site.
  <?php if (!empty($done['updated'])): ?>The database was also updated to <?php echo $done['version'] ? 'V' . htmlspecialchars($done['version']) : 'this version'; ?>.<?php endif; ?>
  Setup is now locked. Delete the backup zip from the server.</p>
  <a class="btn" href="<?php echo htmlspecialchars(url('auth/login.php')); ?>">Go to sign in</a>
<?php elseif ($state === 'import'): ?>
  <?php if ($message): ?><p class="err"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
  <h2 style="font-size:1.1rem;margin:0">Restore your site</h2>
  <p class="mut">Upload the zip from <strong>Export Site</strong> on the old host. Everything comes back as it was: accounts, students, courses, payments, files and settings.
  The backup is loaded first, then its database is updated to this version.<?php if (!$fresh): ?> <strong>This replaces everything on this site now.</strong><?php endif; ?></p>
  <form method="POST" enctype="multipart/form-data" autocomplete="off" <?php echo $fresh ? '' : 'onsubmit="return confirm(\'Replace everything on this site with the backup?\')"'; ?>><?php echo csrf_field(); ?>
    <label>Backup zip</label><input type="file" name="backup" accept=".zip,application/zip">
    <div class="mut">This server accepts uploads up to <?php echo htmlspecialchars($limit); ?>.</div>
    <?php if ($serverFiles): ?>
    <label>…or a zip already on the server</label>
    <select name="server_file"><option value="">— none —</option>
      <?php foreach ($serverFiles as $f => $size): ?><option value="<?php echo htmlspecialchars($f); ?>"><?php echo htmlspecialchars($f); ?> (<?php echo number_format($size / 1048576, 1); ?> MB)</option><?php endforeach; ?>
    </select>
    <?php else: ?>
    <div class="mut" style="margin-top:8px">Zip too big to upload? Ask your developer to place it on the server; it will then appear in a list here.</div>
    <?php endif; ?>
    <button>Restore backup</button>
  </form>
  <p class="mut" style="margin-top:14px"><a href="?step=smtp">‹ Back to email</a></p>
  <?php if ($fresh): ?>
</div>
<div class="card" style="margin-top:16px">
  <details>
  <summary style="font-weight:700;cursor:pointer">No backup? Start a new, empty site instead</summary>
  <p class="mut">Creates all tables and the two accounts that run the site.</p>
  <form method="POST" autocomplete="off"><?php echo csrf_field(); ?><input type="hidden" name="mode" value="fresh">
    <?php foreach (['sa_' => ['sa', 'Superadmin', 'Owner of the whole system: payroll, backups, admins.'], 'ad_' => ['ad', 'Admin', 'Runs day-to-day: students, courses, registrations.']] as $p => [$k, $title, $hint]): $v = $form[$k] ?? []; ?>
    <h3 style="font-size:1rem;margin:22px 0 0;padding-top:14px;border-top:1px solid var(--line)"><?php echo $title; ?> account</h3>
    <div class="mut"><?php echo $hint; ?></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 10px">
      <div><label>First name</label><input name="<?php echo $p; ?>first" required value="<?php echo htmlspecialchars($v['first'] ?? ''); ?>"></div>
      <div><label>Last name</label><input name="<?php echo $p; ?>last" required value="<?php echo htmlspecialchars($v['last'] ?? ''); ?>"></div>
    </div>
    <label>Username</label><input name="<?php echo $p; ?>username" required pattern="[A-Za-z0-9._\-]{3,50}" value="<?php echo htmlspecialchars($v['username'] ?? ''); ?>">
    <label>Email</label><input type="email" name="<?php echo $p; ?>email" required value="<?php echo htmlspecialchars($v['email'] ?? ''); ?>">
    <label>Password</label><input type="password" name="<?php echo $p; ?>password" required minlength="8" autocomplete="new-password">
    <label>Confirm password</label><input type="password" name="<?php echo $p; ?>password2" required minlength="8" autocomplete="new-password">
    <?php endforeach; ?>
    <button>Create site and accounts</button>
  </form>
  </details>
  <?php endif; ?>
<?php endif; ?>
</div>
</main></body></html>
