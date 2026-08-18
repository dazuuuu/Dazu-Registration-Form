<?php
// public/courses/free.php — same layout as courses/index.php, filtered to is_free courses.
$page_title = 'Free Courses — Dazu hub Institute';
$page_description = 'Free technology courses at Dazu hub Institute — no payment required, just apply.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../app/v1/helpers/course_catalog.php';
require_once __DIR__ . '/../../app/v1/helpers/free_courses.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../app/v1/models/CourseCategoryModel.php';
require_once __DIR__ . '/../components/public/dazu_course_filter_helpers.php';

use Models\Connection;
use Models\CourseModel;
use Models\CourseCategoryModel;

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
$categoryModel = new CourseCategoryModel($pdo);

// Admin closed the section from Admin → Free Courses — same switch that hides the
// nav link (dazu_free_courses_enabled()); a direct link here shows a closed notice
// instead of the listing rather than a broken/empty grid.
$sectionEnabled = dazu_free_courses_enabled();

$courses = $sectionEnabled ? array_map('course_catalog_enrich', $courseModel->getFree(true)) : [];

try {
    $courseCategories = $categoryModel->getAll(true);
} catch (Throwable $e) {
    $courseCategories = [];
}

[$courseCategories, $courses] = dazu_prepare_public_course_listing($courseCategories, $courses);

foreach ($courses as &$course) {
    $course['_search_blob'] = dazu_course_search_blob($course);
}
unset($course);

$totalCourseCount = count($courses);

$activeCategoryId = (int) ($_GET['category'] ?? 0);
$activeCategoryIsValid = $activeCategoryId !== 0 && in_array(
    $activeCategoryId,
    array_map(static fn($c) => (int) $c['id'], $courseCategories),
    true
);
if (!$activeCategoryIsValid) {
    $activeCategoryId = 0;
}

$visibleCount = $activeCategoryId === 0
    ? $totalCourseCount
    : count(array_filter($courses, static fn($c) => (int)($c['_display_category_id'] ?? 0) === $activeCategoryId));

ob_start();
?>
<div class="bg-white min-h-screen py-16">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 space-y-10">
    <div class="max-w-2xl">
      <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-100 rounded-full">100% Free</span>
      <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900 mt-4">Free Courses</h1>
      <p class="text-slate-600 font-medium leading-relaxed mt-3">No payment required — tell us why you'd be a great fit and apply for free. Search or filter by category, then open a course to apply.</p>
    </div>

    <?php if (!$sectionEnabled): ?>
      <div class="text-center py-24 border border-dashed border-slate-200 rounded-2xl bg-slate-50">
        <h3 class="text-lg font-bold text-slate-900">Free courses are currently closed</h3>
        <p class="text-slate-500 text-sm mt-1">We're not accepting free-course applications right now. Check back soon, or browse our <a href="<?php echo url('courses/index.php'); ?>" class="text-green-600 font-bold">full course list</a>.</p>
      </div>
    <?php else: ?>

    <?php if (!empty($courseCategories)): ?>
      <?php
      $tabsId = 'dazu-free-courses-cat-tabs';
      $countId = 'dazu-free-courses-count';
      $gridId = 'dazu-free-courses-grid';
      $emptyId = 'dazu-free-courses-empty';
      $searchId = 'dazu-free-courses-search';
      $showSearch = true;
      $urlSync = true;
      include __DIR__ . '/../components/public/dazu_course_category_cards.php';
      ?>
    <?php endif; ?>

    <?php if (empty($courses)): ?>
      <div class="text-center py-24 border border-dashed border-slate-200 rounded-2xl bg-slate-50">
        <h3 class="text-lg font-bold text-slate-900">No free courses available right now</h3>
        <p class="text-slate-500 text-sm mt-1">Check back soon, or browse our <a href="<?php echo url('courses/index.php'); ?>" class="text-green-600 font-bold">full course list</a>.</p>
      </div>
    <?php else: ?>
      <div id="dazu-free-courses-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 items-start">
        <?php foreach ($courses as $course):
            $catId = (int) ($course['_display_category_id'] ?? 0);
            $hidden = ($activeCategoryId !== 0 && $catId !== $activeCategoryId) ? 'dazu-filter-hidden' : '';
        ?>
          <div class="dazu-course-list-item <?php echo $hidden; ?>"
               data-category-id="<?php echo $catId; ?>"
               data-search="<?php echo htmlspecialchars($course['_search_blob'] ?? '', ENT_QUOTES); ?>">
            <?php include __DIR__ . '/../components/public/dazu_course_card.php'; ?>
          </div>
        <?php endforeach; unset($course); ?>
      </div>

      <div id="dazu-free-courses-empty" class="dazu-course-empty" hidden>
        <h3>No matching tracks</h3>
        <p data-empty-message>Nothing here yet.</p>
        <button type="button" data-empty-reset>Clear filters</button>
      </div>
    <?php endif; ?>

    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
