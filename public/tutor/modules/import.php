<?php
// Tutor: build course content from one spreadsheet. Download the template, fill it in (Excel or Google Sheets), upload it,
// pick the courses: every unit, lesson, question and final assignment is added to all of them at once.
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/models/TutorCourseModel.php';
require_once __DIR__ . '/../../../app/v1/models/Session.php';
require_once __DIR__ . '/../../../app/v1/services/CourseImportService.php';

Middleware::requireTutor();
$pdo = \Models\Connection::getInstance()->getConnection();
$tutor = (new \Models\TutorModel($pdo))->getByUserId((int) $_SESSION['user_id']);
if (!$tutor) die('Tutor profile not found.');
$tutorId = (int) $tutor['id'];
$ownerId = (int) $tutor['user_id'];
$tc = new \Models\TutorCourseModel($pdo);
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

if (($_GET['download'] ?? '') === 'template') {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="dazu-course-template.xlsx"');
    echo CourseImportService::templateXlsx();
    exit;
}

$st = $pdo->prepare("SELECT c.id, c.title, COUNT(DISTINCT r.id) AS students
    FROM tutor_students ts JOIN registrations r ON r.user_id = ts.user_id AND r.status = 'approved' JOIN courses c ON c.id = r.course_id
    WHERE ts.tutor_id = ? GROUP BY c.id, c.title ORDER BY c.title");
$st->execute([$tutorId]);
$courses = $st->fetchAll(PDO::FETCH_ASSOC);
$installed = true;
try { $pdo->query("SELECT import_batch, unlock_after_all FROM course_modules LIMIT 0"); } catch (Throwable $ex) { $installed = false; }

$plan = null;
$errors = [];
$done = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $installed) {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Security check failed. Please try again.';
    } else {
        $f = $_FILES['sheet'] ?? null;
        $pick = array_values(array_intersect(array_map('intval', (array) ($_POST['courses'] ?? [])), array_map(fn($c) => (int) $c['id'], $courses)));
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) $errors[] = 'Choose the filled-in spreadsheet to upload.';
        elseif (!in_array(strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION)), ['csv', 'xlsx'], true)) $errors[] = 'Upload a .csv or .xlsx file (the template).';
        elseif ($f['size'] > 5 * 1048576) $errors[] = 'The file is bigger than 5 MB.';
        elseif (!$pick) $errors[] = 'Tick at least one course to add the content to.';
        if (!$errors) {
            try {
                $plan = CourseImportService::plan(CourseImportService::readFile($f['tmp_name'], (string) $f['name']));
                if (!$plan['ok']) {
                    $errors = $plan['errors'];
                } else {
                    $n = CourseImportService::apply($pdo, $tc, $ownerId, $pick, $plan, !empty($_POST['replace']), $tutorId);
                    foreach ($pick as $cid) { try { $tc->refreshCourseForAll($ownerId, (int) $cid); } catch (Throwable $ex) {} }
                    $done = $plan['counts'] + ['courses' => count($pick), 'modules' => $n];
                }
            } catch (Throwable $ex) {
                $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'The file could not be read. Use the template and save it as CSV or Excel.';
                error_log('course import: ' . $ex->getMessage());
            }
        }
    }
}

$page_title = 'Import a course';
ob_start();
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div><a href="index.php" class="small">‹ Course builder</a><h4 class="mb-0">Import a whole course from a spreadsheet</h4></div>
  <a href="?download=template" class="btn btn-brand"><i class="fas fa-file-excel me-1"></i>Download the template</a>
</div>
<?php if (!$installed): ?><div class="alert alert-warning">Ask the admin to run System Updates (update 133) first.</div><?php endif; ?>
<?php if ($done): ?>
  <div class="alert alert-success"><strong>Done.</strong> Added <?php echo (int) $done['units']; ?> unit<?php echo $done['units'] == 1 ? '' : 's'; ?>, <?php echo (int) $done['lessons']; ?> lessons,
    <?php echo (int) $done['questions']; ?> questions<?php echo $done['assignments'] ? ' and ' . (int) $done['assignments'] . ' final assignment' . ($done['assignments'] == 1 ? '' : 's') : ''; ?>
    to <?php echo (int) $done['courses']; ?> course<?php echo $done['courses'] == 1 ? '' : 's'; ?>. Every student of those courses sees it now. <a href="index.php">Open the course builder</a> to check or edit.</div>
<?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><strong>Nothing was saved.</strong> Fix these and upload again:<ul class="mb-0 mt-1"><?php foreach (array_slice($errors, 0, 25) as $er): ?><li><?php echo $e($er); ?></li><?php endforeach; ?></ul><?php if (count($errors) > 25): ?><div class="small">…and <?php echo count($errors) - 25; ?> more.</div><?php endif; ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card-tutor"><div class="head"><i class="fas fa-upload"></i>Upload</div><div class="p-3">
      <form method="POST" enctype="multipart/form-data"><?php echo csrf_field(); ?>
        <label class="form-label fw-semibold">1. Your filled-in spreadsheet (.xlsx or .csv)</label>
        <input type="file" name="sheet" accept=".csv,.xlsx" class="form-control mb-3" required>
        <label class="form-label fw-semibold">2. Add it to these courses</label>
        <?php if (!$courses): ?><p class="text-muted small">No students are assigned to you yet, so you have no courses.</p><?php endif; ?>
        <?php foreach ($courses as $c): ?>
          <label class="form-check d-flex justify-content-between border rounded-3 p-2 ps-5 mb-2"><span><input type="checkbox" class="form-check-input" name="courses[]" value="<?php echo (int) $c['id']; ?>"> <?php echo $e($c['title']); ?></span><span class="small text-muted"><?php echo (int) $c['students']; ?> students</span></label>
        <?php endforeach; ?>
        <?php if (count($courses) > 1): ?><button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="this.form.querySelectorAll('[name=\'courses[]\']').forEach(function(x){x.checked=true})">Tick all my courses</button><?php endif; ?>
        <label class="form-check mb-3"><input type="checkbox" class="form-check-input" name="replace" value="1"> Replace what an earlier upload added to these courses (modules you built by hand are kept)</label>
        <button class="btn btn-brand w-100" <?php echo $installed && $courses ? '' : 'disabled'; ?>><i class="fas fa-wand-magic-sparkles me-1"></i>Build the course</button>
      </form>
    </div></div>
  </div>
  <div class="col-lg-5">
    <div class="card-tutor"><div class="head"><i class="fas fa-circle-info"></i>How the template works</div><div class="p-3 small">
      <p>Open the template in Excel or Google Sheets. One row per item. The <strong>Type</strong> column says what the row is:</p>
      <ul>
        <li><strong>UNIT</strong> starts a unit (a module). Give it a number in <em>Unit</em>, a Title, a Description and <em>What students learn</em>. The <em>Subject</em> column is optional: if you put one (e.g. "Python Programming"), it is created in the course's Subjects (or matched to one that already exists) and the unit is tagged with it.</li>
        <li><strong>LESSON</strong> is reading material. Put the unit number, a Title and the <em>Lesson notes</em>. A video link is optional.</li>
        <li><strong>QUESTION</strong> is a question in that unit. <em>Answer type</em>: text, choice, file or link. For choice, write options like <code>A | B | C</code> and the number of the right one.</li>
        <li><strong>ASSIGNMENT</strong> is a final assignment. They are kept in a last module that <strong>opens only after the student finishes every other module</strong>.</li>
      </ul>
      <p class="mb-0">Save it as CSV or Excel and upload. If any row has a mistake nothing is saved and you are told which row to fix. Your hand-made modules and assignments stay as they are.</p>
    </div></div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/tutor/layout.php';
