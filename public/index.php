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
?>
<section class="relative bg-slate-950 text-white overflow-hidden">
  <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-950 to-emerald-950 opacity-90"></div>
  <div class="relative max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 py-20 md:py-28 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
    <div class="space-y-6">
      <span class="inline-block text-green-400 text-[10px] uppercase tracking-widest font-extrabold px-3 py-1 bg-green-500/10 border border-green-500/30 rounded-full">KICD-Aligned · Thika Campus</span>
      <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold tracking-tight leading-tight">
        Build real tech skills at <span class="text-[#22C55E]">Dazu hub</span> Institute
      </h1>
      <p class="text-slate-300 text-lg leading-relaxed max-w-xl">
        Project-based certification tracks in software, cybersecurity, data analytics and AI. Study physically at Witeithie House, Room 529 — or online.
      </p>
      <div class="flex flex-wrap gap-4">
        <a href="<?php echo url('auth/student_register.php'); ?>" class="px-8 py-3.5 text-xs font-extrabold uppercase tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-xl">Apply Now</a>
        <a href="<?php echo url('courses/index.php'); ?>" class="px-8 py-3.5 text-xs font-extrabold uppercase tracking-widest text-white border border-slate-600 hover:border-green-500 rounded-xl">View Courses</a>
      </div>
    </div>
    <div class="relative hidden lg:block">
      <img src="<?php echo asset('app/hero_student_1783619555973-Dfwss1g9.jpg'); ?>" alt="Student at Dazu hub Institute" class="rounded-2xl shadow-2xl border border-slate-700 w-full h-[420px] object-cover" onerror="this.src='https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=800&q=80'">
    </div>
  </div>
</section>

<section class="py-16 bg-white border-b border-slate-100">
  <div class="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
      <div><div class="text-3xl font-extrabold text-slate-900">500+</div><div class="text-xs uppercase tracking-widest text-slate-500 font-bold mt-1">Graduates</div></div>
      <div><div class="text-3xl font-extrabold text-slate-900">10+</div><div class="text-xs uppercase tracking-widest text-slate-500 font-bold mt-1">Course Tracks</div></div>
      <div><div class="text-3xl font-extrabold text-slate-900">95%</div><div class="text-xs uppercase tracking-widest text-slate-500 font-bold mt-1">Placement Rate</div></div>
      <div><div class="text-3xl font-extrabold text-slate-900">4.8★</div><div class="text-xs uppercase tracking-widest text-slate-500 font-bold mt-1">Student Rating</div></div>
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
