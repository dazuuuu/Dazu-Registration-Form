<?php
/** Physical campus section — static content */
?>
<section class="bg-white py-20 border-t border-slate-100">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
      <div class="space-y-6">
        <span class="inline-block text-[#15A24A] text-xs uppercase tracking-widest font-extrabold px-3 py-1.5 bg-green-50 border border-green-200 rounded-full">Physical Campus Environment</span>
        <h2 class="text-3xl md:text-4xl font-extrabold text-slate-950 tracking-tight uppercase">Our Physical Lab in Thika Town</h2>
        <p class="text-slate-600 font-medium leading-relaxed">Step into <strong>Room 529</strong>, our modern, fully-equipped development space located in the heart of Thika Town. Meet other passionate coders, work under professional mentorship, and access our elite local server infrastructure.</p>
        <ul class="space-y-4 text-sm text-slate-600">
          <li class="flex items-start gap-3"><span class="text-[#15A24A] font-bold mt-0.5">✓</span> High-speed fiber optic connection optimized for AWS, Vercel &amp; Firebase deployments.</li>
          <li class="flex items-start gap-3"><span class="text-[#15A24A] font-bold mt-0.5">✓</span> Ergonomic software workstations paired with external development test screens.</li>
          <li class="flex items-start gap-3"><span class="text-[#15A24A] font-bold mt-0.5">✓</span> Interactive presentation screens for weekly group code reviews &amp; presentation sessions.</li>
        </ul>
        <div class="flex flex-wrap gap-3 pt-2">
          <a href="<?php echo url('about'); ?>" class="px-6 py-3 text-xs font-extrabold uppercase tracking-widest text-slate-900 bg-white border border-slate-200 hover:bg-slate-50 rounded-xl">Explore Campus Tour</a>
          <a href="<?php echo url('contact'); ?>" class="px-6 py-3 text-xs font-extrabold uppercase tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-xl">Visit Room 529</a>
        </div>
      </div>

      <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 md:p-8 space-y-6">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-widest text-slate-500">Campus Facilities</span>
          <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#15A24A] bg-green-50 border border-green-200 px-2.5 py-1 rounded-full">Room 529 Active</span>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <?php
          $facilities = [
              ['icon' => '💻', 'title' => 'Modern Workstations', 'text' => 'Dual monitors and comfortable developer seating.'],
              ['icon' => '⬡', 'title' => 'Local Lab Servers', 'text' => 'Deploy and test full-stack apps on local subnets.'],
              ['icon' => '📶', 'title' => 'High-Speed Optic', 'text' => 'Fiber internet for immediate cloud deployments.'],
              ['icon' => '🖥', 'title' => 'Interactive Screens', 'text' => 'Live code walkthroughs and design sprints.'],
          ];
          foreach ($facilities as $f): ?>
            <div class="bg-white border border-slate-200 rounded-xl p-4">
              <span class="text-xl"><?php echo $f['icon']; ?></span>
              <h3 class="text-[10px] font-extrabold uppercase tracking-wide text-slate-900 mt-2"><?php echo htmlspecialchars($f['title']); ?></h3>
              <p class="text-xs text-slate-500 mt-1 leading-relaxed"><?php echo htmlspecialchars($f['text']); ?></p>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="bg-white border border-slate-200 rounded-xl p-5 text-center">
            <span class="block text-3xl font-extrabold text-[#15A24A]">98.4%</span>
            <span class="block mt-1 text-[10px] uppercase tracking-widest font-bold text-slate-500">Placement Rate</span>
          </div>
          <div class="bg-white border border-slate-200 rounded-xl p-5 text-center">
            <span class="block text-3xl font-extrabold text-[#15A24A]">250+</span>
            <span class="block mt-1 text-[10px] uppercase tracking-widest font-bold text-slate-500">Live Portfolios</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
