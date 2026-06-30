<?php
// public/blogs/index.php
require_once __DIR__ . '/../../app/config/db_connect.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$search = trim($_GET['search'] ?? '');
$category_slug = $_GET['category'] ?? null;

// Get all published blogs
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
$allBlogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for sidebar
$stmt = $pdo->prepare("SELECT * FROM blog_categories ORDER BY name ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all tags
$stmt = $pdo->prepare("SELECT * FROM blog_tags ORDER BY name ASC");
$stmt->execute();
$allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active category name
$activeCategory = null;
if ($category_slug) {
    foreach ($categories as $cat) {
        if ($cat['slug'] === $category_slug) {
            $activeCategory = $cat;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $activeCategory ? htmlspecialchars($activeCategory['name']) . ' - ' : ''; ?>Blog - Ismano</title>
    <meta name="description" content="Latest news, updates, and insights from Ismano">

    <link rel="stylesheet" href="../assets/css/theme.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body { padding-top: var(--navbar-height); background: var(--color-bg); }

        /* Hero Section */
        .page-hero {
            background: var(--color-primary);
            padding: var(--space-16) 0 var(--space-14);
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
        .page-hero h1 {
            font-family: var(--font-display);
            font-style: italic;
            font-size: clamp(2.2rem, 5vw, 3.5rem);
            font-weight: 700;
            color: #fff;
            margin-bottom: var(--space-4);
            letter-spacing: 0.01em;
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
        .breadcrumb-wrap a { color: rgba(255,255,255,0.5); text-decoration: none; transition: color var(--transition-fast); }
        .breadcrumb-wrap a:hover { color: var(--color-accent); }
        .breadcrumb-wrap .sep { font-size: 8px; }

        /* Layout */
        .blogs-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: var(--space-10);
            padding: var(--space-12) 0 var(--space-20);
            align-items: start;
        }

        /* Sidebar */
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
        .sidebar-block-title i { color: var(--color-accent); }

        /* Search */
        .search-form { position: relative; }
        .search-form input {
            width: 100%;
            padding: 10px 44px 10px 14px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-full);
            background: var(--color-surface-alt);
            font-size: var(--text-sm);
            transition: border-color var(--transition-fast);
            outline: none;
        }
        .search-form input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(10,10,10,0.06);
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
        }

        /* Category list */
        .cat-list { list-style: none; display: flex; flex-direction: column; gap: 2px; }
        .cat-link {
            display: flex; align-items: center; justify-content: space-between;
            padding: 9px 12px;
            border-radius: var(--radius-md);
            font-size: var(--text-sm);
            color: var(--color-text-body);
            text-decoration: none;
            transition: background var(--transition-fast);
        }
        .cat-link:hover { background: var(--color-surface-alt); color: var(--color-primary); }
        .cat-link.is-active {
            background: var(--color-primary);
            color: #fff;
            font-weight: 500;
        }
        .cat-count {
            font-size: 10px; font-weight: 600;
            padding: 2px 7px;
            border-radius: var(--radius-full);
            background: rgba(0,0,0,0.08);
        }
        .cat-link.is-active .cat-count { background: rgba(255,255,255,0.2); }

        /* Tags */
        .tag {
            display: inline-block;
            padding: 5px 12px;
            margin: 3px;
            background: var(--color-surface-alt);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-full);
            font-size: var(--text-xs);
            color: var(--color-text-body);
            text-decoration: none;
            transition: all var(--transition-fast);
        }
        .tag:hover {
            background: var(--color-primary);
            border-color: var(--color-primary);
            color: white;
        }

        /* Blog Grid */
        .blogs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-5);
        }
        .blog-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: all var(--transition-base);
            height: 100%;
        }
        .blog-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: transparent;
        }
        .blog-image {
            position: relative;
            aspect-ratio: 16 / 10;
            overflow: hidden;
        }
        .blog-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.65s ease;
        }
        .blog-card:hover .blog-image img { transform: scale(1.06); }
        .blog-category-badge {
            position: absolute; top: 14px; left: 14px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .blog-content { padding: var(--space-5) var(--space-6); }
        .blog-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--space-3);
            font-size: var(--text-xs);
            color: var(--color-text-muted);
        }
        .blog-title {
            font-family: var(--font-display);
            font-size: var(--text-lg);
            font-weight: 700;
            margin-bottom: var(--space-3);
        }
        .blog-title a {
            color: var(--color-text-heading);
            text-decoration: none;
            transition: color var(--transition-fast);
        }
        .blog-title a:hover { color: var(--color-primary); }
        .blog-excerpt {
            font-size: var(--text-sm);
            color: var(--color-text-muted);
            line-height: 1.65;
            margin-bottom: var(--space-4);
        }
        .blog-readmore {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: var(--radius-full);
            background: var(--color-surface-alt);
            border: 1px solid var(--color-border);
            font-size: var(--text-xs);
            font-weight: 600;
            color: var(--color-primary);
            text-decoration: none;
            transition: all var(--transition-fast);
        }
        .blog-readmore:hover {
            background: var(--color-primary);
            border-color: var(--color-primary);
            color: #fff;
        }
        .blog-readmore:hover i { transform: translateX(3px); }

        /* Empty state */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: var(--space-20) var(--space-8);
            border: 1px dashed var(--color-border);
            border-radius: var(--radius-xl);
        }

        @media (max-width: 820px) {
            .blogs-layout { grid-template-columns: 1fr; }
            .sidebar { position: static; }
        }
        @media (max-width: 600px) {
            .blogs-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../components/public/navbar.php'; ?>

<?php
  $hero_eyebrow = 'From our blog';
  $hero_title   = 'Latest Insights';
  $hero_sub     = 'Stories, thoughts & perspectives from our team';
  $hero_image   = 'https://images.unsplash.com/photo-1499750310107-5fef28a66643?w=1600&q=80';
  include __DIR__ . '/../components/public/page_hero.php';
?>

<!-- Hero Section -->
<!--section class="page-hero">
    <div class="container page-hero-inner">
        <div class="breadcrumb-wrap">
            <a href="/Ismano/public/">Home</a>
            <i class="fas fa-chevron-right sep"></i>
            <span>Blog</span>
        </div>
        <h1>Latest <strong>Insights</strong></h1>
        <p>Stories, thoughts, and perspectives from our team.</p>
    </div>
</section-->

<div class="container">
    <div class="blogs-layout">

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-block">
                <p class="sidebar-block-title"><i class="fas fa-magnifying-glass"></i> Search</p>
                <form method="GET" class="search-form">
                    <?php if ($category_slug): ?>
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_slug); ?>">
                    <?php endif; ?>
                    <input type="text" name="search" placeholder="Search articles…" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-magnifying-glass"></i></button>
                </form>
                <?php if ($search): ?>
                    <a href="<?php echo $category_slug ? '?category=' . urlencode($category_slug) : '?'; ?>" class="clear-search" style="display:inline-flex;align-items:center;gap:4px;margin-top:10px;font-size:12px;">
                        <i class="fas fa-xmark"></i> Clear search
                    </a>
                <?php endif; ?>
            </div>

            <div class="sidebar-block">
                <p class="sidebar-block-title"><i class="fas fa-folder"></i> Categories</p>
                <ul class="cat-list">
                    <li>
                        <a href="/Realestate/public/blogs/" class="cat-link <?php echo !$category_slug ? 'is-active' : ''; ?>">
                            <span>All Posts</span>
                            <span class="cat-count"><?php echo count($allBlogs); ?></span>
                        </a>
                    </li>
                    <?php foreach ($categories as $cat):
                        $catCount = 0;
                        foreach ($allBlogs as $blog) {
                            if ($blog['category_id'] == $cat['id']) $catCount++;
                        }
                        if ($catCount === 0) continue;
                    ?>
                        <li>
                            <a href="?category=<?php echo urlencode($cat['slug']); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                               class="cat-link <?php echo $category_slug === $cat['slug'] ? 'is-active' : ''; ?>">
                                <span><?php echo htmlspecialchars($cat['name']); ?></span>
                                <span class="cat-count"><?php echo $catCount; ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="sidebar-block">
                <p class="sidebar-block-title"><i class="fas fa-tags"></i> Popular Tags</p>
                <div>
                    <?php foreach ($allTags as $tag): ?>
                        <a href="tag.php?slug=<?php echo urlencode($tag['slug']); ?>" class="tag">
                            <?php echo htmlspecialchars($tag['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

        <!-- Blog Grid -->
        <main>
            <div class="blogs-grid">
                <?php if (empty($allBlogs)): ?>
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fa-regular fa-newspaper"></i></div>
                        <h3>No posts found</h3>
                        <p>No blog posts match your current filters. Try a different category or clear your search.</p>
                        <a href="/Realestate/public/blogs/" class="btn btn--dark">View All Posts</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($allBlogs as $index => $blog): ?>
                        <article class="blog-card">
                            <div class="blog-image">
                                <?php if ($blog['featured_image']): ?>
                                    <img src="<?php echo htmlspecialchars($blog['featured_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($blog['title']); ?>"
                                         loading="<?php echo $index < 4 ? 'eager' : 'lazy'; ?>">
                                <?php else: ?>
                                    <div style="width:100%;height:100%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;">
                                        <i class="fas fa-newspaper fa-3x text-white"></i>
                                    </div>
                                <?php endif; ?>
                                <?php if ($blog['category_name']): ?>
                                    <span class="blog-category-badge"><?php echo htmlspecialchars($blog['category_name']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="blog-content">
                                <div class="blog-meta">
                                    <span><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($blog['published_at'] ?? $blog['created_at'])); ?></span>
                                    <span><i class="far fa-eye"></i> <?php echo number_format($blog['view_count']); ?> views</span>
                                </div>
                                <h3 class="blog-title">
                                    <a href="readmore.php?slug=<?php echo urlencode($blog['slug']); ?>">
                                        <?php echo htmlspecialchars($blog['title']); ?>
                                    </a>
                                </h3>
                                <p class="blog-excerpt">
                                    <?php echo htmlspecialchars(substr(strip_tags($blog['excerpt'] ?? $blog['content']), 0, 120)); ?>...
                                </p>
                                <a href="readmore.php?slug=<?php echo urlencode($blog['slug']); ?>" class="blog-readmore">
                                    Read More <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include __DIR__ . '/../components/public/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>