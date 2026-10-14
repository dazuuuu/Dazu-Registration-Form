<?php
// Admin: Directors. The people who sign certificates (founder / directors), with their signature. The one marked
// "Signs certificates" is printed on new certificates, next to the signature of the tutor who trained the student.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/signatures.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';

Middleware::requireAdmin();
$db = \Models\Connection::getInstance()->getConnection();
$self = url('admin/directors/index.php');
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = null; $err = null;
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $err = 'Security check failed. Please refresh and try again.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim(preg_replace('/\s+/', ' ', (string) ($_POST['full_name'] ?? '')));
        $title = trim(preg_replace('/\s+/', ' ', (string) ($_POST['title'] ?? ''))) ?: 'Director';
        $hasFile = ($_FILES['signature']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        switch ($_POST['action'] ?? '') {
            case 'save':
                if ($name === '' || mb_strlen($name) > 150 || mb_strlen($title) > 80) { $err = 'Enter a name (up to 150 characters) and a title (up to 80).'; break; }
                $sig = null;
                if ($hasFile) {
                    $r = sig_store($_FILES['signature']);
                    if (!$r['ok']) { $err = $r['error']; break; }
                    $sig = $r['path'];
                }
                if ($id) {
                    $old = $db->prepare('SELECT signature_path FROM directors WHERE id = ?');
                    $old->execute([$id]);
                    $oldSig = $old->fetchColumn();
                    $db->prepare('UPDATE directors SET full_name = ?, title = ?, signature_path = COALESCE(?, signature_path), updated_at = NOW() WHERE id = ?')
                       ->execute([$name, $title, $sig, $id]);
                    // Old certificates keep pointing at the director, so the replaced file is no longer needed.
                    if ($sig && $oldSig) sig_delete($oldSig);
                    $msg = 'Saved.';
                } else {
                    $first = !(int) $db->query('SELECT COUNT(*) FROM directors WHERE is_active = 1 AND on_certificates = 1')->fetchColumn();
                    $db->prepare('INSERT INTO directors (full_name, title, signature_path, on_certificates, created_at) VALUES (?, ?, ?, ?, NOW())')
                       ->execute([$name, $title, $sig, $first ? 1 : 0]);
                    $msg = $name . ' added.' . ($first ? ' They now sign certificates.' : '');
                }
                break;
            case 'sign':
                $db->beginTransaction();
                $db->exec('UPDATE directors SET on_certificates = 0');
                $db->prepare('UPDATE directors SET on_certificates = 1, is_active = 1 WHERE id = ?')->execute([$id]);
                $db->commit();
                $msg = 'New certificates will now be signed by this director. Certificates already issued keep their signer.';
                break;
            case 'remove':
                $db->prepare('UPDATE directors SET is_active = 0, on_certificates = 0 WHERE id = ?')->execute([$id]);
                $msg = 'Removed. Certificates they already signed still show their signature.';
                break;
        }
    }
    $_SESSION[$err ? 'error_msg' : 'success_msg'] = $err ?: $msg;
    header('Location: ' . $self);
    exit;
}

try {
    $directors = $db->query('SELECT * FROM directors WHERE is_active = 1 ORDER BY on_certificates DESC, id')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $ex) {
    $directors = [];
    $_SESSION['error_msg'] = 'The directors table is not installed yet. Open System Updates and install the latest version.';
}
$editId = (int) ($_GET['edit'] ?? 0);
$page_title = 'Directors';
$breadcrumbs = [['label' => 'Directors', 'active' => true]];
ob_start();
?>
<?php foreach (['success_msg' => 'success', 'error_msg' => 'danger'] as $key => $cls): if (isset($_SESSION[$key])): ?>
    <div class="alert alert-<?php echo $cls; ?>"><?php echo $e($_SESSION[$key]); ?></div>
<?php unset($_SESSION[$key]); endif; endforeach; ?>

<p class="text-muted">Certificates carry two signatures: the tutor who trained the student (tutors upload their own) and the director chosen here.
Upload a photo or scan of the signature in dark ink on white paper; the background is removed automatically.</p>

<div class="row g-3">
<?php foreach ($directors as $d): $sig = sig_data_uri($d['signature_path']); $editing = $editId === (int) $d['id']; ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100 <?php echo $d['on_certificates'] ? 'border-success' : ''; ?>">
            <div class="card-body">
                <?php if ($editing): ?>
                    <form method="POST" enctype="multipart/form-data"><?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?php echo (int) $d['id']; ?>">
                        <label class="form-label small fw-semibold">Full name</label><input name="full_name" class="form-control form-control-sm mb-2" maxlength="150" required value="<?php echo $e($d['full_name']); ?>">
                        <label class="form-label small fw-semibold">Title on the certificate</label><input name="title" class="form-control form-control-sm mb-2" maxlength="80" value="<?php echo $e($d['title']); ?>">
                        <label class="form-label small fw-semibold">New signature <span class="text-muted fw-normal">(leave empty to keep)</span></label>
                        <input type="file" name="signature" class="form-control form-control-sm mb-3" accept="image/png,image/jpeg">
                        <button class="btn btn-sm btn-success">Save</button> <a href="<?php echo $e($self); ?>" class="btn btn-sm btn-link">Cancel</a>
                    </form>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div><div class="fw-bold"><?php echo $e($d['full_name']); ?></div><div class="small text-muted"><?php echo $e($d['title']); ?></div></div>
                        <?php if ($d['on_certificates']): ?><span class="badge bg-success">Signs certificates</span><?php endif; ?>
                    </div>
                    <div class="border rounded bg-white d-flex align-items-center justify-content-center my-3" style="height:90px">
                        <?php if ($sig): ?><img src="<?php echo $sig; ?>" alt="Signature of <?php echo $e($d['full_name']); ?>" style="max-width:90%;max-height:78px">
                        <?php else: ?><span class="small text-warning"><i class="fas fa-triangle-exclamation me-1"></i>No signature yet: the line is left blank on certificates</span><?php endif; ?>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="?edit=<?php echo (int) $d['id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen me-1"></i><?php echo $sig ? 'Edit' : 'Add signature'; ?></a>
                        <?php if (!$d['on_certificates']): ?>
                            <form method="POST" class="m-0"><?php echo csrf_field(); ?><input type="hidden" name="action" value="sign"><input type="hidden" name="id" value="<?php echo (int) $d['id']; ?>">
                                <button class="btn btn-sm btn-outline-success">Use on certificates</button></form>
                            <form method="POST" class="m-0" onsubmit="return confirm('Remove <?php echo $e(addslashes($d['full_name'])); ?>?')"><?php echo csrf_field(); ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?php echo (int) $d['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger">Remove</button></form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100 border-dashed">
            <div class="card-body">
                <h6 class="fw-bold">Add a director</h6>
                <form method="POST" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="action" value="save">
                    <label class="form-label small fw-semibold">Full name</label><input name="full_name" class="form-control form-control-sm mb-2" maxlength="150" required>
                    <label class="form-label small fw-semibold">Title on the certificate</label><input name="title" class="form-control form-control-sm mb-2" maxlength="80" placeholder="Director">
                    <label class="form-label small fw-semibold">Signature</label><input type="file" name="signature" class="form-control form-control-sm mb-3" accept="image/png,image/jpeg">
                    <button class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i>Add</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
