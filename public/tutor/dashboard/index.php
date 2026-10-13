<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/SubmissionModel.php';
require_once __DIR__ . '/../../../app/v1/models/DashboardModel.php';

use Models\Connection;
use Models\TutorModel;
use Models\SubmissionModel;
use Models\DashboardModel;

Middleware::requireTutor();

$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$tutor = $tutorModel->getByUserId((int)$_SESSION['user_id']);
$stats = (new DashboardModel($pdo))->tutorStats((int)$tutor['id']);
$pending = (new SubmissionModel($pdo))->getPendingForTutor((int)$tutor['id']);

$page_title = 'Dashboard';
ob_start();
$pwaPortal = 'tutor'; require __DIR__ . '/../../components/pwa/banner.php';
?>
<?php
require_once __DIR__ . '/../../../app/v1/models/TutorCourseModel.php';
$tc = new \Models\TutorCourseModel($pdo);
$myStudents = $tutorModel->getStudentsForTutor((int) $tutor['id']);
$firstName = strtok($tutorModel->fullName($tutor), ' ') ?: 'there';
$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
?>
<style>
.td-row{display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid var(--line);text-decoration:none;color:inherit}
.td-row:last-child{border-bottom:0}
.td-row:hover{background:#fafbf8}
.td-row .as-ava{width:40px;height:40px;font-size:.8rem}
.td-row .grow{flex:1;min-width:0}
.td-row .nm{font-weight:600;font-size:.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.td-row .sb{font-size:.75rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.td-mini{height:6px;border-radius:99px;background:#eceee9;overflow:hidden;margin-top:6px}
.td-mini>div{height:100%;background:var(--g);border-radius:99px}
</style>

<div class="as-hero">
    <div class="hi"><?php echo date('l, j F'); ?></div>
    <h1><?php echo $greet . ', ' . htmlspecialchars($firstName); ?> 👋</h1>
    <p><?php echo $stats['pending']
        ? 'You have <strong>' . (int) $stats['pending'] . '</strong> submission' . ($stats['pending'] > 1 ? 's' : '') . ' waiting for a grade.'
        : 'You are all caught up on grading. Nice work!'; ?></p>
    <div class="d-flex gap-2 flex-wrap mt-3">
        <?php if ($stats['pending']): ?><a href="<?php echo url('tutor/submissions/index.php'); ?>" class="btn btn-light"><i class="fas fa-clipboard-check me-2 text-success"></i>Start grading</a><?php endif; ?>
        <a href="<?php echo url('tutor/students/index.php'); ?>" class="btn btn-outline-light"><i class="fas fa-user-graduate me-2"></i>My students</a>
    </div>
</div>

<div class="as-stats">
    <a class="as-stat" href="<?php echo url('tutor/students/index.php'); ?>"><span class="ic"><i class="fas fa-user-graduate"></i></span><span class="k">Students</span><span class="v"><?php echo count($myStudents); ?></span></a>
    <a class="as-stat" href="<?php echo url('tutor/courses/index.php'); ?>"><span class="ic dark"><i class="fas fa-book-open"></i></span><span class="k">Courses</span><span class="v"><?php echo (int) $stats['courses']; ?></span></a>
    <a class="as-stat" href="<?php echo url('tutor/submissions/index.php'); ?>"><span class="ic warn"><i class="fas fa-clipboard-check"></i></span><span class="k">To grade</span><span class="v"><?php echo (int) $stats['pending']; ?></span></a>
    <div class="as-stat"><span class="ic"><i class="fas fa-flag-checkered"></i></span><span class="k">Completed</span><span class="v"><?php echo count(array_filter($myStudents, fn($s) => !empty($s['studies_completed_at']))); ?></span></div>
</div>

<div class="as-quick mb-3">
    <a href="<?php echo url('tutor/modules/index.php'); ?>"><i class="fas fa-layer-group"></i>Course builder</a>
    <a href="<?php echo url('tutor/submissions/index.php'); ?>"><i class="fas fa-clipboard-check"></i>Grade work</a>
    <a href="<?php echo url('tutor/resources/index.php'); ?>"><i class="fas fa-folder-open"></i>Resources</a>
    <a href="<?php echo url('tutor/students/index.php'); ?>"><i class="fas fa-key"></i>Resend logins</a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card-tutor">
            <div class="head justify-content-between"><span><i class="fas fa-users me-2"></i>Student progress</span><a class="small" href="<?php echo url('tutor/students/index.php'); ?>">See all</a></div>
            <?php if (!$myStudents): ?><div class="as-empty"><i class="fas fa-user-graduate"></i>No students assigned yet.</div><?php endif; ?>
            <?php foreach (array_slice($myStudents, 0, 8) as $s): $p = $tc->progressFor($s);
                $ini = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($s['full_name'])), 0, 2)))); ?>
                <a class="td-row" href="<?php echo url('tutor/students/view.php?id=' . (int) $s['id']); ?>">
                    <span class="as-ava"><?php echo htmlspecialchars($ini); ?></span>
                    <div class="grow">
                        <div class="d-flex justify-content-between gap-2"><span class="nm"><?php echo htmlspecialchars($s['full_name']); ?></span>
                            <span class="small fw-semibold"><?php echo $p['total'] ? $p['percent'] . '%' : '—'; ?></span></div>
                        <div class="sb"><?php echo htmlspecialchars($s['course_title']); ?></div>
                        <div class="td-mini"><div style="width:<?php echo (int) $p['percent']; ?>%"></div></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-tutor">
            <div class="head justify-content-between"><span><i class="fas fa-inbox me-2"></i>Waiting for a grade</span><?php if ($pending): ?><a class="small" href="<?php echo url('tutor/submissions/index.php'); ?>">Open</a><?php endif; ?></div>
            <?php if (!$pending): ?><div class="as-empty"><i class="fas fa-mug-hot"></i>Nothing to grade right now.</div><?php endif; ?>
            <?php foreach (array_slice($pending, 0, 5) as $sub): ?>
                <a class="td-row" href="<?php echo url('tutor/submissions/index.php#sub-' . (int) $sub['id']); ?>">
                    <span class="as-ava" style="background:#fff4e0;color:#b45309"><i class="fas fa-file-pen"></i></span>
                    <div class="grow"><div class="nm"><?php echo htmlspecialchars((string) $sub['item_title']); ?></div>
                        <div class="sb"><?php echo htmlspecialchars($sub['full_name']); ?> · <?php echo $sub['submitted_at'] ? date('j M', strtotime($sub['submitted_at'])) : ''; ?></div></div>
                    <i class="fas fa-chevron-right text-muted small"></i>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="card-tutor">
            <div class="head"><i class="fas fa-book me-2"></i>My courses</div>
            <?php if (empty($tutor['courses'])): ?><div class="as-empty"><i class="fas fa-book"></i>No courses yet.</div><?php endif; ?>
            <?php foreach ($tutor['courses'] as $c): ?>
                <a class="td-row" href="<?php echo url('tutor/modules/index.php?course_id=' . (int) $c['id']); ?>">
                    <span class="as-ava" style="background:var(--g-50);color:var(--g-d)"><i class="fas fa-book-open"></i></span>
                    <div class="grow"><div class="nm"><?php echo htmlspecialchars($c['title']); ?></div><div class="sb">Open in course builder</div></div>
                    <i class="fas fa-chevron-right text-muted small"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/payroll_section.php'; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
