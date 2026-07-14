<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($page_title ?? 'Dazu hub Institute'); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($page_description ?? 'Practical tech training in Thika Town — software, cybersecurity, data analytics and AI.'); ?>">
  <link rel="icon" type="image/jpeg" href="https://i.ibb.co/fzfZFtGc/223025.jpg">
  <link rel="stylesheet" href="<?php echo htmlspecialchars(dazu_tailwind_css()); ?>">
  <link rel="stylesheet" href="<?php echo asset('css/dazu-frontend-theme.css'); ?>">
  <style>svg.dazu-icon{width:1.25rem;height:1.25rem;flex-shrink:0;max-width:100%}</style>
  <?php if (!empty($extra_head)) echo $extra_head; ?>
</head>
<body class="bg-white text-slate-900 font-sans antialiased selection:bg-green-100 selection:text-green-800">
<?php include __DIR__ . '/../../components/public/dazu_navbar.php'; ?>
<main class="flex-1">
  <?php echo $content ?? ''; ?>
</main>
<?php include __DIR__ . '/../../components/public/dazu_footer.php'; ?>
<script src="<?php echo asset('js/dazu-course-category-filter.js?v=20260701'); ?>"></script>
<?php if (!empty($extra_js)) echo $extra_js; ?>
</body>
</html>
