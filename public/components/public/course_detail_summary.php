<?php
// public/components/public/course_detail_summary.php
// Course hero/summary for share and view pages. Requires $course.

if (!isset($course)) return;

if (!function_exists('dazu_course_image_url')) {
    require_once __DIR__ . '/../../../app/v1/helpers/dazu_theme.php';
}

$coverImg = dazu_course_image_url($course);
$fee = (float) $course['course_fee'];
$mo  = max(1, (int) $course['duration_months']);
$icons = ['PY'=>'🐍','PHP'=>'🐘','ND'=>'⬡','FE'=>'⚡','FS'=>'🚀','AI'=>'🤖'];
$icon = $icons[strtoupper($course['abbreviation'] ?? '')] ?? '📚';
?>
<div class="cds-card dh-reveal">
    <div class="cds-cover">
        <?php if ($coverImg): ?>
            <img src="<?php echo htmlspecialchars($coverImg); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>">
        <?php else: ?>
            <div class="cds-cover-ph"><?php echo $icon; ?></div>
        <?php endif; ?>
    </div>
    <div class="cds-body">
        <div class="cds-badges">
            <span class="cds-badge"><i class="fas fa-clock"></i> <?php echo $mo; ?> month<?php echo $mo !== 1 ? 's' : ''; ?></span>
            <span class="cds-badge"><i class="fas fa-laptop-code"></i> Online</span>
            <span class="cds-badge"><i class="fas fa-building-columns"></i> Physical</span>
        </div>
        <h1 class="cds-title"><?php echo htmlspecialchars($course['title']); ?></h1>
        <div class="cds-fee">
            <span class="cds-fee-amt">KSh <?php echo number_format($fee); ?></span>
            <span class="cds-fee-sub">from KSh <?php echo number_format($fee / $mo); ?>/month</span>
        </div>
        <?php if (!empty($course['overview'])): ?>
            <p class="cds-overview"><?php echo nl2br(htmlspecialchars($course['overview'])); ?></p>
        <?php endif; ?>
        <?php if (!empty($course['description'])): ?>
            <div class="cds-desc">
                <?php
                $desc = $course['description'];
                echo preg_match('/<[a-z][\s\S]*>/i', $desc) ? $desc : nl2br(htmlspecialchars($desc));
                ?>
            </div>
        <?php endif; ?>
    </div>
</div>
