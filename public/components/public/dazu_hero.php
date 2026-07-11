<?php
/**
 * Homepage hero — matches Dazu/EDURA design (navbar untouched).
 */
$heroImage = asset('app/hero_student_1783619555973-Dfwss1g9.jpg');
if (!is_file(public_path('assets/app/hero_student_1783619555973-Dfwss1g9.jpg'))) {
    $heroImage = 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=800&q=80';
}

if (!function_exists('dazu_hero_cta')) {
    function dazu_hero_cta(string $label, string $href, string $variant = 'green'): void {
        $green = $variant === 'green';
        ?>
        <a href="<?php echo htmlspecialchars($href); ?>"
           class="inline-flex items-stretch overflow-hidden rounded-md shadow-sm transition-opacity hover:opacity-95 <?php echo $green ? 'bg-[#15A24A]' : 'bg-[#0F172A]'; ?>">
          <span class="px-5 sm:px-7 py-3.5 text-[11px] sm:text-xs font-extrabold uppercase tracking-widest text-white flex items-center">
            <?php echo htmlspecialchars($label); ?>
          </span>
          <span class="w-11 sm:w-12 flex items-center justify-center <?php echo $green ? 'bg-[#128a3e]' : 'bg-black'; ?>">
            <span class="w-7 h-7 bg-white rounded-full flex items-center justify-center text-[#15A24A]">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </span>
          </span>
        </a>
        <?php
    }
}
?>
<section class="relative bg-white overflow-hidden" id="dazu-hero">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 pt-4 md:pt-6 pb-0">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-10 items-center">
      <div class="space-y-5 z-10">
        <div class="inline-flex items-center gap-2 text-[#15A24A] text-[11px] font-extrabold uppercase tracking-widest">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14M13 6l6 6-6 6"/></svg>
          Online E-Learning Course
        </div>

        <h1 class="text-3xl sm:text-4xl lg:text-[2.85rem] font-extrabold leading-[1.05] tracking-tight text-slate-900 uppercase">
          <span class="text-[#15A24A] normal-case">Online Education</span><br>
          Feels Like Real Classroom
        </h1>

        <ul class="space-y-2.5 text-sm font-semibold text-slate-700">
          <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#15A24A] text-white flex items-center justify-center text-[10px] shrink-0">✓</span> Get Certified</li>
          <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#15A24A] text-white flex items-center justify-center text-[10px] shrink-0">✓</span> Gain Job-ready Skills</li>
          <li class="flex items-center gap-3"><span class="w-5 h-5 rounded-full bg-[#15A24A] text-white flex items-center justify-center text-[10px] shrink-0">✓</span> Great Life</li>
        </ul>

        <div class="flex flex-wrap items-center gap-3 pt-1">
          <?php dazu_hero_cta('Get Started', url('courses/index.php'), 'green'); ?>
          <?php dazu_hero_cta('Our Courses', url('courses/index.php'), 'dark'); ?>
        </div>
      </div>

      <div class="relative flex justify-center lg:justify-end items-center py-2 lg:py-4 min-h-[300px] sm:min-h-[360px]">
        <div class="absolute w-[260px] h-[260px] sm:w-[340px] sm:h-[340px] rounded-full border-[3px] border-[#15A24A] pointer-events-none"></div>

        <div class="relative z-10 w-[220px] sm:w-[280px] lg:w-[300px] aspect-[4/5] rounded-2xl overflow-hidden shadow-lg border-4 border-white">
          <img src="<?php echo htmlspecialchars($heroImage); ?>" alt="Dazu Hub student"
               class="w-full h-full object-cover object-top" referrerpolicy="no-referrer">
        </div>

        <div class="absolute top-[8%] left-[0%] sm:left-[6%] bg-white rounded-lg shadow-md border border-slate-100 px-3 py-2.5 flex items-center gap-2.5 z-20 max-w-[170px]">
          <span class="w-9 h-9 rounded-md bg-violet-100 text-violet-600 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          </span>
          <div class="min-w-0">
            <p class="text-base font-extrabold text-slate-900 leading-none">16500+</p>
            <p class="text-[9px] font-bold text-violet-600 uppercase tracking-wide mt-0.5">Active Students</p>
          </div>
        </div>

        <div class="absolute bottom-[14%] right-[0%] sm:right-[4%] bg-white rounded-lg shadow-md border border-slate-100 px-3 py-2.5 flex items-center gap-2.5 z-20 max-w-[190px]">
          <span class="w-9 h-9 rounded-md bg-red-50 text-red-500 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
          </span>
          <div class="min-w-0">
            <p class="text-base font-extrabold text-slate-900 leading-none">7500+</p>
            <p class="text-[9px] font-bold text-violet-600 uppercase tracking-wide mt-0.5">Online Video Courses</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Stats strip -->
  <div class="border-t border-[#15A24A]/10 bg-[#eef8f0] mt-2">
    <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 py-4">
      <div class="flex flex-wrap items-center justify-center lg:justify-between gap-x-6 gap-y-3">
        <?php
        $stats = [
            ['label' => '20k+ Online Courses', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
            ['label' => 'Lifetime Access', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Value For Money', 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Lifetime Support', 'icon' => 'M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Community Support', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ];
        foreach ($stats as $stat): ?>
          <div class="flex items-center gap-2.5 shrink-0">
            <span class="w-9 h-9 rounded-full bg-[#15A24A] text-white flex items-center justify-center shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $stat['icon']; ?>"/></svg>
            </span>
            <span class="text-[10px] sm:text-xs font-bold text-slate-700 uppercase tracking-wide whitespace-nowrap"><?php echo htmlspecialchars($stat['label']); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
