<?php
// Documents: agreement, ID, CV, certificates, reports, forms, completion letter. Upload, preview and download.
// Files live in private storage and are served by intern/file.php after a permission check.
require __DIR__ . '/../_boot.php';

$self = url('intern/documents/index.php');
if (intern_post_ok()) {
    if (($_POST['act'] ?? '') === 'delete') {
        // Interns can remove only what they uploaded themselves.
        $st = $db->prepare('SELECT 1 FROM intern_documents WHERE id = ? AND intern_id = ? AND uploaded_by = ?');
        $st->execute([(int) $_POST['doc_id'], (int) $intern['id'], (int) $intern['user_id']]);
        if ($st->fetchColumn()) in_document_delete($db, (int) $_POST['doc_id'], (int) $intern['id']);
        SessionModel::setFlash('success', 'Document removed.');
    } else {
        $cat = in_array($_POST['category'] ?? '', ['id', 'cv', 'certificate', 'report', 'form', 'other'], true) ? $_POST['category'] : 'other';
        $r = in_document_add($db, (int) $intern['id'], $cat, (string) ($_POST['title'] ?? ''), $_FILES['file'] ?? []);
        SessionModel::setFlash($r['ok'] ? 'success' : 'error', in_e($r['ok'] ? 'Document uploaded.' : $r['error']));
    }
    header('Location: ' . $self);
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: ' . $self);
    exit;
}
$docs = in_documents($db, (int) $intern['id']);
$icon = fn($mime) => str_contains((string) $mime, 'pdf') ? 'fa-file-pdf' : (str_starts_with((string) $mime, 'image/') ? 'fa-file-image' : (str_contains((string) $mime, 'word') ? 'fa-file-word' : 'fa-file'));
$page_title = 'Documents';
ob_start();
?>
<div class="row g-3">
    <div class="col-lg-4">
        <form method="POST" enctype="multipart/form-data" class="ip-card"><?php echo csrf_field(); ?>
            <h3 class="mb-3">Upload a document</h3>
            <label class="form-label small fw-semibold">Type</label>
            <select name="category" class="form-select mb-2"><?php foreach (['cv' => 'CV', 'id' => 'ID', 'certificate' => 'Certificate', 'report' => 'Report', 'form' => 'Form', 'other' => 'Other'] as $k => $l): ?><option value="<?php echo $k; ?>"><?php echo $l; ?></option><?php endforeach; ?></select>
            <label class="form-label small fw-semibold">Title</label><input name="title" class="form-control mb-2" maxlength="200" placeholder="e.g. My CV">
            <input type="file" name="file" class="form-control mb-3" required>
            <button class="btn btn-success w-100"><i class="fas fa-upload me-1"></i>Upload</button>
            <div class="ip-muted mt-2">PDF, Word, Excel, images and more — up to 20 MB. Only you and Dazu staff can open your files.</div>
        </form>
    </div>
    <div class="col-lg-8">
        <div class="ip-card">
            <h3 class="mb-3">My documents</h3>
            <?php if (!$docs): ?><div class="ip-muted">No documents yet. HR adds your agreement and completion letter here.</div><?php endif; ?>
            <?php foreach ($docs as $d): $u = in_file_url('doc', (int) $d['id']); ?>
                <div class="ip-row"><i class="fas <?php echo $icon($d['mime']); ?> fa-lg text-success"></i>
                    <div class="grow"><div class="nm"><?php echo in_e($d['title']); ?></div><div class="ip-muted"><?php echo in_e(INTERN_DOC_CATEGORIES[$d['category']] ?? ''); ?> · <?php echo in_e($d['uploader']); ?> · <?php echo date('j M Y', strtotime($d['created_at'])); ?></div></div>
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo in_e($u); ?>" target="_blank" title="Preview"><i class="fas fa-eye"></i></a>
                    <a class="btn btn-sm btn-outline-success" href="<?php echo in_e($u . '&dl=1'); ?>" title="Download"><i class="fas fa-download"></i></a>
                    <?php if ((int) $d['uploaded_by'] === (int) $intern['user_id']): ?>
                    <form method="POST" class="m-0" onsubmit="return confirm('Remove this document?')"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete"><input type="hidden" name="doc_id" value="<?php echo (int) $d['id']; ?>"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/intern/layout.php';
