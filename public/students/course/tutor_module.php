<?php
// One module of the tutor's course: materials, and questions answered by text, multiple choice, file/zip or GitHub link.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/lms_files.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/StudentModel.php';
require_once __DIR__ . '/../../../app/v1/models/TutorCourseModel.php';
require_once __DIR__ . '/../../../app/v1/services/PortalNotifications.php';

use Models\Connection;
use Models\StudentModel;
use Models\TutorCourseModel;

Middleware::requireStudent();
$pdo = Connection::getInstance()->getConnection();
$reg = (new StudentModel($pdo))->getByUserId((int) $_SESSION['user_id']);
if (!$reg || $reg['portal_locked']) { header('Location: index.php'); exit(); }
$portal_locked = false;

$tc = new TutorCourseModel($pdo);
$tutor = $tc->tutorForRegistration($reg);
$moduleId = (int) ($_GET['id'] ?? 0);
$module = $tutor ? $tc->getModule($moduleId, (int) $tutor['user_id']) : null;
if (!$module || (int) $module['course_id'] !== (int) $reg['course_id']) { header('Location: index.php'); exit(); }
$regId = (int) $reg['id'];
$self = 'tutor_module.php?id=' . $moduleId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        header('Location: ' . $self); exit();
    }
    if (($_POST['action'] ?? '') === 'mark_done') {
        if (!array_filter($tc->getItems($moduleId), [TutorCourseModel::class, 'isGradable'])) {
            $tc->markModuleDone($regId, $moduleId);
            PortalNotifications::notify($pdo, (int) $module['tutor_id'], 'module_done', $reg['full_name'] . ' finished a module',
                $module['title'], 'tutor/students/view.php?id=' . $regId, PortalNotifications::photoOf($pdo, (int) $reg['user_id']));
            SessionModel::setFlash('success', 'Module marked as done.');
        }
        header('Location: ' . $self); exit();
    }
    $item = $tc->getItem((int) ($_POST['content_id'] ?? 0));
    if (!$item || (int) $item['module_id'] !== $moduleId) { header('Location: ' . $self); exit(); }

    $filePath = null;
    if (($item['answer_mode'] ?? '') === 'file' && !empty($_FILES['answer_file']['name'])) {
        $up = lms_store_upload($_FILES['answer_file'], 'submissions/modules', LMS_ANSWER_EXTS);
        if (!$up['ok']) { SessionModel::setFlash('error', htmlspecialchars($up['error'])); header('Location: ' . $self . '#item-' . (int) $item['id']); exit(); }
        $filePath = $up['path'];
    }
    $r = $tc->submit($regId, $item, $_POST['answer'] ?? null, $filePath, $_POST['github_url'] ?? null);
    if ($r['ok']) {
        $subId = $tc->submissionsFor($regId, [(int) $item['id']])[(int) $item['id']]['id'] ?? 0;
        PortalNotifications::notify($pdo, (int) $module['tutor_id'], 'submission',
            $reg['full_name'] . (empty($r['auto_graded']) ? ' submitted an answer' : ' answered a quiz (' . ($r['correct'] ? 'correct' : 'wrong') . ')'),
            $module['title'] . ' · ' . $item['title'],
            empty($r['auto_graded']) ? 'tutor/submissions/index.php#sub-' . (int) $subId : 'tutor/students/view.php?id=' . $regId,
            PortalNotifications::photoOf($pdo, (int) $reg['user_id']));
    }
    if (!$r['ok']) {
        SessionModel::setFlash('error', htmlspecialchars($r['error']));
    } elseif (!empty($r['auto_graded'])) {
        SessionModel::setFlash($r['correct'] ? 'success' : 'error', $r['correct'] ? 'Correct! Marks awarded.' : 'Not quite — that answer is incorrect.');
    } else {
        SessionModel::setFlash('success', 'Answer submitted. Your tutor will mark it.');
    }
    header('Location: ' . $self . '#item-' . (int) $item['id']); exit();
}

$items = $tc->getItems($moduleId);
$gradable = array_filter($items, [TutorCourseModel::class, 'isGradable']);
$subs = $tc->submissionsFor($regId, array_map(fn($i) => (int) $i['id'], $gradable));
$isDone = $tc->isModuleDone($regId, $moduleId);
$fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');

$page_title = 'Module: ' . $module['title'];
ob_start();
?>
<a href="index.php" class="btn btn-link btn-sm ps-0 mb-3">← Back to course</a>

<div class="card-student mb-4">
    <div class="card-head d-flex justify-content-between align-items-center">
        <span><?php echo htmlspecialchars($module['title']); ?></span>
        <?php if ($isDone): ?><span class="badge bg-light text-success"><i class="fas fa-check me-1"></i>Completed</span><?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($module['description']): ?><p class="text-muted"><?php echo nl2br(htmlspecialchars($module['description'])); ?></p><?php endif; ?>
        <?php if (!empty($module['instructions'])): ?><div class="bg-light border rounded p-3 small mb-3" style="white-space:pre-wrap"><strong>Instructions:</strong> <?php echo htmlspecialchars($module['instructions']); ?></div><?php endif; ?>
        <?php if ($embed = lms_video_embed($module['video_url'] ?? '')): ?>
            <div class="ratio ratio-16x9"><iframe src="<?php echo htmlspecialchars($embed); ?>" allowfullscreen loading="lazy"></iframe></div>
        <?php elseif (!empty($module['video_url'])): ?>
            <a href="<?php echo htmlspecialchars($module['video_url']); ?>" target="_blank" rel="noopener"><i class="fas fa-play-circle me-1"></i>Watch the module video</a>
        <?php endif; ?>
        <?php if ($gradable): ?><p class="small text-muted mb-0 mt-2">This module is completed once all <?php echo count($gradable); ?> question<?php echo count($gradable) > 1 ? 's are' : ' is'; ?> marked (<?php echo count(array_filter($subs, fn($s) => $s['status'] === 'graded')); ?> marked so far).</p><?php endif; ?>
    </div>
</div>

<?php if (!$items): ?><div class="alert alert-info">Your tutor has not added content to this module yet.</div><?php endif; ?>

<?php foreach ($items as $c): $q = TutorCourseModel::isGradable($c); $sub = $subs[(int) $c['id']] ?? null; $mode = $c['answer_mode'] ?: 'text'; ?>
<div class="card-student mb-4" id="item-<?php echo (int) $c['id']; ?>">
    <div class="card-header bg-light border-bottom p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0 fw-bold"><i class="fas <?php echo $q ? 'fa-question-circle text-info' : 'fa-file-alt text-primary'; ?> me-2"></i><?php echo htmlspecialchars($c['title']); ?></h6>
        <?php if ($q): ?><div>
            <span class="badge bg-secondary"><?php echo (int) ($c['max_score'] ?: 10); ?> marks</span>
            <?php if (!$sub): ?><span class="badge bg-warning text-dark">Not answered</span>
            <?php elseif ($sub['status'] === 'graded'): ?><span class="badge bg-success">Marked: <?php echo $fmt($sub['marks']); ?>/<?php echo (int) ($c['max_score'] ?: 10); ?></span>
            <?php else: ?><span class="badge bg-info text-dark">Awaiting marking</span><?php endif; ?>
        </div><?php endif; ?>
    </div>
    <div class="card-body p-4">
        <?php if ($c['content']): ?><div style="white-space:pre-wrap;line-height:1.6"><?php echo htmlspecialchars($c['content']); ?></div><?php endif; ?>
        <?php if ($c['file_path'] || $c['link_url']): ?>
            <div class="mt-3">
                <?php if ($c['file_path']): ?><a href="<?php echo htmlspecialchars(settings_asset_url($c['file_path'])); ?>" target="_blank" class="btn btn-sm btn-outline-secondary me-2"><i class="fas fa-download me-1"></i>Download file</a><?php endif; ?>
                <?php if ($embed = lms_video_embed($c['link_url'])): ?><div class="ratio ratio-16x9 mt-2"><iframe src="<?php echo htmlspecialchars($embed); ?>" allowfullscreen loading="lazy"></iframe></div>
                <?php elseif ($c['link_url']): ?><a href="<?php echo htmlspecialchars($c['link_url']); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-link me-1"></i>Open link</a><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($q && $c['due_date']): ?><div class="small text-danger mt-2"><i class="fas fa-clock me-1"></i>Due <?php echo date('d M Y', strtotime($c['due_date'])); ?></div><?php endif; ?>

        <?php if ($q): ?>
            <hr class="my-4">
            <?php if ($sub && $sub['status'] === 'graded'): ?>
                <div class="bg-light p-3 rounded border">
                    <h6 class="fw-bold text-success mb-2"><i class="fas fa-check-circle me-1"></i>Your answer</h6>
                    <?php require __DIR__ . '/_answer_view.php'; ?>
                    <div class="mt-3 pt-3 border-top"><strong>Grade:</strong> <?php echo $fmt($sub['marks']); ?> / <?php echo (int) ($c['max_score'] ?: 10); ?>
                        <?php if ($sub['feedback']): ?><div class="text-muted mt-1" style="white-space:pre-wrap"><?php echo htmlspecialchars($sub['feedback']); ?></div><?php endif; ?></div>
                </div>
            <?php else: ?>
                <?php if ($sub): ?><div class="alert alert-info small py-2">Submitted <?php echo date('d M Y H:i', strtotime($sub['submitted_at'])); ?>. You can change your answer until your tutor marks it.</div>
                    <div class="mb-3"><?php require __DIR__ . '/_answer_view.php'; ?></div><?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?><input type="hidden" name="content_id" value="<?php echo (int) $c['id']; ?>">
                    <?php if ($mode === 'choice'): ?>
                        <?php foreach ($c['options'] as $oi => $opt): ?>
                            <div class="form-check mb-2"><input class="form-check-input" type="radio" name="answer" value="<?php echo $oi; ?>" id="o<?php echo (int) $c['id'] . '_' . $oi; ?>" required>
                                <label class="form-check-label" for="o<?php echo (int) $c['id'] . '_' . $oi; ?>"><?php echo htmlspecialchars($opt); ?></label></div>
                        <?php endforeach; ?>
                        <div class="small text-muted mb-2">Marked instantly — you get one attempt.</div>
                    <?php elseif ($mode === 'file'): ?>
                        <label class="form-label fw-semibold">Upload your work</label>
                        <input type="file" name="answer_file" class="form-control mb-1" required accept=".<?php echo implode(',.', LMS_ANSWER_EXTS); ?>">
                        <div class="small text-muted mb-2">To submit a folder, compress it to a .zip first (right-click → Compress / Send to → Compressed folder). Max 50 MB.</div>
                        <textarea name="answer" class="form-control mb-2" rows="2" placeholder="Note to your tutor (optional)"></textarea>
                    <?php elseif ($mode === 'github'): ?>
                        <label class="form-label fw-semibold">GitHub repository link</label>
                        <input type="url" name="github_url" class="form-control mb-2" required placeholder="https://github.com/username/project" value="<?php echo htmlspecialchars((string) ($sub['github_url'] ?? '')); ?>">
                        <textarea name="answer" class="form-control mb-2" rows="2" placeholder="Note to your tutor (optional)"></textarea>
                    <?php else: ?>
                        <label class="form-label fw-semibold">Your answer</label>
                        <textarea name="answer" class="form-control mb-2" rows="5" required><?php echo htmlspecialchars((string) ($sub['answer_text'] ?? '')); ?></textarea>
                    <?php endif; ?>
                    <button class="btn btn-brand w-100"><?php echo $sub ? 'Update answer' : 'Submit answer'; ?></button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if ($items && !$gradable && !$isDone): ?>
    <form method="POST" class="text-center mb-4"><?php echo csrf_field(); ?><input type="hidden" name="action" value="mark_done">
        <button class="btn btn-success btn-lg"><i class="fas fa-check me-1"></i>I have finished this module</button></form>
<?php endif; ?>

<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/students/layout.php';
