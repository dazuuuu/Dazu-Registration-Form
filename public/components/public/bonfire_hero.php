<?php
/**
 * Bonfire Adventures full-viewport hero — adapted for Dazu course discovery.
 * Visual source: https://github.com/Muigai-Kiongo/bonfireadventures.com
 */
require_once __DIR__ . '/../../../app/v1/helpers/bonfire_theme.php';
require_once __DIR__ . '/dazu_hero_helpers.php';
require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';

$heroStats = $heroStats ?? ['courses' => 0, 'students' => 0];
$courseCount = (int) ($heroStats['courses'] ?? 0);
$studentCount = (int) ($heroStats['students'] ?? 0);

$heroBg = bonfire_asset_v('hero-bg.png');
$heroCss = bonfire_asset_v('custom-hero.css');
$coursesUrl = url('courses/index.php');
$applyUrl = url('apply/index.php');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($heroCss, ENT_QUOTES); ?>">
<style>
.bf-hero { background-image: url('<?php echo htmlspecialchars($heroBg, ENT_QUOTES); ?>'); }
.bf-hero .hero-nav a.is-active { color: #D4AF37; }
.bf-hero .hero-title { font-size: clamp(3rem, 10vw, 7rem); }
.bf-hero .hero-subtitle {
  font-family: 'Poppins', sans-serif;
  font-size: clamp(1rem, 2.5vw, 1.35rem);
  font-weight: 500;
  color: rgba(255, 255, 255, 0.85);
  margin-top: 1rem;
  letter-spacing: 0.05em;
}
.bf-hero .hero-stats {
  display: flex;
  gap: 2rem;
  justify-content: center;
  margin-top: 1.5rem;
  flex-wrap: wrap;
}
.bf-hero .hero-stat {
  text-align: center;
  color: #fff;
}
.bf-hero .hero-stat b {
  display: block;
  font-family: 'Poppins', sans-serif;
  font-size: 1.75rem;
  font-weight: 800;
  color: #D4AF37;
}
.bf-hero .hero-stat small {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  opacity: 0.8;
}
@media (max-width: 768px) {
  .bf-hero .hero-header { padding: 20px 20px; flex-wrap: wrap; gap: 1rem; }
  .bf-hero .hero-nav { gap: 12px; flex-wrap: wrap; justify-content: center; }
  .bf-hero .hero-logo { margin: 0; font-size: 18px; }
  .bf-hero .hero-search-bar { flex-direction: column; width: 95%; }
  .bf-hero .search-field { border-right: none; border-bottom: 1px solid #eee; }
  .bf-hero .search-field:last-of-type { border-bottom: none; }
  .bf-hero .search-btn { width: 100%; border-radius: 5px; }
}
</style>

<header class="custom-hero bf-hero" id="bf-hero">
  <div class="hero-header">
    <a href="<?php echo url('courses/index.php'); ?>" class="hero-search-icon" aria-label="Search courses" title="Browse courses">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
    </a>
    <nav class="hero-nav" aria-label="Main navigation">
      <a href="<?php echo url('/'); ?>" class="<?php echo dazu_nav_active('home') ? 'is-active' : ''; ?>">home</a>
      <a href="<?php echo url('courses'); ?>" class="<?php echo dazu_nav_active('courses') ? 'is-active' : ''; ?>">courses</a>
      <a href="<?php echo url('/'); ?>" class="hero-logo">
        <img src="<?php echo bonfire_asset_v('logo.webp'); ?>" alt="Dazu hub Institute" height="40" style="height:40px;width:auto;">
      </a>
      <a href="<?php echo url('about'); ?>" class="<?php echo dazu_nav_active('about') ? 'is-active' : ''; ?>">about</a>
      <a href="<?php echo url('contact'); ?>" class="<?php echo dazu_nav_active('contact') ? 'is-active' : ''; ?>">contact</a>
    </nav>
    <a href="tel:+254750116600" class="hero-contact" style="text-decoration:none;color:inherit;">+254 750 116600</a>
  </div>

  <div class="hero-center">
    <div>
      <h1 class="hero-title">LEARN TECH</h1>
      <p class="hero-subtitle">Practical software, AI &amp; cybersecurity training in Thika Town</p>
      <?php if ($courseCount > 0 || $studentCount > 0): ?>
        <div class="hero-stats">
          <?php if ($courseCount > 0): ?>
            <div class="hero-stat">
              <b><?php echo number_format($courseCount); ?>+</b>
              <small>Practical Courses</small>
            </div>
          <?php endif; ?>
          <?php if ($studentCount > 0): ?>
            <div class="hero-stat">
              <b><?php echo number_format($studentCount); ?>+</b>
              <small>Active Students</small>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="hero-bottom">
    <form class="hero-search-bar" action="<?php echo htmlspecialchars($coursesUrl, ENT_QUOTES); ?>" method="get" role="search">
      <div class="search-field">
        <label for="bf-course-q">Find a course</label>
        <input type="search" id="bf-course-q" name="q" placeholder="e.g. Python, Cybersecurity, AI…" autocomplete="off">
      </div>
      <div class="search-field">
        <label for="bf-course-track">Track</label>
        <select id="bf-course-track" name="track" onchange="if(this.value) window.location.href=this.value">
          <option value="">All Tracks</option>
          <option value="<?php echo htmlspecialchars($coursesUrl, ENT_QUOTES); ?>">Software &amp; Web</option>
          <option value="<?php echo htmlspecialchars($coursesUrl, ENT_QUOTES); ?>">AI &amp; Data Science</option>
          <option value="<?php echo htmlspecialchars($coursesUrl, ENT_QUOTES); ?>">Security &amp; Infrastructure</option>
        </select>
      </div>
      <div class="search-field">
        <label for="bf-apply">Ready to enroll?</label>
        <select id="bf-apply" name="action" onchange="if(this.value==='apply') window.location.href='<?php echo htmlspecialchars($applyUrl, ENT_QUOTES); ?>'">
          <option value="">Choose action</option>
          <option value="browse">Browse all courses</option>
          <option value="apply">Apply now</option>
        </select>
      </div>
      <button type="submit" class="search-btn">Find Courses</button>
    </form>
  </div>
</header>
