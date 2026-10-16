<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../../app/v1/helpers/brand.php';

/**
 * Cache-busted asset URL.
 *
 * asset() emits a bare, unversioned URL. sw.js caches every .css/.js and served
 * them cache-first with no expiry, so a deployed change never reached anyone who
 * had already loaded the page. Appending the file's mtime changes the URL on every
 * deploy, which misses both the HTTP cache and the service worker cache key
 * (caches.match() includes the query string), so the new file is always fetched.
 *
 * This works even for visitors still running the old service worker.
 */
if (!function_exists('dazu_asset_v')) {
    function dazu_asset_v(string $path): string
    {
        $file = public_path('assets/' . ltrim($path, '/'));
        $ver  = is_file($file) ? filemtime($file) : null;
        return $ver ? asset($path) . '?v=' . $ver : asset($path);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($page_title ?? 'Dazu Training Institute'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($page_description ?? 'Practical tech training in Nairobi, Thika and online — software, cybersecurity, data analytics and AI.'); ?>">
  <?php
    require_once __DIR__ . '/../../../app/v1/helpers/seo.php';
    require_once __DIR__ . '/../../../app/v1/helpers/site_logo.php';
    if (empty($og_image) && !empty($course) && is_array($course)) {
        $og_image = course_og_image_url($course);
        if (empty($page_description)) {
            $page_description = mb_strimwidth(strip_tags((string) ($course['overview'] ?? $course['description'] ?? '')), 0, 160, '…');
        }
    }
    if (empty($og_image)) {
        $og_image = seo_public_preview_image($page_key ?? null);
    }
    echo seo_head_tags(
        html_entity_decode((string) ($page_title ?? 'Dazu Training Institute'), ENT_QUOTES, 'UTF-8'),
        (string) ($page_description ?? ''),
        $og_image ?? null,
        $og_url ?? null
    );
  ?>
  <?php echo dazu_favicon_tags(); ?>
  <link rel="stylesheet" href="<?php echo htmlspecialchars(dazu_tailwind_css()); ?>">
  <link rel="stylesheet" href="<?php echo dazu_asset_v('css/dazu-frontend-theme.css'); ?>">
  <style>svg.dazu-icon{width:1.25rem;height:1.25rem;flex-shrink:0;max-width:100%}</style>
  <?php if (!empty($extra_head)) echo $extra_head; ?>
</head>
<body class="bg-white text-slate-900 font-sans antialiased selection:bg-green-100 selection:text-green-800">
<?php include __DIR__ . '/../../components/public/dazu_navbar.php'; ?>
<main class="flex-1">
  <?php echo $content ?? ''; ?>
</main>
<?php include __DIR__ . '/../../components/public/dazu_footer.php'; ?>
<script src="<?php echo dazu_asset_v('js/dazu-course-category-filter.js'); ?>"></script>
<?php if (!empty($extra_js)) echo $extra_js; ?>
</body>
</html>
