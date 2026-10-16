<?php
require_once __DIR__ . '/../../app/v1/bootstrap.php';
require_once __DIR__ . '/../../app/v1/models/DashboardModel.php';

use Models\DashboardModel;

if (isLoggedIn() && (int) ($_SESSION['role_id'] ?? 99) === 1) {
    redirect(url('superadmin/dashboard.php'));
}
if (!isLoggedIn()) {
    redirect(url('admin/portal.php'));
}
// Admins, and tutors who have been given admin rights (Middleware switches their session into the admin portal).
require_once __DIR__ . '/../../app/v1/helpers/middleware.php';
Middleware::requireAdmin();

$dash = new DashboardModel($pdo);
$stats = $dash->adminStats();
$page_title = 'System Overview';
$breadcrumbs = [['label' => 'Dashboard', 'active' => true]];

ob_start();
$pwaPortal = 'admin'; require __DIR__ . '/../components/pwa/banner.php';
require __DIR__ . '/../components/admin/app_home.php';
?>
<div class="dash-desktop">
<?php require __DIR__ . '/../components/admin/desktop_home.php'; ?>
</div><!-- /.dash-desktop -->
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/admin/layout.php';
