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
    ['name' => 'COURSES', 'href' => url('courses'), 'key' => 'courses', 'submenu' => [
        ['name' => 'All Courses', 'href' => url('courses')],
        ['name' => 'Free Courses', 'href' => url('courses/free.php')],
    ]],
    ['name' => 'ABOUT', 'href' => url('about'), 'key' => 'about'],
    ['name' => 'PROJECTS', 'href' => url('projects'), 'key' => 'projects'],
    ['name' => 'BLOGS', 'href' => url('blog'), 'key' => 'blog'],
    ['name' => 'CONTACTS', 'href' => url('contact'), 'key' => 'contact'],
];

$isLoggedIn = !empty($_SESSION['logged_in']);
$role = $_SESSION['role'] ?? '';
?>
<style>
/* Nav submenu ("Courses" -> All / Free) — hand-authored rather than Tailwind utilities:
   the shipped CSS is a precompiled/purged build, so classes not already scanned elsewhere
   (e.g. group-hover:) would silently no-op. Inline here for the same reason dazu_hero.php
   inlines its CSS: sw.js caches every .css file, so this can't go stale on deploy. */
.dazu-nav-item-has-sub { position: relative; }
.dazu-nav-caret {
  display: inline-flex; align-items: center; justify-content: center;
  width: 1.1rem; height: 1.1rem; margin-left: .2rem;
  background: none; border: 0; padding: 0; cursor: pointer; color: inherit; vertical-align: -2px;
}
.dazu-nav-caret svg { width: .65rem; height: .65rem; transition: transform .15s ease; }
.dazu-nav-item-has-sub.dazu-nav-open .dazu-nav-caret svg { transform: rotate(180deg); }
.dazu-nav-submenu {
  position: absolute; top: calc(100% + .6rem); left: 0; min-width: 170px;
  background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
  box-shadow: 0 14px 34px -12px rgba(15,23,42,.18); padding: .4rem;
  opacity: 0; visibility: hidden; transform: translateY(-6px);
  transition: opacity .15s ease, transform .15s ease, visibility .15s;
  z-index: 60;
}
.dazu-nav-item-has-sub:hover .dazu-nav-submenu,
.dazu-nav-item-has-sub:focus-within .dazu-nav-submenu,
.dazu-nav-item-has-sub.dazu-nav-open .dazu-nav-submenu {
  opacity: 1; visibility: visible; transform: translateY(0);
}
.dazu-nav-submenu a {
  display: block; padding: .55rem .75rem; border-radius: 7px;
  font-size: .78rem; font-weight: 700; color: #334155; text-decoration: none; white-space: nowrap;
}
.dazu-nav-submenu a:hover { background: #f0fdf4; color: #15A24A; }
.dazu-mobile-submenu { margin: .1rem 0 .5rem 1rem; display: flex; flex-direction: column; gap: .1rem; }
.dazu-mobile-submenu a {
  display: block; padding: .5rem .75rem; font-size: .7rem; font-weight: 700;
  color: #64748b; text-decoration: none; border-radius: 8px;
}
.dazu-mobile-submenu a:hover { background: #f8fafc; color: #15A24A; }
</style>
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
        <img src="<?php echo asset('images/logo/logo.png'); ?>" alt="Dazu Hub Logo" class="h-14 w-14 rounded-lg object-cover shrink-0 transition-transform duration-300 group-hover:scale-105">
        <div class="text-left leading-none">
          <div class="text-2xl font-extrabold text-slate-950 tracking-tight">Dazu<span class="text-[#22C55E] ml-1">hub</span></div>
          <div class="text-[10px] text-slate-500 font-extrabold tracking-tight mt-1">Institute</div>
        </div>
      </a>

      <div class="hidden lg:flex items-center gap-8">
        <?php foreach ($menuItems as $item): ?>
          <?php $active = dazu_nav_active($item['key']); $sub = $item['submenu'] ?? []; ?>
          <?php if ($sub): ?>
            <div class="dazu-nav-item-has-sub">
              <a href="<?php echo $item['href']; ?>" class="relative py-2 text-xs font-bold tracking-widest transition-colors hover:text-[#22C55E] <?php echo $active ? 'text-slate-900' : 'text-slate-700'; ?>">
                <?php echo htmlspecialchars($item['name']); ?>
                <?php if ($active): ?><span class="absolute bottom-0 left-0 right-0 h-[2px] bg-[#22C55E] rounded-full"></span><?php endif; ?>
              </a><button type="button" class="dazu-nav-caret" aria-expanded="false" aria-label="Show <?php echo htmlspecialchars($item['name']); ?> submenu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button>
              <div class="dazu-nav-submenu">
                <?php foreach ($sub as $s): ?>
                  <a href="<?php echo $s['href']; ?>"><?php echo htmlspecialchars($s['name']); ?></a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php else: ?>
            <a href="<?php echo $item['href']; ?>" class="relative py-2 text-xs font-bold tracking-widest transition-colors hover:text-[#22C55E] <?php echo $active ? 'text-slate-900' : 'text-slate-700'; ?>">
              <?php echo htmlspecialchars($item['name']); ?>
              <?php if ($active): ?><span class="absolute bottom-0 left-0 right-0 h-[2px] bg-[#22C55E] rounded-full"></span><?php endif; ?>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <div class="hidden lg:flex items-center gap-4">
        <a href="tel:+254750116600" class="text-xs font-extrabold text-slate-700 hover:text-[#15A24A] flex items-center gap-1.5 mr-2">+254 750 116600</a>
        <a href="<?php echo url('apply/index.php'); ?>" class="px-6 py-2.5 text-xs font-extrabold tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-md uppercase transition-all duration-200">APPLY</a>
        <?php if ($isLoggedIn && in_array($role, ['admin', 'superadmin'], true)): ?>
          <a href="<?php echo url('admin/dashboard.php'); ?>" class="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md">Admin</a>
        <?php elseif ($isLoggedIn && $role === 'student'): ?>
          <a href="<?php echo url('students/dashboard/index.php'); ?>" class="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md">Portal</a>
        <?php elseif ($isLoggedIn && $role === 'tutor'): ?>
          <a href="<?php echo url('tutor/dashboard/index.php'); ?>" class="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md">Portal</a>
        <?php else: ?>
          <a href="<?php echo url('auth/login.php'); ?>" class="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md transition-all duration-200 flex items-center gap-1.5">
            <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Login
          </a>
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
        <?php if (!empty($item['submenu'])): ?>
          <div class="dazu-mobile-submenu">
            <?php foreach ($item['submenu'] as $s): ?>
              <a href="<?php echo $s['href']; ?>">↳ <?php echo htmlspecialchars($s['name']); ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
      <div class="pt-4 border-t border-slate-100 flex flex-col gap-3">
        <a href="<?php echo url('apply/index.php'); ?>" class="w-full py-3 text-center text-xs font-extrabold tracking-widest text-white bg-[#15A24A] rounded-md uppercase">APPLY</a>
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
(function(){
  // Caret click toggles the submenu explicitly — needed for touch, where :hover never
  // reliably fires. The "COURSES" link itself is untouched and still navigates normally.
  var carets = document.querySelectorAll('.dazu-nav-caret');
  carets.forEach(function (caret) {
    caret.addEventListener('click', function (e) {
      e.preventDefault();
      var wrap = caret.closest('.dazu-nav-item-has-sub');
      if (!wrap) return;
      var open = wrap.classList.toggle('dazu-nav-open');
      caret.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });
  document.addEventListener('click', function (e) {
    document.querySelectorAll('.dazu-nav-item-has-sub.dazu-nav-open').forEach(function (wrap) {
      if (!wrap.contains(e.target)) {
        wrap.classList.remove('dazu-nav-open');
        var c = wrap.querySelector('.dazu-nav-caret');
        if (c) c.setAttribute('aria-expanded', 'false');
      }
    });
  });
})();
</script>
