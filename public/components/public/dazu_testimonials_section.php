<?php
/**
 * Testimonials section — video + text, ported from Dazu-frontend Home.tsx
 * Expects $videoTestimonials and $textTestimonials from index.php
 */
$videoTestimonials = $videoTestimonials ?? [];
$textTestimonials = $textTestimonials ?? [];
$playable = array_values(array_filter($videoTestimonials, static fn($t) => (bool) testimonial_video_embed_url($t)));
if (!$playable && function_exists('testimonial_static_video_fallbacks')) {
    $playable = array_values(array_filter(
        testimonial_static_video_fallbacks(),
        static fn($t) => (bool) testimonial_video_embed_url($t)
    ));
}

$gradients = [
    'linear-gradient(to bottom right, #10b981, #115e59)',
    'linear-gradient(to bottom right, #2563eb, #312e81)',
    'linear-gradient(to bottom right, #9333ea, #4a044e)',
    'linear-gradient(to bottom right, #f59e0b, #9a3412)',
];
?>
<section class="py-24 bg-white" id="testimonials">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">

    <!-- Video Testimonials -->
    <div class="text-center mb-16">
      <span class="text-[#D4AF37] text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-[#FFE9C8] border border-[#D4AF37]/30 rounded-full inline-block" style="font-family:'Poppins',sans-serif;">Alumni Experience</span>
      <h2 class="text-3xl md:text-4xl font-extrabold text-[#0A4322] tracking-tight flex items-center justify-center gap-2.5 mt-4" style="font-family:'Poppins',sans-serif;">
        <span class="inline-block p-1.5 bg-[#FFE9C8] text-[#D4AF37] rounded-lg border border-[#D4AF37]/30">
          <svg class="h-5 w-5 fill-[#D4AF37]" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
        </span>
        Video Testimonials
      </h2>
      <p class="text-slate-500 text-sm font-medium leading-relaxed max-w-lg mx-auto mt-3">Watch Dazu Hub Alumni Share Their Learning Experience</p>
    </div>

    <?php if ($playable): ?>
      <div id="dazu-video-row">
        <?php foreach ($playable as $i => $t):
            $embed = testimonial_video_embed_url($t);
            $thumb = testimonial_video_thumb($t);
            $name = $t['customer_name'] ?? 'Alumni';
            $track = $t['service_tag'] ?? 'Tech Track';
            $title = $t['role'] ?? ($t['testimonial_text'] ?? '');
            $gradient = $gradients[$i % count($gradients)];
            $duration = $t['duration'] ?? '0:18';
            $embedQs = (str_contains($embed, '?') ? '&' : '?') . 'autoplay=1&modestbranding=1&rel=0';
        ?>
          <article class="dazu-vcard group rounded-xl overflow-hidden border-2 border-slate-950 bg-slate-950 shadow-sm hover:-translate-y-0.5 transition-transform"
                   data-dazu-vcard
                   data-embed="<?php echo htmlspecialchars($embed); ?>"
                   data-name="<?php echo htmlspecialchars($name); ?>"
                   data-track="<?php echo htmlspecialchars($track); ?>">
            <!-- Inline player -->
            <div class="dazu-vplayer hidden">
              <iframe class="dazu-viframe w-full h-full border-0"
                      data-src="<?php echo htmlspecialchars($embed . $embedQs); ?>"
                      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                      allowfullscreen
                      title="<?php echo htmlspecialchars($name); ?> testimonial"></iframe>
              <div class="absolute top-1.5 right-1.5 z-30 flex items-center gap-1">
                <button type="button" class="dazu-vpause w-7 h-7 rounded-full bg-black/70 text-white text-[10px] hover:bg-black/90 flex items-center justify-center" aria-label="Pause" title="Pause">
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/></svg>
                </button>
                <button type="button" class="dazu-vexpand w-7 h-7 rounded-full bg-black/70 text-white hover:bg-black/90 flex items-center justify-center" aria-label="Expand" title="Expand">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                </button>
              </div>
            </div>

            <!-- Poster -->
            <div class="dazu-vposter">
              <?php if ($thumb): ?>
                <img src="<?php echo htmlspecialchars($thumb); ?>" alt="<?php echo htmlspecialchars($name); ?>"
                     class="absolute inset-0 w-full h-full object-cover" loading="lazy">
              <?php else: ?>
                <div class="absolute inset-0" style="background:<?php echo htmlspecialchars($gradient); ?>"></div>
              <?php endif; ?>
              <div class="absolute inset-0" style="background:linear-gradient(to bottom right, rgba(15,23,42,0.8), rgba(2,6,23,0.9))"></div>

              <button type="button" class="dazu-vplay absolute inset-0 flex items-center justify-center z-10"
                      aria-label="Play <?php echo htmlspecialchars($name); ?>">
                <span class="w-9 h-9 bg-[#15A24A] text-white rounded-full flex items-center justify-center group-hover:scale-110 transition-transform border border-white/20 shadow-lg">
                  <svg class="w-3.5 h-3.5 fill-white ml-0.5" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                </span>
              </button>

              <div class="absolute bottom-0 left-0 right-0 z-10 p-2 bg-black/55 backdrop-blur-sm border-t border-white/5">
                <span class="text-[7px] font-extrabold uppercase tracking-widest text-green-300 block truncate"><?php echo htmlspecialchars($track); ?></span>
                <h4 class="text-[10px] font-extrabold text-white leading-tight truncate"><?php echo htmlspecialchars($name); ?></h4>
                <div class="flex items-center justify-between mt-0.5">
                  <p class="text-[8px] text-slate-300 truncate mr-1 hidden sm:block"><?php echo htmlspecialchars($title); ?></p>
                  <span class="flex items-center gap-0.5 text-[8px] text-slate-300 shrink-0 bg-white/10 px-1 py-0.5 rounded">
                    <svg class="w-2 h-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <?php echo htmlspecialchars($duration); ?>
                  </span>
                </div>
              </div>
            </div>
          </article>
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

<!-- Expanded video overlay -->
<div id="dazu-video-expand" class="fixed inset-0 bg-slate-950/90 backdrop-blur-md hidden items-center justify-center z-50 p-4">
  <div class="bg-slate-950 border border-slate-800 rounded-2xl w-full max-w-sm sm:max-w-md overflow-hidden relative flex flex-col aspect-[9/16] shadow-2xl">
    <div class="flex items-center justify-between p-3 border-b border-slate-800 bg-slate-950 text-white shrink-0">
      <div class="text-left min-w-0">
        <span id="dazu-video-expand-track" class="text-[10px] uppercase tracking-widest text-[#15A24A] font-extrabold"></span>
        <h3 id="dazu-video-expand-title" class="font-extrabold text-xs sm:text-sm text-slate-100 truncate"></h3>
      </div>
      <button type="button" id="dazu-video-expand-close" class="p-1.5 hover:bg-slate-800 rounded-lg text-slate-400 hover:text-white shrink-0 ml-2" aria-label="Close expanded video">✕</button>
    </div>
    <div class="relative flex-1 bg-black">
      <iframe id="dazu-video-expand-iframe" class="absolute inset-0 w-full h-full border-0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen title="Expanded video testimonial"></iframe>
    </div>
  </div>
</div>

<script>
(function(){
  var expandModal = document.getElementById('dazu-video-expand');
  var expandIframe = document.getElementById('dazu-video-expand-iframe');
  var expandClose = document.getElementById('dazu-video-expand-close');
  var activeCard = null;

  function stopCard(card, keepExpanded) {
    if (!card) return;
    var player = card.querySelector('.dazu-vplayer');
    var poster = card.querySelector('.dazu-vposter');
    var iframe = card.querySelector('.dazu-viframe');
    if (player) player.classList.add('hidden');
    if (poster) poster.classList.remove('hidden');
    if (iframe) iframe.removeAttribute('src');
    if (!keepExpanded && activeCard === card) activeCard = null;
  }

  function stopAll(except) {
    document.querySelectorAll('[data-dazu-vcard]').forEach(function(card){
      if (card !== except) stopCard(card);
    });
  }

  function playCard(card) {
    stopAll(card);
    var player = card.querySelector('.dazu-vplayer');
    var poster = card.querySelector('.dazu-vposter');
    var iframe = card.querySelector('.dazu-viframe');
    if (!player || !iframe) return;
    var src = iframe.getAttribute('data-src');
    if (src) iframe.setAttribute('src', src);
    player.classList.remove('hidden');
    if (poster) poster.classList.add('hidden');
    activeCard = card;
  }

  function openExpand(card) {
    var embed = card.getAttribute('data-embed');
    var name = card.getAttribute('data-name');
    var track = card.getAttribute('data-track');
    if (!embed || !expandModal || !expandIframe) return;
    document.getElementById('dazu-video-expand-track').textContent = track;
    document.getElementById('dazu-video-expand-title').textContent = name + ' — Alumni Story';
    var qs = embed.indexOf('?') >= 0 ? '&' : '?';
    expandIframe.src = embed + qs + 'autoplay=1&modestbranding=1&rel=0';
    expandModal.classList.remove('hidden');
    expandModal.classList.add('flex');
    expandModal.dataset.fromCard = '1';
  }

  function closeExpand() {
    if (!expandModal) return;
    expandModal.classList.add('hidden');
    expandModal.classList.remove('flex');
    if (expandIframe) expandIframe.removeAttribute('src');
  }

  document.querySelectorAll('[data-dazu-vcard]').forEach(function(card){
    var playBtn = card.querySelector('.dazu-vplay');
    var pauseBtn = card.querySelector('.dazu-vpause');
    var expandBtn = card.querySelector('.dazu-vexpand');

    if (playBtn) {
      playBtn.addEventListener('click', function(e){
        e.stopPropagation();
        playCard(card);
      });
    }
    if (pauseBtn) {
      pauseBtn.addEventListener('click', function(e){
        e.stopPropagation();
        stopCard(card);
      });
    }
    if (expandBtn) {
      expandBtn.addEventListener('click', function(e){
        e.stopPropagation();
        if (!card.querySelector('.dazu-vplayer') || card.querySelector('.dazu-vplayer').classList.contains('hidden')) {
          playCard(card);
        }
        openExpand(card);
      });
    }
  });

  if (expandClose) {
    expandClose.addEventListener('click', function(){
      closeExpand();
    });
  }
  if (expandModal) {
    expandModal.addEventListener('click', function(e){
      if (e.target === expandModal) closeExpand();
    });
  }

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
