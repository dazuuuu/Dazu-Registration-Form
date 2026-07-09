<?php
// public/apply/index.php — Standalone shareable registration form
$page_title = 'Apply Now — Dazu Hub Tech Skills Institute';
$page_description = 'Apply for a course at Dazu Hub Tech Skills Institute. Pay via M-Pesa Lipa na M-PESA and submit your application online.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/helpers/course_catalog.php';
require_once __DIR__ . '/../../app/v1/helpers/registration_submit.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';

use Models\Connection;
use Models\CourseModel;

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
[$displayCourses] = course_catalog_for_display($courseModel);

$reg_success = !empty($_GET['success']);
$reg_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dh_register'])) {
    $result = dh_process_registration_post($pdo, $courseModel);

    if (dh_registration_wants_json()) {
        dh_registration_json_response($result);
    }

    if (!empty($result['ok'])) {
        header('Location: ' . url('apply/index.php?success=1'));
        exit;
    }

    $reg_error = $result['error'] ?? 'Submission failed.';
}

$skip_apply_modal = true;

$extra_css = '<link rel="stylesheet" href="' . asset('css/homepage.css') . '">'
    . '<link rel="stylesheet" href="' . asset('css/apply-modal.css') . '">';

ob_start();
?>

<section class="dh-apply-standalone">
    <div class="dh-container" style="max-width:640px;">
        <div class="dh-apply-standalone-head dh-reveal">
            <a href="<?php echo url('/'); ?>" class="dh-apply-back"><i class="fas fa-arrow-left"></i> Back to home</a>
            <h1>Course Application</h1>
            <p>Complete the form below after paying via M-Pesa. You can share this page link directly with applicants.</p>
        </div>

        <div class="hp-form-card dh-reveal dh-delay-1">
            <?php if (!$reg_success): ?>
            <div class="hp-form-head">
                <h2><i class="fas fa-graduation-cap" style="margin-right:6px;"></i> Apply Now</h2>
                <p>Limited slots per course · We follow up within 24 hours</p>
            </div>
            <?php endif; ?>
            <div class="hp-form-body">
                <?php
                $reg_form_id = 'applyStandaloneForm';
                $reg_form_action = url('apply/index.php');
                include __DIR__ . '/../components/public/registration_form.php';
                ?>
            </div>
        </div>
    </div>
</section>

<?php
$content = ob_get_clean();
$extra_js = '<script src="' . asset('js/apply-form.js') . '"></script>';
require __DIR__ . '/../templates/public/layout.php';
