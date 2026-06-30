<?php
// public/components/admin/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$role = $_SESSION['role'] ?? '';
$role_id = $_SESSION['role_id'] ?? 0;

// Determine active menu
function isActive($path, $current_dir, $current_page) {
    if (strpos($current_dir, $path) !== false) return true;
    if ($current_page == $path) return true;
    return false;
}

// Check if we're in a store subdirectory
function isStoreActive($current_dir) {
    return $current_dir == 'store' || $current_dir == 'categories' || $current_dir == 'products';
}
?>
<button class="admin-sidebar-toggle" id="adminSidebarToggle" aria-label="Toggle Sidebar">
    <i class="fas fa-bars"></i>
</button>

<div class="admin-sidebar-overlay" id="adminSidebarOverlay"></div>

<div class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header text-center">
        <button class="sidebar-close-mobile" id="sidebarCloseMobile" aria-label="Close Sidebar">
            <i class="fas fa-times"></i>
        </button>
        <h3>
            <i class="fas fa-shield-alt me-2"></i>
            Ismano Admin
        </h3>
        <small>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?></small>
        <small class="d-block mt-1">
            <span class="badge" style="background:#2a2a2a;">
                <?php echo ucfirst($role); ?>
            </span>
        </small>
    </div>
    
    <div class="sidebar-nav">
        <div class="nav flex-column">
            <!-- Dashboard -->
            <a href="/Realestate/public/admin/dashboard.php" 
               class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>

            <!-- Orders / Sales -->
            <a href="/Realestate/public/admin/orders/index.php" 
               class="nav-link <?php echo isActive('orders', $current_dir, $current_page) ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i>
                <span>Orders</span>
                <span class="orders-badge" id="ordersBadge" style="display:none;">0</span>
            </a>
            
            <!-- Enquiries -->
            <a href="/Realestate/public/admin/enquiries/index.php" 
               class="nav-link <?php echo isActive('enquiries', $current_dir, $current_page) ? 'active' : ''; ?>">
                <i class="fas fa-question-circle"></i>
                <span>Enquiries</span>
                <span class="enquiries-badge" id="enquiriesBadge" style="display:none;">0</span>
            </a>
            
            <!-- Projects -->
            <a href="/Realestate/public/admin/projects/index.php" 
               class="nav-link <?php echo isActive('projects', $current_dir, $current_page) ? 'active' : ''; ?>">
                <i class="fas fa-folder-open"></i>
                <span>Projects</span>
            </a>
            
            <!-- Services -->
            <a href="/Realestate/public/admin/services/index.php" 
               class="nav-link <?php echo isActive('services', $current_dir, $current_page) ? 'active' : ''; ?>">
                <i class="fas fa-cogs"></i>
                <span>Services</span>
            </a>
            
            <!-- Blog Posts -->
            <a href="/Realestate/public/admin/blogs/index.php" 
               class="nav-link <?php echo isActive('blogs', $current_dir, $current_page) ? 'active' : ''; ?>">
                <i class="fas fa-blog"></i>
                <span>Blog Posts</span>
            </a>

            <!-- Gallery Posts -->
            <a href="/Realestate/public/admin/gallery/index.php" 
               class="nav-link <?php echo isActive('gallery', $current_dir, $current_page) ? 'active' : ''; ?>">
                <i class="fas fa-images"></i>
                <span>Gallery</span>
            </a>
            
            <!-- Testimonials -->
            <a href="/Realestate/public/admin/testimonials/index.php" 
               class="nav-link <?php echo isActive('testimonials', $current_dir, $current_page) ? 'active' : ''; ?>">
                <i class="fas fa-star"></i>
                <span>Testimonials</span>
            </a>
            
            <!-- STORE SECTION with Dropdown -->
            <div class="nav-item">
                <a href="#storeMenu" class="nav-link <?php echo isStoreActive($current_dir) ? 'active' : ''; ?>" 
                   data-bs-toggle="collapse" role="button" aria-expanded="<?php echo isStoreActive($current_dir) ? 'true' : 'false'; ?>">
                    <i class="fas fa-store"></i>
                    <span>Store</span>
                    <i class="fas fa-chevron-down ms-auto" style="font-size: 12px;"></i>
                </a>
                <div class="collapse <?php echo isStoreActive($current_dir) ? 'show' : ''; ?>" id="storeMenu">
                    <div class="ps-4 mt-2">
                        <a href="/Realestate/public/admin/store/products/index.php" 
                           class="nav-link <?php echo $current_dir == 'products' ? 'active' : ''; ?>">
                            <i class="fas fa-box"></i>
                            <span>Products</span>
                        </a>
                        <a href="/Realestate/public/admin/store/categories/index.php" 
                           class="nav-link <?php echo $current_dir == 'categories' ? 'active' : ''; ?>">
                            <i class="fas fa-tags"></i>
                            <span>Categories</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <hr>
            
            <!-- Profile -->
            <a href="/Realestate/public/profile/admin/" 
               class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
            
            <!-- Settings -->
            <a href="/Realestate/public/admin/settings/index.php" 
               class="nav-link <?php echo isActive('settings', $current_dir, $current_page) ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            
            <!-- Logout -->
            <a href="/Realestate/public/auth/logout.php" class="nav-link text-danger">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</div>

<!-- New-order toast (page-level so it shows on mobile even when the sidebar is collapsed) -->
<div id="orderToast" class="order-toast" role="alert" aria-live="polite">
    <i class="fas fa-bell"></i>
    <div class="order-toast-body">
        <strong>New order received</strong>
        <div id="orderToastMsg" class="order-toast-msg"></div>
    </div>
    <a href="/Realestate/public/admin/orders/index.php" class="order-toast-link">View</a>
    <button type="button" class="order-toast-close" aria-label="Dismiss">&times;</button>
</div>

<style>
/* ============================================================
   ADMIN SIDEBAR - FULLY RESPONSIVE
============================================================ */

/* Sidebar Toggle Button (Mobile) */
.admin-sidebar-toggle {
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 1002;
    width: 45px;
    height: 45px;
    border-radius: 8px;
    background: var(--brand-primary, #0D9488);
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

.admin-sidebar-toggle:hover {
    background: var(--brand-primary-deep, #0A766B);
    transform: scale(1.05);
}

/* Sidebar Overlay (Mobile) */
.admin-sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    display: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.admin-sidebar-overlay.active {
    display: block;
    opacity: 1;
}

/* Sidebar Container */
.admin-sidebar {
    width: 280px;
    background: #1a1a1a;
    color: #e0e0e0;
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    transition: all 0.3s ease;
    z-index: 1001;
    box-shadow: 2px 0 10px rgba(0,0,0,0.05);
}

/* Sidebar Header */
.sidebar-header {
    padding: 25px 20px;
    border-bottom: 1px solid #333;
    margin-bottom: 20px;
    position: relative;
}

.sidebar-close-mobile {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    background: rgba(255,255,255,0.1);
    border: none;
    color: #e0e0e0;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.sidebar-close-mobile:hover {
    background: rgba(255,255,255,0.2);
    color: white;
}

.sidebar-header h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
}

.sidebar-header small {
    font-size: 0.75rem;
    color: #888;
    margin-top: 8px;
    display: block;
}

/* Sidebar Navigation */
.sidebar-nav {
    padding: 0 15px;
}

.sidebar-nav .nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 15px;
    color: #ccc;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 4px;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.sidebar-nav .nav-link i {
    width: 20px;
    font-size: 1rem;
    color: #888;
}

.sidebar-nav .nav-link:hover {
    background: #2a2a2a;
    color: #fff;
}

.sidebar-nav .nav-link:hover i {
    color: #fff;
}

.sidebar-nav .nav-link.active {
    background: #2a2a2a;
    color: #fff;
}

.sidebar-nav .nav-link.active i {
    color: #fff;
}

.sidebar-nav .nav-link.text-danger {
    color: #dc2626;
}

.sidebar-nav .nav-link.text-danger:hover {
    background: #dc2626;
    color: #fff;
}

.sidebar-nav .nav-link.text-danger:hover i {
    color: #fff;
}

.sidebar-nav hr {
    border-color: #333;
    margin: 15px 0;
}

/* Sidebar dropdown styles */
.admin-sidebar .nav-link .fa-chevron-down {
    margin-left: auto;
    transition: transform 0.3s ease;
}

.admin-sidebar .nav-link[aria-expanded="true"] .fa-chevron-down {
    transform: rotate(180deg);
}

.admin-sidebar .collapse .nav-link {
    padding-left: 35px;
    font-size: 0.85rem;
}

.admin-sidebar .collapse .nav-link i {
    font-size: 0.8rem;
    width: 20px;
}

.admin-sidebar .collapse .nav-link.active {
    background: #2a2a2a;
    border-left: 3px solid var(--brand-primary, #00A1F3);
}

/* Orders attention badge */
.admin-sidebar .orders-badge,
.admin-sidebar .enquiries-badge {
    margin-left: auto;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    background: #e74c3c;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    line-height: 20px;
    text-align: center;
    border-radius: 9999px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
    animation: ordersPulse 2s ease-in-out infinite;
}

@keyframes ordersPulse {
    0%, 100% { transform: scale(1); }
    50%      { transform: scale(1.12); }
}

/* New-order toast — fixed to the viewport, fully responsive */
.order-toast {
    position: fixed;
    right: 16px;
    bottom: 16px;
    left: auto;
    z-index: 1080;
    display: none;
    align-items: center;
    gap: 12px;
    max-width: 360px;
    width: calc(100% - 32px);
    background: #fff;
    border-left: 4px solid #e74c3c;
    border-radius: 10px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.18);
    padding: 14px 16px;
    font-size: 14px;
    animation: orderToastIn 0.3s ease;
}

.order-toast.show { display: flex; }
.order-toast > .fa-bell { color: #e74c3c; font-size: 18px; }
.order-toast-body { flex: 1; min-width: 0; }
.order-toast-msg { color: #555; font-size: 13px; }
.order-toast-link {
    background: var(--brand-primary, #00A1F3);
    color: #fff;
    text-decoration: none;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 13px;
    white-space: nowrap;
}
.order-toast-close {
    background: none; 
    border: none; 
    font-size: 22px; 
    line-height: 1;
    color: #999; 
    cursor: pointer; 
    padding: 0 2px;
}

@keyframes orderToastIn { 
    from { opacity: 0; transform: translateY(12px); } 
    to { opacity: 1; transform: translateY(0); } 
}

/* ============================================================
   RESPONSIVE BREAKPOINTS
============================================================ */

/* Tablet - Hide sidebar by default, show toggle button */
@media (max-width: 992px) {
    .admin-sidebar-toggle {
        display: flex;
    }
    
    .admin-sidebar {
        transform: translateX(-100%);
        width: 280px;
    }
    
    .admin-sidebar.active {
        transform: translateX(0);
    }
    
    .sidebar-close-mobile {
        display: flex;
    }
    
    /* Adjust main content when sidebar is hidden */
    .admin-content {
        margin-left: 0 !important;
        padding: 15px !important;
    }
}

/* Mobile */
@media (max-width: 768px) {
    .admin-sidebar-toggle {
        top: 10px;
        left: 10px;
        width: 40px;
        height: 40px;
        font-size: 18px;
    }
    
    .admin-sidebar {
        width: 260px;
    }
    
    .sidebar-header {
        padding: 20px 15px;
    }
    
    .sidebar-header h3 {
        font-size: 1.1rem;
    }
    
    .sidebar-nav {
        padding: 0 12px;
    }
    
    .sidebar-nav .nav-link {
        padding: 10px 12px;
        font-size: 0.85rem;
    }
    
    .admin-sidebar .collapse .nav-link {
        padding-left: 30px;
    }
    
    .order-toast {
        right: 12px;
        left: 12px;
        bottom: 12px;
        width: auto;
        max-width: none;
    }
}

/* Small Mobile */
@media (max-width: 480px) {
    .admin-sidebar-toggle {
        top: 8px;
        left: 8px;
        width: 36px;
        height: 36px;
    }
    
    .admin-sidebar {
        width: 85vw;
        max-width: 280px;
    }
    
    .sidebar-header {
        padding: 15px 12px;
    }
    
    .sidebar-header h3 {
        font-size: 1rem;
    }
    
    .sidebar-header small {
        font-size: 0.7rem;
    }
    
    .sidebar-nav .nav-link {
        padding: 8px 10px;
        font-size: 0.8rem;
    }
    
    .sidebar-nav .nav-link i {
        width: 18px;
        font-size: 0.9rem;
    }
}

/* Scrollbar styling */
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
</style>

<script>
// Store dropdown state in localStorage
document.addEventListener('DOMContentLoaded', function() {
    const storeLink = document.querySelector('a[href="#storeMenu"]');
    const storeMenu = document.getElementById('storeMenu');
    if (storeLink && storeMenu) {
        // Load saved state
        const savedState = localStorage.getItem('storeMenuOpen');
        if (savedState === 'true') {
            storeMenu.classList.add('show');
            storeLink.setAttribute('aria-expanded', 'true');
        }
        
        // Save state when toggled
        storeLink.addEventListener('click', function(e) {
            setTimeout(() => {
                const isOpen = storeMenu.classList.contains('show');
                localStorage.setItem('storeMenuOpen', isOpen);
            }, 100);
        });
    }
});

// Mobile sidebar toggle functionality
(function() {
    const toggleBtn = document.getElementById('adminSidebarToggle');
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('adminSidebarOverlay');
    const closeBtn = document.getElementById('sidebarCloseMobile');
    
    function openSidebar() {
        if (sidebar) sidebar.classList.add('active');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', openSidebar);
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
    }
    
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    
    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    });
    
    // Handle window resize - if sidebar is open on mobile and window is resized to desktop,
    // automatically close mobile sidebar
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 992 && sidebar && sidebar.classList.contains('active')) {
                closeSidebar();
            }
        }, 250);
    });
})();

// Live new-order notifications for admins
(function () {
    var badge = document.getElementById('ordersBadge');
    var toast = document.getElementById('orderToast');
    var toastMsg = document.getElementById('orderToastMsg');
    var toastClose = toast ? toast.querySelector('.order-toast-close') : null;
    
    if (toastClose) {
        toastClose.addEventListener('click', function () { 
            toast.classList.remove('show'); 
        });
    }

    var baseTitle = document.title;

    function setBadge(count) {
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'inline-block';
            document.title = '(' + count + ') ' + baseTitle;
        } else {
            badge.style.display = 'none';
            document.title = baseTitle;
        }
    }

    function showToast(orderNo, name) {
        if (!toast) return;
        toastMsg.textContent = orderNo + (name ? ' — ' + name : '');
        toast.classList.add('show');
        setTimeout(function () { 
            toast.classList.remove('show'); 
        }, 12000);
    }

    function poll() {
        fetch('/Realestate/public/api/store/admin/new_orders_count.php', { 
            headers: { 'Accept': 'application/json' } 
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d || !d.success) {
                if (d && d.error) console.error('[orders badge] ' + d.error + (d.where ? ' @ ' + d.where : ''));
                return;
            }
            setBadge(d.count);

            // Detect a genuinely new order across page loads via localStorage.
            var lastSeen = parseInt(localStorage.getItem('lastSeenOrderId') || '0', 10);
            if (d.latest_id > 0) {
                if (lastSeen === 0) {
                    // First run on this browser — set the baseline silently.
                    localStorage.setItem('lastSeenOrderId', d.latest_id);
                } else if (d.latest_id > lastSeen) {
                    showToast(d.latest_order, d.latest_name);
                    localStorage.setItem('lastSeenOrderId', d.latest_id);
                }
            }
        })
        .catch(function () { /* stay quiet on errors */ });
    }

    poll();
    setInterval(poll, 30000); // every 30s
})();
</script>