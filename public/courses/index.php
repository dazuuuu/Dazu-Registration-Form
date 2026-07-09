<?php
// public/courses/index.php — All courses (physical & online)
$page_title = 'Our Courses — Dazu Hub Tech Skills Institute';
$page_description = 'Browse web development, cybersecurity, data analytics, AI and more. Study physically in Nairobi or online with flexible payment plans.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../app/v1/helpers/course_catalog.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';

use Models\Connection;
use Models\CourseModel;

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
[$displayCourses, $usingStaticCourses] = course_catalog_for_display($courseModel);

$extra_css = '<link rel="stylesheet" href="' . asset('css/homepage.css') . '">';

ob_start();
?>

<?php
$banner_eyebrow = 'Programs';
$banner_title   = 'Our Courses';
$banner_lead    = 'Hands-on tech training in Nairobi — attend classes on campus or study online from anywhere in Kenya.';
include __DIR__ . '/../components/public/page_banner.php';
?>

<section class="dh-study-modes">
    <div class="dh-container">
        <div class="dh-study-modes-grid">
            <div class="dh-study-mode-card dh-reveal">
                <h3><i class="fas fa-building" aria-hidden="true"></i> Physical classes</h3>
                <p>Learn on campus with tutors, peers, and lab time. Ideal if you want face-to-face mentorship in Nairobi.</p>
                <ul>
                    <li><i class="fas fa-check"></i> In-person sessions at our institute</li>
                    <li><i class="fas fa-check"></i> Student ID card included</li>
                    <li><i class="fas fa-check"></i> Registration fee: KSh 1,500</li>
                    <li><i class="fas fa-check"></i> Hands-on projects with classmates</li>
                </ul>
            </div>
            <div class="dh-study-mode-card dh-reveal dh-delay-1">
                <h3><i class="fas fa-laptop" aria-hidden="true"></i> Online classes</h3>
                <p>Same curriculum and tutor support — join live sessions and submit work through the student portal.</p>
                <ul>
                    <li><i class="fas fa-check"></i> Live online sessions</li>
                    <li><i class="fas fa-check"></i> Student portal for coursework &amp; fees</li>
                    <li><i class="fas fa-check"></i> Registration fee: KSh 1,000</li>
                    <li><i class="fas fa-check"></i> Flexible monthly payment plans</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="dh-courses-page-grid">
    <div class="dh-container">
        <div class="hp-section-head dh-reveal" style="margin-bottom:36px;">
            <h2>All course tracks</h2>
        </div>

        <?php if (empty($displayCourses)): ?>
            <div class="dh-empty-state dh-reveal">
                <i class="fas fa-book-open"></i>
                <h3>No courses available yet</h3>
                <p>Check back soon or contact us for the next intake.</p>
                <a href="<?php echo url('contact/index.php'); ?>" class="dh-btn-apply" style="display:inline-flex;">Contact us</a>
            </div>
        <?php else: ?>
            <?php include __DIR__ . '/../components/public/course_cards_grid.php'; ?>
        <?php endif; ?>
    </div>
</section>

<section class="dh-page-cta">
    <div class="dh-container dh-reveal">
        <h2>Ready to enroll?</h2>
        <p>Choose your course, pick physical or online, and apply in minutes.</p>
        <div class="dh-page-cta-actions">
            <button type="button" class="dh-btn-apply" data-dh-apply-open>Apply now</button>
            <a href="<?php echo url('contact/index.php'); ?>" class="dh-btn-login">Ask a question</a>
        </div>
    </div>
</section>

<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/layout.php';
