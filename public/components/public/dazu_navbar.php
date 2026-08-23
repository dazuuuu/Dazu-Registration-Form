<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../../app/v1/helpers/free_courses.php';

$menuItems = [
    ['name' => 'HOME', 'href' => url('/'), 'key' => 'home'],
    ['name' => 'COURSES', 'href' => url('courses'), 'key' => 'courses'],
    ['name' => 'FREE COURSES', 'href' => url('courses/free.php'), 'key' => 'free-courses'],
    ['name' => 'ABOUT', 'href' => url('about'), 'key' => 'about'],
    ['name' => 'PROJECTS', 'href' => url('projects'), 'key' => 'projects'],
    ['name' => 'BLOGS', 'href' => url('blog'), 'key' => 'blog'],
    ['name' => 'CONTACTS', 'href' => url('contact'), 'key' => 'contact'],
];

if (!dazu_free_courses_enabled()) {
    $menuItems = array_values(array_filter($menuItems, static fn($item) => $item['key'] !== 'free-courses'));
}

$isLoggedIn = !empty($_SESSION['logged_in']);
$role = $_SESSION['role'] ?? '';

$logoUrl = asset('images/logo/logo.png');
try {
    if (!class_exists('Models\\SettingsModel')) {
        require_once __DIR__ . '/../../../app/v1/models/SettingsModel.php';
    }
    $settings = new \Models\SettingsModel();
    $storedLogo = trim((string) $settings->get('logo_path', ''));
    if ($storedLogo !== '') {
        $logoUrl = settings_asset_url($storedLogo);
    }
} catch (Throwable $e) {
    // Fall back to bundled logo below.
}
if (!is_file(public_path('images/logo/logo.png')) && $logoUrl === asset('images/logo/logo.png')) {
    $logoUrl = 'https://i.ibb.co/fzfZFtGc/223025.jpg';
}

$portalHref = url('auth/login.php');
$portalLabel = 'Login';
$portalIcon = true;
if ($isLoggedIn && in_array($role, ['admin', 'superadmin'], true)) {
    $portalHref = url('admin/dashboard.php');
    $portalLabel = 'Admin';
    $portalIcon = false;
} elseif ($isLoggedIn && $role === 'student') {
    $portalHref = url('students/dashboard/index.php');
    $portalLabel = 'Portal';
    $portalIcon = false;
} elseif ($isLoggedIn && $role === 'tutor') {
    $portalHref = url('tutor/dashboard/index.php');
    $portalLabel = 'Portal';
    $portalIcon = false;
}
?>
<nav class="sticky top-0 z-50 w-full bg-white border-b border-slate-100 font-sans">
  <div class="bg-[#0F172A] border-b border-slate-800 py-2">
    <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 flex items-center justify-end gap-2 text-white text-[11px] font-bold">
      <svg class="h-3.5 w-3.5 text-[#15A24A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
      <span class="text-slate-300">Call / WhatsApp:</span>
      <a href="tel:+254750116600" class="text-[#15A24A] hover:text-[#128a3e] hover:underline font-extrabold transition-colors">+254 750 116600</a>
    </div>
  </div>

  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="flex items-center justify-between h-20">
      <a href="<?php echo url('/'); ?>" class="flex items-center gap-2 group focus:outline-none" id="nav-logo">
        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Dazu Hub Logo" class="h-14 w-14 rounded-lg object-cover shrink-0 transition-transform duration-300 group-hover:scale-105" referrerpolicy="no-referrer">
        <div class="text-left leading-none">
          <div class="text-2xl font-extrabold text-slate-950 tracking-tight">Dazu<span class="text-[#22C55E] ml-1">hub</span></div>
          <div class="text-[10px] text-slate-500 font-extrabold tracking-tight mt-1">Institute</div>
        </div>
      </a>

      <div class="hidden lg:flex items-center gap-8">
        <?php foreach ($menuItems as $item): ?>
          <?php $active = dazu_nav_active($item['key']); ?>
          <a href="<?php echo $item['href']; ?>" id="nav-link-<?php echo strtolower(htmlspecialchars($item['key'])); ?>"
             class="relative py-2 text-xs font-bold tracking-widest transition-colors duration-200 hover:text-[#22C55E] <?php echo $active ? 'text-slate-900' : 'text-slate-700'; ?>">
            <?php echo htmlspecialchars($item['name']); ?>
            <?php if ($active): ?><span class="absolute bottom-0 left-0 right-0 h-[2px] bg-[#22C55E] rounded-full"></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="hidden lg:flex items-center gap-4">
        <a href="tel:+254750116600" class="text-xs font-extrabold text-slate-700 hover:text-[#15A24A] transition-all flex items-center gap-1.5 mr-2">
          <svg class="h-4 w-4 text-[#15A24A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
          +254 750 116600
        </a>
        <a href="<?php echo url('apply/index.php'); ?>" id="nav-btn-apply"
           class="px-6 py-2.5 text-xs font-extrabold tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-md uppercase transition-all duration-200">APPLY</a>
        <a href="<?php echo htmlspecialchars($portalHref); ?>" id="nav-btn-login"
           class="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md transition-all duration-200 flex items-center gap-1.5">
          <?php if ($portalIcon): ?>
            <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          <?php endif; ?>
          <?php echo htmlspecialchars($portalLabel); ?>
        </a>
      </div>

      <button type="button" class="lg:hidden p-2 text-slate-600 hover:text-[#22C55E] rounded-xl focus:outline-none" id="dazu-mobile-toggle" aria-label="Toggle menu">
        <svg class="h-6 w-6 dazu-menu-open" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        <svg class="h-6 w-6 dazu-menu-close hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
  </div>

  <div class="lg:hidden hidden border-t border-b border-slate-100 bg-white" id="dazu-mobile-menu">
    <div class="px-4 pt-4 pb-6 space-y-2">
      <?php foreach ($menuItems as $item): ?>
        <a href="<?php echo $item['href']; ?>" class="block px-4 py-3 text-xs font-bold tracking-wider rounded-xl transition-colors <?php echo dazu_nav_active($item['key']) ? 'text-[#22C55E] bg-green-50/50' : 'text-slate-700 hover:text-[#22C55E] hover:bg-slate-50'; ?>">
          <?php echo htmlspecialchars($item['name']); ?>
        </a>
      <?php endforeach; ?>
      <div class="pt-4 border-t border-slate-100 flex flex-col gap-3">
        <a href="tel:+254750116600" class="w-full py-3 text-center text-xs font-extrabold text-slate-700 hover:text-[#15A24A] bg-slate-50 rounded-md transition-all duration-200 flex items-center justify-center gap-1.5">
          <svg class="h-4 w-4 text-[#15A24A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
          +254 750 116600
        </a>
        <a href="<?php echo url('apply/index.php'); ?>" class="w-full py-3 text-center text-xs font-extrabold tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-md uppercase transition-all duration-200">APPLY</a>
        <a href="<?php echo htmlspecialchars($portalHref); ?>" class="w-full py-3 text-center text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-50 transition-all duration-200 flex items-center justify-center gap-1.5">
          <?php if ($portalIcon): ?>
            <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          <?php endif; ?>
          <?php echo htmlspecialchars($portalLabel); ?>
        </a>
      </div>
    </div>
  </div>
</nav>
<script>
(function(){
  var btn = document.getElementById('dazu-mobile-toggle');
  var menu = document.getElementById('dazu-mobile-menu');
  var openIcon = btn ? btn.querySelector('.dazu-menu-open') : null;
  var closeIcon = btn ? btn.querySelector('.dazu-menu-close') : null;
  if (!btn || !menu) return;
  btn.addEventListener('click', function(){
    var open = menu.classList.toggle('hidden');
    if (openIcon && closeIcon) {
      openIcon.classList.toggle('hidden', !open);
      closeIcon.classList.toggle('hidden', open);
    }
  });
})();
</script>
