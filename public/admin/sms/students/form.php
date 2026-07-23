<?php
require_once __DIR__ . '/../_init.php';
sms_require_tables($pdo);
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$student = $id ? $sms->getStudent($id) : null;
$courses = $sms->courses();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    $result = $id ? $sms->updateStudent($id, $data) : $sms->createStudent($data);
    if (!empty($result['ok'])) {
        sms_flash('success', $id ? 'Student updated.' : 'Student created.');
        sms_redirect('admin/sms/students/view.php?id=' . ($id ?: $result['id']));
    }
    $error = $result['error'] ?? 'Save failed.';
    $student = array_merge($student ?? [], $data);
}

$page_title = $id ? 'Edit student' : 'Add student';
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('admin/dashboard.php#sms-alerts')],
    ['label' => 'Students', 'url' => url('admin/sms/students/index.php')],
    ['label' => $page_title, 'active' => true],
];

ob_start();
?>
<div class="card shadow-sm"><div class="card-body">
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post" class="row g-3">
    <div class="col-md-6"><label class="form-label">Name *</label><input name="name" required class="form-control" value="<?= htmlspecialchars($student['name'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($student['email'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= htmlspecialchars($student['phone'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Course *</label>
      <select name="course_id" class="form-select" required>
        <option value="">— Select —</option>
        <?php foreach ($courses as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= (int)($student['course_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4"><label class="form-label">Duration</label><input name="duration" class="form-control" value="<?= htmlspecialchars($student['duration'] ?? '') ?>" placeholder="e.g. 6 months"></div>
    <div class="col-md-4"><label class="form-label">Start date</label><input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($student['start_date'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Parent phone</label><input name="parent_phone" class="form-control" value="<?= htmlspecialchars($student['parent_phone'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Parent email</label><input type="email" name="parent_email" class="form-control" value="<?= htmlspecialchars($student['parent_email'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Cost (KES)</label><input type="number" step="0.01" min="0" name="cost" class="form-control" value="<?= htmlspecialchars($student['cost'] ?? '0') ?>"></div>
    <div class="col-md-4"><label class="form-label">Paid (KES)</label><input type="number" step="0.01" min="0" name="paid" class="form-control" value="<?= htmlspecialchars($student['paid'] ?? '0') ?>"></div>
    <div class="col-md-4"><label class="form-label">Status</label>
      <select name="status" class="form-select">
        <?php foreach (['active','completed','withdrawn'] as $st): ?>
        <option value="<?= $st ?>" <?= ($student['status'] ?? 'active') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($student['notes'] ?? '') ?></textarea></div>
    <div class="col-12"><button class="btn btn-success"><?= $id ? 'Update' : 'Create' ?> student</button>
      <a href="<?= url('admin/sms/students/index.php') ?>" class="btn btn-link">Cancel</a></div>
  </form>
</div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../templates/admin/layout.php';
