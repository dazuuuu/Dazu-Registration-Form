<?php
$page_title = 'Dazu hub Institute — Practical Tech Training in Thika';
$page_description = 'Hands-on software development, cybersecurity, data analytics and AI courses at Dazu hub Institute, Thika Town.';

require_once __DIR__ . '/../app/v1/init.php';
require_once __DIR__ . '/../app/v1/helpers/dazu_theme.php';
require_once __DIR__ . '/../app/v1/models/Connection.php';
require_once __DIR__ . '/../app/v1/models/CourseModel.php';

use Models\Connection;
use Models\CourseModel;

$pdo = Connection::getInstance()->getConnection();
$courseModel = new CourseModel($pdo);
[$displayCourses] = dazu_load_courses($courseModel);
$featured = array_slice(array_filter($displayCourses, static fn($c) => !empty($c['is_active']) || (int)($c['id'] ?? 0) === 0), 0, 4);

ob_start();
include __DIR__ . '/components/public/dazu_hero.php';
?>

<section class="bg-slate-50 border-y border-slate-100 py-12">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
      <div class="space-y-1"><span class="block text-4xl font-extrabold text-slate-950">1200+</span><span class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Students Trained</span></div>
      <div class="space-y-1"><span class="block text-4xl font-extrabold text-slate-950">14+</span><span class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Practical Courses</span></div>
      <div class="space-y-1"><span class="block text-4xl font-extrabold text-slate-950">95%</span><span class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Placement Rate</span></div>
      <div class="space-y-1"><span class="block text-4xl font-extrabold text-slate-950">4.8★</span><span class="text-[10px] uppercase tracking-widest font-bold text-slate-400">Student Rating</span></div>
    </div>
  </div>
</section>

<section class="py-20 bg-slate-50">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="max-w-2xl mb-12">
      <span class="text-green-600 text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-200 rounded-full">Our Programs</span>
      <h2 class="text-3xl md:text-4xl font-extrabold text-slate-900 mt-4">Featured Technology Courses</h2>
      <p class="text-slate-600 mt-3">Lab-driven tracks with flexible M-Pesa payment plans. Physical classes in Thika Town.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
      <?php foreach ($featured as $course): ?>
        <?php
          $id = (int) ($course['id'] ?? 0);
          $img = dazu_course_image_url($course);
          $category = dazu_course_category($course['abbreviation'] ?? '');
          $viewUrl = $id > 0 ? url('courses/view.php?id=' . $id) : url('courses/index.php');
        ?>
        <article class="group bg-white border border-slate-200 rounded-2xl overflow-hidden hover:border-green-600 hover:-translate-y-1 transition-all shadow-sm hover:shadow-md">
          <div class="relative h-44 overflow-hidden bg-slate-100">
            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" referrerpolicy="no-referrer">
            <span class="absolute top-3 left-3 text-[10px] font-extrabold text-white bg-green-600 px-2.5 py-1 rounded-full uppercase"><?php echo htmlspecialchars($category); ?></span>
          </div>
          <div class="p-5 space-y-3">
            <h3 class="font-extrabold text-slate-900 leading-snug"><?php echo htmlspecialchars($course['title']); ?></h3>
            <p class="text-xs text-slate-500 font-semibold"><?php echo (int)($course['duration_months'] ?? 0); ?> months · KES <?php echo number_format((float)($course['course_fee'] ?? 0)); ?></p>
            <p class="text-sm text-slate-600 line-clamp-2"><?php echo htmlspecialchars($course['overview'] ?? ''); ?></p>
            <a href="<?php echo $viewUrl; ?>" class="inline-flex items-center gap-1 text-xs font-extrabold uppercase tracking-wider text-green-600 hover:text-green-700">View course →</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="text-center mt-12">
      <a href="<?php echo url('courses/index.php'); ?>" class="inline-block px-8 py-3.5 text-xs font-extrabold uppercase tracking-widest text-white bg-slate-900 hover:bg-slate-800 rounded-xl">Browse All Courses</a>
    </div>
  </div>
</section>

<section class="py-20 bg-white">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 text-center">
    <h2 class="text-3xl font-extrabold text-slate-900 mb-4">Ready to start your tech career?</h2>
    <p class="text-slate-600 max-w-xl mx-auto mb-8">Join hundreds of students building portfolios and landing jobs through hands-on training at Dazu hub Institute.</p>
    <a href="<?php echo url('auth/student_register.php'); ?>" class="inline-block px-10 py-4 text-xs font-extrabold uppercase tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-xl">Start Application</a>
  </div>
</section>
<?php
$content = ob_get_clean();
require __DIR__ . '/templates/public/dazu_layout.php';
