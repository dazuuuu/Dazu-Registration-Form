<?php
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$username = $_SESSION['username'] ?? 'Staff';
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-success" href="<?php echo url('employee/dashboard/index.php'); ?>">
            <i class="fas fa-id-badge me-1"></i> Staff Portal
        </a>
        <div class="navbar-nav ms-auto flex-row gap-2 align-items-center">
            <a class="nav-link <?php echo $current_dir === 'dashboard' ? 'active fw-semibold' : ''; ?>" href="<?php echo url('employee/dashboard/index.php'); ?>">Dashboard</a>
            <a class="nav-link <?php echo $current_dir === 'profile' ? 'active fw-semibold' : ''; ?>" href="<?php echo url('employee/profile/index.php'); ?>">My profile</a>
            <span class="text-muted small ms-2 d-none d-sm-inline"><?php echo htmlspecialchars($username); ?></span>
            <a class="nav-link text-danger" href="<?php echo url('auth/logout.php'); ?>" title="Sign out"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</nav>
