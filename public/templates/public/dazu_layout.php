<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../../app/v1/helpers/bonfire_theme.php';

/**
 * Cache-busted asset URL.
 */
if (!function_exists('dazu_asset_v')) {
    function dazu_asset_v(string $path): string
    {
        $file = public_path('assets/' . ltrim($path, '/'));
        $ver  = is_file($file) ? filemtime($file) : null;
        return $ver ? asset($path) . '?v=' . $ver : asset($path);
    }
}

$isHome = dazu_nav_active('home');
$favicon = bonfire_asset_v('favicon.webp');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($page_title ?? 'Dazu hub Institute'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($page_description ?? 'Practical tech training in Thika Town — software, cybersecurity, data analytics and AI.'); ?>">
  <link rel="icon" type="image/webp" href="<?php echo htmlspecialchars($favicon, ENT_QUOTES); ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="<?php echo htmlspecialchars(dazu_tailwind_css()); ?>">
  <link rel="stylesheet" href="<?php echo dazu_asset_v('css/dazu-frontend-theme.css'); ?>">
  <link rel="stylesheet" href="<?php echo dazu_asset_v('css/bonfire-theme.css'); ?>">
  <style>svg.dazu-icon{width:1.25rem;height:1.25rem;flex-shrink:0;max-width:100%}</style>
  <?php if (!empty($extra_head)) echo $extra_head; ?>
</head>
<body class="bonfire-ui bg-white text-slate-900 font-sans antialiased selection:bg-green-100 selection:text-green-800">
<?php if (!$isHome): ?>
  <?php include __DIR__ . '/../../components/public/dazu_navbar.php'; ?>
<?php endif; ?>
<main class="flex-1">
  <?php echo $content ?? ''; ?>
</main>
<?php include __DIR__ . '/../../components/public/dazu_footer.php'; ?>
<script src="<?php echo dazu_asset_v('js/dazu-course-category-filter.js'); ?>"></script>
<?php if (!empty($extra_js)) echo $extra_js; ?>
</body>
</html>
