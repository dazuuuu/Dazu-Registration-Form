<?php
// public/courses/view.php — Course apply page (program details + admission form)
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../app/v1/helpers/course_catalog.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../app/v1/services/CourseRegistrationHandler.php';

use Models\Connection;
use Models\CourseModel;
use Services\CourseRegistrationHandler;

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
$course = course_resolve_for_page($courseModel);
$courseId = $course ? (int) $course['id'] : course_share_parse_id();

if ($course) {
    $course = course_catalog_enrich($course);
}

if (!course_is_shareable($course)) {
    $page_title = 'Course not found';
    ob_start();
    echo '<div class="max-w-[1280px] mx-auto px-4 py-20 text-center"><p class="text-slate-600 mb-4">Course not found or inactive.</p><a href="' . htmlspecialchars(url('courses/index.php')) . '" class="text-green-600 font-bold">Browse courses</a></div>';
    $content = ob_get_clean();
    require __DIR__ . '/../templates/public/dazu_layout.php';
    exit;
}

$page_title = htmlspecialchars($course['title']) . ' — Apply | Dazu hub Institute';
$page_description = mb_strimwidth(strip_tags($course['overview'] ?? ''), 0, 160, '…');

$reg_success = false;
$reg_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $handler = new CourseRegistrationHandler($pdo);
    $result = $handler->process($_POST, $_FILES, $course, null);
    if ($result['ok']) {
        $reg_success = true;
    } else {
        $reg_error = $result['error'] ?? 'Registration failed.';
    }
}

$share_url = course_share_url((int) $course['id']);
$share_title = 'Apply for ' . $course['title'] . ' at Dazu hub Institute';

[$allCourses, $usingStatic] = dazu_load_courses($courseModel);
$allCourses = array_values(array_filter($allCourses, static function ($c) use ($usingStatic) {
    if ($usingStatic) {
        return true;
    }
    return !empty($c['is_active']) && (int) ($c['id'] ?? 0) > 0;
}));
$allCourses = array_map('course_catalog_enrich', $allCourses);

ob_start();
?>
<div class="bg-slate-50 min-h-screen py-10 md:py-16">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8">
    <a href="<?php echo url('courses/index.php'); ?>" class="mb-8 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-green-600">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      Back to Courses
    </a>

    <?php $input_id = 'viewShareUrl'; include __DIR__ . '/../components/public/dazu_course_share_bar.php'; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      <div class="lg:col-span-5 space-y-6">
        <?php include __DIR__ . '/../components/public/dazu_course_sidebar.php'; ?>
      </div>
      <div class="lg:col-span-7">
        <?php $pfx = 'view'; include __DIR__ . '/../components/public/dazu_admission_form.php'; ?>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  var switcher = document.getElementById('dazu-course-switcher');
  if (!switcher) return;
  switcher.addEventListener('change', function () {
    var url = switcher.value;
    if (url) window.location.href = url;
  });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
