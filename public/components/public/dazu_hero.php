<?php
/**
 * Homepage hero — matches EDURA reference layout exactly.
 */
$heroImage = 'https://images.unsplash.com/photo-1580894732444-8ecded7900cd?auto=format&fit=crop&w=900&q=80';
$localHero = public_path('assets/app/hero_student_1783619555973-Dfwss1g9.jpg');
if (is_file($localHero)) {
    $heroImage = asset('app/hero_student_1783619555973-Dfwss1g9.jpg');
}

$coursesUrl = url('courses/index.php');
?>
<section class="relative bg-white overflow-hidden" id="dazu-hero">
  <!-- Background decorations -->
  <div class="pointer-events-none absolute inset-0 z-0" aria-hidden="true">
    <!-- Top-left green corner lines -->
    <svg class="absolute top-6 left-4 md:left-12 w-24 h-24 text-[#15A24A]/25" viewBox="0 0 100 100" fill="none">
      <path d="M8 8 H55 M8 8 V55" stroke="currentColor" stroke-width="2"/>
      <path d="M20 20 H45 M20 20 V45" stroke="currentColor" stroke-width="1.5"/>
    </svg>
    <!-- Top-right faint waves -->
    <svg class="absolute top-8 right-6 md:right-16 w-32 h-20 text-slate-200" viewBox="0 0 120 60" fill="none">
      <path d="M0 30 Q30 10 60 30 T120 30" stroke="currentColor" stroke-width="1.5"/>
      <path d="M0 45 Q30 25 60 45 T120 45" stroke="currentColor" stroke-width="1"/>
      <circle cx="95" cy="12" r="2" fill="currentColor"/>
      <circle cx="108" cy="22" r="1.5" fill="currentColor"/>
    </svg>
    <!-- Bottom-left dot cluster -->
    <div class="absolute bottom-16 left-[12%] hidden sm:grid grid-cols-5 gap-1.5 opacity-40">
      <?php for ($i = 0; $i < 15; $i++): ?>
        <span class="w-1.5 h-1.5 rounded-full bg-[#15A24A]"></span>
      <?php endfor; ?>
    </div>
  </div>

  <div class="relative z-10 max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 pt-4 md:pt-5 pb-10 md:pb-14">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-6 items-center">
      <!-- Left -->
      <div class="space-y-6 lg:pr-4">
        <p class="inline-flex items-center gap-2 text-slate-600 text-[11px] font-bold uppercase tracking-[0.18em]">
          <svg class="w-4 h-4 text-[#15A24A] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14M13 6l6 6-6 6"/></svg>
          Online E-Learning Course
        </p>

        <h1 class="font-extrabold leading-[1.08] tracking-tight">
          <span class="block text-[#15A24A] text-4xl sm:text-5xl lg:text-[3.4rem]">Online Education</span>
          <span class="block text-slate-900 text-4xl sm:text-5xl lg:text-[3.4rem] mt-1">Feels Like Real Classroom</span>
        </h1>

        <ul class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm font-semibold text-slate-700">
          <li class="flex items-center gap-2 whitespace-nowrap">
            <span class="w-5 h-5 rounded-full bg-[#15A24A] text-white flex items-center justify-center text-[10px] shrink-0">✓</span>
            Get Certified
          </li>
          <li class="flex items-center gap-2 whitespace-nowrap">
            <span class="w-5 h-5 rounded-full bg-[#15A24A] text-white flex items-center justify-center text-[10px] shrink-0">✓</span>
            Gain Job-ready Skills
          </li>
          <li class="flex items-center gap-2 whitespace-nowrap">
            <span class="w-5 h-5 rounded-full bg-[#15A24A] text-white flex items-center justify-center text-[10px] shrink-0">✓</span>
            Great Life
          </li>
        </ul>

        <div class="flex flex-wrap items-center gap-3 pt-1">
          <a href="<?php echo htmlspecialchars($coursesUrl); ?>"
             class="inline-flex items-center justify-center px-7 sm:px-8 py-3.5 bg-[#15A24A] hover:bg-[#128a3e] text-white text-[11px] sm:text-xs font-extrabold uppercase tracking-widest transition-colors rounded-none">
            Get Started →
          </a>
          <a href="<?php echo htmlspecialchars($coursesUrl); ?>"
             class="inline-flex items-center justify-center px-7 sm:px-8 py-3.5 bg-[#0F172A] hover:bg-black text-white text-[11px] sm:text-xs font-extrabold uppercase tracking-widest transition-colors rounded-none">
            Our Courses →
          </a>
        </div>
      </div>

      <!-- Right -->
      <div class="relative flex justify-center lg:justify-end items-end min-h-[340px] sm:min-h-[420px] lg:min-h-[480px]">
        <!-- Green circle behind head -->
        <div class="absolute top-[6%] left-1/2 -translate-x-1/2 lg:left-[58%] lg:translate-x-0 w-[220px] h-[220px] sm:w-[280px] sm:h-[280px] lg:w-[320px] lg:h-[320px] rounded-full border-[3px] border-[#15A24A] pointer-events-none z-0"></div>

        <img src="<?php echo htmlspecialchars($heroImage); ?>"
             alt="Student at Dazu Hub Institute"
             class="relative z-10 max-h-[360px] sm:max-h-[440px] lg:max-h-[500px] w-auto object-contain object-bottom drop-shadow-sm"
             referrerpolicy="no-referrer">

        <!-- Active Students -->
        <div class="absolute z-20 left-[2%] sm:left-[8%] lg:left-[4%] bottom-[28%] sm:bottom-[32%] bg-white rounded-xl shadow-lg border border-slate-100 px-4 py-3 flex items-center gap-3">
          <span class="w-11 h-11 rounded-full bg-[#15A24A] text-white flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
          </span>
          <div>
            <p class="text-xl font-extrabold text-slate-900 leading-none">16500+</p>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Active Students</p>
          </div>
        </div>

        <!-- Online Video Courses -->
        <div class="absolute z-20 right-[0%] sm:right-[6%] lg:right-[2%] top-[38%] sm:top-[40%] bg-white rounded-xl shadow-lg border border-slate-100 px-4 py-3 flex items-center gap-3">
          <span class="w-11 h-11 rounded-full bg-red-500 text-white flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          </span>
          <div>
            <p class="text-xl font-extrabold text-slate-900 leading-none">7500+</p>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Online Video Courses</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
