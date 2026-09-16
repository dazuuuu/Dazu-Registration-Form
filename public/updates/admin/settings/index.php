<?php
// public/admin/settings/index.php

require_once __DIR__ . '/../../../app/v1/init.php';
require_once __DIR__ . '/../../../app/v1/helpers/middleware.php';
require_once __DIR__ . '/../../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../../app/v1/models/SettingModel.php';
require_once __DIR__ . '/../../../app/v1/models/CourseModel.php';

Middleware::requireAdmin();

$settings = new SettingModel($pdo);
$courseModel = new \Models\CourseModel($pdo);
$popularCourses = $courseModel->getHomepageCourses();
$logoUrl = settings_asset_url($settings->get('logo_path'));
$siteName = $settings->get('site_name', 'Dazu Training Institute');
$heroSlides = $settings->getHeroSlides(false);
$pageHeaders = $settings->getAllPageHeaders();
$classScheduleOnline = trim((string) $settings->get('class_schedule_online', '8am, 12pm, 6pm'));
$classSchedulePhysical = trim((string) $settings->get('class_schedule_physical', '5am'));

$flash = $_SESSION['flash'] ?? null;
if (is_array($flash) && isset($flash['type'], $flash['msg'])) {
    unset($_SESSION['flash']);
} else {
    $flash = null;
}

$page_title = 'Settings';
$breadcrumbs = [['label' => 'Settings', 'active' => true]];

$settingCards = [
    ['title' => 'Branding', 'subtitle' => 'Logo & site name', 'icon' => 'fa-image', 'color' => 'primary', 'url' => url('admin/settings/edit.php?section=logo'), 'button' => 'Edit branding'],
    ['title' => 'Homepage Hero', 'subtitle' => count($heroSlides) . ' slide' . (count($heroSlides) === 1 ? '' : 's'), 'icon' => 'fa-images', 'color' => 'info', 'url' => url('admin/settings/edit.php?section=hero'), 'button' => 'Manage slides'],
    ['title' => 'Page Banners', 'subtitle' => count($pageHeaders) . ' page' . (count($pageHeaders) === 1 ? '' : 's'), 'icon' => 'fa-heading', 'color' => 'warning', 'url' => url('admin/settings/edit.php?section=headers'), 'button' => 'Edit banners'],
    ['title' => 'Contact & Map', 'subtitle' => 'Phone, email, campus map embed', 'icon' => 'fa-map-location-dot', 'color' => 'success', 'url' => url('admin/settings/edit.php?section=contact'), 'button' => 'Edit contact'],
    ['title' => 'Class Schedule', 'subtitle' => 'Shared online and physical class times', 'icon' => 'fa-calendar-days', 'color' => 'primary', 'url' => url('admin/settings/edit.php?section=schedule'), 'button' => 'Edit schedule'],
    ['title' => 'Popular Courses', 'subtitle' => count($popularCourses) . ' course' . (count($popularCourses) === 1 ? '' : 's') . ' on homepage', 'icon' => 'fa-star', 'color' => 'warning', 'url' => url('admin/settings/edit.php?section=popular_courses'), 'button' => 'Manage popular'],
    ['title' => 'SmS Receipts', 'subtitle' => 'Logo, address, footer for PDF receipts', 'icon' => 'fa-receipt', 'color' => 'secondary', 'url' => url('admin/settings/edit.php?section=receipt'), 'button' => 'Edit receipts'],
];

ob_start();
?>
<style>
.settings-card { border: 0; border-radius: 14px; box-shadow: 0 3px 14px rgba(20,35,25,.07); height: 100%; }
.settings-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.settings-logo-preview { height: 90px; border: 1px dashed #d9dee7; border-radius: 10px; background: #f8fafc; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.settings-logo-preview img { max-height: 64px; max-width: 80%; object-fit: contain; }
.settings-thumbs { display: flex; gap: 8px; flex-wrap: wrap; }
.settings-thumbs img, .settings-thumbs .video-thumb { width: 64px; height: 44px; object-fit: cover; border-radius: 8px; border: 1px solid #e5e7eb; }
.settings-thumbs .video-thumb { display: inline-flex; align-items: center; justify-content: center; background: #111827; color: #fff; }
.settings-header-list { max-height: 150px; overflow: auto; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h5 class="mb-1"><i class="fas fa-cog me-2"></i>Site Settings</h5>
    <div class="text-muted small">Manage branding, homepage media, public page banners, contact details, schedules, and receipts.</div>
  </div>
  <a href="<?php echo url('admin/dashboard.php'); ?>" class="btn btn-outline-secondary btn-sm">
    <i class="fas fa-arrow-left me-1"></i> Dashboard
  </a>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($flash['msg']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<div class="row g-3">
  <?php foreach ($settingCards as $card): ?>
    <div class="col-md-6 col-xl-4">
      <div class="card settings-card">
        <div class="card-body d-flex flex-column gap-3">
          <div class="d-flex align-items-center gap-3">
            <div class="settings-icon bg-<?php echo $card['color']; ?>-subtle text-<?php echo $card['color']; ?>">
              <i class="fas <?php echo htmlspecialchars($card['icon']); ?>"></i>
            </div>
            <div>
              <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($card['title']); ?></h6>
              <div class="small text-muted"><?php echo htmlspecialchars($card['subtitle']); ?></div>
            </div>
          </div>

          <?php if ($card['title'] === 'Branding'): ?>
            <div class="settings-logo-preview">
              <?php if ($logoUrl): ?>
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Current logo">
              <?php else: ?>
                <span class="small text-muted">No logo uploaded yet</span>
              <?php endif; ?>
            </div>
            <div class="small text-muted">Current site name: <strong><?php echo htmlspecialchars($siteName); ?></strong></div>
          <?php elseif ($card['title'] === 'Homepage Hero'): ?>
            <?php if ($heroSlides): ?>
              <div class="settings-thumbs">
                <?php foreach (array_slice($heroSlides, 0, 6) as $slide): ?>
                  <?php
                    $slideExt = strtolower(pathinfo(parse_url((string) ($slide['image_path'] ?? ''), PHP_URL_PATH) ?: (string) ($slide['image_path'] ?? ''), PATHINFO_EXTENSION));
                    $isVideoSlide = (($slide['media_type'] ?? '') === 'video') || in_array($slideExt, ['mp4', 'webm', 'mov'], true);
                  ?>
                  <?php if ($isVideoSlide): ?>
                    <span class="video-thumb"><i class="fas fa-play"></i></span>
                  <?php else: ?>
                    <img src="<?php echo htmlspecialchars(settings_asset_url($slide['image_path'])); ?>" alt="">
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="small text-muted">No slides yet. The homepage will use fallback images.</div>
            <?php endif; ?>
          <?php elseif ($card['title'] === 'Page Banners'): ?>
            <div class="settings-header-list">
              <?php foreach ($pageHeaders as $header): ?>
                <div class="d-flex align-items-center justify-content-between small py-1 border-bottom">
                  <span class="text-capitalize"><?php echo htmlspecialchars($header['page_key']); ?></span>
                  <?php if (!empty($header['image_path'])): ?>
                    <span class="badge text-bg-success">image set</span>
                  <?php else: ?>
                    <span class="badge text-bg-light border text-muted">no image</span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php elseif ($card['title'] === 'Popular Courses'): ?>
            <?php if (!empty($popularCourses)): ?>
              <div class="d-flex flex-wrap gap-1">
                <?php foreach (array_slice($popularCourses, 0, 5) as $pc): ?>
                  <span class="badge text-bg-warning"><?php echo htmlspecialchars($pc['title']); ?></span>
                <?php endforeach; ?>
                <?php if (count($popularCourses) > 5): ?>
                  <span class="badge text-bg-light border text-muted">+<?php echo count($popularCourses) - 5; ?> more</span>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="small text-muted">No courses marked as popular yet. All courses show on homepage.</div>
            <?php endif; ?>
          <?php elseif ($card['title'] === 'Class Schedule'): ?>
            <div class="small text-muted">
              <div class="d-flex justify-content-between gap-2 py-1 border-bottom">
                <strong class="text-dark">Online</strong>
                <span class="text-end"><?php echo htmlspecialchars($classScheduleOnline !== '' ? $classScheduleOnline : 'Not set'); ?></span>
              </div>
              <div class="d-flex justify-content-between gap-2 py-1">
                <strong class="text-dark">Physical</strong>
                <span class="text-end"><?php echo htmlspecialchars($classSchedulePhysical !== '' ? $classSchedulePhysical : 'Not set'); ?></span>
              </div>
            </div>
          <?php else: ?>
            <div class="small text-muted flex-grow-1"><?php echo htmlspecialchars($card['subtitle']); ?></div>
          <?php endif; ?>

          <div class="mt-auto">
            <a href="<?php echo htmlspecialchars($card['url']); ?>" class="btn btn-primary btn-sm">
              <i class="fas fa-pen me-1"></i><?php echo htmlspecialchars($card['button']); ?>
            </a>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../templates/admin/layout.php';
