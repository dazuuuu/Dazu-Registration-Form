<?php
$page_title = 'Our Technology Courses — Dazu hub Institute';
$page_description = 'Browse software, cybersecurity, data analytics and AI courses at Dazu hub Institute, Thika Town.';

require_once __DIR__ . '/../../app/v1/init.php';
require_once __DIR__ . '/../../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../../app/v1/helpers/uploads.php';
require_once __DIR__ . '/../../app/v1/helpers/course_share.php';
require_once __DIR__ . '/../../app/v1/models/Connection.php';
require_once __DIR__ . '/../../app/v1/models/CourseModel.php';

use Models\Connection;
use Models\CourseModel;

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
[$courses, $usingStatic] = dazu_load_courses($courseModel);
$courses = array_values(array_filter($courses, static fn($c) => !empty($c['is_active']) || $usingStatic));

ob_start();
?>
<div class="bg-white min-h-screen py-16">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="max-w-2xl mb-12">
      <span class="text-green-600 text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-200 rounded-full">Practical Certifications</span>
      <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900 mt-4">Our Technology Courses</h1>
      <p class="text-slate-600 font-medium leading-relaxed mt-3">Lab-driven certification tracks. Study physically at Thika Town or online.</p>
    </div>

    <?php if (empty($courses)): ?>
      <div class="text-center py-24 border border-dashed border-slate-200 rounded-2xl bg-slate-50">
        <h3 class="text-lg font-bold text-slate-900">No courses available</h3>
        <p class="text-slate-500 text-sm mt-1">Check back soon or contact us for upcoming intakes.</p>
      </div>
    <?php else: ?>
      <p class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-8">Showing <?php echo count($courses); ?> course<?php echo count($courses) === 1 ? '' : 's'; ?></p>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($courses as $course): ?>
          <?php
            $id = (int) ($course['id'] ?? 0);
            $img = dazu_course_image_url($course);
            $category = dazu_course_category($course['abbreviation'] ?? '');
            $rating = htmlspecialchars($course['rating'] ?? '4.8');
            $viewUrl = $id > 0 ? url('courses/view.php?id=' . $id) : url('courses/index.php');
          ?>
          <article class="group bg-white border border-slate-200 rounded-2xl overflow-hidden flex flex-col hover:border-green-600 hover:-translate-y-1.5 transition-all shadow-sm hover:shadow-md">
            <div class="relative h-48 bg-slate-100 overflow-hidden">
              <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" referrerpolicy="no-referrer">
              <span class="absolute top-3 left-3 text-[10px] font-extrabold text-white bg-green-600 px-2.5 py-1 rounded-full uppercase"><?php echo htmlspecialchars($category); ?></span>
            </div>
            <div class="p-6 flex-1 flex flex-col justify-between space-y-4">
              <div class="space-y-3">
                <div class="flex items-center gap-1.5 text-xs">
                  <span class="font-extrabold text-amber-500">★</span>
                  <span class="font-extrabold text-slate-800"><?php echo $rating; ?></span>
                  <span class="text-slate-300">•</span>
                  <span class="text-slate-500 font-semibold"><?php echo (int)($course['duration_months'] ?? 0); ?> months</span>
                </div>
                <h2 class="text-lg font-extrabold text-slate-900 leading-snug"><?php echo htmlspecialchars($course['title']); ?></h2>
                <p class="text-sm text-slate-600 line-clamp-3"><?php echo htmlspecialchars($course['overview'] ?? $course['description'] ?? ''); ?></p>
              </div>
              <div class="flex items-center justify-between pt-2 border-t border-slate-100">
                <span class="text-sm font-extrabold text-green-600">KES <?php echo number_format((float)($course['course_fee'] ?? 0)); ?></span>
                <div class="flex gap-2">
                  <a href="<?php echo $viewUrl; ?>" class="px-4 py-2 text-[10px] font-extrabold uppercase tracking-wider text-slate-700 border border-slate-200 rounded-lg hover:border-green-600">Details</a>
                  <a href="<?php echo url('auth/student_register.php'); ?>?course_id=<?php echo $id; ?>" class="px-4 py-2 text-[10px] font-extrabold uppercase tracking-wider text-white bg-green-600 hover:bg-green-700 rounded-lg">Apply</a>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../templates/public/dazu_layout.php';
