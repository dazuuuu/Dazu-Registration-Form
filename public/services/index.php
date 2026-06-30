<?php
// public/services/index.php
require_once __DIR__ . '/../../app/config/db_connect.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$search = trim($_GET['search'] ?? '');

// Get all published services
$stmt = $pdo->prepare("
    SELECT s.*, u.username as creator_name 
    FROM services s 
    LEFT JOIN users u ON s.created_by = u.id 
    WHERE s.status = 'published' 
    ORDER BY s.created_at DESC
");
$stmt->execute();
$allServices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter by search
$services = $allServices;
if ($search) {
    $services = array_filter($services, function ($s) use ($search) {
        return stripos($s['title'], $search) !== false
            || stripos($s['short_description'], $search) !== false;
    });
    $services = array_values($services);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Our Services — Ismano Portfolio</title>
  <meta name="description" content="Explore our comprehensive range of creative and technical services.">

  <link rel="stylesheet" href="../assets/css/theme.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
  /* ── Base offset for fixed nav ─────────────────────────── */
  body { padding-top: var(--navbar-height); }


  /* ═══════════════════════════════════════════════════════
     LAYOUT — sidebar + grid
  ═══════════════════════════════════════════════════════ */
  .services-layout {
    display: grid;
    grid-template-columns: 260px 1fr;
    gap: var(--space-10);
    padding: var(--space-12) 0 var(--space-20);
    align-items: start;
  }

  /* ═══════════════════════════════════════════════════════
     SIDEBAR
  ═══════════════════════════════════════════════════════ */
  .sidebar { position: sticky; top: calc(var(--navbar-height) + 24px); }

  .sidebar-block {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
    margin-bottom: var(--space-5);
  }
  .sidebar-block-title {
    font-family: var(--font-body);
    font-size: var(--text-xs);
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--color-text-muted);
    margin-bottom: var(--space-4);
    display: flex;
    align-items: center;
    gap: var(--space-2);
  }
  .sidebar-block-title i { color: var(--color-accent); font-size: 0.85em; }

  /* Search */
  .search-form { position: relative; }
  .search-form input {
    width: 100%;
    padding: 10px 44px 10px 14px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-full);
    font-family: var(--font-body);
    font-size: var(--text-sm);
    color: var(--color-text-body);
    background: var(--color-surface-alt);
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
    outline: none;
  }
  .search-form input:focus {
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(10,10,10,0.06);
    background: var(--color-surface);
  }
  .search-form button {
    position: absolute; right: 4px; top: 50%;
    transform: translateY(-50%);
    width: 34px; height: 34px;
    border-radius: var(--radius-full);
    border: none;
    background: var(--color-primary);
    color: #fff;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem;
    transition: background var(--transition-fast);
  }
  .search-form button:hover { background: #222; }

  /* Clear search */
  .clear-search {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: var(--text-xs); color: var(--color-text-muted);
    margin-top: var(--space-2);
    cursor: pointer; transition: color var(--transition-fast);
    background: none; border: none; padding: 0;
    font-family: var(--font-body);
  }
  .clear-search:hover { color: var(--color-primary); }

  /* Stats mini-block */
  .stat-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: var(--space-3) 0;
    border-bottom: 1px solid var(--color-border);
    font-size: var(--text-sm);
  }
  .stat-row:last-child { border-bottom: none; padding-bottom: 0; }
  .stat-row-label { color: var(--color-text-muted); font-weight: 400; }
  .stat-row-value { font-weight: 600; color: var(--color-text-heading); }

  /* ═══════════════════════════════════════════════════════
     SERVICES GRID
  ═══════════════════════════════════════════════════════ */
  .grid-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: var(--space-8); gap: var(--space-4); flex-wrap: wrap;
  }
  .grid-header-left h2 {
    font-size: var(--text-xl); font-weight: 700; margin: 0; line-height: 1.2;
  }
  .result-count {
    font-size: var(--text-xs); font-weight: 500; color: var(--color-text-muted);
    margin-top: 2px; display: flex; align-items: center; gap: 6px;
  }
  .result-count span {
    display: inline-block; padding: 2px 8px;
    border-radius: var(--radius-full);
    background: var(--color-surface-alt);
    border: 1px solid var(--color-border);
    font-weight: 600; color: var(--color-text-heading);
  }

  /* Filter pill */
  .filter-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px;
    border-radius: var(--radius-full);
    background: var(--color-primary);
    color: #fff;
    font-size: var(--text-xs); font-weight: 500;
    text-decoration: none;
  }
  .filter-pill i { font-size: 0.65em; opacity: 0.7; }
  .filter-pill:hover { background: #222; color: #fff; }

  .services-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-5);
  }

  /* Service card */
  .service-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    display: flex; flex-direction: column;
    transition: box-shadow var(--transition-base), transform var(--transition-base), border-color var(--transition-base);
    position: relative;
    height: 100%;
  }
  .service-card:hover {
    box-shadow: var(--shadow-xl);
    transform: translateY(-5px);
    border-color: transparent;
  }

  .service-thumb {
    position: relative;
    aspect-ratio: 16 / 10;
    overflow: hidden;
    background: var(--color-surface-alt);
  }
  .service-thumb img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform 0.65s ease;
    display: block;
  }
  .service-card:hover .service-thumb img { transform: scale(1.06); }

  /* No-image placeholder */
  .service-thumb-placeholder {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #f0f0ec, #e4e4dc);
  }
  .service-thumb-placeholder i { font-size: 2rem; color: var(--color-border); }

  .service-body {
    padding: var(--space-5) var(--space-6);
    display: flex; flex-direction: column; flex-grow: 1; gap: var(--space-3);
  }
  .service-title {
    font-family: var(--font-display);
    font-size: var(--text-lg);
    font-weight: 700;
    color: var(--color-text-heading);
    line-height: 1.3;
    margin: 0;
  }
  .service-description {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    line-height: 1.65;
    flex-grow: 1;
  }

  .service-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-4) var(--space-6);
    border-top: 1px solid var(--color-border);
    gap: var(--space-3);
  }
  .service-meta {
    display: flex; align-items: center; gap: var(--space-4);
    font-size: var(--text-xs); color: var(--color-text-muted);
  }
  .service-meta span { display: flex; align-items: center; gap: 4px; }

  .service-view-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 16px;
    border-radius: var(--radius-full);
    background: var(--color-surface-alt);
    border: 1px solid var(--color-border);
    font-size: var(--text-xs); font-weight: 600;
    color: var(--color-primary);
    text-decoration: none;
    transition: all var(--transition-fast);
    white-space: nowrap;
    flex-shrink: 0;
  }
  .service-view-btn:hover {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: #fff;
  }
  .service-view-btn i { font-size: 0.7em; transition: transform var(--transition-fast); }
  .service-view-btn:hover i { transform: translateX(3px); }

  /* ── Empty state ──────────────────────────────────────── */
  .empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: var(--space-20) var(--space-8);
    border: 1px dashed var(--color-border);
    border-radius: var(--radius-xl);
    background: var(--color-surface-alt);
  }
  .empty-icon {
    width: 72px; height: 72px;
    border-radius: 50%;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto var(--space-6);
    font-size: 1.75rem; color: var(--color-text-muted);
  }
  .empty-state h3 { font-size: var(--text-xl); margin-bottom: var(--space-3); }
  .empty-state p  { font-size: var(--text-sm); color: var(--color-text-muted); max-width: 360px; margin: 0 auto var(--space-8); }

  /* ═══════════════════════════════════════════════════════
     MOBILE RESPONSIVE
  ═══════════════════════════════════════════════════════ */
  @media (max-width: 1024px) {
    .services-layout { grid-template-columns: 220px 1fr; gap: var(--space-8); }
  }

  @media (max-width: 820px) {
    .services-layout { grid-template-columns: 1fr; }
    .sidebar { position: static; }
  }

  @media (max-width: 600px) {
    .services-grid { grid-template-columns: 1fr; }
    .grid-header { flex-direction: column; align-items: flex-start; }
    .page-hero { padding: var(--space-12) 0 var(--space-10); }
  }
  </style>
</head>
<body>

<?php include __DIR__ . '/../components/public/navbar.php'; ?>

<!-- ══════════════════════════════════════════════════════
     PAGE HERO
══════════════════════════════════════════════════════ -->

<?php
  $hero_eyebrow = 'What we offer';
  $hero_title   = 'Our Services';
  $hero_sub     = 'Creative & technical solutions, end to end';
  $hero_desc    = 'Comprehensive digital solutions tailored to elevate your business and bring your ideas to life.';
  $hero_image   = 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?w=1600&q=80';
  include __DIR__ . '/../components/public/page_hero.php';
?>

<!--section class="page-hero">
  <div class="container page-hero-inner">
    <div class="breadcrumb-wrap">
      <a href="/Ismano/public/">Home</a>
      <i class="fas fa-chevron-right sep"></i>
      <span>Services</span>
    </div>
    <p class="eyebrow">What we offer</p>
    <h1>Our <strong>Services</strong></h1>
    <p>Comprehensive digital solutions tailored to elevate your business and bring your ideas to life.</p>
  </div>
</section-->

<!-- ══════════════════════════════════════════════════════
     LAYOUT
══════════════════════════════════════════════════════ -->
<div class="container">
  <div class="services-layout">

    <!-- ── Sidebar ─────────────────────────────────────── -->
    <aside class="sidebar" aria-label="Filter services">

      <!-- Search -->
      <div class="sidebar-block">
        <p class="sidebar-block-title"><i class="fas fa-magnifying-glass"></i> Search</p>
        <form method="GET" class="search-form" role="search">
          <input type="text" name="search"
                 placeholder="Search services…"
                 value="<?php echo htmlspecialchars($search); ?>"
                 aria-label="Search services">
          <button type="submit" aria-label="Submit search">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
          </button>
        </form>
        <?php if ($search): ?>
          <a href="?" class="clear-search">
            <i class="fas fa-xmark"></i> Clear search
          </a>
        <?php endif; ?>
      </div>

      <!-- Stats -->
      <div class="sidebar-block">
        <p class="sidebar-block-title"><i class="fas fa-chart-bar"></i> Overview</p>
        <div class="stat-row">
          <span class="stat-row-label">Total services</span>
          <span class="stat-row-value"><?php echo count($allServices); ?></span>
        </div>
        <div class="stat-row">
          <span class="stat-row-label">Showing</span>
          <span class="stat-row-value"><?php echo count($services); ?></span>
        </div>
      </div>

    </aside>

    <!-- ── Services grid ────────────────────────────────── -->
    <main aria-label="Services list">

      <div class="grid-header">
        <div class="grid-header-left">
          <h2>
            <?php echo $search ? 'Search Results' : 'All Services'; ?>
          </h2>
          <p class="result-count">
            <span><?php echo count($services); ?></span>
            <?php echo count($services) === 1 ? 'service found' : 'services found'; ?>
          </p>
        </div>

        <!-- Active filter pills -->
        <?php if ($search): ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
            <a href="/Realestate/public/services/" class="filter-pill" title="Clear search">
              <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
              "<?php echo htmlspecialchars(mb_strimwidth($search, 0, 20, '…')); ?>"
              <i class="fas fa-xmark" aria-hidden="true"></i>
            </a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Grid -->
      <div class="services-grid">
        <?php if (empty($services)): ?>
          <div class="empty-state">
            <div class="empty-icon"><i class="fa-regular fa-folder-open" aria-hidden="true"></i></div>
            <h3>No services found</h3>
            <p>No services match your search criteria. Try different keywords or clear your search.</p>
            <a href="/Realestate/public/services/" class="btn btn--dark">
              View All Services <i class="fas fa-arrow-right"></i>
            </a>
          </div>

        <?php else: ?>
          <?php foreach ($services as $i => $service): ?>
            <article class="service-card reveal reveal-delay-<?php echo ($i % 4) + 1; ?>">

              <div class="service-thumb">
                <?php if (!empty($service['cover_image'])): ?>
                  <img src="<?php echo htmlspecialchars($service['cover_image']); ?>"
                       alt="<?php echo htmlspecialchars($service['title']); ?>"
                       loading="<?php echo $i < 4 ? 'eager' : 'lazy'; ?>">
                <?php else: ?>
                  <div class="service-thumb-placeholder" aria-hidden="true">
                    <i class="fa-regular fa-cogs"></i>
                  </div>
                <?php endif; ?>
              </div>

              <div class="service-body">
                <h3 class="service-title"><?php echo htmlspecialchars($service['title']); ?></h3>
                <p class="service-description">
                  <?php echo htmlspecialchars(mb_strimwidth(strip_tags($service['short_description'] ?? ''), 0, 120, '…')); ?>
                </p>
              </div>

              <div class="service-footer">
                <div class="service-meta">
                  <span><i class="fa-regular fa-eye" aria-hidden="true"></i> <?php echo number_format($service['view_count'] ?? 0); ?></span>
                </div>
                <a href="readmore.php?slug=<?php echo urlencode($service['slug']); ?>" class="service-view-btn">
                  Learn More <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>
              </div>

            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </main>
  </div><!-- /.services-layout -->
</div><!-- /.container -->

<?php include __DIR__ . '/../components/public/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Scroll reveal
(function () {
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
    });
  }, { threshold: 0.08 });
  document.querySelectorAll('.reveal').forEach(el => io.observe(el));
})();
</script>

</body>
</html>