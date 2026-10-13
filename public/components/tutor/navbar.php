<?php
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$username = $_SESSION['username'] ?? 'Tutor';
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-success" href="<?php echo url('tutor/dashboard/index.php'); ?>">
            <i class="fas fa-chalkboard-teacher me-1"></i> Tutor Portal
        </a>
        <div class="navbar-nav ms-auto flex-row flex-wrap gap-2 align-items-center">
            <a class="nav-link <?php echo $current_dir === 'dashboard' ? 'active fw-semibold' : ''; ?>" href="<?php echo url('tutor/dashboard/index.php'); ?>">Dashboard</a>
            <a class="nav-link <?php echo $current_dir === 'courses' ? 'active fw-semibold' : ''; ?>" href="<?php echo url('tutor/courses/index.php'); ?>">My Courses</a>
            <a class="nav-link <?php echo $current_dir === 'students' ? 'active fw-semibold' : ''; ?>" href="<?php echo url('tutor/students/index.php'); ?>">Students</a>
            <a class="nav-link <?php echo $current_dir === 'modules' ? 'active fw-semibold' : ''; ?>" href="<?php echo url('tutor/modules/index.php'); ?>">Course Builder</a>
            <a class="nav-link <?php echo $current_dir === 'submissions' ? 'active fw-semibold' : ''; ?>" href="<?php echo url('tutor/submissions/index.php'); ?>">Submissions</a>
            <a class="nav-link <?php echo $current_dir === 'resources' ? 'active fw-semibold' : ''; ?>" href="<?php echo url('tutor/resources/index.php'); ?>">Resources</a>
            <?php
            require_once __DIR__ . '/../../../app/v1/helpers/tutor_permissions.php';
            require_once __DIR__ . '/../../../app/v1/models/Connection.php';
            if (empty($_SESSION['preview']) && tutor_admin_allowed(\Models\Connection::getInstance()->getConnection(), (int) ($_SESSION['user_id'] ?? 0))): ?>
            <a class="btn btn-sm btn-dark" href="<?php echo url('admin/dashboard.php'); ?>"><i class="fas fa-user-shield me-1"></i>Admin portal</a>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-success d-none" data-pwa-install><i class="fas fa-download me-1"></i>Install app</button>
            <?php $pwaPortal = 'tutor'; require __DIR__ . '/../pwa/bell.php'; ?>
            <span class="text-muted small ms-2"><?php echo htmlspecialchars($username); ?></span>
            <a class="nav-link text-danger" href="<?php echo url('auth/logout.php'); ?>"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</nav>
