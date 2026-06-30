<?php
// public/templates/admin/layout.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
    header('Location: /Realestate/public/auth/login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Admin Panel - <?php echo $page_title ?? 'Ismano'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* ============================================================
           ADMIN THEME - FULLY RESPONSIVE
        ============================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            color: #1a1a1a;
            overflow-x: hidden;
        }
        
        /* Admin Wrapper */
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
            position: relative;
        }
        
        /* Sidebar Styles - Matches sidebar.php */
        .admin-sidebar {
            width: 280px;
            background: #1a1a1a;
            color: #e0e0e0;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }
        
        /* Sidebar Toggle Button (Mobile) */
        .sidebar-toggle {
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1002;
            width: 45px;
            height: 45px;
            border-radius: 8px;
            background: #1a1a1a;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .sidebar-toggle:hover {
            background: #333;
            transform: scale(1.05);
        }
        
        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }
        
        /* Main Content Area */
        .admin-content {
            flex: 1;
            margin-left: 280px;
            padding: 25px 30px;
            min-height: 100vh;
            background: #f5f5f5;
            transition: margin-left 0.3s ease;
            width: calc(100% - 280px);
        }
        
        /* Top Bar */
        .top-bar {
            background: #fff;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
        }
        
        .breadcrumb {
            margin: 0;
            padding: 0;
            background: transparent;
            font-size: 0.8rem;
        }
        
        .breadcrumb-item a {
            color: #666;
            text-decoration: none;
        }
        
        .breadcrumb-item a:hover {
            color: #1a1a1a;
        }
        
        .breadcrumb-item.active {
            color: #1a1a1a;
            font-weight: 500;
        }
        
        /* User Dropdown */
        .user-dropdown .btn {
            background: #fff;
            border: 1px solid #e0e0e0;
            color: #1a1a1a;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.85rem;
        }
        
        .user-dropdown .btn:hover {
            background: #f5f5f5;
            border-color: #ccc;
        }
        
        .dropdown-menu {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .dropdown-item {
            padding: 8px 16px;
            font-size: 0.85rem;
            color: #1a1a1a;
        }
        
        .dropdown-item i {
            width: 20px;
            margin-right: 8px;
            color: #666;
        }
        
        .dropdown-item:hover {
            background: #f5f5f5;
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 12px 20px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .alert-danger {
            background: #ffebee;
            color: #c62828;
        }
        
        /* Cards */
        .card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Tables */
        .table {
            color: #1a1a1a;
            margin-bottom: 0;
        }
        
        .table thead th {
            background: #fafafa;
            border-bottom: 2px solid #e0e0e0;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Buttons */
        .btn-primary {
            background: #1a1a1a;
            border-color: #1a1a1a;
        }
        
        .btn-primary:hover {
            background: #333;
            border-color: #333;
        }
        
        .btn-outline-primary {
            color: #1a1a1a;
            border-color: #ccc;
        }
        
        .btn-outline-primary:hover {
            background: #1a1a1a;
            border-color: #1a1a1a;
            color: #fff;
        }
        
        /* Form Controls */
        .form-control:focus,
        .form-select:focus {
            border-color: #1a1a1a;
            box-shadow: 0 0 0 0.2rem rgba(0,0,0,0.1);
        }
        
        /* Scrollbar */
        .admin-sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .admin-sidebar::-webkit-scrollbar-track {
            background: #1a1a1a;
        }
        
        .admin-sidebar::-webkit-scrollbar-thumb {
            background: #444;
            border-radius: 3px;
        }
        
        .admin-sidebar::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* ============================================================
           RESPONSIVE BREAKPOINTS
        ============================================================ */
        
        /* Tablet Landscape */
        @media (max-width: 992px) {
            .sidebar-toggle {
                display: flex;
            }
            
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-sidebar.active {
                transform: translateX(0);
            }
            
            .admin-content {
                margin-left: 0;
                padding: 20px;
                width: 100%;
            }
            
            .top-bar {
                margin-top: 55px;
            }
        }
        
        /* Tablet Portrait */
        @media (max-width: 768px) {
            .admin-content {
                padding: 15px;
            }
            
            .top-bar {
                flex-direction: column;
                align-items: stretch;
                margin-top: 55px;
                gap: 12px;
            }
            
            .top-bar > div:first-child {
                text-align: center;
            }
            
            .user-dropdown {
                text-align: center;
            }
            
            .page-title {
                font-size: 1.3rem;
            }
            
            .breadcrumb {
                justify-content: center;
            }
            
            .card-header {
                padding: 12px 15px;
            }
            
            .card-body {
                padding: 15px;
            }
            
            /* Make tables scrollable on mobile */
            .table-responsive {
                margin: 0 -15px;
                width: calc(100% + 30px);
                padding: 0 15px;
            }
            
            /* Adjust button sizes for mobile */
            .btn {
                padding: 8px 16px;
                font-size: 0.85rem;
            }
            
            /* Form adjustments */
            .form-control,
            .form-select {
                font-size: 16px; /* Prevents zoom on mobile */
            }
        }
        
        /* Mobile Small */
        @media (max-width: 576px) {
            .admin-content {
                padding: 10px;
            }
            
            .top-bar {
                margin-top: 50px;
                padding: 12px 15px;
            }
            
            .page-title {
                font-size: 1.2rem;
            }
            
            .sidebar-toggle {
                top: 10px;
                left: 10px;
                width: 40px;
                height: 40px;
                font-size: 18px;
            }
            
            .card-header {
                padding: 10px 12px;
                font-size: 0.95rem;
            }
            
            .card-body {
                padding: 12px;
            }
            
            /* Stack buttons on mobile */
            .btn-group {
                flex-direction: column;
                gap: 8px;
            }
            
            .btn-group .btn {
                width: 100%;
                border-radius: 6px !important;
            }
            
            /* Adjust modal for mobile */
            .modal-dialog {
                margin: 10px;
            }
            
            .modal-body {
                padding: 15px;
            }
        }
        
        /* Desktop Large */
        @media (min-width: 1400px) {
            .container-fluid {
                max-width: 1400px;
                margin: 0 auto;
            }
            
            .admin-content {
                padding: 30px 40px;
            }
        }
        
        /* Print Styles */
        @media print {
            .admin-sidebar,
            .sidebar-toggle,
            .user-dropdown,
            .mobile-menu-toggle,
            .btn,
            .no-print {
                display: none !important;
            }
            
            .admin-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
        
        /* Loading States */
        .loading {
            position: relative;
            opacity: 0.6;
            pointer-events: none;
        }
        
        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 30px;
            height: 30px;
            margin: -15px 0 0 -15px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #1a1a1a;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Utility Classes */
        .text-truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .cursor-pointer {
            cursor: pointer;
        }
        
        /* Animation for content */
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
    
    <?php if (isset($extra_css)): ?>
        <?php echo $extra_css; ?>
    <?php endif; ?>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar Overlay (Mobile) -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Sidebar Toggle Button (Mobile) -->
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Include Admin Sidebar -->
        <?php include_once __DIR__ . '/../../components/admin/sidebar.php'; ?>
        
        <!-- Main Content Area -->
        <div class="admin-content fade-in">
            <!-- Top Bar -->
            <div class="top-bar">
                <div>
                    <h1 class="page-title"><?php echo $page_title ?? 'Dashboard'; ?></h1>
                    <?php if (isset($breadcrumbs)): ?>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mt-2">
                                <li class="breadcrumb-item"><a href="/Realestate/public/admin/dashboard.php">Dashboard</a></li>
                                <?php foreach ($breadcrumbs as $crumb): ?>
                                    <li class="breadcrumb-item <?php echo isset($crumb['active']) ? 'active' : ''; ?>">
                                        <?php if (isset($crumb['url']) && !isset($crumb['active'])): ?>
                                            <a href="<?php echo $crumb['url']; ?>"><?php echo $crumb['label']; ?></a>
                                        <?php else: ?>
                                            <?php echo $crumb['label']; ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                    <?php endif; ?>
                </div>
                <div class="user-dropdown dropdown">
                    <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/Realestate/public/profile/admin/">
                            <i class="fas fa-user"></i> My Profile
                        </a></li>
                        <?php if (isset($_SESSION['role_id']) && $_SESSION['role_id'] <= 2): ?>
                        <li><a class="dropdown-item" href="/Realestate/public/admin/settings/index.php">
                            <i class="fas fa-cog"></i> Settings
                        </a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/Realestate/public/auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a></li>
                    </ul>
                </div>
            </div>
            
            <!-- Flash Messages -->
            <?php if (isset($_SESSION['flash']['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['flash']['success']; unset($_SESSION['flash']['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['flash']['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $_SESSION['flash']['error']; unset($_SESSION['flash']['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['flash']['warning'])): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $_SESSION['flash']['warning']; unset($_SESSION['flash']['warning']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Page Content -->
            <?php echo $content; ?>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Mobile Sidebar Script -->
    <script>
        (function() {
            'use strict';
            
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.admin-sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            function openSidebar() {
                if (sidebar) sidebar.classList.add('active');
                if (overlay) overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
                
                // Save state
                localStorage.setItem('adminSidebarOpen', 'true');
            }
            
            function closeSidebar() {
                if (sidebar) sidebar.classList.remove('active');
                if (overlay) overlay.classList.remove('active');
                document.body.style.overflow = '';
                
                // Save state
                localStorage.setItem('adminSidebarOpen', 'false');
            }
            
            function toggleSidebar() {
                if (sidebar && sidebar.classList.contains('active')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }
            
            // Toggle button click
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', toggleSidebar);
            }
            
            // Overlay click
            if (overlay) {
                overlay.addEventListener('click', closeSidebar);
            }
            
            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar && sidebar.classList.contains('active')) {
                    closeSidebar();
                }
            });
            
            // Handle window resize - close sidebar when switching to desktop
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    if (window.innerWidth > 992 && sidebar && sidebar.classList.contains('active')) {
                        closeSidebar();
                    }
                }, 250);
            });
            
            // Close sidebar when clicking on a link (mobile only)
            if (sidebar) {
                const sidebarLinks = sidebar.querySelectorAll('.nav-link');
                sidebarLinks.forEach(function(link) {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= 992) {
                            setTimeout(closeSidebar, 150);
                        }
                    });
                });
            }
            
            // Restore sidebar state on page load (desktop only)
            if (window.innerWidth > 992) {
                // On desktop, sidebar is always visible
                if (sidebar) sidebar.classList.remove('active');
            } else {
                // On mobile, check saved state
                const savedState = localStorage.getItem('adminSidebarOpen');
                if (savedState === 'true' && sidebar) {
                    setTimeout(function() {
                        sidebar.classList.add('active');
                        if (overlay) overlay.classList.add('active');
                    }, 100);
                }
            }
        })();
    </script>
    
    <?php if (isset($extra_js)): ?>
        <?php echo $extra_js; ?>
    <?php endif; ?>
</body>
</html>