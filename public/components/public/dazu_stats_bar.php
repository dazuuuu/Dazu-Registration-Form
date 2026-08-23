<?php /** Stats bar — ported from Dazu-frontend Home.tsx */ ?>
<section class="bg-slate-50 border-y border-slate-100 py-12" id="dazu-stats">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
      <?php
      $stats = [
          ['target' => 1200, 'label' => 'Students Trained'],
          ['target' => 14, 'label' => 'Practical Courses'],
          ['target' => 350, 'label' => 'Projects Completed'],
          ['target' => 25, 'label' => 'Industry Mentors'],
      ];
      foreach ($stats as $i => $stat): ?>
        <div class="space-y-1">
          <span class="block text-4xl font-extrabold text-slate-950">
            <span class="dazu-counter" data-target="<?php echo (int) $stat['target']; ?>">0</span>+
          </span>
          <span class="text-[10px] uppercase tracking-widest font-bold text-slate-400"><?php echo htmlspecialchars($stat['label']); ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<script>
(function(){
  function animateCounter(el){
    var target = parseInt(el.getAttribute('data-target'), 10);
    var start = null, duration = 2000;
    function step(ts){
      if(!start) start = ts;
      var p = Math.min((ts - start) / duration, 1);
      var eased = p * (2 - p);
      el.textContent = Math.floor(eased * target).toLocaleString();
      if(p < 1) requestAnimationFrame(step); else el.textContent = target.toLocaleString();
    }
    requestAnimationFrame(step);
  }
  var obs = new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if(e.isIntersecting && !e.target.dataset.done){
        e.target.dataset.done = '1';
        animateCounter(e.target);
      }
    });
  }, { threshold: 0.3 });
  document.querySelectorAll('.dazu-counter').forEach(function(el){ obs.observe(el); });
})();
</script>
