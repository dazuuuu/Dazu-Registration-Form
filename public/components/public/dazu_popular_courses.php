<?php
/**
 * Homepage "Our Courses" — category cards + filtered course grid.
 * Expects $popularCategories and $popularCourses.
 */
$popularCategories = $popularCategories ?? [];
$popularCourses = $popularCourses ?? [];

$listing = dazu_prepare_course_listing($popularCategories, $popularCourses);
$popularCategories = $listing['categories'];
$popularCourses = $listing['courses'];

$activeCategoryId = 0;
$visibleCount = count($popularCourses);
$tabsId = 'dazu-home-course-cat-tabs';
$countId = 'dazu-home-courses-count';
$totalCourseCount = count($popularCourses);
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

    <div class="dazu-courses-listing">
      <?php if (!empty($popularCategories) || !empty($popularCourses)): ?>
        <?php
        $courseCategories = $popularCategories;
        $gridId = 'dazu-home-courses-grid';
        $showAllTab = true;
        include __DIR__ . '/dazu_course_category_cards.php';
        ?>
      <?php endif; ?>

      <?php if (empty($popularCourses)): ?>
        <p class="text-slate-500 text-sm font-semibold py-8 text-center">No courses available yet. Add courses in the admin panel.</p>
      <?php else: ?>
        <p class="text-xs font-extrabold uppercase tracking-widest text-slate-600 mt-8 mb-6">
          Showing <span id="<?php echo $countId; ?>"><?php echo $visibleCount; ?></span> course<?php echo $visibleCount === 1 ? '' : 's'; ?>
        </p>
        <div id="dazu-home-courses-grid" class="dazu-courses-grid grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4 2xl:grid-cols-5 gap-4 sm:gap-6 text-left items-start">
          <?php foreach ($popularCourses as $course):
              $catId = (int) ($course['_display_category_id'] ?? 0);
              $hidden = ($activeCategoryId > 0 && $catId !== $activeCategoryId) ? 'dazu-filter-hidden' : '';
          ?>
            <div class="dazu-course-list-item <?php echo $hidden; ?>" data-category-id="<?php echo $catId; ?>">
              <?php include __DIR__ . '/dazu_course_card.php'; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="mt-20 pt-12 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 text-left">
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

    <div class="text-center pt-10">
      <a href="<?php echo url('courses/index.php'); ?>"
         class="inline-flex items-center justify-center px-10 py-3.5 bg-[#15A24A] hover:bg-[#128a3e] text-white text-xs font-extrabold uppercase tracking-widest transition-colors">
        View All Courses →
      </a>
    </div>
  </div>
</section>
