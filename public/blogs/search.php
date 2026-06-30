<?php
// public/blogs/search.php

$keyword = trim($_GET['q'] ?? $_GET['search'] ?? '');
if (empty($keyword)) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/../../app/config/db_connect.php';

// Search blogs
$stmt = $pdo->prepare("
    SELECT b.*, c.name as category_name, c.slug as category_slug,
           u.username as author_name
    FROM blogs b 
    LEFT JOIN blog_categories c ON b.category_id = c.id 
    LEFT JOIN users u ON b.author_id = u.id
    WHERE b.status = 'published' 
    AND (b.title LIKE :keyword OR b.excerpt LIKE :keyword OR b.content LIKE :keyword)
    ORDER BY b.published_at DESC
");
$keywordParam = "%$keyword%";
$stmt->bindParam(':keyword', $keywordParam);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results for "<?php echo htmlspecialchars($keyword); ?>" - Ismano Blog</title>
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { padding-top: var(--navbar-height); }
        .search-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 60px 0;
            color: white;
        }
        .result-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            margin-bottom: var(--space-5);
            transition: all var(--transition-fast);
        }
        .result-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .result-title {
            font-family: var(--font-display);
            font-size: var(--text-lg);
            margin-bottom: var(--space-2);
        }
        .result-title a {
            color: var(--color-text-heading);
            text-decoration: none;
        }
        .result-title a:hover { color: var(--color-primary); }
        .result-meta {
            font-size: var(--text-xs);
            color: var(--color-text-muted);
            margin-bottom: var(--space-3);
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../components/public/nav_home.php'; ?>

<?php
  $hero_eyebrow = 'Search';
  $hero_title   = 'Search Results';
  $hero_sub     = 'Found ' . count($results) . ' result' . (count($results) === 1 ? '' : 's')
                . ' for "' . $keyword . '"';
  $hero_image   = 'https://images.unsplash.com/photo-1457369804613-52c61a468e7d?w=1600&q=80';
  include __DIR__ . '/../components/public/page_hero.php';
?>

<!--section class="search-hero">
    <div class="container">
        <div class="breadcrumb-wrap" style="display:flex;gap:8px;margin-bottom:20px;font-size:12px;">
            <a href="/Ismano/public/" class="text-white-50">Home</a>
            <i class="fas fa-chevron-right text-white-50"></i>
            <a href="/Ismano/public/blogs/" class="text-white-50">Blog</a>
            <i class="fas fa-chevron-right text-white-50"></i>
            <span class="text-white-50">Search</span>
        </div>
        <h1>Search Results</h1>
        <p class="lead">Found <?php echo count($results); ?> results for "<?php echo htmlspecialchars($keyword); ?>"</p>
    </div>
</section-->

<div class="container my-5">
    <?php if (empty($results)): ?>
        <div class="text-center py-5">
            <i class="fas fa-search fa-4x text-muted mb-4"></i>
            <h3>No results found</h3>
            <p class="text-muted">Try different keywords or browse all posts.</p>
            <a href="/Realestate/public/blogs/" class="btn btn-primary">View All Posts</a>
        </div>
    <?php else: ?>
        <?php foreach ($results as $result): ?>
            <div class="result-card">
                <div class="result-meta">
                    <span class="badge bg-primary"><?php echo htmlspecialchars($result['category_name'] ?? 'Uncategorized'); ?></span>
                    <span class="ms-3"><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($result['published_at'])); ?></span>
                    <span class="ms-3"><i class="far fa-eye"></i> <?php echo number_format($result['view_count']); ?> views</span>
                </div>
                <h3 class="result-title">
                    <a href="readmore.php?slug=<?php echo urlencode($result['slug']); ?>">
                        <?php echo htmlspecialchars($result['title']); ?>
                    </a>
                </h3>
                <p class="text-muted"><?php echo htmlspecialchars(substr(strip_tags($result['excerpt']), 0, 160)); ?>...</p>
                <a href="readmore.php?slug=<?php echo urlencode($result['slug']); ?>" class="btn btn-sm btn-outline-primary">
                    Read More <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../components/public/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>