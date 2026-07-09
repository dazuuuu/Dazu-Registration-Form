<?php
// public/components/public/navbar.php — Dazu Hub Tech Skills Institute
if (session_status() === PHP_SESSION_NONE) session_start();

$base = '/Registration-System/public';

if (!function_exists('dhNavActive')) {
    function dhNavActive(string $segment): string {
        $uri  = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        $self = basename($_SERVER['PHP_SELF'] ?? '');
        if ($segment === 'home') {
            return ($self === 'index.php' && strpos($uri, '/public/') !== false
                    && strpos($uri, '/admin/') === false) ? 'is-active' : '';
        }
        return strpos($uri, '/' . ltrim($segment, '/')) !== false ? 'is-active' : '';
    }
}

$isLoggedIn  = !empty($_SESSION['logged_in']);
$isAdmin     = $isLoggedIn && isset($_SESSION['role_id']) && $_SESSION['role_id'] <= 2;
$isTutor     = $isLoggedIn && isset($_SESSION['role_id']) && $_SESSION['role_id'] == 3;
$username    = htmlspecialchars($_SESSION['username'] ?? '');
$initial     = strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1));
?>

<header class="dh-nav" id="dhNav" role="banner">
    <div class="dh-nav-inner dh-container">

        <a class="dh-brand" href="<?php echo $base; ?>/" aria-label="Dazu Hub Tech Skills Institute — Home">
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
            <a href="<?php echo $base; ?>/" class="dh-nav-link <?php echo dhNavActive('home'); ?>">Home</a>
            <a href="<?php echo $base; ?>/#courses" class="dh-nav-link">Courses</a>
            <a href="<?php echo $base; ?>/about/" class="dh-nav-link <?php echo dhNavActive('about'); ?>">About</a>
            <a href="<?php echo $base; ?>/projects/" class="dh-nav-link <?php echo dhNavActive('projects'); ?>">Projects</a>
            <a href="<?php echo $base; ?>/blogs/" class="dh-nav-link <?php echo dhNavActive('blogs'); ?>">Blogs</a>
            <a href="<?php echo $base; ?>/contact/" class="dh-nav-link <?php echo dhNavActive('contact'); ?>">Contacts</a>
        </nav>

        <div class="dh-nav-actions">
            <?php if ($isLoggedIn): ?>
                <div class="dh-user-menu" id="dhUserMenu">
                    <button class="dh-user-trigger" id="dhUserTrigger"
                            aria-expanded="false" aria-haspopup="true" aria-controls="dhUserDropdown">
                        <span class="dh-user-avatar"><?php echo $initial; ?></span>
                        <span class="dh-user-name"><?php echo $username; ?></span>
                        <i class="fas fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="dh-user-dropdown" id="dhUserDropdown" role="menu">
                        <?php if ($isAdmin): ?>
                            <a class="dh-dd-item" href="<?php echo $base; ?>/admin/dashboard.php" role="menuitem">
                                <i class="fas fa-gauge-high"></i> Admin Dashboard
                            </a>
                            <a class="dh-dd-item" href="<?php echo $base; ?>/admin/registrations/index.php?status=pending" role="menuitem">
                                <i class="fas fa-user-clock"></i> Pending Approvals
                            </a>
                        <?php elseif ($isTutor): ?>
                            <a class="dh-dd-item" href="<?php echo $base; ?>/tutor/" role="menuitem">
                                <i class="fas fa-chalkboard-teacher"></i> Tutor Portal
                            </a>
                        <?php else: ?>
                            <a class="dh-dd-item" href="<?php echo $base; ?>/profile/client/" role="menuitem">
                                <i class="fas fa-user-graduate"></i> My Dashboard
                            </a>
                        <?php endif; ?>
                        <a class="dh-dd-item" href="<?php echo $base; ?>/profile/" role="menuitem">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                        <hr class="dh-dd-divider">
                        <a class="dh-dd-item dh-dd-danger" href="<?php echo $base; ?>/auth/logout.php" role="menuitem">
                            <i class="fas fa-arrow-right-from-bracket"></i> Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?php echo $base; ?>/#register" class="dh-btn-apply">Apply</a>
                <a href="<?php echo $base; ?>/auth/login.php" class="dh-btn-login">
                    <i class="far fa-user" aria-hidden="true"></i> Login
                </a>
            <?php endif; ?>

            <button class="dh-hamburger" id="dhHamburger"
                    aria-label="Open menu" aria-expanded="false" aria-controls="dhDrawer">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<div class="dh-drawer-backdrop" id="dhBackdrop" aria-hidden="true"></div>

<aside class="dh-drawer" id="dhDrawer" aria-hidden="true" aria-label="Mobile navigation">
    <div class="dh-drawer-head">
        <span class="dh-drawer-brand">Dazu <strong>hub</strong></span>
        <button class="dh-drawer-close" id="dhDrawerClose" aria-label="Close menu">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <nav class="dh-drawer-body" aria-label="Mobile navigation">
        <a class="dh-drawer-link" href="<?php echo $base; ?>/" style="--di:0">Home</a>
        <a class="dh-drawer-link" href="<?php echo $base; ?>/#courses" style="--di:1">Courses</a>
        <a class="dh-drawer-link" href="<?php echo $base; ?>/about/" style="--di:2">About</a>
        <a class="dh-drawer-link" href="<?php echo $base; ?>/projects/" style="--di:3">Projects</a>
        <a class="dh-drawer-link" href="<?php echo $base; ?>/blogs/" style="--di:4">Blogs</a>
        <a class="dh-drawer-link" href="<?php echo $base; ?>/contact/" style="--di:5">Contacts</a>
        <hr class="dh-drawer-divider" style="--di:6">
        <?php if ($isLoggedIn): ?>
            <?php if ($isAdmin): ?>
                <a class="dh-drawer-link" href="<?php echo $base; ?>/admin/dashboard.php" style="--di:7">Admin Dashboard</a>
            <?php else: ?>
                <a class="dh-drawer-link" href="<?php echo $base; ?>/profile/client/" style="--di:7">My Dashboard</a>
            <?php endif; ?>
            <a class="dh-drawer-link dh-drawer-link-danger" href="<?php echo $base; ?>/auth/logout.php" style="--di:8">Logout</a>
        <?php else: ?>
            <a class="dh-drawer-link" href="<?php echo $base; ?>/auth/login.php" style="--di:7">Login</a>
        <?php endif; ?>
    </nav>
    <?php if (!$isLoggedIn): ?>
    <div class="dh-drawer-foot">
        <a href="<?php echo $base; ?>/#register" class="dh-btn-apply dh-btn-apply--block">Apply</a>
    </div>
    <?php endif; ?>
</aside>

<style>
.dh-nav {
    position: fixed; top: 0; left: 0; right: 0; z-index: 900;
    height: 78px;
    background: #fff;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    box-shadow: 0 1px 0 rgba(0,0,0,0.04);
    transition: box-shadow .25s ease, height .25s ease;
}
.dh-nav.scrolled {
    height: 72px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
}
.dh-nav-inner {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    height: 100%;
    gap: 24px;
}

/* Logo */
.dh-brand {
    display: flex; align-items: center; gap: 12px;
    text-decoration: none; flex-shrink: 0;
}
.dh-brand-icon { display: flex; line-height: 0; }
.dh-brand-text { display: flex; flex-direction: column; line-height: 1.1; }
.dh-brand-title {
    font-family: var(--font-display);
    font-size: 1.35rem; font-weight: 800;
    color: #0f172a; letter-spacing: -0.02em;
}
.dh-brand-hub { color: #00A859; }
.dh-brand-sub {
    font-family: var(--font-body);
    font-size: 0.68rem; font-weight: 500;
    color: #94a3b8; letter-spacing: 0.01em;
    margin-top: 2px;
}

/* Nav links */
.dh-nav-links {
    display: flex; align-items: center; justify-content: center;
    gap: 6px;
}
.dh-nav-link {
    font-family: var(--font-body);
    font-size: 0.72rem; font-weight: 600;
    letter-spacing: 0.1em; text-transform: uppercase;
    color: #334155;
    padding: 10px 14px;
    text-decoration: none;
    position: relative;
    transition: color .2s ease;
}
.dh-nav-link:hover { color: #00A859; }
.dh-nav-link.is-active { color: #00A859; }
.dh-nav-link.is-active::after {
    content: '';
    position: absolute; left: 14px; right: 14px; bottom: 4px;
    height: 2px; background: #00A859; border-radius: 2px;
}

/* Actions */
.dh-nav-actions {
    display: flex; align-items: center; gap: 12px;
    flex-shrink: 0;
}
.dh-btn-apply {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 11px 26px;
    background: #00A859;
    color: #fff !important;
    font-family: var(--font-body);
    font-size: 0.72rem; font-weight: 700;
    letter-spacing: 0.12em; text-transform: uppercase;
    border-radius: 8px;
    text-decoration: none;
    border: none;
    box-shadow: 0 4px 14px rgba(0,168,89,0.28);
    transition: background .2s ease, transform .15s ease, box-shadow .2s ease;
}
.dh-btn-apply:hover {
    background: #008f4c;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(0,168,89,0.35);
}
.dh-btn-apply--block { width: 100%; }
.dh-btn-login {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 22px;
    background: #fff;
    color: #334155 !important;
    font-family: var(--font-body);
    font-size: 0.88rem; font-weight: 500;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    text-decoration: none;
    transition: border-color .2s ease, color .2s ease;
}
.dh-btn-login:hover {
    border-color: #00A859;
    color: #00A859 !important;
}
.dh-btn-login i { font-size: 0.95rem; }

/* User menu */
.dh-user-menu { position: relative; }
.dh-user-trigger {
    display: flex; align-items: center; gap: 8px;
    padding: 6px 12px 6px 6px;
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 999px; cursor: pointer;
    font-family: var(--font-body); font-size: 0.875rem; color: #334155;
}
.dh-user-avatar {
    width: 30px; height: 30px; border-radius: 50%;
    background: #00A859; color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.78rem; font-weight: 700;
}
.dh-user-name {
    max-width: 90px; overflow: hidden;
    text-overflow: ellipsis; white-space: nowrap; font-weight: 500;
}
.dh-user-trigger .fa-chevron-down { font-size: 0.6rem; color: #94a3b8; transition: transform .2s; }
.dh-user-trigger[aria-expanded="true"] .fa-chevron-down { transform: rotate(180deg); }
.dh-user-dropdown {
    position: absolute; top: calc(100% + 8px); right: 0;
    min-width: 210px; background: #fff;
    border: 1px solid #e2e8f0; border-radius: 12px;
    box-shadow: 0 16px 40px rgba(0,0,0,0.12);
    padding: 6px; opacity: 0; pointer-events: none;
    transform: translateY(-6px); transition: opacity .2s, transform .2s;
}
.dh-user-dropdown.open { opacity: 1; pointer-events: all; transform: none; }
.dh-dd-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 12px; border-radius: 8px;
    font-size: 0.875rem; color: #475569; text-decoration: none;
}
.dh-dd-item:hover { background: #ecfdf3; color: #00A859; }
.dh-dd-danger { color: #dc2626; }
.dh-dd-danger:hover { background: #fef2f2; color: #dc2626; }
.dh-dd-divider { border: none; border-top: 1px solid #e2e8f0; margin: 5px 0; }

/* Hamburger */
.dh-hamburger {
    display: none;
    flex-direction: column; gap: 5px;
    align-items: center; justify-content: center;
    width: 40px; height: 40px;
    background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 8px; cursor: pointer;
}
.dh-hamburger span {
    display: block; width: 18px; height: 2px;
    background: #0f172a; border-radius: 2px;
    transition: transform .3s, opacity .3s;
}
.dh-hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.dh-hamburger.open span:nth-child(2) { opacity: 0; }
.dh-hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* Drawer */
.dh-drawer-backdrop {
    position: fixed; inset: 0; z-index: 1000;
    background: rgba(15,23,42,0.45);
    opacity: 0; pointer-events: none; transition: opacity .3s;
}
.dh-drawer-backdrop.open { opacity: 1; pointer-events: all; }
.dh-drawer {
    position: fixed; top: 0; right: 0; bottom: 0;
    width: min(320px, 88vw); z-index: 1001;
    background: #fff; border-left: 1px solid #e2e8f0;
    box-shadow: -8px 0 40px rgba(0,0,0,0.12);
    display: flex; flex-direction: column;
    transform: translateX(100%);
    transition: transform .35s cubic-bezier(.32,0,.15,1);
}
.dh-drawer.open { transform: none; }
.dh-drawer-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 20px; border-bottom: 1px solid #e2e8f0;
}
.dh-drawer-brand {
    font-family: var(--font-display); font-size: 1.1rem; font-weight: 800; color: #0f172a;
}
.dh-drawer-brand strong { color: #00A859; }
.dh-drawer-close {
    width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
    color: #64748b; cursor: pointer;
}
.dh-drawer-body { flex: 1; padding: 8px 0; overflow-y: auto; }
.dh-drawer-link {
    display: block; padding: 14px 22px;
    font-family: var(--font-display); font-size: 0.95rem; font-weight: 600;
    color: #0f172a; text-decoration: none;
    border-bottom: 1px solid #f1f5f9;
    text-transform: uppercase; letter-spacing: 0.06em; font-size: 0.8rem;
}
.dh-drawer-link:hover { color: #00A859; background: #f0fdf4; }
.dh-drawer-link-danger { color: #dc2626; }
.dh-drawer-divider { border: none; border-top: 1px solid #e2e8f0; margin: 8px 0; }
.dh-drawer-foot { padding: 20px; border-top: 1px solid #e2e8f0; }

@media (max-width: 1024px) {
    .dh-nav-links { display: none; }
    .dh-nav-actions .dh-btn-apply,
    .dh-nav-actions .dh-btn-login { display: none; }
    .dh-hamburger { display: flex; }
    .dh-nav-inner { grid-template-columns: 1fr auto; }
}
@media (max-width: 480px) {
    .dh-nav { height: 68px; }
    .dh-brand-title { font-size: 1.15rem; }
    .dh-brand-sub { font-size: 0.62rem; }
}
</style>

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
        drawer.classList.add('open');
        backdrop.classList.add('open');
        hamburger.classList.add('open');
        hamburger.setAttribute('aria-expanded', 'true');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeDrawer(){
        drawer.classList.remove('open');
        backdrop.classList.remove('open');
        hamburger.classList.remove('open');
        hamburger.setAttribute('aria-expanded', 'false');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (hamburger) hamburger.addEventListener('click', openDrawer);
    if (closeBtn)  closeBtn.addEventListener('click', closeDrawer);
    if (backdrop)  backdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && drawer && drawer.classList.contains('open')) closeDrawer();
    });
    if (drawer) {
        drawer.querySelectorAll('a').forEach(function(a){
            a.addEventListener('click', function(){ setTimeout(closeDrawer, 120); });
        });
    }

    var trigger  = document.getElementById('dhUserTrigger');
    var dropdown = document.getElementById('dhUserDropdown');
    if (trigger && dropdown) {
        trigger.addEventListener('click', function(e){
            e.stopPropagation();
            var open = dropdown.classList.toggle('open');
            trigger.setAttribute('aria-expanded', String(open));
        });
        document.addEventListener('click', function(e){
            if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    }
})();
</script>
