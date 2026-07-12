<?php
/**
 * Testimonials section — video + text, ported from Dazu-frontend Home.tsx
 * Expects $videoTestimonials and $textTestimonials from index.php
 */
$videoTestimonials = $videoTestimonials ?? [];
$textTestimonials = $textTestimonials ?? [];
$playable = array_values(array_filter($videoTestimonials, static fn($t) => (bool) testimonial_video_embed_url($t)));

$gradients = [
    'from-emerald-500 to-teal-800',
    'from-blue-600 to-indigo-900',
    'from-purple-600 to-fuchsia-950',
    'from-amber-500 to-orange-800',
];
?>
<section class="py-24 bg-white" id="testimonials">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">

    <!-- Video Testimonials -->
    <div class="text-center mb-16">
      <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-100 rounded-full inline-block">Alumni Experience</span>
      <h2 class="text-3xl md:text-4xl font-extrabold text-slate-950 tracking-tight flex items-center justify-center gap-2.5 mt-4">
        <span class="inline-block p-1.5 bg-green-50 text-[#15A24A] rounded-lg border border-green-100">
          <svg class="h-5 w-5 fill-[#15A24A]" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
        </span>
        Video Testimonials
      </h2>
      <p class="text-slate-500 text-sm font-medium leading-relaxed max-w-lg mx-auto mt-3">Watch Dazu Hub Alumni Share Their Learning Experience</p>
    </div>

    <?php if ($playable): ?>
      <div class="grid grid-cols-2 gap-3 sm:gap-8 max-w-5xl mx-auto px-1 sm:px-0">
        <?php foreach ($playable as $i => $t):
            $embed = testimonial_video_embed_url($t);
            $name = $t['customer_name'] ?? 'Alumni';
            $track = $t['service_tag'] ?? 'Tech Track';
            $title = $t['role'] ?? ($t['testimonial_text'] ?? '');
            $gradient = $gradients[$i % count($gradients)];
            $duration = $t['duration'] ?? '0:18';
        ?>
          <button type="button"
                  class="dazu-video-trigger group relative rounded-xl sm:rounded-2xl overflow-hidden border-2 border-slate-950 cursor-pointer transition-all duration-200 hover:-translate-y-1 active:translate-y-0 select-none text-left w-full"
                  style="aspect-ratio:16/16.38"
                  data-embed="<?php echo htmlspecialchars($embed); ?>"
                  data-name="<?php echo htmlspecialchars($name); ?>"
                  data-track="<?php echo htmlspecialchars($track); ?>">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-900 to-slate-950 opacity-90 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center p-2 sm:p-6 text-center">
              <div class="absolute inset-0 bg-gradient-to-br <?php echo htmlspecialchars($gradient); ?> opacity-70 group-hover:opacity-85 transition-opacity"></div>
              <div class="absolute inset-0 bg-slate-950/20 group-hover:bg-slate-950/5 transition-colors"></div>
              <div class="w-8 h-8 sm:w-12 sm:h-12 bg-[#15A24A] text-white rounded-full flex items-center justify-center relative z-10 group-hover:scale-110 transition-transform border border-white/20 shrink-0">
                <svg class="h-2.5 w-2.5 sm:h-4 sm:w-4 fill-white ml-0.5" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
              </div>
              <div class="absolute bottom-1.5 left-1.5 right-1.5 sm:bottom-4 sm:left-4 sm:right-4 text-left z-10 bg-black/50 backdrop-blur-sm p-1.5 sm:p-3 rounded-lg sm:rounded-xl border border-white/5">
                <span class="text-[7px] sm:text-[9px] font-extrabold uppercase tracking-widest text-green-300 block mb-0.5 truncate"><?php echo htmlspecialchars($track); ?></span>
                <h4 class="text-[9px] sm:text-sm font-extrabold text-white leading-tight truncate"><?php echo htmlspecialchars($name); ?></h4>
                <div class="flex items-center justify-between text-[8px] sm:text-[10px] text-slate-300 font-semibold mt-0.5 sm:mt-1">
                  <p class="truncate mr-2 hidden sm:block"><?php echo htmlspecialchars($title); ?></p>
                  <span class="flex items-center gap-0.5 sm:gap-1 shrink-0 bg-white/10 px-1 sm:px-1.5 py-0.5 rounded">
                    <svg class="h-2 w-2 sm:h-3 sm:w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?php echo htmlspecialchars($duration); ?>
                  </span>
                </div>
              </div>
            </div>
          </button>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="max-w-xl mx-auto text-center p-8 bg-slate-50 border border-dashed border-slate-200 rounded-2xl text-slate-500 text-sm">
        Video testimonials will appear once you add YouTube links in
        <a href="<?php echo url('admin/testimonials/create_video.php'); ?>" class="text-[#15A24A] font-bold hover:underline">Admin → Testimonials</a>.
      </div>
    <?php endif; ?>

    <!-- Text Testimonials -->
    <?php if ($textTestimonials): ?>
      <div class="max-w-2xl mx-auto space-y-4 text-center mt-24 mb-16">
        <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold">Proven Outcomes</span>
        <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-slate-950">Student Success Stories</h2>
        <p class="text-slate-500 text-sm font-medium leading-relaxed max-w-lg mx-auto">Read how Dazu Hub alumni launched their high-growth technology careers right here in Thika.</p>
      </div>

      <div class="max-w-3xl mx-auto" id="dazu-text-testimonials">
        <?php foreach ($textTestimonials as $i => $t):
            $initials = $t['customer_initial'] ?? testimonial_initials($t['customer_name'] ?? '');
            $role = $t['role'] ?? '';
            $quote = $t['testimonial_text'] ?? '';
        ?>
          <article class="dazu-text-slide bg-slate-50 border border-slate-200 rounded-3xl p-8 md:p-12 text-left relative overflow-hidden <?php echo $i === 0 ? '' : 'hidden'; ?>" data-index="<?php echo $i; ?>">
            <span class="absolute top-8 right-8 text-8xl font-serif text-slate-200/50 select-none">"</span>
            <div class="space-y-6 relative z-10">
              <p class="text-base sm:text-lg text-slate-700 italic font-medium leading-relaxed">"<?php echo htmlspecialchars($quote); ?>"</p>
              <div class="flex items-center gap-4 pt-4">
                <div class="h-12 w-12 bg-[#15A24A] rounded-full flex items-center justify-center text-white font-extrabold text-sm tracking-wide"><?php echo htmlspecialchars($initials); ?></div>
                <div>
                  <h4 class="text-base font-bold text-slate-950"><?php echo htmlspecialchars($t['customer_name'] ?? ''); ?></h4>
                  <?php if ($role): ?>
                    <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider mt-0.5"><?php echo htmlspecialchars($role); ?></p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>

        <?php if (count($textTestimonials) > 1): ?>
          <div class="flex items-center justify-between mt-8">
            <div class="flex items-center gap-2" id="dazu-text-dots">
              <?php foreach ($textTestimonials as $i => $t): ?>
                <button type="button" class="dazu-text-dot h-2.5 rounded-full transition-all duration-300 <?php echo $i === 0 ? 'w-8 bg-[#15A24A]' : 'w-2.5 bg-slate-200'; ?>" data-index="<?php echo $i; ?>" aria-label="Go to testimonial <?php echo $i + 1; ?>"></button>
              <?php endforeach; ?>
            </div>
            <div class="flex gap-2">
              <button type="button" id="dazu-text-prev" class="p-2.5 border border-slate-200 bg-white hover:bg-slate-50 rounded-lg text-slate-700 transition-colors" aria-label="Previous">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
              </button>
              <button type="button" id="dazu-text-next" class="p-2.5 border border-slate-200 bg-white hover:bg-slate-50 rounded-lg text-slate-700 transition-colors" aria-label="Next">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              </button>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Video modal -->
<div id="dazu-video-modal" class="fixed inset-0 bg-slate-950/90 backdrop-blur-md hidden items-center justify-center z-50 p-4">
  <div class="bg-slate-950 border border-slate-800 rounded-2xl w-full max-w-sm sm:max-w-md overflow-hidden relative flex flex-col aspect-[9/16] shadow-2xl">
    <div class="flex items-center justify-between p-4 border-b border-slate-800 bg-slate-950 text-white shrink-0">
      <div class="text-left">
        <span id="dazu-video-modal-track" class="text-[10px] uppercase tracking-widest text-[#15A24A] font-extrabold"></span>
        <h3 id="dazu-video-modal-title" class="font-extrabold text-xs sm:text-sm text-slate-100"></h3>
      </div>
      <button type="button" id="dazu-video-modal-close" class="p-1.5 hover:bg-slate-800 rounded-lg text-slate-400 hover:text-white" aria-label="Close">✕</button>
    </div>
    <div class="relative flex-1 bg-black">
      <iframe id="dazu-video-modal-iframe" class="absolute inset-0 w-full h-full border-0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen title="Video testimonial"></iframe>
    </div>
  </div>
</div>

<script>
(function(){
  var modal = document.getElementById('dazu-video-modal');
  var iframe = document.getElementById('dazu-video-modal-iframe');
  var closeBtn = document.getElementById('dazu-video-modal-close');
  document.querySelectorAll('.dazu-video-trigger').forEach(function(btn){
    btn.addEventListener('click', function(){
      var embed = btn.getAttribute('data-embed');
      var name = btn.getAttribute('data-name');
      var track = btn.getAttribute('data-track');
      document.getElementById('dazu-video-modal-track').textContent = track;
      document.getElementById('dazu-video-modal-title').textContent = name + ' — Alumni Story';
      iframe.src = embed + (embed.indexOf('?') >= 0 ? '&' : '?') + 'autoplay=1&modestbranding=1&rel=0';
      modal.classList.remove('hidden'); modal.classList.add('flex');
    });
  });
  if(closeBtn) closeBtn.addEventListener('click', function(){
    modal.classList.add('hidden'); modal.classList.remove('flex');
    iframe.removeAttribute('src');
  });

  var slides = document.querySelectorAll('.dazu-text-slide');
  var dots = document.querySelectorAll('.dazu-text-dot');
  if(slides.length > 1){
    var cur = 0;
    function show(n){
      cur = (n + slides.length) % slides.length;
      slides.forEach(function(s,i){ s.classList.toggle('hidden', i !== cur); });
      dots.forEach(function(d,i){
        d.classList.toggle('w-8', i === cur); d.classList.toggle('bg-[#15A24A]', i === cur);
        d.classList.toggle('w-2.5', i !== cur); d.classList.toggle('bg-slate-200', i !== cur);
      });
    }
    var prev = document.getElementById('dazu-text-prev');
    var next = document.getElementById('dazu-text-next');
    if(prev) prev.addEventListener('click', function(){ show(cur-1); });
    if(next) next.addEventListener('click', function(){ show(cur+1); });
    dots.forEach(function(d){ d.addEventListener('click', function(){ show(parseInt(d.dataset.index,10)); }); });
    setInterval(function(){ show(cur+1); }, 6000);
  }
})();
</script>
