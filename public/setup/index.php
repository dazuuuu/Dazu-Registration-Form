<?php
// public/setup/index.php — first-run installer at /setup
// Does not use bootstrap.php so a missing database does not fatal the page.

require_once dirname(__DIR__, 2) . '/app/v1/config/paths.php';
require_once dirname(__DIR__, 2) . '/app/v1/helpers/uploads.php';
require_once dirname(__DIR__, 2) . '/app/v1/services/MigrationService.php';
require_once dirname(__DIR__, 2) . '/app/v1/services/SetupService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$setup = new SetupService();
$errors = [];
$notices = [];
$migrationResults = [];
$adminResult = null;
$pending = [];
$appliedCount = 0;
$dbReady = false;
$pdo = null;
$migrationService = null;
$connectionError = null;
$justCompleted = !empty($_SESSION['setup_just_completed']);
unset($_SESSION['setup_just_completed']);

$posted = [
    'username' => trim((string) ($_POST['username'] ?? '')),
    'email' => trim((string) ($_POST['email'] ?? '')),
    'first_name' => trim((string) ($_POST['first_name'] ?? '')),
    'last_name' => trim((string) ($_POST['last_name'] ?? '')),
];

try {
    $serverPdo = $setup->connectServer();
    $setup->ensureDatabase($serverPdo);
    $pdo = $serverPdo;
    $dbReady = true;
} catch (Throwable $e) {
    $connectionError = $e->getMessage();
}

if ($dbReady && $pdo instanceof PDO) {
    $migrationService = new MigrationService($pdo);
    try {
        $pending = $migrationService->pending();
        $appliedCount = count($migrationService->appliedFilenames());
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$locked = $setup->isComplete() && !$justCompleted;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Security check failed. Refresh the page and try again.';
    } elseif (!$dbReady || !$pdo instanceof PDO || !$migrationService instanceof MigrationService) {
        $errors[] = 'Cannot continue until the database connection succeeds. Check DB_* values in .env.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        if ($password !== $confirm) {
            $errors[] = 'Password confirmation does not match.';
        }

        if (!$errors) {
            $result = $migrationService->applyPending();
            $migrationResults = $result['results'];
            if (!empty($result['failed'])) {
                $errors[] = 'Migration stopped at ' . $result['failed'][0]['filename'] . ': ' . $result['failed'][0]['error'];
            } else {
                $notices[] = $result['ran'] > 0
                    ? 'Applied ' . $result['ran'] . ' migration' . ($result['ran'] === 1 ? '' : 's') . '.'
                    : 'Database schema is already up to date.';

                $adminResult = $setup->createAdmin(
                    $pdo,
                    $posted['username'],
                    $posted['email'],
                    $password,
                    $posted['first_name'],
                    $posted['last_name']
                );

                if (empty($adminResult['ok'])) {
                    $errors[] = $adminResult['message'] ?? 'Could not create the administrator.';
                } else {
                    $setup->markComplete();
                    $_SESSION['setup_just_completed'] = true;
                    $_SESSION['setup_flash'] = [
                        'notices' => array_merge($notices, [$adminResult['message']]),
                        'migrations' => $migrationResults,
                        'email' => $posted['email'],
                    ];
                    header('Location: ' . url('setup/'));
                    exit;
                }
            }
        }
    }
}

if ($justCompleted && !empty($_SESSION['setup_flash'])) {
    $notices = $_SESSION['setup_flash']['notices'] ?? [];
    $migrationResults = $_SESSION['setup_flash']['migrations'] ?? [];
    $posted['email'] = $_SESSION['setup_flash']['email'] ?? $posted['email'];
    unset($_SESSION['setup_flash']);
}

$logoPath = url('assets/images/logo/logo.png');
$logoExists = is_file(public_path('assets/images/logo/logo.png'));
$pageTitle = 'System setup';
$done = $setup->isComplete() || $justCompleted;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> — Dazu Hub Institute</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(url('assets/css/theme.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(url('assets/css/auth.css')); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .wp-auth-wrap{max-width:520px}
        .setup-status{display:flex;flex-direction:column;gap:8px;margin-bottom:18px}
        .setup-status-item{display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:.88rem;padding:8px 10px;border:1px solid #e4e7ec;border-radius:6px;background:#fafafa}
        .setup-code{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:.8rem}
        .setup-muted{color:#667085;font-size:.82rem;margin-bottom:16px}
        .setup-results{max-height:180px;overflow:auto;font-size:.8rem}
    </style>
</head>
<body class="wp-auth-body">
<div class="wp-auth-wrap">
    <a class="wp-auth-logo" href="<?php echo htmlspecialchars(url('/')); ?>" aria-label="Dazu Hub Institute — Home">
        <?php if ($logoExists): ?>
            <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Dazu Hub Institute logo">
        <?php else: ?>
            <span class="wp-auth-logo-mark"><i class="fas fa-screwdriver-wrench"></i></span>
        <?php endif; ?>
        <span class="wp-auth-logo-name">Dazu Hub</span>
    </a>

    <div class="wp-auth-card">
        <h1 class="h5 mb-2"><?php echo $done ? 'Setup complete' : 'First-run setup'; ?></h1>
        <p class="setup-muted mb-3">
            <?php if ($done): ?>
                The database is ready and an administrator account is in place. Sign in on the admin portal to use the system.
            <?php else: ?>
                This page creates the database if needed, runs required migrations, and creates credentials for the admin portal.
            <?php endif; ?>
        </p>

        <div class="setup-status">
            <div class="setup-status-item">
                <span>MySQL connection</span>
                <?php if ($dbReady): ?>
                    <span class="badge text-bg-success">Connected</span>
                <?php else: ?>
                    <span class="badge text-bg-danger">Failed</span>
                <?php endif; ?>
            </div>
            <div class="setup-status-item">
                <span>Database</span>
                <span class="setup-code"><?php echo htmlspecialchars($setup->databaseName() ?: '(not set)'); ?></span>
            </div>
            <div class="setup-status-item">
                <span>Pending migrations</span>
                <?php if (!$dbReady): ?>
                    <span class="badge text-bg-secondary">—</span>
                <?php elseif ($pending): ?>
                    <span class="badge text-bg-warning"><?php echo number_format(count($pending)); ?></span>
                <?php else: ?>
                    <span class="badge text-bg-success">0</span>
                <?php endif; ?>
            </div>
            <div class="setup-status-item">
                <span>Applied migrations</span>
                <span><?php echo $dbReady ? number_format($appliedCount) : '—'; ?></span>
            </div>
        </div>

        <?php if ($connectionError): ?>
            <div class="alert alert-danger">
                Could not connect to MySQL: <?php echo htmlspecialchars($connectionError); ?>
                <div class="small mt-1">Copy <span class="setup-code">.env.example</span> to <span class="setup-code">.env</span> and set <span class="setup-code">DB_HOST</span>, <span class="setup-code">DB_NAME</span>, <span class="setup-code">DB_USER</span>, and <span class="setup-code">DB_PASS</span>.</div>
            </div>
        <?php endif; ?>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>

        <?php foreach ($notices as $notice): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div>
        <?php endforeach; ?>

        <?php if ($migrationResults): ?>
            <div class="setup-results mb-3">
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php foreach ($migrationResults as $result): ?>
                        <tr>
                            <td class="setup-code"><?php echo htmlspecialchars($result['filename']); ?></td>
                            <td>
                                <span class="badge text-bg-<?php echo ($result['status'] ?? '') === 'failed' ? 'danger' : 'success'; ?>">
                                    <?php echo htmlspecialchars($result['status'] ?? ''); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($done): ?>
            <a class="wp-auth-submit text-decoration-none" href="<?php echo htmlspecialchars(url('admin/portal.php')); ?>">
                <i class="fas fa-user-shield"></i> Open admin portal
            </a>
            <?php if (!empty($posted['email'])): ?>
                <p class="setup-muted mt-3 mb-0">Sign in with <strong><?php echo htmlspecialchars($posted['email']); ?></strong> and the password you just set.</p>
            <?php endif; ?>
        <?php elseif ($locked): ?>
            <p class="setup-muted">Setup is locked because it already completed. Use the admin portal to sign in. To run setup again, delete <span class="setup-code">storage/setup.lock</span>.</p>
            <a class="wp-auth-submit text-decoration-none" href="<?php echo htmlspecialchars(url('admin/portal.php')); ?>">
                <i class="fas fa-user-shield"></i> Open admin portal
            </a>
        <?php else: ?>
            <form method="post" novalidate>
                <?php echo csrf_field(); ?>
                <div class="row g-2">
                    <div class="col-sm-6">
                        <div class="wp-auth-field">
                            <label for="first_name">First name</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($posted['first_name']); ?>">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="wp-auth-field">
                            <label for="last_name">Last name</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($posted['last_name']); ?>">
                        </div>
                    </div>
                </div>
                <div class="wp-auth-field">
                    <label for="username">Admin username</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($posted['username']); ?>" required autocomplete="username">
                </div>
                <div class="wp-auth-field">
                    <label for="email">Admin email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($posted['email']); ?>" required autocomplete="email">
                </div>
                <div class="wp-auth-field">
                    <label for="password">Admin password</label>
                    <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                </div>
                <div class="wp-auth-field">
                    <label for="confirm_password">Confirm password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                </div>
                <button type="submit" class="wp-auth-submit" <?php echo $dbReady ? '' : 'disabled'; ?>>
                    <i class="fas fa-database"></i> Run migrations and create admin
                </button>
            </form>
        <?php endif; ?>
    </div>

    <p class="wp-auth-back">
        <a href="<?php echo htmlspecialchars(url('/')); ?>"><i class="fas fa-arrow-left me-1"></i> Back to site</a>
    </p>
</div>
</body>
</html>
