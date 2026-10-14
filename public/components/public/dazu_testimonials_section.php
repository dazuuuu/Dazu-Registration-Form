<?php
/**
 * Testimonials section — video + text, ported from Dazu-frontend Home.tsx
 * Expects $videoTestimonials and $textTestimonials from index.php
 */
$videoTestimonials = $videoTestimonials ?? [];
$textTestimonials = $textTestimonials ?? [];
// Student stories come only from approved video testimonials (Admin → Testimonials → Add student story).
$playable = array_values(array_filter($videoTestimonials, static fn($t) => (bool) testimonial_video_embed_url($t)));

$gradients = [
    'linear-gradient(to bottom right, #10b981, #115e59)',
    'linear-gradient(to bottom right, #2563eb, #312e81)',
    'linear-gradient(to bottom right, #9333ea, #4a044e)',
    'linear-gradient(to bottom right, #f59e0b, #9a3412)',
];
?>
<section class="bg-white" id="testimonials">

    <!-- Video Testimonials -->
    <div class="bg-white py-20 md:py-28">
      <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
        <div class="text-center mb-14">
          <span class="inline-block px-3 py-1 bg-green-50 text-[#15A24A] text-[11px] font-extrabold uppercase tracking-widest rounded-full border border-green-100 mb-4">
            Student Stories
          </span>
          <h2 class="text-3xl md:text-5xl font-extrabold text-slate-900 tracking-tight">
            Hear From Our Students
          </h2>
          <p class="text-slate-500 text-base font-medium leading-relaxed max-w-2xl mx-auto mt-5">
            Real stories from our graduates who have transformed their careers through our hands-on training programs.
          </p>
        </div>

    <?php if ($playable): ?>
      <style>
        .dz-sv{--side:22%;--main:52%;--h:440px;position:relative;outline:none}
        .dz-sv-track{display:flex;align-items:flex-start;justify-content:center;gap:10px;touch-action:pan-y;user-select:none;-webkit-user-select:none}
        .dz-sv-card{display:none;flex:0 0 var(--side);min-width:0;cursor:pointer;transition:flex-basis .4s ease}
        .dz-sv-card.is-active{flex-basis:var(--main);cursor:default}
        .dz-sv-card.is-active,.dz-sv-card.is-prev,.dz-sv-card.is-next{display:block}
        .dz-sv-media{position:relative;height:var(--h);border-radius:10px;overflow:hidden;background:#0f172a}
        .dz-sv-media>img,.dz-sv-media>video.dz-sv-poster{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;pointer-events:none;transition:filter .4s,transform .4s}
        .dz-sv-card:not(.is-active) .dz-sv-media>img,.dz-sv-card:not(.is-active) .dz-sv-media>video.dz-sv-poster{filter:blur(6px);transform:scale(1.08)}
        .dz-sv-card:not(.is-active) .dz-sv-media::after{content:"";position:absolute;inset:0;background:rgba(15,23,42,.15)}
        .dz-sv-player{position:absolute;inset:0;width:100%;height:100%;border:0;z-index:3;background:#000}
        .dz-sv-play{position:absolute;top:14px;left:14px;z-index:2;display:none;align-items:center;gap:6px;padding:7px 12px;border:0;border-radius:6px;background:rgba(255,255,255,.22);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);color:#fff;font-size:12px;font-weight:600;cursor:pointer}
        .dz-sv-play:hover{background:rgba(255,255,255,.35)}
        .dz-sv-play svg{width:10px;height:10px;fill:#fff}
        .dz-sv-quote{position:absolute;left:0;right:0;bottom:0;z-index:2;display:none;margin:0;padding:60px 18px 16px;background:linear-gradient(to top,rgba(0,0,0,.78),transparent);color:#f1f5f9;font-size:12.5px;line-height:1.55}
        .dz-sv-card.is-active .dz-sv-play{display:inline-flex}
        .dz-sv-card.is-active .dz-sv-quote{display:block}
        .dz-sv-name{margin:10px 0 1px;font-size:13px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .dz-sv-meta{display:block;font-size:12px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .dz-sv-nav{display:flex;align-items:center;justify-content:center;gap:14px;margin-top:22px}
        .dz-sv-dots{display:flex;gap:6px}
        .dz-sv-dot{width:6px;height:6px;border-radius:999px;background:#cbd5e1;border:0;padding:0;cursor:pointer;transition:all .3s}
        .dz-sv-dot.is-on{width:22px;background:#15A24A}
        .dz-sv-arrow{width:34px;height:34px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#0f172a;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:border-color .15s,color .15s}
        .dz-sv-arrow:hover{border-color:#15A24A;color:#15A24A}
        .dz-sv-arrow svg{width:14px;height:14px}
        @media(max-width:1023px){.dz-sv{--side:17%;--main:62%;--h:400px}}
        @media(max-width:640px){
          .dz-sv{--side:8%;--main:80%;--h:min(440px,125vw);margin:0 -16px}
          .dz-sv-track{gap:6px}
          .dz-sv-card.is-prev .dz-sv-media,.dz-sv-card.is-next .dz-sv-media{border-radius:8px}
          .dz-sv-card.is-prev .dz-sv-name,.dz-sv-card.is-prev .dz-sv-meta,.dz-sv-card.is-next .dz-sv-name,.dz-sv-card.is-next .dz-sv-meta{visibility:hidden}
          .dz-sv-quote{font-size:12px;padding:48px 14px 12px;-webkit-line-clamp:5;-webkit-box-orient:vertical;overflow:hidden}
          .dz-sv-card.is-active .dz-sv-quote{display:-webkit-box}
          .dz-sv-play{top:10px;left:10px;padding:6px 10px;font-size:11px}
          .dz-sv-nav{margin-top:16px}
        }
      </style>
      <div class="dz-sv" id="dz-sv">
        <div class="dz-sv-track" id="dz-sv-track">
          <?php foreach ($playable as $i => $t):
              $embed = testimonial_video_embed_url($t);
              $ytId = testimonial_youtube_id($t['video_url'] ?? '');
              $isFile = !$ytId && (($t['video_provider'] ?? '') === 'upload' || preg_match('#\.(mp4|webm|mov|m4v)(\?|$)#i', $embed));
              $thumb = trim((string) ($t['thumbnail_url'] ?? '')) ?: ($ytId ? 'https://img.youtube.com/vi/' . $ytId . '/hqdefault.jpg' : '');
              $name = $t['customer_name'] ?? 'Alumni';
              $meta = trim((string) ($t['role'] ?? '')) ?: ($t['service_tag'] ?? '');
              $quote = trim((string) ($t['testimonial_text'] ?? ''));
              $src = $isFile ? $embed : $embed . (str_contains($embed, '?') ? '&' : '?') . 'autoplay=1';
          ?>
            <article class="dz-sv-card" data-i="<?php echo $i; ?>" data-kind="<?php echo $isFile ? 'file' : 'embed'; ?>" data-src="<?php echo htmlspecialchars($src, ENT_QUOTES); ?>">
              <div class="dz-sv-media" style="background:<?php echo htmlspecialchars($gradients[$i % count($gradients)]); ?>">
                <?php if ($thumb): ?>
                  <img src="<?php echo htmlspecialchars($thumb, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>" loading="lazy" draggable="false">
                <?php elseif ($isFile): ?>
                  <video class="dz-sv-poster" src="<?php echo htmlspecialchars($embed, ENT_QUOTES); ?>#t=0.5" muted playsinline preload="metadata"></video>
                <?php endif; ?>
                <button type="button" class="dz-sv-play"><svg viewBox="0 0 24 24"><path d="M6 4l15 8-15 8z"/></svg>Play Video</button>
                <?php if ($quote): ?><p class="dz-sv-quote"><?php echo htmlspecialchars($quote); ?></p><?php endif; ?>
              </div>
              <h4 class="dz-sv-name"><?php echo htmlspecialchars($name); ?></h4>
              <?php if ($meta): ?><span class="dz-sv-meta"><?php echo htmlspecialchars($meta); ?></span><?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
        <?php if (count($playable) > 1): ?>
          <div class="dz-sv-nav">
            <button type="button" class="dz-sv-arrow" data-step="-1" aria-label="Previous story"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg></button>
            <div class="dz-sv-dots">
              <?php foreach ($playable as $i => $_): ?>
                <button type="button" class="dz-sv-dot" data-i="<?php echo $i; ?>" aria-label="Show story <?php echo $i + 1; ?>"></button>
              <?php endforeach; ?>
            </div>
            <button type="button" class="dz-sv-arrow" data-step="1" aria-label="Next story"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
          </div>
        <?php endif; ?>
      </div>
      <script>
      (function(){
        var root = document.getElementById('dz-sv');
        var track = document.getElementById('dz-sv-track');
        var cards = [].slice.call(track.querySelectorAll('.dz-sv-card'));
        var dots = [].slice.call(root.querySelectorAll('.dz-sv-dot'));
        var n = cards.length, cur = 0, moved = false;

        function stop(c){ var p = c.querySelector('.dz-sv-player'); if (p) p.remove(); }
        function show(k){
          if (n < 2) k = 0;
          cur = (k + n) % n;
          cards.forEach(function(c, i){
            var rel = (i - cur + n) % n;
            c.classList.remove('is-active', 'is-prev', 'is-next');
            if (rel === 0) { c.classList.add('is-active'); c.style.order = 1; }
            else if (rel === n - 1) { c.classList.add('is-prev'); c.style.order = 0; }
            else if (n > 2 && rel === 1) { c.classList.add('is-next'); c.style.order = 2; }
            if (rel !== 0) stop(c);
          });
          dots.forEach(function(d, i){ d.classList.toggle('is-on', i === cur); });
        }
        function play(c){
          if (c.querySelector('.dz-sv-player')) return;
          var p;
          if (c.dataset.kind === 'file') {
            p = document.createElement('video');
            p.controls = true; p.autoplay = true; p.playsInline = true;
          } else {
            p = document.createElement('iframe');
            p.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
            p.allowFullscreen = true;
            p.title = 'Student story';
          }
          p.className = 'dz-sv-player';
          p.src = c.dataset.src;
          c.querySelector('.dz-sv-media').appendChild(p);
        }

        cards.forEach(function(c, i){
          c.addEventListener('click', function(){ if (!moved && !c.classList.contains('is-active')) show(i); });
          c.querySelector('.dz-sv-play').addEventListener('click', function(e){ e.stopPropagation(); play(c); });
        });
        dots.forEach(function(d){ d.addEventListener('click', function(){ show(+d.dataset.i); }); });
        root.querySelectorAll('.dz-sv-arrow').forEach(function(b){ b.addEventListener('click', function(){ show(cur + (+b.dataset.step)); }); });

        // Swipe / drag a blurred card into the middle.
        var startX = null, startY = 0;
        track.addEventListener('pointerdown', function(e){ startX = e.clientX; startY = e.clientY; moved = false; });
        track.addEventListener('pointermove', function(e){ if (startX !== null && Math.abs(e.clientX - startX) > 8) moved = true; });
        track.addEventListener('pointerup', function(e){
          if (startX === null) return;
          var dx = e.clientX - startX, dy = e.clientY - startY;
          startX = null;
          if (n > 1 && Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) show(cur + (dx < 0 ? 1 : -1));
          setTimeout(function(){ moved = false; }, 0);
        });
        track.addEventListener('pointercancel', function(){ startX = null; moved = false; });

        // Horizontal trackpad / shift+wheel scrolling.
        var wheelLock = false;
        track.addEventListener('wheel', function(e){
          var dx = Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : (e.shiftKey ? e.deltaY : 0);
          if (n < 2 || Math.abs(dx) < 15) return;
          e.preventDefault();
          if (wheelLock) return;
          wheelLock = true;
          show(cur + (dx > 0 ? 1 : -1));
          setTimeout(function(){ wheelLock = false; }, 450);
        }, { passive: false });

        root.tabIndex = 0;
        root.addEventListener('keydown', function(e){
          if (e.key === 'ArrowRight') show(cur + 1);
          if (e.key === 'ArrowLeft') show(cur - 1);
        });

        show(n > 2 ? 1 : 0);
      })();
      </script>
    <?php else: ?>
      <div class="max-w-[1120px] mx-auto text-center px-6 py-16 md:py-20 border border-dashed border-slate-200 rounded-3xl text-slate-400 text-sm md:text-base font-semibold">
        Student stories will appear here once they are added under Admin → Testimonials → Add student story.
      </div>
    <?php endif; ?>
      </div>
    </div>

    <!-- Text Testimonials -->
    <?php if ($textTestimonials): ?>
      <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 py-24 md:py-32">
      <div class="max-w-2xl mx-auto space-y-4 text-center mb-16">
        <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold">Proven Outcomes</span>
        <h2 class="text-3xl md:text-4xl font-extrabold tracking-tight text-slate-950">Student Success Stories</h2>
        <p class="text-slate-500 text-sm font-medium leading-relaxed max-w-lg mx-auto">Read how Dazu Training Institute alumni launched high-growth technology careers through our Nairobi, Thika, and online learning paths.</p>
      </div>

      <div class="max-w-[1120px] mx-auto" id="dazu-text-testimonials">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <?php foreach ($textTestimonials as $i => $t):
            $initials = $t['customer_initial'] ?? testimonial_initials($t['customer_name'] ?? '');
            $role = $t['role'] ?? '';
            $quote = $t['testimonial_text'] ?? '';
        ?>
          <article class="dazu-text-slide bg-slate-50 border border-slate-200 rounded-3xl p-8 md:p-12 text-left relative overflow-hidden <?php echo $i < 2 ? '' : 'hidden'; ?>" data-index="<?php echo $i; ?>">
            <span class="absolute top-8 left-10 text-5xl font-serif text-slate-200/70 select-none">"</span>
            <div class="space-y-8 relative z-10">
              <p class="text-lg md:text-xl text-slate-700 italic font-semibold leading-relaxed pt-8">"<?php echo htmlspecialchars($quote); ?>"</p>
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
        </div>

        <?php if (count($textTestimonials) > 1): ?>
          <div class="flex items-center justify-between mt-8">
            <div class="flex items-center gap-2" id="dazu-text-dots">
              <?php for ($i = 0, $pages = (int) ceil(count($textTestimonials) / 2); $i < $pages; $i++): ?>
                <button type="button" class="dazu-text-dot h-2.5 rounded-full transition-all duration-300 <?php echo $i === 0 ? 'w-8 bg-[#15A24A]' : 'w-2.5 bg-slate-200'; ?>" data-index="<?php echo $i; ?>" aria-label="Go to testimonial <?php echo $i + 1; ?>"></button>
              <?php endfor; ?>
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
      </div>
    <?php endif; ?>
</section>

<script>
(function(){
  var slides = document.querySelectorAll('.dazu-text-slide');
  var dots = document.querySelectorAll('.dazu-text-dot');
  if(slides.length > 1){
    var cur = 0;
    var perPage = 2;
    var pages = Math.ceil(slides.length / perPage);
    function show(n){
      cur = (n + pages) % pages;
      slides.forEach(function(s,i){
        var page = Math.floor(i / perPage);
        s.classList.toggle('hidden', page !== cur);
      });
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
