<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('url')) require_once __DIR__ . '/../../../app/v1/config/paths.php';
$isLoggedIn = !empty($_SESSION['logged_in']);
$role = $_SESSION['role'] ?? '';
?>
<nav class="sticky top-0 z-50 h-20 bg-white/95 backdrop-blur border-b border-slate-200">
  <div class="max-w-[1480px] mx-auto h-full px-5 md:px-8 flex items-center justify-between">
    <a href="<?php echo url('/'); ?>" class="flex items-center gap-3 shrink-0 no-underline" style="text-decoration:none!important">
      <span class="h-12 w-12 rounded-[9px] bg-[#00A843] text-white flex items-center justify-center text-2xl font-black">D</span>
      <span class="text-[22px] md:text-[28px] font-extrabold tracking-tight" style="color:#111827!important">Dazu Training Institute</span>
    </a>
    <div class="hidden lg:flex items-center gap-8 xl:gap-9 text-[15px] font-bold">
      <a style="color:#111827!important;text-decoration:none" class="hover:text-[#00A843]" href="<?php echo url('/'); ?>">HOME</a>
      <span class="relative group"><a style="color:#111827!important;text-decoration:none" class="inline-flex items-center gap-1 hover:text-[#00A843]" href="<?php echo url('courses'); ?>">COURSES <span class="text-lg leading-none">⌄</span></a><span class="hidden group-hover:block absolute top-full -left-4 pt-3 w-48"><span class="block bg-white rounded-xl shadow-xl border border-slate-100 p-2"><a class="block px-3 py-2 hover:bg-slate-50 rounded-lg" href="<?php echo url('courses'); ?>">All Courses</a><a class="block px-3 py-2 hover:bg-slate-50 rounded-lg" href="<?php echo url('courses/free.php'); ?>">Free Courses</a></span></span></span>
      <a style="color:#111827!important;text-decoration:none" class="hover:text-[#00A843]" href="<?php echo url('about'); ?>">ABOUT</a><a style="color:#111827!important;text-decoration:none" class="hover:text-[#00A843]" href="<?php echo url('projects'); ?>">PROJECTS</a><a style="color:#111827!important;text-decoration:none" class="hover:text-[#00A843]" href="<?php echo url('blogs'); ?>">BLOGS</a><a style="color:#111827!important;text-decoration:none" class="hover:text-[#00A843]" href="<?php echo url('contact'); ?>">CONTACTS</a>
    </div>
    <div class="hidden lg:flex items-center gap-5"><a href="<?php echo url('apply/index.php'); ?>" class="px-7 py-3 rounded-full bg-[#00A843] text-white text-[16px] font-extrabold hover:bg-green-700">APPLY</a><?php if ($isLoggedIn && in_array($role, ['admin','superadmin'], true)): ?><a class="font-extrabold text-[#00A843]" href="<?php echo url('admin/dashboard.php'); ?>">Admin</a><?php elseif ($isLoggedIn && $role === 'student'): ?><a class="font-extrabold text-[#00A843]" href="<?php echo url('students/dashboard/index.php'); ?>">Portal</a><?php else: ?><a class="font-extrabold text-[#00A843] text-[17px]" href="<?php echo url('auth/login.php'); ?>">Login</a><?php endif; ?></div>
    <button id="dazu-mobile-toggle" class="lg:hidden text-2xl text-slate-700" aria-label="Toggle menu">☰</button>
  </div>
  <div id="dazu-mobile-menu" class="hidden lg:hidden border-t border-slate-100 bg-white p-4 space-y-1 text-sm font-bold"><a class="block p-3" href="<?php echo url('/'); ?>">HOME</a><a class="block p-3" href="<?php echo url('courses'); ?>">COURSES</a><a class="block p-3" href="<?php echo url('about'); ?>">ABOUT</a><a class="block p-3" href="<?php echo url('projects'); ?>">PROJECTS</a><a class="block p-3" href="<?php echo url('blogs'); ?>">BLOGS</a><a class="block p-3" href="<?php echo url('contact'); ?>">CONTACTS</a><a class="block p-3 text-[#00A843]" href="<?php echo url('apply/index.php'); ?>">APPLY</a><a class="block p-3 text-[#00A843]" href="<?php echo url('auth/login.php'); ?>">LOGIN</a></div>
</nav><script>document.getElementById('dazu-mobile-toggle')?.addEventListener('click',()=>document.getElementById('dazu-mobile-menu').classList.toggle('hidden'));</script>
