<?php
// public/courses/category.php — dedicated shareable School / Category page with SEO OpenGraph tags
require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../app/v1/helpers/course_catalog.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../app/v1/models/CourseCategoryModel.php';

use Models\Connection;
use Models\CourseModel;
use Models\CourseCategoryModel;

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
$categoryModel = new CourseCategoryModel($pdo);

$slug = trim($_GET['slug'] ?? '');
$id = (int) ($_GET['id'] ?? 0);

$category = null;
if ($slug !== '') {
    $category = $categoryModel->getBySlug($slug);
}
if (!$category && $id > 0) {
    $category = $categoryModel->getById($id);
}

// Fallback if not found
if (!$category) {
    $page_title = 'School Not Found — Dazu Training Institute';
    $page_description = 'Browse technology certification courses at Dazu Training Institute.';
    ob_start();
    ?>
    <div class="max-w-3xl mx-auto px-4 py-24 text-center">
      <div class="w-16 h-16 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
      <h1 class="text-2xl font-extrabold text-slate-900 mb-2">School or Category Not Found</h1>
      <p class="text-slate-500 text-sm mb-6">The faculty or category you are looking for may have been updated or moved.</p>
      <a href="<?php echo url('courses'); ?>" class="inline-flex items-center gap-2 px-6 py-3 bg-[#15A24A] hover:bg-[#128a3e] text-white text-xs font-extrabold uppercase tracking-wider rounded-xl transition-all">
        View All Courses &rarr;
      </a>
    </div>
    <?php
    $content = ob_get_clean();
    require __DIR__ . '/../templates/public/dazu_layout.php';
    exit();
}

$schoolName = function_exists('dazu_format_school_name') ? dazu_format_school_name($category['name']) : 'School of ' . $category['name'];
$categorySlug = $category['slug'] ?? 'school';

$page_title = htmlspecialchars($schoolName) . ' — Dazu Training Institute';
$page_description = !empty($category['description'])
    ? trim($category['description'])
    : "Explore industry-recognized courses, practical projects, and professional certification programs in {$schoolName} at Dazu Training Institute.";

// Featured image for OpenGraph / WhatsApp / Twitter sharing
$og_image = !empty($category['featured_image'])
    ? $category['featured_image']
    : (!empty($category['icon_path']) ? $category['icon_path'] : null);

$og_url = url('courses/category.php?slug=' . rawurlencode($categorySlug));

// Fetch courses for this category
$rawCourses = $courseModel->getByCategoryId((int) $category['id'], true);
$courses = [];
foreach ($rawCourses as $c) {
    $courses[] = course_catalog_enrich($c);
}

// All categories for the bottom switcher
try {
    $allCategories = $categoryModel->getAll(true);
} catch (\Throwable $e) {
    $allCategories = [];
}

$shareText = "Explore {$schoolName} at Dazu Training Institute!\n\n" . $page_description . "\n\nBrowse courses here: " . $og_url;
$whatsappShareUrl = 'https://api.whatsapp.com/send?text=' . rawurlencode($shareText);

ob_start();
?>
<!-- School Title + Share Bar -->
<div class="bg-white border-b border-slate-100 shadow-sm sticky top-0 z-30">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-4">

      <!-- Breadcrumb + School name -->
      <div class="min-w-0">
        <nav class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-400 mb-1">
          <a href="<?php echo url('/'); ?>" class="hover:text-[#15A24A] transition-colors">Home</a>
          <span>/</span>
          <a href="<?php echo url('courses'); ?>" class="hover:text-[#15A24A] transition-colors">Courses</a>
          <span>/</span>
          <span class="text-[#15A24A] truncate max-w-[180px]"><?php echo htmlspecialchars($schoolName); ?></span>
        </nav>
        <div class="flex items-center gap-2">
          <?php if (!empty($category['icon_path'])): ?>
            <img src="<?php echo htmlspecialchars(settings_asset_url($category['icon_path'])); ?>" alt="" class="w-6 h-6 object-contain shrink-0">
          <?php endif; ?>
          <h1 class="text-lg font-extrabold text-slate-900 tracking-tight truncate"><?php echo htmlspecialchars($schoolName); ?></h1>
          <span class="shrink-0 text-[10px] font-bold text-[#15A24A] bg-green-50 border border-green-100 px-2 py-0.5 rounded-full">
            <?php echo count($courses); ?> course<?php echo count($courses) !== 1 ? 's' : ''; ?>
          </span>
        </div>
      </div>

      <!-- Share buttons -->
      <div class="flex items-center gap-2 shrink-0 flex-wrap">
        <!-- WhatsApp -->
        <a href="<?php echo htmlspecialchars($whatsappShareUrl); ?>" target="_blank" rel="noopener"
           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-[#25D366] hover:bg-[#20ba59] text-white text-[11px] font-bold transition-all shadow-sm">
          <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zM12 2C6.477 2 2 6.477 2 12c0 1.891.526 3.66 1.441 5.174L2 22l4.981-1.408C8.436 21.498 10.17 22 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18.2c-1.63 0-3.15-.494-4.431-1.34l-.317-.208-2.964.838.835-2.887-.225-.333A8.17 8.17 0 013.8 12c0-4.521 3.679-8.2 8.2-8.2s8.2 3.679 8.2 8.2-3.679 8.2-8.2 8.2z"/></svg>
          <span>WhatsApp</span>
        </a>

        <!-- Copy link -->
        <button type="button" id="copy-school-link-btn" data-url="<?php echo htmlspecialchars($og_url); ?>"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-white border border-slate-200 hover:border-[#15A24A] hover:text-[#15A24A] text-slate-600 text-[11px] font-bold transition-all shadow-sm">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
          <span id="copy-btn-text">Copy Link</span>
        </button>

        <!-- Share via native (mobile) -->
        <button type="button" id="native-share-btn"
                data-title="<?php echo htmlspecialchars($schoolName); ?>"
                data-text="<?php echo htmlspecialchars($page_description); ?>"
                data-url="<?php echo htmlspecialchars($og_url); ?>"
                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-slate-900 hover:bg-slate-700 text-white text-[11px] font-bold transition-all shadow-sm"
                style="display:none;">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
          <span>Share</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Courses Section -->
<section class="py-10 bg-slate-50 min-h-[400px]">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">

    <?php if (empty($courses)): ?>
      <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center max-w-xl mx-auto shadow-sm">
        <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        </div>
        <h3 class="text-lg font-extrabold text-slate-800 mb-1">New Programs Enrolling Soon</h3>
        <p class="text-xs text-slate-500 mb-6">We are currently curating new curriculum and cohort schedules for this school. Check our complete course catalog or talk to an advisor.</p>
        <div class="flex flex-wrap items-center justify-center gap-3">
          <a href="<?php echo url('courses'); ?>" class="px-5 py-2.5 bg-[#15A24A] hover:bg-[#128a3e] text-white text-xs font-extrabold uppercase tracking-wider rounded-xl transition-all">
            Browse All Courses
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 items-stretch">
        <?php foreach ($courses as $course): ?>
          <div class="h-full">
            <?php include __DIR__ . '/../components/public/dazu_course_card.php'; ?>
          </div>
        <?php endforeach; unset($course); ?>
      </div>
    <?php endif; ?>

    <!-- Explore Other Schools Navigation -->
    <?php if (!empty($allCategories) && count($allCategories) > 1): ?>
      <div class="mt-16 pt-10 border-t border-slate-200">
        <div class="text-center max-w-xl mx-auto mb-6">
          <h3 class="text-sm font-extrabold text-slate-900 tracking-tight">Explore Other Schools &amp; Faculties</h3>
          <p class="text-xs text-slate-500 mt-1">Discover other specialized career disciplines at Dazu Training Institute</p>
        </div>

        <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-3">
          <a href="<?php echo url('courses'); ?>"
             class="px-4 py-2 rounded-xl text-xs font-bold bg-white border border-slate-200 text-slate-700 hover:border-[#15A24A] hover:text-[#15A24A] shadow-sm transition-all">
            All Courses
          </a>
          <?php foreach ($allCategories as $otherCat): ?>
            <?php
            $isCurrent = (int) $otherCat['id'] === (int) $category['id'];
            $otherSchoolName = function_exists('dazu_format_school_name') ? dazu_format_school_name($otherCat['name']) : 'School of ' . $otherCat['name'];
            ?>
            <a href="<?php echo url('courses/category.php?slug=' . rawurlencode($otherCat['slug'] ?? '')); ?>"
               class="px-4 py-2 rounded-xl text-xs font-bold transition-all <?php echo $isCurrent ? 'bg-[#15A24A] text-white shadow-md shadow-green-200' : 'bg-white border border-slate-200 text-slate-700 hover:border-[#15A24A] hover:text-[#15A24A] shadow-sm'; ?>">
              <?php echo htmlspecialchars($otherSchoolName); ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<script>
(function(){
  // Copy link button
  var copyBtn = document.getElementById('copy-school-link-btn');
  var copyText = document.getElementById('copy-btn-text');
  if (copyBtn && copyText) {
    copyBtn.addEventListener('click', function(){
      var link = copyBtn.getAttribute('data-url') || window.location.href;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(link).then(function(){
          copyText.textContent = 'Copied!';
          copyBtn.classList.add('border-green-300','text-[#15A24A]');
          setTimeout(function(){
            copyText.textContent = 'Copy Link';
            copyBtn.classList.remove('border-green-300','text-[#15A24A]');
          }, 2500);
        }).catch(function(){ prompt('Copy this link:', link); });
      } else {
        prompt('Copy this link:', link);
      }
    });
  }

  // Native share API (mobile)
  var nativeBtn = document.getElementById('native-share-btn');
  if (nativeBtn && navigator.share) {
    nativeBtn.style.display = 'inline-flex';
    nativeBtn.addEventListener('click', function(){
      navigator.share({
        title: nativeBtn.getAttribute('data-title'),
        text:  nativeBtn.getAttribute('data-text'),
        url:   nativeBtn.getAttribute('data-url'),
      }).catch(function(){});
    });
  }
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
