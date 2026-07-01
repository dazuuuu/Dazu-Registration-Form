<?php
// public/courses/index.php — All courses listing
$page_title = 'Our Courses — Dazu Hub Institute';
$page_description = 'Browse Python, PHP, Full-Stack, AI and more tech courses at Dazu Hub Institute, Nairobi.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';

use Models\Connection;
use Models\CourseModel;

$pdo = Connection::getInstance()->getConnection();
$courses = (new CourseModel($pdo))->getAll(true);

ob_start();
?>
<section class="dh-section dh-section--alt">
    <div class="dh-container">
        <div class="dh-section-head dh-reveal">
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
                        <a href="<?php echo htmlspecialchars(course_view_url($c)); ?>" class="dh-btn dh-btn-primary dh-btn--sm">View Details</a>
                        <button type="button" class="dh-btn dh-btn-outline dh-btn--sm course-share-copy"
                                data-url="<?php echo htmlspecialchars(course_share_url((int) $c['id'])); ?>"
                                title="Copy share link">
                            <i class="fas fa-share-nodes"></i>
                        </button>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<script>
(function () {
    document.querySelectorAll('.course-share-copy').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-url');
            if (!url) return;
            var done = function () {
                var icon = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(function () { btn.innerHTML = icon; }, 2000);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(done);
            } else {
                var t = document.createElement('textarea');
                t.value = url;
                document.body.appendChild(t);
                t.select();
                document.execCommand('copy');
                document.body.removeChild(t);
                done();
            }
        });
    });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/layout.php';
