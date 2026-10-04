<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/CurriculumModel.php';
require_once __DIR__ . '/../../../app/v1/models/ProgressModel.php';
require_once __DIR__ . '/../../../app/v1/models/TutorCourseModel.php';
require_once __DIR__ . '/../../../app/v1/helpers/lms_files.php';
require_once __DIR__ . '/../../../app/v1/services/CertificateService.php';

use Models\Connection;
use Models\StudentModel;
use Models\TutorModel;
use Models\CurriculumModel;
use Models\ProgressModel;
use Models\TutorCourseModel;

Middleware::requireStudent();
$pdo = Connection::getInstance()->getConnection();
$studentModel = new StudentModel($pdo);
$tutorModel = new TutorModel($pdo);
$curriculum = new CurriculumModel($pdo);
$progressModel = new ProgressModel($pdo);
$userId = (int)$_SESSION['user_id'];

$reg = $studentModel->getByUserId($userId);
if (!$reg) { header('Location: ' . url('students/dashboard/index.php')); exit(); }
$portal_locked = (bool)$reg['portal_locked'];

$tutors = $tutorModel->getForCourse((int)$reg['course_id']);
$units = $curriculum->getCourseTree((int)$reg['course_id']);
$progress = $progressModel->getProgress((int)$reg['id'], (int)$reg['course_id']);
if (empty($progress) && !empty($units)) {
    $progressModel->initializeForRegistration((int)$reg['id'], (int)$reg['course_id']);
    $progress = $progressModel->getProgress((int)$reg['id'], (int)$reg['course_id']);
}
$progressMap = [];
foreach ($progress as $p) $progressMap[(int)$p['unit_id']] = $p;

// The assigned tutor's own course: overview, video, instructions, resources and modules with progress.
$tc = new TutorCourseModel($pdo);
$mp = $tc->progressFor($reg);
$courseTutorId = $mp['tutor'] ? (int) $mp['tutor']['user_id'] : 0;
$overview = $courseTutorId ? $tc->getOverview($courseTutorId, (int) $reg['course_id']) : null;
$tutorResources = $courseTutorId ? $tc->getResources($courseTutorId, (int) $reg['course_id']) : [];
$st = $pdo->prepare("SELECT 1 FROM certificates WHERE registration_id = ? AND status <> 'withdrawn' LIMIT 1");
$st->execute([(int) $reg['id']]);
$hasCert = (bool) $st->fetchColumn();

$portal_locked = (bool)$reg['portal_locked'];
$page_title = 'My Course';
ob_start();
?>

<?php if (!empty($tutors)): ?>
<div class="card-student mb-4"><div class="card-head"><i class="fas fa-chalkboard-teacher me-2"></i>Your Tutor(s)</div><div class="card-body">
<div class="row g-3">
<?php foreach ($tutors as $t): ?>
<div class="col-md-6 d-flex gap-3 align-items-start">
    <?php if (!empty($t['photo_path'])): ?>
        <img src="<?php echo htmlspecialchars($t['photo_path']); ?>" class="rounded-circle" width="72" height="72" style="object-fit:cover;">
    <?php else: ?>
        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width:72px;height:72px;"><i class="fas fa-user fa-2x"></i></div>
    <?php endif; ?>
    <div>
        <strong class="fs-5"><?php echo htmlspecialchars(trim(($t['first_name']??'').' '.($t['last_name']??'')) ?: 'Tutor'); ?></strong>
        <?php if ($t['bio']): ?><p class="text-muted small mb-1"><?php echo nl2br(htmlspecialchars($t['bio'])); ?></p><?php endif; ?>
        <?php if ($t['phone']): ?><small><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($t['phone']); ?></small><?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div></div></div>
<?php else: ?>
<div class="alert alert-info">A tutor will be assigned to your course soon.</div>
<?php endif; ?>

<?php if ($mp['total'] || $overview && (trim((string) $overview['overview']) !== '' || $overview['video_path'] || $overview['video_url'])): ?>
<div class="card-student mb-4"><div class="card-head"><i class="fas fa-chalkboard me-2"></i><?php echo htmlspecialchars($reg['course_title']); ?> — Course Overview</div><div class="card-body">
    <?php if ($hasCert): ?>
        <div class="alert alert-success"><i class="fas fa-award me-1"></i>Congratulations — you have finished this course! <a href="<?php echo url('students/certificates/index.php'); ?>" class="alert-link">Download your certificate</a>.</div>
    <?php elseif (!empty($reg['studies_completed_at'])): ?>
        <div class="alert alert-info"><i class="fas fa-check-circle me-1"></i>Studies completed. Your certificate is issued once your tutor confirms and your fee balance is zero.</div>
    <?php endif; ?>
    <?php if ($mp['total']): ?>
        <div class="d-flex justify-content-between small mb-1"><span><strong><?php echo $mp['done']; ?></strong> of <?php echo $mp['total']; ?> modules completed</span>
            <span><?php echo $mp['grade_percent'] !== null ? 'Grade so far: <strong>' . $mp['grade_percent'] . '%</strong>' : ''; ?></span></div>
        <div class="progress mb-3" style="height:10px"><div class="progress-bar bg-success" style="width:<?php echo $mp['percent']; ?>%"></div></div>
    <?php endif; ?>
    <?php if (!empty($overview['overview'])): ?><div class="mb-3" style="white-space:pre-wrap"><?php echo htmlspecialchars($overview['overview']); ?></div><?php endif; ?>
    <?php if (!empty($overview['video_path'])): ?>
        <video src="<?php echo htmlspecialchars(settings_asset_url($overview['video_path'])); ?>" controls class="w-100 rounded mb-3" style="max-height:420px;background:#000"></video>
    <?php elseif ($embed = lms_video_embed($overview['video_url'] ?? '')): ?>
        <div class="ratio ratio-16x9 mb-3"><iframe src="<?php echo htmlspecialchars($embed); ?>" allowfullscreen loading="lazy"></iframe></div>
    <?php elseif (!empty($overview['video_url'])): ?>
        <p><a href="<?php echo htmlspecialchars($overview['video_url']); ?>" target="_blank" rel="noopener"><i class="fas fa-play-circle me-1"></i>Watch the intro video</a></p>
    <?php endif; ?>
    <?php if (!empty($overview['instructions'])): ?>
        <div class="bg-light border rounded p-3 mb-3"><strong><i class="fas fa-list-ol me-1"></i>Instructions</strong><div class="mt-1 small" style="white-space:pre-wrap"><?php echo htmlspecialchars($overview['instructions']); ?></div></div>
    <?php endif; ?>
    <?php if ($tutorResources): ?>
        <strong class="d-block mb-2"><i class="fas fa-folder-open me-1"></i>Resources</strong>
        <ul class="list-unstyled small mb-0">
            <?php foreach ($tutorResources as $r): ?>
                <li class="mb-1"><i class="fas <?php echo $r['file_path'] ? 'fa-file-download' : 'fa-link'; ?> me-2 text-muted"></i><a href="<?php echo htmlspecialchars($r['file_path'] ? settings_asset_url($r['file_path']) : $r['link_url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($r['title']); ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div></div>
<?php endif; ?>

<?php if ($mp['modules']): ?>
<div class="card-student mb-4"><div class="card-head"><i class="fas fa-layer-group me-2"></i>Modules</div><div class="card-body p-0">
    <div class="list-group list-group-flush">
    <?php foreach ($mp['modules'] as $i => $tm): ?>
    <div class="list-group-item p-3 d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <div>
            <?php echo $tm['is_done'] ? '<i class="fas fa-check-circle text-success me-2"></i>' : '<i class="far fa-circle text-muted me-2"></i>'; ?>
            <strong>Module <?php echo $i + 1; ?>:</strong> <?php echo htmlspecialchars($tm['title']); ?>
            <?php if ($tm['is_done']): ?><span class="badge bg-success ms-1">Completed</span><?php endif; ?>
            <?php if ($tm['description']): ?><div class="text-muted small mt-1"><?php echo htmlspecialchars($tm['description']); ?></div><?php endif; ?>
        </div>
        <?php if (!$portal_locked): ?><a href="tutor_module.php?id=<?php echo (int) $tm['id']; ?>" class="btn btn-sm btn-brand"><?php echo $tm['is_done'] ? 'Review' : 'Open'; ?></a><?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
</div></div>
<?php endif; ?>

<?php if (!empty($units) || !$mp['modules']): ?>
<div class="card-student"><div class="card-head"><i class="fas fa-book-open me-2"></i>Course Units — <?php echo htmlspecialchars($reg['course_title']); ?></div><div class="card-body p-0">
<?php if (empty($units)): ?>
<p class="p-4 text-muted mb-0">Course content is being prepared by your tutor.</p>
<?php else: ?>
<div class="list-group list-group-flush">
<?php foreach ($units as $i => $unit):
    $pid = (int)$unit['id'];
    $p = $progressMap[$pid] ?? null;
    $unlocked = $p && $p['is_unlocked'];
    $completed = $p && $p['completed_at'];
?>
<div class="list-group-item d-flex justify-content-between align-items-center <?php echo !$unlocked ? 'bg-light text-muted' : ''; ?>">
    <div>
        <?php if (!$unlocked): ?><i class="fas fa-lock me-2"></i>
        <?php elseif ($completed): ?><i class="fas fa-check-circle text-success me-2"></i>
        <?php else: ?><i class="fas fa-play-circle text-primary me-2"></i><?php endif; ?>
        <strong>Unit <?php echo $i+1; ?>:</strong> <?php echo htmlspecialchars($unit['title']); ?>
        <?php if ($completed): ?><span class="badge bg-success ms-2">Completed</span>
        <?php elseif ($unlocked): ?><span class="badge bg-primary ms-2">Available</span>
        <?php else: ?><span class="badge bg-secondary ms-2">Locked — complete previous unit</span><?php endif; ?>
    </div>
    <?php if ($unlocked && !$portal_locked): ?>
    <a href="unit.php?id=<?php echo $pid; ?>" class="btn btn-sm btn-brand">Open</a>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
</div></div>

<?php endif; ?>

<?php $content = ob_get_clean();
require __DIR__ . '/../../templates/students/layout.php';
