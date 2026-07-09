<?php
// public/projects/index.php — Student projects (public layout)
$page_title = 'Student Projects — Dazu Hub Tech Skills Institute';
$page_description = 'Browse real applications and portfolios built by Dazu Hub students during their training programs.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/config/db_connect.php';
require_once __DIR__ . '/../../app/v1/controllers/ProjectController.php';

$controller      = new ProjectController($pdo);
$category_filter = $_GET['category'] ?? null;
$search          = trim($_GET['search'] ?? '');

$allProjects = $controller->getProjects('published');
$categories  = $controller->getCategories();

$projects = $allProjects;
if ($category_filter) {
    $projects = array_filter($allProjects, function ($p) use ($category_filter, $categories) {
        foreach ($categories as $cat) {
            if ($cat['id'] == $p['category_id'] && $cat['category_slug'] == $category_filter) {
                return true;
            }
        }
        return false;
    });
}
if ($search) {
    $projects = array_filter($projects, function ($p) use ($search) {
        return stripos($p['small_title'], $search) !== false
            || stripos($p['major_title'], $search) !== false
            || stripos($p['description'], $search) !== false;
    });
}
$projects = array_values($projects);

$activeCat = null;
if ($category_filter) {
    foreach ($categories as $c) {
        if ($c['category_slug'] === $category_filter) {
            $activeCat = $c;
            break;
        }
    }
}

ob_start();
?>

<?php
$banner_eyebrow = 'Student work';
$banner_title   = 'Projects';
$banner_lead    = 'Real applications and portfolios created by our students during training.';
include __DIR__ . '/../components/public/page_banner.php';
?>

<section class="dh-listing-section">
    <div class="dh-container">
        <div class="dh-listing-layout">

            <aside class="dh-sidebar" aria-label="Filter projects">
                <div class="dh-sidebar-block">
                    <p class="dh-sidebar-title"><i class="fas fa-magnifying-glass"></i> Search</p>
                    <form method="GET" class="dh-search-form" role="search">
                        <?php if ($category_filter): ?>
                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                        <?php endif; ?>
                        <input type="search" name="search" placeholder="Search projects…"
                               value="<?php echo htmlspecialchars($search); ?>" aria-label="Search projects">
                        <button type="submit" aria-label="Search"><i class="fas fa-magnifying-glass"></i></button>
                    </form>
                    <?php if ($search): ?>
                        <a href="<?php echo url('projects/' . ($category_filter ? '?category=' . urlencode($category_filter) : '')); ?>" class="dh-clear-link">
                            <i class="fas fa-xmark"></i> Clear search
                        </a>
                    <?php endif; ?>
                </div>

                <div class="dh-sidebar-block">
                    <p class="dh-sidebar-title"><i class="fas fa-layer-group"></i> Categories</p>
                    <ul class="dh-cat-list">
                        <li>
                            <a href="<?php echo url('projects/' . ($search ? '?search=' . urlencode($search) : '')); ?>"
                               class="dh-cat-link <?php echo !$category_filter ? 'is-active' : ''; ?>">
                                <span>All projects</span>
                                <span class="dh-cat-count"><?php echo count($allProjects); ?></span>
                            </a>
                        </li>
                        <?php foreach ($categories as $cat):
                            $catCount = count(array_filter($allProjects, fn($p) => (int)$p['category_id'] === (int)$cat['id']));
                            if ($catCount === 0) continue; ?>
                        <li>
                            <a href="?category=<?php echo urlencode($cat['category_slug']); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                               class="dh-cat-link <?php echo $category_filter === $cat['category_slug'] ? 'is-active' : ''; ?>">
                                <span><?php echo htmlspecialchars($cat['category_name']); ?></span>
                                <span class="dh-cat-count"><?php echo $catCount; ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </aside>

            <main>
                <div class="dh-listing-head dh-reveal">
                    <div>
                        <h2>
                            <?php
                            if ($activeCat) {
                                echo htmlspecialchars($activeCat['category_name']);
                            } elseif ($search) {
                                echo 'Search results';
                            } else {
                                echo 'All projects';
                            }
                            ?>
                        </h2>
                        <p class="dh-listing-count"><?php echo count($projects); ?> project<?php echo count($projects) === 1 ? '' : 's'; ?> found</p>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <?php if ($activeCat): ?>
                            <a href="<?php echo url('projects/' . ($search ? '?search=' . urlencode($search) : '')); ?>" class="dh-filter-pill">
                                <?php echo htmlspecialchars($activeCat['category_name']); ?> <i class="fas fa-xmark"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($search): ?>
                            <a href="<?php echo url('projects/' . ($category_filter ? '?category=' . urlencode($category_filter) : '')); ?>" class="dh-filter-pill">
                                "<?php echo htmlspecialchars(mb_strimwidth($search, 0, 20, '…')); ?>" <i class="fas fa-xmark"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dh-card-grid">
                    <?php if (empty($projects)): ?>
                        <div class="dh-empty-state dh-reveal">
                            <i class="far fa-folder-open"></i>
                            <h3>No projects found</h3>
                            <p>Try a different category or clear your search.</p>
                            <a href="<?php echo url('projects/'); ?>" class="dh-btn-apply" style="display:inline-flex;">View all</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($projects as $i => $project): ?>
                        <article class="dh-media-card dh-reveal dh-delay-<?php echo ($i % 4) + 1; ?>">
                            <a href="<?php echo url('projects/readmore.php?id=' . (int)$project['id']); ?>" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;flex:1;">
                                <div class="dh-media-card-img">
                                    <?php if (!empty($project['cover_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($project['cover_image']); ?>"
                                             alt="<?php echo htmlspecialchars($project['small_title']); ?>"
                                             loading="<?php echo $i < 4 ? 'eager' : 'lazy'; ?>">
                                    <?php else: ?>
                                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#ecfdf3,#d1fae5);color:#00A859;font-size:2.5rem;">
                                            <i class="far fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($project['category_name'])): ?>
                                        <span class="dh-media-card-badge"><?php echo htmlspecialchars($project['category_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="dh-media-card-body">
                                    <?php if (!empty($project['major_title'])): ?>
                                        <div class="dh-media-card-meta">
                                            <?php if (!empty($project['student_name'])): ?>
                                                <?php echo htmlspecialchars($project['student_name']); ?>
                                                <?php if (!empty($project['year_of_study'])): ?>
                                                    · Year <?php echo htmlspecialchars($project['year_of_study']); ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($project['major_title']); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <h3 class="dh-media-card-title"><?php echo htmlspecialchars($project['small_title']); ?></h3>
                                    <p class="dh-media-card-excerpt">
                                        <?php echo htmlspecialchars(mb_strimwidth(strip_tags($project['description'] ?? ''), 0, 120, '…')); ?>
                                    </p>
                                    <div class="dh-media-card-foot">
                                        <span class="dh-media-card-meta" style="margin:0;">
                                            <span><i class="far fa-eye"></i> <?php echo number_format($project['view_count'] ?? 0); ?></span>
                                            <span><i class="far fa-calendar"></i> <?php echo date('M Y', strtotime($project['created_at'])); ?></span>
                                        </span>
                                        <span class="dh-media-card-link">View <i class="fas fa-arrow-right"></i></span>
                                    </div>
                                </div>
                            </a>
                        </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</section>

<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/layout.php';
