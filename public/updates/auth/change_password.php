<?php
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/models/UserModel.php';

if (!isLoggedIn()) {
    redirect(url('auth/login.php'));
}

$userModel = new UserModel($pdo);
$user = $userModel->getUserById((int) $_SESSION['user_id']);
$mustChange = !empty($user['must_change_password']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare('UPDATE users SET password_hash = :h, must_change_password = 0 WHERE id = :id');
            $stmt->execute([':h' => $hash, ':id' => (int) $user['id']]);
        } catch (PDOException $e) {
            $stmt = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
            $stmt->execute([':h' => $hash, ':id' => (int) $user['id']]);
        }

        $role = $_SESSION['role'] ?? '';
        if ($role === 'student') {
            redirect(url('students/dashboard/index.php'));
        }
        if ($role === 'tutor') {
            redirect(url('tutor/dashboard/index.php'));
        }
        if ((int) ($_SESSION['role_id'] ?? 99) <= 2) {
            redirect(url('admin/dashboard.php'));
        }
        redirect(url('profile/client/index.php'));
    }
}

$page_title = 'Set your new password';
$standalone = true;
ob_start();
?>
<div class="bg-slate-50 min-h-screen py-16 flex items-center justify-center">
  <div class="max-w-md w-full mx-auto px-4">
    <div class="bg-white border border-slate-200 rounded-2xl p-8 md:p-10 space-y-6 shadow-sm">
      <div class="text-center space-y-2">
        <h1 class="text-2xl font-extrabold text-slate-900">Set a new password</h1>
        <p class="text-sm text-slate-500"><?= $mustChange ? 'For security, you must choose a new password before continuing.' : 'Update your account password.' ?></p>
      </div>
      <?php if ($error): ?>
        <div class="p-4 border border-red-200 bg-red-50 text-red-700 text-sm rounded-xl"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="post" class="space-y-4">
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2">New password</label>
          <input type="password" name="password" required minlength="8" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm">
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2">Confirm password</label>
          <input type="password" name="password_confirm" required minlength="8" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm">
        </div>
        <button type="submit" class="w-full py-3.5 text-xs font-extrabold uppercase tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-xl">Save password</button>
      </form>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
