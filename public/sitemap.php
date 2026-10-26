<?php
// Served as /sitemap.xml: the public pages, every live course and every published blog post.
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
        $urls[] = [$abs('courses/view?id=' . (int) $c['id']), $c['updated_at']];
    }
} catch (Throwable $e) {
    error_log('[sitemap] courses: ' . $e->getMessage());
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
