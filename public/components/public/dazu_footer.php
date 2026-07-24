<?php
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
require_once __DIR__ . '/../../../app/v1/helpers/bonfire_theme.php';
$year = date('Y');
$logoUrl = bonfire_asset_v('logo.webp');
$footerBg = bonfire_asset_v('footer-bg.webp');
?>
<footer class="relative text-white border-t border-[#0d5a2d] pt-16 pb-12 overflow-hidden" style="background-color:#0A4322;font-family:'Poppins',sans-serif;">
  <div class="absolute inset-0 opacity-10 pointer-events-none" style="background:url('<?php echo htmlspecialchars($footerBg, ENT_QUOTES); ?>') center/cover no-repeat;" aria-hidden="true"></div>
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 relative z-10">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-12 mb-16">
      <div class="space-y-6 col-span-2 md:col-span-1">
        <a href="<?php echo url('/'); ?>" class="flex items-center gap-3 group">
          <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES); ?>" alt="Dazu hub Institute" class="h-12 w-auto object-contain shrink-0 transition-transform duration-300 group-hover:scale-105">
        </a>
        <p class="text-sm leading-relaxed text-white/70" style="font-family:'Quicksand',sans-serif;">
          Empowering future tech leaders with highly practical, project-based technological education in Thika Town. Transform your potential today.
        </p>
        <div class="flex items-center gap-3">
          <a href="#" class="p-2 bg-white/10 hover:bg-[#D4AF37] hover:text-[#0A4322] rounded-lg transition-colors duration-200" aria-label="Facebook">
            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
          </a>
          <a href="#" class="p-2 bg-white/10 hover:bg-[#D4AF37] hover:text-[#0A4322] rounded-lg transition-colors duration-200 flex items-center justify-center" aria-label="TikTok">
            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.53.02C13.84 0 15.14.01 16.44 0c.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.59-1-.01 2.62.02 5.24 0 7.86-.08 1.93-.74 3.86-2.06 5.23-1.47 1.56-3.66 2.38-5.83 2.19-2.32-.17-4.61-1.51-5.63-3.65-1.15-2.33-.84-5.32.88-7.29 1.34-1.58 3.52-2.38 5.59-2.11v4.01c-1.16-.14-2.4.28-3.13 1.2-.78 1-.77 2.52-.01 3.49.77 1 2.16 1.33 3.25.79.88-.41 1.42-1.32 1.43-2.3V0h3.18z"/></svg>
          </a>
          <a href="#" class="p-2 bg-white/10 hover:bg-[#D4AF37] hover:text-[#0A4322] rounded-lg transition-colors duration-200" aria-label="YouTube">
            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
          </a>
        </div>
      </div>
      <div class="space-y-6">
        <h4 class="text-sm uppercase tracking-widest font-bold text-[#D4AF37]">Company</h4>
        <ul class="space-y-3 text-sm text-white/70" style="font-family:'Quicksand',sans-serif;">
          <li><a href="<?php echo url('about'); ?>" class="hover:text-[#D4AF37] transition-colors duration-200">About Us</a></li>
          <li><a href="<?php echo url('projects'); ?>" class="hover:text-[#D4AF37] transition-colors duration-200">Student Projects</a></li>
          <li><a href="<?php echo url('blog'); ?>" class="hover:text-[#D4AF37] transition-colors duration-200">Institute Blog</a></li>
          <li><a href="<?php echo url('contact'); ?>" class="hover:text-[#D4AF37] transition-colors duration-200">Contact &amp; Support</a></li>
        </ul>
      </div>
      <div class="space-y-6">
        <h4 class="text-sm uppercase tracking-widest font-bold text-[#D4AF37]">Courses</h4>
        <ul class="space-y-3 text-sm text-white/70" style="font-family:'Quicksand',sans-serif;">
          <li><a href="<?php echo url('courses'); ?>" class="hover:text-[#D4AF37] transition-colors duration-200">Software Development</a></li>
          <li><a href="<?php echo url('courses'); ?>" class="hover:text-[#D4AF37] transition-colors duration-200">Artificial Intelligence</a></li>
          <li><a href="<?php echo url('courses'); ?>" class="hover:text-[#D4AF37] transition-colors duration-200">Cybersecurity &amp; Networks</a></li>
          <li><a href="<?php echo url('courses'); ?>" class="hover:text-[#D4AF37] transition-colors duration-200">Creative Graphics &amp; Design</a></li>
        </ul>
      </div>
      <div class="space-y-6 col-span-2 md:col-span-1">
        <h4 class="text-sm uppercase tracking-widest font-bold text-[#D4AF37]">Contact</h4>
        <ul class="space-y-4 text-sm text-white/70" style="font-family:'Quicksand',sans-serif;">
          <li class="flex items-start gap-3">
            <svg class="h-5 w-5 text-[#D4AF37] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            <a href="tel:+254750116600" class="hover:text-[#D4AF37]">0750 116 600</a>
          </li>
          <li class="flex items-start gap-3">
            <svg class="h-5 w-5 text-[#D4AF37] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <a href="mailto:info@dazuaihub.com" class="hover:text-[#D4AF37]">info@dazuaihub.com</a>
          </li>
          <li class="flex items-start gap-3">
            <svg class="h-5 w-5 text-[#D4AF37] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span>Witeithie House, Room 529, Thika Town</span>
          </li>
          <li class="flex items-start gap-3">
            <svg class="h-5 w-5 text-[#D4AF37] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Mon - Sat: 8:00 AM - 5:00 PM</span>
          </li>
        </ul>
      </div>
    </div>
    <div class="border-t border-white/10 pt-8 flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-white/50">
      <p>&copy; <?php echo $year; ?> Dazu hub Institute. All rights reserved.</p>
      <div class="flex gap-6">
        <a href="<?php echo url('contact'); ?>" class="hover:text-[#D4AF37] transition-colors duration-200">Support</a>
        <a href="<?php echo url('about'); ?>" class="hover:text-[#D4AF37] transition-colors duration-200">Terms of Study</a>
      </div>
    </div>
  </div>
</footer>
<a href="https://wa.me/254750116600" target="_blank" rel="noopener" class="bf-whatsapp-float" aria-label="Chat on WhatsApp" title="Chat on WhatsApp">
  <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
</a>
