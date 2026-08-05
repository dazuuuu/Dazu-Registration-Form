<?php
// public/components/public/navbar.php — Dazu Hub Institute
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}

if (!function_exists('dhNavActive')) {
    function dhNavActive(string $segment): string {
        $uri  = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        $self = basename($_SERVER['PHP_SELF'] ?? '');

        if ($segment === 'home') {
            return ($self === 'index.php' && !preg_match('#/(admin|students|tutor|auth|store)/#', $uri))
                ? 'is-active' : '';
        }

        $map = [
            'courses'  => '/courses',
            'about'    => '/about',
            'services' => '/services',
            'projects' => '/projects',
            'blogs'    => '/blogs',
            'contact'  => '/contact',
        ];

        if (isset($map[$segment])) {
            return strpos($uri, $map[$segment]) !== false ? 'is-active' : '';
        }

        return strpos($uri, '/' . ltrim($segment, '/')) !== false ? 'is-active' : '';
    }
}

$logoPath = url('assets/images/logo/logo.png');
$isLoggedIn  = !empty($_SESSION['logged_in']);
$isAdmin     = $isLoggedIn && isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','superadmin'], true);
$isTutor     = $isLoggedIn && isset($_SESSION['role']) && $_SESSION['role'] === 'tutor';
$isStudent   = $isLoggedIn && isset($_SESSION['role']) && $_SESSION['role'] === 'student';
$username    = htmlspecialchars($_SESSION['username'] ?? '');
$initial     = strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1));

$navLinks = [
    ['label' => 'Home',     'href' => url('/'),                    'active' => dhNavActive('home')],
    ['label' => 'Courses',  'href' => url('courses/index.php'),     'active' => dhNavActive('courses')],
    ['label' => 'About',    'href' => url('about/index.php'),       'active' => dhNavActive('about')],
    ['label' => 'Services', 'href' => url('services/index.php'),     'active' => dhNavActive('services')],
    ['label' => 'Projects', 'href' => url('projects/index.php'),     'active' => dhNavActive('projects')],
    ['label' => 'Blog',     'href' => url('blogs/index.php'),       'active' => dhNavActive('blogs')],
    ['label' => 'Contact',  'href' => url('contact/index.php'),     'active' => dhNavActive('contact')],
];
?>

<header class="dh-nav" id="dhNav" role="banner">
    <div class="dh-nav-inner dh-container">
        <a class="dh-brand" href="<?php echo url('/'); ?>" aria-label="Dazu Hub Institute — Home">
            <span class="dh-brand-logo-wrap">
                <img src="<?php echo $logoPath; ?>" alt="Dazu Hub Institute" class="dh-brand-logo" width="120" height="48"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <span class="dh-brand-fallback" style="display:none;">
                    <svg width="38" height="38" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <rect width="38" height="38" rx="8" fill="#2E7D32"/>
                        <text x="19" y="26" text-anchor="middle" font-family="Space Grotesk,sans-serif" font-size="17" font-weight="800" fill="#fff">D</text>
                    </svg>
                    <span class="dh-brand-name">Dazu <strong>Hub</strong></span>
                </span>
            </span>
        </a>

        <nav class="dh-nav-links" aria-label="Primary navigation">
            <?php foreach ($navLinks as $link): ?>
                <a href="<?php echo $link['href']; ?>" class="dh-nav-link <?php echo $link['active']; ?>">
                    <?php echo htmlspecialchars($link['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="dh-nav-actions">
            <button class="dh-theme-btn" id="dhThemeToggle" aria-label="Toggle dark mode" title="Toggle dark / light mode">
                <i class="fas fa-moon dh-icon-moon" aria-hidden="true"></i>
                <i class="fas fa-sun  dh-icon-sun"  aria-hidden="true"></i>
            </button>

            <?php if ($isLoggedIn): ?>
                <div class="dh-user-menu" id="dhUserMenu">
                    <button class="dh-user-trigger" id="dhUserTrigger" aria-expanded="false" aria-haspopup="true" aria-controls="dhUserDropdown">
                        <span class="dh-user-avatar"><?php echo $initial; ?></span>
                        <span class="dh-user-name"><?php echo $username; ?></span>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="dh-user-dropdown" id="dhUserDropdown" role="menu">
                        <?php if ($isAdmin): ?>
                            <a class="dh-dd-item" href="<?php echo url('admin/dashboard.php'); ?>" role="menuitem"><i class="fas fa-gauge-high"></i> Admin Dashboard</a>
                            <a class="dh-dd-item" href="<?php echo url('admin/registrations/index.php?status=pending'); ?>" role="menuitem"><i class="fas fa-user-clock"></i> Pending Approvals</a>
                        <?php elseif ($isTutor): ?>
                            <a class="dh-dd-item" href="<?php echo url('tutor/dashboard/index.php'); ?>" role="menuitem"><i class="fas fa-chalkboard-teacher"></i> Tutor Portal</a>
                        <?php elseif ($isStudent): ?>
                            <a class="dh-dd-item" href="<?php echo url('students/dashboard/index.php'); ?>" role="menuitem"><i class="fas fa-user-graduate"></i> Student Portal</a>
                        <?php else: ?>
                            <a class="dh-dd-item" href="<?php echo url('profile/client/'); ?>" role="menuitem"><i class="fas fa-user"></i> My Dashboard</a>
                        <?php endif; ?>
                        <a class="dh-dd-item" href="<?php echo url('profile/'); ?>" role="menuitem"><i class="fas fa-id-card"></i> My Profile</a>
                        <hr class="dh-dd-divider">
                        <a class="dh-dd-item dh-dd-danger" href="<?php echo url('auth/logout.php'); ?>" role="menuitem"><i class="fas fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?php echo url('auth/login.php'); ?>" class="dh-nav-link dh-nav-login"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="<?php echo url('auth/student_register.php'); ?>" class="dh-btn dh-btn-primary dh-nav-cta">Apply Now</a>
            <?php endif; ?>

            <button class="dh-hamburger" id="dhHamburger" aria-label="Open menu" aria-expanded="false" aria-controls="dhDrawer">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<div class="dh-drawer-backdrop" id="dhBackdrop" aria-hidden="true"></div>

<aside class="dh-drawer" id="dhDrawer" aria-hidden="true" aria-label="Mobile navigation">
    <div class="dh-drawer-head">
        <img src="<?php echo $logoPath; ?>" alt="Dazu Hub" height="36" onerror="this.style.display='none'">
        <button class="dh-drawer-close" id="dhDrawerClose" aria-label="Close menu"><i class="fas fa-times"></i></button>
    </div>
    <nav class="dh-drawer-body" aria-label="Mobile navigation">
        <?php foreach ($navLinks as $i => $link): ?>
            <a class="dh-drawer-link" href="<?php echo $link['href']; ?>" style="--di:<?php echo $i; ?>">
                <?php echo htmlspecialchars($link['label']); ?>
            </a>
        <?php endforeach; ?>
        <a class="dh-drawer-link" href="<?php echo url('auth/student_register.php'); ?>" style="--di:7"><i class="fas fa-pen-to-square"></i> Apply Now</a>
        <hr class="dh-drawer-divider" style="--di:8">
        <?php if ($isLoggedIn): ?>
            <?php if ($isAdmin): ?>
                <a class="dh-drawer-link" href="<?php echo url('admin/dashboard.php'); ?>" style="--di:9"><i class="fas fa-gauge-high"></i> Admin</a>
            <?php elseif ($isTutor): ?>
                <a class="dh-drawer-link" href="<?php echo url('tutor/dashboard/index.php'); ?>" style="--di:9"><i class="fas fa-chalkboard-teacher"></i> Tutor Portal</a>
            <?php elseif ($isStudent): ?>
                <a class="dh-drawer-link" href="<?php echo url('students/dashboard/index.php'); ?>" style="--di:9"><i class="fas fa-user-graduate"></i> Student Portal</a>
            <?php endif; ?>
            <a class="dh-drawer-link dh-drawer-link-danger" href="<?php echo url('auth/logout.php'); ?>" style="--di:10"><i class="fas fa-arrow-right-from-bracket"></i> Logout</a>
        <?php else: ?>
            <a class="dh-drawer-link" href="<?php echo url('auth/login.php'); ?>" style="--di:9"><i class="fas fa-sign-in-alt"></i> Login</a>
        <?php endif; ?>
    </nav>
</aside>

<script>
(function(){
    'use strict';
    var nav = document.getElementById('dhNav');
    window.addEventListener('scroll', function(){
        if (nav) nav.classList.toggle('scrolled', window.scrollY > 40);
    }, { passive: true });

    function applyTheme(isDark){
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
        try { localStorage.setItem('dh-theme', isDark ? 'dark' : 'light'); } catch(e){}
    }
    function isCurrentlyDark(){ return document.documentElement.getAttribute('data-theme') === 'dark'; }

    document.querySelectorAll('#dhThemeToggle, [data-drawer-theme]').forEach(function(btn){
        btn.addEventListener('click', function(){ applyTheme(!isCurrentlyDark()); });
    });

    var hamburger = document.getElementById('dhHamburger');
    var drawer    = document.getElementById('dhDrawer');
    var backdrop  = document.getElementById('dhBackdrop');
    var closeBtn  = document.getElementById('dhDrawerClose');

    function openDrawer(){
        drawer.classList.add('open'); backdrop.classList.add('open'); hamburger.classList.add('open');
        hamburger.setAttribute('aria-expanded', 'true');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeDrawer(){
        drawer.classList.remove('open'); backdrop.classList.remove('open'); hamburger.classList.remove('open');
        hamburger.setAttribute('aria-expanded', 'false');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (hamburger) hamburger.addEventListener('click', openDrawer);
    if (closeBtn)  closeBtn.addEventListener('click', closeDrawer);
    if (backdrop)  backdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && drawer && drawer.classList.contains('open')) closeDrawer(); });
    if (drawer) drawer.querySelectorAll('a').forEach(function(a){ a.addEventListener('click', function(){ setTimeout(closeDrawer, 120); }); });

    var trigger = document.getElementById('dhUserTrigger');
    var dropdown = document.getElementById('dhUserDropdown');
    if (trigger && dropdown){
        trigger.addEventListener('click', function(e){
            e.stopPropagation();
            var open = dropdown.classList.toggle('open');
            trigger.setAttribute('aria-expanded', String(open));
        });
        document.addEventListener('click', function(e){
            if (!trigger.contains(e.target) && !dropdown.contains(e.target)){
                dropdown.classList.remove('open');
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    }
})();
</script>
