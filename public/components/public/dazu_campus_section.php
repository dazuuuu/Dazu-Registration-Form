<?php
/** Physical campus — ported from Dazu-frontend Home.tsx */
?>
<section class="py-24 bg-[#F8F6F1] border-t border-[#e1e1e1]" id="campus">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
      <div class="lg:col-span-6 space-y-6">
        <span class="text-[#D4AF37] text-xs font-extrabold uppercase tracking-widest px-3 py-1 bg-[#FFE9C8] border border-[#D4AF37]/30 rounded-full inline-block" style="font-family:'Poppins',sans-serif;">Physical Campus Environment</span>
        <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-[#0A4322]" style="font-family:'Poppins',sans-serif;">Our Physical Lab in Thika Town</h2>
        <p class="text-slate-500 text-sm font-medium leading-relaxed">
          Step into <strong>Room 529</strong>, our modern, fully-equipped development space located in the heart of Thika Town. Meet other passionate coders, work under professional mentorship, and access our elite local server infrastructure.
        </p>
        <div class="space-y-3 pt-2">
          <?php foreach ([
            'High-speed fiber optic connection optimized for AWS, Vercel & Firebase deployments.',
            'Ergonomic software workstations paired with external development test screens.',
            'Interactive presentation screens for weekly group code reviews & presentation sessions.',
          ] as $item): ?>
            <div class="flex items-start gap-2 text-slate-700 text-sm font-semibold">
              <svg class="h-5 w-5 text-[#228B22] shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span><?php echo htmlspecialchars($item); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="pt-4 flex gap-4">
          <a href="<?php echo url('about'); ?>" class="px-6 py-3 text-xs font-bold bg-white hover:bg-slate-100 border border-slate-200 text-slate-800 rounded-md transition-colors">Explore Campus Tour</a>
          <a href="<?php echo url('contact'); ?>" class="bf-btn bf-btn--primary px-6 py-3 text-xs">Visit Room 529</a>
        </div>
      </div>

      <div class="lg:col-span-6 bg-white border border-slate-200 rounded-3xl p-8 space-y-6">
        <div class="flex justify-between items-center pb-4 border-b border-slate-100">
          <h3 class="text-xs uppercase tracking-widest font-extrabold text-slate-400">Campus Facilities</h3>
          <span class="text-[10px] uppercase tracking-widest font-bold text-[#15A24A] bg-green-50 px-2.5 py-0.5 rounded-full border border-green-100">Room 529 Active</span>
        </div>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <?php
            $facilities = [
                ['icon' => 'laptop', 'title' => 'Modern Workstations', 'text' => 'Equipped with external dual-monitors and comfortable developer seating.'],
                ['icon' => 'cpu', 'title' => 'Local Lab Servers', 'text' => 'Deploy and test your full-stack software safely inside our local subnets.'],
                ['icon' => 'wifi', 'title' => 'High-Speed Optic', 'text' => 'Fiber internet access ensuring immediate cloud deployments and research.'],
                ['icon' => 'tv', 'title' => 'Interactive Screens', 'text' => 'Used for live code walkthroughs, design sprints, and slide reviews.'],
            ];
            foreach ($facilities as $f): ?>
              <div class="border border-slate-100 rounded-xl p-4 text-left space-y-2">
                <div class="p-2 bg-green-50 text-[#15A24A] rounded-lg w-fit">
                  <?php if ($f['icon'] === 'laptop'): ?>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                  <?php elseif ($f['icon'] === 'cpu'): ?>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                  <?php elseif ($f['icon'] === 'wifi'): ?>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
                  <?php else: ?>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                  <?php endif; ?>
                </div>
                <h4 class="font-extrabold text-xs text-slate-950"><?php echo htmlspecialchars($f['title']); ?></h4>
                <p class="text-[11px] text-slate-500 font-medium leading-normal"><?php echo htmlspecialchars($f['text']); ?></p>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="border border-slate-100 rounded-xl p-4 text-center">
              <span class="block text-2xl font-extrabold text-slate-950">98.4%</span>
              <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Placement rate</span>
            </div>
            <div class="border border-slate-100 rounded-xl p-4 text-center">
              <span class="block text-2xl font-extrabold text-slate-950">250+</span>
              <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Live Portfolios</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
