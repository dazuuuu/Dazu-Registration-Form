<?php
/**
 * EDURA-style homepage hero — single layout (navbar untouched).
 * Requires: url(), asset()
 */
$heroImage = asset('app/hero_student_1783619555973-Dfwss1g9.jpg');
if (!is_file(public_path('assets/app/hero_student_1783619555973-Dfwss1g9.jpg'))) {
    $heroImage = 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=800&q=80';
}
?>
<section class="relative bg-[#f8faf8] overflow-hidden" id="dazu-hero">
  <div class="absolute top-8 right-[18%] w-28 h-28 rounded-full border-2 border-dashed border-slate-300/70 pointer-events-none hidden lg:block"></div>
  <div class="absolute bottom-24 left-[8%] w-16 h-16 rounded-full border border-slate-200 pointer-events-none hidden md:block"></div>
  <div class="absolute top-1/3 left-[42%] w-3 h-3 rounded-full bg-[#15A24A]/30 pointer-events-none hidden lg:block"></div>

  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 pt-12 pb-0">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-6 items-center min-h-[520px]">
      <!-- Left copy -->
      <div class="space-y-7 z-10">
        <div class="inline-flex items-center gap-2 text-[#15A24A] text-xs font-extrabold uppercase tracking-widest">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14M13 6l6 6-6 6"/></svg>
          Online E-Learning Course
        </div>

        <h1 class="text-4xl sm:text-5xl lg:text-[3.25rem] font-extrabold leading-[1.08] tracking-tight text-slate-900">
          <span class="text-[#15A24A]">Online Education</span><br>
          Feels Like Real<br class="hidden sm:block"> Classroom
        </h1>

        <ul class="space-y-3 text-sm font-semibold text-slate-700">
          <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#15A24A] text-white flex items-center justify-center text-[10px]">✓</span> Get Certified</li>
          <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#15A24A] text-white flex items-center justify-center text-[10px]">✓</span> Gain Job-ready Skills</li>
          <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#15A24A] text-white flex items-center justify-center text-[10px]">✓</span> Great Life</li>
        </ul>

        <div class="flex flex-wrap items-center gap-4 pt-2">
          <a href="<?php echo url('courses/index.php'); ?>"
             class="inline-flex items-center gap-2 px-7 py-3.5 rounded-full bg-[#15A24A] hover:bg-[#128a3e] text-white text-xs font-extrabold uppercase tracking-widest transition-all shadow-md shadow-green-600/20">
            Get Started
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </a>
          <a href="<?php echo url('courses/index.php'); ?>"
             class="inline-flex items-center gap-2 px-7 py-3.5 rounded-full bg-slate-900 hover:bg-slate-800 text-white text-xs font-extrabold uppercase tracking-widest transition-all">
            Our Courses
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </a>
        </div>
      </div>

      <!-- Right visual -->
      <div class="relative flex justify-center lg:justify-end items-center py-8 lg:py-12">
        <div class="absolute w-[300px] h-[300px] sm:w-[380px] sm:h-[380px] rounded-full border-[3px] border-[#15A24A]/80 pointer-events-none"></div>
        <div class="absolute w-[320px] h-[320px] sm:w-[400px] sm:h-[400px] rounded-full border border-dashed border-[#15A24A]/30 pointer-events-none"></div>

        <div class="relative z-10 w-[260px] sm:w-[320px] aspect-[4/5] rounded-[2rem] overflow-hidden shadow-xl border-4 border-white">
          <img src="<?php echo htmlspecialchars($heroImage); ?>" alt="Dazu Hub student"
               class="w-full h-full object-cover object-top" referrerpolicy="no-referrer">
        </div>

        <div class="absolute top-[12%] left-[2%] sm:left-[8%] bg-white rounded-2xl shadow-lg border border-slate-100 px-4 py-3 flex items-center gap-3 z-20">
          <span class="w-10 h-10 rounded-xl bg-[#15A24A]/10 flex items-center justify-center text-[#15A24A] font-extrabold text-sm">👥</span>
          <div>
            <p class="text-lg font-extrabold text-slate-900 leading-none">16500+</p>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wide mt-0.5">Active Students</p>
          </div>
        </div>

        <div class="absolute bottom-[18%] right-[0%] sm:right-[4%] bg-white rounded-2xl shadow-lg border border-slate-100 px-4 py-3 flex items-center gap-3 z-20">
          <span class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center text-red-500 font-extrabold text-sm">▶</span>
          <div>
            <p class="text-lg font-extrabold text-slate-900 leading-none">7500+</p>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wide mt-0.5">Online Video Courses</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats strip -->
  <div class="mt-4 border-t border-[#15A24A]/15 bg-[#eef8f0]">
    <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 py-5">
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">
        <?php
        $stats = [
            ['icon' => '📚', 'label' => '20k+ Online Courses'],
            ['icon' => '♾', 'label' => 'Lifetime Access'],
            ['icon' => '💰', 'label' => 'Value For Money'],
            ['icon' => '🛟', 'label' => 'Lifetime Support'],
            ['icon' => '🤝', 'label' => 'Community Support'],
        ];
        foreach ($stats as $stat): ?>
          <div class="flex flex-col sm:flex-row items-center justify-center gap-2 text-xs font-bold text-slate-700">
            <span class="w-9 h-9 rounded-full bg-[#15A24A] text-white flex items-center justify-center text-sm shadow-sm"><?php echo $stat['icon']; ?></span>
            <span class="uppercase tracking-wide text-[10px] sm:text-xs"><?php echo htmlspecialchars($stat['label']); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
