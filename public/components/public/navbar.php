<?php
// public/components/public/navbar.php — Dazu Hub Institute
if (session_status() === PHP_SESSION_NONE) session_start();

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

$logoPath = '/Registration-System/public/assets/images/logo/logo.png';
$isLoggedIn  = !empty($_SESSION['logged_in']);
$isAdmin     = $isLoggedIn && isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','superadmin'], true);
$isTutor     = $isLoggedIn && isset($_SESSION['role']) && $_SESSION['role'] === 'tutor';
$isStudent   = $isLoggedIn && isset($_SESSION['role']) && $_SESSION['role'] === 'student';
$username    = htmlspecialchars($_SESSION['username'] ?? '');
$initial     = strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1));
?>

<header class="dh-nav" id="dhNav" role="banner">
    <div class="dh-nav-inner dh-container">

        <!-- Logo / Brand -->
        <a class="dh-brand" href="/Registration-System/public/" aria-label="Dazu Hub Institute — Home">
            <span class="dh-brand-logo-wrap">
                <img src="<?php echo $logoPath; ?>"
                     alt="Dazu Hub Institute"
                     class="dh-brand-logo"
                     width="120" height="48"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <!-- Fallback mark if logo missing -->
                <span class="dh-brand-fallback" style="display:none;">
                    <svg width="38" height="38" viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="38" height="38" rx="8" fill="var(--dh-green)"/>
                        <text x="19" y="26" text-anchor="middle" font-family="Space Grotesk,sans-serif" font-size="17" font-weight="800" fill="#fff">&lt;/&gt;</text>
                    </svg>
                    <span class="dh-brand-name">Dazu <strong>Hub</strong></span>
                </span>
            </span>
        </a>

        <!-- Desktop navigation -->
        <nav class="dh-nav-links" aria-label="Primary navigation">
            <a href="/Registration-System/public/" class="dh-nav-link <?php echo dhNavActive('home'); ?>">Home</a>
            <a href="/Registration-System/public/#courses" class="dh-nav-link">Courses</a>
            <a href="/Registration-System/public/#how-it-works" class="dh-nav-link">How It Works</a>
            <a href="/Registration-System/public/#register" class="dh-nav-link">Apply</a>
        </nav>

        <!-- Right actions -->
        <div class="dh-nav-actions">

            <!-- Dark/light mode toggle -->
            <button class="dh-theme-btn" id="dhThemeToggle" aria-label="Toggle dark mode" title="Toggle dark / light mode">
                <i class="fas fa-moon dh-icon-moon" aria-hidden="true"></i>
                <i class="fas fa-sun  dh-icon-sun"  aria-hidden="true"></i>
            </button>

            <!-- Auth state -->
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
                            <a class="dh-dd-item" href="/Registration-System/public/admin/dashboard.php" role="menuitem">
                                <i class="fas fa-gauge-high"></i> Admin Dashboard
                            </a>
                            <a class="dh-dd-item" href="/Registration-System/public/admin/registrations/index.php?status=pending" role="menuitem">
                                <i class="fas fa-user-clock"></i> Pending Approvals
                            </a>
                        <?php elseif ($isTutor): ?>
                            <a class="dh-dd-item" href="/Registration-System/public/tutor/dashboard/index.php" role="menuitem">
                                <i class="fas fa-chalkboard-teacher"></i> Tutor Portal
                            </a>
                        <?php else: ?>
                            <a class="dh-dd-item" href="/Registration-System/public/profile/client/" role="menuitem">
                                <i class="fas fa-user-graduate"></i> My Dashboard
                            </a>
                        <?php endif; ?>
                        <a class="dh-dd-item" href="/Registration-System/public/profile/" role="menuitem">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                        <hr class="dh-dd-divider">
                        <a class="dh-dd-item dh-dd-danger" href="/Registration-System/public/auth/logout.php" role="menuitem">
                            <i class="fas fa-arrow-right-from-bracket"></i> Logout
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/Registration-System/public/auth/login.php" class="dh-nav-link dh-nav-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
                <a href="/Registration-System/public/#register" class="dh-btn dh-btn-primary">
                    Apply Now
                </a>
            <?php endif; ?>

            <!-- Hamburger -->
            <button class="dh-hamburger" id="dhHamburger"
                    aria-label="Open menu" aria-expanded="false" aria-controls="dhDrawer">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- Mobile drawer overlay -->
<div class="dh-drawer-backdrop" id="dhBackdrop" aria-hidden="true"></div>

<!-- Mobile drawer -->
<aside class="dh-drawer" id="dhDrawer" aria-hidden="true" aria-label="Mobile navigation">
    <div class="dh-drawer-head">
        <img src="<?php echo $logoPath; ?>" alt="Dazu Hub" height="36"
             onerror="this.style.display='none'">
        <button class="dh-drawer-close" id="dhDrawerClose" aria-label="Close menu">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <nav class="dh-drawer-body" aria-label="Mobile navigation">
        <a class="dh-drawer-link" href="/Registration-System/public/" style="--di:0">
            <i class="fas fa-house"></i> Home
        </a>
        <a class="dh-drawer-link" href="/Registration-System/public/#courses" style="--di:1">
            <i class="fas fa-layer-group"></i> Courses
        </a>
        <a class="dh-drawer-link" href="/Registration-System/public/#how-it-works" style="--di:2">
            <i class="fas fa-list-ol"></i> How It Works
        </a>
        <a class="dh-drawer-link" href="/Registration-System/public/#register" style="--di:3">
            <i class="fas fa-pen-to-square"></i> Apply Now
        </a>
        <hr class="dh-drawer-divider" style="--di:4">
        <?php if ($isLoggedIn): ?>
            <?php if ($isAdmin): ?>
                <a class="dh-drawer-link" href="/Registration-System/public/admin/dashboard.php" style="--di:5">
                    <i class="fas fa-gauge-high"></i> Admin Dashboard
                </a>
            <?php else: ?>
                <a class="dh-drawer-link" href="/Registration-System/public/profile/client/" style="--di:5">
                    <i class="fas fa-user-graduate"></i> My Dashboard
                </a>
            <?php endif; ?>
            <a class="dh-drawer-link dh-drawer-link-danger" href="/Registration-System/public/auth/logout.php" style="--di:6">
                <i class="fas fa-arrow-right-from-bracket"></i> Logout
            </a>
        <?php else: ?>
            <a class="dh-drawer-link" href="/Registration-System/public/auth/login.php" style="--di:5">
                <i class="fas fa-sign-in-alt"></i> Login / Student Portal
            </a>
        <?php endif; ?>
    </nav>
    <div class="dh-drawer-foot">
        <button class="dh-theme-btn dh-drawer-theme-btn" data-drawer-theme="true"
                aria-label="Toggle dark mode">
            <i class="fas fa-moon dh-icon-moon"></i>
            <i class="fas fa-sun  dh-icon-sun"></i>
            <span class="dh-theme-label">Toggle dark mode</span>
        </button>
        <?php if (!$isLoggedIn): ?>
            <a href="/Registration-System/public/#register" class="dh-btn dh-btn-primary" style="width:100%;justify-content:center;">
                <i class="fas fa-paper-plane"></i> Apply Now
            </a>
        <?php endif; ?>
    </div>
</aside>

<style>
/* ============================================================
   DAZU HUB — NAVBAR
   Uses CSS variables from layout.php
============================================================ */
.dh-nav {
    position: fixed; top: 0; left: 0; right: 0; z-index: 900;
    height: 70px;
    background: var(--nav-bg);
    border-bottom: 1px solid var(--nav-border);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    transition: height var(--trans), box-shadow var(--trans), background var(--trans);
}

.dh-nav.scrolled {
    height: 62px;
    box-shadow: var(--nav-shadow);
}

.dh-nav-inner {
    display: flex;
    align-items: center;
    height: 100%;
    gap: 28px;
}

/* Brand / Logo */
.dh-brand {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    text-decoration: none;
}

.dh-brand-logo-wrap {
    display: flex;
    align-items: center;
}

.dh-brand-logo {
    height: 44px;
    width: auto;
    object-fit: contain;
    /* In dark mode the logo has a white bg — add subtle card treatment */
    transition: filter var(--trans);
}

[data-theme="dark"] .dh-brand-logo {
    /* Slightly brighten; the white bg blends better against dark nav */
    border-radius: var(--r-sm);
    padding: 4px 8px;
    background: rgba(255,255,255,0.06);
}

.dh-brand-fallback {
    display: flex; align-items: center; gap: 10px;
}
.dh-brand-name {
    font-family: var(--font-display);
    font-size: 1.2rem;
    color: var(--text);
}
.dh-brand-name strong { color: var(--dh-green); }

/* Desktop nav links */
.dh-nav-links {
    display: flex;
    align-items: center;
    gap: 2px;
    margin-left: auto;
}

.dh-nav-link {
    font-family: var(--font-body);
    font-size: 0.875rem; font-weight: 500;
    color: var(--text-sub);
    padding: 8px 14px;
    border-radius: var(--r-sm);
    text-decoration: none;
    position: relative;
    white-space: nowrap;
    transition: color var(--trans), background var(--trans);
}
.dh-nav-link:hover { color: var(--dh-green); background: var(--dh-green-pale); }
[data-theme="dark"] .dh-nav-link:hover { background: rgba(46,125,50,0.12); }
.dh-nav-link.is-active { color: var(--dh-green); font-weight: 600; }
.dh-nav-link.is-active::after {
    content: '';
    position: absolute; bottom: 4px; left: 14px; right: 14px;
    height: 2px; background: var(--dh-green); border-radius: 2px;
}

.dh-nav-login { display: flex; align-items: center; gap: 6px; }
.dh-nav-login i { font-size: 0.8rem; }

/* Right action cluster */
.dh-nav-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

/* Theme toggle button */
.dh-theme-btn {
    display: flex; align-items: center; justify-content: center;
    width: 38px; height: 38px;
    border-radius: var(--r-sm);
    background: var(--bg-alt);
    border: 1px solid var(--border);
    color: var(--text-muted);
    cursor: pointer;
    position: relative;
}
.dh-theme-btn:hover { color: var(--dh-green); border-color: var(--dh-green); }

/* Show moon in light, sun in dark */
.dh-icon-sun  { display: none; }
.dh-icon-moon { display: block; }
[data-theme="dark"] .dh-icon-sun  { display: block; color: #F59E0B; }
[data-theme="dark"] .dh-icon-moon { display: none; }

/* User dropdown */
.dh-user-menu { position: relative; }
.dh-user-trigger {
    display: flex; align-items: center; gap: 8px;
    padding: 5px 12px 5px 5px;
    background: var(--bg-alt);
    border: 1px solid var(--border);
    border-radius: var(--r-full);
    color: var(--text-sub);
    font-family: var(--font-body); font-size: 0.875rem;
    cursor: pointer;
}
.dh-user-trigger:hover { border-color: var(--dh-green); }
.dh-user-avatar {
    width: 28px; height: 28px; border-radius: 50%;
    background: var(--dh-green); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.78rem; font-weight: 700;
}
.dh-user-name {
    max-width: 90px; overflow: hidden;
    text-overflow: ellipsis; white-space: nowrap;
    font-weight: 500; color: var(--text);
}
.dh-user-trigger .fa-chevron-down {
    font-size: 0.6rem; color: var(--text-muted);
    transition: transform var(--trans);
}
.dh-user-trigger[aria-expanded="true"] .fa-chevron-down { transform: rotate(180deg); }

.dh-user-dropdown {
    position: absolute; top: calc(100% + 8px); right: 0;
    min-width: 210px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--r-md);
    box-shadow: var(--shadow-xl);
    padding: 6px;
    opacity: 0; pointer-events: none;
    transform: translateY(-6px) scale(0.97);
    transform-origin: top right;
    transition: opacity var(--trans), transform var(--trans);
}
.dh-user-dropdown.open {
    opacity: 1; pointer-events: all;
    transform: none;
}
.dh-dd-item {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 12px;
    border-radius: var(--r-xs);
    font-size: 0.875rem; color: var(--text-sub);
    text-decoration: none;
}
.dh-dd-item i { width: 16px; text-align: center; }
.dh-dd-item:hover { background: var(--dh-green-pale); color: var(--dh-green); }
[data-theme="dark"] .dh-dd-item:hover { background: rgba(46,125,50,0.12); }
.dh-dd-danger { color: #DC2626; }
.dh-dd-danger:hover { background: rgba(220,38,38,0.08); color: #DC2626; }
.dh-dd-divider { border: none; border-top: 1px solid var(--border); margin: 5px 0; }

/* Apply Now CTA button in nav (reuses layout.php .dh-btn-primary) */
.dh-nav .dh-btn { padding: 9px 18px; font-size: 0.85rem; }

/* Hamburger */
.dh-hamburger {
    display: none;
    flex-direction: column; gap: 5px;
    align-items: center; justify-content: center;
    width: 38px; height: 38px;
    background: var(--bg-alt);
    border: 1px solid var(--border);
    border-radius: var(--r-sm);
    cursor: pointer;
}
.dh-hamburger span {
    display: block; width: 18px; height: 2px;
    background: var(--text);
    border-radius: 2px;
    transition: transform 0.3s ease, opacity 0.3s ease;
}
.dh-hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.dh-hamburger.open span:nth-child(2) { opacity: 0; }
.dh-hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* ── DRAWER ───────────────────────────────────────────────── */
.dh-drawer-backdrop {
    position: fixed; inset: 0; z-index: 1000;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
    opacity: 0; pointer-events: none;
    transition: opacity var(--trans-slow);
}
.dh-drawer-backdrop.open { opacity: 1; pointer-events: all; }

.dh-drawer {
    position: fixed; top: 0; right: 0; bottom: 0;
    width: min(320px, 85vw); z-index: 1001;
    background: var(--bg);
    border-left: 1px solid var(--border);
    box-shadow: -8px 0 40px rgba(0,0,0,0.2);
    display: flex; flex-direction: column;
    transform: translateX(100%);
    transition: transform var(--trans-slow) cubic-bezier(0.32, 0, 0.15, 1);
    overflow-y: auto;
}
.dh-drawer.open { transform: none; }

.dh-drawer-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.dh-drawer-head img { height: 34px; width: auto; }

[data-theme="dark"] .dh-drawer-head img {
    background: rgba(255,255,255,0.06);
    border-radius: var(--r-sm);
    padding: 4px 8px;
}

.dh-drawer-close {
    width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    background: var(--bg-alt); border: 1px solid var(--border);
    border-radius: var(--r-sm); color: var(--text-muted);
    cursor: pointer; font-size: 0.9rem;
}
.dh-drawer-close:hover { background: var(--dh-green); color: #fff; border-color: var(--dh-green); }

.dh-drawer-body { flex: 1; padding: 12px 0; }
.dh-drawer-link {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 22px;
    font-family: var(--font-display);
    font-size: 1rem; font-weight: 600;
    color: var(--text);
    border-bottom: 1px solid var(--border);
    text-decoration: none;
    opacity: 0; transform: translateX(20px);
    transition: color var(--trans), background var(--trans),
                opacity 0.3s ease calc(var(--di, 0) * 50ms),
                transform 0.3s ease calc(var(--di, 0) * 50ms);
}
.dh-drawer.open .dh-drawer-link { opacity: 1; transform: none; }
.dh-drawer-link i { width: 20px; text-align: center; color: var(--dh-green); }
.dh-drawer-link:hover { color: var(--dh-green); background: var(--dh-green-pale); }
[data-theme="dark"] .dh-drawer-link:hover { background: rgba(46,125,50,0.1); }
.dh-drawer-link-danger { color: #DC2626; }
.dh-drawer-link-danger i { color: #DC2626; }
.dh-drawer-link-danger:hover { background: rgba(220,38,38,0.06); }

.dh-drawer-divider {
    border: none; border-top: 1px solid var(--border);
    margin: 6px 0;
    opacity: 0; transition: opacity 0.3s ease calc(var(--di, 0) * 50ms);
}
.dh-drawer.open .dh-drawer-divider { opacity: 1; }

.dh-drawer-foot {
    padding: 20px;
    border-top: 1px solid var(--border);
    display: flex; flex-direction: column; gap: 12px;
    flex-shrink: 0;
}
.dh-drawer-theme-btn {
    width: 100%; height: auto;
    padding: 11px 16px;
    justify-content: flex-start;
    gap: 12px;
    background: var(--bg-alt);
}
.dh-theme-label { font-family: var(--font-body); font-size: 0.9rem; color: var(--text); }

/* ── RESPONSIVE ───────────────────────────────────────────── */
@media (max-width: 900px) {
    .dh-nav-links { display: none; }
    .dh-nav-actions .dh-btn { display: none; }
    .dh-nav-actions .dh-nav-login { display: none; }
    .dh-hamburger { display: flex; }
}

@media (max-width: 480px) {
    .dh-nav { height: 62px; }
    .dh-nav.scrolled { height: 56px; }
    .dh-brand-logo { height: 36px; }
    body { padding-top: 62px; }
}
</style>

<script>
(function(){
    'use strict';

    /* ── Scroll shrink ── */
    var nav = document.getElementById('dhNav');
    window.addEventListener('scroll', function(){
        if (nav) nav.classList.toggle('scrolled', window.scrollY > 40);
    }, { passive: true });

    /* ── Dark mode ── */
    function applyTheme(isDark){
        document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
        try { localStorage.setItem('dh-theme', isDark ? 'dark' : 'light'); } catch(e){}
    }

    function isCurrentlyDark(){
        return document.documentElement.getAttribute('data-theme') === 'dark';
    }

    document.querySelectorAll('#dhThemeToggle, [data-drawer-theme]').forEach(function(btn){
        btn.addEventListener('click', function(){
            applyTheme(!isCurrentlyDark());
        });
    });

    /* ── Mobile drawer ── */
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
        backdrop.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeDrawer(){
        drawer.classList.remove('open');
        backdrop.classList.remove('open');
        hamburger.classList.remove('open');
        hamburger.setAttribute('aria-expanded', 'false');
        drawer.setAttribute('aria-hidden', 'true');
        backdrop.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        hamburger.focus();
    }

    if (hamburger) hamburger.addEventListener('click', openDrawer);
    if (closeBtn)  closeBtn.addEventListener('click', closeDrawer);
    if (backdrop)  backdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && drawer && drawer.classList.contains('open')) closeDrawer();
    });
    if (drawer){
        drawer.querySelectorAll('a').forEach(function(a){
            a.addEventListener('click', function(){ setTimeout(closeDrawer, 120); });
        });
    }

    /* ── User dropdown ── */
    var trigger  = document.getElementById('dhUserTrigger');
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