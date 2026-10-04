<?php
// After sign-in, a tutor who also has admin rights picks the portal to open. They can switch any time from either portal.
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/tutor_permissions.php';

if (empty($_SESSION['logged_in'])) {
    redirect(url('auth/login.php'));
}
$uid = (int) ($_SESSION['user_id'] ?? 0);
if (!tutor_admin_allowed($pdo, $uid)) {
    redirect(url(($_SESSION['role'] ?? '') === 'tutor' ? 'tutor/dashboard/index.php' : 'auth/login.php'));
}
$name = $_SESSION['username'] ?? 'there';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Choose a portal — Dazu Training Institute</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
  body { background: #f5f6f8; min-height: 100vh; display: grid; place-items: center; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
  .portal-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px; text-decoration: none; color: #0f172a;
                 display: block; height: 100%; transition: border-color .15s, box-shadow .15s; }
  .portal-card:hover { border-color: #0D9488; box-shadow: 0 6px 20px rgba(13,148,136,.12); color: #0f172a; }
  .portal-card i { font-size: 2rem; color: #0D9488; }
</style>
</head>
<body>
<main class="container" style="max-width:640px;padding:24px 16px">
  <h1 class="h4 text-center mb-1">Welcome, <?php echo htmlspecialchars($name); ?></h1>
  <p class="text-center text-muted mb-4">Your account has the tutor portal and admin rights. Where would you like to go?</p>
  <div class="row g-3">
    <div class="col-sm-6">
      <a class="portal-card" href="<?php echo url('tutor/dashboard/index.php'); ?>">
        <i class="fas fa-chalkboard-teacher mb-3"></i>
        <h2 class="h5">Tutor portal</h2>
        <p class="small text-muted mb-0">Your students, course builder, submissions and alerts.</p>
      </a>
    </div>
    <div class="col-sm-6">
      <a class="portal-card" href="<?php echo url('admin/dashboard.php'); ?>">
        <i class="fas fa-user-shield mb-3"></i>
        <h2 class="h5">Admin portal</h2>
        <p class="small text-muted mb-0">Registrations, students, payments and the rest of the admin tools.</p>
      </a>
    </div>
  </div>
  <p class="text-center small text-muted mt-4">You can switch between them at any time from the top of either portal.
    · <a href="<?php echo url('auth/logout.php'); ?>">Sign out</a></p>
</main>
</body>
</html>
