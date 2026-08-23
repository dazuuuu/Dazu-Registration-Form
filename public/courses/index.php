<?php
$page_title = 'Our Technology Courses — Dazu hub Institute';
$page_description = 'Browse software, cybersecurity, data analytics and AI courses at Dazu hub Institute, Thika Town.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';
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

foreach ($courses as &$course) {
    $course['_display_category_id'] = dazu_resolve_course_category_id($course, $courseCategories);
}
unset($course);

// Keep the chip on the card consistent with the tab the card is filed under.
// CourseModel joins category_name off courses.category_id, which is NULL on some
// rows; dazu_resolve_course_category_id() then files those by abbreviation, so
// the chip and the tab disagree without this.
$categoryNamesById = [];
foreach ($courseCategories as $cat) {
    $categoryNamesById[(int) $cat['id']] = (string) $cat['name'];
}
foreach ($courses as &$course) {
    $resolvedId = (int) ($course['_display_category_id'] ?? 0);
    if (isset($categoryNamesById[$resolvedId])) {
        $course['category_name'] = $categoryNamesById[$resolvedId];
    }
    $course['_search_blob'] = dazu_course_search_blob($course);
}
unset($course);

$courseCategories = dazu_recount_course_categories($courseCategories, $courses);

$totalCourseCount = count($courses);

// 0 = "All courses", the default. ?category=3 deep-links to that tab.
// Validated here and not only inside the tabs component, because that component
// returns early when there is nothing worth rendering — an unclamped id would
// then hide every card with no tab left to undo it.
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
      <span class="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-100 rounded-full">Practical Certifications</span>
      <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900 mt-4">Our Technology Courses</h1>
      <p class="text-slate-600 font-medium leading-relaxed mt-3">Every track we run is listed below. Search or filter by category, then open a course to view details and apply online.</p>
    </div>

    <?php if (!empty($courseCategories)): ?>
      <?php
      $tabsId = 'dazu-courses-page-cat-tabs';
      $countId = 'dazu-courses-count';
      $gridId = 'dazu-courses-grid';
      $emptyId = 'dazu-courses-empty';
      $searchId = 'dazu-courses-search';
      $showSearch = true;
      $urlSync = true;
      include __DIR__ . '/../components/public/dazu_course_category_cards.php';
      ?>
    <?php endif; ?>

    <?php if (empty($courses)): ?>
      <div class="text-center py-24 border border-dashed border-slate-200 rounded-2xl bg-slate-50">
        <h3 class="text-lg font-bold text-slate-900">No courses available</h3>
        <p class="text-slate-500 text-sm mt-1">Add courses in the admin panel or check back soon.</p>
      </div>
    <?php else: ?>
      <div id="dazu-courses-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 items-start">
        <?php foreach ($courses as $course):
            $catId = (int) ($course['_display_category_id'] ?? 0);
            $hidden = ($activeCategoryId !== 0 && $catId !== $activeCategoryId) ? 'dazu-filter-hidden' : '';
            $courseCardVariant = 'listing';
        ?>
          <div class="dazu-course-list-item <?php echo $hidden; ?>"
               data-category-id="<?php echo $catId; ?>"
               data-search="<?php echo htmlspecialchars($course['_search_blob'] ?? '', ENT_QUOTES); ?>">
            <?php include __DIR__ . '/../components/public/dazu_course_card.php'; ?>
          </div>
        <?php endforeach; unset($course); ?>
      </div>

      <div id="dazu-courses-empty" class="dazu-course-empty" hidden>
        <h3>No matching tracks</h3>
        <p data-empty-message>Nothing here yet.</p>
        <button type="button" data-empty-reset>Clear filters</button>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
foreach ($courses as $ogCourse) {
    $img = trim((string) ($ogCourse['cover_image'] ?? $ogCourse['card_image'] ?? ''));
    if ($img !== '' && $img !== 'null') {
        $og_image = dazu_course_image_url($ogCourse);
        break;
    }
}
require __DIR__ . '/../templates/public/dazu_layout.php';