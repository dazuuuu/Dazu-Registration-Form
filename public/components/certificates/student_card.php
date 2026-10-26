<?php
// Certificate card on a student's page (admin + superadmin).
// Expects: $pdo, $enrols (approved enrolments), $bal (total outstanding), $certArea ('admin'|'superadmin').
require_once __DIR__ . '/../../../app/v1/services/CertificateService.php';

$certIds = array_map(fn($e) => (int) $e['id'], $enrols);
$certByReg = [];
if ($certIds) {
    $st = $pdo->query("SELECT id, registration_id, certificate_no, status FROM certificates
                       WHERE status <> 'withdrawn' AND registration_id IN (" . implode(',', $certIds) . ")");
    foreach ($st->fetchAll() as $c) {
        $certByReg[(int) $c['registration_id']] = $c;
    }
}
$certReturn = url($certArea . '/students/view.php?id=' . (int) ($_GET['id'] ?? 0));
?>
<div class="card mb-3"><div class="card-header fw-semibold"><i class="fas fa-certificate text-success me-1"></i>Certificate</div>
    <ul class="list-group list-group-flush">
        <?php foreach ($enrols as $e): $c = $certByReg[(int) $e['id']] ?? null; ?>
        <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small">
                <div class="fw-semibold"><?php echo htmlspecialchars((string) $e['course_title']); ?></div>
                <?php if ($c): ?>
                    <span class="text-muted"><?php echo htmlspecialchars($c['certificate_no']); ?> · <?php echo htmlspecialchars($c['status'] === 'collected' ? 'Picked Up' : ucwords(str_replace('_', ' ', $c['status']))); ?></span>
                <?php elseif ($bal > 0): ?>
                    <span class="text-danger">Clear the <?php echo 'KES ' . number_format($bal); ?> balance to generate a certificate.</span>
                <?php else: ?>
                    <span class="text-success">Fees cleared — ready for a certificate.</span>
                <?php endif; ?>
            </div>
            <?php if ($c): ?>
                <div class="d-flex gap-1">
                    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?php echo url($certArea . '/certificates/preview.php?id=' . $c['id']); ?>"><i class="fas fa-eye"></i></a>
                    <a class="btn btn-sm btn-outline-success" title="Download PDF" href="<?php echo url($certArea . '/certificates/preview.php?id=' . $c['id'] . '&print=1'); ?>"><i class="fas fa-download"></i></a>
                    <a class="btn btn-sm btn-outline-primary" href="<?php echo url($certArea . '/certificates/edit.php?id=' . $c['id']); ?>">Open · Send</a>
                </div>
            <?php else: ?>
                <form method="POST" action="<?php echo url($certArea . '/certificates/index.php'); ?>" class="m-0">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="registration_id" value="<?php echo (int) $e['id']; ?>">
                    <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($certReturn); ?>">
                    <button class="btn btn-sm btn-success" <?php echo $bal > 0 ? 'disabled title="Fee balance must be zero"' : ''; ?>><i class="fas fa-certificate me-1"></i>Generate certificate</button>
                </form>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
