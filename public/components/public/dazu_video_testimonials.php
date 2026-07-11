<?php
/** Video testimonials — inline play inside cards on the homepage */
$videoTestimonials = $videoTestimonials ?? [];
$playable = array_values(array_filter($videoTestimonials, static fn($t) => (bool) testimonial_video_embed_url($t)));
?>
<section class="bg-slate-50 border-y border-slate-100 py-20" id="video-testimonials">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="text-center max-w-2xl mx-auto mb-12">
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
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
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
            $cardId = 'dazu-vcard-' . $i;
        ?>
          <article class="group relative rounded-3xl overflow-hidden border border-slate-200/80 bg-white shadow-md hover:shadow-xl transition-shadow"
                   style="min-height:420px"
                   data-dazu-video-card>
            <!-- Player layer (hidden until play) -->
            <div class="dazu-video-player hidden absolute inset-0 z-20 bg-black">
              <iframe class="w-full h-full border-0"
                      data-src="<?php echo htmlspecialchars($embed . (str_contains($embed, '?') ? '&' : '?') . 'autoplay=1'); ?>"
                      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                      allowfullscreen
                      title="<?php echo htmlspecialchars($name); ?> testimonial"></iframe>
              <button type="button"
                      class="dazu-video-stop absolute top-3 right-3 z-30 w-9 h-9 rounded-full bg-black/60 text-white text-sm hover:bg-black/80"
                      aria-label="Stop video">✕</button>
            </div>

            <!-- Poster / card face -->
            <div class="dazu-video-poster relative w-full h-full" style="min-height:420px">
              <?php if ($thumb): ?>
                <img src="<?php echo htmlspecialchars($thumb); ?>" alt="<?php echo htmlspecialchars($name); ?>"
                     class="absolute inset-0 w-full h-full object-cover" loading="lazy">
              <?php else: ?>
                <div class="absolute inset-0 <?php echo htmlspecialchars($gradient); ?>"></div>
              <?php endif; ?>
              <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/50 to-slate-900/20"></div>

              <div class="absolute top-4 left-4 flex items-center gap-2">
                <span class="px-2.5 py-1 rounded-full bg-[#15A24A] text-white text-[10px] font-extrabold uppercase tracking-wider">Alumni</span>
                <?php if (!empty($t['rating'])): ?>
                  <span class="px-2 py-1 rounded-full bg-white/15 text-white text-[10px] font-bold backdrop-blur-sm"><?php echo (int) $t['rating']; ?>★</span>
                <?php endif; ?>
              </div>

              <button type="button"
                      class="dazu-video-play absolute inset-0 flex items-center justify-center z-10 group/play"
                      aria-label="Play <?php echo htmlspecialchars($name); ?> video">
                <span class="w-16 h-16 md:w-[4.5rem] md:h-[4.5rem] rounded-full bg-[#15A24A] text-white flex items-center justify-center shadow-2xl ring-4 ring-white/30 group-hover/play:scale-110 transition-transform">
                  <svg class="w-7 h-7 ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                </span>
              </button>

              <div class="absolute bottom-0 left-0 right-0 p-5 md:p-6 z-10">
                <span class="block text-[10px] uppercase tracking-widest font-bold text-green-300 mb-1"><?php echo htmlspecialchars($tag); ?></span>
                <h3 class="text-base md:text-lg font-extrabold text-white uppercase tracking-wide leading-tight"><?php echo htmlspecialchars($name); ?></h3>
                <?php if ($role): ?>
                  <p class="text-xs text-slate-300 mt-1 font-medium"><?php echo htmlspecialchars($role); ?></p>
                <?php endif; ?>
                <?php if (!empty($t['testimonial_text']) && ($t['testimonial_text'] ?? '') !== 'Video testimonial from ' . $name): ?>
                  <p class="text-xs text-slate-400 mt-2 line-clamp-2 italic">"<?php echo htmlspecialchars($t['testimonial_text']); ?>"</p>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<style>
#video-testimonials [data-dazu-video-card] { aspect-ratio: 9 / 14; max-height: 520px; }
@media (min-width: 640px) { #video-testimonials [data-dazu-video-card] { aspect-ratio: 4 / 5; } }
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
      card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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
