<?php
// public/templates/students/layout.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}

require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';

Middleware::requireStudent();

$portal_locked = $portal_locked ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title ?? 'Student Portal'); ?> — Dazu Training Institute</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --brand: #0D9488;
            --brand-dark: #0A766B;
            --brand-light: #f0fdfa;
            --text: #1a1a1a;
            --muted: #64748b;
            --border: #e2e8f0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f6f8;
            color: var(--text);
            min-height: 100vh;
        }
        .student-navbar {
            background: #fff;
            border-bottom: 1px solid var(--border);
            padding: 12px 20px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .student-brand {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--brand);
            text-decoration: none;
        }
        .student-brand:hover { color: var(--brand-dark); }
        .nav-link-sm {
            color: var(--muted);
            text-decoration: none;
            font-size: 14px;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .nav-link-sm:hover, .nav-link-sm.active {
            color: var(--brand);
            background: var(--brand-light);
        }
        .student-user-btn {
            background: transparent;
            border: none;
            display: flex;
            align-items: center;
            color: var(--text);
            padding: 0;
        }
        .student-avatar {
            width: 34px;
            height: 34px;
            background: linear-gradient(135deg, var(--brand), #14b8a6);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 14px;
        }
        .student-main {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 20px 40px;
        }
        .stat-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            height: 100%;
        }
        .stat-card .label { font-size: 13px; color: var(--muted); margin-bottom: 4px; }
        .stat-card .value { font-size: 1.5rem; font-weight: 700; color: var(--brand); }
        .card-student {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .card-student .card-head {
            padding: 16px 20px;
            background: #fafafa;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
        }
        .card-student .card-body { padding: 20px; }
        .locked-banner {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: #fff;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            text-align: center;
        }
        .locked-banner i { font-size: 2.5rem; margin-bottom: 12px; }
        .btn-brand {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
        }
        .btn-brand:hover {
            background: var(--brand-dark);
            border-color: var(--brand-dark);
            color: #fff;
        }
        .badge-plan {
            background: var(--brand-light);
            color: var(--brand);
            font-weight: 600;
        }
        @media (max-width: 991.98px) {
            .student-main {
                padding-bottom: calc(72px + env(safe-area-inset-bottom, 0px));
            }
        }
        /* Mobile bottom navigation */
        .student-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 200;
            display: flex;
            justify-content: space-around;
            align-items: stretch;
            background: #fff;
            border-top: 1px solid var(--border);
            box-shadow: 0 -4px 20px rgba(0,0,0,0.06);
            padding-bottom: env(safe-area-inset-bottom, 0px);
            min-height: 62px;
        }
        .student-bottom-nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            padding: 8px 4px;
            text-decoration: none;
            color: var(--muted);
            font-size: 0.68rem;
            font-weight: 600;
            transition: color 0.2s, background 0.2s;
        }
        .student-bottom-nav-item i { font-size: 1.15rem; }
        .student-bottom-nav-item.is-active {
            color: var(--brand);
            background: var(--brand-light);
        }
        .student-bottom-nav-item:active { opacity: 0.85; }
        @media (max-width: 576px) {
            .student-main { padding: 16px 12px 32px; }
        }
    </style>
    <?php if (isset($extra_css)): echo $extra_css; endif; ?>
</head>
<body>

<?php require __DIR__ . '/../../components/students/navbar.php'; ?>

<main class="student-main">
    <?php
    require_once __DIR__ . '/../../../app/v1/models/Session.php';
    if ($flash = SessionModel::getFlash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo $flash; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($flash = SessionModel::getFlash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $flash; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php echo $content; ?>
</main>

<?php require __DIR__ . '/../../components/students/bottom_nav.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (isset($extra_js)): echo $extra_js; endif; ?>
</body>
</html>
