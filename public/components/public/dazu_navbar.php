<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../../app/v1/helpers/bonfire_theme.php';

$menuItems = [
    ['name' => 'HOME', 'href' => url('/'), 'key' => 'home'],
    ['name' => 'COURSES', 'href' => url('courses'), 'key' => 'courses'],
    ['name' => 'ABOUT', 'href' => url('about'), 'key' => 'about'],
    ['name' => 'PROJECTS', 'href' => url('projects'), 'key' => 'projects'],
    ['name' => 'BLOGS', 'href' => url('blog'), 'key' => 'blog'],
    ['name' => 'CONTACT', 'href' => url('contact'), 'key' => 'contact'],
];

$isLoggedIn = !empty($_SESSION['logged_in']);
$role = $_SESSION['role'] ?? '';
$logoUrl = bonfire_asset_v('logo.webp');
?>
<nav class="sticky top-0 z-50 w-full bg-white border-b border-[#F0F0F0] shadow-sm font-sans" style="font-family:'Poppins',sans-serif;">
  <!-- Top utility bar — Bonfire header style -->
  <div class="bg-[#0A4322] border-b border-[#0d5a2d] py-2">
    <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 flex items-center justify-between gap-4 text-white text-[11px] font-semibold">
      <div class="hidden sm:flex items-center gap-4">
        <a href="tel:+254750116600" class="flex items-center gap-1.5 hover:text-[#D4AF37] transition-colors">
          <svg class="h-3.5 w-3.5 text-[#D4AF37]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
          +254 750 116600
        </a>
        <span class="text-white/40">|</span>
        <a href="mailto:info@dazuaihub.com" class="hover:text-[#D4AF37] transition-colors">info@dazuaihub.com</a>
      </div>
      <div class="flex items-center gap-3 ml-auto">
        <span class="text-white/60 hidden md:inline">Witeithie House, Thika Town</span>
        <a href="https://wa.me/254750116600" target="_blank" rel="noopener" class="text-[#D4AF37] hover:text-white transition-colors font-bold">WhatsApp</a>
      </div>
    </div>
  </div>

  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="flex items-center justify-between h-20">
      <a href="<?php echo url('/'); ?>" class="flex items-center gap-3 group focus:outline-none" id="nav-logo">
        <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES); ?>" alt="Dazu hub Institute" class="h-12 w-auto object-contain shrink-0 transition-transform duration-300 group-hover:scale-105">
      </a>

      <div class="hidden lg:flex items-center gap-7">
        <?php foreach ($menuItems as $item): ?>
          <?php $active = dazu_nav_active($item['key']); ?>
          <a href="<?php echo $item['href']; ?>" class="relative py-2 text-xs font-semibold tracking-widest uppercase transition-colors hover:text-[#D4AF37] <?php echo $active ? 'text-[#0A4322]' : 'text-[#444444]'; ?>" style="font-family:'Poppins',sans-serif;">
            <?php echo htmlspecialchars($item['name']); ?>
            <?php if ($active): ?><span class="absolute bottom-0 left-0 right-0 h-[2px] bg-[#D4AF37] rounded-full"></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="hidden lg:flex items-center gap-3">
        <a href="<?php echo url('apply/index.php'); ?>" class="bf-btn bf-btn--primary text-xs px-6 py-2.5">Apply Now</a>
        <?php if ($isLoggedIn && in_array($role, ['admin', 'superadmin'], true)): ?>
          <a href="<?php echo url('admin/dashboard.php'); ?>" class="px-4 py-2 text-xs font-semibold text-[#444444] bg-white border border-[#e1e1e1] hover:border-[#D4AF37] rounded-full transition-all">Admin</a>
        <?php elseif ($isLoggedIn && $role === 'student'): ?>
          <a href="<?php echo url('students/dashboard/index.php'); ?>" class="px-4 py-2 text-xs font-semibold text-[#444444] bg-white border border-[#e1e1e1] hover:border-[#D4AF37] rounded-full transition-all">Portal</a>
        <?php elseif ($isLoggedIn && $role === 'tutor'): ?>
          <a href="<?php echo url('tutor/dashboard/index.php'); ?>" class="px-4 py-2 text-xs font-semibold text-[#444444] bg-white border border-[#e1e1e1] hover:border-[#D4AF37] rounded-full transition-all">Portal</a>
        <?php else: ?>
          <a href="<?php echo url('auth/login.php'); ?>" class="px-4 py-2 text-xs font-semibold text-[#444444] bg-white border border-[#e1e1e1] hover:border-[#D4AF37] rounded-full transition-all flex items-center gap-1.5">
            <svg class="h-4 w-4 text-[#888]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Login
          </a>
        <?php endif; ?>
      </div>

      <button type="button" class="lg:hidden p-2 text-[#444444]" id="dazu-mobile-toggle" aria-label="Toggle menu">
        <svg class="h-6 w-6" id="dazu-menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
    </div>
  </div>

  <div class="lg:hidden hidden border-t border-[#f0f0f0] bg-white" id="dazu-mobile-menu">
    <div class="px-4 pt-4 pb-6 space-y-2">
      <?php foreach ($menuItems as $item): ?>
        <a href="<?php echo $item['href']; ?>" class="block px-4 py-3 text-xs font-semibold tracking-wider uppercase rounded-xl <?php echo dazu_nav_active($item['key']) ? 'text-[#D4AF37] bg-[#FFE9C8]/40' : 'text-[#444444] hover:bg-[#F8F6F1]'; ?>">
          <?php echo htmlspecialchars($item['name']); ?>
        </a>
      <?php endforeach; ?>
      <div class="pt-4 border-t border-[#f0f0f0] flex flex-col gap-3">
        <a href="<?php echo url('apply/index.php'); ?>" class="w-full py-3 text-center text-xs font-semibold tracking-widest text-white bg-[#228B22] hover:bg-[#D37A1F] rounded-full uppercase transition-colors">Apply Now</a>
        <a href="<?php echo url('auth/login.php'); ?>" class="w-full py-3 text-center text-xs font-semibold text-[#444444] bg-white border border-[#e1e1e1] rounded-full">Login</a>
      </div>
    </div>
  </div>
</nav>
<script>
(function(){
  var btn = document.getElementById('dazu-mobile-toggle');
  var menu = document.getElementById('dazu-mobile-menu');
  if (btn && menu) btn.addEventListener('click', function(){ menu.classList.toggle('hidden'); });
})();
</script>
