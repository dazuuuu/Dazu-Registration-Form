<?php
// Admin: promotional ads for a course (a Black-Friday-style image popup shown on the public site).
// Each ad advertises exactly one course; its button links straight to that course's page with the
// inline Book Now form ready. Only the newest active ad is shown to visitors (course_ad_popup.php).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$page_title = 'Course ads';
$breadcrumbs = [['label' => 'Courses', 'url' => url('admin/courses/index.php')], ['label' => 'Course ads', 'active' => true]];

$ready = true;
try { $db->query('SELECT is_active FROM course_ads LIMIT 0'); } catch (Throwable $x) { $ready = false; }
if (!$ready) {
    $content = '<div class="alert alert-warning">Run <a href="' . $e(url('admin/system/updates.php')) . '">System Updates</a> first (update 140: course ads).</div>';
    require __DIR__ . '/../../templates/admin/layout.php';
    exit;
}

$courses = $db->query("SELECT id, title FROM courses WHERE is_active = 1 ORDER BY title")->fetchAll(PDO::FETCH_KEY_PAIR);
$self = url('admin/course-ads/index.php');
$me = (int) ($_SESSION['user_id'] ?? 0);
$back = function (?string $ok, ?string $err = null) use ($self, $e): void {
    if ($ok) SessionModel::setFlash('success', $e($ok));
    if ($err) SessionModel::setFlash('error', $e($err));
    header('Location: ' . $self);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) $back(null, 'Security check failed. Please try again.');
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'create') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        if (!isset($courses[$courseId])) $back(null, 'Choose the course this ad is for.');
        if (empty($_FILES['image']['name'])) $back(null, 'Choose an image for the ad.');
        $upload = upload_image($_FILES['image'], __DIR__ . '/../../uploads/course_ads', url('uploads/course_ads'));
        if (!$upload['ok']) $back(null, $upload['error']);
        $title = trim((string) ($_POST['title'] ?? ''));
        $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
        $btn = trim((string) ($_POST['button_text'] ?? '')) ?: 'Apply now';
        $db->prepare('INSERT INTO course_ads (course_id, image_path, title, subtitle, button_text, is_active, created_by) VALUES (?, ?, ?, ?, ?, 1, ?)')
           ->execute([$courseId, $upload['path'], $title ?: null, $subtitle ?: null, mb_substr($btn, 0, 40), $me]);
        $back('Ad created. It is now the one shown to visitors (the newest active ad is shown).');
    }

    $st = $db->prepare('SELECT * FROM course_ads WHERE id = ?');
    $st->execute([$id]);
    $ad = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ad) $back(null, 'Ad not found.');

    if ($action === 'toggle') {
        $db->prepare('UPDATE course_ads SET is_active = ?, updated_at = ? WHERE id = ?')->execute([(int) $ad['is_active'] ? 0 : 1, gmdate('Y-m-d H:i:s'), $id]);
        $back('Saved.');
    }
    if ($action === 'update') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        if (!isset($courses[$courseId])) $back(null, 'Choose the course this ad is for.');
        $imagePath = $ad['image_path'];
        if (!empty($_FILES['image']['name'])) {
            $upload = upload_image($_FILES['image'], __DIR__ . '/../../uploads/course_ads', url('uploads/course_ads'));
            if (!$upload['ok']) $back(null, $upload['error']);
            $imagePath = $upload['path'];
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
        $btn = trim((string) ($_POST['button_text'] ?? '')) ?: 'Apply now';
        $db->prepare('UPDATE course_ads SET course_id = ?, image_path = ?, title = ?, subtitle = ?, button_text = ?, updated_at = ? WHERE id = ?')
           ->execute([$courseId, $imagePath, $title ?: null, $subtitle ?: null, mb_substr($btn, 0, 40), gmdate('Y-m-d H:i:s'), $id]);
        $back('Saved.');
    }
    if ($action === 'remove') {
        $db->prepare('DELETE FROM course_ads WHERE id = ?')->execute([$id]);
        $back('Ad removed.');
    }
    $back(null, 'Nothing to do.');
}

$ads = $db->query("SELECT ca.*, c.title AS course_title FROM course_ads ca JOIN courses c ON c.id = ca.course_id ORDER BY ca.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$page_title = 'Course ads';
ob_start();
?>
<div class="card mb-3"><div class="card-body">
    <h5 class="mb-1">Course ads</h5>
    <p class="text-muted small mb-0">A full-image popup shown on the public site, like a Black Friday banner, advertising one course. Its button takes the visitor
        straight to that course's page with the Book Now form ready. Only the newest <strong>active</strong> ad below is shown to visitors; set an older one inactive
        (or remove it) once a new promotion starts.</p>
</div></div>

<?php foreach ($ads as $ad): ?>
<div class="card mb-3"><div class="card-body">
    <form method="POST" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?php echo (int) $ad['id']; ?>">
        <div class="row g-3 align-items-start">
            <div class="col-md-3">
                <img src="<?php echo $e(settings_asset_url($ad['image_path'])); ?>" class="img-fluid rounded border mb-2" style="max-height:140px;object-fit:cover;width:100%">
                <input type="file" name="image" accept="image/*" class="form-control form-control-sm">
                <div class="form-text">Leave empty to keep the current image.</div>
            </div>
            <div class="col-md-9">
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Course</label>
                        <select name="course_id" class="form-select" required>
                            <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo (int) $cid; ?>" <?php echo (int) $cid === (int) $ad['course_id'] ? 'selected' : ''; ?>><?php echo $e($ct); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Button text</label><input name="button_text" class="form-control" maxlength="40" value="<?php echo $e($ad['button_text']); ?>"></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Title (optional, shown on the image)</label><input name="title" class="form-control" maxlength="150" value="<?php echo $e((string) $ad['title']); ?>"></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Subtitle (optional)</label><input name="subtitle" class="form-control" maxlength="255" value="<?php echo $e((string) $ad['subtitle']); ?>"></div>
                </div>
                <div class="d-flex gap-2 align-items-center mt-3 flex-wrap">
                    <button class="btn btn-brand btn-sm">Save</button>
                    <span class="badge <?php echo (int) $ad['is_active'] ? 'bg-success' : 'bg-secondary'; ?>"><?php echo (int) $ad['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?></span>
                    <span class="text-muted small">For <?php echo $e($ad['course_title']); ?> · added <?php echo $e(gmdate('d M Y', strtotime($ad['created_at']))); ?></span>
                </div>
            </div>
        </div>
    </form>
    <div class="d-flex gap-2 mt-2 border-top pt-2">
        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int) $ad['id']; ?>">
            <button class="btn btn-outline-secondary btn-sm"><?php echo (int) $ad['is_active'] ? 'Set inactive' : 'Make active'; ?></button></form>
        <form method="POST" onsubmit="return confirm('Remove this ad?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?php echo (int) $ad['id']; ?>">
            <button class="btn btn-outline-danger btn-sm">Remove</button></form>
    </div>
</div></div>
<?php endforeach; ?>

<form method="POST" enctype="multipart/form-data" class="card"><div class="card-body"><?php echo csrf_field(); ?><input type="hidden" name="action" value="create">
    <h6 class="fw-bold">New ad</h6>
    <?php if (!$courses): ?><p class="text-muted small mb-0">No active courses to advertise yet.</p><?php else: ?>
    <div class="row g-2">
        <div class="col-md-4"><label class="form-label small fw-semibold mb-0">Course</label>
            <select name="course_id" class="form-select" required><option value="">Choose a course…</option>
                <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo (int) $cid; ?>"><?php echo $e($ct); ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-4"><label class="form-label small fw-semibold mb-0">Image</label><input type="file" name="image" accept="image/*" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label small fw-semibold mb-0">Button text</label><input name="button_text" class="form-control" placeholder="Apply now"></div>
        <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Title (optional)</label><input name="title" class="form-control" maxlength="150" placeholder="e.g. Black Friday — Diploma in IT"></div>
        <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Subtitle (optional)</label><input name="subtitle" class="form-control" maxlength="255" placeholder="e.g. Extra 20% off this week"></div>
        <div class="col-12"><button class="btn btn-brand mt-2">Create ad</button></div>
    </div>
    <div class="small text-muted mt-2">Creating a new ad does not remove old ones — set them inactive if you no longer want them considered.</div>
    <?php endif; ?>
</div></form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
