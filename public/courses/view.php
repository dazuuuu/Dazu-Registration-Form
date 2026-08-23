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

// The applicant can switch course from the form's Course Choice select.
//
// CourseRegistrationHandler::process() writes $course['id'] and ignores
// $post['course_id'], so the posted choice has to be resolved into $course *here*.
// Without this the select would look like it worked and then enrol them on
// whatever course the URL happened to point at.
//
// Re-resolved before enrich/render so the sidebar, fee box, page title and share
// link all describe the course actually being applied for.
$courseChangeError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCourseId = (int) ($_POST['course_id'] ?? 0);
    if ($postedCourseId > 0 && $postedCourseId !== (int) ($course['id'] ?? 0)) {
        $picked = $courseModel->getById($postedCourseId);
        if (course_is_shareable($picked)) {
            $course = $picked;
            $courseId = $postedCourseId;
        } else {
            // Deactivated between page load and submit.
            $courseChangeError = 'The course you selected is no longer available. Please choose another.';
        }
    }
}

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
$og_image = dazu_course_image_url($course);

// Courses offered in the Course Choice select. getAll(true) and course_is_shareable()
// use the same is_active === 1 rule, so the current course is always among them.
$courseChoices = $courseModel->getAll(true);
$allowCourseChange = true;

$reg_success = false;
$reg_error = null;
$reg_no = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($courseChangeError !== null) {
        $reg_error = $courseChangeError;
    } else {
        $handler = new CourseRegistrationHandler($pdo);
        $result = $handler->process($_POST, $_FILES, $course, null);
        if ($result['ok']) {
            $reg_success = true;
            $reg_no = $result['registration_no'] ?? null;
        } else {
            $reg_error = $result['error'] ?? 'Registration failed.';
        }
    }
}

$share_url = course_share_url((int) $course['id']);
$share_title = 'Apply for ' . $course['title'] . ' at Dazu hub Institute';
$extra_head = '<style>
  .dazu-course-preview{background:#fff}.dazu-course-preview .preview-media{aspect-ratio:16/9;border-radius:16px;overflow:hidden;background:#0f172a;position:relative}.dazu-course-preview .preview-media img{width:100%;height:100%;object-fit:cover}.dazu-course-preview .preview-media:after{content:"▶";position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:64px;height:64px;border-radius:50%;display:grid;place-items:center;background:#ffffff33;border:1px solid #ffffff88;color:white;font-size:24px}.dazu-course-preview .lg\:col-span-8{min-width:0}@media(min-width:1024px){.dazu-course-preview .course-enrol-column{position:sticky;top:1rem}.dazu-course-preview .course-enrol-column>div{border-radius:0;border-width:0 0 0 1px;background:#f8fafc;min-height:calc(100vh - 6rem)}} 
</style>';

ob_start();
?>
<div class="dazu-course-preview bg-white min-h-screen">
  <div class="max-w-[1720px] mx-auto px-4 md:px-8 py-10 md:py-12">
    <a href="<?php echo url('courses/index.php'); ?>" class="mb-8 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-green-600">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      Back to Courses
    </a>

    <?php $input_id = 'viewShareUrl'; include __DIR__ . '/../components/public/dazu_course_share_bar.php'; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      <div class="lg:col-span-8 space-y-6">
        <div class="preview-media"><img src="<?php echo htmlspecialchars(dazu_course_image_url($course)); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>"></div>
        <?php include __DIR__ . '/../components/public/dazu_course_sidebar.php'; ?>
      </div>
      <div class="lg:col-span-4 course-enrol-column">
        <?php $pfx = 'view'; include __DIR__ . '/../components/public/dazu_admission_form.php'; ?>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
