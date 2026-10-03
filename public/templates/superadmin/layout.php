<?php
// public/templates/superadmin/layout.php
// Shared frame for every superadmin page. Pages set $page_title, $breadcrumbs, $content, and optionally
// $extra_css, $extra_js, $sa_subtitle, $sa_actions (HTML) and $sa_custom_header (true when the page draws its own heading).
if (session_status() === PHP_SESSION_NONE) session_start();
if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
Middleware::requireSuperPortal();

$flashes = ['success' => 'check-circle', 'error' => 'exclamation-circle', 'warning' => 'exclamation-triangle'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Superadmin — <?php echo htmlspecialchars(strip_tags((string)($page_title ?? 'Dashboard'))); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>.sa-bottom,.sa-sheet,.sa-sheet-bg,.sa-help,.sa-help-bg{display:none}.sa-bottom{position:fixed}</style>
    <link rel="stylesheet" href="<?php echo sa_asset('css/superadmin.css'); ?>">
    <?php include __DIR__ . '/../../components/superadmin/pwa_head.php'; ?>
    <script>
    (function () {
        var base = <?php echo json_encode(WEB_ROOT === '' ? '' : rtrim(WEB_ROOT, '/')); ?>;
        window.url = function (path) { path = String(path || '').replace(/^\//, ''); return (base ? base + '/' : '/') + path; };
    })();
    </script>
    <?php if (isset($extra_css)) echo $extra_css; ?>
</head>
<body class="sa-body">
<div class="sa-frame">
    <?php include __DIR__ . '/../../components/superadmin/topbar.php'; ?>

    <main class="sa-content">
        <?php if (empty($sa_custom_header)): ?>
        <div class="sa-head">
            <a class="sa-back" href="javascript:history.back()" aria-label="Back"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h1><?php echo $page_title ?? 'Dashboard'; ?></h1>
                <?php if (!empty($sa_subtitle)): ?><p><?php echo $sa_subtitle; ?></p>
                <?php elseif (!empty($breadcrumbs)): ?><p class="sa-crumbs"><?php
                    echo implode(' / ', array_map(fn($b) => !empty($b['url']) ? '<a href="' . htmlspecialchars($b['url']) . '">' . htmlspecialchars($b['label']) . '</a>' : htmlspecialchars($b['label']), $breadcrumbs)); ?></p><?php endif; ?>
            </div>
            <?php if (!empty($sa_actions)): ?><div class="sa-head-actions"><?php echo $sa_actions; ?></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <?php foreach ($flashes as $type => $icon):
            $msg = $_SESSION['flash'][$type] ?? null;
            if ($msg !== null): unset($_SESSION['flash'][$type]); ?>
            <div class="alert alert-<?php echo $type === 'error' ? 'danger' : $type; ?> alert-dismissible fade show sa-alert">
                <i class="fas fa-<?php echo $icon; ?> me-2"></i><?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; endforeach; ?>
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show sa-alert"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php echo $content; ?>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo sa_asset('superadmin/app.js'); ?>"></script>
<?php if (isset($extra_js)) echo $extra_js; ?>
<?php require __DIR__ . '/../../components/cache_guard.php'; ?>
</body>
</html>
