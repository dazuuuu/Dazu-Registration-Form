<?php
// public/admin/registrations/index.php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/RegistrationModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';

use Models\Connection;
use Models\RegistrationModel;

Middleware::requireAdmin();

$pdo     = Connection::getInstance()->getConnection();
$regModel = new RegistrationModel($pdo);

$status = in_array($_GET['status'] ?? '', ['pending','approved','rejected']) ? $_GET['status'] : 'pending';
$rows   = $regModel->listByStatus($status);

// Count badges for each tab
$counts = [];
foreach (['pending','approved','rejected'] as $s) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM registrations WHERE status = :s");
    $st->execute([':s' => $s]);
    $counts[$s] = (int) $st->fetchColumn();
}

$page_title  = 'Student Registrations';
$breadcrumbs = [['label' => 'Registrations', 'active' => true]];

ob_start();
?>
<ul class="nav nav-tabs mb-4">
    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $status === $key ? 'active' : ''; ?>"
               href="?status=<?php echo $key; ?>">
                <?php echo $label; ?>
                <?php if ($counts[$key] > 0): ?>
                    <span class="badge <?php echo $key === 'pending' ? 'bg-warning text-dark' : ($key === 'approved' ? 'bg-success' : 'bg-danger'); ?> ms-1">
                        <?php echo $counts[$key]; ?>
                    </span>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Course</th>
                        <th>Mode</th>
                        <th>Plan</th>
                        <th>Reg. Fee</th>
                        <th>Course Fee</th>
                        <th>Submitted</th>
                        <?php if ($status === 'approved'): ?><th>Reg. No.</th><?php endif; ?>
                        <?php if ($status === 'rejected'): ?><th>Reason</th><?php endif; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">
                        No <?php echo $status; ?> registrations.
                    </td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($r['full_name']); ?>
                            <?php if ($r['possible_returning_student']): ?>
                                <span class="badge bg-info ms-1" title="Possible returning student">↩ Returning?</span>
                            <?php endif; ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($r['email']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($r['course_title']); ?></td>
                        <td><?php echo $r['study_mode'] === 'physical' ? '🏫 Physical' : '💻 Online'; ?></td>
                        <td><?php echo htmlspecialchars($r['payment_plan']); ?></td>
                        <td>
                            <?php if ($r['registration_fee_waived']): ?>
                                <span class="text-decoration-line-through text-muted">KSh <?php echo number_format($r['registration_fee']); ?></span>
                                <span class="badge bg-secondary ms-1">Waived</span>
                            <?php else: ?>
                                KSh <?php echo number_format($r['registration_fee']); ?>
                            <?php endif; ?>
                        </td>
                        <td>KSh <?php echo number_format($r['course_fee']); ?></td>
                        <td><small><?php echo date('d M Y', strtotime($r['created_at'])); ?></small></td>
                        <?php if ($status === 'approved'): ?>
                            <td><strong><?php echo htmlspecialchars($r['registration_no']); ?></strong></td>
                        <?php endif; ?>
                        <?php if ($status === 'rejected'): ?>
                            <td><small class="text-danger"><?php echo htmlspecialchars($r['rejection_reason']); ?></small></td>
                        <?php endif; ?>
                        <td>
                            <a href="<?php echo url('admin/registrations/view.php?id=' . (int)$r['id']); ?>"
                               class="btn btn-sm btn-outline-primary">
                                <?php echo $status === 'pending' ? 'Review' : 'View'; ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';