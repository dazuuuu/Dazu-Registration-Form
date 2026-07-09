<?php
// public/components/public/course_cards_grid.php
// Expects: $displayCourses (array), $usingStaticCourses (bool, optional)

if (!function_exists('url')) {
    require_once __DIR__ . '/../../../app/v1/config/paths.php';
}
if (!function_exists('course_view_url')) {
    require_once __DIR__ . '/../../../app/v1/helpers/course_share.php';
}

$usingStatic = !empty($usingStaticCourses);
?>
<div class="hp-courses-grid">
    <?php foreach ($displayCourses as $i => $course):
        $imgSrc = !empty($course['cover_image']) ? $course['cover_image'] : ($course['card_image'] ?? '');
        $faIcon = $course['fa_icon'] ?? 'fa-book';
        $rating = $course['rating'] ?? '4.8';
        if ($usingStatic) {
            $detailLink = url('/#register');
        } else {
            $detailLink = course_view_url($course);
        }
    ?>
    <a href="<?php echo htmlspecialchars($detailLink); ?>" class="hp-course-card dh-reveal dh-delay-<?php echo ($i % 4) + 1; ?>" style="text-decoration:none;color:inherit;">
        <div class="hp-course-img-wrap">
            <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                 alt="<?php echo htmlspecialchars($course['title']); ?>"
                 loading="lazy">
            <span class="hp-course-icon"><i class="fas <?php echo htmlspecialchars($faIcon); ?>" aria-hidden="true"></i></span>
        </div>
        <div class="hp-course-body">
            <div class="hp-course-title"><?php echo htmlspecialchars($course['title']); ?></div>
            <div class="hp-course-overview">
                <?php echo htmlspecialchars(mb_strimwidth($course['overview'] ?? '', 0, 90, '…')); ?>
            </div>
            <div class="hp-course-meta">
                <i class="fas fa-star hp-star" aria-hidden="true"></i>
                <strong><?php echo htmlspecialchars($rating); ?></strong>
                <span class="hp-dot">&middot;</span>
                <span><?php echo (int)($course['duration_months'] ?? 0); ?> months</span>
                <span class="hp-dot">&middot;</span>
                <span>KSh <?php echo number_format((float)($course['course_fee'] ?? 0)); ?></span>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
