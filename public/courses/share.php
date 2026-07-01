<?php
// public/courses/share.php — Standalone shareable course registration page
// URL: courses/share.php?id=3&ref=DH-PY01/2026 (ref = optional student referral)

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

$courseId = course_share_parse_id();
$course   = $courseId > 0 ? $courseModel->getById($courseId) : null;

if (!course_is_shareable($course)) {
    http_response_code(404);
    $page_title = 'Course not found';
    $reason = ($courseId <= 0)
        ? 'Invalid course link.'
        : ($course ? 'This course is currently inactive.' : 'This course could not be found.');
    ob_start();
    echo '<div class="dh-container dh-share-page"><div class="crf-alert crf-alert--err">'
        . htmlspecialchars($reason)
        . ' <a href="' . htmlspecialchars(url('courses/index.php')) . '">Browse courses</a></div></div>';
    $content = ob_get_clean();
    require __DIR__ . '/../templates/public/standalone.php';
    exit;
}

$refParam = trim($_GET['ref'] ?? '');
$referrer = course_share_resolve_referral($pdo, $refParam);
$referralNo = $referrer ? $referrer['registration_no'] : null;

$reg_success = false;
$reg_error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $handler = new CourseRegistrationHandler($pdo);
    $result  = $handler->process($_POST, $_FILES, $course, $referralNo);
    if ($result['ok']) {
        $reg_success = true;
    } else {
        $reg_error = $result['error'] ?? 'Registration failed.';
    }
}

$page_title = htmlspecialchars($course['title']) . ' — Register | Dazu Hub Institute';
$page_description = mb_strimwidth(strip_tags($course['overview'] ?? $course['description'] ?? ''), 0, 160, '…');
$share_url = course_share_url((int) $course['id'], $referralNo);
$share_title = 'Join me at Dazu Hub Institute — ' . $course['title'];

$coverForOg = !empty($course['cover_image']) ? settings_asset_url($course['cover_image']) : '';
if ($coverForOg && strpos($coverForOg, 'http') !== 0) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $og_image = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $coverForOg;
} else {
    $og_image = $coverForOg ?: null;
}

ob_start();
?>
<div class="dh-container dh-share-page">
    <?php
    $input_id = 'shareUrlMain';
    include __DIR__ . '/../components/public/course_share_bar.php';
    ?>

    <div class="dh-share-grid">
        <div class="dh-share-detail">
            <?php include __DIR__ . '/../components/public/course_detail_summary.php'; ?>
        </div>
        <div class="dh-share-form-wrap">
            <div class="crf-card">
                <div class="crf-card-head">
                    <h2>Register Now</h2>
                    <p>Complete the form to apply for this course.</p>
                </div>
                <div class="crf-card-body">
                    <?php
                    $pfx = 'share';
                    include __DIR__ . '/../components/public/course_registration_form.php';
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/standalone.php';
