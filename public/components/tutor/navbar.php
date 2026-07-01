<?php
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$username = $_SESSION['username'] ?? 'Tutor';
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-success" href="/Registration-System/public/tutor/dashboard/index.php">
            <i class="fas fa-chalkboard-teacher me-1"></i> Tutor Portal
        </a>
        <div class="navbar-nav ms-auto flex-row gap-2 align-items-center">
            <a class="nav-link <?php echo $current_dir === 'dashboard' ? 'active fw-semibold' : ''; ?>" href="/Registration-System/public/tutor/dashboard/index.php">Dashboard</a>
            <a class="nav-link <?php echo $current_dir === 'courses' ? 'active fw-semibold' : ''; ?>" href="/Registration-System/public/tutor/courses/index.php">My Courses</a>
            <a class="nav-link <?php echo $current_dir === 'students' ? 'active fw-semibold' : ''; ?>" href="/Registration-System/public/tutor/students/index.php">Students</a>
            <a class="nav-link <?php echo $current_dir === 'submissions' ? 'active fw-semibold' : ''; ?>" href="/Registration-System/public/tutor/submissions/index.php">Submissions</a>
            <span class="text-muted small ms-2"><?php echo htmlspecialchars($username); ?></span>
            <a class="nav-link text-danger" href="/Registration-System/public/auth/logout.php"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</nav>
