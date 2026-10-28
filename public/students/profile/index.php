<?php
// Student: My profile — change or delete their photo.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/helpers/profile_photo.php';

Middleware::requireStudent();
$db = \Models\Connection::getInstance()->getConnection();
$uid = (int) ($_SESSION['user_id'] ?? 0);
pp_handle($db, $uid, url('students/profile/index.php'));

$page_title = 'My profile';
ob_start();
require __DIR__ . '/../../components/profile/photo_card.php';
require_once __DIR__ . '/../../../app/v1/services/StudentLetterService.php';
if (sl_installed($db) && ($slRegs = sl_student_regs($db, $uid))): ?>
<div class="card-tutor p-3 p-md-4 mt-3">
    <div class="fw-bold mb-2"><i class="fas fa-envelope-open-text text-success me-1"></i>Letter of recommendation</div>
    <?php foreach ($slRegs as $slr): sl_sync($db, (int) $slr['id']); $slL = sl_letter($db, (int) $slr['id']); ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-top">
            <span><?php echo htmlspecialchars($slr['title']); ?></span>
            <?php if ($slL): ?><a class="btn btn-sm btn-success" href="<?php echo htmlspecialchars(url('letters/student.php?reg=' . (int) $slr['id'])); ?>">Open my letter</a>
            <?php else: ?><span class="small text-muted">Ready when your course is finished and your fees are paid in full.</span><?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php endif;
$content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
