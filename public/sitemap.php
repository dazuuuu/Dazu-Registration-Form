<?php
// Served as /sitemap.xml: the public pages, every live course, every course category, every live course page (dazu.ac.ke/<slug>,
// not in the menu, so this is how search engines find them) and every published blog post. Linked from /robots.txt.
require_once __DIR__ . '/../app/v1/config/paths.php';
require_once __DIR__ . '/../app/v1/config/db_connect.php';
require_once __DIR__ . '/../app/v1/helpers/course_share.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
$abs = fn(string $path) => course_share_absolute_url(url($path));
$urls = [];
foreach (['/', 'about', 'courses', 'contact', 'blog', 'gallery', 'projects', 'services', 'store', 'testimonials', 'verify'] as $p) {
    if ($p === '/' || is_file(__DIR__ . '/' . ($p === 'blog' ? 'blogs' : $p) . '/index.php')) $urls[] = [$abs($p), null];
}
try {
    foreach ($pdo->query("SELECT id, abbreviation, updated_at FROM courses WHERE is_active = 1 AND (approval_status IS NULL OR approval_status = 'approved')") as $c) {
        $urls[] = [course_clean_url((int) $c['id']), $c['updated_at']];
    }
} catch (Throwable $e) {
    error_log('[sitemap] courses: ' . $e->getMessage());
}
foreach (['courses/free'] as $p) {
    if (is_file(__DIR__ . '/' . $p . '.php')) $urls[] = [$abs($p), null];
}
try {   // course landing pages (Admin → Courses → Course pages)
    foreach ($pdo->query("SELECT p.slug, p.updated_at FROM course_pages p JOIN courses c ON c.id = p.course_id WHERE p.is_published = 1 AND c.is_active = 1") as $cp) {
        $urls[] = [$abs($cp['slug']), $cp['updated_at']];
    }
} catch (Throwable $e) {
    // migration 101 not run yet
}
try {
    foreach ($pdo->query("SELECT slug, updated_at FROM course_categories WHERE is_active = 1 AND slug <> ''") as $cat) {
        $urls[] = [$abs('courses/category') . '?slug=' . rawurlencode($cat['slug']), $cat['updated_at']];
    }
} catch (Throwable $e) {
    error_log('[sitemap] categories: ' . $e->getMessage());
}
try {
    foreach ($pdo->query("SELECT slug, COALESCE(updated_at, published_at) AS changed FROM blogs WHERE status = 'published' AND slug <> ''") as $b) {
        $urls[] = [$abs('blog/' . rawurlencode($b['slug'])), $b['changed']];
    }
} catch (Throwable $e) {
    error_log('[sitemap] blogs: ' . $e->getMessage());
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $changed]) {
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>'
        . ($changed ? '<lastmod>' . date('Y-m-d', strtotime($changed)) . '</lastmod>' : '') . "</url>\n";
}
echo "</urlset>\n";
