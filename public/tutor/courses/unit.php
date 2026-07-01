<?php
require_once __DIR__ . '/../../../app/init.php';
require_once __DIR__ . '/../../../app/helpers/middleware.php';
require_once __DIR__ . '/../../../app/helpers/uploads.php';
require_once __DIR__ . '/../../../app/models/Connection.php';
require_once __DIR__ . '/../../../app/models/TutorModel.php';
require_once __DIR__ . '/../../../app/models/CurriculumModel.php';
require_once __DIR__ . '/../../../app/models/Session.php';

use Models\Connection;
use Models\TutorModel;
use Models\CurriculumModel;

Middleware::requireTutor();
$pdo = Connection::getInstance()->getConnection();
$tutorModel = new TutorModel($pdo);
$curriculum = new CurriculumModel($pdo);
$tutor = $tutorModel->getByUserId((int)$_SESSION['user_id']);
$unitId = (int)($_GET['id'] ?? 0);
$unit = $curriculum->getUnitById($unitId);
if (!$unit || !$tutorModel->tutorTeachesCourse((int)$tutor['id'], (int)$unit['course_id'])) {
    SessionModel::setFlash('error','Unit not found.'); header('Location: index.php'); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify($_POST['csrf_token'] ?? null)) {
    $a = $_POST['action'] ?? '';
    if ($a === 'update_unit') $curriculum->updateUnit($unitId, $_POST['title']??'', $_POST['description']??'');
    elseif ($a === 'add_subunit') $curriculum->createSubUnit($unitId, $_POST['title']??'', $_POST['description']??'');
    elseif ($a === 'add_topic') $curriculum->createTopic((int)$_POST['sub_unit_id'], $_POST['title']??'', $_POST['content']??'');
    elseif ($a === 'add_test') $curriculum->createTest($unitId, $_POST);
    elseif ($a === 'add_project') $curriculum->createProject($unitId, $_POST);
    elseif ($a === 'delete_test') $curriculum->deleteTest((int)$_POST['test_id']);
    elseif ($a === 'delete_project') $curriculum->deleteProject((int)$_POST['project_id']);
    SessionModel::setFlash('success','Saved.');
    header('Location: unit.php?id='.$unitId); exit();
}

$subUnits = $curriculum->getSubUnits($unitId);
$tests = $curriculum->getTests($unitId);
$projects = $curriculum->getProjects($unitId);
$page_title = $unit['title'];
ob_start();
?>
<a href="curriculum.php?course_id=<?php echo (int)$unit['course_id']; ?>" class="btn btn-link btn-sm ps-0 mb-3">← Back to units</a>
<div class="card-tutor mb-3"><div class="head">Unit Details</div><div class="p-3">
<form method="POST"><?php echo csrf_field(); ?><input type="hidden" name="action" value="update_unit">
<div class="mb-2"><input name="title" class="form-control" value="<?php echo htmlspecialchars($unit['title']); ?>" required></div>
<textarea name="description" class="form-control mb-2" rows="2"><?php echo htmlspecialchars($unit['description']??''); ?></textarea>
<button class="btn btn-sm btn-brand">Save Unit</button>
</form></div></div>

<div class="row g-3">
<div class="col-lg-6">
<div class="card-tutor"><div class="head">Sub-units & Topics</div><div class="p-3">
<form method="POST" class="row g-2 mb-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add_subunit">
<input name="title" class="form-control col" placeholder="New sub-unit title" required>
<button class="btn btn-sm btn-outline-success">+ Sub-unit</button>
</form>
<?php foreach ($subUnits as $su): ?>
<div class="border rounded p-2 mb-2">
<strong><?php echo htmlspecialchars($su['title']); ?></strong>
<?php foreach ($curriculum->getTopics((int)$su['id']) as $topic): ?>
<div class="ms-3 mt-2 p-2 bg-light rounded"><strong><?php echo htmlspecialchars($topic['title']); ?></strong><div class="small"><?php echo nl2br(htmlspecialchars(mb_substr($topic['content']??'',0,200))); ?></div></div>
<?php endforeach; ?>
<form method="POST" class="mt-2 ms-3"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add_topic"><input type="hidden" name="sub_unit_id" value="<?php echo (int)$su['id']; ?>">
<input name="title" class="form-control form-control-sm mb-1" placeholder="Topic title" required>
<textarea name="content" class="form-control form-control-sm mb-1" rows="2" placeholder="What to teach..."></textarea>
<button class="btn btn-sm btn-outline-primary">+ Topic</button>
</form>
</div>
<?php endforeach; ?>
</div></div>
</div>
<div class="col-lg-6">
<div class="card-tutor mb-3"><div class="head">Tests</div><div class="p-3">
<?php foreach ($tests as $t): ?>
<div class="d-flex justify-content-between border-bottom py-2">
<span><?php echo htmlspecialchars($t['title']); ?> <small class="text-muted">(<?php echo $t['max_marks']; ?> marks)</small></span>
<form method="POST" class="d-inline" onsubmit="return confirm('Delete?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_test"><input type="hidden" name="test_id" value="<?php echo (int)$t['id']; ?>"><button class="btn btn-sm btn-outline-danger">×</button></form>
</div>
<?php endforeach; ?>
<form method="POST" class="mt-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add_test">
<input name="title" class="form-control form-control-sm mb-1" placeholder="Test title" required>
<textarea name="instructions" class="form-control form-control-sm mb-1" rows="2" placeholder="Instructions"></textarea>
<input name="max_marks" type="number" class="form-control form-control-sm mb-1" value="100" placeholder="Max marks">
<div class="form-check mb-1"><input type="checkbox" name="is_required" value="1" class="form-check-input" checked id="reqT"><label for="reqT" class="form-check-label small">Required to proceed</label></div>
<button class="btn btn-sm btn-brand">+ Add Test</button>
</form></div></div>
<div class="card-tutor"><div class="head">Projects</div><div class="p-3">
<?php foreach ($projects as $p): ?>
<div class="d-flex justify-content-between border-bottom py-2">
<span><?php echo htmlspecialchars($p['title']); ?> <small class="text-muted">(<?php echo $p['max_marks']; ?> marks)</small></span>
<form method="POST" class="d-inline" onsubmit="return confirm('Delete?');"><?php echo csrf_field(); ?><input type="hidden" name="action" value="delete_project"><input type="hidden" name="project_id" value="<?php echo (int)$p['id']; ?>"><button class="btn btn-sm btn-outline-danger">×</button></form>
</div>
<?php endforeach; ?>
<form method="POST" class="mt-2"><?php echo csrf_field(); ?><input type="hidden" name="action" value="add_project">
<input name="title" class="form-control form-control-sm mb-1" placeholder="Project title" required>
<textarea name="instructions" class="form-control form-control-sm mb-1" rows="2" placeholder="Instructions"></textarea>
<input name="max_marks" type="number" class="form-control form-control-sm mb-1" value="100">
<div class="form-check mb-1"><input type="checkbox" name="is_required" value="1" class="form-check-input" checked id="reqP"><label for="reqP" class="form-check-label small">Required to proceed</label></div>
<button class="btn btn-sm btn-brand">+ Add Project</button>
</form></div></div>
</div>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
