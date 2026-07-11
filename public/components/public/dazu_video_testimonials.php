<?php
/** Video testimonials grid — expects $videoTestimonials array */
$videoTestimonials = $videoTestimonials ?? [];
?>
<section class="bg-slate-50 border-y border-slate-100 py-20">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="text-center max-w-2xl mx-auto mb-12">
      <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold">Alumni Experience</span>
      <h2 class="text-3xl md:text-4xl font-extrabold text-slate-950 mt-3 uppercase tracking-tight flex items-center justify-center gap-2">
        <span class="text-[#15A24A]">▶</span> Video Testimonials
      </h2>
      <p class="text-slate-600 font-medium mt-4">Watch Dazu Hub alumni share their learning experience.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach ($videoTestimonials as $i => $t):
          $embed = testimonial_video_embed_url($t);
          $thumb = testimonial_video_thumb($t);
          $bg = $t['bg_class'] ?? 'from-emerald-600 to-teal-900';
          $name = $t['customer_name'] ?? 'Alumni';
          $tag = $t['service_tag'] ?? 'Tech Track';
      ?>
        <button type="button"
                class="dazu-video-card group relative aspect-[9/14] sm:aspect-[4/5] rounded-2xl overflow-hidden border border-slate-200 text-left focus:outline-none focus:ring-2 focus:ring-[#15A24A]"
                data-embed="<?php echo htmlspecialchars($embed ?? ''); ?>"
                data-title="<?php echo htmlspecialchars($name); ?>"
                <?php echo $embed ? '' : 'disabled'; ?>>
          <?php if ($thumb && $embed): ?>
            <img src="<?php echo htmlspecialchars($thumb); ?>" alt="<?php echo htmlspecialchars($name); ?>" class="absolute inset-0 w-full h-full object-cover" loading="lazy">
            <div class="absolute inset-0 bg-slate-950/40 group-hover:bg-slate-950/50 transition-colors"></div>
          <?php else: ?>
            <div class="absolute inset-0 bg-gradient-to-br <?php echo htmlspecialchars($bg); ?>"></div>
          <?php endif; ?>
          <div class="absolute inset-0 flex items-center justify-center">
            <span class="w-14 h-14 rounded-full bg-[#15A24A] text-white flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
              <svg class="w-6 h-6 ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            </span>
          </div>
          <div class="absolute bottom-0 left-0 right-0 p-5 bg-gradient-to-t from-slate-950/90 to-transparent">
            <span class="block text-[10px] uppercase tracking-widest font-bold text-green-300"><?php echo htmlspecialchars($tag); ?></span>
            <span class="block text-sm font-extrabold text-white uppercase mt-1"><?php echo htmlspecialchars($name); ?></span>
          </div>
        </button>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div id="dazu-video-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/80 p-4">
  <div class="relative w-full max-w-4xl bg-black rounded-2xl overflow-hidden">
    <button type="button" id="dazu-video-close" class="absolute top-3 right-3 z-10 w-10 h-10 rounded-full bg-white/10 text-white hover:bg-white/20">✕</button>
    <div class="aspect-video">
      <iframe id="dazu-video-iframe" class="w-full h-full border-0" allow="autoplay; encrypted-media" allowfullscreen title="Video testimonial"></iframe>
    </div>
  </div>
</div>
<script>
(function(){
  var modal = document.getElementById('dazu-video-modal');
  var iframe = document.getElementById('dazu-video-iframe');
  var closeBtn = document.getElementById('dazu-video-close');
  if (!modal || !iframe) return;
  document.querySelectorAll('.dazu-video-card[data-embed]').forEach(function(btn){
    var embed = btn.getAttribute('data-embed');
    if (!embed) return;
    btn.addEventListener('click', function(){
      iframe.src = embed;
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    });
  });
  function closeModal(){
    iframe.src = '';
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
})();
</script>
