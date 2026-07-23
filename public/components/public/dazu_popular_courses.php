<?php
/**
 * Homepage "Our Courses" — filter bar + filtered course grid.
 * Expects $popularCategories and $popularCourses.
 */
require_once __DIR__ . '/dazu_course_filter_helpers.php';

$popularCategories = $popularCategories ?? [];
$popularCourses = $popularCourses ?? [];

foreach ($popularCourses as &$course) {
    $course['_display_category_id'] = $course['_display_category_id'] ?? dazu_resolve_course_category_id($course, $popularCategories ?: null);
}
unset($course);

// Keep the card chip consistent with the tab the card is filed under.
$categoryNamesById = [];
foreach ($popularCategories as $cat) {
    $categoryNamesById[(int) $cat['id']] = (string) $cat['name'];
}
foreach ($popularCourses as &$course) {
    $resolvedId = (int) ($course['_display_category_id'] ?? 0);
    if (isset($categoryNamesById[$resolvedId])) {
        $course['category_name'] = $categoryNamesById[$resolvedId];
    }
    $course['_search_blob'] = dazu_course_search_blob($course);
}
unset($course);

$activeCategoryId = 0;                        // 0 = "All courses"
$totalCourseCount = count($popularCourses);
$visibleCount = $totalCourseCount;
$tabsId = 'dazu-home-course-cat-tabs';
$countId = 'dazu-home-courses-count';
$emptyId = 'dazu-home-courses-empty';
$showSearch = false;   // the homepage section is a showcase, not a search page
?>
<section class="py-24 bg-white" id="popular-courses">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="max-w-xl mx-auto space-y-3 mb-10 text-center">
      <h2 class="text-3xl font-extrabold tracking-tight text-slate-950 relative inline-block pb-3">
        Our Courses
        <span class="absolute bottom-0 left-1/4 right-1/4 h-[3px] bg-[#15A24A] rounded-full"></span>
      </h2>
      <p class="text-slate-400 text-xs font-semibold uppercase tracking-widest pt-2">
        Explore Our Top Technology Certification Tracks
      </p>
    </div>

    <?php if (!empty($popularCategories)): ?>
      <?php
      $courseCategories = $popularCategories;
      $gridId = 'dazu-home-courses-grid';
      include __DIR__ . '/dazu_course_category_cards.php';
      ?>
    <?php endif; ?>

    <?php if (empty($popularCourses)): ?>
      <p class="text-slate-500 text-sm font-semibold py-8 text-center">No courses available yet. Add courses in the admin panel.</p>
    <?php else: ?>
      <div id="dazu-home-courses-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 text-left items-start mt-8">
        <?php foreach ($popularCourses as $course):
            $catId = (int) ($course['_display_category_id'] ?? 0);
            // All (0) hides nothing, so the page is correct before JS runs.
            $hidden = ($activeCategoryId !== 0 && $catId !== $activeCategoryId) ? 'dazu-filter-hidden' : '';
        ?>
          <div class="dazu-course-list-item <?php echo $hidden; ?>"
               data-category-id="<?php echo $catId; ?>"
               data-search="<?php echo htmlspecialchars($course['_search_blob'] ?? '', ENT_QUOTES); ?>">
            <?php include __DIR__ . '/dazu_course_card.php'; ?>
          </div>
        <?php endforeach; unset($course); ?>
      </div>

      <div id="dazu-home-courses-empty" class="dazu-course-empty" hidden>
        <h3>No tracks in this category</h3>
        <p data-empty-message>Nothing here yet.</p>
        <button type="button" data-empty-reset>Show all</button>
      </div>
    <?php endif; ?>

    <div class="text-center mt-10">
      <a href="<?php echo url('courses/index.php'); ?>"
         class="inline-flex items-center justify-center gap-2 rounded-full px-8 py-4 bg-[#15A24A] hover:bg-[#128a3e] text-white text-xs font-extrabold uppercase tracking-widest shadow-lg shadow-green-200 transition-all hover:-translate-y-0.5 focus:outline-none focus:ring-4 focus:ring-green-100">
        View All Courses <span aria-hidden="true">&rarr;</span>
      </a>
    </div>

    <div class="mt-16 pt-12 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 text-left">
      <?php
      $features = [
          ['icon' => 'briefcase', 'title' => 'Hands-on Projects', 'text' => 'Build real-world projects.'],
          ['icon' => 'graduation', 'title' => 'Industry Recognized', 'text' => 'Get certified and boost your career.'],
          ['icon' => 'users', 'title' => 'Expert Mentors', 'text' => 'Learn from industry professionals.'],
          ['icon' => 'clock', 'title' => 'Flexible Learning', 'text' => 'Study at your own pace.'],
      ];
      foreach ($features as $f): ?>
        <div class="flex items-start gap-4">
          <div class="p-2.5 bg-green-50 text-[#15A24A] rounded-xl shrink-0 border border-green-100">
            <?php if ($f['icon'] === 'briefcase'): ?>
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <?php elseif ($f['icon'] === 'graduation'): ?>
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
            <?php elseif ($f['icon'] === 'users'): ?>
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <?php else: ?>
              <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php endif; ?>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-950 text-sm"><?php echo htmlspecialchars($f['title']); ?></h4>
            <p class="text-slate-500 text-xs mt-1 font-medium leading-normal"><?php echo htmlspecialchars($f['text']); ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="hidden" aria-hidden="true">
      <a href="<?php echo url('courses/index.php'); ?>"
         class="inline-flex items-center justify-center px-10 py-3.5 bg-[#15A24A] hover:bg-[#128a3e] text-white text-xs font-extrabold uppercase tracking-widest transition-colors">
        View All Courses →
      </a>
    </div>
  </div>
</section>
