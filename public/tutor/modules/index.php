<?php
// Tutor course builder. Without course_id: the courses of the tutor's assigned students.
// With course_id: that course's overview, intro video, instructions, resources and modules — owned by this tutor
// and shown to every student of the course assigned to them (including students assigned later).
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/lms_files.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/TutorCourseModel.php';
require_once __DIR__ . '/../../../app/v1/services/PortalNotifications.php';

use Models\Connection;
use Models\TutorModel;
use Models\TutorCourseModel;

Middleware::requireTutor();
$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$tutor = $tutorModel->getByUserId((int) $_SESSION['user_id']);
if (!$tutor) die('Tutor profile not found.');
$tutorId = (int) $tutor['id'];
$ownerId = (int) $tutor['user_id']; // course materials are keyed by the tutor's users.id
$tc = new TutorCourseModel($pdo);

$courseId = (int) ($_GET['course_id'] ?? 0);

/* ---------- Course list ---------- */
if (!$courseId) {
    $st = $pdo->prepare(
        "SELECT c.id, c.title, COUNT(DISTINCT r.id) AS students,
                (SELECT COUNT(*) FROM course_modules m WHERE m.course_id = c.id AND m.tutor_id = :t1) AS modules
         FROM tutor_students ts
         JOIN registrations r ON r.user_id = ts.user_id AND r.status = 'approved'
         JOIN courses c ON c.id = r.course_id
         WHERE ts.tutor_id = :t2
         GROUP BY c.id, c.title ORDER BY c.title"
    );
    $st->execute([':t1' => $ownerId, ':t2' => $tutorId]);
    $courses = $st->fetchAll();
    $page_title = 'Course Builder';
    ob_start();
    ?>
    <p class="text-muted">Build your own course content once per course. Every student of that course assigned to you sees it — including students assigned to you later.</p>
    <?php if (!$courses): ?>
        <div class="card-tutor"><p class="p-3 text-muted mb-0">You have no assigned students yet. Courses appear here once the admin assigns students to you.</p></div>
    <?php else: ?>
        <div class="row g-3">
        <?php foreach ($courses as $c): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card-tutor h-100"><div class="p-3 d-flex flex-column h-100">
                    <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($c['title']); ?></h5>
                    <div class="small text-muted mb-3"><i class="fas fa-user-graduate me-1"></i><?php echo (int) $c['students']; ?> student<?php echo $c['students'] == 1 ? '' : 's'; ?>
                        · <i class="fas fa-layer-group ms-1 me-1"></i><?php echo (int) $c['modules']; ?> module<?php echo $c['modules'] == 1 ? '' : 's'; ?></div>
                    <a href="?course_id=<?php echo (int) $c['id']; ?>" class="btn btn-brand mt-auto"><i class="fas fa-pen me-1"></i>Manage course</a>
                </div></div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif;
    $content = ob_get_clean();
    require __DIR__ . '/../../templates/tutor/layout.php';
    exit;
}

/* ---------- One course ---------- */
if (!$tutorModel->tutorTeachesCourse($tutorId, $courseId)) {
    SessionModel::setFlash('error', 'You have no students in that course.');
    header('Location: index.php');
    exit;
}
$st = $pdo->prepare("SELECT id, title FROM courses WHERE id = ?");
$st->execute([$courseId]);
$course = $st->fetch();
$self = 'index.php?course_id=' . $courseId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        header('Location: ' . $self); exit;
    }
    $action = $_POST['action'] ?? '';
    $anchor = '';
    if ($action === 'overview') {
        $videoPath = null;
        if (!empty($_FILES['video']['name'])) {
            $up = upload_video($_FILES['video'], dirname(__DIR__, 2) . '/uploads/tutor_courses/videos', 'uploads/tutor_courses/videos');
            if (!$up['ok']) { SessionModel::setFlash('error', htmlspecialchars($up['error'])); header('Location: ' . $self); exit; }
            $videoPath = $up['path'];
        }
        $tc->saveOverview($ownerId, $courseId, trim($_POST['overview'] ?? ''), trim($_POST['instructions'] ?? ''),
            lms_clean_url($_POST['video_url'] ?? ''), $videoPath, !empty($_POST['remove_video']));
        SessionModel::setFlash('success', 'Course overview saved.');
    } elseif ($action === 'add_resource') {
        $title = trim($_POST['title'] ?? '');
        $link = lms_clean_url($_POST['link_url'] ?? '');
        $file = null;
        if (!empty($_FILES['file']['name'])) {
            $up = lms_store_upload($_FILES['file'], 'tutor_courses/resources', LMS_MATERIAL_EXTS);
            if (!$up['ok']) { SessionModel::setFlash('error', htmlspecialchars($up['error'])); header('Location: ' . $self . '#resources'); exit; }
            $file = $up['path'];
        }
        if ($title === '' || (!$file && !$link)) {
            SessionModel::setFlash('error', 'Give the resource a title and attach a file or a link.');
        } else {
            $tc->addResource($ownerId, $courseId, $title, $file, $link);
            SessionModel::setFlash('success', 'Resource added.');
        }
        $anchor = '#resources';
    } elseif ($action === 'delete_resource') {
        $tc->deleteResource($ownerId, (int) $_POST['resource_id']);
        SessionModel::setFlash('success', 'Resource removed.');
        $anchor = '#resources';
    } elseif ($action === 'save_module') {
        if (trim($_POST['title'] ?? '') === '') {
            SessionModel::setFlash('error', 'Give the module a title.');
        } else {
            $_POST['video_url'] = lms_clean_url($_POST['video_url'] ?? '') ?? '';
            $mid = $tc->saveModule($ownerId, $courseId, (int) ($_POST['module_id'] ?? 0) ?: null, $_POST);
            $tc->refreshCourseForAll($ownerId, $courseId);
            SessionModel::setFlash('success', 'Module saved.');
            if (empty($_POST['module_id'])) {
                PortalNotifications::courseStudents($pdo, $ownerId, $courseId, 'module', 'New module: ' . trim($_POST['title']),
                    $course['title'], 'students/course/tutor_module.php?id=' . $mid, PortalNotifications::photoOf($pdo, $ownerId));
                header('Location: manage.php?id=' . $mid); exit;
            }
        }
        $anchor = '#modules';
    } elseif ($action === 'delete_module') {
        $tc->deleteModule($ownerId, (int) $_POST['module_id']);
        $tc->refreshCourseForAll($ownerId, $courseId);
        SessionModel::setFlash('success', 'Module deleted.');
        $anchor = '#modules';
    }
    header('Location: ' . $self . $anchor);
    exit;
}

$overview = $tc->getOverview($ownerId, $courseId);
$resources = $tc->getResources($ownerId, $courseId);
$modules = $tc->getModules($ownerId, $courseId);
$counts = [];
if ($modules) {
    $ids = implode(',', array_map(fn($m) => (int) $m['id'], $modules));
    foreach ($pdo->query("SELECT module_id, SUM(type = 'material') AS materials, SUM(type <> 'material') AS questions FROM module_contents WHERE module_id IN ($ids) GROUP BY module_id") as $r) {
        $counts[(int) $r['module_id']] = $r;
    }
}

$page_title = $course['title'] . ' — Course Builder';
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-2">← All my courses</a>

<div class="card-tutor mb-4">
    <div class="head"><i class="fas fa-info-circle me-1"></i>Course overview, intro video &amp; instructions</div>
    <form method="POST" enctype="multipart/form-data" class="p-3">
        <?php echo csrf_field(); ?><input type="hidden" name="action" value="overview">
        <div class="mb-3">
            <label class="form-label fw-semibold">Overview</label>
            <textarea name="overview" class="form-control" rows="4" placeholder="What this course covers, what students will be able to do at the end…"><?php echo htmlspecialchars((string) $overview['overview']); ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Instructions for students</label>
            <textarea name="instructions" class="form-control" rows="3" placeholder="How to work through the modules, how answers are marked, deadlines…"><?php echo htmlspecialchars((string) $overview['instructions']); ?></textarea>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Intro video — upload</label>
                <input type="file" name="video" class="form-control" accept="video/*">
                <?php if (!empty($overview['video_path'])): ?>
                    <div class="small mt-1"><a href="<?php echo htmlspecialchars(settings_asset_url($overview['video_path'])); ?>" target="_blank">Current video</a>
                        <label class="ms-2 text-danger"><input type="checkbox" name="remove_video" value="1"> remove</label></div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">…or a YouTube / Vimeo link</label>
                <input type="url" name="video_url" class="form-control" placeholder="https://youtube.com/watch?v=…" value="<?php echo htmlspecialchars((string) $overview['video_url']); ?>">
            </div>
        </div>
        <button class="btn btn-brand"><i class="fas fa-save me-1"></i>Save overview</button>
    </form>
</div>

<div class="card-tutor mb-4" id="resources">
    <div class="head"><i class="fas fa-folder-open me-1"></i>Course resources</div>
    <div class="p-3">
        <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="add_resource">
            <div class="col-md-4"><label class="form-label small">Title</label><input name="title" class="form-control form-control-sm" required></div>
            <div class="col-md-3"><label class="form-label small">File</label><input type="file" name="file" class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label small">or Link</label><input type="url" name="link_url" class="form-control form-control-sm" placeholder="https://"></div>
            <div class="col-md-2"><button class="btn btn-brand btn-sm w-100">Add</button></div>
        </form>
        <?php if (!$resources): ?><p class="text-muted small mb-0">No resources yet.</p><?php else: ?>
        <ul class="list-group">
            <?php foreach ($resources as $r): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span><i class="fas <?php echo $r['file_path'] ? 'fa-file' : 'fa-link'; ?> me-2 text-muted"></i>
                    <a href="<?php echo htmlspecialchars($r['file_path'] ? settings_asset_url($r['file_path']) : $r['link_url']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($r['title']); ?></a></span>
                <form method="POST" class="m-0" onsubmit="return confirm('Remove this resource?')"><?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="delete_resource"><input type="hidden" name="resource_id" value="<?php echo (int) $r['id']; ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4" id="modules">
    <div class="col-lg-4">
        <div class="card-tutor">
            <div class="head">New module</div>
            <form method="POST" class="p-3">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="save_module">
                <div class="mb-2"><label class="form-label small">Title</label><input name="title" class="form-control" required></div>
                <div class="mb-2"><label class="form-label small">Short description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                <div class="mb-3"><label class="form-label small">Instructions</label><textarea name="instructions" class="form-control" rows="2"></textarea></div>
                <button class="btn btn-brand w-100">Create &amp; add content</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card-tutor">
            <div class="head">Modules (<?php echo count($modules); ?>)</div>
            <?php if (!$modules): ?><p class="p-4 text-center text-muted mb-0">No modules yet. Create your first module.</p><?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($modules as $i => $m): $cnt = $counts[(int) $m['id']] ?? ['materials' => 0, 'questions' => 0]; ?>
                <div class="list-group-item p-3">
                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                        <div>
                            <span class="badge bg-light text-dark border me-1"><?php echo $i + 1; ?></span>
                            <strong><?php echo htmlspecialchars($m['title']); ?></strong>
                            <?php if ($m['description']): ?><div class="small text-muted mt-1"><?php echo htmlspecialchars($m['description']); ?></div><?php endif; ?>
                            <div class="small text-muted mt-1"><i class="fas fa-file-alt me-1"></i><?php echo (int) $cnt['materials']; ?> materials · <i class="fas fa-question-circle ms-1 me-1"></i><?php echo (int) $cnt['questions']; ?> questions</div>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="manage.php?id=<?php echo (int) $m['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-pen me-1"></i>Edit</a>
                            <form method="POST" class="m-0" onsubmit="return confirm('Delete this module with all its materials, questions and student answers?')"><?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_module"><input type="hidden" name="module_id" value="<?php echo (int) $m['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
