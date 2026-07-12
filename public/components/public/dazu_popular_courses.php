<?php
/**
 * Homepage "Our Courses" — ported from Dazu-frontend Home.tsx
 * Expects $popularCourses (from index.php / dazu_load_popular_courses_section).
 */
$popularCourses = $popularCourses ?? [];
?>
<section class="py-24 bg-white" id="popular-courses">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 text-center">
    <div class="max-w-xl mx-auto space-y-3 mb-16">
      <h2 class="text-3xl font-extrabold tracking-tight text-slate-950 relative inline-block pb-3">
        Our Courses
        <span class="absolute bottom-0 left-1/4 right-1/4 h-[3px] bg-[#15A24A] rounded-full"></span>
      </h2>
      <p class="text-slate-400 text-xs font-semibold uppercase tracking-widest pt-2">
        Explore Our Top Technology Certification Tracks
      </p>
    </div>

    <?php if (empty($popularCourses)): ?>
      <p class="text-slate-500 text-sm font-semibold py-8">No courses available yet. Add courses in the admin panel.</p>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 text-left items-start">
        <?php foreach ($popularCourses as $course): ?>
          <?php include __DIR__ . '/dazu_course_card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

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
  </div>
</section>
