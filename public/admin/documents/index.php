<?php
require_once __DIR__ . '/../../../app/v1/bootstrap.php';

if (!isLoggedIn() || (int) ($_SESSION['role_id'] ?? 99) > 2) {
    redirect(url('admin/portal.php'));
}

$documents = [];
$error = null;

try {
    $documents = $pdo->query(
        'SELECT d.*, r.status AS registration_status, r.email
         FROM admission_documents d
         LEFT JOIN registrations r ON r.id = d.registration_id
         ORDER BY d.created_at DESC
         LIMIT 500'
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $error = 'Run Admin → System Updates (038_class_schedules_and_admission) to enable admission documents.';
}

$page_title = 'Admission Documents';
$breadcrumbs = [
    ['label' => 'System', 'url' => url('admin/registrations/index.php')],
    ['label' => 'Documents', 'active' => true],
];

ob_start();
?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0">Admission letters</h5>
    <span class="badge bg-secondary"><?php echo count($documents); ?> files</span>
  </div>
  <div class="card-body">
    <?php if ($error): ?>
      <div class="alert alert-warning"><?php echo htmlspecialchars($error); ?></div>
    <?php elseif (empty($documents)): ?>
      <p class="text-muted mb-0">No admission letters yet. They are created automatically when students enroll.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>Registration #</th>
              <th>Student</th>
              <th>Course</th>
              <th>Email</th>
              <th>Status</th>
              <th>Created</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($documents as $doc): ?>
              <tr>
                <td><code><?php echo htmlspecialchars($doc['registration_no']); ?></code></td>
                <td><?php echo htmlspecialchars($doc['student_name']); ?></td>
                <td><?php echo htmlspecialchars($doc['course_title']); ?></td>
                <td class="small"><?php echo htmlspecialchars($doc['email'] ?? '—'); ?></td>
                <td><span class="badge bg-<?php echo ($doc['registration_status'] ?? '') === 'approved' ? 'success' : 'warning'; ?>"><?php echo htmlspecialchars($doc['registration_status'] ?? 'pending'); ?></span></td>
                <td class="small text-muted"><?php echo htmlspecialchars($doc['created_at']); ?></td>
                <td>
                  <?php if (!empty($doc['file_path'])): ?>
                    <a href="<?php echo htmlspecialchars(url($doc['file_path'])); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark">
                      <i class="fas fa-download me-1"></i> Download
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/admin/layout.php';
