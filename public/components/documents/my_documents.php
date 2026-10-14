<?php
// "My documents" body shared by the student and tutor portals.
// Expects: $db (PDO), $userId, $docsSelf (this page's URL). POSTs are handled by docs_handle_post() before output.
require_once __DIR__ . '/../../../app/v1/helpers/lms_files.php';

$docsList = docs_list($db, $userId);
$docsProfile = docs_profile($db, $userId);
$docsMissing = docs_missing($db, $userId);
?>
<h1 class="h4 mb-3"><i class="fas fa-id-card text-success me-2"></i>My Documents</h1>

<?php foreach (['success_msg' => 'success', 'error_msg' => 'danger'] as $key => $cls): if (isset($_SESSION[$key])): ?>
    <div class="alert alert-<?php echo $cls; ?> alert-dismissible fade show"><?php echo htmlspecialchars($_SESSION[$key]); unset($_SESSION[$key]); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; endforeach; ?>

<?php if (SessionModel::get('role') === 'student') { require __DIR__ . '/../id_cards/student_panel.php'; } ?>

<?php if ($docsMissing): ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-1"></i>
        <strong>Required:</strong> please provide your <?php echo htmlspecialchars(implode(', ', $docsMissing)); ?>.</div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-md-6" id="passport-photo">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="fw-bold mb-2">Passport photo <span class="text-danger">*</span></h6>
            <div class="d-flex gap-3 align-items-center flex-wrap">
                <?php if (!empty($docsProfile['profile_photo_path'])): ?>
                    <img src="<?php echo htmlspecialchars(settings_asset_url($docsProfile['profile_photo_path'])); ?>" alt="Passport photo" class="rounded border" style="width:84px;height:100px;object-fit:cover">
                <?php else: ?>
                    <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted" style="width:84px;height:100px"><i class="fas fa-user fa-2x"></i></div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" action="<?php echo htmlspecialchars($docsSelf); ?>" class="flex-grow-1" style="min-width:200px">
                    <?php echo csrf_field(); ?><input type="hidden" name="docs_action" value="photo">
                    <input type="file" name="photo" class="form-control form-control-sm mb-2" accept="image/jpeg,image/png,image/webp" required>
                    <button class="btn btn-sm btn-success"><i class="fas fa-upload me-1"></i><?php echo empty($docsProfile['profile_photo_path']) ? 'Upload photo' : 'Change photo'; ?></button>
                    <div class="form-text">Clear, passport-style, JPG/PNG, max 5 MB.</div>
                </form>
            </div>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h6 class="fw-bold mb-2">T-shirt size <span class="text-danger">*</span></h6>
            <form method="POST" action="<?php echo htmlspecialchars($docsSelf); ?>" class="d-flex gap-2 flex-wrap align-items-center">
                <?php echo csrf_field(); ?><input type="hidden" name="docs_action" value="tshirt">
                <select name="tshirt_size" class="form-select form-select-sm" style="max-width:160px" required>
                    <option value="">Choose size…</option>
                    <?php foreach (TSHIRT_SIZES as $sz): ?>
                        <option value="<?php echo $sz; ?>" <?php echo ($docsProfile['tshirt_size'] ?? '') === $sz ? 'selected' : ''; ?>><?php echo $sz; ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-success">Save</button>
            </form>
            <?php if (!empty($docsProfile['tshirt_size'])): ?><div class="small text-success mt-2"><i class="fas fa-check me-1"></i>Saved: <?php echo htmlspecialchars($docsProfile['tshirt_size']); ?></div><?php endif; ?>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3"><div class="card-body">
    <h6 class="fw-bold mb-1">Submit academic documents <span class="text-danger">*</span></h6>
    <p class="text-muted small mb-3">PDF only, up to 10 MB each. Select one or more files, give each a name (e.g. "KCSE Certificate", "National ID"), then submit. The admin will see them.</p>
    <form method="POST" enctype="multipart/form-data" action="<?php echo htmlspecialchars($docsSelf); ?>" id="docsForm">
        <?php echo csrf_field(); ?><input type="hidden" name="docs_action" value="upload">
        <input type="file" id="docsPicker" class="form-control mb-3" accept="application/pdf" multiple>
        <div id="docsRows"></div>
        <button class="btn btn-success d-none" id="docsSubmit"><i class="fas fa-paper-plane me-1"></i>Submit documents</button>
    </form>
</div></div>

<div class="card border-0 shadow-sm"><div class="card-body p-0">
    <div class="p-3 border-bottom fw-bold">Submitted documents (<?php echo count($docsList); ?>)</div>
    <?php if (!$docsList): ?>
        <p class="text-muted p-3 mb-0">Nothing submitted yet.</p>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($docsList as $d): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div>
                        <i class="fas fa-file-pdf text-danger me-2"></i><strong><?php echo htmlspecialchars($d['doc_name']); ?></strong>
                        <div class="small text-muted"><?php echo htmlspecialchars((string) $d['original_name']); ?> · <?php echo date('d M Y', strtotime($d['uploaded_at'])); ?></div>
                    </div>
                    <div class="d-flex gap-1">
                        <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo url('documents/file.php?id=' . (int) $d['id']); ?>"><i class="fas fa-eye"></i></a>
                        <form method="POST" action="<?php echo htmlspecialchars($docsSelf); ?>" onsubmit="return confirm('Remove this document?')" class="m-0">
                            <?php echo csrf_field(); ?><input type="hidden" name="docs_action" value="delete"><input type="hidden" name="doc_id" value="<?php echo (int) $d['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div></div>

<script>
// Each picked PDF gets its own row with a name box. The files are moved into a real <input name="documents[]">
// through DataTransfer so the form posts them in the same order as the names.
(function () {
    var picker = document.getElementById('docsPicker'), rows = document.getElementById('docsRows'),
        submit = document.getElementById('docsSubmit'), form = document.getElementById('docsForm'), files = [];
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function render() {
        rows.innerHTML = '';
        files.forEach(function (f, i) {
            var guess = f.name.replace(/\.pdf$/i, '').replace(/[_-]+/g, ' ');
            rows.insertAdjacentHTML('beforeend',
                '<div class="d-flex gap-2 align-items-center mb-2 flex-wrap">' +
                '<span class="small text-muted text-truncate" style="max-width:220px"><i class="fas fa-file-pdf text-danger me-1"></i>' + esc(f.name) + '</span>' +
                '<input type="text" name="doc_names[]" class="form-control form-control-sm flex-grow-1" style="min-width:180px" required maxlength="150" placeholder="Document name" value="' + esc(guess) + '">' +
                '<button type="button" class="btn btn-sm btn-outline-danger" data-i="' + i + '"><i class="fas fa-times"></i></button></div>');
        });
        submit.classList.toggle('d-none', files.length === 0);
    }
    picker.addEventListener('change', function () {
        Array.prototype.forEach.call(picker.files, function (f) { files.push(f); });
        picker.value = '';
        render();
    });
    rows.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-i]');
        if (b) { files.splice(+b.dataset.i, 1); render(); }
    });
    form.addEventListener('submit', function () {
        var dt = new DataTransfer();
        files.forEach(function (f) { dt.items.add(f); });
        var input = document.createElement('input');
        input.type = 'file'; input.name = 'documents[]'; input.multiple = true; input.hidden = true;
        input.files = dt.files;
        form.appendChild(input);
    });
})();
</script>
