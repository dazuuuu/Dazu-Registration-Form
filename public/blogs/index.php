<?php
// public/blogs/index.php — Blog listing (public layout)
$page_title = 'Blog — Dazu Hub Tech Skills Institute';
$page_description = 'Latest news, course updates, and insights from Dazu Hub Tech Skills Institute in Nairobi.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/config/db_connect.php';

$search = trim($_GET['search'] ?? '');
$category_slug = $_GET['category'] ?? null;

$sql = "
    SELECT b.*, c.name as category_name, c.slug as category_slug, c.color as category_color,
           u.username as author_name
    FROM blogs b
    LEFT JOIN blog_categories c ON b.category_id = c.id
    LEFT JOIN users u ON b.author_id = u.id
    WHERE b.status = 'published'
";
$params = [];

if ($category_slug) {
    $sql .= " AND c.slug = :category_slug";
    $params[':category_slug'] = $category_slug;
}
if ($search) {
    $sql .= " AND (b.title LIKE :search OR b.excerpt LIKE :search OR b.content LIKE :search)";
    $params[':search'] = "%$search%";
}
$sql .= " ORDER BY b.published_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$blogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM blog_categories ORDER BY name ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM blog_tags ORDER BY name ASC LIMIT 20");
$stmt->execute();
$allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);

$activeCategory = null;
if ($category_slug) {
    foreach ($categories as $cat) {
        if ($cat['slug'] === $category_slug) {
            $activeCategory = $cat;
            break;
        }
    }
}

// Count all published for sidebar
$countStmt = $pdo->query("SELECT COUNT(*) FROM blogs WHERE status = 'published'");
$totalPublished = (int) $countStmt->fetchColumn();

ob_start();
?>

<?php
$banner_eyebrow = 'From our blog';
$banner_title   = 'Latest insights';
$banner_lead    = 'News, tips, and stories from Dazu Hub — courses, student success, and the Nairobi tech community.';
include __DIR__ . '/../components/public/page_banner.php';
?>

<section class="dh-listing-section">
    <div class="dh-container">
        <div class="dh-listing-layout">

            <aside class="dh-sidebar" aria-label="Filter blog posts">
                <div class="dh-sidebar-block">
                    <p class="dh-sidebar-title"><i class="fas fa-magnifying-glass"></i> Search</p>
                    <form method="GET" class="dh-search-form" role="search">
                        <?php if ($category_slug): ?>
                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_slug); ?>">
                        <?php endif; ?>
                        <input type="search" name="search" placeholder="Search articles…"
                               value="<?php echo htmlspecialchars($search); ?>" aria-label="Search articles">
                        <button type="submit" aria-label="Search"><i class="fas fa-magnifying-glass"></i></button>
                    </form>
                    <?php if ($search): ?>
                        <a href="<?php echo url('blogs/' . ($category_slug ? '?category=' . urlencode($category_slug) : '')); ?>" class="dh-clear-link">
                            <i class="fas fa-xmark"></i> Clear search
                        </a>
                    <?php endif; ?>
                </div>

                <div class="dh-sidebar-block">
                    <p class="dh-sidebar-title"><i class="fas fa-folder"></i> Categories</p>
                    <ul class="dh-cat-list">
                        <li>
                            <a href="<?php echo url('blogs/' . ($search ? '?search=' . urlencode($search) : '')); ?>"
                               class="dh-cat-link <?php echo !$category_slug ? 'is-active' : ''; ?>">
                                <span>All posts</span>
                                <span class="dh-cat-count"><?php echo $totalPublished; ?></span>
                            </a>
                        </li>
                        <?php foreach ($categories as $cat):
                            $cst = $pdo->prepare(
                                "SELECT COUNT(*) FROM blogs WHERE status = 'published' AND category_id = ?"
                            );
                            $cst->execute([(int)$cat['id']]);
                            $catCount = (int) $cst->fetchColumn();
                            if ($catCount === 0) continue;
                        ?>
                        <li>
                            <a href="?category=<?php echo urlencode($cat['slug']); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                               class="dh-cat-link <?php echo $category_slug === $cat['slug'] ? 'is-active' : ''; ?>">
                                <span><?php echo htmlspecialchars($cat['name']); ?></span>
                                <span class="dh-cat-count"><?php echo $catCount; ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <?php if (!empty($allTags)): ?>
                <div class="dh-sidebar-block">
                    <p class="dh-sidebar-title"><i class="fas fa-tags"></i> Tags</p>
                    <div>
                        <?php foreach ($allTags as $tag): ?>
                            <a href="<?php echo url('blogs/tag.php?slug=' . urlencode($tag['slug'])); ?>" class="dh-tag">
                                <?php echo htmlspecialchars($tag['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </aside>

            <main>
                <div class="dh-listing-head dh-reveal">
                    <div>
                        <h2>
                            <?php
                            if ($activeCategory) {
                                echo htmlspecialchars($activeCategory['name']);
                            } elseif ($search) {
                                echo 'Search results';
                            } else {
                                echo 'All articles';
                            }
                            ?>
                        </h2>
                        <p class="dh-listing-count"><?php echo count($blogs); ?> article<?php echo count($blogs) === 1 ? '' : 's'; ?></p>
                    </div>
                </div>

                <div class="dh-card-grid">
                    <?php if (empty($blogs)): ?>
                        <div class="dh-empty-state dh-reveal">
                            <i class="far fa-newspaper"></i>
                            <h3>No posts found</h3>
                            <p>Try a different category or clear your search.</p>
                            <a href="<?php echo url('blogs/'); ?>" class="dh-btn-apply" style="display:inline-flex;">View all posts</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($blogs as $i => $blog): ?>
                        <article class="dh-media-card dh-reveal dh-delay-<?php echo ($i % 4) + 1; ?>">
                            <a href="<?php echo url('blogs/readmore.php?slug=' . urlencode($blog['slug'])); ?>" style="text-decoration:none;color:inherit;display:flex;flex-direction:column;flex:1;">
                                <div class="dh-media-card-img">
                                    <?php if (!empty($blog['featured_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($blog['featured_image']); ?>"
                                             alt="<?php echo htmlspecialchars($blog['title']); ?>"
                                             loading="<?php echo $i < 4 ? 'eager' : 'lazy'; ?>">
                                    <?php else: ?>
                                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#ecfdf3,#bbf7d0);color:#00A859;font-size:2.5rem;">
                                            <i class="fas fa-newspaper"></i>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($blog['category_name'])): ?>
                                        <span class="dh-media-card-badge"><?php echo htmlspecialchars($blog['category_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="dh-media-card-body">
                                    <div class="dh-media-card-meta">
                                        <span><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($blog['published_at'] ?? $blog['created_at'])); ?></span>
                                        <?php if (!empty($blog['author_name'])): ?>
                                            <span><i class="far fa-user"></i> <?php echo htmlspecialchars($blog['author_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="dh-media-card-title"><?php echo htmlspecialchars($blog['title']); ?></h3>
                                    <p class="dh-media-card-excerpt">
                                        <?php echo htmlspecialchars(mb_strimwidth(strip_tags($blog['excerpt'] ?? $blog['content'] ?? ''), 0, 120, '…')); ?>
                                    </p>
                                    <div class="dh-media-card-foot">
                                        <span class="dh-media-card-meta" style="margin:0;">
                                            <span><i class="far fa-eye"></i> <?php echo number_format($blog['view_count'] ?? 0); ?> views</span>
                                        </span>
                                        <span class="dh-media-card-link">Read more <i class="fas fa-arrow-right"></i></span>
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
