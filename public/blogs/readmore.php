<?php
// public/blogs/readmore.php

$slug = $_GET['slug'] ?? null;
if (!$slug) {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/../../app/config/db_connect.php';

// Get blog post with all details
$stmt = $pdo->prepare("
    SELECT b.*, c.name as category_name, c.slug as category_slug, c.color as category_color,
           u.username as author_name
    FROM blogs b 
    LEFT JOIN blog_categories c ON b.category_id = c.id 
    LEFT JOIN users u ON b.author_id = u.id
    WHERE b.slug = :slug AND b.status = 'published'
");
$stmt->execute([':slug' => $slug]);
$blog = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$blog) {
    header('Location: index.php');
    exit();
}

// Update view count
$updateStmt = $pdo->prepare("UPDATE blogs SET view_count = view_count + 1 WHERE id = :id");
$updateStmt->execute([':id' => $blog['id']]);

// Get blog sections
$stmt = $pdo->prepare("SELECT * FROM blog_sections WHERE blog_id = :blog_id ORDER BY sort_order ASC");
$stmt->execute([':blog_id' => $blog['id']]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get blog tags
$stmt = $pdo->prepare("
    SELECT t.* FROM blog_tags t
    INNER JOIN blog_tag_relations tr ON t.id = tr.tag_id
    WHERE tr.blog_id = :blog_id
");
$stmt->execute([':blog_id' => $blog['id']]);
$tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get related posts
$stmt = $pdo->prepare("
    SELECT b.*, c.name as category_name
    FROM blogs b
    LEFT JOIN blog_categories c ON b.category_id = c.id
    WHERE b.status = 'published' AND b.id != :blog_id 
    AND b.category_id = :category_id
    ORDER BY b.published_at DESC
    LIMIT 3
");
$stmt->execute([':blog_id' => $blog['id'], ':category_id' => $blog['category_id']]);
$relatedPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper for YouTube
function getYoutubeId($url) {
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
    return $matches[1] ?? '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($blog['meta_title'] ?? $blog['title']); ?> - Ismano Blog</title>
    <meta name="description" content="<?php echo htmlspecialchars($blog['meta_description'] ?? substr(strip_tags($blog['excerpt']), 0, 160)); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($blog['meta_keywords'] ?? ''); ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($blog['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(substr(strip_tags($blog['excerpt']), 0, 160)); ?>">
    <?php if ($blog['featured_image']): ?>
        <meta property="og:image" content="<?php echo $blog['featured_image']; ?>">
    <?php endif; ?>
    <meta property="og:type" content="article">
    <meta property="article:published_time" content="<?php echo $blog['published_at']; ?>">

    <link rel="stylesheet" href="../assets/css/theme.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body { padding-top: var(--navbar-height); background: var(--color-bg); }

        /* Hero */
        .post-hero {
            background: var(--color-primary);
            padding: var(--space-12) 0 var(--space-10);
            position: relative;
            overflow: hidden;
        }
        .post-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 60% 80% at 80% 50%,
                        rgba(200,242,60,0.07) 0%, transparent 70%);
            pointer-events: none;
        }
        .post-hero-inner { position: relative; z-index: 1; }
        .post-category {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: var(--text-xs);
            font-weight: 500;
            color: var(--color-accent);
            margin-bottom: var(--space-4);
        }
        .post-hero h1 {
            font-family: var(--font-display);
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 700;
            color: #fff;
            margin-bottom: var(--space-4);
            line-height: 1.2;
        }
        .post-meta {
            display: flex;
            gap: var(--space-5);
            flex-wrap: wrap;
            font-size: var(--text-sm);
            color: rgba(255,255,255,0.55);
        }
        .breadcrumb-wrap {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: var(--text-xs);
            text-transform: uppercase;
            color: rgba(255,255,255,0.35);
            margin-bottom: var(--space-6);
        }
        .breadcrumb-wrap a { color: rgba(255,255,255,0.5); text-decoration: none; }
        .breadcrumb-wrap a:hover { color: var(--color-accent); }

        /* Content */
        .content-layout {
            max-width: 850px;
            margin: 0 auto;
            padding: var(--space-12) 0;
        }
        .featured-image {
            width: 100%;
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-8);
            box-shadow: var(--shadow-lg);
        }
        .blog-content {
            font-size: var(--text-base);
            line-height: 1.8;
            color: var(--color-text-body);
        }
        .blog-content p { margin-bottom: var(--space-5); }
        .blog-content h2 {
            font-size: var(--text-xl);
            margin: var(--space-8) 0 var(--space-4);
            color: var(--color-text-heading);
        }
        .blog-content h3 {
            font-size: var(--text-lg);
            margin: var(--space-6) 0 var(--space-3);
        }
        .blog-content img {
            max-width: 100%;
            border-radius: var(--radius-lg);
            margin: var(--space-5) 0;
        }
        .blog-content blockquote {
            border-left: 4px solid var(--color-primary);
            padding: var(--space-4) var(--space-6);
            background: var(--color-surface-alt);
            border-radius: var(--radius-md);
            margin: var(--space-5) 0;
            font-style: italic;
        }

        /* Tags */
        .tags-section {
            padding-top: var(--space-6);
            margin-top: var(--space-8);
            border-top: 1px solid var(--color-border);
        }
        .tag {
            display: inline-block;
            padding: 5px 12px;
            margin: 3px;
            background: var(--color-surface-alt);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-full);
            font-size: var(--text-xs);
            text-decoration: none;
            color: var(--color-text-body);
            transition: all var(--transition-fast);
        }
        .tag:hover {
            background: var(--color-primary);
            border-color: var(--color-primary);
            color: white;
        }

        /* Share buttons */
        .share-section {
            margin: var(--space-8) 0;
            padding: var(--space-6);
            background: var(--color-surface-alt);
            border-radius: var(--radius-lg);
            text-align: center;
        }
        .share-buttons {
            display: flex;
            justify-content: center;
            gap: var(--space-3);
            margin-top: var(--space-4);
        }
        .share-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: transform var(--transition-fast);
            text-decoration: none;
        }
        .share-btn:hover { transform: translateY(-3px); color: white; }
        .share-facebook { background: #3b5998; }
        .share-twitter { background: #1da1f2; }
        .share-linkedin { background: #0077b5; }
        .share-whatsapp { background: #25d366; }

        /* Related posts */
        .related-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-5);
            margin-top: var(--space-6);
        }
        .related-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: all var(--transition-fast);
        }
        .related-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }
        .related-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .related-card .related-content { padding: var(--space-4); }
        .related-card .related-title {
            font-size: var(--text-sm);
            font-weight: 600;
            margin-bottom: var(--space-2);
        }
        .related-card .related-title a {
            color: var(--color-text-heading);
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .related-grid { grid-template-columns: 1fr; }
            .content-layout { padding: var(--space-6) 0; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../components/public/nav_home.php'; ?>

<?php
  $hero_eyebrow = $blog['category_name'] ?? 'Article';
  $hero_title   = $blog['title'];
  $hero_sub     = 'By ' . ($blog['author_name'] ?? 'Ismano') . ' · '
                . date('M d, Y', strtotime($blog['published_at']))
                . ' · ' . ceil(str_word_count(strip_tags($blog['content'])) / 200) . ' min read';
  $hero_desc    = mb_strimwidth(strip_tags($blog['excerpt'] ?? ''), 0, 160, '…');
  $hero_image   = !empty($blog['featured_image']) ? $blog['featured_image']
                : 'https://images.unsplash.com/photo-1432888498266-38ffec3eaf0a?w=1600&q=80';
  include __DIR__ . '/../components/public/page_hero.php';
?>

<!-- Hero -->
<!--section class="post-hero">
    <div class="container post-hero-inner">
        <div class="breadcrumb-wrap">
            <a href="/Ismano/public/">Home</a>
            <i class="fas fa-chevron-right sep"></i>
            <a href="/Ismano/public/blogs/">Blog</a>
            <i class="fas fa-chevron-right sep"></i>
            <span><?php echo htmlspecialchars($blog['title']); ?></span>
        </div>
        
        <?php if ($blog['category_name']): ?>
            <div class="post-category"><?php echo htmlspecialchars($blog['category_name']); ?></div>
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($blog['title']); ?></h1>
        <div class="post-meta">
            <span><i class="far fa-user"></i> <?php echo htmlspecialchars($blog['author_name']); ?></span>
            <span><i class="far fa-calendar-alt"></i> <?php echo date('F d, Y', strtotime($blog['published_at'])); ?></span>
            <span><i class="far fa-clock"></i> <?php echo ceil(str_word_count(strip_tags($blog['content'])) / 200); ?> min read</span>
            <span><i class="far fa-eye"></i> <?php echo number_format($blog['view_count']); ?> views</span>
        </div>
    </div>
</section-->

<div class="container">
    <div class="content-layout">
        
        <?php if ($blog['featured_image']): ?>
            <img src="<?php echo htmlspecialchars($blog['featured_image']); ?>" class="featured-image" alt="<?php echo htmlspecialchars($blog['title']); ?>">
        <?php endif; ?>
        
        <!-- Dynamic Sections or Full Content -->
        <div class="blog-content">
            <?php if (!empty($sections)): ?>
                <?php foreach ($sections as $section): ?>
                    <?php if ($section['section_type'] === 'text_only'): ?>
                        <?php if ($section['title']): ?>
                            <h2><?php echo htmlspecialchars($section['title']); ?></h2>
                        <?php endif; ?>
                        <?php echo $section['content']; ?>
                    <?php elseif ($section['section_type'] === 'text_image_left'): ?>
                        <div class="row align-items-center my-5">
                            <div class="col-md-6 order-md-2">
                                <?php if ($section['title']): ?>
                                    <h2><?php echo htmlspecialchars($section['title']); ?></h2>
                                <?php endif; ?>
                                <?php echo $section['content']; ?>
                            </div>
                            <div class="col-md-6 order-md-1">
                                <img src="<?php echo $section['media_url']; ?>" class="img-fluid rounded">
                            </div>
                        </div>
                    <?php elseif ($section['section_type'] === 'text_image_right'): ?>
                        <div class="row align-items-center my-5">
                            <div class="col-md-6">
                                <?php if ($section['title']): ?>
                                    <h2><?php echo htmlspecialchars($section['title']); ?></h2>
                                <?php endif; ?>
                                <?php echo $section['content']; ?>
                            </div>
                            <div class="col-md-6">
                                <img src="<?php echo $section['media_url']; ?>" class="img-fluid rounded">
                            </div>
                        </div>
                    <?php elseif ($section['section_type'] === 'youtube' && $section['media_url']): ?>
                        <div class="ratio ratio-16x9 my-5">
                            <iframe src="https://www.youtube.com/embed/<?php echo getYoutubeId($section['media_url']); ?>" allowfullscreen></iframe>
                        </div>
                    <?php elseif ($section['section_type'] === 'video' && $section['media_url']): ?>
                        <div class="ratio ratio-16x9 my-5">
                            <video controls>
                                <source src="<?php echo $section['media_url']; ?>" type="video/mp4">
                            </video>
                        </div>
                    <?php elseif ($section['section_type'] === 'quote'): ?>
                        <blockquote class="my-5">
                            <?php echo $section['content']; ?>
                            <?php if ($section['title']): ?>
                                <footer class="mt-2">— <?php echo htmlspecialchars($section['title']); ?></footer>
                            <?php endif; ?>
                        </blockquote>
                    <?php elseif ($section['section_type'] === 'code_block'): ?>
                        <pre class="bg-dark text-light p-4 rounded my-5"><code><?php echo htmlspecialchars($section['content']); ?></code></pre>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <?php echo $blog['content']; ?>
            <?php endif; ?>
        </div>
        
        <!-- Tags -->
        <?php if (!empty($tags)): ?>
            <div class="tags-section">
                <strong><i class="fas fa-tags"></i> Tags:</strong>
                <?php foreach ($tags as $tag): ?>
                    <a href="tag.php?slug=<?php echo urlencode($tag['slug']); ?>" class="tag">
                        <?php echo htmlspecialchars($tag['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Share Buttons -->
        <div class="share-section">
            <strong>Share this article</strong>
            <div class="share-buttons">
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="share-btn share-facebook">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode($blog['title']); ?>" target="_blank" class="share-btn share-twitter">
                    <i class="fab fa-twitter"></i>
                </a>
                <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="share-btn share-linkedin">
                    <i class="fab fa-linkedin-in"></i>
                </a>
                <a href="https://wa.me/?text=<?php echo urlencode($blog['title'] . ' - http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="share-btn share-whatsapp">
                    <i class="fab fa-whatsapp"></i>
                </a>
            </div>
        </div>
        
        <!-- Related Posts -->
        <?php if (!empty($relatedPosts)): ?>
            <div class="mt-5">
                <h3>Related Posts</h3>
                <div class="related-grid">
                    <?php foreach ($relatedPosts as $related): ?>
                        <div class="related-card">
                            <?php if ($related['featured_image']): ?>
                                <img src="<?php echo htmlspecialchars($related['featured_image']); ?>" alt="<?php echo htmlspecialchars($related['title']); ?>">
                            <?php else: ?>
                                <div style="height:150px;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-newspaper fa-2x text-white"></i>
                                </div>
                            <?php endif; ?>
                            <div class="related-content">
                                <div class="related-title">
                                    <a href="readmore.php?slug=<?php echo urlencode($related['slug']); ?>">
                                        <?php echo htmlspecialchars($related['title']); ?>
                                    </a>
                                </div>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($related['published_at'])); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../components/public/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>