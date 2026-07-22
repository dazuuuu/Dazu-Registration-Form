<?php
/**
 * Homepage hero — two-slide carousel.
 *
 * Images live in public/assets/images/hero/ — drop in hero-1.* and hero-2.*
 * (jpg/jpeg/png/webp/avif all work). Missing files fall back to the bundled
 * student photo, so the page never renders a broken image.
 *
 * Optional: $heroStats (from dazu_hero_stats($pdo)) for the floating cards.
 */
require_once __DIR__ . '/dazu_hero_helpers.php';

$heroFallback = is_file(public_path('assets/app/hero.png'))
    ? asset('app/hero_student_1783619555973-Dfwss1g9.jpg')
    : 'https://images.unsplash.com/photo-1580894732444-8ecded7900cd?auto=format&fit=crop&w=900&q=80';

$heroStats = $heroStats ?? ['courses' => 0, 'students' => 0];

/* Shown on every slide. */
$heroChecks = ['Get Certified', 'Gain Job-ready Skills', 'Great Life'];

/* Floating cards. A zero count is dropped rather than shown as "0+". */
$heroCards = array_values(array_filter([
    [
        'value' => (int) ($heroStats['courses'] ?? 0),
        'label' => 'Practical Courses',
        'tone'  => 'rose',
        'pos'   => 'top',
    ],
    [
        'value' => (int) ($heroStats['students'] ?? 0),
        'label' => 'Active Students',
        'tone'  => 'green',
        'pos'   => 'bottom',
    ],
], static fn($c) => $c['value'] > 0));

$heroSlides = [
    [
        'eyebrow'  => 'Future-Proof Your Career',
        'accent'   => 'Master AI & Analytics',      // green
        'title'    => 'Lead The Industry Growth',   // navy
        'text'     => 'Empower your business or professional career with advanced Python, Data Analytics and automated generative AI content pipelines.',
        'image'    => 'hero-1',
        'imageAlt' => 'Student working on data analytics and design at Dazu hub Institute',
        'cta1'     => ['label' => 'Get Started', 'url' => url('apply/index.php')],
        'cta2'     => ['label' => 'Our Courses', 'url' => url('courses/index.php')],
    ],
    [
        'eyebrow'  => 'Online E-Learning Course',
        'accent'   => 'Online Education',
        'title'    => 'Feels Like Real Classroom',
        'text'     => 'Practical, hands-on physical labs in Thika Town paired with direct industry mentorship to fast-track your tech career.',
        'image'    => 'hero-2',
        'imageAlt' => 'Dazu hub Institute student with a laptop',
        'cta1'     => ['label' => 'Get Started', 'url' => url('apply/index.php')],
        'cta2'     => ['label' => 'Our Courses', 'url' => url('courses/index.php')],
    ],
];
?>
<style>
/* Inline rather than assets/css: sw.js caches every .css file, and PHP output is
   never intercepted by the service worker, so this cannot go stale on deploy. */
.dazu-hero {
  position: relative;
  padding: 3.5rem 0 4.5rem;
  background: #fff;
  overflow: hidden;
}
.dazu-hero__inner { max-width: 1280px; margin: 0 auto; padding: 0 1rem; }
@media (min-width: 768px) { .dazu-hero__inner { padding: 0 2rem; } }
@media (min-width: 1024px) { .dazu-hero__inner { padding: 0 3rem; } }

.dazu-hero__panel {
  display: grid;
  grid-template-columns: 1fr;
  gap: 2.5rem;
  align-items: center;
}
@media (min-width: 1024px) {
  .dazu-hero__panel { grid-template-columns: 1fr 1fr; gap: 3rem; }
}
.dazu-hero__panel[hidden] { display: none; }

/* ---- eyebrow ---- */
.dazu-hero__eyebrow {
  display: flex; align-items: center; gap: .75rem;
  margin-bottom: 1.75rem;
}
.dazu-hero__eyebrow svg { width: 3rem; height: .75rem; color: #15a24a; flex-shrink: 0; }
.dazu-hero__eyebrow span {
  font-size: .8rem; font-weight: 700; letter-spacing: .12em;
  text-transform: uppercase; color: #15a24a;
}

/* ---- headline ----
   dazu-frontend-theme.css forces h1 to the Nasalization display face with
   !important. The reference design sets the headline in the rounded body face,
   so this overrides it — class beats element, and !important beats !important. */
.dazu-hero__title {
  margin: 0;
  font-family: "Nunito", ui-sans-serif, system-ui, sans-serif !important;
  font-size: clamp(2.25rem, 5.2vw, 4rem);
  font-weight: 700;
  line-height: 1.08;
  letter-spacing: -.015em !important;
}
.dazu-hero__title .accent { display: block; color: #15a24a; }
.dazu-hero__title .main   { display: block; color: #0b1120; }

/* ---- checks ---- */
.dazu-hero__checks {
  list-style: none; margin: 1.75rem 0 0; padding: 0;
  display: flex; flex-wrap: wrap; gap: .75rem 1.75rem;
}
.dazu-hero__checks li {
  display: inline-flex; align-items: center; gap: .5rem;
  font-size: .95rem; font-weight: 700; color: #0b1120;
}
.dazu-hero__checks svg { width: 1.15rem; height: 1.15rem; color: #15a24a; flex-shrink: 0; }

.dazu-hero__text {
  margin: 1.75rem 0 0;
  max-width: 32rem;
  font-size: 1rem; font-weight: 500; line-height: 1.7; color: #64748b;
}

/* ---- buttons ---- */
.dazu-hero__ctas { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 2.25rem; }
.dazu-hero__btn {
  display: inline-flex; align-items: center; gap: .6rem;
  padding: 1.05rem 1.9rem;
  border-radius: 10px;
  font-size: .8rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
  text-decoration: none; white-space: nowrap;
  transition: transform .15s ease, background-color .15s ease, box-shadow .15s ease;
}
.dazu-hero__btn svg { width: .95rem; height: .95rem; }
.dazu-hero__btn--primary { background: #15a24a; color: #fff; box-shadow: 0 10px 24px -12px rgba(21,162,74,.9); }
.dazu-hero__btn--primary:hover { background: #128a3e; transform: translateY(-2px); }
.dazu-hero__btn--dark { background: #0b1120; color: #fff; box-shadow: 0 10px 24px -12px rgba(11,17,32,.8); }
.dazu-hero__btn--dark:hover { background: #1e293b; transform: translateY(-2px); }
.dazu-hero__btn:focus-visible { outline: 3px solid rgba(21,162,74,.45); outline-offset: 3px; }

/* ---- footnote ---- */
.dazu-hero__foot {
  margin-top: 1.5rem; padding-top: 0;
  display: flex; flex-wrap: wrap; gap: 1.5rem;
}
.dazu-hero__foot span {
  display: inline-flex; align-items: center; gap: .5rem;
  font-size: .9rem; font-weight: 800; color: #0b1120;
}
.dazu-hero__foot i { width: .5rem; height: .5rem; border-radius: 999px; background: #15a24a; }

/* ---- media ---- */
.dazu-hero__media {
  position: relative;
  display: flex; align-items: center; justify-content: center;
  min-height: 320px;
  padding: 1.5rem 0;
}
.dazu-hero__ring {
  position: relative;
  width: min(84vw, 480px);
  aspect-ratio: 4 / 5;
  border-radius: 1.25rem;
  border: 0;
  background: #f1f5f9;
  overflow: hidden;
}
.dazu-hero__ring img { width: 100%; height: 100%; object-fit: cover; display: block; }

/* dotted decoration */
.dazu-hero__deco {
  position: absolute;
  background-image: radial-gradient(#15a24a 2px, transparent 2px);
  background-size: 14px 14px;
  opacity: .28;
  pointer-events: none;
}
.dazu-hero__deco--tl { width: 140px; height: 110px; top: 4%; left: -2%; }
.dazu-hero__deco--br { width: 140px; height: 110px; bottom: 2%; right: -2%; }
@media (max-width: 767px) { .dazu-hero__deco { display: none; } }

/* floating cards */
.dazu-hero__card {
  position: absolute;
  display: flex; align-items: center; gap: .7rem;
  padding: .7rem .95rem;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 14px 34px -12px rgba(15,23,42,.28);
  white-space: nowrap;
  z-index: 2;
}
.dazu-hero__card--top { top: 14%; right: -4%; }
.dazu-hero__card--bottom { bottom: 12%; left: -4%; }
@media (max-width: 380px) {
  .dazu-hero__card--top { right: 0; }
  .dazu-hero__card--bottom { left: 0; }
}
.dazu-hero__card i {
  display: inline-flex; align-items: center; justify-content: center;
  width: 2.4rem; height: 2.4rem; border-radius: 999px; flex-shrink: 0;
}
.dazu-hero__card i svg { width: 1.15rem; height: 1.15rem; }
.dazu-hero__card--rose i { background: #ffe4e6; color: #e11d48; }
.dazu-hero__card--green i { background: #dcfce7; color: #15a24a; }
.dazu-hero__card b { display: block; font-size: 1.1rem; font-weight: 800; color: #0b1120; line-height: 1.1; }
.dazu-hero__card small { display: block; font-size: .68rem; font-weight: 700; color: #64748b; }

/* ---- carousel controls ---- */
.dazu-hero__nav {
  display: flex; align-items: center; justify-content: center; gap: 1rem;
  margin-top: 2rem;
}
.dazu-hero__nav button.arrow {
  width: 2.6rem; height: 2.6rem; border-radius: 999px;
  border: 1px solid #e2e8f0; background: #fff; color: #475569;
  display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer; transition: border-color .15s ease, color .15s ease;
}
.dazu-hero__nav button.arrow:hover { border-color: #15a24a; color: #15a24a; }
.dazu-hero__nav button.arrow:focus-visible { outline: 3px solid rgba(21,162,74,.45); outline-offset: 2px; }
.dazu-hero__nav button.arrow svg { width: 1rem; height: 1rem; }
.dazu-hero__dots { display: flex; align-items: center; gap: .5rem; }
.dazu-hero__dot {
  width: .6rem; height: .6rem; padding: 0;
  border: 0; border-radius: 999px; background: #cbd5e1;
  cursor: pointer; transition: width .25s ease, background-color .25s ease;
}
.dazu-hero__dot.is-active { width: 1.9rem; background: #15a24a; }
.dazu-hero__dot:focus-visible { outline: 3px solid rgba(21,162,74,.45); outline-offset: 2px; }

@media (prefers-reduced-motion: reduce) {
  .dazu-hero__btn, .dazu-hero__dot { transition: none; }
  .dazu-hero__btn:hover { transform: none; }
}
</style>

<section class="dazu-hero" id="dazu-hero" aria-roledescription="carousel" aria-label="Dazu hub Institute highlights">
  <div class="dazu-hero__inner">
    <div id="dazu-hero-slides">
      <?php foreach ($heroSlides as $i => $slide):
          $img = dazu_hero_image_url($slide['image'], $heroFallback);
      ?>
        <div class="dazu-hero__panel" data-slide="<?php echo $i; ?>"
             role="group" aria-roledescription="slide"
             aria-label="Slide <?php echo $i + 1; ?> of <?php echo count($heroSlides); ?>"
             <?php echo $i === 0 ? '' : 'hidden'; ?>>

          <div>
            <p class="dazu-hero__eyebrow">
              <svg viewBox="0 0 48 12" fill="none" aria-hidden="true"><path d="M0 6h42" stroke="currentColor" stroke-width="2"/><path d="M38 2l5 4-5 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <span><?php echo htmlspecialchars($slide['eyebrow']); ?></span>
            </p>

            <h1 class="dazu-hero__title">
              <span class="accent"><?php echo htmlspecialchars($slide['accent']); ?></span>
              <span class="main"><?php echo htmlspecialchars($slide['title']); ?></span>
            </h1>

            <p class="dazu-hero__text"><?php echo htmlspecialchars($slide['text']); ?></p>

            <div class="dazu-hero__ctas">
              <a class="dazu-hero__btn dazu-hero__btn--primary" href="<?php echo htmlspecialchars($slide['cta1']['url'], ENT_QUOTES); ?>">
                <?php echo htmlspecialchars($slide['cta1']['label']); ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"/></svg>
              </a>
              <a class="dazu-hero__btn dazu-hero__btn--dark" href="<?php echo htmlspecialchars($slide['cta2']['url'], ENT_QUOTES); ?>">
                <?php echo htmlspecialchars($slide['cta2']['label']); ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"/></svg>
              </a>
            </div>

            <div class="dazu-hero__foot">
              <?php foreach ($heroChecks as $check): ?>
                <span><i></i><strong><?php echo htmlspecialchars($check); ?></strong></span>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="dazu-hero__media">
            <span class="dazu-hero__deco dazu-hero__deco--tl" aria-hidden="true"></span>
            <span class="dazu-hero__deco dazu-hero__deco--br" aria-hidden="true"></span>

            <div class="dazu-hero__ring">
              <img src="<?php echo htmlspecialchars($img, ENT_QUOTES); ?>"
                   alt="<?php echo htmlspecialchars($slide['imageAlt']); ?>"
                   width="840" height="840"
                   <?php echo $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"'; ?>
                   decoding="async" referrerpolicy="no-referrer">
            </div>

            <?php foreach ($heroCards as $card): ?>
              <div class="dazu-hero__card dazu-hero__card--<?php echo $card['pos']; ?> dazu-hero__card--<?php echo $card['tone']; ?>">
                <i>
                  <?php if ($card['tone'] === 'rose'): ?>
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13l11-6.5z"/></svg>
                  <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4 2 9l10 5 10-5-10-5z"/><path d="M6 11.5V17c0 1.1 2.7 2.5 6 2.5s6-1.4 6-2.5v-5.5"/></svg>
                  <?php endif; ?>
                </i>
                <span>
                  <b><?php echo number_format($card['value']); ?>+</b>
                  <small><?php echo htmlspecialchars($card['label']); ?></small>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (count($heroSlides) > 1): ?>
      <div class="dazu-hero__nav">
        <button type="button" class="arrow" id="dazu-hero-prev" aria-label="Previous slide">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 19l-7-7 7-7"/></svg>
        </button>
        <div class="dazu-hero__dots" id="dazu-hero-dots">
          <?php foreach ($heroSlides as $i => $slide): ?>
            <button type="button" class="dazu-hero__dot<?php echo $i === 0 ? ' is-active' : ''; ?>"
                    data-slide="<?php echo $i; ?>"
                    aria-label="Go to slide <?php echo $i + 1; ?>"
                    aria-current="<?php echo $i === 0 ? 'true' : 'false'; ?>"></button>
          <?php endforeach; ?>
        </div>
        <button type="button" class="arrow" id="dazu-hero-next" aria-label="Next slide">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
        </button>
      </div>
    <?php endif; ?>
  </div>
</section>

<script>
(function () {
  var root = document.getElementById('dazu-hero');
  if (!root) return;
  var panels = [].slice.call(root.querySelectorAll('.dazu-hero__panel'));
  var dots = [].slice.call(root.querySelectorAll('.dazu-hero__dot'));
  var total = panels.length;
  if (total < 2) return;

  var cur = 0, timer = null;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function show(n) {
    cur = (n + total) % total;
    panels.forEach(function (p, i) { p.hidden = (i !== cur); });
    dots.forEach(function (d, i) {
      d.classList.toggle('is-active', i === cur);
      d.setAttribute('aria-current', i === cur ? 'true' : 'false');
    });
  }
  function start() {
    if (reduce) return;                 // no auto-advance for reduced-motion users
    stop();
    timer = window.setInterval(function () { show(cur + 1); }, 8000);
  }
  function stop() { if (timer) { window.clearInterval(timer); timer = null; } }

  root.querySelector('#dazu-hero-prev').addEventListener('click', function () { show(cur - 1); start(); });
  root.querySelector('#dazu-hero-next').addEventListener('click', function () { show(cur + 1); start(); });
  dots.forEach(function (d) {
    d.addEventListener('click', function () { show(parseInt(d.getAttribute('data-slide'), 10)); start(); });
  });

  // Don't yank the slide out from under someone reading or tabbing through it.
  root.addEventListener('mouseenter', stop);
  root.addEventListener('mouseleave', start);
  root.addEventListener('focusin', stop);
  root.addEventListener('focusout', start);
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) { stop(); } else { start(); }
  });

  start();
})();
</script>
