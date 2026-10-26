<?php
require_once __DIR__ . '/../_init.php';
sms_require_tables($pdo);
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$prospect = $id ? $sms->getProspect($id) : null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $sms->saveProspect($id ?: null, $_POST);
    if (!empty($result['ok'])) {
        sms_flash('success', $id ? 'Prospect updated.' : 'Prospect created.');
        sms_redirect('superadmin/sms/prospects/index.php');
    }
    $error = $result['error'] ?? 'Save failed.';
    $prospect = array_merge($prospect ?? [], $_POST);
}

$page_title = $id ? 'Edit prospect' : 'Add prospect';
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('superadmin/dashboard.php#sms-alerts')],
    ['label' => 'Prospects', 'url' => url('superadmin/sms/prospects/index.php')],
    ['label' => $page_title, 'active' => true],
];

ob_start();
?>
<div class="card shadow-sm"><div class="card-body">
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" class="row g-3">
    <div class="col-md-6"><label class="form-label">Name *</label><input name="name" required class="form-control" value="<?= htmlspecialchars($prospect['name'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= htmlspecialchars($prospect['phone'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($prospect['email'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Interest type</label>
      <select name="interest_type" class="form-select">
        <?php foreach (['course','project'] as $it): ?>
        <option value="<?= $it ?>" <?= ($prospect['interest_type'] ?? 'course') === $it ? 'selected' : '' ?>><?= ucfirst($it) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4"><label class="form-label">Intake / course</label><input name="intake_or_course" class="form-control" value="<?= htmlspecialchars($prospect['intake_or_course'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Follow-up date</label><input type="date" name="follow_up_date" class="form-control" value="<?= htmlspecialchars($prospect['follow_up_date'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Status</label>
      <select name="status" class="form-select">
        <?php foreach (['open','converted','lost'] as $st): ?>
        <option value="<?= $st ?>" <?= ($prospect['status'] ?? 'open') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($prospect['notes'] ?? '') ?></textarea></div>
    <div class="col-12">
      <button class="btn btn-success"><?= $id ? 'Update' : 'Create' ?> prospect</button>
      <a href="<?= url('superadmin/sms/prospects/index.php') ?>" class="btn btn-link">Cancel</a>
    </div>
  </form>
</div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../templates/superadmin/layout.php';
