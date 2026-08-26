<?php
// public/courses/share.php — Standalone shareable course apply page
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../app/v1/helpers/course_catalog.php';
require_once __DIR__ . '/../../app/v1/helpers/enrolment_offer.php';
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
    http_response_code(404);
    $page_title = 'Course not found';
    ob_start();
    echo '<div class="max-w-[1280px] mx-auto px-4 py-20 text-center"><p class="text-slate-600">Course not found.</p></div>';
    $content = ob_get_clean();
    require __DIR__ . '/../templates/public/dazu_layout.php';
    exit;
}

$refParam = trim($_GET['ref'] ?? '');
$referrer = course_share_resolve_referral($pdo, $refParam);
$referralNo = $referrer ? $referrer['registration_no'] : null;

$reg_success = false;
$reg_error = null;
$issued_reg_no = '';
$enrolment_offer_email = '';
$courseChangeError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCourseId = (int) ($_POST['course_id'] ?? 0);
    if ($postedCourseId > 0 && $postedCourseId !== (int) ($course['id'] ?? 0)) {
        $picked = $courseModel->getById($postedCourseId);
        if (course_is_shareable($picked)) {
            $course = course_catalog_enrich($picked);
            $courseId = $postedCourseId;
        } else {
            $courseChangeError = 'The course you selected is no longer available. Please choose another.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($courseChangeError !== null) {
        $reg_error = $courseChangeError;
    } else {
        $handler = new CourseRegistrationHandler($pdo);
        $result = $handler->process($_POST, $_FILES, $course, $referralNo);
        if ($result['ok']) {
            $qs = ['id' => (int) $courseId, 'applied' => 1];
            if ($refParam !== '') {
                $qs['ref'] = $refParam;
            }
            redirect(url('courses/share.php') . '?' . http_build_query($qs));
            exit;
        }
        $reg_error = $result['error'] ?? 'Registration failed.';
    }
}

if (!empty($_GET['applied']) && enrolment_offer_consume_show()) {
    $offer = enrolment_offer_get();
    $reg_success = true;
    $issued_reg_no = (string) ($offer['registration_no'] ?? '');
    $enrolment_offer_email = (string) ($offer['email'] ?? '');
}

$page_title = ($course['title'] ?? 'Course') . ' — Register | Dazu hub Institute';
$share_url = course_share_url((int) $course['id'], $referralNo);
$share_title = 'Join me at Dazu hub — ' . $course['title'];
$og_image = course_og_image_url($course);
$og_url = $share_url;
$page_description = mb_strimwidth(strip_tags($course['overview'] ?? $course['description'] ?? ''), 0, 160, '…');

require_once __DIR__ . '/../../app/v1/helpers/seo.php';
if (seo_is_social_crawler() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    seo_emit_course_share_preview($course, $og_url);
}

ob_start();
?>
<div class="bg-slate-50 min-h-screen py-10 md:py-16">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8">
    <a href="<?php echo url('/'); ?>" class="mb-8 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-green-600">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      Dazu hub Institute
    </a>

    <?php $input_id = 'shareUrlMain'; include __DIR__ . '/../components/public/dazu_course_share_bar.php'; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      <div class="lg:col-span-5 space-y-6">
        <?php include __DIR__ . '/../components/public/dazu_course_sidebar.php'; ?>
      </div>
      <div class="lg:col-span-7">
        <?php
          $pfx = 'share';
          $allowCourseChange = true;
          $courseChoices = $courseModel->getAll(true);
          $courseChangeUrl = url('courses/share.php') . ($refParam !== '' ? ('?ref=' . rawurlencode($refParam)) : '');
          include __DIR__ . '/../components/public/dazu_admission_form.php';
        ?>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
