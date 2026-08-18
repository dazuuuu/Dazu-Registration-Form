<?php
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
require_once __DIR__ . '/../../../app/v1/helpers/site_logo.php';
$year = date('Y');
$footerLogo = site_logo_url();
?>
<footer class="bg-slate-950 text-slate-400 border-t border-slate-900 pt-16 pb-12">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-12 mb-16">
      <div class="space-y-6 col-span-2 md:col-span-1">
        <a href="<?php echo url('/'); ?>" class="flex items-center gap-2 group">
          <img src="<?php echo htmlspecialchars($footerLogo); ?>" alt="Dazu Hub Logo" class="h-12 w-12 rounded-lg object-cover shrink-0 transition-transform duration-300 group-hover:scale-105" referrerpolicy="no-referrer">
          <div class="leading-none">
            <div class="text-xl font-extrabold text-white tracking-tight">Dazu<span class="text-[#22C55E] ml-1">hub</span></div>
            <div class="text-[10px] text-slate-400 font-extrabold tracking-tight mt-1">Institute</div>
          </div>
        </a>
        <p class="text-sm leading-relaxed text-slate-400">
          Empowering future tech leaders with highly practical, project-based technological education in Thika Town. Transform your potential today.
        </p>
        <div class="flex items-center gap-3">
          <a href="#" class="p-2 bg-slate-900 hover:bg-green-600 hover:text-white rounded-lg transition-colors duration-200" aria-label="Facebook">
            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
          </a>
          <a href="#" class="p-2 bg-slate-900 hover:bg-green-600 hover:text-white rounded-lg transition-colors duration-200 flex items-center justify-center" aria-label="TikTok">
            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.53.02C13.84 0 15.14.01 16.44 0c.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.59-1-.01 2.62.02 5.24 0 7.86-.08 1.93-.74 3.86-2.06 5.23-1.47 1.56-3.66 2.38-5.83 2.19-2.32-.17-4.61-1.51-5.63-3.65-1.15-2.33-.84-5.32.88-7.29 1.34-1.58 3.52-2.38 5.59-2.11v4.01c-1.16-.14-2.4.28-3.13 1.2-.78 1-.77 2.52-.01 3.49.77 1 2.16 1.33 3.25.79.88-.41 1.42-1.32 1.43-2.3V0h3.18z"/></svg>
          </a>
          <a href="#" class="p-2 bg-slate-900 hover:bg-green-600 hover:text-white rounded-lg transition-colors duration-200" aria-label="YouTube">
            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
          </a>
        </div>
      </div>
      <div class="space-y-6">
        <h4 class="text-sm uppercase tracking-widest font-bold text-white">Company</h4>
        <ul class="space-y-3 text-sm">
          <li><a href="<?php echo url('about'); ?>" class="hover:text-green-500 transition-colors duration-200">About Us</a></li>
          <li><a href="<?php echo url('projects'); ?>" class="hover:text-green-500 transition-colors duration-200">Student Projects</a></li>
          <li><a href="<?php echo url('blog'); ?>" class="hover:text-green-500 transition-colors duration-200">Institute Blog</a></li>
          <li><a href="<?php echo url('contact'); ?>" class="hover:text-green-500 transition-colors duration-200">Contact &amp; Support</a></li>
        </ul>
      </div>
      <div class="space-y-6">
        <h4 class="text-sm uppercase tracking-widest font-bold text-white">Courses</h4>
        <ul class="space-y-3 text-sm">
          <li><a href="<?php echo url('courses'); ?>" class="hover:text-green-500 transition-colors duration-200">Software Development</a></li>
          <li><a href="<?php echo url('courses'); ?>" class="hover:text-green-500 transition-colors duration-200">Artificial Intelligence</a></li>
          <li><a href="<?php echo url('courses'); ?>" class="hover:text-green-500 transition-colors duration-200">Cybersecurity &amp; Networks</a></li>
          <li><a href="<?php echo url('courses'); ?>" class="hover:text-green-500 transition-colors duration-200">Creative Graphics &amp; Design</a></li>
        </ul>
      </div>
      <div class="space-y-6 col-span-2 md:col-span-1">
        <h4 class="text-sm uppercase tracking-widest font-bold text-white">Contact</h4>
        <ul class="space-y-4 text-sm">
          <li class="flex items-start gap-3">
            <svg class="h-5 w-5 text-green-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            <span>0750 116 600</span>
          </li>
          <li class="flex items-start gap-3">
            <svg class="h-5 w-5 text-green-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <span>admin@dazuhub.com</span>
          </li>
          <li class="flex items-start gap-3">
            <svg class="h-5 w-5 text-green-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span>Witeithie House, Room 529, Thika Town</span>
          </li>
          <li class="flex items-start gap-3">
            <svg class="h-5 w-5 text-green-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Mon - Sat: 8:00 AM - 5:00 PM</span>
          </li>
        </ul>
      </div>
    </div>
    <div class="border-t border-slate-900 pt-8 flex flex-col md:flex-row items-center justify-between gap-4 text-xs">
      <p>&copy; <?php echo $year; ?> Dazu hub Institute. All rights reserved.</p>
      <div class="flex gap-6">
        <a href="<?php echo url('contact'); ?>" class="hover:text-white transition-colors duration-200">Support</a>
        <a href="<?php echo url('about'); ?>" class="hover:text-white transition-colors duration-200">Terms of Study</a>
      </div>
    </div>
  </div>
</footer>
