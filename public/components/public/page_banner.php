<?php
// public/components/public/page_banner.php
// Green page header — same style as Contact / About pages.
// Set before include: $banner_eyebrow, $banner_title, $banner_lead (optional)

$be = trim((string)($banner_eyebrow ?? ''));
$bt = trim((string)($banner_title ?? 'Page'));
$bl = trim((string)($banner_lead ?? ''));
?>
<section class="dh-page-banner">
    <div class="dh-container dh-reveal">
        <?php if ($be !== ''): ?>
            <span class="dh-page-banner-eyebrow"><?php echo htmlspecialchars($be); ?></span>
        <?php endif; ?>
        <h1 class="dh-page-banner-title"><?php echo htmlspecialchars($bt); ?></h1>
        <?php if ($bl !== ''): ?>
            <p class="dh-page-banner-lead"><?php echo htmlspecialchars($bl); ?></p>
        <?php endif; ?>
    </div>
</section>
