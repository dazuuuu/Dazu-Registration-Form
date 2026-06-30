<?php
// public/admin/dashboard.php (Using Admin Template)
require_once __DIR__ . '/../../app/config/db_connect.php';
require_once __DIR__ . '/../../app/helpers/functions.php';

// Check authentication
if (!isLoggedIn()) {
    redirect('/Realestate/public/admin/portal.php');
}

$role_id = $_SESSION['role_id'] ?? 3;
if ($role_id > 2) {
    redirect('/Realestate/public/auth/login.php');
}

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$totalUsers = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role_id = 2");
$totalAdmins = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role_id = 3");
$totalRegularUsers = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM login_attempts WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$loginAttempts24h = $stmt->fetch()['total'];

// Page variables
$page_title = 'Dashboard';
$breadcrumbs = [
    ['label' => 'Dashboard', 'active' => true]
];

// Content
ob_start();
?>
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card-modern bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Total Users</h6>
                        <h2 class="mb-0"><?php echo $totalUsers; ?></h2>
                    </div>
                    <i class="fas fa-users fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card-modern bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Admins</h6>
                        <h2 class="mb-0"><?php echo $totalAdmins; ?></h2>
                    </div>
                    <i class="fas fa-user-shield fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card-modern bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Regular Users</h6>
                        <h2 class="mb-0"><?php echo $totalRegularUsers; ?></h2>
                    </div>
                    <i class="fas fa-user fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card-modern bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Login Attempts (24h)</h6>
                        <h2 class="mb-0"><?php echo $loginAttempts24h; ?></h2>
                    </div>
                    <i class="fas fa-chart-line fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mb-4">
        <div class="card-modern">
            <div class="card-header-modern">
                <h5 class="mb-0">Recent User Registrations</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.created_at DESC LIMIT 5");
                            while ($user = $stmt->fetch()):
                            ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $user['role_id'] == 1 ? 'danger' : ($user['role_id'] == 2 ? 'warning' : 'info'); ?>">
                                        <?php echo $user['role_name']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-4">
        <div class="card-modern">
            <div class="card-header-modern">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary-modern" onclick="window.location.href='/Realestate/public/admin/users.php'">
                        <i class="fas fa-users me-2"></i>Manage Users
                    </button>
                    <button class="btn btn-success-modern">
                        <i class="fas fa-chart-bar me-2"></i>View Reports
                    </button>
                    <button class="btn btn-info-modern">
                        <i class="fas fa-cog me-2"></i>System Settings
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card-modern mt-4">
            <div class="card-header-modern">
                <h5 class="mb-0">System Info</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <strong>PHP Version:</strong> <?php echo phpversion(); ?>
                    </li>
                    <li class="mb-2">
                        <strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?>
                    </li>
                    <li class="mb-2">
                        <strong>Logged in as:</strong> <?php echo $_SESSION['role']; ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
$extra_js = '
<script>
// Chart initialization if needed
</script>
';

$content = ob_get_clean();

// Include admin template
include __DIR__ . '/../templates/admin/layout.php';
?>