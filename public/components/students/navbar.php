<?php
// public/components/students/navbar.php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));
$username     = $_SESSION['username'] ?? 'Student';
$portalLocked = $portal_locked ?? false;
?>
<nav class="student-navbar">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <a href="<?php echo url('students/dashboard/index.php'); ?>" class="student-brand">
            <i class="fas fa-graduation-cap me-2"></i>Dazu Training Institute
        </a>

        <div class="d-flex align-items-center gap-3 d-none d-lg-flex student-nav-desktop">
            <?php if (!$portalLocked): ?>
                <a href="<?php echo url('students/dashboard/index.php'); ?>"
                   class="nav-link-sm <?php echo $current_dir === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> <span>Dashboard</span>
                </a>
                <a href="<?php echo url('students/course/index.php'); ?>"
                   class="nav-link-sm <?php echo $current_dir === 'course' ? 'active' : ''; ?>">
                    <i class="fas fa-book-open"></i> <span>My Course</span>
                </a>
                <a href="<?php echo url('students/payments/index.php'); ?>"
                   class="nav-link-sm <?php echo $current_dir === 'payments' ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill-wave"></i> <span>Payments</span>
                </a>
                <a href="<?php echo url('students/resources/index.php'); ?>"
                   class="nav-link-sm <?php echo $current_dir === 'resources' ? 'active' : ''; ?>">
                    <i class="fas fa-folder-open"></i> <span>Resources</span>
                </a>
            <?php endif; ?>
        </div>

        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="btn student-user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <span class="student-avatar"><i class="fas fa-user"></i></span>
                    <span class="d-none d-md-inline ms-1"><?php echo htmlspecialchars($username); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if (!$portalLocked): ?>
                        <li><a class="dropdown-item" href="<?php echo url('students/dashboard/index.php'); ?>">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo url('students/resources/index.php'); ?>">
                            <i class="fas fa-folder-open me-2"></i> Resources
                        </a></li>
                        <li><a class="dropdown-item" href="<?php echo url('students/payments/index.php'); ?>">
                            <i class="fas fa-receipt me-2"></i> Payment History
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item text-danger" href="<?php echo url(!empty($_SESSION['preview']) ? 'students/preview-exit.php' : 'auth/logout.php'); ?>">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
