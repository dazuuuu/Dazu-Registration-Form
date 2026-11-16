<?php
// The blog editor for a secretary: the same editor and the same fields as the admin's (the admin's own _editor.php is included, not copied or edited). New post: no id. Edit: ?id=<blog> or ?sub=<waiting post>.
// Saving goes to save.php, which calls the existing blog code; with blog approval ON nothing reaches the live blogs until the admin approves it.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/helpers/secretary_ui.php';
require_once __DIR__ . '/../../../app/v1/services/SecretaryBlogService.php';

$sec = secretary_require('blogs');
$db = \Models\Connection::getInstance()->getConnection();
$svc = new SecretaryBlogService($db);
$h = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$blogId = (int) ($_GET['id'] ?? 0) ?: null;
$subId = (int) ($_GET['sub'] ?? 0) ?: null;
$loaded = $svc->load($blogId, $subId);
if (!$loaded) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); exit('That blog was not found.'); }
[$f, $meta] = $loaded;
$needs = SecretaryBlogService::approvalNeeded($db, (int) $sec['id']);
$cats = $db->query('SELECT id, name FROM blog_categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$isNew = !$meta['blog_id'];

$page_title = $isNew ? 'New blog' : 'Edit blog';
$sp_active = 'blogs';
$sp_subtitle = 'Secretary portal';
ob_start();
?>
<style>
.sb-ed .card { border:1px solid var(--line); border-radius:18px; margin-bottom:16px; background:#fff; }
.sb-ed .card-header { background:#fafbfa; border-bottom:1px solid var(--line); padding:14px 18px; font-weight:800; color:var(--gd); border-radius:18px 18px 0 0; }
.sb-ed .card-body { padding:18px; }
.sb-ed .form-label { font-weight:600; margin-bottom:6px; }
.sb-ed .form-control, .sb-ed .form-select { border-radius:10px; border:1px solid #cbd5d0; min-height:44px; }
.sb-ed .form-control:focus, .sb-ed .form-select:focus { border-color:var(--g); box-shadow:0 0 0 .2rem rgba(4,140,68,.18); }
.sb-ed .sb-save { background:var(--g); border:0; color:#fff; border-radius:12px; min-height:50px; padding:0 22px; font-weight:800; width:100%; }
.sb-ed .sb-save:hover { background:var(--gd); } .sb-ed .sb-save:disabled { opacity:.6; }
.sb-ed .sb-back { display:inline-flex; align-items:center; gap:6px; min-height:44px; color:var(--gd); font-weight:700; }
.sb-ed .sb-banner { border-radius:14px; padding:12px 16px; margin-bottom:14px; font-size:.9rem; }
.sb-ed .sb-banner.info { background:var(--gl); color:#1d4d33; border:1px solid #cfe6d8; } .sb-ed .sb-banner.warn { background:#fff4d6; color:#6f4f00; border:1px solid #f0dc9d; } .sb-ed .sb-banner.bad { background:#fde2e0; color:#8a2a22; border:1px solid #f4b8b2; }
.sb-ed .preview-image { max-width:100%; width:220px; border-radius:10px; margin-top:8px; }
.sb-ed .note-editor.note-frame { border-radius:10px; }
.sb-ed .sb-badge { display:inline-block; font-size:.74rem; font-weight:800; padding:4px 11px; border-radius:99px; background:#eceeea; color:#4a524e; } .sb-ed .sb-badge.published { background:var(--gl); color:var(--gd); }
.sb-ed .sb-blocks div { padding:8px 0; border-bottom:1px solid var(--line); font-size:.88rem; } .sb-ed .sb-blocks div:last-child { border:0; }
@media (max-width: 767.98px) { .sb-ed .form-check { display:flex; align-items:center; gap:12px; min-height:44px; padding-left:0; } .sb-ed .form-check-input { width:24px; height:24px; margin:0; float:none; flex:0 0 auto; } .sb-ed .form-check-label { min-height:44px; display:flex; align-items:center; flex:1; } .sb-ed .note-toolbar .note-btn { min-height:40px; min-width:40px; } .sb-ed .card-body { padding:14px; } .sb-ed .note-editor .note-toolbar { overflow-x:auto; } .sb-ed .note-editable { min-height:260px; } }
</style>
<div class="sb-ed">
<a class="sb-back" href="index.php"><i class="fas fa-arrow-left"></i> All blogs</a>
<?php if ($meta['status'] === 'sent_back'): ?><div class="sb-banner bad"><strong>Sent back by the admin.</strong> <?php echo $h($meta['reason']); ?><br><span class="small">Fix it and press the button to send it again.</span></div>
<?php elseif ($meta['status'] === 'waiting'): ?><div class="sb-banner warn"><strong>Waiting for approval.</strong> You can still change it; the admin will see the newest version.</div><?php endif; ?>
<?php if ($needs): ?><div class="sb-banner info"><i class="fas fa-circle-info me-1"></i> <?php echo $isNew ? 'This post will be saved as “Waiting for approval” and is not shown to the public until the admin approves it.' : 'Your change will wait for the admin. The public keeps seeing the last approved version until then.'; ?></div><?php endif; ?>

<form id="blogForm" enctype="multipart/form-data" autocomplete="off">
<?php echo csrf_field(); ?>
<input type="hidden" name="blog_id" value="<?php echo (int) $meta['blog_id']; ?>">
<input type="hidden" name="sub_id" value="<?php echo (int) $meta['sub_id']; ?>">
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="fas fa-edit me-2"></i> Blog Post Content</div>
            <div class="card-body">
                <div class="mb-3"><label class="form-label" for="title">Title *</label><input type="text" id="title" name="title" class="form-control" maxlength="255" required value="<?php echo $h($f['title']); ?>"></div>
                <div class="mb-3"><label class="form-label" for="excerpt">Excerpt (Short Description)</label><textarea id="excerpt" name="excerpt" class="form-control" rows="3" maxlength="1000"><?php echo $h($f['excerpt']); ?></textarea>
                    <small class="text-muted">Brief summary of the post (150-160 characters recommended)</small></div>
                <div class="mb-3"><label class="form-label" for="content">Content *</label><textarea name="content" id="content" class="form-control" rows="15"><?php echo $h($f['content']); ?></textarea>
                    <small class="text-muted">Use Video, Columns and FAQ in the toolbar for videos, side-by-side layouts and Q&amp;As. Click a video to resize it.</small></div>
                <div class="mb-3"><label class="form-label" for="meta_description">SEO Meta Description</label><textarea id="meta_description" name="meta_description" class="form-control" rows="2" maxlength="500"><?php echo $h($f['meta_description']); ?></textarea></div>
            </div>
        </div>
        <?php if (!empty($meta['blocks'])): ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-cubes me-2"></i> Content Blocks (<?php echo (int) $meta['blocks']; ?>)</div>
            <div class="card-body">
                <p class="text-muted small">This post is also built from content blocks. The public page shows the blocks instead of the Content field above. Blocks are changed by the admin.</p>
                <div class="sb-blocks"><?php foreach ($meta['block_list'] as $b): ?><div><strong><?php echo $h(ucfirst(str_replace('_', ' ', (string) $b['section_type']))); ?></strong> <?php echo $h(mb_strimwidth(trim((string) ($b['title'] ?: strip_tags((string) ($b['content'] ?? '')))), 0, 90, '…')); ?></div><?php endforeach; ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="fas fa-cog me-2"></i> Settings</div>
            <div class="card-body">
                <?php if ($isNew && !$needs): ?>
                <div class="mb-3"><label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select"><option value="draft">Draft</option><option value="published">Published</option></select></div>
                <?php elseif (!$isNew): ?>
                <div class="mb-3"><label class="form-label">Status</label><div><span class="sb-badge <?php echo $h($f['status'] === 'published' ? 'published' : ''); ?>"><?php echo $f['status'] === 'published' ? 'Published' : 'Unpublished'; ?></span>
                    <div class="small text-muted mt-1">Use Unpublish and Publish again on the Blogs list.</div></div></div>
                <?php endif; ?>
                <div class="mb-3"><label class="form-label" for="featured_image">Cover Image <?php echo $f['featured_image'] === '' ? '<span class="text-danger">*</span>' : ''; ?></label>
                    <?php if ($f['featured_image'] !== ''): ?><div><img src="<?php echo $h($f['featured_image']); ?>" class="preview-image" alt=""><small class="text-muted d-block mt-1">Current image</small></div><?php endif; ?>
                    <input type="file" name="featured_image" id="featured_image" class="form-control mt-2" accept="image/jpeg,image/png,image/webp,image/gif" <?php echo $f['featured_image'] === '' ? 'required' : ''; ?>>
                    <img id="imagePreview" class="preview-image" style="display:none;" alt="">
                    <small class="text-muted d-block"><?php echo $f['featured_image'] === '' ? 'Required. ' : 'Upload only to replace the current cover. '; ?>Landscape, about 1200×800.</small></div>
                <button type="submit" class="sb-save" id="saveBtn"><i class="fas fa-paper-plane me-2"></i><?php
                    echo $needs ? ($meta['status'] === 'sent_back' ? 'Send again for approval' : 'Send for approval') : ($isNew ? 'Save post' : 'Update post'); ?></button>
            </div>
        </div>
        <div class="card"><div class="card-header"><i class="fas fa-folder me-2"></i> Category</div><div class="card-body">
            <select name="category_id" class="form-select"><option value="">Select Category</option>
                <?php foreach ($cats as $c): ?><option value="<?php echo (int) $c['id']; ?>" <?php echo (int) $f['category_id'] === (int) $c['id'] ? 'selected' : ''; ?>><?php echo $h($c['name']); ?></option><?php endforeach; ?></select></div></div>
        <div class="card"><div class="card-header"><i class="fas fa-tags me-2"></i> Tags</div><div class="card-body">
            <input type="text" name="tags" class="form-control" value="<?php echo $h(implode(', ', (array) $f['tags'])); ?>" placeholder="Enter tags separated by commas"><small class="text-muted">Example: PHP, Laravel, MySQL</small></div></div>
        <div class="card"><div class="card-header"><i class="fas fa-fire me-2"></i> Popular slider</div><div class="card-body">
            <div class="form-check"><input class="form-check-input" type="checkbox" name="is_featured" id="is_featured" value="1" <?php echo !empty($f['is_featured']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="is_featured">Show in the Popular slider at the top of the Blog page</label></div></div></div>
        <div class="card"><div class="card-header"><i class="fas fa-chart-line me-2"></i> SEO Settings</div><div class="card-body">
            <div class="mb-3"><label class="form-label" for="meta_title">Meta Title</label><input type="text" id="meta_title" name="meta_title" class="form-control" maxlength="255" value="<?php echo $h($f['meta_title']); ?>" placeholder="SEO title (60 characters max)"><small class="text-muted">Leave empty to use post title</small></div>
            <div><label class="form-label" for="meta_keywords">Meta Keywords</label><input type="text" id="meta_keywords" name="meta_keywords" class="form-control" maxlength="255" value="<?php echo $h($f['meta_keywords']); ?>" placeholder="keyword1, keyword2, keyword3"></div></div></div>
    </div>
</div>
</form>
</div>

<script>window.url = function (p) { return '/' + String(p || '').replace(/^\//, ''); };</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php include __DIR__ . '/../../admin/blogs/_editor.php'; /* the admin's own editor setup, reused as it is */ ?>
<script>
$(function () {
    var csrf = document.querySelector('#blogForm [name=csrf_token]').value;
    dzInitEditor($('#content'), { callbacks: { onImageUpload: function (files) {
        Array.prototype.forEach.call(files, function (file) {
            var fd = new FormData(); fd.append('image', file); fd.append('csrf_token', csrf);
            fetch('upload_image.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (r) { if (r.ok) $('#content').summernote('insertImage', r.url); else alert('Image not added: ' + r.error); })
                .catch(function () { alert('The image could not be uploaded. Check your connection and try again.'); });
        });
    } } });
});
document.getElementById('featured_image').addEventListener('change', function (e) {
    var f = e.target.files[0]; if (!f) return; var r = new FileReader();
    r.onload = function () { var p = document.getElementById('imagePreview'); p.src = r.result; p.style.display = 'block'; }; r.readAsDataURL(f);
});
document.getElementById('blogForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    var btn = document.getElementById('saveBtn'), old = btn.innerHTML, fd = new FormData(e.target);
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
    try {
        var html = document.getElementById('content').value;
        try { html = $('#content').summernote('code'); } catch (x) { /* the editor is not ready: use the text box */ }
        fd.set('content', html);
        var r = await fetch('save.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        var d = await r.json().catch(function () { return { ok: false, error: 'Could not save. Please reload the page and try again (your text is still in the editor: copy it first).' }; });
        if (d.ok) { window.location.href = 'index.php'; } else { alert(d.error || 'Could not save.'); }
    } catch (err) { alert('No connection. Nothing was saved.'); }
    btn.disabled = false; btn.innerHTML = old;
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/secretary/layout.php';
