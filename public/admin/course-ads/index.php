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
try { $db->query('SELECT ends_at FROM course_ads LIMIT 0'); } catch (Throwable $x) { $ready = false; }
if (!$ready) {
    $content = '<div class="alert alert-warning">Run <a href="' . $e(url('admin/system/updates.php')) . '">System Updates</a> first (update 140/141: course ads).</div>';
    require __DIR__ . '/../../templates/admin/layout.php';
    exit;
}

$courses = $db->query("SELECT id, title FROM courses WHERE is_active = 1 ORDER BY title")->fetchAll(PDO::FETCH_KEY_PAIR);
$self = url('admin/course-ads/index.php');
$me = (int) ($_SESSION['user_id'] ?? 0);
$runsForDays = ['' => 'Keep as is / no end date', '1' => '1 day', '3' => '3 days', '7' => '1 week', '14' => '2 weeks', '30' => '1 month', '60' => '2 months', '90' => '3 months'];
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
        $imagePath = null;
        if (!empty($_FILES['image']['name'])) {
            $upload = upload_image($_FILES['image'], __DIR__ . '/../../uploads/course_ads', url('uploads/course_ads'));
            if (!$upload['ok']) $back(null, $upload['error']);
            $imagePath = $upload['path'];
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
        $btn = trim((string) ($_POST['button_text'] ?? '')) ?: 'Apply now';
        $days = (int) ($_POST['runs_for'] ?? 0);
        $endsAt = $days > 0 ? gmdate('Y-m-d H:i:s', time() + $days * 86400) : null;
        $db->prepare('INSERT INTO course_ads (course_id, image_path, title, subtitle, button_text, ends_at, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?, 1, ?)')
           ->execute([$courseId, $imagePath, $title ?: null, $subtitle ?: null, mb_substr($btn, 0, 40), $endsAt, $me]);
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
        $imagePath = $ad['image_path'] ?: null;
        if (!empty($_FILES['image']['name'])) {
            $upload = upload_image($_FILES['image'], __DIR__ . '/../../uploads/course_ads', url('uploads/course_ads'));
            if (!$upload['ok']) $back(null, $upload['error']);
            $imagePath = $upload['path'];
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        $subtitle = trim((string) ($_POST['subtitle'] ?? ''));
        $btn = trim((string) ($_POST['button_text'] ?? '')) ?: 'Apply now';
        $days = (int) ($_POST['runs_for'] ?? 0);
        $endsAt = $days > 0 ? gmdate('Y-m-d H:i:s', time() + $days * 86400) : $ad['ends_at'];
        $db->prepare('UPDATE course_ads SET course_id = ?, image_path = ?, title = ?, subtitle = ?, button_text = ?, ends_at = ?, updated_at = ? WHERE id = ?')
           ->execute([$courseId, $imagePath, $title ?: null, $subtitle ?: null, mb_substr($btn, 0, 40), $endsAt, gmdate('Y-m-d H:i:s'), $id]);
        $back('Saved.');
    }
    if ($action === 'clear_timer') {
        $db->prepare('UPDATE course_ads SET ends_at = NULL, updated_at = ? WHERE id = ?')->execute([gmdate('Y-m-d H:i:s'), $id]);
        $back('Timer removed — this ad now runs with no end date.');
    }
    if ($action === 'remove') {
        $db->prepare('DELETE FROM course_ads WHERE id = ?')->execute([$id]);
        $back('Ad removed.');
    }
    $back(null, 'Nothing to do.');
}

$ads = $db->query("SELECT ca.*, c.title AS course_title, c.cover_image FROM course_ads ca JOIN courses c ON c.id = ca.course_id ORDER BY ca.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$page_title = 'Course ads';
ob_start();
?>
<div class="card mb-3"><div class="card-body">
    <h5 class="mb-1">Course ads</h5>
    <p class="text-muted small mb-0">A small bottom-right popup shown on the public site, advertising one course. It auto-uses the course's own title and cover image
        unless you override them below. Its Apply button takes the visitor straight to that course's page with the Book Now form ready. Only the newest
        <strong>active</strong> ad below is shown to visitors; set an older one inactive (or remove it) once a new promotion starts.</p>
</div></div>

<?php foreach ($ads as $ad): $remaining = $ad['ends_at'] ? max(0, strtotime($ad['ends_at']) - time()) : null; ?>
<div class="card mb-3"><div class="card-body">
    <form method="POST" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?php echo (int) $ad['id']; ?>">
        <div class="row g-3 align-items-start">
            <div class="col-md-3">
                <img src="<?php echo $e(settings_asset_url($ad['image_path'] ?: $ad['cover_image'])); ?>" class="img-fluid rounded border mb-2" style="max-height:140px;object-fit:cover;width:100%">
                <input type="file" name="image" accept="image/*" class="form-control form-control-sm">
                <div class="form-text"><?php echo $ad['image_path'] ? 'Leave empty to keep this image.' : 'No ad image set — using the course cover image. Upload one to override it.'; ?></div>
            </div>
            <div class="col-md-9">
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Course</label>
                        <select name="course_id" class="form-select" required>
                            <?php foreach ($courses as $cid => $ct): ?><option value="<?php echo (int) $cid; ?>" <?php echo (int) $cid === (int) $ad['course_id'] ? 'selected' : ''; ?>><?php echo $e($ct); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Apply button text</label><input name="button_text" class="form-control" maxlength="40" value="<?php echo $e($ad['button_text']); ?>"></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Title override (optional — the course title is used by default)</label><input name="title" class="form-control" maxlength="150" placeholder="<?php echo $e($ad['course_title']); ?>" value="<?php echo $e((string) $ad['title']); ?>"></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Subtitle (optional)</label><input name="subtitle" class="form-control" maxlength="255" value="<?php echo $e((string) $ad['subtitle']); ?>"></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Extend / restart the countdown</label>
                        <select name="runs_for" class="form-select">
                            <?php foreach ($runsForDays as $d => $label): ?><option value="<?php echo $e($d); ?>"><?php echo $e($label); ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="col-md-6 d-flex align-items-end">
                        <span class="small text-muted"><?php echo $remaining === null ? 'No end date set.' : ($remaining > 0 ? 'Ends ' . $e(gmdate('d M Y, H:i', strtotime($ad['ends_at']))) . ' UTC.' : 'This ad\'s timer has run out — visitors no longer see it.'); ?></span>
                    </div>
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
        <?php if ($ad['ends_at']): ?>
        <form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="clear_timer"><input type="hidden" name="id" value="<?php echo (int) $ad['id']; ?>">
            <button class="btn btn-outline-secondary btn-sm">Remove timer</button></form>
        <?php endif; ?>
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
        <div class="col-md-4"><label class="form-label small fw-semibold mb-0">Image (optional)</label><input type="file" name="image" accept="image/*" class="form-control">
            <div class="form-text">Leave empty to use the course's own cover image.</div></div>
        <div class="col-md-4"><label class="form-label small fw-semibold mb-0">Apply button text</label><input name="button_text" class="form-control" placeholder="Apply now"></div>
        <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Title override (optional — the course title is used by default)</label><input name="title" class="form-control" maxlength="150" placeholder="e.g. Black Friday — Diploma in IT"></div>
        <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Subtitle (optional)</label><input name="subtitle" class="form-control" maxlength="255" placeholder="e.g. Extra 20% off this week"></div>
        <div class="col-md-6"><label class="form-label small fw-semibold mb-0">Runs for</label>
            <select name="runs_for" class="form-select">
                <option value="">No end date</option>
                <?php foreach ($runsForDays as $d => $label): if ($d === '') continue; ?><option value="<?php echo $e($d); ?>"><?php echo $e($label); ?></option><?php endforeach; ?>
            </select>
            <div class="form-text">Shows a live countdown on the ad; it stops showing to visitors once it runs out.</div></div>
        <div class="col-12"><button class="btn btn-brand mt-2">Create ad</button></div>
    </div>
    <div class="small text-muted mt-2">Creating a new ad does not remove old ones — set them inactive if you no longer want them considered.</div>
    <?php endif; ?>
</div></form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
