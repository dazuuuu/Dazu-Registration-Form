<?php
// public/templates/tutor/layout.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
Middleware::requireTutor();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'Tutor Portal'); ?> — Dazu Training Institute</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --brand:#0D9488; --brand-dark:#0A766B; }
        body { background:#f5f6f8; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; }
        .tutor-main { max-width:1200px; margin:0 auto; padding:24px 20px 40px; }
        .card-tutor { background:#fff; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:20px; overflow:hidden; }
        .card-tutor .head { padding:14px 20px; background:#fafafa; border-bottom:1px solid #e2e8f0; font-weight:600; }
        .btn-brand { background:var(--brand); border-color:var(--brand); color:#fff; }
        .btn-brand:hover { background:var(--brand-dark); border-color:var(--brand-dark); color:#fff; }
    </style>
</head>
<body>
<?php require __DIR__ . '/../../components/tutor/navbar.php'; ?>
<main class="tutor-main">
<?php
require_once __DIR__ . '/../../../app/v1/models/Session.php';
if ($f = SessionModel::getFlash('success')): ?><div class="alert alert-success alert-dismissible fade show"><?php echo $f; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;
if ($f = SessionModel::getFlash('error')): ?><div class="alert alert-danger alert-dismissible fade show"><?php echo $f; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif;
echo $content;
?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>
