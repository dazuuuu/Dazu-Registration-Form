<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/SubmissionModel.php';

use Models\Connection;
use Models\TutorModel;
use Models\SubmissionModel;

Middleware::requireAdmin();

$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$tutors = $tutorModel->listAll();

$page_title = 'Tutors';
$breadcrumbs = [['label' => 'Tutors', 'active' => true]];
$extra_css = '<link rel="stylesheet" href="' . sa_asset('css/sheet.css') . '">';
$extra_js = '<script src="' . sa_asset('js/sheet.js') . '"></script>';
$col = fn(int $i): string => chr(64 + $i);
ob_start();
?>
<div class="xl-toolbar">
    <label class="xl-search"><i class="fas fa-magnifying-glass"></i><input type="search" data-sheet-search placeholder="Search tutor name, email or status…" autocomplete="off"></label>
    <span class="xl-count" data-sheet-count></span>
    <a href="create.php" class="btn btn-success"><i class="fas fa-plus me-1"></i> Add Tutor</a>
</div>
<div class="xl-shell">
    <div class="xl-formula"><span class="xl-ref" id="xlRef">A1</span><span class="xl-fx">fx</span><span class="xl-val" id="xlVal">Click a cell. Click a tutor's name to manage them.</span></div>
    <div class="xl-sheet active" id="sheet-tutors">
    <div class="xl-scroll">
    <table class="xl">
        <thead>
            <tr class="xl-letters"><th class="rn"></th><?php for ($i = 1; $i <= 6; $i++) echo '<th>' . $col($i) . '</th>'; ?></tr>
            <tr class="xl-head"><th class="rn"></th><th>Tutor</th><th>Email</th><th class="num">Students</th><th class="num">Courses</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (empty($tutors)): ?>
            <tr data-norow="1"><th class="rn">1</th><td colspan="6" class="text-muted">No tutors yet. <a href="create.php">Create the first tutor</a>.</td></tr>
        <?php endif; ?>
        <?php foreach ($tutors as $n => $t): $name = $tutorModel->fullName($t); ?>
            <tr>
                <th class="rn"><?php echo $n + 1; ?></th>
                <td><a class="xl-link" href="view.php?id=<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($name); ?></a></td>
                <td class="xl-wrap"><?php echo htmlspecialchars($t['email']); ?></td>
                <td class="num"><?php echo count($t['students']); ?></td>
                <td class="num"><?php echo count($t['courses']); ?></td>
                <td><?php echo $t['is_active'] ? '<span class="text-success fw-semibold small">Active</span>' : '<span class="text-muted small">Inactive</span>'; ?></td>
                <td class="text-end"><a href="view.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-primary">Manage</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($tutors): ?><tfoot><tr><th class="rn"></th><td>Total</td><td></td><td class="num"><?php echo array_sum(array_map(fn($t) => count($t['students']), $tutors)); ?></td><td></td><td colspan="2"><?php echo count($tutors); ?> tutors</td></tr></tfoot><?php endif; ?>
    </table>
    </div>
    </div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/admin/layout.php';
