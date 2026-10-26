<?php
// public/components/admin/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$role = $_SESSION['role'] ?? '';
$role_id = $_SESSION['role_id'] ?? 0;
$is_class_schedule_page = $current_dir === 'settings' && ($_GET['section'] ?? '') === 'schedule';
$is_contact_settings_page = $current_dir === 'settings' && ($_GET['section'] ?? '') === 'contact';
$is_footer_settings_page = $current_dir === 'settings' && ($_GET['section'] ?? '') === 'footer';

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

// New System group: registration, courses, tutors, payments, resources, partners, testimonials
function isSystemActive($current_dir) {
    return in_array($current_dir, ['registrations', 'intakes', 'courses', 'free-courses', 'tutors', 'students', 'resources', 'partners', 'testimonials'], true);
}

// Courses sub-section: paid (admin/courses) and free (admin/free-courses) are fully
// separate listings/forms, not one page with a toggle — see CourseModel::listFiltered().
function isCoursesGroupActive($current_dir) {
    return in_array($current_dir, ['courses', 'free-courses'], true);
}

// Existing public-site content pages, now grouped under "Website Content"
function isWebsiteActive($current_dir) {
    if (isSmsActive()) {
        return false;
    }
    return in_array($current_dir, ['orders', 'enquiries', 'projects', 'services', 'blogs', 'gallery', 'campus-media', 'team'], true)
        || isStoreActive($current_dir);
}

// SmS module (admin/sms/*)
function isSmsActive() {
    return strpos($_SERVER['PHP_SELF'] ?? '', '/admin/sms/') !== false;
}

function isSmsSection($section) {
    return strpos($_SERVER['PHP_SELF'] ?? '', '/admin/sms/' . $section) !== false;
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
            Dazu Training Institute Admin
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
            <?php ob_start(); // top-level items are reordered below (Settings → Sidebar Menu Order) ?>
            <?php
            require_once __DIR__ . '/../../../app/v1/helpers/tutor_permissions.php';
            require_once __DIR__ . '/../../../app/v1/models/Connection.php';
            $sbBackToTutor = !empty($_SESSION['tutor_admin']) || admin_also_tutor($pdo ?? \Models\Connection::getInstance()->getConnection(), (int) ($_SESSION['user_id'] ?? 0));
            if ($sbBackToTutor): ?>
            <!-- A tutor using granted admin rights, or an admin who also teaches: one tap to their own tutor portal -->
            <a href="<?php echo url('tutor/dashboard/index.php'); ?>" class="nav-link" style="background:rgba(13,148,136,.15)">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>My Tutor Portal</span>
            </a>
            <?php endif; ?>
            <!--nav:dashboard-->
            <!-- Dashboard -->
            <a href="<?php echo url('admin/dashboard.php'); ?>"
               class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <?php if (empty($_SESSION['tutor_admin']) && (int) $role_id === 2): ?>
            <a href="<?php echo url('admin/expenses/index.php'); ?>"
               class="nav-link <?php echo $current_dir == 'expenses' ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i>
                <span>Expense requests</span>
            </a>
            <?php endif; ?>
            <a href="<?php echo url('admin/announcements/index.php'); ?>"
               class="nav-link <?php echo $current_dir == 'announcements' ? 'active' : ''; ?>">
                <i class="fas fa-bullhorn"></i>
                <span>Announcements</span>
            </a>
            <a href="<?php echo url('admin/notifications/index.php'); ?>"
               class="nav-link <?php echo $current_dir == 'notifications' ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
            </a>
            <a href="<?php echo url('admin/classes/index.php'); ?>"
               class="nav-link <?php echo $current_dir == 'classes' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-days"></i>
                <span>Classes &amp; Attendance</span>
            </a>
            <a href="<?php echo url('admin/interns/index.php'); ?>"
               class="nav-link <?php echo $current_dir == 'interns' ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i>
                <span>Interns</span>
            </a>

            <?php
            $exportMenuOn = $importMenuOn = true;
            try {
                require_once __DIR__ . '/../../../app/v1/models/SettingModel.php';
                require_once __DIR__ . '/../../../app/v1/models/Connection.php';
                $siteSettings = new SettingModel(\Models\Connection::getInstance()->getConnection());
                $exportMenuOn = $siteSettings->get('site_export_enabled', '1') !== '0';
                $importMenuOn = $siteSettings->get('site_import_enabled', '1') !== '0';
            } catch (Throwable $e) {}
            ?>
            <!--nav:preview-->
            <?php if (!function_exists('csrf_field')) { require_once __DIR__ . '/../../../app/v1/helpers/uploads.php'; } ?>
            <form method="POST" action="<?php echo url('admin/preview-portal.php'); ?>" target="_blank" class="m-0"><?php echo csrf_field(); ?>
                <button type="submit" class="nav-link w-100 text-start border-0 bg-transparent">
                    <i class="fas fa-eye"></i>
                    <span>Preview Student Portal</span>
                </button>
            </form>
            <form method="POST" action="<?php echo url('admin/preview-tutor-portal.php'); ?>" target="_blank" class="m-0"><?php echo csrf_field(); ?>
                <button type="submit" class="nav-link w-100 text-start border-0 bg-transparent">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Preview Tutor Portal</span>
                </button>
            </form>
            <form method="POST" action="<?php echo url('admin/preview-intern-portal.php'); ?>" target="_blank" class="m-0"><?php echo csrf_field(); ?>
                <button type="submit" class="nav-link w-100 text-start border-0 bg-transparent">
                    <i class="fas fa-user-graduate"></i>
                    <span>Preview Intern Portal</span>
                </button>
            </form>
            <!--nav:export-->
            <?php if ($exportMenuOn): ?>
            <a href="<?php echo url('admin/backup.php'); ?>"
               class="nav-link <?php echo basename($_SERVER['PHP_SELF'] ?? '') === 'backup.php' ? 'active' : ''; ?>">
                <i class="fas fa-box-archive"></i>
                <span>Export Site</span>
            </a>
            <?php endif; ?>
            <!--nav:import-->
            <?php if ($importMenuOn): ?>
            <a href="<?php echo url('admin/import.php'); ?>"
               class="nav-link <?php echo basename($_SERVER['PHP_SELF'] ?? '') === 'import.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-import"></i>
                <span>Import Site</span>
            </a>
            <?php endif; ?>

            <!--nav:updates-->
            <a href="<?php echo url('admin/system/updates.php'); ?>"
               class="nav-link <?php echo $current_dir == 'system' ? 'active' : ''; ?>">
                <i class="fas fa-database"></i>
                <span>System Updates</span>
                <?php if (!empty($pending_migration_count)): ?>
                    <span class="updates-badge"><?php echo (int) $pending_migration_count; ?></span>
                <?php endif; ?>
            </a>

            <!--nav:system-->
            <!-- SYSTEM SECTION: registration, courses, students, tutors, payments -->
            <div class="nav-item">
                <a href="#systemMenu" class="nav-link <?php echo isSystemActive($current_dir) ? 'active' : ''; ?>"
                   data-bs-toggle="collapse" role="button" aria-expanded="<?php echo isSystemActive($current_dir) ? 'true' : 'false'; ?>">
                    <i class="fas fa-graduation-cap"></i>
                    <span>System</span>
                    <i class="fas fa-chevron-down ms-auto" style="font-size: 12px;"></i>
                </a>
                <div class="collapse <?php echo isSystemActive($current_dir) ? 'show' : ''; ?>" id="systemMenu">
                    <div class="ps-4 mt-2">
                        <a href="<?php echo url('admin/registrations/index.php?status=pending'); ?>"
                           class="nav-link <?php echo $current_dir == 'registrations' ? 'active' : ''; ?>">
                            <i class="fas fa-user-clock"></i>
                            <span>Pending Approvals</span>
                            <span class="registrations-badge" id="registrationsBadge" style="display:none;">0</span>
                        </a>
                        <?php $ikOpen = 0; try { require_once __DIR__ . "/../../../app/v1/models/Connection.php"; $ikDb = \Models\Connection::getInstance()->getConnection(); $ikOpen = (int) $ikDb->query("SELECT COUNT(*) FROM enrolment_bookings WHERE status = 'waiting'")->fetchColumn(); } catch (Throwable $ikE) {} ?>
                        <a href="<?php echo url('admin/intakes/index.php'); ?>"
                           class="nav-link <?php echo $current_dir == 'intakes' && $current_page !== 'bookings.php' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-days"></i>
                            <span>Enrolment</span>
                        </a>
                        <a href="<?php echo url('admin/intakes/bookings.php'); ?>"
                           class="nav-link <?php echo $current_dir == 'intakes' && $current_page === 'bookings.php' ? 'active' : ''; ?>">
                            <i class="fas fa-bookmark"></i>
                            <span>Bookings</span>
                            <?php if ($ikOpen): ?><span class="registrations-badge"><?php echo $ikOpen; ?></span><?php endif; ?>
                        </a>
                        <!-- COURSES SUB-SECTION: paid and free are separate listings/forms -->
                        <div class="nav-item">
                            <a href="#coursesMenu" class="nav-link <?php echo isCoursesGroupActive($current_dir) ? 'active' : ''; ?>"
                               data-bs-toggle="collapse" role="button" aria-expanded="<?php echo isCoursesGroupActive($current_dir) ? 'true' : 'false'; ?>">
                                <i class="fas fa-book"></i>
                                <span>Courses</span>
                                <i class="fas fa-chevron-down ms-auto" style="font-size: 12px;"></i>
                            </a>
                            <div class="collapse <?php echo isCoursesGroupActive($current_dir) ? 'show' : ''; ?>" id="coursesMenu">
                                <div class="ps-4 mt-2">
                                    <a href="<?php echo url('admin/courses/index.php'); ?>"
                                       class="nav-link <?php echo $current_dir == 'courses' ? 'active' : ''; ?>">
                                        <i class="fas fa-book"></i>
                                        <span>Courses</span>
                                    </a>
                                    <a href="<?php echo url('admin/free-courses/index.php'); ?>"
                                       class="nav-link <?php echo $current_dir == 'free-courses' ? 'active' : ''; ?>">
                                        <i class="fas fa-gift"></i>
                                        <span>Free Courses</span>
                                    </a>
                                    <a href="<?php echo url('admin/courses/classes.php'); ?>"
                                       class="nav-link <?php echo basename($_SERVER['PHP_SELF'] ?? '') === 'classes.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-layer-group"></i>
                                        <span>Classes</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <a href="<?php echo url('admin/tutors/index.php'); ?>"
                           class="nav-link <?php echo $current_dir == 'tutors' ? 'active' : ''; ?>">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <span>Tutors</span>
                        </a>
                        <a href="<?php echo url('admin/tutors/ids.php'); ?>"
                           class="nav-link <?php echo $current_page == 'ids.php' ? 'active' : ''; ?>">
                            <i class="fas fa-id-badge"></i>
                            <span>Tutor IDs</span>
                        </a>
                        <?php if (empty($_SESSION['tutor_admin'])): ?>
                        <a href="<?php echo url('admin/tutors/permissions.php'); ?>"
                           class="nav-link <?php echo $current_page == 'permissions.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user-lock"></i>
                            <span>Permissions</span>
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo url('admin/students/index.php'); ?>"
                           class="nav-link <?php echo $current_dir == 'students' ? 'active' : ''; ?>">
                            <i class="fas fa-user-graduate"></i>
                            <span>Students</span>
                        </a>
                        <a href="<?php echo url('admin/students/enroll.php'); ?>"
                           class="nav-link">
                            <i class="fas fa-user-plus"></i>
                            <span>Enrol Student</span>
                        </a>
                        <a href="<?php echo url('admin/resources/index.php'); ?>"
                           class="nav-link <?php echo $current_dir == 'resources' ? 'active' : ''; ?>">
                            <i class="fas fa-folder-open"></i>
                            <span>Resources &amp; IDs</span>
                        </a>
                        <a href="<?php echo url('admin/partners/index.php'); ?>"
                           class="nav-link <?php echo $current_dir == 'partners' ? 'active' : ''; ?>">
                            <i class="fas fa-handshake"></i>
                            <span>Partners</span>
                        </a>
                        <a href="<?php echo url('admin/testimonials/index.php'); ?>"
                           class="nav-link <?php echo isActive('testimonials', $current_dir, $current_page) ? 'active' : ''; ?>">
                            <i class="fas fa-star"></i>
                            <span>Testimonials</span>
                        </a>
                    </div>
                </div>
            </div>

            <!--nav:certificates-->
            <!-- Certificates (Standalone) -->
            <a href="<?php echo url('admin/certificates/index.php'); ?>"
               class="nav-link <?php echo $current_dir == 'certificates' ? 'active' : ''; ?>">
                <i class="fas fa-certificate"></i>
                <span>Certificates</span>
            </a>

            <!--nav:id_cards-->
            <a href="<?php echo url('admin/id_cards/index.php'); ?>"
               class="nav-link <?php echo $current_dir == 'id_cards' ? 'active' : ''; ?>">
                <i class="fas fa-id-card"></i>
                <span>Student ID Cards</span>
            </a>

            <!--nav:directors-->
            <a href="<?php echo url('admin/directors/index.php'); ?>"
               class="nav-link <?php echo $current_dir == 'directors' ? 'active' : ''; ?>">
                <i class="fas fa-signature"></i>
                <span>Directors</span>
            </a>

            <!--nav:website-->
            <!-- WEBSITE CONTENT SECTION: the original public-site management pages -->
            <div class="nav-item">
                <a href="#websiteMenu" class="nav-link <?php echo isWebsiteActive($current_dir) ? 'active' : ''; ?>"
                   data-bs-toggle="collapse" role="button" aria-expanded="<?php echo isWebsiteActive($current_dir) ? 'true' : 'false'; ?>">
                    <i class="fas fa-globe"></i>
                    <span>Website Content</span>
                    <i class="fas fa-chevron-down ms-auto" style="font-size: 12px;"></i>
                </a>
                <div class="collapse <?php echo isWebsiteActive($current_dir) ? 'show' : ''; ?>" id="websiteMenu">
                    <div class="ps-4 mt-2">
                        <a href="<?php echo url('admin/orders/index.php'); ?>" 
                           class="nav-link <?php echo isActive('orders', $current_dir, $current_page) ? 'active' : ''; ?>">
                            <i class="fas fa-receipt"></i>
                            <span>Orders</span>
                            <span class="orders-badge" id="ordersBadge" style="display:none;">0</span>
                        </a>
                        <a href="<?php echo url('admin/enquiries/index.php'); ?>" 
                           class="nav-link <?php echo isActive('enquiries', $current_dir, $current_page) ? 'active' : ''; ?>">
                            <i class="fas fa-question-circle"></i>
                            <span>Enquiries</span>
                            <span class="enquiries-badge" id="enquiriesBadge" style="display:none;">0</span>
                        </a>
                        <a href="<?php echo url('admin/projects/index.php'); ?>" 
                           class="nav-link <?php echo isActive('projects', $current_dir, $current_page) ? 'active' : ''; ?>">
                            <i class="fas fa-folder-open"></i>
                            <span>Projects</span>
                        </a>
                        <a href="<?php echo url('admin/services/index.php'); ?>" 
                           class="nav-link <?php echo isActive('services', $current_dir, $current_page) ? 'active' : ''; ?>">
                            <i class="fas fa-cogs"></i>
                            <span>Services</span>
                        </a>
                        <!-- BLOG SUB-SECTION: posts and categories -->
                        <div class="nav-item">
                            <a href="#blogMenu" class="nav-link <?php echo $current_dir == 'blogs' ? 'active' : ''; ?>"
                               data-bs-toggle="collapse" role="button" aria-expanded="<?php echo $current_dir == 'blogs' ? 'true' : 'false'; ?>">
                                <i class="fas fa-blog"></i>
                                <span>Blog</span>
                                <i class="fas fa-chevron-down ms-auto" style="font-size: 12px;"></i>
                            </a>
                            <div class="collapse <?php echo $current_dir == 'blogs' ? 'show' : ''; ?>" id="blogMenu">
                                <div class="ps-4 mt-2">
                                    <a href="<?php echo url('admin/blogs/index.php'); ?>"
                                       class="nav-link <?php echo $current_dir == 'blogs' && in_array($current_page, ['index.php', 'edit.php'], true) ? 'active' : ''; ?>">
                                        <i class="fas fa-list"></i>
                                        <span>All Posts</span>
                                    </a>
                                    <a href="<?php echo url('admin/blogs/create.php'); ?>"
                                       class="nav-link <?php echo $current_dir == 'blogs' && $current_page == 'create.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-plus"></i>
                                        <span>New Post</span>
                                    </a>
                                    <a href="<?php echo url('admin/blogs/categories.php'); ?>"
                                       class="nav-link <?php echo $current_dir == 'blogs' && $current_page == 'categories.php' ? 'active' : ''; ?>">
                                        <i class="fas fa-folder"></i>
                                        <span>Categories</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <a href="<?php echo url('admin/gallery/index.php'); ?>"
                           class="nav-link <?php echo isActive('gallery', $current_dir, $current_page) ? 'active' : ''; ?>">
                            <i class="fas fa-images"></i>
                            <span>Gallery</span>
                        </a>
                        <a href="<?php echo url('admin/campus-media/index.php'); ?>"
                           class="nav-link <?php echo isActive('campus-media', $current_dir, $current_page) ? 'active' : ''; ?>">
                            <i class="fas fa-camera-retro"></i>
                            <span>Campus Media</span>
                        </a>
                        <a href="<?php echo url('admin/team/index.php'); ?>"
                           class="nav-link <?php echo isActive('team', $current_dir, $current_page) ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i>
                            <span>Team</span>
                        </a>
                        <!-- STORE SUB-SECTION (nested collapse, unchanged from original) -->
                        <div class="nav-item">
                            <a href="#storeMenu" class="nav-link <?php echo isStoreActive($current_dir) ? 'active' : ''; ?>" 
                               data-bs-toggle="collapse" role="button" aria-expanded="<?php echo isStoreActive($current_dir) ? 'true' : 'false'; ?>">
                                <i class="fas fa-store"></i>
                                <span>Store</span>
                                <i class="fas fa-chevron-down ms-auto" style="font-size: 12px;"></i>
                            </a>
                            <div class="collapse <?php echo isStoreActive($current_dir) ? 'show' : ''; ?>" id="storeMenu">
                                <div class="ps-4 mt-2">
                                    <a href="<?php echo url('admin/store/products/index.php'); ?>" 
                                       class="nav-link <?php echo $current_dir == 'products' ? 'active' : ''; ?>">
                                        <i class="fas fa-box"></i>
                                        <span>Products</span>
                                    </a>
                                    <a href="<?php echo url('admin/store/categories/index.php'); ?>" 
                                       class="nav-link <?php echo $current_dir == 'categories' ? 'active' : ''; ?>">
                                        <i class="fas fa-tags"></i>
                                        <span>Categories</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!--nav:divider-->
            <hr>
            
            <!--nav:profile-->
            <!-- Profile -->
            <a href="<?php echo url('profile/admin/'); ?>" 
               class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
            
            <!--nav:settings-->
            <!-- Settings -->
            <a href="<?php echo url('admin/settings/index.php'); ?>"
               class="nav-link <?php echo isActive('settings', $current_dir, $current_page) && !$is_class_schedule_page && !$is_contact_settings_page && !$is_footer_settings_page ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <!--nav:contact_settings-->
            <a href="<?php echo url('admin/settings/edit.php?section=contact'); ?>"
               class="nav-link <?php echo $is_contact_settings_page ? 'active' : ''; ?>">
                <i class="fas fa-address-book"></i>
                <span>Contact Settings</span>
            </a>
            <!--nav:footer_settings-->
            <a href="<?php echo url('admin/settings/edit.php?section=footer'); ?>"
               class="nav-link <?php echo $is_footer_settings_page ? 'active' : ''; ?>">
                <i class="fas fa-shoe-prints"></i>
                <span>Footer &amp; Social Links</span>
            </a>
            <!--nav:schedule-->
            <a href="<?php echo url('admin/settings/edit.php?section=schedule'); ?>"
               class="nav-link <?php echo $is_class_schedule_page ? 'active' : ''; ?>">
                <i class="fas fa-calendar-days"></i>
                <span>Class Schedule</span>
            </a>

            <?php
            require_once __DIR__ . '/../../../app/v1/helpers/admin_nav.php';
            echo admin_nav_reorder(ob_get_clean(), admin_nav_order($siteSettings ?? null));
            ?>

            <!-- Logout (always last) -->
            <a href="<?php echo url('auth/logout.php'); ?>" class="nav-link text-danger">
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
    <a href="<?php echo url('admin/orders/index.php'); ?>" class="order-toast-link">View</a>
    <button type="button" class="order-toast-close" aria-label="Dismiss">&times;</button>
</div>

<?php
// Mobile (≤768px): bottom bar instead of the drawer. Each category tab opens its links in a bottom sheet.
// [label, path, icon]; a path starting with "post:" is submitted as a CSRF form in a new tab.
$adm_self = $_SERVER['PHP_SELF'] ?? '';
$adm_sheets = [
    'system' => ['System', 'fa-graduation-cap', isSystemActive($current_dir), [
        ['Pending approvals', 'admin/registrations/index.php?status=pending', 'fa-user-clock'],
        ['Announcements', 'admin/announcements/index.php', 'fa-bullhorn'],
        ['Classes & attendance', 'admin/classes/index.php', 'fa-calendar-days'],
        ['Interns', 'admin/interns/index.php', 'fa-user-graduate'],
        ['My interns', 'admin/interns/supervise.php', 'fa-user-check'],
        ['Courses', 'admin/courses/index.php', 'fa-book'],
        ['Add a course', 'admin/courses/create.php', 'fa-plus'],
        ['Free courses', 'admin/free-courses/index.php', 'fa-gift'],
        ['Classes', 'admin/courses/classes.php', 'fa-layer-group'],
        ['Tutors', 'admin/tutors/index.php', 'fa-chalkboard-teacher'],
        empty($_SESSION['tutor_admin']) ? ['Permissions', 'admin/tutors/permissions.php', 'fa-user-lock'] : null,
        ['Students', 'admin/students/index.php', 'fa-user-graduate'],
        ['Enrol student', 'admin/students/enroll.php', 'fa-user-plus'],
        ['Resources & IDs', 'admin/resources/index.php', 'fa-folder-open'],
        ['Partners', 'admin/partners/index.php', 'fa-handshake'],
        ['Testimonials', 'admin/testimonials/index.php', 'fa-star'],
        ['Certificates', 'admin/certificates/index.php', 'fa-certificate'],
        ['Student ID cards', 'admin/id_cards/index.php', 'fa-id-card'],
        ['Directors', 'admin/directors/index.php', 'fa-signature'],
    ]],
    'website' => ['Website', 'fa-globe', isWebsiteActive($current_dir), [
        ['Orders', 'admin/orders/index.php', 'fa-receipt'],
        ['Enquiries', 'admin/enquiries/index.php', 'fa-question-circle'],
        ['Projects', 'admin/projects/index.php', 'fa-folder-open'],
        ['Services', 'admin/services/index.php', 'fa-cogs'],
        ['Blog posts', 'admin/blogs/index.php', 'fa-blog'],
        ['New post', 'admin/blogs/create.php', 'fa-plus'],
        ['Blog categories', 'admin/blogs/categories.php', 'fa-folder'],
        ['Gallery', 'admin/gallery/index.php', 'fa-images'],
        ['Campus media', 'admin/campus-media/index.php', 'fa-camera-retro'],
        ['Team', 'admin/team/index.php', 'fa-users'],
        ['Products', 'admin/store/products/index.php', 'fa-box'],
        ['Store categories', 'admin/store/categories/index.php', 'fa-tags'],
    ]],
    'more' => ['More', 'fa-grip', in_array($current_dir, ['settings', 'system'], true) || in_array($current_page, ['backup.php', 'import.php'], true), [
        !empty($_SESSION['tutor_admin']) ? ['My tutor portal', 'tutor/dashboard/index.php', 'fa-chalkboard-teacher'] : null,
        ['Notifications', 'admin/notifications/index.php', 'fa-bell'],
        ['System updates', 'admin/system/updates.php', 'fa-database'],
        ['Settings', 'admin/settings/index.php', 'fa-cog'],
        ['Contact settings', 'admin/settings/edit.php?section=contact', 'fa-address-book'],
        ['Footer & social', 'admin/settings/edit.php?section=footer', 'fa-shoe-prints'],
        ['Class schedule', 'admin/settings/edit.php?section=schedule', 'fa-calendar-days'],
        ($exportMenuOn ?? true) ? ['Export site', 'admin/backup.php', 'fa-box-archive'] : null,
        ($importMenuOn ?? true) ? ['Import site', 'admin/import.php', 'fa-file-import'] : null,
        ['Preview student portal', 'post:admin/preview-portal.php', 'fa-eye'],
        ['Preview tutor portal', 'post:admin/preview-tutor-portal.php', 'fa-chalkboard-teacher'],
        ['Preview intern portal', 'post:admin/preview-intern-portal.php', 'fa-user-graduate'],
        ['My profile', 'profile/admin/', 'fa-user-circle'],
        ['Logout', 'auth/logout.php', 'fa-sign-out-alt'],
    ]],
];
?>
<nav class="adm-bottom" aria-label="Admin sections">
    <a href="<?php echo url('admin/dashboard.php'); ?>" class="<?php echo $current_page === 'dashboard.php' ? 'on' : ''; ?>"><i class="fas fa-house"></i><span>Home</span></a>
    <?php foreach (['system', 'enrol', 'website', 'more'] as $key): ?>
        <?php if ($key === 'enrol'): ?>
            <a href="<?php echo url('admin/students/enroll.php'); ?>" class="adm-fab <?php echo $current_page === 'enroll.php' ? 'on' : ''; ?>" aria-label="Enrol a student"><b><i class="fas fa-plus"></i></b><span>Enrol</span></a>
        <?php else: [$label, $icon, $active] = $adm_sheets[$key]; ?>
            <button type="button" class="<?php echo $active ? 'on' : ''; ?>" data-adm-sheet="<?php echo $key; ?>" aria-controls="admSheet-<?php echo $key; ?>" aria-expanded="false"><i class="fas <?php echo $icon; ?>"></i><span><?php echo $label; ?></span></button>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
<div class="adm-sheet-bg" id="admSheetBg" hidden></div>
<?php foreach ($adm_sheets as $key => [$label, , , $links]): ?>
<div class="adm-sheet" id="admSheet-<?php echo $key; ?>" role="dialog" aria-label="<?php echo $label; ?>" hidden>
    <div class="adm-sheet-grab"></div>
    <h4><?php echo $label; ?></h4>
    <div class="adm-sheet-grid">
    <?php foreach (array_filter($links) as [$text, $path, $icon]): ?>
        <?php if (strpos($path, 'post:') === 0): ?>
            <form method="POST" action="<?php echo url(substr($path, 5)); ?>" target="_blank" class="m-0"><?php echo csrf_field(); ?>
                <button type="submit"><i class="fas <?php echo $icon; ?>"></i><span><?php echo $text; ?></span></button>
            </form>
        <?php else: ?>
            <a href="<?php echo url($path); ?>" class="<?php echo $path === 'auth/logout.php' ? 'danger' : ''; ?>"><i class="fas <?php echo $icon; ?>"></i><span><?php echo htmlspecialchars($text); ?></span></a>
        <?php endif; ?>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

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
    background: linear-gradient(180deg, #373435 0%, #2b2829 100%);
    color: #e9ece7;
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
    border-bottom: 1px solid rgba(255,255,255,.08);
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

.sidebar-header h3 i { color: #44A024; }
.sidebar-header h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
}

.sidebar-header small {
    font-size: 0.75rem;
    color: #b9c2b6;
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
    color: #dfe5dc;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 4px;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.sidebar-nav .nav-link i {
    width: 20px;
    font-size: 1rem;
    color: #9bc98a;
}

.sidebar-nav .nav-link:hover {
    background: rgba(68,160,36,.16);
    color: #fff;
}

.sidebar-nav .nav-link:hover i {
    color: #fff;
}

.sidebar-nav .nav-link.active {
    background: #44A024;
    color: #fff;
    box-shadow: 0 6px 16px rgba(68,160,36,.35);
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
    border-color: rgba(255,255,255,.1);
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
    background: rgba(68,160,36,.22);
    box-shadow: none;
    border-left: 3px solid #44A024;
}

/* Orders attention badge */
.admin-sidebar .orders-badge,
.admin-sidebar .enquiries-badge,
.admin-sidebar .updates-badge {
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

/* Mobile bottom bar + category sheets (hidden on tablet/desktop, which keep the sidebar) */
.adm-bottom, .adm-sheet, .adm-sheet-bg { display: none; }

@media (max-width: 768px) {
    .admin-sidebar-toggle, .admin-sidebar, .admin-sidebar-overlay, .sidebar-toggle { display: none !important; }
    .admin-content { padding-bottom: calc(84px + env(safe-area-inset-bottom)) !important; }
    .admin-content .top-bar { margin-top: 0 !important; }
    .order-toast { bottom: calc(76px + env(safe-area-inset-bottom)); }

    .adm-bottom {
        display: flex; position: fixed; left: 0; right: 0; bottom: 0; z-index: 1043;
        background: #fff; border-top: 1px solid #eceff1;
        padding: 6px 4px calc(6px + env(safe-area-inset-bottom));
        box-shadow: 0 -4px 16px rgba(0,0,0,.18);
    }
    .adm-bottom a, .adm-bottom button {
        flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; align-items: center; gap: 3px;
        background: none; border: 0; color: #8a9096; text-decoration: none;
        font-size: 11px; padding: 6px 2px; border-radius: 10px;
    }
    .adm-bottom i { font-size: 18px; }
    .adm-bottom span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
    .adm-bottom .on, .adm-bottom [aria-expanded="true"] { color: #2f7d4f; background: #eaf5ee; font-weight: 600; }
    .adm-bottom .on i { color: #2f7d4f; }
    .adm-bottom .adm-fab { background: none !important; color: #2f7d4f; }
    .adm-bottom .adm-fab b { width: 50px; height: 50px; margin-top: -24px; border-radius: 50%; background: #2f7d4f; color: #fff;
        display: flex; align-items: center; justify-content: center; box-shadow: 0 6px 16px rgba(47,125,79,.4); border: 4px solid #fff; }
    .adm-bottom .adm-fab b i { font-size: 20px; color: #fff; }

    .adm-sheet-bg:not([hidden]) { display: block; position: fixed; inset: 0; z-index: 1041; background: rgba(0,0,0,.45); }
    .adm-sheet:not([hidden]) {
        display: block; position: fixed; left: 0; right: 0; bottom: 0; z-index: 1042;
        max-height: 78vh; overflow-y: auto; background: #fff; color: #1a1a1a;
        border-radius: 18px 18px 0 0; padding: 8px 16px calc(84px + env(safe-area-inset-bottom));
        animation: admSheetIn .22s ease;
    }
    .adm-sheet-grab { width: 40px; height: 4px; border-radius: 4px; background: #ccc; margin: 4px auto 12px; }
    .adm-sheet h4 { font-size: .8rem; text-transform: uppercase; letter-spacing: .06em; color: #777; margin: 0 0 10px; }
    .adm-sheet-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
    .adm-sheet-grid a, .adm-sheet-grid button {
        width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px;
        padding: 12px 6px; border: 1px solid #eee; border-radius: 12px; background: #fafafa;
        color: #1a1a1a; text-decoration: none; font-size: 12px; text-align: center; line-height: 1.2;
    }
    .adm-sheet-grid i { font-size: 18px; color: var(--brand-primary, #0D9488); }
    .adm-sheet-grid .danger, .adm-sheet-grid .danger i { color: #dc2626; }
    body.adm-sheet-open { overflow: hidden; }
}

@keyframes admSheetIn { from { transform: translateY(30px); opacity: 0; } to { transform: none; opacity: 1; } }

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
    background: #2b2829;
}

.admin-sidebar::-webkit-scrollbar-thumb {
    background: rgba(68,160,36,.5);
    border-radius: 3px;
}

.admin-sidebar::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

<script>
// Persist collapse state per menu group in localStorage
document.addEventListener('DOMContentLoaded', function() {
    var groups = ['systemMenu', 'coursesMenu', 'websiteMenu', 'storeMenu'];
    groups.forEach(function (id) {
        var link = document.querySelector('a[href="#' + id + '"]');
        var menu = document.getElementById(id);
        if (!link || !menu) return;

        var savedState = localStorage.getItem(id + 'Open');
        if (savedState === 'true') {
            menu.classList.add('show');
            link.setAttribute('aria-expanded', 'true');
        } else if (savedState === 'false') {
            menu.classList.remove('show');
            link.setAttribute('aria-expanded', 'false');
        }
        // If savedState is null (first visit), leave whatever the PHP active-state logic set.

        link.addEventListener('click', function() {
            setTimeout(function () {
                localStorage.setItem(id + 'Open', menu.classList.contains('show'));
            }, 100);
        });
    });
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

// Portal previews open in a new tab, except inside the installed app: it keeps its own cookies, so a new browser tab
// would not have the preview session. There the preview opens in the same window ("Exit preview" comes back here).
(function () {
    var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
    if (!standalone) return;
    document.querySelectorAll('form[action*="preview-portal"], form[action*="preview-tutor-portal"], form[action*="preview-intern-portal"]').forEach(function (f) { f.target = '_self'; });
})();

// Mobile bottom bar: any [data-adm-sheet] button (bottom bar or a page tile) toggles that sheet
(function () {
    var bg = document.getElementById('admSheetBg');
    function sheets() { return Array.prototype.slice.call(document.querySelectorAll('.adm-sheet')); }
    function closeAll() {
        sheets().forEach(function (s) { s.hidden = true; });
        document.querySelectorAll('[data-adm-sheet]').forEach(function (t) { t.setAttribute('aria-expanded', 'false'); });
        if (bg) bg.hidden = true;
        document.body.classList.remove('adm-sheet-open');
    }
    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-adm-sheet]');
        if (!t) return;
        var s = document.getElementById('admSheet-' + t.getAttribute('data-adm-sheet'));
        if (!s) return;
        var wasOpen = !s.hidden;
        closeAll();
        if (wasOpen) return;
        s.hidden = false;
        if (bg) bg.hidden = false;
        document.querySelectorAll('[data-adm-sheet="' + t.getAttribute('data-adm-sheet') + '"]').forEach(function (x) { x.setAttribute('aria-expanded', 'true'); });
        document.body.classList.add('adm-sheet-open');
    });
    if (bg) bg.addEventListener('click', closeAll);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
    window.addEventListener('resize', function () { if (window.innerWidth > 768) closeAll(); });
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
        fetch(url('api/store/admin/new_orders_count.php'), { 
            headers: { 'Accept': 'application/json' } 
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d || !d.success) {
                if (d && d.error && d.store_enabled !== false) {
                    console.error('[orders badge] ' + d.error + (d.where ? ' @ ' + d.where : ''));
                }
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
