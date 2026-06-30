<?php
// public/projects/index.php
require_once __DIR__ . '/../../app/config/db_connect.php';
require_once __DIR__ . '/../../app/controllers/ProjectController.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$controller      = new ProjectController($pdo);
$category_filter = $_GET['category'] ?? null;
$search          = trim($_GET['search'] ?? '');

$allProjects = $controller->getProjects('published');
$categories  = $controller->getCategories();

// Filter by category
$projects = $allProjects;
if ($category_filter) {
    $projects = array_filter($allProjects, function ($p) use ($category_filter, $categories) {
        foreach ($categories as $cat) {
            if ($cat['id'] == $p['category_id'] && $cat['category_slug'] == $category_filter) return true;
        }
        return false;
    });
}

// Filter by search
if ($search) {
    $projects = array_filter($projects, function ($p) use ($search) {
        return stripos($p['small_title'],  $search) !== false
            || stripos($p['major_title'],  $search) !== false
            || stripos($p['description'],  $search) !== false;
    });
}

$projects = array_values($projects); // reset keys for indexed loops
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Our Projects — Ismano Portfolio</title>
  <meta name="description" content="Browse our full portfolio of creative projects across all categories.">

  <link rel="stylesheet" href="../assets/css/theme.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
  /* ── Base offset for fixed nav ─────────────────────────── */
  body { padding-top: var(--navbar-height); }

  /* ═══════════════════════════════════════════════════════
     PAGE HERO — minimal dark banner
  ═══════════════════════════════════════════════════════ */
  .page-hero {
    background: var(--color-primary);
    padding: var(--space-20) 0 var(--space-16);
    position: relative;
    overflow: hidden;
  }
  .page-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 60% 80% at 80% 50%,
                rgba(200,242,60,0.07) 0%, transparent 70%);
    pointer-events: none;
  }
  .page-hero-inner { position: relative; z-index: 1; }

  .page-hero .eyebrow { color: rgba(255,255,255,0.45); margin-bottom: var(--space-3); }
  .page-hero .eyebrow::before { background: var(--color-accent); }

  .page-hero h1 {
    font-family: var(--font-display);
    font-style: italic;
    font-size: clamp(2.2rem, 5vw, 3.5rem);
    font-weight: 700;
    color: #fff;
    margin-bottom: var(--space-4);
    letter-spacing: 0.01em;
    line-height: 1.08;
  }
  .page-hero h1 strong {
    font-style: normal;
    color: var(--color-accent);
  }
  .page-hero p {
    font-size: var(--text-sm);
    color: rgba(255,255,255,0.55);
    max-width: 480px;
    line-height: 1.8;
  }

  /* Breadcrumb */
  .breadcrumb-wrap {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    font-size: var(--text-xs);
    font-weight: 500;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.35);
    margin-bottom: var(--space-6);
  }
  .breadcrumb-wrap a { color: rgba(255,255,255,0.5); transition: color var(--transition-fast); }
  .breadcrumb-wrap a:hover { color: var(--color-accent); }
  .breadcrumb-wrap .sep { font-size: 8px; }

  /* ═══════════════════════════════════════════════════════
     LAYOUT — sidebar + grid
  ═══════════════════════════════════════════════════════ */
  .projects-layout {
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

  /* Category list */
  .cat-list { list-style: none; display: flex; flex-direction: column; gap: 2px; }
  .cat-link {
    display: flex; align-items: center; justify-content: space-between;
    padding: 9px 12px;
    border-radius: var(--radius-md);
    font-size: var(--text-sm);
    font-weight: 400;
    color: var(--color-text-body);
    text-decoration: none;
    transition: background var(--transition-fast), color var(--transition-fast);
    gap: var(--space-2);
  }
  .cat-link:hover { background: var(--color-surface-alt); color: var(--color-primary); }
  .cat-link.is-active {
    background: var(--color-primary);
    color: #fff;
    font-weight: 500;
  }
  .cat-link-left { display: flex; align-items: center; gap: 8px; }
  .cat-link-left i { font-size: 0.75em; color: inherit; opacity: 0.6; }
  .cat-link.is-active .cat-link-left i { opacity: 0.9; }
  .cat-count {
    font-size: 10px; font-weight: 600;
    padding: 2px 7px;
    border-radius: var(--radius-full);
    background: rgba(0,0,0,0.08);
    color: inherit;
    flex-shrink: 0;
  }
  .cat-link.is-active .cat-count { background: rgba(255,255,255,0.2); }

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
     PROJECTS GRID
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

  /* Active filter pill */
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

  .projects-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-5);
  }

  /* Project card */
  .proj-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    display: flex; flex-direction: column;
    transition: box-shadow var(--transition-base), transform var(--transition-base), border-color var(--transition-base);
    position: relative;
  }
  .proj-card:hover {
    box-shadow: var(--shadow-xl);
    transform: translateY(-5px);
    border-color: transparent;
  }

  .proj-thumb {
    position: relative;
    aspect-ratio: 16 / 10;
    overflow: hidden;
    background: var(--color-surface-alt);
  }
  .proj-thumb img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform 0.65s ease;
    display: block;
  }
  .proj-card:hover .proj-thumb img { transform: scale(1.06); }

  /* No-image placeholder */
  .proj-thumb-placeholder {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #f0f0ec, #e4e4dc);
  }
  .proj-thumb-placeholder i { font-size: 2rem; color: var(--color-border); }

  .proj-cat-badge {
    position: absolute; top: 14px; left: 14px;
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(8px);
    padding: 4px 11px;
    border-radius: var(--radius-full);
    font-size: 10px; font-weight: 600;
    color: var(--color-primary);
    letter-spacing: 0.05em; text-transform: uppercase;
  }

  .proj-body {
    padding: var(--space-5) var(--space-6);
    display: flex; flex-direction: column; flex-grow: 1; gap: var(--space-2);
  }
  .proj-subtitle {
    font-size: 10px; font-weight: 600; letter-spacing: 0.1em;
    text-transform: uppercase; color: #6a5af9;
  }
  .proj-title {
    font-family: var(--font-display);
    font-size: var(--text-lg); font-weight: 700;
    color: var(--color-text-heading); line-height: 1.3;
  }
  .proj-excerpt {
    font-size: var(--text-sm); color: var(--color-text-muted);
    line-height: 1.65; flex-grow: 1;
  }

  .proj-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding: var(--space-4) var(--space-6);
    border-top: 1px solid var(--color-border);
    gap: var(--space-3);
  }
  .proj-meta {
    display: flex; align-items: center; gap: var(--space-4);
    font-size: var(--text-xs); color: var(--color-text-muted);
  }
  .proj-meta span { display: flex; align-items: center; gap: 4px; }

  .proj-view-btn {
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
  .proj-view-btn:hover {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: #fff;
  }
  .proj-view-btn i { font-size: 0.7em; transition: transform var(--transition-fast); }
  .proj-view-btn:hover i { transform: translateX(3px); }

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
    .projects-layout { grid-template-columns: 220px 1fr; gap: var(--space-8); }
  }

  @media (max-width: 820px) {
    .projects-layout { grid-template-columns: 1fr; }

    /* Sidebar collapses to horizontal filter row on mobile */
    .sidebar { position: static; }
    .sidebar-block--categories { order: -1; }

    /* Mobile filter bar */
    .cat-list {
      flex-direction: row;
      flex-wrap: wrap;
      gap: 6px;
    }
    .cat-link {
      padding: 6px 12px;
      border: 1px solid var(--color-border);
      border-radius: var(--radius-full);
    }
    .cat-link.is-active { border-color: var(--color-primary); }
    .cat-link-left i { display: none; }
  }

  @media (max-width: 600px) {
    .projects-grid { grid-template-columns: 1fr; }
    .grid-header { flex-direction: column; align-items: flex-start; }
    .page-hero { padding: var(--space-12) 0 var(--space-10); }
  }

  @media (max-width: 380px) {
    .cat-count { display: none; }
  }
  </style>
</head>
<body>

<!--?php include  '/public/components/public/nav.php'; ?-->
<?php include __DIR__ . '/../components/public/navbar.php'; ?>


<?php
  $hero_eyebrow = 'Our work';
  $hero_title   = 'Creative Projects';
  $hero_sub     = 'Innovative work across every discipline';
  $hero_desc    = 'Explore our portfolio of innovative solutions and creative work.';
  $hero_image   = 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=1600&q=80';
  include __DIR__ . '/../components/public/page_hero.php';
?>


<!-- ══════════════════════════════════════════════════════
     PAGE HERO
══════════════════════════════════════════════════════ -->
<!--section class="page-hero">
  <div class="container page-hero-inner">
    <div class="breadcrumb-wrap">
      <a href="/Ismano/public/">Home</a>
      <i class="fas fa-chevron-right sep"></i>
      <span>Projects</span>
    </div>
    <p class="eyebrow">Our work</p>
    <h1>Creative <strong>Projects</strong></h1>
    <p>Explore our portfolio of innovative solutions and creative work across every discipline.</p>
  </div>
</section-->

<!-- ══════════════════════════════════════════════════════
     LAYOUT
══════════════════════════════════════════════════════ -->
<div class="container">
  <div class="projects-layout">

    <!-- ── Sidebar ─────────────────────────────────────── -->
    <aside class="sidebar" aria-label="Filter projects">

      <!-- Search -->
      <div class="sidebar-block">
        <p class="sidebar-block-title"><i class="fas fa-magnifying-glass"></i> Search</p>
        <form method="GET" class="search-form" role="search">
          <?php if ($category_filter): ?>
            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
          <?php endif; ?>
          <input type="text" name="search"
                 placeholder="Search projects…"
                 value="<?php echo htmlspecialchars($search); ?>"
                 aria-label="Search projects">
          <button type="submit" aria-label="Submit search">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
          </button>
        </form>
        <?php if ($search): ?>
          <a href="?<?php echo $category_filter ? 'category=' . urlencode($category_filter) : ''; ?>"
             class="clear-search">
            <i class="fas fa-xmark"></i> Clear search
          </a>
        <?php endif; ?>
      </div>

      <!-- Categories -->
      <div class="sidebar-block sidebar-block--categories">
        <p class="sidebar-block-title"><i class="fas fa-layer-group"></i> Categories</p>
        <ul class="cat-list" role="list">
          <li>
            <a href="/Realestate/public/projects/<?php echo $search ? '?search=' . urlencode($search) : ''; ?>"
               class="cat-link <?php echo !$category_filter ? 'is-active' : ''; ?>">
              <span class="cat-link-left">
                <i class="fas fa-border-all" aria-hidden="true"></i> All Projects
              </span>
              <span class="cat-count"><?php echo count($allProjects); ?></span>
            </a>
          </li>
          <?php foreach ($categories as $cat):
            $catCount = count(array_filter($allProjects, fn($p) => $p['category_id'] == $cat['id']));
            if ($catCount === 0) continue; ?>
            <li>
              <a href="?category=<?php echo urlencode($cat['category_slug']); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                 class="cat-link <?php echo $category_filter === $cat['category_slug'] ? 'is-active' : ''; ?>">
                <span class="cat-link-left">
                  <i class="fas fa-folder" aria-hidden="true"></i>
                  <?php echo htmlspecialchars($cat['category_name']); ?>
                </span>
                <span class="cat-count"><?php echo $catCount; ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Stats -->
      <div class="sidebar-block">
        <p class="sidebar-block-title"><i class="fas fa-chart-bar"></i> Overview</p>
        <div class="stat-row">
          <span class="stat-row-label">Total projects</span>
          <span class="stat-row-value"><?php echo count($allProjects); ?></span>
        </div>
        <div class="stat-row">
          <span class="stat-row-label">Categories</span>
          <span class="stat-row-value"><?php echo count($categories); ?></span>
        </div>
        <div class="stat-row">
          <span class="stat-row-label">Showing</span>
          <span class="stat-row-value"><?php echo count($projects); ?></span>
        </div>
      </div>

    </aside>

    <!-- ── Projects grid ────────────────────────────────── -->
    <main aria-label="Projects list">

      <div class="grid-header">
        <div class="grid-header-left">
          <h2>
            <?php if ($category_filter):
              $activeCat = null;
              foreach ($categories as $c) { if ($c['category_slug'] === $category_filter) { $activeCat = $c; break; } }
              echo $activeCat ? htmlspecialchars($activeCat['category_name']) : 'Filtered Projects';
            elseif ($search):
              echo 'Search Results';
            else:
              echo 'All Projects';
            endif; ?>
          </h2>
          <p class="result-count">
            <span><?php echo count($projects); ?></span>
            <?php echo count($projects) === 1 ? 'project found' : 'projects found'; ?>
          </p>
        </div>

        <!-- Active filter pills -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
          <?php if ($category_filter && $activeCat ?? null): ?>
            <a href="/Realestate/public/projects/<?php echo $search ? '?search=' . urlencode($search) : ''; ?>"
               class="filter-pill" title="Remove category filter">
              <i class="fas fa-folder" aria-hidden="true"></i>
              <?php echo htmlspecialchars($activeCat['category_name']); ?>
              <i class="fas fa-xmark" aria-hidden="true"></i>
            </a>
          <?php endif; ?>
          <?php if ($search): ?>
            <a href="/Realestate/public/projects/<?php echo $category_filter ? '?category=' . urlencode($category_filter) : ''; ?>"
               class="filter-pill" title="Clear search">
              <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
              "<?php echo htmlspecialchars(mb_strimwidth($search, 0, 20, '…')); ?>"
              <i class="fas fa-xmark" aria-hidden="true"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Grid -->
      <div class="projects-grid">
        <?php if (empty($projects)): ?>
          <div class="empty-state">
            <div class="empty-icon"><i class="fa-regular fa-folder-open" aria-hidden="true"></i></div>
            <h3>No projects found</h3>
            <p>No projects match your current filters. Try a different category or clear your search.</p>
            <a href="/Realestate/public/projects/" class="btn btn--dark">
              View All Projects <i class="fas fa-arrow-right"></i>
            </a>
          </div>

        <?php else: ?>
          <?php foreach ($projects as $i => $project): ?>
            <article class="proj-card reveal reveal-delay-<?php echo ($i % 4) + 1; ?>">

              <div class="proj-thumb">
                <?php if (!empty($project['cover_image'])): ?>
                  <img src="<?php echo htmlspecialchars($project['cover_image']); ?>"
                       alt="<?php echo htmlspecialchars($project['small_title']); ?>"
                       loading="<?php echo $i < 4 ? 'eager' : 'lazy'; ?>">
                <?php else: ?>
                  <div class="proj-thumb-placeholder" aria-hidden="true">
                    <i class="fa-regular fa-image"></i>
                  </div>
                <?php endif; ?>

                <?php if (!empty($project['category_name'])): ?>
                  <span class="proj-cat-badge"><?php echo htmlspecialchars($project['category_name']); ?></span>
                <?php endif; ?>
              </div>

              <div class="proj-body">
                <?php if (!empty($project['major_title'])): ?>
                  <div class="proj-subtitle"><?php echo htmlspecialchars($project['major_title']); ?></div>
                <?php endif; ?>
                <h3 class="proj-title"><?php echo htmlspecialchars($project['small_title']); ?></h3>
                <p class="proj-excerpt">
                  <?php echo htmlspecialchars(mb_strimwidth(strip_tags($project['description'] ?? ''), 0, 110, '…')); ?>
                </p>
              </div>

              <div class="proj-footer">
                <div class="proj-meta">
                  <span><i class="fa-regular fa-eye" aria-hidden="true"></i> <?php echo number_format($project['view_count'] ?? 0); ?></span>
                  <span><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?php echo date('M Y', strtotime($project['created_at'])); ?></span>
                </div>
                <a href="readmore.php?id=<?php echo (int)$project['id']; ?>" class="proj-view-btn">
                  View <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>
              </div>

            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </main>
  </div><!-- /.projects-layout -->
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