<?php
// public/auth/register.php
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/UserModel.php';

$userModel = new UserModel($pdo);
$error = '';
$success = '';

if (isLoggedIn()) {
    redirect(url('profile/'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $phone = $_POST['phone'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = '[err] USERNAME, EMAIL, and PASS are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '[err] invalid EMAIL format';
    } elseif (strlen($password) < 6) {
        $error = '[err] PASS must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = '[err] PASS and PASS_CONFIRM do not match';
    } else {
        $existingUser = $userModel->getUserByEmail($email);
        if ($existingUser) {
            $error = '[err] EMAIL already registered';
        } else {
            $existingUsername = $userModel->getUserByUsername($username);
            if ($existingUsername) {
                $error = '[err] USERNAME already taken';
            } else {
                if ($userModel->createUser($username, $email, $password, 3)) {
                    $user = $userModel->getUserByEmail($email);
                    if ($user) {
                        $userModel->updateProfile($user['id'], $first_name, $last_name, $phone);
                    }
                    $success = '[ ok ] account created — redirecting to login...';
                    header("refresh:2;url=login.php");
                } else {
                    $error = '[err] provisioning failed — try again';
                }
            }
        }
    }
}

function old(string $key): string {
    return htmlspecialchars($_POST[$key] ?? '', ENT_QUOTES, 'UTF-8');
}

$auth_page_title = 'Create account';
$auth_window_title = 'dazu@hub — register.dazu.hub';
$auth_page_mode = 'register';
require __DIR__ . '/../components/auth/terminal_layout_start.php';
?>

<div id="authBootLog" class="auth-terminal-boot" aria-live="polite"></div>

<?php if ($error): ?>
    <div class="auth-terminal-alert error" role="alert">
        <i class="fas fa-times-circle me-1"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="auth-terminal-alert success" role="alert">
        <i class="fas fa-check-circle me-1"></i> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<form method="POST" class="auth-terminal-form" novalidate>
    <div class="auth-cmd-row">
        <span class="auth-cmd-prompt">dazu@hub:~/register$</span>
        <label class="auth-cmd-label" for="username">export USERNAME=</label>
        <input type="text" id="username" name="username" class="auth-cmd-input"
               placeholder="your_handle" value="<?php echo old('username'); ?>" required autocomplete="username">
    </div>

    <div class="auth-cmd-row">
        <span class="auth-cmd-prompt">dazu@hub:~/register$</span>
        <label class="auth-cmd-label" for="email">export EMAIL=</label>
        <input type="email" id="email" name="email" class="auth-cmd-input"
               placeholder="you@dazuhub.institute" value="<?php echo old('email'); ?>" required autocomplete="email">
    </div>

    <div class="auth-cmd-row">
        <span class="auth-cmd-prompt">dazu@hub:~/register$</span>
        <label class="auth-cmd-label" for="first_name">export FNAME=</label>
        <input type="text" id="first_name" name="first_name" class="auth-cmd-input"
               placeholder="optional" value="<?php echo old('first_name'); ?>" autocomplete="given-name">
    </div>

    <div class="auth-cmd-row">
        <span class="auth-cmd-prompt">dazu@hub:~/register$</span>
        <label class="auth-cmd-label" for="last_name">export LNAME=</label>
        <input type="text" id="last_name" name="last_name" class="auth-cmd-input"
               placeholder="optional" value="<?php echo old('last_name'); ?>" autocomplete="family-name">
    </div>

    <div class="auth-cmd-row">
        <span class="auth-cmd-prompt">dazu@hub:~/register$</span>
        <label class="auth-cmd-label" for="phone">export PHONE=</label>
        <input type="tel" id="phone" name="phone" class="auth-cmd-input"
               placeholder="optional" value="<?php echo old('phone'); ?>" autocomplete="tel">
    </div>

    <div class="auth-cmd-row">
        <span class="auth-cmd-prompt">dazu@hub:~/register$</span>
        <label class="auth-cmd-label" for="password">export PASS=</label>
        <input type="password" id="password" name="password" class="auth-cmd-input"
               placeholder="min 6 chars" required autocomplete="new-password">
    </div>

    <div class="auth-cmd-row">
        <span class="auth-cmd-prompt">dazu@hub:~/register$</span>
        <label class="auth-cmd-label" for="confirm_password">export PASS_CONFIRM=</label>
        <input type="password" id="confirm_password" name="confirm_password" class="auth-cmd-input"
               placeholder="repeat password" required autocomplete="new-password">
    </div>

    <button type="submit" class="auth-cmd-submit">
        <i class="fas fa-user-plus"></i> ./register.sh --execute
    </button>
</form>

<div class="auth-terminal-links">
    <div class="row">
        <span class="guest">guest@hub:~$</span>
        <span>have an account?</span>
        <a href="login.php">./signin.sh</a>
    </div>
</div>

<?php require __DIR__ . '/../components/auth/terminal_layout_end.php'; ?>
