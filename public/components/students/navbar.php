<?php
// public/components/students/navbar.php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir  = basename(dirname($_SERVER['PHP_SELF']));
$username     = $_SESSION['username'] ?? 'Student';
$portalLocked = $portal_locked ?? false;
?>
<nav class="student-navbar">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <a href="/Registration-System/public/students/dashboard/index.php" class="student-brand">
            <i class="fas fa-graduation-cap me-2"></i>Dazu Hub Institute
        </a>

        <div class="d-flex align-items-center gap-3">
            <?php if (!$portalLocked): ?>
                <a href="/Registration-System/public/students/dashboard/index.php"
                   class="nav-link-sm <?php echo $current_dir === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> <span class="d-none d-md-inline">Dashboard</span>
                </a>
                <a href="/Registration-System/public/students/payments/index.php"
                   class="nav-link-sm <?php echo $current_dir === 'payments' ? 'active' : ''; ?>">
                    <i class="fas fa-money-bill-wave"></i> <span class="d-none d-md-inline">Payments</span>
                </a>
            <?php endif; ?>

            <div class="dropdown">
                <button class="btn student-user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <span class="student-avatar"><i class="fas fa-user"></i></span>
                    <span class="d-none d-md-inline ms-1"><?php echo htmlspecialchars($username); ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if (!$portalLocked): ?>
                        <li><a class="dropdown-item" href="/Registration-System/public/students/dashboard/index.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a></li>
                        <li><a class="dropdown-item" href="/Registration-System/public/students/payments/index.php">
                            <i class="fas fa-receipt me-2"></i> Payment History
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item text-danger" href="/Registration-System/public/auth/logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
