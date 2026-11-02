<?php
/** Text testimonials carousel — expects $textTestimonials array */
$textTestimonials = $textTestimonials ?? [];
if (!$textTestimonials) return;
?>
<section class="bg-white py-20">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="text-center max-w-2xl mx-auto mb-12">
      <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold">Proven Outcomes</span>
      <h2 class="text-3xl md:text-4xl font-extrabold text-slate-950 mt-3 uppercase tracking-tight">Student Success Stories</h2>
      <p class="text-slate-600 font-medium mt-4">Read how Dazu Training Institute alumni launched high-growth technology careers through our Nairobi, Thika, and online learning paths.</p>
    </div>

    <div id="dazu-text-testimonials" class="max-w-4xl mx-auto">
      <?php foreach ($textTestimonials as $i => $t):
          $initials = $t['customer_initial'] ?? testimonial_initials($t['customer_name'] ?? '');
          $role = $t['role'] ?? '';
          $quote = $t['testimonial_text'] ?? '';
      ?>
        <article class="dazu-text-slide bg-white border border-slate-200 rounded-3xl p-8 md:p-12 relative <?php echo $i === 0 ? '' : 'hidden'; ?>" data-index="<?php echo $i; ?>">
          <span class="absolute top-6 right-8 text-6xl text-slate-100 font-serif leading-none select-none">"</span>
          <blockquote class="text-slate-600 text-lg md:text-xl leading-relaxed italic relative z-10">"<?php echo htmlspecialchars($quote); ?>"</blockquote>
          <div class="flex items-center gap-4 mt-8 pt-6 border-t border-slate-100">
            <div class="w-12 h-12 rounded-full bg-[#15A24A] text-white font-extrabold text-sm flex items-center justify-center shrink-0"><?php echo htmlspecialchars($initials); ?></div>
            <div>
              <div class="text-sm font-extrabold text-slate-950 uppercase tracking-wide"><?php echo htmlspecialchars($t['customer_name'] ?? ''); ?></div>
              <?php if ($role): ?>
                <div class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($role); ?></div>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>

      <?php if (count($textTestimonials) > 1): ?>
        <div class="flex items-center justify-between mt-8">
          <div class="flex items-center gap-2" id="dazu-text-dots">
            <?php foreach ($textTestimonials as $i => $t): ?>
              <button type="button" class="dazu-text-dot h-2 rounded-full transition-all <?php echo $i === 0 ? 'w-8 bg-[#15A24A]' : 'w-2 bg-slate-200'; ?>" data-index="<?php echo $i; ?>" aria-label="Slide <?php echo $i + 1; ?>"></button>
            <?php endforeach; ?>
          </div>
          <div class="flex gap-2">
            <button type="button" id="dazu-text-prev" class="w-10 h-10 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50">‹</button>
            <button type="button" id="dazu-text-next" class="w-10 h-10 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50">›</button>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php if (count($textTestimonials) > 1): ?>
<script>
(function(){
  var slides = document.querySelectorAll('.dazu-text-slide');
  var dots = document.querySelectorAll('.dazu-text-dot');
  var cur = 0;
  function show(n){
    cur = (n + slides.length) % slides.length;
    slides.forEach(function(s,i){ s.classList.toggle('hidden', i !== cur); });
    dots.forEach(function(d,i){
      d.classList.toggle('w-8', i === cur);
      d.classList.toggle('bg-[#15A24A]', i === cur);
      d.classList.toggle('w-2', i !== cur);
      d.classList.toggle('bg-slate-200', i !== cur);
    });
  }
  var prev = document.getElementById('dazu-text-prev');
  var next = document.getElementById('dazu-text-next');
  if (prev) prev.addEventListener('click', function(){ show(cur - 1); });
  if (next) next.addEventListener('click', function(){ show(cur + 1); });
  dots.forEach(function(d){ d.addEventListener('click', function(){ show(parseInt(d.dataset.index, 10)); }); });
  setInterval(function(){ show(cur + 1); }, 8000);
})();
</script>
<?php endif; ?>
