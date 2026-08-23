<?php
/** Homepage hero — ported from Dazu-frontend Home.tsx carousel */
$heroImage1 = 'https://images.unsplash.com/photo-1580894732444-8ecded7900cd?auto=format&fit=crop&w=900&q=80';
$localHero = public_path('assets/app/hero_student_1783619555973-Dfwss1g9.jpg');
if (is_file($localHero)) {
    $heroImage1 = asset('app/hero_student_1783619555973-Dfwss1g9.jpg');
}

$heroSlides = [
    [
        'badge' => 'Learn. Build. Succeed.',
        'title1' => 'Upgrade Your Tech Skills.',
        'titleAccent' => 'Build Your Future.',
        'description' => 'Practical, hands-on training in high-demand tech skills to launch your career.',
        'image' => $heroImage1,
        'imageAlt' => 'Dazu Hub Tech Skills Student Profile',
        'cta1Text' => 'Explore Courses',
        'cta1Url' => url('courses/index.php'),
        'cta2Text' => 'Watch Overview',
        'cta2Url' => '#',
        'showPlayBtn' => true,
        'imagePosition' => 'center top',
    ],
    [
        'badge' => 'Future-Proof Your Career',
        'title1' => 'Master AI & Analytics.',
        'titleAccent' => 'Lead the Innovation.',
        'description' => 'Empower your business or career with advanced Data Analytics and automated AI pipelines.',
        'image' => 'https://i.pinimg.com/736x/4f/d6/5e/4fd65e9c73665937a0f7c5990de5ae35.jpg',
        'imageAlt' => 'AI & Data Analytics Course',
        'cta1Text' => 'Apply Online Now',
        'cta1Url' => url('apply/index.php'),
        'cta2Text' => 'Explore Courses',
        'cta2Url' => url('courses/index.php'),
        'showPlayBtn' => false,
        'imagePosition' => 'center',
    ],
];
$overviewVideoId = 'EUY5XaeDsCI';
?>
<section class="relative min-h-[85vh] flex items-center pt-10 pb-24 bg-white overflow-hidden" id="dazu-hero">
  <div class="absolute top-10 right-10 w-24 h-24 opacity-25 bg-[radial-gradient(#CBD5E1_2px,transparent_2px)] [background-size:12px_12px] pointer-events-none hidden md:block" aria-hidden="true"></div>
  <div class="absolute bottom-10 left-10 w-24 h-24 opacity-25 bg-[radial-gradient(#CBD5E1_2px,transparent_2px)] [background-size:12px_12px] pointer-events-none hidden md:block" aria-hidden="true"></div>

  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 w-full z-10 relative">
    <div id="dazu-hero-slides">
      <?php foreach ($heroSlides as $i => $slide): ?>
        <div class="dazu-hero-panel grid grid-cols-1 lg:grid-cols-12 gap-12 items-center <?php echo $i === 0 ? '' : 'hidden'; ?>" data-slide="<?php echo $i; ?>">
          <div class="lg:col-span-6 space-y-8 text-left">
            <div class="inline-block">
              <span class="text-[#15A24A] font-extrabold text-sm tracking-wider uppercase"><?php echo htmlspecialchars($slide['badge']); ?></span>
              <span class="block h-[3px] w-12 bg-[#15A24A] mt-1 rounded-full"></span>
            </div>
            <h1 class="text-2xl sm:text-5xl md:text-6xl font-extrabold text-slate-950 tracking-tight leading-[1.15] font-sans">
              <span class="block"><?php echo htmlspecialchars($slide['title1']); ?></span>
              <span class="block text-[#15A24A]"><?php echo htmlspecialchars($slide['titleAccent']); ?></span>
            </h1>
            <p class="text-sm sm:text-lg text-slate-500 font-medium leading-relaxed max-w-lg"><?php echo htmlspecialchars($slide['description']); ?></p>
            <div class="flex flex-row items-center gap-2 sm:gap-5 pt-2">
              <a href="<?php echo htmlspecialchars($slide['cta1Url']); ?>"
                 class="px-3.5 py-3 sm:px-7 sm:py-4 text-[10px] sm:text-xs font-extrabold tracking-widest uppercase text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-md transition-all duration-200 text-center flex items-center justify-center gap-1.5 sm:gap-2 hover:-translate-y-0.5">
                <?php echo htmlspecialchars($slide['cta1Text']); ?>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
              </a>
              <?php if ($slide['showPlayBtn']): ?>
                <button type="button" id="dazu-hero-overview-btn"
                        class="px-3.5 py-3 sm:px-7 sm:py-4 text-[10px] sm:text-xs font-extrabold tracking-widest uppercase text-slate-900 border-2 border-[#15A24A] hover:bg-[#15A24A]/5 rounded-md transition-all duration-200 text-center flex items-center justify-center gap-1.5 sm:gap-2 hover:-translate-y-0.5">
                  <svg class="h-3.5 w-3.5 fill-slate-900 ml-0.5" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                  <?php echo htmlspecialchars($slide['cta2Text']); ?>
                </button>
              <?php else: ?>
                <a href="<?php echo htmlspecialchars($slide['cta2Url']); ?>"
                   class="px-3.5 py-3 sm:px-7 sm:py-4 text-[10px] sm:text-xs font-extrabold tracking-widest uppercase text-slate-900 border-2 border-[#15A24A] hover:bg-[#15A24A]/5 rounded-md transition-all duration-200 text-center flex items-center justify-center gap-1.5 sm:gap-2 hover:-translate-y-0.5">
                  <?php echo htmlspecialchars($slide['cta2Text']); ?>
                </a>
              <?php endif; ?>
            </div>
            <div class="pt-8 border-t border-slate-100 flex items-center gap-6 text-xs font-semibold text-slate-400">
              <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#15A24A]"></span>Room 529 Physical Labs</span>
              <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-[#15A24A]"></span>Thika Town Campus</span>
            </div>
          </div>

          <div class="lg:col-span-6 relative flex justify-center items-center py-8">
            <div class="absolute inset-0 pointer-events-none z-20" aria-hidden="true">
              <svg class="absolute top-[5%] left-[10%] w-[120px] h-[60px] text-slate-900 hidden md:block" viewBox="0 0 100 50" fill="none"><path d="M10 40 C30 10, 70 10, 90 40" stroke="currentColor" stroke-width="2.5" stroke-dasharray="3 3"/><polygon points="90,40 82,32 92,30" fill="currentColor"/></svg>
              <svg class="absolute bottom-[5%] left-[20%] w-[180px] h-[80px] text-[#15A24A] hidden md:block" viewBox="0 0 100 50" fill="none"><path d="M90 10 C70 40, 30 40, 10 10" stroke="currentColor" stroke-width="2.5"/><polygon points="10,10 18,18 8,20" fill="currentColor"/></svg>
              <div class="absolute top-[18%] left-[18%] animate-bounce"><span class="text-slate-400 font-mono text-xs font-extrabold">{ }</span></div>
              <div class="absolute top-[10%] right-[10%] animate-bounce" style="animation-delay:500ms"><span class="text-slate-400 font-mono text-xs font-extrabold">&lt;/&gt;</span></div>
              <div class="absolute bottom-[40%] left-[2%] text-[#F7DF1E] font-extrabold text-xs -rotate-12 animate-bounce" style="animation-delay:200ms">JS</div>
              <div class="absolute bottom-[28%] right-[2%] text-[#1572B6] font-extrabold text-xs rotate-[8deg] animate-bounce" style="animation-delay:300ms">CSS</div>
            </div>
            <div class="relative w-[280px] sm:w-[360px] h-[280px] sm:h-[360px] bg-[#15A24A] rounded-[48px] -rotate-6 overflow-visible flex items-center justify-center">
              <div class="absolute inset-2 border-2 border-dashed border-white/30 rounded-[42px]"></div>
              <div class="absolute inset-0 rounded-[48px] overflow-hidden rotate-6 hover:rotate-0 transition-transform duration-500">
                <img src="<?php echo htmlspecialchars($slide['image']); ?>" alt="<?php echo htmlspecialchars($slide['imageAlt']); ?>"
                     class="w-full h-full object-cover" style="object-position:<?php echo htmlspecialchars($slide['imagePosition']); ?>"
                     referrerpolicy="no-referrer">
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="absolute bottom-[-10px] left-1/2 -translate-x-1/2 z-20 flex items-center gap-4">
      <button type="button" id="dazu-hero-prev" class="p-2 rounded-full border border-slate-200 bg-white/90 backdrop-blur-sm hover:bg-white text-slate-700 hover:text-[#15A24A] transition-all shadow-sm" aria-label="Previous slide">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <div class="flex gap-2" id="dazu-hero-dots">
        <?php foreach ($heroSlides as $i => $slide): ?>
          <button type="button" class="dazu-hero-dot h-2.5 rounded-full transition-all duration-300 <?php echo $i === 0 ? 'w-6 bg-[#15A24A]' : 'w-2.5 bg-slate-200 hover:bg-slate-300'; ?>" data-slide="<?php echo $i; ?>" aria-label="Go to slide <?php echo $i + 1; ?>"></button>
        <?php endforeach; ?>
      </div>
      <button type="button" id="dazu-hero-next" class="p-2 rounded-full border border-slate-200 bg-white/90 backdrop-blur-sm hover:bg-white text-slate-700 hover:text-[#15A24A] transition-all shadow-sm" aria-label="Next slide">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </button>
    </div>
  </div>
</section>

<!-- Overview video modal -->
<div id="dazu-overview-modal" class="fixed inset-0 bg-slate-950/90 backdrop-blur-md hidden items-center justify-center z-50 p-4">
  <div class="bg-slate-950 border border-slate-800 rounded-2xl w-full max-w-sm sm:max-w-md overflow-hidden relative flex flex-col aspect-[9/16] shadow-2xl">
    <div class="flex items-center justify-between p-4 border-b border-slate-800 text-white shrink-0">
      <div><span class="text-[10px] uppercase tracking-widest text-[#15A24A] font-extrabold">Dazu Hub</span><h3 class="font-extrabold text-sm">Institute Overview</h3></div>
      <button type="button" id="dazu-overview-close" class="p-1.5 hover:bg-slate-800 rounded-lg text-slate-400 hover:text-white" aria-label="Close">✕</button>
    </div>
    <div class="relative flex-1 bg-black">
      <iframe id="dazu-overview-iframe" class="absolute inset-0 w-full h-full border-0" title="Dazu Hub Overview" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    </div>
  </div>
</div>

<script>
(function(){
  var panels = document.querySelectorAll('.dazu-hero-panel');
  var dots = document.querySelectorAll('.dazu-hero-dot');
  var cur = 0, total = panels.length;
  var overviewBtn = document.getElementById('dazu-hero-overview-btn');
  var overviewModal = document.getElementById('dazu-overview-modal');
  var overviewClose = document.getElementById('dazu-overview-close');
  var overviewIframe = document.getElementById('dazu-overview-iframe');
  var videoId = <?php echo json_encode($overviewVideoId); ?>;

  function show(n){
    cur = (n + total) % total;
    panels.forEach(function(p,i){ p.classList.toggle('hidden', i !== cur); if(i===cur){ p.classList.add('dazu-hero-slide'); setTimeout(function(){p.classList.remove('dazu-hero-slide');},600);} });
    dots.forEach(function(d,i){
      d.classList.toggle('w-6', i === cur); d.classList.toggle('bg-[#15A24A]', i === cur);
      d.classList.toggle('w-2.5', i !== cur); d.classList.toggle('bg-slate-200', i !== cur);
    });
  }
  document.getElementById('dazu-hero-prev').addEventListener('click', function(){ show(cur-1); });
  document.getElementById('dazu-hero-next').addEventListener('click', function(){ show(cur+1); });
  dots.forEach(function(d){ d.addEventListener('click', function(){ show(parseInt(d.dataset.slide,10)); }); });
  setInterval(function(){ show(cur+1); }, 8000);

  if(overviewBtn && overviewModal){
    overviewBtn.addEventListener('click', function(){
      overviewIframe.src = 'https://www.youtube.com/embed/' + videoId + '?autoplay=1&modestbranding=1&rel=0';
      overviewModal.classList.remove('hidden'); overviewModal.classList.add('flex');
    });
  }
  if(overviewClose && overviewModal){
    overviewClose.addEventListener('click', function(){
      overviewModal.classList.add('hidden'); overviewModal.classList.remove('flex');
      overviewIframe.removeAttribute('src');
    });
  }
})();
</script>
