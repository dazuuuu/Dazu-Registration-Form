<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../../app/v1/helpers/free_courses.php';
require_once __DIR__ . '/../../../app/v1/helpers/site_contact.php';

$menuItems = [
    ['name' => 'HOME', 'href' => url('/'), 'key' => 'home'],
    ['name' => 'COURSES', 'href' => url('courses'), 'key' => 'courses'],
    ['name' => 'FREE COURSES', 'href' => url('courses/free.php'), 'key' => 'free-courses'],
    ['name' => 'ABOUT', 'href' => url('about'), 'key' => 'about'],
    ['name' => 'PROJECTS', 'href' => url('projects'), 'key' => 'projects'],
    ['name' => 'BLOGS', 'href' => url('blogs/index.php'), 'key' => 'blog'],
    ['name' => 'CONTACTS', 'href' => url('contact'), 'key' => 'contact'],
];

// FREE COURSES stays hidden unless admin left the section open AND at least one
// public free course exists. An empty listing never gets a nav item.
if (!dazu_free_courses_nav_visible()) {
    $menuItems = array_values(array_filter($menuItems, static fn($item) => $item['key'] !== 'free-courses'));
}

$isLoggedIn = !empty($_SESSION['logged_in']);
$role = $_SESSION['role'] ?? '';
require_once __DIR__ . '/../../../app/v1/helpers/brand.php';
$contact = site_contact_settings();
$navSiteName = trim((string) ($contact['site_name'] ?? '')) ?: 'Dazu Training Institute';
$navCategories = function_exists('dazu_navbar_categories') ? dazu_navbar_categories() : [];
?>
<style>
/* Scoped styles for Dazu Navbar & Courses Dropdown */
.dazu-nav-dropdown {
  position: relative;
  display: inline-flex;
  align-items: center;
  height: 100%;
}
.dazu-nav-dropdown .dazu-dropdown-caret {
  transition: transform 0.2s ease, color 0.2s ease;
}
.dazu-nav-dropdown:hover .dazu-dropdown-caret,
.dazu-nav-dropdown:focus-within .dazu-dropdown-caret {
  transform: rotate(180deg);
  color: #22C55E;
}
.dazu-dropdown-menu {
  display: none;
  position: absolute;
  top: 100%;
  left: 0;
  width: 290px;
  padding-top: 10px;
  z-index: 100;
  pointer-events: none;
  opacity: 0;
  transform: translateY(4px);
  transition: opacity 0.18s cubic-bezier(0.16, 1, 0.3, 1), transform 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}
.dazu-dropdown-menu::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 14px;
}
.dazu-nav-dropdown:hover .dazu-dropdown-menu,
.dazu-nav-dropdown:focus-within .dazu-dropdown-menu,
.dazu-nav-dropdown.is-open .dazu-dropdown-menu {
  display: block;
  pointer-events: auto;
  opacity: 1;
  transform: translateY(0);
}
.dazu-dropdown-card {
  background: #ffffff;
  border-radius: 14px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.12), 0 8px 10px -6px rgba(15, 23, 42, 0.08);
  overflow: hidden;
  padding: 6px 0;
}
.dazu-dropdown-item-all {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 16px;
  text-decoration: none;
  border-bottom: 1px solid #f1f5f9;
  background: #ffffff;
  transition: background-color 0.15s ease;
}
.dazu-dropdown-item-all:hover {
  background-color: #f0fdf4;
}
.dazu-dropdown-all-icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: #dcfce7;
  color: #15a24a;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.dazu-dropdown-header-label {
  padding: 8px 16px 4px 16px;
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #94a3b8;
}
.dazu-dropdown-scroll {
  max-height: 280px;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding: 2px 0;
}
.dazu-dropdown-scroll::-webkit-scrollbar {
  width: 4px;
}
.dazu-dropdown-scroll::-webkit-scrollbar-track {
  background: #f8fafc;
}
.dazu-dropdown-scroll::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}
.dazu-dropdown-school-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 16px;
  font-size: 12px;
  font-weight: 700;
  color: #334155;
  text-decoration: none;
  transition: background-color 0.15s ease, color 0.15s ease, padding-left 0.15s ease;
}
.dazu-dropdown-school-item:hover {
  background-color: #f0fdf4;
  color: #15a24a;
  padding-left: 20px;
}
.dazu-dropdown-school-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #cbd5e1;
  flex-shrink: 0;
  transition: background-color 0.15s ease, transform 0.15s ease;
}
.dazu-dropdown-school-item:hover .dazu-dropdown-school-dot {
  background-color: #15a24a;
  transform: scale(1.3);
}
.dazu-dropdown-school-text {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>

<nav class="sticky top-0 z-50 w-full bg-white border-b border-slate-100 font-sans" id="dazu-main-navbar">
  <!-- Top bar with contact -->
  <div class="bg-[#0F172A] border-b border-slate-800 py-2">
    <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 flex items-center justify-end gap-2 text-white text-[11px] font-bold">
      <svg class="h-3.5 w-3.5 text-[#15A24A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
      <span class="text-slate-300">Call / WhatsApp:</span>
      <a href="tel:<?php echo htmlspecialchars($contact['contact_phone_tel']); ?>" class="text-[#15A24A] hover:text-[#128a3e] hover:underline font-extrabold"><?php echo htmlspecialchars($contact['contact_phone']); ?></a>
    </div>
  </div>

  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="flex items-center justify-between h-20">
      <!-- Logo -->
      <a href="<?php echo url('/'); ?>" class="flex items-center gap-2 group focus:outline-none shrink-0" id="nav-logo">
        <img src="<?php echo htmlspecialchars(brand_url('logo')); ?>" alt="<?php echo htmlspecialchars($navSiteName); ?>" class="h-12 sm:h-14 w-auto shrink-0 transition-transform duration-300 group-hover:scale-105">
      </a>

      <!-- Desktop Navigation Menu -->
      <div class="hidden lg:flex items-center gap-6 xl:gap-8 h-full">
        <?php foreach ($menuItems as $item): ?>
          <?php $active = dazu_nav_active($item['key']); ?>
          <?php if ($item['key'] === 'courses'): ?>
            <div class="dazu-nav-dropdown" id="nav-courses-dropdown">
              <a href="<?php echo $item['href']; ?>" class="relative py-2 text-xs font-bold tracking-widest transition-colors hover:text-[#22C55E] flex items-center gap-1.5 <?php echo $active ? 'text-slate-900 font-extrabold' : 'text-slate-700'; ?>" aria-haspopup="true" aria-expanded="false">
                <span><?php echo htmlspecialchars($item['name']); ?></span>
                <svg class="dazu-dropdown-caret w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                <?php if ($active): ?><span class="absolute bottom-0 left-0 right-0 h-[2px] bg-[#22C55E] rounded-full"></span><?php endif; ?>
              </a>

              <!-- Courses Dropdown Panel -->
              <div class="dazu-dropdown-menu" id="nav-courses-menu" role="menu">
                <div class="dazu-dropdown-card">
                  <a href="<?php echo url('courses'); ?>" class="dazu-dropdown-item-all">
                    <span class="dazu-dropdown-all-icon">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    </span>
                    <div>
                      <div class="text-xs font-extrabold text-slate-900 leading-tight">All Courses</div>
                      <div class="text-[10px] text-slate-500 font-medium leading-tight">Browse complete catalog</div>
                    </div>
                  </a>

                  <?php if (!empty($navCategories)): ?>
                    <div class="dazu-dropdown-header-label">Schools &amp; Faculties</div>
                    <div class="dazu-dropdown-scroll">
                      <?php foreach ($navCategories as $navCat): ?>
                        <?php $schoolName = function_exists('dazu_format_school_name') ? dazu_format_school_name($navCat['name']) : 'School of ' . $navCat['name']; ?>
                        <a href="<?php echo url('courses/category.php?slug=' . rawurlencode($navCat['slug'] ?? '')); ?>" class="dazu-dropdown-school-item" role="menuitem">
                          <?php if (!empty($navCat['icon_path'])): ?>
                            <img src="<?php echo htmlspecialchars(settings_asset_url($navCat['icon_path'])); ?>" alt="" class="w-3.5 h-3.5 object-contain shrink-0">
                          <?php else: ?>
                            <span class="dazu-dropdown-school-dot"></span>
                          <?php endif; ?>
                          <span class="dazu-dropdown-school-text"><?php echo htmlspecialchars($schoolName); ?></span>
                        </a>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php else: ?>
            <a href="<?php echo $item['href']; ?>" class="relative py-2 text-xs font-bold tracking-widest transition-colors hover:text-[#22C55E] <?php echo $active ? 'text-slate-900 font-extrabold' : 'text-slate-700'; ?>">
              <?php echo htmlspecialchars($item['name']); ?>
              <?php if ($active): ?><span class="absolute bottom-0 left-0 right-0 h-[2px] bg-[#22C55E] rounded-full"></span><?php endif; ?>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <!-- Right Actions (Desktop) -->
      <div class="hidden lg:flex items-center gap-3 shrink-0">
        <a href="tel:<?php echo htmlspecialchars($contact['contact_phone_tel']); ?>" class="text-xs font-extrabold text-slate-700 hover:text-[#15A24A] flex items-center gap-1.5 whitespace-nowrap"><?php echo htmlspecialchars($contact['contact_phone']); ?></a>
        <a href="<?php echo url('apply/index.php'); ?>" class="px-6 py-2.5 text-xs font-extrabold tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-md uppercase transition-colors shrink-0">APPLY</a>
        <?php if ($isLoggedIn && in_array($role, ['admin', 'superadmin'], true)): ?>
          <a href="<?php echo url('admin/dashboard.php'); ?>" class="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md shrink-0">Admin</a>
        <?php elseif ($isLoggedIn && $role === 'student'): ?>
          <a href="<?php echo url('students/dashboard/index.php'); ?>" class="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md shrink-0">Portal</a>
        <?php elseif ($isLoggedIn && ($role === 'tutor' || $role === 'employee')): ?>
          <a href="<?php echo url($role === 'employee' ? 'employee/dashboard/index.php' : 'tutor/dashboard/index.php'); ?>" class="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md shrink-0">Portal</a>
        <?php else: ?>
          <a href="<?php echo url('auth/login.php'); ?>" class="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md transition-colors flex items-center gap-1.5 shrink-0">
            <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Login
          </a>
        <?php endif; ?>
      </div>

      <!-- Mobile Hamburger Button -->
      <button type="button" class="lg:hidden p-2 text-slate-600 hover:text-slate-900 focus:outline-none" id="dazu-mobile-toggle" aria-label="Toggle navigation menu" aria-expanded="false">
        <svg class="h-6 w-6" id="dazu-menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
    </div>
  </div>

  <!-- Mobile Drawer Menu -->
  <div class="lg:hidden hidden border-t border-slate-100 bg-white" id="dazu-mobile-menu">
    <div class="px-4 pt-4 pb-6 space-y-2">
      <?php foreach ($menuItems as $item): ?>
        <?php $active = dazu_nav_active($item['key']); ?>
        <?php if ($item['key'] === 'courses'): ?>
          <div class="rounded-xl border border-slate-100 overflow-hidden">
            <div class="flex items-center justify-between <?php echo $active ? 'bg-green-50/60' : 'bg-white'; ?>">
              <a href="<?php echo $item['href']; ?>" class="flex-1 px-4 py-3 text-xs font-bold tracking-wider <?php echo $active ? 'text-[#22C55E]' : 'text-slate-700 hover:text-[#15A24A]'; ?>">
                <?php echo htmlspecialchars($item['name']); ?>
              </a>
              <button type="button" id="dazu-mobile-courses-toggle" class="px-4 py-3 text-slate-400 hover:text-[#15A24A] flex items-center justify-center focus:outline-none" aria-label="Toggle courses categories">
                <svg class="w-4 h-4 transition-transform duration-200" id="dazu-mobile-courses-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
              </button>
            </div>
            <div class="hidden px-3 py-2 space-y-1 bg-slate-50 border-t border-slate-100" id="dazu-mobile-courses-sub">
              <a href="<?php echo url('courses'); ?>" class="block px-3 py-2 text-xs font-extrabold text-slate-900 hover:text-[#15A24A] rounded-lg hover:bg-white transition-colors">
                &rarr; All Courses
              </a>
              <?php foreach ($navCategories as $navCat): ?>
                <?php $schoolName = function_exists('dazu_format_school_name') ? dazu_format_school_name($navCat['name']) : 'School of ' . $navCat['name']; ?>
                <a href="<?php echo url('courses/category.php?slug=' . rawurlencode($navCat['slug'] ?? '')); ?>" class="block px-3 py-2 text-xs font-semibold text-slate-700 hover:text-[#15A24A] rounded-lg hover:bg-white transition-colors">
                  <?php echo htmlspecialchars($schoolName); ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php else: ?>
          <a href="<?php echo $item['href']; ?>" class="block px-4 py-3 text-xs font-bold tracking-wider rounded-xl <?php echo $active ? 'text-[#22C55E] bg-green-50/60' : 'text-slate-700 hover:bg-slate-50'; ?>">
            <?php echo htmlspecialchars($item['name']); ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>

      <div class="pt-4 border-t border-slate-100 flex flex-col gap-3">
        <a href="<?php echo url('apply/index.php'); ?>" class="w-full py-3 text-center text-xs font-extrabold tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-md uppercase transition-colors">APPLY</a>
        <?php if ($isLoggedIn && in_array($role, ['admin', 'superadmin'], true)): ?>
          <a href="<?php echo url('admin/dashboard.php'); ?>" class="w-full py-3 text-center text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md">Admin Dashboard</a>
        <?php elseif ($isLoggedIn && ($role === 'student' || $role === 'tutor' || $role === 'employee')): ?>
          <a href="<?php echo url(['student' => 'students/dashboard/index.php', 'tutor' => 'tutor/dashboard/index.php', 'employee' => 'employee/dashboard/index.php'][$role]); ?>" class="w-full py-3 text-center text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md">My Portal</a>
        <?php else: ?>
          <a href="<?php echo url('auth/login.php'); ?>" class="w-full py-3 text-center text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md transition-colors">Login</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<script>
(function(){
  // Mobile drawer toggle
  var mobileBtn = document.getElementById('dazu-mobile-toggle');
  var mobileMenu = document.getElementById('dazu-mobile-menu');
  if (mobileBtn && mobileMenu) {
    mobileBtn.addEventListener('click', function(){
      var isHidden = mobileMenu.classList.contains('hidden');
      if (isHidden) {
        mobileMenu.classList.remove('hidden');
        mobileBtn.setAttribute('aria-expanded', 'true');
      } else {
        mobileMenu.classList.add('hidden');
        mobileBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // Mobile courses sub-accordion toggle
  var coursesToggle = document.getElementById('dazu-mobile-courses-toggle');
  var coursesSub = document.getElementById('dazu-mobile-courses-sub');
  var coursesIcon = document.getElementById('dazu-mobile-courses-icon');
  if (coursesToggle && coursesSub) {
    coursesToggle.addEventListener('click', function(e){
      e.preventDefault();
      var isHidden = coursesSub.classList.contains('hidden');
      if (isHidden) {
        coursesSub.classList.remove('hidden');
        if (coursesIcon) coursesIcon.style.transform = 'rotate(180deg)';
      } else {
        coursesSub.classList.add('hidden');
        if (coursesIcon) coursesIcon.style.transform = 'rotate(0deg)';
      }
    });
  }

  // Desktop dropdown tap / keyboard escape support
  var dropdownWrapper = document.getElementById('nav-courses-dropdown');
  if (dropdownWrapper) {
    document.addEventListener('click', function(e){
      if (!dropdownWrapper.contains(e.target)) {
        dropdownWrapper.classList.remove('is-open');
      }
    });
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape') {
        dropdownWrapper.classList.remove('is-open');
      }
    });
  }
})();
</script>
