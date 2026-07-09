<?php
// public/components/public/navbar.php — Dazu Hub Tech Skills Institute (mockup)
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

$isLoggedIn  = !empty($_SESSION['logged_in']);
$isAdmin     = $isLoggedIn && isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','superadmin'], true);
$isTutor     = $isLoggedIn && isset($_SESSION['role']) && $_SESSION['role'] === 'tutor';
$isStudent   = $isLoggedIn && isset($_SESSION['role']) && $_SESSION['role'] === 'student';
$username    = htmlspecialchars($_SESSION['username'] ?? '');
$initial     = strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1));

$navLinks = [
    ['label' => 'Home',     'href' => url('/'),                 'active' => dhNavActive('home')],
    ['label' => 'Courses',  'href' => url('/#courses'),         'active' => ''],
    ['label' => 'About',    'href' => url('about/index.php'),   'active' => dhNavActive('about')],
    ['label' => 'Projects', 'href' => url('projects/index.php'),'active' => dhNavActive('projects')],
    ['label' => 'Blogs',    'href' => url('blogs/index.php'),   'active' => dhNavActive('blogs')],
    ['label' => 'Contacts', 'href' => url('contact/index.php'), 'active' => dhNavActive('contact')],
];
?>

<header class="dh-nav" id="dhNav" role="banner">
    <div class="dh-nav-inner dh-container">
        <a class="dh-brand" href="<?php echo url('/'); ?>" aria-label="Dazu Hub Tech Skills Institute — Home">
            <span class="dh-brand-icon" aria-hidden="true">
                <svg width="42" height="42" viewBox="0 0 42 42" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="42" height="42" rx="10" fill="#00A859"/>
                    <text x="21" y="28" text-anchor="middle" font-family="Inter, sans-serif" font-size="16" font-weight="800" fill="#fff">&lt;/&gt;</text>
                </svg>
            </span>
            <span class="dh-brand-text">
                <span class="dh-brand-title">Dazu <span class="dh-brand-hub">hub</span></span>
                <span class="dh-brand-sub">Tech Skills Institute</span>
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
                <a href="<?php echo url('/#register'); ?>" class="dh-btn-apply">Apply</a>
                <a href="<?php echo url('auth/login.php'); ?>" class="dh-btn-login">
                    <i class="far fa-user" aria-hidden="true"></i> Login
                </a>
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
        <span class="dh-drawer-brand">Dazu <strong>hub</strong></span>
        <button class="dh-drawer-close" id="dhDrawerClose" aria-label="Close menu"><i class="fas fa-times"></i></button>
    </div>
    <nav class="dh-drawer-body" aria-label="Mobile navigation">
        <?php foreach ($navLinks as $i => $link): ?>
            <a class="dh-drawer-link" href="<?php echo $link['href']; ?>" style="--di:<?php echo $i; ?>">
                <?php echo htmlspecialchars($link['label']); ?>
            </a>
        <?php endforeach; ?>
        <?php if ($isLoggedIn): ?>
            <hr class="dh-drawer-divider">
            <?php if ($isAdmin): ?>
                <a class="dh-drawer-link" href="<?php echo url('admin/dashboard.php'); ?>">Admin Dashboard</a>
            <?php elseif ($isTutor): ?>
                <a class="dh-drawer-link" href="<?php echo url('tutor/dashboard/index.php'); ?>">Tutor Portal</a>
            <?php elseif ($isStudent): ?>
                <a class="dh-drawer-link" href="<?php echo url('students/dashboard/index.php'); ?>">Student Portal</a>
            <?php endif; ?>
            <a class="dh-drawer-link dh-drawer-link-danger" href="<?php echo url('auth/logout.php'); ?>">Logout</a>
        <?php else: ?>
            <a class="dh-drawer-link" href="<?php echo url('auth/login.php'); ?>">Login</a>
        <?php endif; ?>
    </nav>
    <?php if (!$isLoggedIn): ?>
    <div class="dh-drawer-foot">
        <a href="<?php echo url('/#register'); ?>" class="dh-btn-apply dh-btn-apply--block">Apply</a>
    </div>
    <?php endif; ?>
</aside>

<script>
(function(){
    'use strict';
    var nav = document.getElementById('dhNav');
    window.addEventListener('scroll', function(){
        if (nav) nav.classList.toggle('scrolled', window.scrollY > 20);
    }, { passive: true });

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
