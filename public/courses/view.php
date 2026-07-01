<?php
// public/courses/view.php — Course detail + registration (full site layout)
// URL: courses/view.php?id=3  or  courses/view.php?abbr=PY

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../app/v1/services/CourseRegistrationHandler.php';

use Models\Connection;
use Models\CourseModel;
use Services\CourseRegistrationHandler;

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
$course = course_resolve_for_page($courseModel);

if (!$course) {
    $page_title = 'Course not found';
    $page_description = '';
    ob_start();
    echo '<div class="dh-container dh-share-page"><div class="crf-alert crf-alert--err">This course is not available. <a href="'
        . htmlspecialchars(url('courses/index.php')) . '">Browse all courses</a></div></div>';
    $content = ob_get_clean();
    require __DIR__ . '/../templates/public/layout.php';
    exit;
}

$page_title = htmlspecialchars($course['title']) . ' — Dazu Hub Institute';
$page_description = mb_strimwidth(strip_tags($course['overview'] ?? $course['description'] ?? ''), 0, 160, '…');

$reg_success = false;
$reg_error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $handler = new CourseRegistrationHandler($pdo);
    $result  = $handler->process($_POST, $_FILES, $course, null);
    if ($result['ok']) {
        $reg_success = true;
    } else {
        $reg_error = $result['error'] ?? 'Registration failed.';
    }
}

$share_url = course_share_url((int) $course['id']);
$share_title = 'Register for ' . $course['title'] . ' at Dazu Hub Institute';

ob_start();
?>
<div class="dh-container dh-share-page">
    <?php $input_id = 'viewShareUrl'; include __DIR__ . '/../components/public/course_share_bar.php'; ?>

    <div class="dh-share-grid">
        <div class="dh-share-detail">
            <?php include __DIR__ . '/../components/public/course_detail_summary.php'; ?>
        </div>
        <div class="dh-share-form-wrap">
            <div class="crf-card">
                <div class="crf-card-head">
                    <h2>Apply for This Course</h2>
                    <p>Your selected course is pre-filled below. Complete the form to apply.</p>
                </div>
                <div class="crf-card-body">
                    <?php $pfx = 'view'; include __DIR__ . '/../components/public/course_registration_form.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/layout.php';
