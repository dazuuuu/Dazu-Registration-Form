<?php
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
$year = date('Y');
?>
<footer class="bg-slate-950 text-slate-400 border-t border-slate-900 pt-16 pb-12 font-sans">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-12 mb-16">
      <div class="space-y-6 col-span-2 md:col-span-1">
        <a href="<?php echo url('/'); ?>" class="flex items-center gap-2 group">
          <img src="https://i.ibb.co/fzfZFtGc/223025.jpg" alt="Dazu Hub Logo" class="h-12 w-12 rounded-lg object-cover" referrerpolicy="no-referrer">
          <div class="leading-none">
            <div class="text-xl font-extrabold text-white tracking-tight">Dazu<span class="text-[#22C55E] ml-1">hub</span></div>
            <div class="text-[10px] text-slate-400 font-extrabold mt-1">Institute</div>
          </div>
        </a>
        <p class="text-sm leading-relaxed">Empowering future tech leaders with highly practical, project-based technological education in Thika Town.</p>
      </div>
      <div class="space-y-6">
        <h4 class="text-sm uppercase tracking-widest font-bold text-white">Company</h4>
        <ul class="space-y-3 text-sm">
          <li><a href="<?php echo url('about'); ?>" class="hover:text-green-500">About Us</a></li>
          <li><a href="<?php echo url('projects'); ?>" class="hover:text-green-500">Student Projects</a></li>
          <li><a href="<?php echo url('blog'); ?>" class="hover:text-green-500">Institute Blog</a></li>
          <li><a href="<?php echo url('contact'); ?>" class="hover:text-green-500">Contact &amp; Support</a></li>
        </ul>
      </div>
      <div class="space-y-6">
        <h4 class="text-sm uppercase tracking-widest font-bold text-white">Courses</h4>
        <ul class="space-y-3 text-sm">
          <li><a href="<?php echo url('courses'); ?>" class="hover:text-green-500">Software Development</a></li>
          <li><a href="<?php echo url('courses'); ?>" class="hover:text-green-500">Artificial Intelligence</a></li>
          <li><a href="<?php echo url('courses'); ?>" class="hover:text-green-500">Cybersecurity</a></li>
          <li><a href="<?php echo url('courses'); ?>" class="hover:text-green-500">Data Analytics</a></li>
        </ul>
      </div>
      <div class="space-y-6 col-span-2 md:col-span-1">
        <h4 class="text-sm uppercase tracking-widest font-bold text-white">Contact</h4>
        <ul class="space-y-4 text-sm">
          <li>0750 116 600</li>
          <li>info@dazuaihub.com</li>
          <li>Witeithie House, Room 529, Thika Town</li>
          <li>Mon - Sat: 8:00 AM - 5:00 PM</li>
        </ul>
      </div>
    </div>
    <div class="border-t border-slate-900 pt-8 flex flex-col md:flex-row items-center justify-between gap-4 text-xs">
      <p>&copy; <?php echo $year; ?> Dazu hub Institute. All rights reserved.</p>
      <div class="flex gap-6">
        <a href="<?php echo url('contact'); ?>" class="hover:text-white">Support</a>
        <a href="<?php echo url('about'); ?>" class="hover:text-white">Terms of Study</a>
      </div>
    </div>
  </div>
</footer>
