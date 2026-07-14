<?php
$page_title = 'Our Technology Courses — Dazu hub Institute';
$page_description = 'Browse software, cybersecurity, data analytics and AI courses at Dazu hub Institute, Thika Town.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';
require_once __DIR__ . '/../../app/v1/models/CourseCategoryModel.php';

use Models\Connection;
use Models\CourseModel;
use Models\CourseCategoryModel;

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
$categoryModel = new CourseCategoryModel($pdo);

[$courses, $usingStatic] = dazu_load_courses($courseModel);
$courses = array_values(array_filter($courses, static function ($c) use ($usingStatic) {
    if ($usingStatic) {
        return true;
    }
    return !empty($c['is_active']) && (int) ($c['id'] ?? 0) > 0;
}));

try {
    $courseCategories = $categoryModel->getAll(true);
} catch (Throwable $e) {
    $courseCategories = [];
}

if (empty($courseCategories)) {
    $courseCategories = dazu_fallback_course_categories();
}

$listing = dazu_prepare_course_listing($courseCategories, $courses);
$courseCategories = $listing['categories'];
$courses = $listing['courses'];

$activeCategoryId = 0;
$visibleCount = count($courses);

ob_start();
?>
<div class="bg-white min-h-screen py-16">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 space-y-10">
    <div class="max-w-2xl">
      <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-100 rounded-full">Practical Certifications</span>
      <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900 mt-4">Our Technology Courses</h1>
      <p class="text-slate-600 font-medium leading-relaxed mt-3">Pick a category below, then choose a course to view details or apply online.</p>
    </div>

    <?php if (!empty($courseCategories) || !empty($courses)): ?>
      <?php
      $tabsId = 'dazu-courses-page-cat-tabs';
      $countId = 'dazu-courses-count';
      $gridId = 'dazu-courses-grid';
      $totalCourseCount = count($courses);
      $showAllTab = true;
      include __DIR__ . '/../components/public/dazu_course_category_cards.php';
      ?>
    <?php endif; ?>

    <?php if (empty($courses)): ?>
      <div class="text-center py-24 border border-dashed border-slate-200 rounded-2xl bg-slate-50">
        <h3 class="text-lg font-bold text-slate-900">No courses available</h3>
        <p class="text-slate-500 text-sm mt-1">Add courses in the admin panel or check back soon.</p>
      </div>
    <?php else: ?>
      <p class="text-xs font-extrabold uppercase tracking-widest text-slate-600">
        Showing <span id="dazu-courses-count"><?php echo $visibleCount; ?></span> course<?php echo $visibleCount === 1 ? '' : 's'; ?>
      </p>
      <div id="dazu-courses-grid" class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4 2xl:grid-cols-5 gap-4 sm:gap-6 items-start">
        <?php foreach ($courses as $course):
            $catId = (int) ($course['_display_category_id'] ?? 0);
            $hidden = ($activeCategoryId > 0 && $catId !== $activeCategoryId) ? 'dazu-filter-hidden' : '';
        ?>
          <div class="dazu-course-list-item <?php echo $hidden; ?>" data-category-id="<?php echo $catId; ?>">
            <?php include __DIR__ . '/../components/public/dazu_course_card.php'; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
