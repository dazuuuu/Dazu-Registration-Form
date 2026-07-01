<?php
// public/courses/index.php — All courses listing
$page_title = 'Our Courses — Dazu Hub Institute';
$page_description = 'Browse Python, PHP, Full-Stack, AI and more tech courses at Dazu Hub Institute, Nairobi.';

require_once __DIR__ . '/../../app/init.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';

use Models\Connection;
use Models\CourseModel;

$pdo = Connection::getInstance()->getConnection();
$courses = (new CourseModel($pdo))->getAll(true);

ob_start();
?>
<section class="dh-section" style="padding-top:48px;">
    <div class="dh-container">
        <div class="dh-section-head" style="text-align:center;max-width:640px;margin:0 auto 40px;">
            <span class="dh-eyebrow">Programs</span>
            <h1 class="dh-h2">Courses at Dazu Hub Institute</h1>
            <p class="dh-lead">Hands-on tech training with flexible payment plans. Choose online or physical study mode.</p>
        </div>

        <?php if (empty($courses)): ?>
            <div class="dh-alert">No courses are open right now. Please check back soon.</div>
        <?php else: ?>
        <div class="dh-course-grid">
            <?php foreach ($courses as $c): ?>
            <article class="dh-course-card">
                <?php if (!empty($c['cover_image'])): ?>
                    <img src="<?php echo htmlspecialchars(settings_asset_url($c['cover_image'])); ?>" alt="" class="dh-course-img">
                <?php else: ?>
                    <div class="dh-course-img dh-course-img--placeholder"><i class="fas fa-graduation-cap"></i></div>
                <?php endif; ?>
                <div class="dh-course-body">
                    <h2 class="dh-course-title"><?php echo htmlspecialchars($c['title']); ?></h2>
                    <p class="dh-course-meta"><?php echo (int)$c['duration_months']; ?> months · KSh <?php echo number_format($c['course_fee']); ?></p>
                    <p class="dh-course-desc"><?php echo htmlspecialchars(mb_substr(strip_tags($c['overview'] ?: $c['description'] ?: ''), 0, 140)); ?>…</p>
                    <div class="dh-course-actions">
                        <a href="<?php echo url('courses/view.php?id=' . (int)$c['id']); ?>" class="dh-btn dh-btn--primary dh-btn--sm">View & Apply</a>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/layout.php';
