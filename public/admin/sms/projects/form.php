<?php
require_once __DIR__ . '/../_init.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$project = $id ? $sms->getProject($id) : null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $sms->saveProject($id ?: null, $_POST);
    if (!empty($result['ok'])) {
        sms_flash('success', $id ? 'Project updated.' : 'Project created.');
        sms_redirect('admin/sms/projects/view.php?id=' . ($id ?: $result['id']));
    }
    $error = $result['error'] ?? 'Save failed.';
    $project = array_merge($project ?? [], $_POST);
}

$page_title = $id ? 'Edit project' : 'Add project';
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('admin/sms/index.php')],
    ['label' => 'Projects', 'url' => url('admin/sms/projects/index.php')],
    ['label' => $page_title, 'active' => true],
];

ob_start();
?>
<div class="card shadow-sm"><div class="card-body">
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" class="row g-3">
    <div class="col-md-8"><label class="form-label">Title *</label><input name="title" required class="form-control" value="<?= htmlspecialchars($project['title'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Status</label>
      <select name="status" class="form-select">
        <?php foreach (['active','in_progress','completed','cancelled'] as $st): ?>
        <option value="<?= $st ?>" <?= ($project['status'] ?? 'active') === $st ? 'selected' : '' ?>><?= str_replace('_', ' ', ucfirst($st)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4"><label class="form-label">Client name</label><input name="client_name" class="form-control" value="<?= htmlspecialchars($project['client_name'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Client email</label><input type="email" name="client_email" class="form-control" value="<?= htmlspecialchars($project['client_email'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Client phone</label><input name="client_phone" class="form-control" value="<?= htmlspecialchars($project['client_phone'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Company</label><input name="client_company" class="form-control" value="<?= htmlspecialchars($project['client_company'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">Start date</label><input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($project['start_date'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">Due date</label><input type="date" name="due_date" class="form-control" value="<?= htmlspecialchars($project['due_date'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">Estimated cost (KES)</label><input type="number" step="0.01" min="0" name="estimated_cost" class="form-control" value="<?= htmlspecialchars($project['estimated_cost'] ?? '0') ?>"></div>
    <div class="col-md-3"><label class="form-label">Paid amount (KES)</label><input type="number" step="0.01" min="0" name="paid_amount" class="form-control" value="<?= htmlspecialchars($project['paid_amount'] ?? '0') ?>"></div>
    <div class="col-md-3"><label class="form-label">Follow-up date</label><input type="date" name="follow_up_date" class="form-control" value="<?= htmlspecialchars($project['follow_up_date'] ?? '') ?>"></div>
    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($project['notes'] ?? '') ?></textarea></div>
    <div class="col-12">
      <button class="btn btn-success"><?= $id ? 'Update' : 'Create' ?> project</button>
      <a href="<?= url('admin/sms/projects/index.php') ?>" class="btn btn-link">Cancel</a>
    </div>
  </form>
</div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../templates/admin/layout.php';
