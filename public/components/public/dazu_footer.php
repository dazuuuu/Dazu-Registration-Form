<?php
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
require_once __DIR__ . '/../../../app/v1/helpers/site_logo.php';
require_once __DIR__ . '/../../../app/v1/helpers/site_contact.php';
$year = date('Y');
$footerLogo = site_logo_url();
$contact = site_contact_settings();
?>
<footer class="bg-slate-950 text-slate-400 border-t border-slate-900 pt-16 pb-12">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-12 mb-16">
      <div class="space-y-6 col-span-2 md:col-span-1">
        <a href="<?php echo url('/'); ?>" class="flex items-center gap-2 group">
          <img src="<?php echo htmlspecialchars($footerLogo); ?>" alt="Dazu Training Institute Logo" class="h-12 w-12 rounded-lg object-cover shrink-0 transition-transform duration-300 group-hover:scale-105" referrerpolicy="no-referrer">
          <div class="leading-none">
            <div class="text-xl font-extrabold text-white tracking-tight">Dazu<span class="text-[#22C55E] ml-1">Training</span></div>
            <div class="text-[10px] text-slate-400 font-extrabold tracking-tight mt-1">Institute</div>
          </div>
        </a>
        <p class="text-sm leading-relaxed text-slate-400">
          Empowering future tech leaders with highly practical, project-based technology education in Nairobi, Thika, and online. Transform your potential today.
        </p>
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
            <span><?php echo htmlspecialchars($contact['contact_phone']); ?></span>
          </li>
          <li class="flex items-start gap-3">
            <svg class="h-5 w-5 text-green-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <span><?php echo htmlspecialchars($contact['contact_email']); ?></span>
          </li>
          <li class="flex items-start gap-3">
            <svg class="h-5 w-5 text-green-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span><?php echo htmlspecialchars($contact['contact_address']); ?></span>
          </li>
          <li class="flex items-start gap-3">
            <svg class="h-5 w-5 text-green-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span><?php echo htmlspecialchars($contact['contact_hours']); ?></span>
          </li>
        </ul>
      </div>
    </div>
    <div class="border-t border-slate-900 pt-8 flex flex-col md:flex-row items-center justify-between gap-4 text-xs">
      <p>&copy; <?php echo $year; ?> Dazu Training Institute. All rights reserved.</p>
      <div class="flex gap-6">
        <a href="<?php echo url('contact'); ?>" class="hover:text-white transition-colors duration-200">Support</a>
        <a href="<?php echo url('about'); ?>" class="hover:text-white transition-colors duration-200">Terms of Study</a>
      </div>
    </div>
  </div>
</footer>
