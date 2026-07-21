<?php
/** Video testimonials — compact portrait cards, inline play on click */
$videoTestimonials = $videoTestimonials ?? [];
$playable = array_values(array_filter($videoTestimonials, static fn($t) => (bool) testimonial_video_embed_url($t)));
?>
<section class="bg-slate-50 border-y border-slate-100 py-16" id="video-testimonials">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="text-center max-w-2xl mx-auto mb-10">
      <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold">Alumni Experience</span>
      <h2 class="text-3xl md:text-4xl font-extrabold text-slate-950 mt-3 uppercase tracking-tight flex items-center justify-center gap-2">
        <span class="text-[#15A24A]">▶</span> Video Testimonials
      </h2>
      <p class="text-slate-600 font-medium mt-4">Watch Dazu Hub alumni share their learning experience.</p>
    </div>

    <?php if (!$playable): ?>
      <div class="max-w-xl mx-auto text-center p-8 bg-white border border-dashed border-slate-200 rounded-2xl text-slate-500 text-sm">
        Video testimonials will appear here once you add YouTube links in
        <a href="<?php echo url('admin/testimonials/create_video.php'); ?>" class="text-[#15A24A] font-bold hover:underline">Admin → Testimonials</a>.
      </div>
    <?php else: ?>
      <div id="dazu-video-row" class="flex gap-5 md:gap-6 overflow-x-auto pb-4 snap-x scroll-smooth items-start" style="-webkit-overflow-scrolling: touch;">
        <?php foreach ($playable as $i => $t):
            $embed = testimonial_video_embed_url($t);
            $thumb = testimonial_video_thumb($t);
            $name = $t['customer_name'] ?? 'Alumni';
            $tag = $t['service_tag'] ?? 'Tech Track';
            $role = $t['role'] ?? '';
            $gradients = [
                'from-emerald-600 via-teal-700 to-slate-900',
                'from-blue-700 via-indigo-800 to-slate-950',
                'from-purple-700 via-fuchsia-800 to-slate-950',
                'from-amber-600 via-orange-700 to-slate-900',
            ];
            $gradient = $t['bg_class'] ?? $gradients[$i % count($gradients)];
            if (str_starts_with($gradient, 'from-')) {
                $gradient = 'bg-gradient-to-br ' . $gradient;
            }
        ?>
          <article class="dazu-video-card group relative flex-shrink-0 w-[220px] sm:w-[240px] snap-start rounded-xl overflow-hidden border border-slate-200/80 bg-white shadow-md hover:shadow-lg transition-shadow"
                   data-dazu-video-card>
            <!-- Player layer (hidden until play) -->
            <div class="dazu-video-player hidden absolute inset-0 z-20 bg-black aspect-[4/5]">
              <iframe class="w-full h-full border-0"
                      data-src="<?php echo htmlspecialchars($embed . (str_contains($embed, '?') ? '&' : '?') . 'autoplay=1'); ?>"
                      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                      allowfullscreen
                      title="<?php echo htmlspecialchars($name); ?> testimonial"></iframe>
              <button type="button"
                      class="dazu-video-stop absolute top-2 right-2 z-30 w-7 h-7 rounded-full bg-black/70 text-white text-xs hover:bg-black/90 flex items-center justify-center"
                      aria-label="Stop video">✕</button>
            </div>

            <!-- Poster / card face -->
            <div class="dazu-video-poster relative w-full aspect-[4/5]">
              <?php if ($thumb): ?>
                <img src="<?php echo htmlspecialchars($thumb); ?>" alt="<?php echo htmlspecialchars($name); ?>"
                     class="absolute inset-0 w-full h-full object-cover" loading="lazy">
              <?php else: ?>
                <div class="absolute inset-0 <?php echo htmlspecialchars($gradient); ?>"></div>
              <?php endif; ?>
              <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>

              <div class="absolute top-3 left-3 flex items-center gap-1.5 z-10">
                <span class="px-2 py-0.5 rounded-full bg-[#15A24A] text-white text-[9px] font-extrabold uppercase tracking-wider leading-none">Alumni</span>
                <?php if (!empty($t['rating'])): ?>
                  <span class="px-1.5 py-0.5 rounded-full bg-white/20 text-white text-[9px] font-bold backdrop-blur-sm"><?php echo (int) $t['rating']; ?>★</span>
                <?php endif; ?>
              </div>

              <button type="button"
                      class="dazu-video-play absolute inset-0 flex items-center justify-center z-10 group/play"
                      aria-label="Play <?php echo htmlspecialchars($name); ?> video">
                <span class="w-12 h-12 rounded-full bg-[#15A24A] text-white flex items-center justify-center shadow-xl ring-2 ring-white/40 group-hover/play:scale-110 transition-transform">
                  <svg class="w-5 h-5 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                </span>
              </button>

              <div class="absolute bottom-0 left-0 right-0 z-10">
                <div class="px-3 pt-8 pb-2">
                  <span class="block text-[9px] uppercase tracking-widest font-extrabold text-[#15A24A] mb-0.5"><?php echo htmlspecialchars($tag); ?></span>
                </div>
                <div class="bg-black px-3 py-2.5">
                  <h3 class="text-xs font-extrabold text-white uppercase tracking-wide leading-tight truncate"><?php echo htmlspecialchars($name); ?></h3>
                  <?php if ($role): ?>
                    <p class="text-[10px] text-slate-400 mt-0.5 truncate"><?php echo htmlspecialchars($role); ?></p>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<style>
#video-testimonials #dazu-video-row::-webkit-scrollbar { height: 6px; }
#video-testimonials #dazu-video-row::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
#video-testimonials .dazu-video-player iframe { display: block; width: 100%; height: 100%; }
</style>
<script>
(function(){
  function stopAll(except) {
    document.querySelectorAll('[data-dazu-video-card]').forEach(function(card){
      if (card === except) return;
      var player = card.querySelector('.dazu-video-player');
      var poster = card.querySelector('.dazu-video-poster');
      var iframe = card.querySelector('iframe[data-src]');
      if (player) player.classList.add('hidden');
      if (poster) poster.classList.remove('hidden');
      if (iframe) iframe.removeAttribute('src');
    });
  }

  document.querySelectorAll('[data-dazu-video-card]').forEach(function(card){
    var playBtn = card.querySelector('.dazu-video-play');
    var stopBtn = card.querySelector('.dazu-video-stop');
    var player = card.querySelector('.dazu-video-player');
    var poster = card.querySelector('.dazu-video-poster');
    var iframe = card.querySelector('iframe[data-src]');
    if (!playBtn || !player || !iframe) return;

    playBtn.addEventListener('click', function(){
      stopAll(card);
      var src = iframe.getAttribute('data-src');
      if (src) iframe.setAttribute('src', src);
      player.classList.remove('hidden');
      if (poster) poster.classList.add('hidden');
    });

    if (stopBtn) {
      stopBtn.addEventListener('click', function(e){
        e.stopPropagation();
        player.classList.add('hidden');
        if (poster) poster.classList.remove('hidden');
        iframe.removeAttribute('src');
      });
    }
  });
})();
</script>
