<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';

$menuItems = [
    ['name' => 'HOME', 'href' => url('/'), 'key' => 'home'],
    ['name' => 'COURSES', 'href' => url('courses'), 'key' => 'courses'],
    ['name' => 'ABOUT', 'href' => url('about'), 'key' => 'about'],
    ['name' => 'PROJECTS', 'href' => url('projects'), 'key' => 'projects'],
    ['name' => 'BLOGS', 'href' => url('blog'), 'key' => 'blog'],
    ['name' => 'CONTACTS', 'href' => url('contact'), 'key' => 'contact'],
];

$isLoggedIn = !empty($_SESSION['logged_in']);
$role = $_SESSION['role'] ?? '';
?>
<nav class="sticky top-0 z-50 w-full bg-white border-b border-slate-100 font-sans">
  <div class="bg-[#0F172A] border-b border-slate-800 py-2">
    <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 flex items-center justify-end gap-2 text-white text-[11px] font-bold">
      <svg class="h-3.5 w-3.5 text-[#15A24A]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
      <span class="text-slate-300">Call / WhatsApp:</span>
      <a href="tel:+254750116600" class="text-[#15A24A] hover:text-[#128a3e] hover:underline font-extrabold">+254 750 116600</a>
    </div>
  </div>

  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="flex items-center justify-between h-20">
      <a href="<?php echo url('/'); ?>" class="flex items-center gap-2 group focus:outline-none" id="nav-logo">
        <img src="https://i.ibb.co/fzfZFtGc/223025.jpg" alt="Dazu Hub Logo" class="h-14 w-14 rounded-lg object-cover shrink-0 transition-transform duration-300 group-hover:scale-105" referrerpolicy="no-referrer">
        <div class="text-left leading-none">
          <div class="text-2xl font-extrabold text-slate-950 tracking-tight">Dazu<span class="text-[#22C55E] ml-1">hub</span></div>
          <div class="text-[10px] text-slate-500 font-extrabold tracking-tight mt-1">Institute</div>
        </div>
      </a>

      <div class="hidden lg:flex items-center gap-8">
        <?php foreach ($menuItems as $item): ?>
          <?php $active = dazu_nav_active($item['key']); ?>
          <a href="<?php echo $item['href']; ?>" class="relative py-2 text-xs font-bold tracking-widest transition-colors hover:text-[#22C55E] <?php echo $active ? 'text-slate-900' : 'text-slate-700'; ?>">
            <?php echo htmlspecialchars($item['name']); ?>
            <?php if ($active): ?><span class="absolute bottom-0 left-0 right-0 h-[2px] bg-[#22C55E] rounded-full"></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="hidden lg:flex items-center gap-4">
        <a href="tel:+254750116600" class="text-xs font-extrabold text-slate-700 hover:text-[#15A24A] flex items-center gap-1.5 mr-2">+254 750 116600</a>
        <a href="<?php echo url('courses'); ?>" class="px-6 py-2.5 text-xs font-extrabold tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-md uppercase">APPLY</a>
        <?php if ($isLoggedIn && in_array($role, ['admin', 'superadmin'], true)): ?>
          <a href="<?php echo url('admin/dashboard.php'); ?>" class="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md">Admin</a>
        <?php elseif ($isLoggedIn && $role === 'student'): ?>
          <a href="<?php echo url('students/dashboard/index.php'); ?>" class="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md">Portal</a>
        <?php elseif ($isLoggedIn && $role === 'tutor'): ?>
          <a href="<?php echo url('tutor/dashboard/index.php'); ?>" class="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md">Portal</a>
        <?php else: ?>
          <a href="<?php echo url('auth/login.php'); ?>" class="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md">Login</a>
        <?php endif; ?>
      </div>

      <button type="button" class="lg:hidden p-2 text-slate-600" id="dazu-mobile-toggle" aria-label="Toggle menu">
        <svg class="h-6 w-6" id="dazu-menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
    </div>
  </div>

  <div class="lg:hidden hidden border-t border-slate-100 bg-white" id="dazu-mobile-menu">
    <div class="px-4 pt-4 pb-6 space-y-2">
      <?php foreach ($menuItems as $item): ?>
        <a href="<?php echo $item['href']; ?>" class="block px-4 py-3 text-xs font-bold tracking-wider rounded-xl <?php echo dazu_nav_active($item['key']) ? 'text-[#22C55E] bg-green-50/50' : 'text-slate-700 hover:bg-slate-50'; ?>">
          <?php echo htmlspecialchars($item['name']); ?>
        </a>
      <?php endforeach; ?>
      <div class="pt-4 border-t border-slate-100 flex flex-col gap-3">
        <a href="<?php echo url('courses'); ?>" class="w-full py-3 text-center text-xs font-extrabold tracking-widest text-white bg-[#15A24A] rounded-md uppercase">APPLY</a>
        <a href="<?php echo url('auth/login.php'); ?>" class="w-full py-3 text-center text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-md">Login</a>
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
