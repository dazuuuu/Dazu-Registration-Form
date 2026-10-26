<?php
// Edit one module: its details, and its materials and questions (written, multiple choice, file/zip upload, GitHub link).
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
$tutor = (new TutorModel($pdo))->getByUserId((int) $_SESSION['user_id']);
if (!$tutor) die('Tutor profile not found.');
$tutorId = (int) $tutor['user_id']; // course materials are keyed by the tutor's users.id
$tc = new TutorCourseModel($pdo);

$moduleId = (int) ($_GET['id'] ?? 0);
$module = $tc->getModule($moduleId, $tutorId);
if (!$module) { SessionModel::setFlash('error', 'Module not found.'); header('Location: index.php'); exit; }
$self = 'manage.php?id=' . $moduleId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        SessionModel::setFlash('error', 'Security check failed. Please try again.');
        header('Location: ' . $self); exit;
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'save_module') {
        if (trim($_POST['title'] ?? '') === '') {
            SessionModel::setFlash('error', 'Give the module a title.');
        } else {
            $_POST['video_url'] = lms_clean_url($_POST['video_url'] ?? '') ?? '';
            $tc->saveModule($tutorId, (int) $module['course_id'], $moduleId, $_POST);
            SessionModel::setFlash('success', 'Module details saved.');
        }
    } elseif ($action === 'save_item') {
        $file = null;
        if (!empty($_FILES['file']['name'])) {
            $up = lms_store_upload($_FILES['file'], 'tutor_courses/materials', LMS_MATERIAL_EXTS);
            if (!$up['ok']) { SessionModel::setFlash('error', htmlspecialchars($up['error'])); header('Location: ' . $self); exit; }
            $file = $up['path'];
        }
        $_POST['link_url'] = lms_clean_url($_POST['link_url'] ?? '') ?? '';
        $r = $tc->saveItem($moduleId, (int) ($_POST['item_id'] ?? 0) ?: null, $_POST, $file, !empty($_POST['remove_file']));
        SessionModel::setFlash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Saved.' : htmlspecialchars($r['error']));
        if ($r['ok'] && empty($_POST['item_id'])) {
            $isQ = ($_POST['type'] ?? '') !== 'material';
            PortalNotifications::courseStudents($pdo, $tutorId, (int) $module['course_id'], $isQ ? 'assignment' : 'material',
                ($isQ ? 'New assignment: ' : 'New study material: ') . trim($_POST['title']), $module['title'],
                'students/course/tutor_module.php?id=' . $moduleId, PortalNotifications::photoOf($pdo, $tutorId));
        }
        if (!$r['ok'] && !empty($_POST['item_id'])) { header('Location: ' . $self . '&edit=' . (int) $_POST['item_id']); exit; }
    } elseif ($action === 'delete_item') {
        $tc->deleteItem($moduleId, (int) $_POST['item_id']);
        SessionModel::setFlash('success', 'Item deleted.');
    }
    if (in_array($action, ['save_item', 'delete_item'], true)) {
        $tc->refreshCourseForAll($tutorId, (int) $module['course_id']);
    }
    header('Location: ' . $self);
    exit;
}

$items = $tc->getItems($moduleId);
$editId = (int) ($_GET['edit'] ?? 0);
$edit = $editId ? ($tc->getItem($editId) ?: null) : null;
if ($edit && (int) $edit['module_id'] !== $moduleId) $edit = null;
$e = $edit ?? ['id' => 0, 'type' => 'question', 'answer_mode' => 'text', 'title' => '', 'content' => '', 'options' => [],
               'correct_option' => null, 'file_path' => null, 'link_url' => '', 'max_score' => 10, 'due_date' => null, 'order_index' => 0];
if ($e['type'] === 'assignment') $e['type'] = 'question';

$page_title = 'Module: ' . $module['title'];
ob_start();
?>
<a href="index.php?course_id=<?php echo (int) $module['course_id']; ?>#modules" class="btn btn-link btn-sm ps-0 mb-2">← Back to course</a>

<div class="card-tutor mb-4">
    <div class="head d-flex justify-content-between align-items-center">
        <span>Module details</span>
        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#modDetails">Edit</button>
    </div>
    <div class="p-3">
        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($module['title']); ?></h5>
        <?php if ($module['description']): ?><p class="text-muted mb-1"><?php echo nl2br(htmlspecialchars($module['description'])); ?></p><?php endif; ?>
        <form method="POST" class="collapse mt-3" id="modDetails">
            <?php echo csrf_field(); ?><input type="hidden" name="action" value="save_module">
            <div class="row g-2">
                <div class="col-md-9"><label class="form-label small">Title</label><input name="title" class="form-control" required value="<?php echo htmlspecialchars($module['title']); ?>"></div>
                <div class="col-md-3"><label class="form-label small">Position</label><input type="number" name="order_index" class="form-control" value="<?php echo (int) $module['order_index']; ?>"></div>
                <div class="col-12"><label class="form-label small">Description</label><textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars((string) $module['description']); ?></textarea></div>
                <div class="col-12"><label class="form-label small">Instructions</label><textarea name="instructions" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($module['instructions'] ?? '')); ?></textarea></div>
                <div class="col-12"><label class="form-label small">Video link (YouTube / Vimeo, optional)</label><input type="url" name="video_url" class="form-control" value="<?php echo htmlspecialchars((string) ($module['video_url'] ?? '')); ?>"></div>
            </div>
            <button class="btn btn-brand mt-3">Save details</button>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card-tutor" id="itemForm">
            <div class="head"><?php echo $edit ? 'Edit item' : 'Add material or question'; ?></div>
            <form method="POST" enctype="multipart/form-data" class="p-3">
                <?php echo csrf_field(); ?><input type="hidden" name="action" value="save_item"><input type="hidden" name="item_id" value="<?php echo (int) $e['id']; ?>">
                <div class="mb-2">
                    <label class="form-label small">Type</label>
                    <select name="type" id="itemType" class="form-select">
                        <option value="question" <?php echo $e['type'] === 'question' ? 'selected' : ''; ?>>Question</option>
                        <option value="material" <?php echo $e['type'] === 'material' ? 'selected' : ''; ?>>Study material</option>
                    </select>
                </div>
                <div class="mb-2 q-only">
                    <label class="form-label small">How students answer</label>
                    <select name="answer_mode" id="answerMode" class="form-select">
                        <?php foreach (TutorCourseModel::ANSWER_MODES as $k => $label): ?>
                            <option value="<?php echo $k; ?>" <?php echo $e['answer_mode'] === $k ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2"><label class="form-label small">Title</label><input name="title" class="form-control" required value="<?php echo htmlspecialchars($e['title']); ?>"></div>
                <div class="mb-2"><label class="form-label small"><span class="q-only">Question / instructions</span><span class="m-only">Content</span></label>
                    <textarea name="content" class="form-control" rows="4"><?php echo htmlspecialchars((string) $e['content']); ?></textarea></div>
                <div class="choice-only mb-2">
                    <label class="form-label small">Options (one per line)</label>
                    <textarea name="options" class="form-control" rows="4" placeholder="Option A&#10;Option B&#10;Option C"><?php echo htmlspecialchars(implode("\n", $e['options'])); ?></textarea>
                    <label class="form-label small mt-2">Correct option number</label>
                    <input type="number" name="correct_option" min="1" class="form-control" style="max-width:120px" value="<?php echo $e['correct_option'] !== null ? (int) $e['correct_option'] + 1 : ''; ?>">
                </div>
                <div class="row g-2 mb-2 q-only">
                    <div class="col-6"><label class="form-label small">Marks</label><input type="number" name="max_score" min="1" class="form-control" value="<?php echo (int) ($e['max_score'] ?: 10); ?>"></div>
                    <div class="col-6"><label class="form-label small">Due date (optional)</label><input type="date" name="due_date" class="form-control" value="<?php echo htmlspecialchars((string) $e['due_date']); ?>"></div>
                </div>
                <div class="mb-2"><label class="form-label small">Attach a file (optional)</label><input type="file" name="file" class="form-control">
                    <?php if ($e['file_path']): ?><div class="small mt-1"><a href="<?php echo htmlspecialchars(settings_asset_url($e['file_path'])); ?>" target="_blank">Current file</a>
                        <label class="ms-2 text-danger"><input type="checkbox" name="remove_file" value="1"> remove</label></div><?php endif; ?></div>
                <div class="mb-2"><label class="form-label small">Link (optional — video, article…)</label><input type="url" name="link_url" class="form-control" value="<?php echo htmlspecialchars((string) $e['link_url']); ?>"></div>
                <?php if ($edit): ?><div class="mb-2"><label class="form-label small">Position</label><input type="number" name="order_index" class="form-control" style="max-width:120px" value="<?php echo (int) $e['order_index']; ?>"></div><?php endif; ?>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-brand flex-grow-1"><?php echo $edit ? 'Save changes' : 'Add to module'; ?></button>
                    <?php if ($edit): ?><a href="<?php echo $self; ?>" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card-tutor">
            <div class="head">Module content (<?php echo count($items); ?>)</div>
            <?php if (!$items): ?><p class="p-4 text-center text-muted mb-0">Nothing added yet.</p><?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($items as $i => $c): $q = TutorCourseModel::isGradable($c); ?>
                <div class="list-group-item p-3 <?php echo $editId === (int) $c['id'] ? 'bg-light' : ''; ?>">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <i class="fas <?php echo $q ? 'fa-question-circle text-info' : 'fa-file-alt text-primary'; ?> me-1"></i>
                            <strong><?php echo htmlspecialchars($c['title']); ?></strong>
                            <span class="badge bg-light text-dark border ms-1"><?php echo $q ? htmlspecialchars(TutorCourseModel::ANSWER_MODES[$c['answer_mode']] ?? 'Written answer') : 'Material'; ?></span>
                            <?php if ($q): ?><span class="badge bg-secondary ms-1"><?php echo (int) ($c['max_score'] ?: 10); ?> marks</span><?php endif; ?>
                        </div>
                        <div class="d-flex gap-1 flex-shrink-0">
                            <a href="<?php echo $self; ?>&edit=<?php echo (int) $c['id']; ?>#itemForm" class="btn btn-sm btn-outline-primary"><i class="fas fa-pen"></i></a>
                            <form method="POST" class="m-0" onsubmit="return confirm('Delete this item?')"><?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_item"><input type="hidden" name="item_id" value="<?php echo (int) $c['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                        </div>
                    </div>
                    <?php if ($c['content']): ?><div class="small text-muted mt-2" style="white-space:pre-wrap"><?php echo htmlspecialchars($c['content']); ?></div><?php endif; ?>
                    <?php if ($c['answer_mode'] === 'choice' && $q): ?>
                        <ol class="small mt-2 mb-0">
                            <?php foreach ($c['options'] as $oi => $opt): ?>
                                <li class="<?php echo $oi === (int) $c['correct_option'] ? 'text-success fw-semibold' : ''; ?>"><?php echo htmlspecialchars($opt); ?><?php echo $oi === (int) $c['correct_option'] ? ' ✓' : ''; ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                    <?php if ($c['file_path'] || $c['link_url']): ?>
                        <div class="small mt-2">
                            <?php if ($c['file_path']): ?><a href="<?php echo htmlspecialchars(settings_asset_url($c['file_path'])); ?>" target="_blank" class="me-3"><i class="fas fa-paperclip me-1"></i>File</a><?php endif; ?>
                            <?php if ($c['link_url']): ?><a href="<?php echo htmlspecialchars($c['link_url']); ?>" target="_blank" rel="noopener"><i class="fas fa-link me-1"></i>Link</a><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    var type = document.getElementById('itemType'), mode = document.getElementById('answerMode');
    function sync() {
        var q = type.value === 'question';
        document.querySelectorAll('.q-only').forEach(function (el) { el.style.display = q ? '' : 'none'; });
        document.querySelectorAll('.m-only').forEach(function (el) { el.style.display = q ? 'none' : ''; });
        document.querySelectorAll('.choice-only').forEach(function (el) { el.style.display = q && mode.value === 'choice' ? '' : 'none'; });
    }
    type.addEventListener('change', sync); mode.addEventListener('change', sync); sync();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
