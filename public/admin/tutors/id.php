<?php
// Admin: a tutor's staff ID card, photo, signature and sample certificate. The same previews the tutor sees, plus the
// check results, editing the printed name / role / ID number, cropping and approving the photo, requesting a new photo
// or signature, the card's print status, the print-ready PDF and the history.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/services/TutorIdService.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$id = (int) ($_GET['id'] ?? 0);
$st = $db->prepare('SELECT user_id FROM tutors WHERE id = ?');
$st->execute([$id]);
$t = ($uid = (int) $st->fetchColumn()) ? staff_tutor($db, $uid) : null;
if (!$t) { header('Location: ' . url('admin/tutors/index.php')); exit; }
$self = url('admin/tutors/id.php?id=' . $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['tid_flash'] = !csrf_verify($_POST['csrf_token'] ?? null) ? ['error', 'Security check failed. Please refresh and try again.']
        : (tid_installed($db) ? tid_handle_post($db, $t, true) : ['error', 'Run System Updates (migration 080) first.']);
    header('Location: ' . $self);
    exit;
}

$page_title = 'ID card · ' . tid_name($t);
$breadcrumbs = [['label' => 'Tutors', 'url' => url('admin/tutors/index.php')], ['label' => tid_name($t), 'url' => url('admin/tutors/view.php?id=' . $id)], ['label' => 'ID card', 'active' => true]];
ob_start();
echo '<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"><div><h1 class="h4 mb-0">' . htmlspecialchars(tid_name($t)) . '</h1><div class="small text-muted">'
    . htmlspecialchars($t['email']) . ' · ' . htmlspecialchars($t['staff_no'] ?: 'No ID number yet') . '</div></div><a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars(url('admin/tutors/view.php?id=' . $id)) . '">Back to tutor</a></div>';
if (!tid_installed($db)) {
    echo '<div class="alert alert-warning">Run System Updates (migration 080) to use this page.</div>';
} else {
    $tidAdmin = true;
    $tidSelf = $self;
    require __DIR__ . '/../../components/tutor_id/panel.php';
}
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
