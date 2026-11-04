<?php
require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../../app/v1/models/TutorModel.php';
require_once __DIR__ . '/../../../app/v1/services/StudentLetterService.php';

use Models\Connection;
use Models\TutorModel;

Middleware::requireTutor();
$pdo = Connection::getInstance()->getConnection();
$me = (int) $_SESSION['user_id'];
$tutor = (new TutorModel($pdo))->getByUserId($me);

// Letter of recommendation topics (courses under 12 months): only the tutor of the course; sl_course_topics_save() checks and logs.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'letter_topics') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $_SESSION['tc_flash'] = ['danger', 'Security check failed. Please refresh and try again.'];
    } else {
        $r = sl_course_topics_save($pdo, (int) ($_POST['course_id'] ?? 0), (string) ($_POST['topics'] ?? ''), $me);
        $_SESSION['tc_flash'] = $r['ok'] ? ['success', 'Topics saved. Letters made from now on use them.'] : ['danger', $r['error']];
    }
    header('Location: ' . url('tutor/courses/index.php'));
    exit;
}
$flash = $_SESSION['tc_flash'] ?? null;
unset($_SESSION['tc_flash']);
$letterOn = sl_installed($pdo);
$noTopics = $letterOn ? sl_courses_missing_topics($pdo, $tutor['courses'] ?? []) : [];

$page_title = 'My Courses';
ob_start();
?>
<?php if ($flash): ?><div class="alert alert-<?php echo $flash[0]; ?>"><?php echo htmlspecialchars($flash[1]); ?></div><?php endif; ?>
<div class="row g-3">
<?php foreach ($tutor['courses'] as $c):
    $full = $letterOn ? sl_course($pdo, (int) $c['id']) : null;
    $short = $full && !sl_long_course($full); ?>
<div class="col-md-6"><div class="card-tutor p-4">
    <h5><?php echo htmlspecialchars($c['title']); ?></h5>
    <p class="text-muted small mb-3">Abbreviation: <?php echo htmlspecialchars($c['abbreviation']); ?></p>
    <a href="curriculum.php?course_id=<?php echo (int)$c['id']; ?>" class="btn btn-brand btn-sm"><i class="fas fa-sitemap me-1"></i> Manage Units & Content</a>
    <?php if ($short): ?>
      <?php if (in_array((int) $c['id'], $noTopics, true)): ?>
        <div class="alert alert-warning small mt-3 mb-2"><i class="fas fa-bell me-1"></i>Add at least one topic for the letters of recommendation of this course.</div>
      <?php endif; ?>
      <form method="post" class="mt-3"><?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="letter_topics"><input type="hidden" name="course_id" value="<?php echo (int) $c['id']; ?>">
        <label class="form-label small fw-semibold mb-1">Letter of recommendation topics (up to <?php echo SL_MAX_TOPICS; ?>, one per line)</label>
        <textarea name="topics" rows="4" maxlength="600" class="form-control form-control-sm" placeholder="e.g. HTML and CSS&#10;JavaScript&#10;Responsive design"><?php echo htmlspecialchars(implode("\n", sl_course_topics($pdo, (int) $c['id']))); ?></textarea>
        <div class="small text-muted mt-1">Every letter of this course joins them into "hands-on training in …". A change only affects letters made after it.</div>
        <button class="btn btn-sm btn-outline-success mt-2">Save topics</button>
      </form>
    <?php endif; ?>
</div></div>
<?php endforeach; ?>
<?php if (empty($tutor['courses'])): ?><p class="text-muted">No courses assigned yet. Contact admin.</p><?php endif; ?>
</div>
<?php $content = ob_get_clean(); require __DIR__ . '/../../templates/tutor/layout.php';
